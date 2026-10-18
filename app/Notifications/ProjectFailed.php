<?php

namespace App\Notifications;

use App\Models\Project;

/** Collecte échouée (objectif non atteint à la date limite) : les dons sont remboursés. */
class ProjectFailed extends ProjectNotification
{
    public function __construct(public Project $project) {}

    protected function title(): string
    {
        return 'Collecte non aboutie : don remboursé';
    }

    protected function message(): string
    {
        return "« {$this->project->title} » n'a pas atteint son objectif avant le {$this->project->end_date->format('d/m/Y')}. Votre don est remboursé sur votre moyen de paiement.";
    }

    protected function url(): string
    {
        return route('projects.show', $this->project);
    }
}
