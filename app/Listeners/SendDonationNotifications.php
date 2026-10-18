<?php

namespace App\Listeners;

use App\Events\FundingConfirmed;
use App\Models\Project;
use App\Notifications\DonationConfirmed;
use App\Notifications\ProjectMilestoneReached;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/** Don confirmé : reçu envoyé au donateur ; à 50 % de l'objectif, tous les donateurs sont prévenus (une fois). */
class SendDonationNotifications implements ShouldQueue
{
    public function handle(FundingConfirmed $event): void
    {
        $funding = $event->funding->fresh(['project', 'user']);

        if (! $funding) {
            return;
        }

        if (! $funding->is_matching && $funding->user) {
            $funding->user->notify(new DonationConfirmed($funding));
        }

        $project = $funding->project;
        $milestone = config('projects.milestone_percent');

        // Mise à jour conditionnelle : un seul envoi même si deux dons sont confirmés en même temps
        if ($project->progress_percent >= $milestone && $project->progress_percent < 100
            && Project::whereKey($project->id)->whereNull('milestone_notified_at')->update(['milestone_notified_at' => now()]) === 1) {
            Notification::send(Project::donorsOf($project), new ProjectMilestoneReached($project));
        }
    }
}
