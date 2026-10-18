<?php

namespace App\Listeners;

use App\Enums\ProjectStatus;
use App\Events\ProjectStatusChanged;
use App\Models\Project;
use App\Notifications\ProjectFailed;
use App\Notifications\ProjectFunded;
use App\Notifications\ProjectWorksStarted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/** Projet financé, échoué (dons remboursés) ou travaux commencés : les donateurs citoyens sont notifiés. */
class NotifyDonorsOfProjectStatus implements ShouldQueue
{
    public function handle(ProjectStatusChanged $event): void
    {
        $project = $event->project->fresh();

        $notification = match ($project?->status) {
            ProjectStatus::Funded => $event->from === ProjectStatus::Open ? new ProjectFunded($project) : null,
            ProjectStatus::Failed => new ProjectFailed($project),
            ProjectStatus::InProgress => new ProjectWorksStarted($project),
            default => null,
        };

        if ($notification) {
            Notification::send(Project::donorsOf($project), $notification);
        }
    }
}
