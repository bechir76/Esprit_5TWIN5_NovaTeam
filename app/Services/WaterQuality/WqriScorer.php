<?php

namespace App\Services\WaterQuality;

use App\Enums\AnalysisStatus;
use App\Enums\InfrastructureStatus;
use App\Enums\WaterParameter;
use App\Enums\WqriLevel;
use App\Models\Incident;
use App\Models\Measurement;
use App\Models\Zone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Calcul déterministe de l'Indice de Risque Sanitaire (WQRI 0-100) d'une zone.
 *
 * Facteurs pondérés :
 *   - water_quality  (0.40) : taux de non-conformité des mesures sur 90 jours
 *   - parameter_severity (0.25) : gravité des paramètres dépassés (E.coli > nitrate > turbidité > pH/chlore)
 *   - infrastructure_state (0.20) : état des ouvrages de la zone (bon / à surveiller / critique)
 *   - incidents (0.10) : incidents de contamination ouverts sur 90 jours
 *   - population (0.05) : densité relative de population (risque de santé publique)
 *
 * Les seuils et poids sont dans config/water_quality.php.
 * L'IA rédige uniquement le résumé textuel (WaterQualityAiService::wqriSummary).
 */
class WqriScorer
{
    /** Poids des paramètres pour le facteur gravité (0–1). */
    protected const PARAMETER_SEVERITY = [
        'ecoli' => 1.0,
        'nitrate' => 0.7,
        'turbidite' => 0.5,
        'ph' => 0.3,
        'chlore' => 0.3,
        'conductivite' => 0.2,
    ];

    protected const STATUS_POINTS = ['bon' => 0.0, 'a_surveiller' => 0.5, 'critique' => 1.0];

    /**
     * @return array<string, array{value: float|int|string|null, normalized: float, weight: float, label: string}>
     */
    public function factors(Zone $zone): array
    {
        $config = config('water_quality.wqri');
        $window = $config['window_days'] ?? 90;
        $since = now()->subDays($window);

        // Mesures sur la fenêtre de la zone
        $measurementStats = DB::table('measurements')
            ->join('water_analyses', 'measurements.water_analysis_id', '=', 'water_analyses.id')
            ->join('infrastructures', 'water_analyses.infrastructure_id', '=', 'infrastructures.id')
            ->where('infrastructures.zone_id', $zone->id)
            ->where('water_analyses.sampled_at', '>=', $since)
            ->whereIn('water_analyses.status', [AnalysisStatus::Validee->value, AnalysisStatus::NonConforme->value])
            ->select([
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN measurements.is_compliant = 0 THEN 1 ELSE 0 END) as non_compliant'),
            ])
            ->first();

        $total = (int) ($measurementStats?->total ?? 0);
        $nonCompliant = (int) ($measurementStats?->non_compliant ?? 0);
        $nonCompliantRate = $total > 0 ? $nonCompliant / $total : 0.0;

        // Gravité des paramètres non conformes
        $paramSeverity = $this->parameterSeverityFactor($zone->id, $since);

        // État des infrastructures
        $infraFactor = $this->infrastructureStateFactor($zone->id);

        // Incidents de contamination ouverts
        $contaminations = Incident::query()
            ->whereHas('infrastructure', fn ($q) => $q->where('zone_id', $zone->id))
            ->where('type', 'contamination')
            ->open()
            ->where('created_at', '>=', $since)
            ->count();
        $incidentCeiling = $config['incident_ceiling'] ?? 5;
        $incidentFactor = min(1.0, $contaminations / max(1, $incidentCeiling));

        // Population relative
        $maxPop = $config['population_ceiling'] ?? 150000;
        $populationFactor = min(1.0, ($zone->population_served ?? 0) / max(1, $maxPop));

        $weights = $config['weights'] ?? [
            'water_quality' => 0.40,
            'parameter_severity' => 0.25,
            'infrastructure_state' => 0.20,
            'incidents' => 0.10,
            'population' => 0.05,
        ];

