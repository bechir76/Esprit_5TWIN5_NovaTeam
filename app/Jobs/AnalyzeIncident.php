<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Services\Ai\IncidentAiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Analyse IA d'un nouvel incident (en queue : les appels au LLM peuvent prendre plusieurs secondes) :
 * type et gravité proposés (B1, à côté des valeurs choisies), score d'urgence et résumé (B2), photo (B6).
 */
class AnalyzeIncident implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public Incident $incident) {}

    public function handle(IncidentAiService $ai): void
    {
        $incident = $this->incident->load(['infrastructure', 'user']);

        $classification = $ai->classify($incident->description, $incident);
        $summary = $ai->summarize($incident);

        // Champs IA uniquement : pas d'événement de modèle (ni historique ni notification)
        $incident->forceFill([
            'ai_type' => $classification['type'],
            'ai_severity' => $classification['severity'],
            'ai_urgency_score' => $summary['score'],
            'ai_urgency_reason' => $summary['reason'],
            'ai_summary' => $summary['summary'],
            'ai_photo_analysis' => $ai->analyzePhoto($incident),
            'ai_analyzed_at' => now(),
        ])->saveQuietly();
    }
}
