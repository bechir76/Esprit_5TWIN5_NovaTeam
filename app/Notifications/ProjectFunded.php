<?php

namespace App\Notifications;

use App\Models\Project;

/** Objectif atteint : le projet soutenu est financé. */
class ProjectFunded extends ProjectNotification
{
    public function __construct(public Project $project) {}

    protected function title(): string
    {
        return 'Projet financé à 100 %';
    }

    protected function message(): string
    {
        return "Grâce à vous, « {$this->project->title} » a atteint son objectif de ".Project::money($this->project->target_budget).' DT. Les travaux vont pouvoir commencer.';
    }

    protected function url(): string
    {
        return route('projects.show', $this->project);
    }
}
