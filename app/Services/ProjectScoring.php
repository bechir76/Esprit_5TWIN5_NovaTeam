<?php

namespace App\Services;

use App\Enums\IncidentSeverity;
use App\Enums\InfrastructureStatus;
use App\Models\Incident;
use App\Models\Project;

/**
 * Score de priorité 0-100, calculé par du code déterministe (jamais par le LLM) à partir de données
 * en lecture seule : incidents récents et gravité sur l'ouvrage lié, état de l'ouvrage, population de la zone,
 * budget. Poids et plafonds : config/projects.php (scoring).
 */
class ProjectScoring
{
    private const SEVERITY_POINTS = ['faible' => 1, 'moyenne' => 2, 'elevee' => 3, 'critique' => 4];

    private const STATUS_POINTS = ['bon' => 0.0, 'a_surveiller' => 0.5, 'critique' => 1.0];

    /**
     * Facteurs du score : valeur brute, valeur normalisée (0 à 1), poids et libellé lisible.
     * Aucune donnée personnelle (le LLM ne reçoit que ces facteurs).
     *
     * @return array<string, array{value: float|int|string|null, normalized: float, weight: float, label: string}>
     */
    public function factors(Project $project): array
    {
        $config = config('projects.scoring');
        $infrastructure = $project->infrastructure()->with('zone')->first();
        $window = $config['incident_window_days'];

        $incidents = $infrastructure
            ? Incident::where('infrastructure_id', $infrastructure->id)->where('created_at', '>=', now()->subDays($window))->get(['severity'])
            : collect();

        $count = $incidents->count();
        $severity = $count
            ? $incidents->avg(fn (Incident $i) => self::SEVERITY_POINTS[$i->severity instanceof IncidentSeverity ? $i->severity->value : $i->severity] ?? 1)
            : 0.0;
        $status = $infrastructure?->status instanceof InfrastructureStatus ? $infrastructure->status : null;
        $population = (int) ($infrastructure?->zone?->population_served ?? 0);
        $budget = (float) $project->target_budget;

        $weights = $config['weights'];

        return [
            'incidents' => [
                'value' => $count,
                'normalized' => min(1, $count / $config['incidents_ceiling']),
                'weight' => $weights['incidents'],
                'label' => "{$count} incident(s) sur l'ouvrage en ".round($window / 30).' mois',
            ],
            'severity' => [
                'value' => round($severity, 2),
                'normalized' => $severity / 4,
                'weight' => $weights['severity'],
                'label' => $count ? 'gravité moyenne '.number_format($severity, 1, ',', '').' / 4' : 'aucun incident récent',
            ],
            'infrastructure' => [
                'value' => $status?->value,
                'normalized' => $status ? self::STATUS_POINTS[$status->value] : 0.0,
                'weight' => $weights['infrastructure'],
                'label' => $status ? 'ouvrage « '.mb_strtolower($status->label()).' »' : 'aucun ouvrage rattaché',
            ],
            'population' => [
                'value' => $population,
                'normalized' => min(1, $population / $config['population_ceiling']),
                'weight' => $weights['population'],
                'label' => number_format($population, 0, ',', ' ').' habitants desservis',
            ],
            'cost' => [
                'value' => $budget,
                'normalized' => 1 - min(1, $budget / $config['budget_ceiling']),
                'weight' => $weights['cost'],
                'label' => 'budget de '.Project::money($budget).' DT',
            ],
        ];
    }

    /** @param  array<string, array{normalized: float, weight: float}>|null  $factors */
    public function score(Project $project, ?array $factors = null): int
    {
        $factors ??= $this->factors($project);
        $totalWeight = array_sum(array_column($factors, 'weight')) ?: 1;

        return (int) round(100 * array_sum(array_map(fn (array $f) => $f['normalized'] * $f['weight'], $factors)) / $totalWeight);
    }

    /** Niveau affiché par le badge de priorité. */
    public static function level(?int $score): array
    {
        return match (true) {
            $score === null => ['Non évalué', 'b-mute'],
            $score >= 60 => ['Priorité élevée', 'b-crit'],
            $score >= 35 => ['Priorité moyenne', 'b-warn'],
            default => ['Priorité faible', 'b-ok'],
        };
    }
}
