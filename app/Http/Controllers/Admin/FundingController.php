<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FundingSource;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFundingRequest;
use App\Http\Requests\Admin\UpdateFundingRequest;
use App\Models\Funding;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRUD des financements (personnel).
 * Création depuis la liste ou depuis la fiche d'un projet (?projet=ID, retour vers la fiche).
 * Chaque écriture recalcule le statut du projet (hooks du modèle Funding).
 */
class FundingController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'projet' => ['nullable', 'integer'],
            'source' => ['nullable', Rule::enum(FundingSource::class)],
            'du' => ['nullable', 'date_format:Y-m-d'],
            'au' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:du'],
        ]);

        $fundings = Funding::query()
            ->with(['project', 'user'])
            ->search($filters['q'] ?? null)
            ->forProject($filters['projet'] ?? null)
            ->ofSource($filters['source'] ?? null)
            ->fundedBetween($filters['du'] ?? null, $filters['au'] ?? null)
            ->orderByDesc('funded_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        // KPI : agrégats en base
        $total = (float) Funding::sum('amount');
        $municipal = (float) Funding::ofSource(FundingSource::Municipalite)->sum('amount');

        return view('back.fundings.index', [
            'fundings' => $fundings,
            'filters' => $filters,
            'projects' => Project::options(),
            'totalCollected' => $total,
            'contributionsCount' => Funding::count(),
            'municipalShare' => $total > 0 ? (int) round($municipal / $total * 100) : 0,
            'fullyFundedCount' => Project::whereRaw('(select coalesce(sum(fundings.amount), 0) from fundings where fundings.project_id = projects.id) >= projects.target_budget')->count(),
            // Progression par projet : collectes en cours ou tout juste financées, les plus avancées d'abord
            'progressProjects' => Project::query()
                ->ofStatus([ProjectStatus::EnCollecte, ProjectStatus::Finance])
                ->withSum('fundings', 'amount')
                ->orderByProgress()
                ->limit(6)
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $project = Project::find($request->integer('projet'));

        $funding = new Funding([
            'project_id' => $project?->id,
            'funded_at' => today(),
        ]);

        return view('back.fundings.form', $this->formData($funding) + [
            'fromProject' => $project !== null,
        ]);
    }

    public function store(StoreFundingRequest $request): RedirectResponse
    {
        $funding = DB::transaction(fn () => Funding::create($request->validated()));

        return $this->redirectAfterSave($request, $funding)->with('status', "Financement {$funding->reference} enregistré.");
    }

    public function show(Funding $funding): View
    {
        $funding->load(['project.infrastructure.zone', 'user']);
        $funding->project->loadSum('fundings', 'amount');

        return view('back.fundings.show', ['funding' => $funding]);
    }

    public function edit(Funding $funding): View
    {
        return view('back.fundings.form', $this->formData($funding) + ['fromProject' => false]);
    }

    public function update(UpdateFundingRequest $request, Funding $funding): RedirectResponse
    {
        DB::transaction(fn () => $funding->update($request->validated()));

        return $this->redirectAfterSave($request, $funding)->with('status', "Financement {$funding->reference} mis à jour.");
    }

    public function destroy(Request $request, Funding $funding): RedirectResponse
    {
        $project = $funding->project;
        DB::transaction(fn () => $funding->delete());

        $redirect = $request->query('retour') === 'projet'
            ? to_route('admin.projects.show', $project)
            : to_route('admin.fundings.index');

        return $redirect->with('status', "Financement {$funding->reference} supprimé.");
    }

    // ---------------------------------------------------------------- Helpers

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
