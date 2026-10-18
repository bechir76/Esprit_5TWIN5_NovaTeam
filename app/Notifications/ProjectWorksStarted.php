<?php

namespace App\Notifications;

use App\Models\Project;

/** Le projet financé entre en travaux. */
class ProjectWorksStarted extends ProjectNotification
{
    public function __construct(public Project $project) {}

    protected function title(): string
    {
        return 'Les travaux ont commencé';
    }

    protected function message(): string
    {
        return "Les travaux du projet « {$this->project->title} », que vous avez soutenu, ont commencé.";
    }

    protected function url(): string
    {
        return route('projects.show', $this->project);
    }
}
