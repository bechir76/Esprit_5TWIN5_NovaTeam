<?php

namespace App\Notifications;

use App\Models\Incident;

/** Aux administrateurs : le délai cible de traitement d'un incident est dépassé (envoyée une seule fois). */
class IncidentSlaBreached extends IncidentNotification
{
    public function __construct(public Incident $incident) {}

    protected function title(): string
    {
        return "Délai dépassé : {$this->incident->reference}";
    }

    protected function message(): string
    {
        return "L'incident « {$this->incident->title} » ({$this->incident->severity->label()}) est ouvert depuis le "
            .$this->incident->created_at->format('d/m/Y H:i')." : délai cible de {$this->incident->slaHours()} h dépassé.";
    }

    protected function url(): string
    {
        return route('admin.incidents.show', $this->incident);
    }
}
