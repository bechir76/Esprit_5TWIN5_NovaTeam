<?php

namespace App\Events;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Statut d'un projet modifié (émis par le hook updated du modèle Project). */
class ProjectStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(public Project $project, public ProjectStatus $from) {}
}
