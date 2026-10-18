<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Projets recommandés à un citoyen connecté (lecture seule sur ses incidents) : d'abord ceux qui réparent
 * un ouvrage qu'il a signalé, puis ceux de la zone de ses signalements, sinon les plus prioritaires (score B1).
 * L'explication est un texte gabarit : aucune donnée personnelle n'est envoyée au LLM.
 */
class ProjectRecommender
{
    /** @return Collection<int, array{project: Project, reason: string}> */
    public function for(User $user, int $limit = 3): Collection
    {
        $incidents = Incident::query()->where('user_id', $user->id)->whereNotNull('infrastructure_id')
            ->with('infrastructure.zone')->latest()->limit(20)->get();

        $byInfrastructure = $incidents->keyBy('infrastructure_id');
        $zones = $incidents->pluck('infrastructure.zone')->filter()->keyBy('id');

        $candidates = Project::query()->open()->whereDate('end_date', '>=', today())->withCollected()->with('infrastructure.zone')
            ->orderByDesc('ai_priority_score')->limit(30)->get();

        $recommended = $candidates->map(function (Project $project) use ($byInfrastructure, $zones): array {
            $incident = $byInfrastructure->get($project->infrastructure_id);
            $zone = $project->infrastructure ? $zones->get($project->infrastructure->zone_id) : null;

            return match (true) {
                $incident !== null => ['rank' => 0, 'project' => $project,
                    'reason' => "Ce projet répare l'ouvrage concerné par votre signalement « {$incident->title} »."],
                $zone !== null => ['rank' => 1, 'project' => $project,
                    'reason' => "Ce projet concerne votre zone ({$zone->name}), où vous avez signalé un incident."],
                default => ['rank' => 2, 'project' => $project,
                    'reason' => $project->ai_priority_score !== null
                        ? "Projet prioritaire du réseau (score {$project->ai_priority_score}/100)."
                        : 'Projet ouvert aux dons du réseau du Grand Tunis.'],
            };
        });

        return $recommended->sortBy([['rank', 'asc'], [fn ($a, $b) => ($b['project']->ai_priority_score ?? -1) <=> ($a['project']->ai_priority_score ?? -1)]])
            ->take($limit)
            ->map(fn (array $r) => ['project' => $r['project'], 'reason' => $r['reason']])
            ->values();
    }
}