        return [
            'water_quality' => [
                'value' => $nonCompliant,
                'normalized' => round($nonCompliantRate, 4),
                'weight' => $weights['water_quality'],
                'label' => $total > 0
                    ? "{$nonCompliant}/{$total} mesures non conformes sur {$window} jours"
                    : "Aucune mesure enregistrée sur {$window} jours",
            ],
            'parameter_severity' => [
                'value' => round($paramSeverity, 2),
                'normalized' => round($paramSeverity, 4),
                'weight' => $weights['parameter_severity'],
                'label' => $paramSeverity > 0
                    ? 'Paramètres à risque élevé dépassés (E.coli, nitrate, turbidité)'
                    : 'Aucun paramètre critique dépassé',
            ],
            'infrastructure_state' => [
                'value' => round($infraFactor, 2),
                'normalized' => round($infraFactor, 4),
                'weight' => $weights['infrastructure_state'],
                'label' => "État moyen des ouvrages de la zone",
            ],
            'incidents' => [
                'value' => $contaminations,
                'normalized' => round($incidentFactor, 4),
                'weight' => $weights['incidents'],
                'label' => "{$contaminations} incident(s) de contamination ouvert(s) sur {$window} jours",
            ],
            'population' => [
                'value' => $zone->population_served ?? 0,
                'normalized' => round($populationFactor, 4),
                'weight' => $weights['population'],
                'label' => number_format($zone->population_served ?? 0, 0, ',', ' ').' habitants desservis',
            ],
        ];
    }

    /** Score 0–100 à partir des facteurs. */
    public function score(Zone $zone, ?array $factors = null): int
    {
        $factors ??= $this->factors($zone);
        $totalWeight = array_sum(array_column($factors, 'weight')) ?: 1;

        return (int) round(100 * array_sum(array_map(
            fn (array $f) => $f['normalized'] * $f['weight'],
            $factors
        )) / $totalWeight);
    }

    /** Niveau WQRI à partir du score. */
    public function level(int $score): WqriLevel
    {
        $thresholds = config('water_quality.wqri.levels', ['critique' => 70, 'eleve' => 45, 'moyen' => 20]);

        return match (true) {
            $score >= $thresholds['critique'] => WqriLevel::Critique,
            $score >= $thresholds['eleve'] => WqriLevel::Eleve,
            $score >= $thresholds['moyen'] => WqriLevel::Moyen,
            default => WqriLevel::Faible,
        };
    }

    // ---------------------------------------------------------------- Facteurs internes

    /** Gravité moyenne des paramètres non conformes (0–1) en pondérant par dangerosité. */
    private function parameterSeverityFactor(int $zoneId, Carbon $since): float
    {
        $nonCompliantParams = DB::table('measurements')
            ->join('water_analyses', 'measurements.water_analysis_id', '=', 'water_analyses.id')
            ->join('infrastructures', 'water_analyses.infrastructure_id', '=', 'infrastructures.id')
            ->where('infrastructures.zone_id', $zoneId)
            ->where('water_analyses.sampled_at', '>=', $since)
            ->where('measurements.is_compliant', false)
            ->distinct()
            ->pluck('measurements.parameter')
            ->unique();

        if ($nonCompliantParams->isEmpty()) {
            return 0.0;
        }

        $total = $nonCompliantParams->sum(fn (string $p) => self::PARAMETER_SEVERITY[$p] ?? 0.1);

        return min(1.0, $total / $nonCompliantParams->count());
    }

    /** Facteur état des infrastructures : moyenne pondérée des états (0–1). */
    private function infrastructureStateFactor(int $zoneId): float
    {
        $statuses = DB::table('infrastructures')
            ->where('zone_id', $zoneId)
            ->pluck('status');

        if ($statuses->isEmpty()) {
            return 0.0;
        }

        $sum = $statuses->sum(fn (string $s) => self::STATUS_POINTS[$s] ?? 0.5);

        return min(1.0, $sum / $statuses->count());
    }
}
