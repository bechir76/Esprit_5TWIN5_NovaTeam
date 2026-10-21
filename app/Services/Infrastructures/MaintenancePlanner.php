<?php

namespace App\Services\Infrastructures;

use App\Enums\IncidentStatus;
use App\Enums\InfrastructureStatus;
use App\Models\Infrastructure;
use App\Models\Zone;
use App\Services\Ai\InfrastructureAiService;

/**
 * Plan de maintenance d'une zone : le code sélectionne et trie les ouvrages (score de risque, retard d'inspection,
 * incidents ouverts, état critique) ; l'IA rédige les actions et leur justification (repli : règles).
 * Le plan est enregistré sur la zone (maintenance_plan) pour l'afficher et l'exporter en PDF.
 */
class MaintenancePlanner
{
    public function __construct(private InfrastructureAiService $ai) {}

    /**
     * Ouvrages prioritaires de la zone, du plus au moins urgent (au plus config('infrastructures.maintenance_plan_size')).
     *
     * @return list<array<string, mixed>>
     */
    public function candidates(Zone $zone): array
    {
        $since = now()->subMonths(12);

        return $zone->infrastructures()
            ->withCount([
                'incidents as open_incidents' => fn ($q) => $q->whereIn('status', array_column(IncidentStatus::openCases(), 'value')),
                'incidents as recent_incidents' => fn ($q) => $q->where('created_at', '>=', $since),
            ])
            ->get()
            ->map(fn (Infrastructure $i) => [
                'id' => $i->id,
                'code' => $i->code,
                'name' => $i->name,
                'type' => $i->type->label(),
                'status' => $i->status->label(),
                'risk_score' => $i->risk_score,
                'risk_level' => $i->risk_level?->label(),
                'days_overdue' => $i->inspectionDaysOverdue(),
                'open_incidents' => (int) $i->open_incidents,
                'recent_incidents' => (int) $i->recent_incidents,
                'main_factors' => collect($i->risk_factors ?? [])->sortByDesc('points')->filter(fn ($f) => $f['points'] > 0)->take(2)->pluck('label')->values()->all(),
                'recommendation' => $i->risk_recommendations[0] ?? null,
                // Priorité : risque (0-100) + retard (jusqu'à 30) + incidents ouverts (5 chacun, jusqu'à 25) + 10 si état critique
                'priority' => (int) round(($i->risk_score ?? 0)
                    + min(30, $i->inspectionDaysOverdue() / 6)
                    + min(25, 5 * (int) $i->open_incidents)
                    + ($i->status === InfrastructureStatus::Critique ? 10 : 0)),
            ])
            ->sortBy([['priority', 'desc'], ['code', 'asc']])
            ->take((int) config('infrastructures.maintenance_plan_size', 5))
            ->values()
            ->map(fn (array $item, int $k) => ['rank' => $k + 1] + $item)
            ->all();
    }

    /**
     * Génère et enregistre le plan de la zone.
     *
     * @return array{summary: string, actions: list<array<string, mixed>>, source: string}
     */
    public function generate(Zone $zone): array
    {
        $plan = $this->ai->maintenancePlan($zone, $this->candidates($zone));

        $zone->forceFill([
            'maintenance_plan' => $plan,
            'maintenance_plan_at' => now(),
            'maintenance_plan_source' => $plan['source'],
        ])->save();

        return $plan;
    }
}
