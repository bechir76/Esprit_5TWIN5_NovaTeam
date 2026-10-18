<?php

namespace App\Services\Incidents;

use App\Models\Incident;
use App\Models\Infrastructure;
use App\Services\Ai\IncidentAiService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Incidents liés : regroupe les incidents ouverts proches en lieu (~500 m) et en temps (6 h) par union-find,
 * algorithme déterministe (ordre par date puis id). Chaque groupe reçoit la clé « CL-<plus petit id> »
 * et un diagnostic court (IA ou repli), affichés dans le tableau de bord du module.
 */
class IncidentClusterer
{
    public function __construct(private IncidentAiService $ai) {}

    /**
     * Calcule les groupes sans rien enregistrer.
     *
     * @return Collection<string, Collection<int, Incident>> clé du groupe => incidents
     */
    public function groups(): Collection
    {
        $radius = (float) config('incidents.clusters.radius_meters');
        $window = (int) config('incidents.clusters.window_hours') * 60;

        $incidents = Incident::query()->open()->whereNull('duplicate_of_id')
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->orderBy('created_at')->orderBy('id')
            ->get()->values();

        $parent = range(0, $incidents->count() - 1);
        $find = function (int $i) use (&$parent, &$find): int {
            return $parent[$i] === $i ? $i : $parent[$i] = $find($parent[$i]);
        };

        // Paires proches en temps (liste triée : on s'arrête dès que l'écart dépasse la fenêtre) puis en distance
        foreach ($incidents as $i => $a) {
            for ($j = $i + 1; $j < $incidents->count(); $j++) {
                $b = $incidents[$j];

                if ($a->created_at->diffInMinutes($b->created_at) > $window) {
                    break;
                }

                if (Geo::distance($a->latitude, $a->longitude, $b->latitude, $b->longitude) <= $radius) {
                    $parent[$find($j)] = $find($i);
                }
            }
        }

        return $incidents
            ->groupBy(fn (Incident $incident, int $index) => $find($index), preserveKeys: true)
            ->filter(fn (Collection $group) => $group->count() >= (int) config('incidents.clusters.min_size'))
            ->mapWithKeys(fn (Collection $group) => ['CL-'.$group->min('id') => $group->values()])
            ->sortKeys();
    }

    /**
     * Enregistre les clés de groupe et les diagnostics ; les incidents qui ne sont plus groupés sont libérés.
     *
     * @return Collection<string, Collection<int, Incident>>
     */
    public function run(): Collection
    {
        $groups = $this->groups();

        DB::transaction(function () use ($groups): void {
            Incident::query()->whereNotNull('cluster_key')
                ->whereNotIn('id', $groups->flatten()->pluck('id'))
                ->update(['cluster_key' => null, 'cluster_diagnosis' => null]);

            foreach ($groups as $key => $group) {
                // Diagnostic recalculé seulement pour un groupe nouveau ou modifié
                $unchanged = $group->every(fn (Incident $i) => $i->cluster_key === $key && $i->cluster_diagnosis);
                $diagnosis = $unchanged ? $group->first()->cluster_diagnosis
                    : $this->ai->diagnoseCluster($group, $this->nearbyInfrastructures($group))['diagnosis'];

                Incident::query()->whereIn('id', $group->pluck('id'))
                    ->update(['cluster_key' => $key, 'cluster_diagnosis' => $diagnosis]);
            }
        });

        return $groups;
    }

    /**
     * Ouvrages liés au groupe ou à moins de 1 km de son centre (lecture seule), les plus proches d'abord.
     *
     * @param  Collection<int, Incident>  $group
     * @return Collection<int, Infrastructure>
     */
    public function nearbyInfrastructures(Collection $group): Collection
    {
        $lat = (float) $group->avg('latitude');
        $lng = (float) $group->avg('longitude');

        return Infrastructure::query()
            ->whereIn('id', $group->pluck('infrastructure_id')->filter())
            ->orWhere(fn ($q) => $q->whereBetween('latitude', [$lat - 0.01, $lat + 0.01])->whereBetween('longitude', [$lng - 0.012, $lng + 0.012]))
            ->get()
            ->sortBy(fn (Infrastructure $o) => $o->hasCoordinates() ? Geo::distance($lat, $lng, $o->latitude, $o->longitude) : PHP_INT_MAX)
            ->take(5)
            ->values();
    }
}
