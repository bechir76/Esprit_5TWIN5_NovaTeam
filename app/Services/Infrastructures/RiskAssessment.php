<?php

namespace App\Services\Infrastructures;

use App\Enums\RiskLevel;
use App\Models\Infrastructure;
use App\Models\User;
use App\Notifications\InfrastructureNotification;
use App\Notifications\InfrastructureRiskCritical;
use App\Services\Ai\InfrastructureAiService;
use Illuminate\Support\Facades\Notification;

/**
 * Évaluation complète du risque d'un ouvrage : score et facteurs (RiskScorer, code déterministe),
 * explication et recommandations (InfrastructureAiService, repli par règles), enregistrement, historique
 * (activitylog, événement « risk_computed ») et alerte quand le niveau devient « Critique ».
 * Utilisée par la commande infrastructures:score, le bouton « Recalculer » et le seeder.
 */
class RiskAssessment
{
    public function __construct(
        private RiskScorer $scorer,
        private InfrastructureAiService $ai,
    ) {}

    public function assess(Infrastructure $infrastructure, ?User $causer = null, bool $notify = true): Infrastructure
    {
        $factors = $this->scorer->factors($infrastructure);
        $score = $this->scorer->score($infrastructure, $factors);
        $level = $this->scorer->level($score);
        $text = $this->ai->explainRisk($infrastructure, $score, $level, $factors);

        $previous = ['score' => $infrastructure->risk_score, 'level' => $infrastructure->risk_level];

        $infrastructure->forceFill([
            'risk_score' => $score,
            'risk_level' => $level,
            'risk_factors' => $factors,
            'risk_summary' => $text['summary'],
            'risk_recommendations' => $text['recommendations'],
            'risk_computed_at' => now(),
            'risk_source' => $text['source'],
        ])->save();

        activity('infrastructures')
            ->performedOn($infrastructure)
            ->causedBy($causer)
            ->event('risk_computed')
            ->withProperties([
                'old' => ['risk_score' => $previous['score'], 'risk_level' => $previous['level']?->value],
                'attributes' => ['risk_score' => $score, 'risk_level' => $level->value],
                'source' => $text['source'],
            ])
            ->log('Score de risque recalculé');

        if ($notify && $level === RiskLevel::Critique && $previous['level'] !== RiskLevel::Critique) {
            Notification::send(InfrastructureNotification::recipients(), new InfrastructureRiskCritical($infrastructure->loadMissing('zone')));
        }

        return $infrastructure;
    }
}
