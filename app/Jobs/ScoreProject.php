<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\Ai\ProjectAiService;
use App\Services\ProjectScoring;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Score de priorité d'un projet (code déterministe) et explication rédigée par l'IA (repli : texte gabarit).
 * En queue : l'appel au LLM peut prendre plusieurs secondes.
 */
class ScoreProject implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public Project $project) {}

    public function handle(ProjectScoring $scoring, ProjectAiService $ai): void
    {
        $factors = $scoring->factors($this->project);
        $score = $scoring->score($this->project, $factors);

        $this->project->update([
            'ai_priority_score' => $score,
            'ai_priority_reason' => $ai->priorityReason($factors, $score),
            'ai_scored_at' => now(),
        ]);
    }
}
