<?php

namespace App\Services;

use App\Enums\FundingStatus;
use App\Models\Project;
use Illuminate\Support\Carbon;

/**
 * Prévision de financement calculée par le code : rythme moyen des dons confirmés sur les 14 derniers jours,
 * date estimée d'atteinte de l'objectif et probabilité simple (haute / moyenne / faible).
 * Le LLM peut seulement reformuler le message (ProjectAiService::forecastMessage()).
 */
class FundingForecast
{
    /**
     * @return array{collected: float, remaining: float, daily_rate: float, eta: ?Carbon, reached: bool, on_time: bool, probability: string, message: string}
     */
    public function for(Project $project): array
    {
        $window = config('projects.forecast_window_days');
        $collected = (float) $project->fundings()->confirmed()->sum('amount');
        $remaining = max(0, (float) $project->target_budget - $collected);
        $recent = (float) $project->fundings()->confirmed()->whereDate('funded_at', '>', today()->subDays($window))->sum('amount');
        $rate = round($recent / $window, 2);

        if ($remaining <= 0) {
            return $this->result($collected, 0, $rate, today(), true, true, 'haute', 'Objectif atteint.');
        }

        if ($rate <= 0) {
            return $this->result($collected, $remaining, 0, null, false, false, 'faible', 'Au rythme actuel, objectif non atteint (aucun don confirmé ces '.$window.' derniers jours).');
        }

        $eta = today()->addDays((int) ceil($remaining / $rate));
        $onTime = $eta->lte($project->end_date);
        $probability = match (true) {
            $eta->lte($project->end_date->copy()->subDays(7)) => 'haute',
            $onTime => 'moyenne',
            default => 'faible',
        };

        $message = $onTime
            ? 'Au rythme actuel, objectif atteint le '.$eta->format('d/m').'.'
            : 'Au rythme actuel, objectif non atteint avant la date limite du '.$project->end_date->format('d/m').'.';

        return $this->result($collected, $remaining, $rate, $eta, false, $onTime, $probability, $message);
    }

    /**
     * Courbe du cumul des dons confirmés (jusqu'à aujourd'hui) et courbe idéale linéaire jusqu'à la date limite.
     *
     * @return array{labels: list<string>, cumulative: list<?float>, ideal: list<float>, target: float}
     */
    public function series(Project $project): array
    {
        $start = $project->start_date->copy();
        $end = $project->end_date->copy();
        $days = max(1, (int) $start->diffInDays($end));
        // Au plus ~60 points sur le graphique
        $step = max(1, (int) ceil($days / 60));
        $target = (float) $project->target_budget;

        $perDay = $project->fundings()->where('status', FundingStatus::Confirmed->value)
            ->selectRaw('date(funded_at) as day, sum(amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = $cumulative = $ideal = [];
        for ($d = 0; $d <= $days; $d += $step) {
            $date = $start->copy()->addDays($d);
            $labels[] = $date->format('d/m');
            $ideal[] = round($target * $d / $days, 2);
            $cumulative[] = $date->lte(today())
                ? round((float) $perDay->filter(fn ($total, $day) => $day <= $date->toDateString())->sum(), 2)
                : null;
        }

        return ['labels' => $labels, 'cumulative' => $cumulative, 'ideal' => $ideal, 'target' => $target];
    }

    /** @return array{collected: float, remaining: float, daily_rate: float, eta: ?Carbon, reached: bool, on_time: bool, probability: string, message: string} */
    private function result(float $collected, float $remaining, float $rate, ?Carbon $eta, bool $reached, bool $onTime, string $probability, string $message): array
    {
        return [
            'collected' => $collected,
            'remaining' => $remaining,
            'daily_rate' => $rate,
            'eta' => $eta,
            'reached' => $reached,
            'on_time' => $onTime,
            'probability' => $probability,
            'message' => $message,
        ];
    }
}
