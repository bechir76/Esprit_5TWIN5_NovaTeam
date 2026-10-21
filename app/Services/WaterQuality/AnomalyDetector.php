<?php

namespace App\Services\WaterQuality;

use App\Enums\WaterParameter;
use App\Models\Measurement;
use Illuminate\Support\Facades\DB;

/**
 * Détection d'anomalies statistiques sur une mesure (B1).
 *
 * Compare la valeur mesurée à la moyenne historique du même paramètre
 * sur la même zone (90 jours glissants, analyses validées uniquement).
 * Un Z-score absolu > seuil (config : 2.5 par défaut) déclenche une anomalie.
 *
 * La méthode ne touche pas à `is_compliant` (calculé par WaterParameter::isCompliant).
 * Elle retourne les données statistiques ; c'est WaterQualityAiService::explainAnomaly
 * qui rédige l'explication en langage naturel.
 */
class AnomalyDetector
{
    /**
     * Analyse une mesure et retourne les données statistiques.
     *
     * @return array{
     *   is_anomaly: bool,
     *   z_score: ?float,
     *   historical_avg: ?float,
     *   historical_stddev: ?float,
     *   samples: int,
     *   reason: string
     * }
     */
    public function analyze(Measurement $measurement): array
    {
        $threshold = (float) config('water_quality.anomaly.z_score_threshold', 2.5);
        $minSamples = (int) config('water_quality.anomaly.min_samples', 5);
        $windowDays = (int) config('water_quality.anomaly.window_days', 90);

        $stats = $this->historicalStats(
            $measurement->water_analysis_id,
            $measurement->parameter instanceof WaterParameter ? $measurement->parameter->value : (string) $measurement->parameter,
            $windowDays,
            $measurement->id,
        );

        if ($stats['samples'] < $minSamples || $stats['stddev'] <= 0) {
            return [
                'is_anomaly' => false,
                'z_score' => null,
                'historical_avg' => $stats['avg'],
                'historical_stddev' => $stats['stddev'],
                'samples' => $stats['samples'],
                'reason' => "Historique insuffisant ({$stats['samples']} échantillon(s), minimum {$minSamples}).",
            ];
        }

        $value = (float) $measurement->value;
        $zScore = round(($value - $stats['avg']) / $stats['stddev'], 2);
        $isAnomaly = abs($zScore) > $threshold;

        $param = $measurement->parameter instanceof WaterParameter
            ? $measurement->parameter
            : WaterParameter::from((string) $measurement->parameter);

        $direction = $zScore > 0 ? 'élevée' : 'faible';
        $reason = $isAnomaly
            ? "Valeur anormalement {$direction} : {$value} {$param->unit()} (Z={$zScore}, moyenne historique ".round($stats['avg'], 3)." ± ".round($stats['stddev'], 3).")."
            : "Valeur dans la plage normale (Z={$zScore}).";

        return [
            'is_anomaly' => $isAnomaly,
            'z_score' => $zScore,
            'historical_avg' => round($stats['avg'], 4),
            'historical_stddev' => round($stats['stddev'], 4),
            'samples' => $stats['samples'],
            'reason' => $reason,
        ];
    }

    /**
     * Statistiques historiques du même paramètre sur la même zone (analyses validées uniquement).
     * Exclut la mesure courante pour ne pas biaiser la distribution.
     *
     * @return array{avg: float, stddev: float, samples: int}
     */
    private function historicalStats(int $currentAnalysisId, string $parameter, int $windowDays, ?int $excludeMeasurementId): array
    {
        // Obtenir la zone de l'analyse courante
        $zoneId = DB::table('water_analyses')
            ->join('infrastructures', 'water_analyses.infrastructure_id', '=', 'infrastructures.id')
            ->where('water_analyses.id', $currentAnalysisId)
            ->value('infrastructures.zone_id');

        if (! $zoneId) {
            return ['avg' => 0.0, 'stddev' => 0.0, 'samples' => 0];
        }

        $since = now()->subDays($windowDays);

        $varianceExpression = DB::connection()->getDriverName() === 'sqlite'
            ? 'AVG(measurements.value * measurements.value) - AVG(measurements.value) * AVG(measurements.value) as var'
            : 'VARIANCE(CAST(measurements.value AS REAL)) as var';

        $query = DB::table('measurements')
            ->join('water_analyses', 'measurements.water_analysis_id', '=', 'water_analyses.id')
            ->join('infrastructures', 'water_analyses.infrastructure_id', '=', 'infrastructures.id')
            ->where('infrastructures.zone_id', $zoneId)
            ->where('measurements.parameter', $parameter)
            ->where('water_analyses.status', 'validee')
            ->where('water_analyses.sampled_at', '>=', $since)
            ->when($excludeMeasurementId, fn ($q) => $q->where('measurements.id', '!=', $excludeMeasurementId))
            ->selectRaw('COUNT(*) as samples, AVG(CAST(measurements.value AS REAL)) as avg, '
                .$varianceExpression);

        $row = $query->first();
        $samples = (int) ($row->samples ?? 0);

        if ($samples === 0) {
            return ['avg' => 0.0, 'stddev' => 0.0, 'samples' => 0];
        }

        // SQLite ne fournit pas STDDEV : on calcule à partir de la variance (E[X²] - E[X]²)
        $avg = (float) ($row->avg ?? 0);
        $var = (float) ($row->var ?? 0);
        $stddev = $var > 0 ? sqrt($var) : 0.0;

        return ['avg' => $avg, 'stddev' => $stddev, 'samples' => $samples];
    }
}
