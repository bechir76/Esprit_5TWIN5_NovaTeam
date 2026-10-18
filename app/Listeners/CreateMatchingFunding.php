<?php

namespace App\Listeners;

use App\Enums\FundingSource;
use App\Enums\FundingStatus;
use App\Enums\ProjectStatus;
use App\Events\FundingConfirmed;
use App\Models\Funding;
use App\Models\Project;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

/**
 * Cofinancement automatique : chaque don citoyen confirmé est doublé (1 pour 1) par un financement municipal
 * marqué is_matching, dans la limite du plafond par projet (config/projects.php) et du reste à collecter.
 * Jamais de cofinancement d'un cofinancement ; un seul par don (matched_funding_id unique).
 */
class CreateMatchingFunding implements ShouldQueue
{
    public function handle(FundingConfirmed $event): void
    {
        $donation = $event->funding->fresh();

        if (! config('projects.matching.enabled') || ! $donation || $donation->is_matching
            || $donation->source_type !== FundingSource::Citoyen || ! $donation->isConfirmed()) {
            return;
        }

        $matching = DB::transaction(function () use ($donation): ?Funding {
            $project = Project::query()->lockForUpdate()->find($donation->project_id);

            if (! $project || ! in_array($project->status, [ProjectStatus::Open, ProjectStatus::Funded], true)
                || Funding::where('matched_funding_id', $donation->id)->exists()) {
                return null;
            }

            $used = (float) $project->fundings()->where('is_matching', true)->confirmed()->sum('amount');
            $amount = min((float) $donation->amount, config('projects.matching.cap_per_project') - $used, $project->remainingAmount());

            if ($amount <= 0) {
                return null;
            }

            return $project->fundings()->create([
                'source_type' => FundingSource::Municipalite,
                'donor_name' => config('projects.matching.donor_name'),
                'amount' => round($amount, 2),
                'funded_at' => today(),
                'status' => FundingStatus::Confirmed,
                'paid_at' => now(),
                'is_matching' => true,
                'matched_funding_id' => $donation->id,
                'note' => "Cofinancement 1 pour 1 du don {$donation->reference}.",
            ]);
        });

        if ($matching) {
            FundingConfirmed::dispatch($matching);
        }
    }
}
