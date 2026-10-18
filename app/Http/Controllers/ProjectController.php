<?php

namespace App\Http\Controllers;

use App\Enums\FundingSource;
use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Http\Requests\StoreContributionRequest;
use App\Models\Funding;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Site citoyen : projets publics (/projets), fiche d'un projet (/projets/{slug})
 * et contribution d'un citoyen connecté (paiement simulé).
 */
class ProjectController extends Controller
{
    /** Tris proposés : valeur du paramètre « tri » => libellé. */
    public const SORTS = [
        'urgents' => 'Plus urgents d\'abord',
        'presque' => 'Presque financés',
        'recents' => 'Plus récents',
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'categorie' => ['nullable', Rule::enum(ProjectCategory::class)],
            'tri' => ['nullable', Rule::in(array_keys(self::SORTS))],
        ]);

        $query = Project::query()
            ->published()
            ->with('infrastructure.zone')
            ->withSum('fundings', 'amount')
            ->ofCategory($filters['categorie'] ?? null);

        match ($filters['tri'] ?? 'urgents') {
            // Collectes ouvertes et non échues d'abord, par date de fin la plus proche
            'urgents' => $query
                ->orderByRaw('case when status = ? and end_date >= ? then 0 else 1 end', [ProjectStatus::EnCollecte->value, today()->toDateString()])
                ->orderBy('end_date'),
            'presque' => $query->orderByProgress(),
            'recents' => $query->latest(),
        };

        return view('pages.projects.public-index', [
            'projects' => $query->orderBy('id')->paginate(9)->withQueryString(),
            'filters' => $filters,
            'sorts' => self::SORTS,
        ]);
    }

    public function show(Project $project): View
    {
        // Brouillons et projets masqués : non publiés
        abort_unless($project->is_public && $project->status !== ProjectStatus::Brouillon, 404);

        $project->load(['infrastructure.zone', 'fundings' => fn ($q) => $q->orderByDesc('funded_at')->orderByDesc('id')])
            ->loadSum('fundings', 'amount');

        // Répartition par source (groupBy en base) et nombre de contributions citoyennes
        $bySource = $project->fundings()
            ->selectRaw('source_type, sum(amount) as total, count(*) as contributions')
            ->groupBy('source_type')
            ->get()
            ->keyBy(fn (Funding $f) => $f->source_type->value);

        return view('pages.projects.public-show', [
            'project' => $project,
            'bySource' => $bySource,
            'sources' => FundingSource::cases(),
            'statuses' => ProjectStatus::cases(),
        ]);
    }

    /** POST /projets/{slug}/contributions : enregistre la contribution du citoyen connecté (aucun paiement réel). */
    public function contribute(StoreContributionRequest $request, Project $project): RedirectResponse
    {
        $funding = $project->recordContribution($request->user(), $request->validated('amount'));

        return to_route('projects.show', $project)->with('status', $funding->contributionThanks());
    }
}
