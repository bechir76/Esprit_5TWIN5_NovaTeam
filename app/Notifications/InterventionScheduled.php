<?php

namespace App\Notifications;

use App\Models\Intervention;

/** Au citoyen : une intervention est planifiée sur son signalement (date seulement, jamais le rapport interne). */
class InterventionScheduled extends IncidentNotification
{
    public function __construct(public Intervention $intervention) {}

    protected function title(): string
    {
        return "Intervention planifiée pour {$this->intervention->incident->reference}";
    }

    protected function message(): string
    {
        return 'Une équipe interviendra le '.$this->intervention->scheduled_at->translatedFormat('l j F \à H\hi')
            ." pour votre signalement « {$this->intervention->incident->title} ».";
    }

    protected function url(): string
    {
        return $this->intervention->incident->trackingUrl();
    }
}
