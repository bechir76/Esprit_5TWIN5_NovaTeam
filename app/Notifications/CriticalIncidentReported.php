<?php

namespace App\Notifications;

use App\Models\Incident;

/** Aux administrateurs : un incident de gravité critique vient d'être signalé. */
class CriticalIncidentReported extends IncidentNotification
{
    public function __construct(public Incident $incident) {}

    protected function title(): string
    {
        return "Incident critique {$this->incident->reference}";
    }

    protected function message(): string
    {
        return "{$this->incident->type->label()} critique signalé : « {$this->incident->title} » ({$this->incident->address}). Délai cible : {$this->incident->slaHours()} h.";
    }

    protected function url(): string
    {
        return route('admin.incidents.show', $this->incident);
    }
}
