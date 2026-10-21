<?php

namespace App\Notifications;

use App\Models\Infrastructure;

/** Aux administrateurs et gestionnaires : l'inspection d'un ouvrage est en retard (envoyée une fois par échéance). */
class InfrastructureInspectionOverdue extends InfrastructureNotification
{
    public function __construct(public Infrastructure $infrastructure) {}

    protected function title(): string
    {
        return "Inspection en retard : {$this->infrastructure->code}";
    }

    protected function message(): string
    {
        return "L'inspection de « {$this->infrastructure->name} » ({$this->infrastructure->zone->name}) était prévue le "
            .$this->infrastructure->next_inspection_at->format('d/m/Y')." ({$this->infrastructure->inspectionDaysOverdue()} jour(s) de retard, "
            ."état actuel « {$this->infrastructure->status->label()} »).";
    }

    protected function url(): string
    {
        return route('admin.infrastructures.show', $this->infrastructure);
    }
}
