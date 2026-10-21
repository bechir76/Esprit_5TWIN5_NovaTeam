<?php

namespace App\Services\WaterQuality;

use App\Enums\WaterParameter;
use App\Events\WaterQualityAlertRaised;
use App\Models\Measurement;
use App\Models\WaterAnalysis;
use App\Models\Zone;
use App\Services\Ai\WaterQualityAiService;
use Illuminate\Support\Facades\Cache;

/**
 * Orchestre le calcul du WQRI (A1), le déclenchement des alertes (A2)
 * et la détection d'anomalies (B1) pour une analyse ou une zone.
 */
class WqriAssessment
{
    public const CACHE_KEY = 'wqri_stats';

    public function __construct(
        private WqriScorer $scorer,
        private WaterQualityAiService $ai,
        private AnomalyDetector $detector,
    ) {}

    // ---------------------------------------------------------------- A1 : calcul WQRI d'une zone

    public function computeZone(Zone $zone): Zone
    {
        $factors = $this->scorer->factors($zone);
        $score = $this->scorer->score($zone, $factors);
        $level = $this->scorer->level($score);
        $summary = $this->ai->wqriSummary($zone, $score, $level, $factors);

        $zone->forceFill([
            'wqri_score' => $score,
            'wqri_level' => $level->value,
            'wqri_factors' => $factors,
            'wqri_summary' => $summary,
            'wqri_computed_at' => now(),
        ])->save();

        Cache::forget(self::CACHE_KEY);

        return $zone;
    }

    // ---------------------------------------------------------------- A2 : alertes de seuil

    /**
     * Vérifie une analyse et dispatche WaterQualityAlertRaised si des seuils critiques sont dépassés.
     * Appelée après la création ou la mise à jour d'une analyse (Measurement::saved).
     */
    public function checkAlerts(WaterAnalysis $analysis): void
    {
        $analysis->loadMissing(['measurements', 'infrastructure.zone']);
        $violations = [];

        foreach ($analysis->measurements as $measurement) {
            $param = $measurement->parameter instanceof WaterParameter
                ? $measurement->parameter
                : WaterParameter::tryFrom((string) $measurement->parameter);

            if ($param === null || $measurement->is_compliant) {
                continue;
            }

            $violations[] = [
                'parameter' => $param->label(),
                'value' => (float) $measurement->value,
                'threshold' => $param->thresholdHint(),
            ];
        }

        if (empty($violations)) {
            $analysis->forceFill(['has_alert' => false, 'alert_parameters' => null])->save();

            return;
        }

        // Éviter les doubles alertes sur la même analyse
        if ($analysis->alert_dispatched_at !== null && $analysis->alert_dispatched_at->gt(now()->subMinutes(5))) {
            return;
        }

        $analysis->forceFill([
            'has_alert' => true,
            'alert_parameters' => $violations,
            'alert_dispatched_at' => now(),
        ])->save();

        WaterQualityAlertRaised::dispatch($analysis, $violations);
    }

    // ---------------------------------------------------------------- B1 : détection d'anomalie

    /**
     * Analyse une mesure pour anomalie statistique et persiste les résultats.
     * Appelée dans Measurement::saved() hook.
     */
    public function detectAnomaly(Measurement $measurement): void
    {
        $stats = $this->detector->analyze($measurement);

        $explanation = $stats['is_anomaly']
            ? $this->ai->explainAnomaly($measurement, $stats)
            : null;

        $measurement->forceFill([
            'is_anomaly' => $stats['is_anomaly'],
            'anomaly_explanation' => $explanation,
            'historical_avg' => $stats['historical_avg'],
            'historical_stddev' => $stats['historical_stddev'],
            'z_score' => $stats['z_score'],
        ])->saveQuietly();
    }
}
