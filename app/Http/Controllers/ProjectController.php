<?php

namespace App\Http\Controllers;

use App\Enums\FundingSource;
use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Exceptions\DonationRefused;
use App\Http\Requests\StoreContributionRequest;
use App\Models\Funding;
use App\Models\Project;
use App\Services\Ai\ProjectAiService;
use App\Services\DonationService;
use App\Services\FundingForecast;
use App\Services\ProjectRecommender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Site citoyen : projets publics (/projets), fiche d'un projet (/projets/{slug}), don par Stripe Checkout
 * (citoyen connecté) et page Transparence (/projets-transparence).
 */
class ProjectController extends Controller
{
    /** Tris proposés : valeur du paramètre « tri » => libellé. */
    public const SORTS = [
        'urgents' => 'Plus urgents d\'abord',
        'presque' => 'Presque financés',
        'recents' => 'Plus récents',
    ];

    public function index(Request $request, ProjectRecommender $recommender): View
    {
        $filters = $request->validate([
            'categorie' => ['nullable', Rule::enum(ProjectCategory::class)],
            'tri' => ['nullable', Rule::in(array_keys(self::SORTS))],
        ]);

        $query = Project::query()
            ->published()
            ->with('infrastructure.zone')
            ->withCollected()
            ->ofCategory($filters['categorie'] ?? null);

        match ($filters['tri'] ?? 'urgents') {
            // Collectes ouvertes et non échues d'abord, par date de fin la plus proche
            'urgents' => $query
                ->orderByRaw('case when status = ? and end_date >= ? then 0 else 1 end', [ProjectStatus::Open->value, today()->toDateString()])
                ->orderBy('end_date'),
            'presque' => $query->orderByProgress(),
            'recents' => $query->latest(),
        };

        $user = $request->user();

        return view('pages.projects.public-index', [
            'projects' => $query->orderBy('id')->paginate(9)->withQueryString(),
            'filters' => $filters,
            'sorts' => self::SORTS,
            // B4 : recommandations pour un citoyen connecté
            'recommendations' => $user?->role === Role::Citoyen ? $recommender->for($user) : collect(),
        ]);
    }

    public function show(Project $project, FundingForecast $forecasts, ProjectAiService $ai): View
    {
        // Brouillons et projets masqués : non publiés
        abort_unless(Gate::allows('view', $project), 404);

        $project->load(['infrastructure.zone', 'fundings' => fn ($q) => $q->confirmed()->orderByDesc('funded_at')->orderByDesc('id')->limit(8)])
            ->loadSum(['fundings as fundings_sum_amount' => fn ($q) => $q->confirmed()], 'amount');

        // Répartition par source des dons confirmés (groupBy en base)
        $bySource = $project->fundings()->confirmed()
            ->selectRaw('source_type, sum(amount) as total, count(*) as contributions')
            ->groupBy('source_type')
            ->get()
            ->keyBy(fn (Funding $f) => $f->source_type->value);

        // B3 : prévision calculée par le code ; message éventuellement reformulé par l'IA (mis en cache)
        $forecast = $forecasts->for($project);
        $forecastMessage = $project->status === ProjectStatus::Open
            ? Cache::remember("project-forecast-{$project->id}-".md5($forecast['message']), now()->addHours(6), fn () => $ai->forecastMessage($project, $forecast))
            : null;

        return view('pages.projects.public-show', [
            'project' => $project,
            'bySource' => $bySource,
            'sources' => FundingSource::cases(),
            'statuses' => ProjectStatus::cases(),
            'forecast' => $forecast,
            'forecastMessage' => $forecastMessage,
        ]);
    }

    /**
     * POST /projects/{slug}/donate : don « pending » puis redirection vers Stripe Checkout.
     * Le donateur vient de la session, le montant est validé ; seul le webhook confirmera le paiement.
     */
    public function donate(StoreContributionRequest $request, Project $project, DonationService $donations): RedirectResponse
    {
        Gate::authorize('donate', $project);

        try {
            $url = $donations->start($project, $request->user(), (float) $request->validated('amount'), $request->boolean('anonymous'));
        } catch (DonationRefused $e) {
            return back()->withErrors(['project' => $e->getMessage()])->withInput();
        }

        return redirect()->away($url);
    }

    /** Retour de Stripe après paiement : message seulement, la confirmation arrive par le webhook. */
    public function donateSuccess(Project $project): RedirectResponse
    {
        return to_route('projects.show', $project)
            ->with('status', 'Merci ! Votre paiement est en cours de confirmation : votre reçu sera disponible dans « Mes contributions ».');
    }

    public function donateCancel(Project $project): RedirectResponse
    {
        return to_route('projects.show', $project)->with('status', 'Paiement annulé : aucun montant n\'a été débité.');
    }

    /** Transparence : flux public des dons confirmés (« Anonyme » sur demande, sans donnée personnelle). */
    public function transparency(): View
    {
        $confirmed = Funding::query()->confirmed()->whereHas('project', fn ($q) => $q->published());

        return view('pages.projects.transparency', [
            'fundings' => (clone $confirmed)->with('project:id,title,slug,status,is_public')
                ->orderByDesc('paid_at')->orderByDesc('id')->paginate(20),
            'total' => (float) (clone $confirmed)->sum('amount'),
            'count' => (clone $confirmed)->count(),
            'bySource' => (clone $confirmed)->selectRaw('source_type, sum(amount) as total')->groupBy('source_type')->pluck('total', 'source_type'),
        ]);
    }
}
