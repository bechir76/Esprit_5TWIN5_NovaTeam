<?php

namespace App\Events;

use App\Models\WaterAnalysis;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Déclenchée quand une analyse contient au moins une mesure dépassant un seuil critique (A2).
 * Les listeners :
 *   - NotifyZoneSubscribers : notifie les techniciens et gestionnaires de la zone
 *   - CreateAlertMaintenanceTask : (futur) crée une tâche prioritaire
 */
class WaterQualityAlertRaised
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<array{parameter: string, value: float, threshold: string}>  $violations
     */
    public function __construct(
        public WaterAnalysis $analysis,
        public array $violations,
    ) {}
}
