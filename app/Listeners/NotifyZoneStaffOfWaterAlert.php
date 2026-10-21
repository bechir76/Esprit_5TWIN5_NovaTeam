<?php

namespace App\Listeners;

use App\Enums\Role;
use App\Events\WaterQualityAlertRaised;
use App\Notifications\WaterQualityAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Alerte qualité eau (A2) : notifie les techniciens et gestionnaires actifs
 * qui sont rattachés à la zone ou, à défaut, tous les admins.
 * En queue pour ne pas bloquer l'enregistrement d'une mesure.
 */
class NotifyZoneStaffOfWaterAlert implements ShouldQueue
{
    public function handle(WaterQualityAlertRaised $event): void
    {
        $analysis = $event->analysis->loadMissing(['infrastructure.zone']);

        // Destinataires : admins et gestionnaires actifs (les techniciens peuvent être ajoutés plus tard
        // via une table de liaison user_zone si le besoin d'affectation par zone est implémenté)
        $recipients = \App\Models\User::query()
            ->whereIn('role', [Role::Admin->value, Role::Gestionnaire->value])
            ->where('is_active', true)
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new WaterQualityAlert($analysis, $event->violations));
        }
    }
}
