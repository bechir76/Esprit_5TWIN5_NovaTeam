<?php

namespace App\Services\Incidents;

use App\Enums\IncidentStatus;
use App\Models\Incident;
use Illuminate\Support\Collection;

/**
 * Prévision de la durée de résolution, calculée par le code à partir des incidents déjà résolus :
 * médiane du même type et de la même gravité, sinon médiane globale, sinon délai cible (SLA) de la gravité.
 */
class ResolutionEstimator
{
    /** @var Collection<int, Incident>|null */
    private ?Collection $resolved = null;

    /**
     * @return array{hours: float, basis: string, samples: int}
     *                                                          basis : type_severity (même type et gravité), global ou sla
     */
    public function estimate(Incident $incident): array
    {
        $min = (int) config('incidents.estimate_min_samples', 3);
        $resolved = $this->resolved();

        $same = $resolved->filter(fn (Incident $i) => $i->type === $incident->type && $i->severity === $incident->severity);

        if ($same->count() >= $min) {
            return ['hours' => $this->median($same), 'basis' => 'type_severity', 'samples' => $same->count()];
        }

        if ($resolved->count() >= $min) {
            return ['hours' => $this->median($resolved), 'basis' => 'global', 'samples' => $resolved->count()];
        }

        return ['hours' => (float) $incident->slaHours(), 'basis' => 'sla', 'samples' => $resolved->count()];
    }

    /** « environ 6 h », « environ 2 jours ». */
    public static function label(float $hours): string
    {
        if ($hours < 1) {
            return 'moins d\'une heure';
        }

        if ($hours < 48) {
            return 'environ '.(int) round($hours).' h';
        }

        return 'environ '.(int) round($hours / 24).' jours';
    }

    /** @return Collection<int, Incident> */
    private function resolved(): Collection
    {
        return $this->resolved ??= Incident::query()
            ->ofStatus(IncidentStatus::Resolu)
            ->whereNotNull('resolved_at')
            ->get(['id', 'type', 'severity', 'created_at', 'resolved_at']);
    }

    /** @param  Collection<int, Incident>  $incidents */
    private function median(Collection $incidents): float
    {
        return round((float) $incidents
            ->map(fn (Incident $i) => max(0, $i->created_at->diffInMinutes($i->resolved_at)) / 60)
            ->median(), 1);
    }
}
