<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AnalysisStatus;
use App\Enums\InfrastructureStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInfrastructureRequest;
use App\Http\Requests\Admin\StoreInspectionRequest;
use App\Http\Requests\Admin\UpdateInfrastructureRequest;
use App\Livewire\InfrastructureRisk;
use App\Models\Infrastructure;
use App\Models\Zone;
use App\Services\Infrastructures\InfrastructureAlerts;
use App\Services\Infrastructures\InspectionRecorder;
use App\Services\Infrastructures\RiskAssessment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** CRUD des infrastructures (personnel, InfrastructurePolicy), inspections, fiche 360°, export CSV et fiche PDF. */
class InfrastructureController extends Controller
{
    /** Liste : tableau, filtres (?q, ?type, ?status, ?zone, ?inspection, ?sort) et pagination rendus par le composant Livewire InfrastructureTable. */
    public function index(): View
    {
        Gate::authorize('viewAny', Infrastructure::class);

        return view('pages.infrastructures.index');
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Infrastructure::class);

        // ?zone=ID (lien « Ajouter » depuis la fiche d'une zone) : zone présélectionnée
        $infrastructure = new Infrastructure([
            'zone_id' => $request->integer('zone') ?: null,
            'is_public' => true,
        ]);

        return view('pages.infrastructures.form', ['infrastructure' => $infrastructure, 'zones' => Zone::options()]);
    }

    public function store(StoreInfrastructureRequest $request, InfrastructureAlerts $alerts): RedirectResponse
    {
        $infrastructure = Infrastructure::create($request->validated());
        $alerts->statusChanged($infrastructure, null);

        return to_route('admin.infrastructures.show', $infrastructure)->with('status', "Infrastructure {$infrastructure->code} créée.");
    }

    /** Fiche 360° : informations, incidents et interventions, projets, analyses d'eau, historique et indicateurs. */
    public function show(Infrastructure $infrastructure): View
    {
        Gate::authorize('view', $infrastructure);

        $infrastructure->load([
            'zone',
            'incidents' => fn ($q) => $q->with(['interventions' => fn ($q) => $q->orderBy('scheduled_at')])->latest(),
            'projects' => fn ($q) => $q->withCollected()->latest(),
            'waterAnalyses' => fn ($q) => $q->with('measurements')->orderByDesc('sampled_at'),
        ]);

        return view('pages.infrastructures.show', [
            'infrastructure' => $infrastructure,
            'indicators' => $this->indicators($infrastructure),
            'activities' => $infrastructure->activities()->with('causer')->latest()->orderByDesc('id')->limit(30)->get(),
        ]);
    }

    public function edit(Infrastructure $infrastructure): View
    {
        Gate::authorize('update', $infrastructure);

        return view('pages.infrastructures.form', ['infrastructure' => $infrastructure, 'zones' => Zone::options()]);
    }

    public function update(UpdateInfrastructureRequest $request, Infrastructure $infrastructure, InfrastructureAlerts $alerts): RedirectResponse
    {
        $previous = $infrastructure->status;
        $infrastructure->update($request->validated());
        $alerts->statusChanged($infrastructure, $previous);

        return to_route('admin.infrastructures.show', $infrastructure)->with('status', "Infrastructure {$infrastructure->code} mise à jour.");
    }

    public function destroy(Infrastructure $infrastructure): RedirectResponse
    {
        Gate::authorize('delete', $infrastructure);

        $infrastructure->delete();

        return to_route('admin.infrastructures.index')->with('status', "Infrastructure {$infrastructure->code} supprimée.");
    }

    /** A1 : « Enregistrer une inspection » (date, nouvel état, note) ; met à jour les dates d'inspection et l'historique. */
    public function inspect(StoreInspectionRequest $request, Infrastructure $infrastructure, InspectionRecorder $inspections): RedirectResponse
    {
        $data = $request->validated();
        $date = Carbon::parse($data['inspected_at']);

        $inspections->record($infrastructure, $date, InfrastructureStatus::from($data['status']), $data['note'] ?? null, $request->user());

        return to_route('admin.infrastructures.show', $infrastructure)->with('status',
            "Inspection du {$date->format('d/m/Y')} enregistrée : état « {$infrastructure->status->label()} », prochaine inspection le {$infrastructure->next_inspection_at->format('d/m/Y')}.");
    }

    /** B1 : « Recalculer » sans JavaScript (le composant Livewire InfrastructureRisk fait le même calcul). */
    public function assessRisk(Request $request, Infrastructure $infrastructure, RiskAssessment $assessment): RedirectResponse
    {
        Gate::authorize('assessRisk', $infrastructure);

        $assessment->assess($infrastructure, $request->user());

        return to_route('admin.infrastructures.show', $infrastructure)->with('status', InfrastructureRisk::recomputedMessage($infrastructure));
    }

    /** A5 : export CSV de la liste avec les filtres actifs (séparateur ;, UTF-8 avec BOM pour Excel). */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('export', Infrastructure::class);

        $filters = Infrastructure::normalizeFilters($request->query());
        $infrastructures = Infrastructure::query()->with('zone')->filter($filters)->sorted($filters['sort'])->get();

        return response()->streamDownload(function () use ($infrastructures): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM : accents corrects dans Excel
            fputcsv($out, ['Code', 'Nom', 'Type', 'Zone', 'Matériau', 'Capacité', 'Mise en service', 'État', 'Dernière inspection', 'Prochaine inspection', 'Inspection en retard', 'Score de risque', 'Niveau de risque'], ';');

            foreach ($infrastructures as $i) {
                fputcsv($out, array_map(self::csvCell(...), [
                    $i->code, $i->name, $i->type->label(), $i->zone->name, $i->material ?? '', $i->formattedCapacity(), $i->commissioned_year,
                    $i->status->label(), $i->last_inspection_at?->format('d/m/Y') ?? '', $i->next_inspection_at?->format('d/m/Y') ?? '',
                    $i->isInspectionOverdue() ? 'oui' : 'non', $i->risk_score ?? '', $i->risk_level?->label() ?? '',
                ]), ';');
            }

            fclose($out);
        }, 'infrastructures-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Injection de formule (Excel) : une cellule texte commençant par =, +, -, @, tabulation ou retour chariot est préfixée d'une apostrophe. */
    private static function csvCell(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /** A5 : fiche PDF d'un ouvrage (informations, risque, recommandations, dernières inspections et incidents). */
    public function pdf(Infrastructure $infrastructure): Response
    {
        Gate::authorize('downloadPdf', $infrastructure);

        $infrastructure->load(['zone', 'incidents' => fn ($q) => $q->latest()->limit(10)]);

        return Pdf::loadView('pages.infrastructures.pdf', [
            'infrastructure' => $infrastructure,
            'inspections' => $infrastructure->activities()->with('causer')->where('event', 'inspected')->latest()->orderByDesc('id')->limit(5)->get(),
        ])->download("fiche-{$infrastructure->code}.pdf");
    }

    /**
     * Indicateurs de la fiche 360° (relations déjà chargées).
     *
     * @return array{incidents_12_months: int, average_resolution_hours: ?float, invested: float, compliance_rate: ?int}
     */
    private function indicators(Infrastructure $infrastructure): array
    {
        $resolved = $infrastructure->incidents->filter(fn ($i) => $i->resolved_at !== null);
        $analyses = $infrastructure->waterAnalyses->filter(fn ($a) => in_array($a->status, [AnalysisStatus::Validee, AnalysisStatus::NonConforme], true));

        return [
            'incidents_12_months' => $infrastructure->incidents->filter(fn ($i) => $i->created_at->gte(now()->subMonths(12)))->count(),
            'average_resolution_hours' => $resolved->isEmpty() ? null : round($resolved->avg(fn ($i) => $i->created_at->diffInMinutes($i->resolved_at) / 60), 1),
            'invested' => (float) $infrastructure->projects->sum(fn ($p) => (float) $p->fundings_sum_amount),
            'compliance_rate' => $analyses->isEmpty() ? null
                : (int) round($analyses->filter(fn ($a) => $a->status === AnalysisStatus::Validee)->count() / $analyses->count() * 100),
        ];
    }
}
