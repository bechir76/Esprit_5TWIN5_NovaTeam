<?php

namespace App\Livewire;

use App\Models\Zone;
use App\Services\Infrastructures\MaintenancePlanner;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Carte « Plan de maintenance » de la fiche d'une zone : bouton « Générer un plan de maintenance » sans rechargement
 * (même calcul que la route POST admin.zones.maintenance-plan), top des actions avec justification et export PDF.
 * Limité à config('ai.rate_limit') générations par minute et par utilisateur.
 */
class ZoneMaintenancePlan extends Component
{
    public Zone $zone;

    public ?string $message = null;

    public function generate(MaintenancePlanner $planner): void
    {
        Gate::authorize('planMaintenance', $this->zone);

        $this->message = null;
        $key = 'ai-maintenance-plan:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, config('ai.rate_limit'))) {
            $this->addError('plan', 'Trop de générations : réessayez dans '.RateLimiter::availableIn($key).' secondes.');

            return;
        }

        RateLimiter::hit($key, 60);

        $planner->generate($this->zone);

        $this->message = self::generatedMessage($this->zone);
    }

    public static function generatedMessage(Zone $zone): string
    {
        return 'Plan de maintenance généré : '.count($zone->maintenance_plan['actions'] ?? []).' action(s) prioritaire(s).';
    }

    public function render(): View
    {
        return view('livewire.zone-maintenance-plan');
    }
}
