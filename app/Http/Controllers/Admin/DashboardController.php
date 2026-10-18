<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AnalysisStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentType;
use App\Enums\InfrastructureStatus;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\Funding;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\Project;
use App\Models\WaterAnalysis;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Tableau de bord de la console. */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        // Dernière analyse non conforme avec sa pire mesure (la plus éloignée du seuil)
        $lastNonCompliant = WaterAnalysis::query()
            ->with(['infrastructure', 'measurements'])
            ->ofStatus(AnalysisStatus::NonConforme)
            ->orderByDesc('sampled_at')
            ->first();

        $worstMeasurement = null;
        if ($lastNonCompliant) {
            $worstMeasurement = $lastNonCompliant->measurements
                ->filter(fn ($m) => ! $m->is_compliant)
                ->first();
        }

        return view('pages.dashboard', [
            'atRiskCount'       => Infrastructure::atRisk()->count(),
            'criticalCount'     => Infrastructure::ofStatus(InfrastructureStatus::Critique)->count(),
            'lastNonCompliant'  => $lastNonCompliant,
            'worstMeasurement'  => $worstMeasurement,
        ] + $this->incidentStats() + $this->projectStats());
    }

    /**
     * Indicateurs du module incidents : KPI, sparklines hebdomadaires, répartition par type
     * (groupBy en base), signalés / résolus par mois et derniers incidents.
     *
     * @return array<string, mixed>
     */
    private function incidentStats(): array
    {
        // Sparklines : incidents signalés par semaine sur 8 semaines (toutes gravités, puis critiques)
        $weeks = collect(range(7, 0))->map(fn (int $k) => now()->startOfWeek()->subWeeks($k));
        $recent = Incident::where('created_at', '>=', $weeks->first())->get(['created_at', 'severity']);
        $perWeek = fn ($incidents) => $weeks->map(fn (Carbon $start) => $incidents
            ->filter(fn (Incident $i) => $i->created_at->between($start, $start->copy()->endOfWeek()))
            ->count())->all();

        // Répartition des incidents ouverts par type
        $byType = Incident::open()->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');
        $donut = collect(IncidentType::cases())
            ->map(fn (IncidentType $type) => [$type->pluralLabel(), (int) ($byType[$type->value] ?? 0), $type->color()])
            ->values()
            ->all();

        // Signalés et résolus par mois sur 6 mois
        $months = collect(range(5, 0))->map(fn (int $k) => now()->startOfMonth()->subMonths($k));
        $reported = Incident::where('created_at', '>=', $months->first())->pluck('created_at');
        $resolved = Incident::where('resolved_at', '>=', $months->first())->pluck('resolved_at');
        $countIn = fn ($dates, Carbon $month) => $dates->filter(fn ($d) => Carbon::make($d)->isSameMonth($month))->count();

        return [
            'openIncidents' => Incident::open()->count(),
            'newIncidentsThisWeek' => Incident::where('created_at', '>=', now()->subWeek())->count(),
            'criticalIncidents' => Incident::open()->ofSeverity(IncidentSeverity::Critique)->count(),
            'newCriticalToday' => Incident::ofSeverity(IncidentSeverity::Critique)->where('created_at', '>=', now()->subDay())->count(),
            'incidentSpark' => $perWeek($recent),
            'criticalSpark' => $perWeek($recent->filter(fn (Incident $i) => $i->severity === IncidentSeverity::Critique)),
            'incidentsByType' => $donut,
            'incidentsPerMonth' => [
                'labels' => $months->map(fn (Carbon $m) => Str::ucfirst(rtrim($m->translatedFormat('M'), '.')))->all(),
                'reported' => $months->map(fn (Carbon $m) => $countIn($reported, $m))->all(),
                'resolved' => $months->map(fn (Carbon $m) => $countIn($resolved, $m))->all(),
            ],
            'latestIncidents' => Incident::with('infrastructure')->latest()->limit(5)->get(),
        ];
    }

    /**
     * Indicateurs du module projets : fonds collectés (total, mois en cours, cumul sur 8 mois)
     * et avancement des projets en cours.
     *
     * @return array<string, mixed>
     */
    private function projectStats(): array
    {
        // Sparkline : total cumulé des financements à la fin de chacun des 8 derniers mois
        $months = collect(range(7, 0))->map(fn (int $k) => now()->startOfMonth()->subMonths($k));
        $cumulative = (float) Funding::confirmed()->whereDate('funded_at', '<', $months->first())->sum('amount');
        $recent = Funding::confirmed()->whereDate('funded_at', '>=', $months->first())->get(['funded_at', 'amount']);
        $spark = [];
        foreach ($months as $month) {
            $cumulative += (float) $recent->filter(fn (Funding $f) => $f->funded_at->isSameMonth($month))->sum('amount');
            $spark[] = round($cumulative);
        }

        return [
            'fundsCollected' => (float) Funding::confirmed()->sum('amount'),
            'fundsThisMonth' => (float) Funding::confirmed()->whereDate('funded_at', '>=', now()->startOfMonth())->sum('amount'),
            'fundsSpark' => $spark,
            // Avancement des projets en collecte, financés ou en travaux, les plus avancés d'abord
            'dashboardProjects' => Project::query()
                ->ofStatus([ProjectStatus::Open, ProjectStatus::Funded, ProjectStatus::InProgress])
                ->withCollected()
                ->orderByProgress()
                ->limit(4)
                ->get(),
        ];
    }
}
