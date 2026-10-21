<?php

namespace App\Jobs;

use App\Models\Measurement;
use App\Services\WaterQuality\WqriAssessment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job déclenché après la création ou la mise à jour d'une mesure :
 *   - B1 : détection d'anomalie statistique (Z-score + explication IA)
 *   - A2 : vérification des alertes de dépassement de seuil sur l'analyse
 *
 * En queue pour ne pas bloquer l'enregistrement de la mesure.
 */
class AnalyzeWaterMeasurement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public Measurement $measurement) {}

    public function handle(WqriAssessment $assessment): void
    {
        $measurement = $this->measurement->fresh(['waterAnalysis.infrastructure.zone', 'waterAnalysis.measurements']);

        if (! $measurement) {
            return;
        }

        // B1 : anomalie statistique sur la mesure
        $assessment->detectAnomaly($measurement);

        // A2 : alertes de seuil sur l'analyse parente (toutes les mesures)
        $assessment->checkAlerts($measurement->waterAnalysis);
    }
}
