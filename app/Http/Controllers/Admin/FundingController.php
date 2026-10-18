<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FundingSource;
use App\Enums\FundingStatus;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFundingRequest;
use App\Http\Requests\Admin\UpdateFundingRequest;
use App\Models\Funding;
use App\Models\Project;
use App\Services\FundingForecast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD des financements (administrateur et gestionnaire, FundingPolicy), export CSV et tableau de bord financier.
 * Création depuis la liste ou depuis la fiche d'un projet (?projet=ID, retour vers la fiche).
 * Les financements saisis par le personnel sont encaissés (« confirmé ») ; les dons en ligne passent par Stripe.
 */
class FundingController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $fundings = $this->filteredQuery($filters)
            ->with(['project', 'user'])
            ->orderByDesc('funded_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        // KPI : agrégats en base, dons confirmés uniquement
        $total = (float) Funding::confirmed()->sum('amount');
        $municipal = (float) Funding::confirmed()->ofSource(FundingSource::Municipalite)->sum('amount');

        return view('pages.fundings.index', [
            'fundings' => $fundings,
            'filters' => $filters,
            'projects' => Project::options(),
            'totalCollected' => $total,
            'contributionsCount' => Funding::confirmed()->count(),
            'municipalShare' => $total > 0 ? (int) round($municipal / $total * 100) : 0,
            'fullyFundedCount' => Project::query()->fundedAtLeast(100)->count(),
            // Progression par projet : collectes en cours ou tout juste financées, les plus avancées d'abord
            'progressProjects' => Project::query()
                ->ofStatus([ProjectStatus::Open, ProjectStatus::Funded])
                ->withCollected()
                ->orderByProgress()
                ->limit(6)
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Funding::class);

        $project = Project::find($request->integer('projet'));

        $funding = new Funding([
            'project_id' => $project?->id,
            'funded_at' => today(),
        ]);

        return view('pages.fundings.form', $this->formData($funding) + [
            'fromProject' => $project !== null,
        ]);
    }

    public function store(StoreFundingRequest $request): RedirectResponse
    {
        // Financement institutionnel saisi par le personnel : encaissé à la date indiquée
        $funding = DB::transaction(fn () => Funding::create($request->validated() + [
            'status' => FundingStatus::Confirmed,
            'paid_at' => Carbon::parse($request->validated('funded_at')),
        ]));

        return $this->redirectAfterSave($request, $funding)->with('status', "Financement {$funding->reference} enregistré.");
    }

    public function show(Funding $funding): View
    {
        Gate::authorize('view', $funding);

        $funding->load(['project.infrastructure.zone', 'user', 'matching']);
        $funding->project->loadSum(['fundings as fundings_sum_amount' => fn ($q) => $q->confirmed()], 'amount');

        return view('pages.fundings.show', ['funding' => $funding]);
    }

    public function edit(Funding $funding): View
    {
        Gate::authorize('update', $funding);

        return view('pages.fundings.form', $this->formData($funding) + ['fromProject' => false]);
    }

    public function update(UpdateFundingRequest $request, Funding $funding): RedirectResponse
    {
        DB::transaction(fn () => $funding->update($request->validated()));

        return $this->redirectAfterSave($request, $funding)->with('status', "Financement {$funding->reference} mis à jour.");
    }

    public function destroy(Request $request, Funding $funding): RedirectResponse
    {
        Gate::authorize('delete', $funding);

        // Un don payé en ligne n'est jamais supprimé : il est remboursé (projet « Échoué »)
        if ($funding->stripe_payment_intent_id) {
            return back()->with('error', "Le don {$funding->reference} a été payé en ligne : il ne peut pas être supprimé.");
        }

        $project = $funding->project;
        DB::transaction(fn () => $funding->delete());

        $redirect = $request->query('retour') === 'projet'
            ? to_route('admin.projects.show', $project)
            : to_route('admin.fundings.index');

        return $redirect->with('status', "Financement {$funding->reference} supprimé.");
    }

    /** GET /admin/financements/export : CSV des financements filtrés (séparateur « ; » pour Excel). */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Funding::class);

        $query = $this->filteredQuery($this->filters($request))->with('project:id,title')->orderByDesc('funded_at')->orderByDesc('id');

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
            fputcsv($out, ['Référence', 'Date', 'Projet', 'Source', 'Financeur', 'Anonyme', 'Cofinancement', 'Montant (DT)', 'Statut', 'Reçu'], ';');

            $query->chunk(500, function ($fundings) use ($out): void {
                foreach ($fundings as $f) {
                    fputcsv($out, [
                        $f->reference, $f->funded_at->format('d/m/Y'), $f->project->title, $f->source_type->label(),
                        $f->donor_name, $f->is_anonymous ? 'oui' : 'non', $f->is_matching ? 'oui' : 'non',
                        number_format((float) $f->amount, 2, ',', ''), $f->status->label(), $f->receipt_number,
                    ], ';');
                }
            });

            fclose($out);
        }, 'financements-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** GET /admin/financements/tableau-de-bord : tableau de bord financier (A10). */
    public function dashboard(FundingForecast $forecasts): View
    {
        Gate::authorize('viewAny', Funding::class);

        // Évolution mensuelle sur 12 mois (dons confirmés)
        $months = collect(range(11, 0))->map(fn (int $k) => now()->startOfMonth()->subMonths($k));
        $perMonth = Funding::confirmed()->where('funded_at', '>=', $months->first())->get(['funded_at', 'amount'])
            ->groupBy(fn (Funding $f) => $f->funded_at->format('Y-m'));

        // Top donateurs : citoyens regroupés par compte, « Anonyme » respecté ; institutions par nom
        $topDonors = Funding::confirmed()->where('is_matching', false)
            ->selectRaw("case when is_anonymous = 1 then 'Anonyme' else donor_name end as donor, source_type, sum(amount) as total, count(*) as gifts")
            ->groupBy('donor', 'source_type')
            ->orderByDesc('total')->limit(8)->get();

        $open = Project::query()->ofStatus(ProjectStatus::Open)->withCollected()->with('infrastructure')->get();
        $late = $open->filter(fn (Project $p) => ! $forecasts->for($p)['on_time']);

        return view('pages.fundings.dashboard', [
            'bySource' => Funding::confirmed()->selectRaw('source_type, sum(amount) as total')->groupBy('source_type')->pluck('total', 'source_type'),
            'monthly' => [
                'labels' => $months->map(fn (Carbon $m) => Str::ucfirst($m->translatedFormat('M Y')))->all(),
                'totals' => $months->map(fn (Carbon $m) => round((float) ($perMonth[$m->format('Y-m')] ?? collect())->sum('amount'), 2))->all(),
            ],
            'topDonors' => $topDonors,
            'almostFunded' => $open->filter(fn (Project $p) => $p->progress_percent >= config('projects.almost_funded_percent'))->sortByDesc('progress_percent'),
            'late' => $late->map(fn (Project $p) => ['project' => $p, 'forecast' => $forecasts->for($p)]),
            'total' => (float) Funding::confirmed()->sum('amount'),
            'pendingCount' => Funding::ofStatus(FundingStatus::Pending)->count(),
            'refundedTotal' => (float) Funding::ofStatus(FundingStatus::Refunded)->sum('amount'),
        ]);
    }

    // ---------------------------------------------------------------- Helpers

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'projet' => ['nullable', 'integer'],
            'source' => ['nullable', Rule::enum(FundingSource::class)],
            'statut' => ['nullable', Rule::enum(FundingStatus::class)],
            'du' => ['nullable', 'date_format:Y-m-d'],
            'au' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:du'],
        ]);
    }

    /** @param  array<string, mixed>  $filters */
    private function filteredQuery(array $filters): Builder
    {
        return Funding::query()
            ->search($filters['q'] ?? null)
            ->forProject($filters['projet'] ?? null)
            ->ofSource($filters['source'] ?? null)
            ->ofStatus($filters['statut'] ?? null)
            ->fundedBetween($filters['du'] ?? null, $filters['au'] ?? null);
    }

    /** Retour vers la fiche du projet si le formulaire a été ouvert depuis celle-ci. */
    private function redirectAfterSave(Request $request, Funding $funding): RedirectResponse
    {
        return $request->input('retour') === 'projet'
            ? to_route('admin.projects.show', $funding->project_id)
            : to_route('admin.fundings.show', $funding);
    }

    /** @return array<string, mixed> */
    private function formData(Funding $funding): array
    {
        return [
            'funding' => $funding,
            'projects' => Project::query()
                ->orderBy('title')
                ->get(['id', 'title', 'start_date'])
                ->mapWithKeys(fn (Project $p) => [$p->id => "{$p->title} (depuis le {$p->start_date->format('d/m/Y')})"])
                ->all(),
        ];
    }
}
