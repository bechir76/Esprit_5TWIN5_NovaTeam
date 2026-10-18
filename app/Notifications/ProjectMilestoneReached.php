<?php

namespace App\Notifications;

use App\Models\Project;

/** Le projet soutenu a atteint la moitié de son objectif. */
class ProjectMilestoneReached extends ProjectNotification
{
    public function __construct(public Project $project) {}

    protected function title(): string
    {
        return 'Projet à mi-parcours';
    }

    protected function message(): string
    {
        return "« {$this->project->title} » a dépassé ".config('projects.milestone_percent').' % de son objectif de '.Project::money($this->project->target_budget).' DT.';
    }

    protected function url(): string
    {
        return route('projects.show', $this->project);
    }
}
