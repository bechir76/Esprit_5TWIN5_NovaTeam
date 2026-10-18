<?php

namespace App\Notifications;

use App\Enums\IncidentStatus;
use App\Models\Incident;

/** Au citoyen : le statut de son signalement a changé (lien vers la page de suivi signée). */
class IncidentStatusChanged extends IncidentNotification
{
    public function __construct(public Incident $incident, public IncidentStatus $from, public IncidentStatus $to) {}

    protected function title(): string
    {
        return "Votre signalement {$this->incident->reference} est « {$this->to->label()} »";
    }

    protected function message(): string
    {
        return "Le statut de votre signalement « {$this->incident->title} » est passé de « {$this->from->label()} » à « {$this->to->label()} ».";
    }

    protected function url(): string
    {
        return $this->incident->trackingUrl();
    }
}
