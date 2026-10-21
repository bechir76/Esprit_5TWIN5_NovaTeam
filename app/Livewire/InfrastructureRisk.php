<?php

namespace App\Livewire;

use App\Models\Infrastructure;
use App\Services\Infrastructures\RiskAssessment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Carte « Score de risque » de la fiche admin d'un ouvrage : score, niveau, explication, 3 recommandations,
 * détail des facteurs et bouton « Recalculer » sans rechargement (même calcul que la route POST
 * admin.infrastructures.risk et la commande infrastructures:score). Limité à config('ai.rate_limit') par minute.
 */
class InfrastructureRisk extends Component
{
    public Infrastructure $infrastructure;

    /** Message de confirmation du dernier recalcul. */
    public ?string $message = null;

    public function recompute(RiskAssessment $assessment): void
    {
        Gate::authorize('assessRisk', $this->infrastructure);

        $this->message = null;
        $key = 'ai-infrastructure-risk:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, config('ai.rate_limit'))) {
            $this->addError('risk', 'Trop de recalculs : réessayez dans '.RateLimiter::availableIn($key).' secondes.');

            return;
        }

        RateLimiter::hit($key, 60);

        $assessment->assess($this->infrastructure, auth()->user());

        $this->message = self::recomputedMessage($this->infrastructure);
    }

    public static function recomputedMessage(Infrastructure $infrastructure): string
    {
        return "Score de risque recalculé : {$infrastructure->risk_score}/100 (".mb_strtolower($infrastructure->risk_level->label()).').';
    }

    public function render(): View
    {
        return view('livewire.infrastructure-risk');
    }
}
