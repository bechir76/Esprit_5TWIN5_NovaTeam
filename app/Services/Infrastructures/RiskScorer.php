<?php

namespace App\Services\Infrastructures;

use App\Enums\AnalysisStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\RiskLevel;
use App\Models\Incident;
use App\Models\Infrastructure;

/**
 * Score de risque prédictif 0-100 d'un ouvrage, calculé par du code déterministe (jamais par le LLM) :
 * âge, état, matériau, inspection en retard, incidents (ouverts, critiques, sur 12 mois), groupes d'incidents liés
 * (cluster_key) et analyses d'eau non conformes. Poids, plafonds et seuils : config/infrastructures.php (risk).
 * Les facteurs ne contiennent aucune donnée personnelle : ce sont eux qui sont envoyés au LLM.
 */
class RiskScorer
{
    /**
     * Facteurs : valeur brute, valeur normalisée (0 à 1), poids, contribution au score (points sur 100) et libellé.
     *
     * @return array<string, array{value: int|float|string|null, normalized: float, weight: float, points: float, label: string}>
     */
    public function factors(Infrastructure $infrastructure): array
    {
        $config = config('infrastructures.risk');
        $since = now()->subMonths($config['incident_window_months']);

        // Âge
        $age = $infrastructure->age();

        // Matériau
        $material = $infrastructure->material;
        $materialPoints = $material !== null ? ($config['material_points'][$material] ?? $config['material_default']) : $config['material_default'];

        // Inspection : jours de retard sur la prochaine échéance (jamais inspecté = retard maximal)
        $neverInspected = $infrastructure->last_inspection_at === null;
        $daysOverdue = $infrastructure->inspectionDaysOverdue();

        // Incidents de l'ouvrage : sur la fenêtre (récents, critiques, groupes liés) et ouverts quelle que soit leur date
        $incidents = Incident::query()->where('infrastructure_id', $infrastructure->id)
            ->where(fn ($q) => $q->where('created_at', '>=', $since)->orWhereIn('status', array_column(IncidentStatus::openCases(), 'value')))
            ->get(['id', 'status', 'severity', 'cluster_key', 'created_at']);
        $recent = $incidents->filter(fn (Incident $i) => $i->created_at->gte($since));
        $open = $incidents->filter(fn (Incident $i) => $i->status->isOpen())->count();
        $critical = $recent->filter(fn (Incident $i) => $i->severity === IncidentSeverity::Critique)->count();
        $incidentPoints = $recent->count() * $config['incident_points']['recent']
            + $open * $config['incident_points']['open']
            + $critical * $config['incident_points']['critical'];
        $clusters = $recent->pluck('cluster_key')->filter()->unique()->count();

        // Analyses d'eau terminées sur la fenêtre (validées ou non conformes)
        $analyses = $infrastructure->waterAnalyses()
            ->where('sampled_at', '>=', now()->subMonths($config['water_window_months']))
            ->whereIn('status', [AnalysisStatus::Validee->value, AnalysisStatus::NonConforme->value])
            ->pluck('status');
        $nonCompliant = $analyses->filter(fn ($s) => ($s instanceof AnalysisStatus ? $s : AnalysisStatus::tryFrom((string) $s)) === AnalysisStatus::NonConforme)->count();

        $months = $config['incident_window_months'];
        $status = $infrastructure->status;

        $factors = [
            'age' => [
                'value' => $age,
                'normalized' => min(1, $age / $config['age_ceiling']),
                'label' => "ouvrage de {$age} ans (mis en service en {$infrastructure->commissioned_year})",
            ],
            'status' => [
                'value' => $status->value,
                'normalized' => (float) ($config['status_points'][$status->value] ?? 0),
                'label' => 'état « '.mb_strtolower($status->label()).' »',
            ],
            'material' => [
                'value' => $material,
                'normalized' => (float) $materialPoints,
                'label' => $material !== null ? 'matériau '.mb_strtolower($material) : 'matériau non renseigné',
            ],
            'inspection' => [
                'value' => $neverInspected ? null : $daysOverdue,
                'normalized' => $neverInspected ? 1.0 : min(1, $daysOverdue / $config['inspection_overdue_ceiling_days']),
                'label' => match (true) {
                    $neverInspected => 'aucune inspection enregistrée',
                    $daysOverdue > 0 => "inspection en retard de {$daysOverdue} jour(s)",
                    default => 'inspection à jour',
                },
            ],
            'incidents' => [
                'value' => $recent->count(),
                'normalized' => min(1, $incidentPoints / $config['incident_ceiling']),
                'label' => "{$recent->count()} incident(s) en {$months} mois, {$open} ouvert(s), {$critical} critique(s)",
            ],
            'clusters' => [
                'value' => $clusters,
                'normalized' => min(1, $clusters / $config['cluster_ceiling']),
                'label' => $clusters ? "{$clusters} groupe(s) d'incidents liés" : 'aucun groupe d\'incidents liés',
            ],
            'water_quality' => [
                'value' => $nonCompliant,
                'normalized' => $analyses->count() ? $nonCompliant / $analyses->count() : 0.0,
                'label' => $analyses->count()
                    ? "{$nonCompliant} analyse(s) d'eau non conforme(s) sur {$analyses->count()}"
                    : 'aucune analyse d\'eau récente',
            ],
        ];

        $weights = $config['weights'];
        $total = array_sum($weights) ?: 1;

        foreach ($factors as $key => $factor) {
            $normalized = round((float) $factor['normalized'], 3);
            $factors[$key] = [
                'value' => $factor['value'],
                'normalized' => $normalized,
                'weight' => (float) $weights[$key],
                'points' => round(100 * $normalized * $weights[$key] / $total, 1),
                'label' => $factor['label'],
            ];
        }

        return $factors;
    }

    /** @param  array<string, array{points: float}>|null  $factors */
    public function score(Infrastructure $infrastructure, ?array $factors = null): int
    {
        $factors ??= $this->factors($infrastructure);

        return (int) max(0, min(100, round(array_sum(array_column($factors, 'points')))));
    }

    public function level(int $score): RiskLevel
    {
        return RiskLevel::fromScore($score);
    }
}
