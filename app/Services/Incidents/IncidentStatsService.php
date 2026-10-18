<?php

namespace App\Services\Incidents;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\Incident;
use App\Models\Intervention;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Indicateurs du tableau de bord du module incidents (calculés en PHP, compatibles SQLite et MySQL),
 * mis en cache quelques minutes (config/incidents.php).
 */
class IncidentStatsService
{
    public const CACHE_KEY = 'incidents.stats';

    /** @return array<string, mixed> */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, (int) config('incidents.stats_cache_seconds', 300), fn () => $this->compute());
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    public function compute(): array
    {
        $since = now()->startOfMonth()->subMonths(5);
        $incidents = Incident::query()->with('infrastructure.zone')->where('created_at', '>=', $since->copy()->subYear())->get();
        $resolved = $incidents->filter(fn (Incident $i) => $i->status === IncidentStatus::Resolu && $i->resolved_at);
        $months = collect(range(5, 0))->map(fn (int $k) => now()->startOfMonth()->subMonths($k));

        // Incidents par type et par mois (6 derniers mois)
        $byTypeMonth = [
            'labels' => $months->map(fn (Carbon $m) => Str::ucfirst(rtrim($m->translatedFormat('M Y'), '.')))->all(),
            'series' => collect(IncidentType::cases())->map(fn (IncidentType $type) => [
                'label' => $type->pluralLabel(),
                'color' => $type->color(),
                'data' => $months->map(fn (Carbon $m) => $incidents->filter(fn (Incident $i) => $i->type === $type && $i->created_at->isSameMonth($m))->count())->all(),
            ])->all(),
        ];

        // Temps moyen de résolution (heures) par gravité
        $resolutionBySeverity = collect(IncidentSeverity::cases())->mapWithKeys(fn (IncidentSeverity $severity) => [
            $severity->label() => round((float) $resolved->filter(fn (Incident $i) => $i->severity === $severity)
                ->avg(fn (Incident $i) => $i->created_at->diffInMinutes($i->resolved_at) / 60), 1),
        ])->all();

        // Respect du délai cible : incidents résolus dans leur délai / incidents résolus
        $withinSla = $resolved->filter(fn (Incident $i) => $i->resolved_at->lte($i->slaDeadline()))->count();

        // Charge par équipe : interventions planifiées, en cours ou terminées ces 30 derniers jours
        $teamLoad = Intervention::query()->where('scheduled_at', '>=', now()->subDays(30))->where('status', '!=', 'annulee')->get()
            ->groupBy('team')
            ->map(fn ($group, string $team) => ['team' => $team, 'count' => $group->count(), 'hours' => round((float) $group->sum('estimated_hours'), 1)])
            ->sortByDesc('count')->values()->all();

        // Zones les plus touchées (via l'ouvrage lié)
        $topZones = $incidents->filter(fn (Incident $i) => $i->infrastructure?->zone)
            ->countBy(fn (Incident $i) => $i->infrastructure->zone->name)
            ->sortDesc()->take(5)->all();

        // Accord IA / humain : type et gravité proposés par l'IA identiques aux valeurs retenues
        $analyzed = $incidents->filter(fn (Incident $i) => $i->ai_type !== null);

        return [
            'byTypeMonth' => $byTypeMonth,
            'resolutionBySeverity' => $resolutionBySeverity,
            'slaRate' => $resolved->isEmpty() ? null : (int) round($withinSla / $resolved->count() * 100),
            'overdueOpen' => Incident::query()->overdue()->count(),
            'teamLoad' => $teamLoad,
            'topZones' => $topZones,
            'withoutInfrastructure' => Incident::query()->open()->whereNull('infrastructure_id')->count(),
            'aiAnalyzed' => $analyzed->count(),
            'aiTypeAgreement' => $analyzed->isEmpty() ? null : (int) round($analyzed->filter(fn (Incident $i) => $i->ai_type === $i->type->value)->count() / $analyzed->count() * 100),
            'aiSeverityAgreement' => $analyzed->isEmpty() ? null : (int) round($analyzed->filter(fn (Incident $i) => $i->ai_severity === $i->severity->value)->count() / $analyzed->count() * 100),
            'computedAt' => now()->format('d/m/Y H:i'),
        ];
    }
}
