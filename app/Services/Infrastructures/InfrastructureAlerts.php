<?php

namespace App\Services\Infrastructures;

use App\Enums\InfrastructureStatus;
use App\Models\Infrastructure;
use App\Notifications\InfrastructureBecameCritical;
use App\Notifications\InfrastructureNotification;
use Illuminate\Support\Facades\Notification;

/** Alerte des administrateurs et gestionnaires quand un ouvrage passe à l'état « Critique » (création, modification, inspection). */
class InfrastructureAlerts
{
    public function statusChanged(Infrastructure $infrastructure, ?InfrastructureStatus $previous): void
    {
        if ($infrastructure->status === InfrastructureStatus::Critique && $previous !== InfrastructureStatus::Critique) {
            Notification::send(
                InfrastructureNotification::recipients(),
                new InfrastructureBecameCritical($infrastructure->loadMissing('zone'), $previous?->label()),
            );
        }
    }
}
