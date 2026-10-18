<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FundingStatus;
use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Exceptions\InvalidProjectTransition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectRequest;
use App\Http\Requests\Admin\UpdateProjectRequest;
use App\Jobs\ScoreProject;
use App\Models\Funding;
use App\Models\Infrastructure;
use App\Models\Project;
use App\Services\Ai\ProjectAiService;
use App\Services\DonationService;
use App\Services\FundingForecast;
use App\Services\ProjectScoring;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

/** CRUD des projets de rénovation (administrateur et gestionnaire, ProjectPolicy). */
class ProjectController extends Controller
{
    /** Liste : composant Livewire ProjectTable (recherche, filtres, tri, score). */
    public function index(): View
    {
        return view('pages.projects.index');
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Project::class);

        // ?infrastructure=ID : infrastructure présélectionnée
        $project = new Project([
            'infrastructure_id' => $request->integer('infrastructure') ?: null,
            'status' => ProjectStatus::Draft,
        ]);

        return view('pages.projects.form', $this->formData($project));
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('image');

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store(Project::IMAGE_DIRECTORY, Project::IMAGE_DISK);
        }

        $project = Project::create($data);
        ScoreProject::dispatch($project);

        return to_route('admin.projects.show', $project)->with('status', "Projet « {$project->title} » créé.");
    }

    public function show(Project $project, FundingForecast $forecasts, ProjectScoring $scoring): View
    {
        Gate::authorize('update', $project);

        $project->load(['infrastructure.zone', 'fundings' => fn ($q) => $q->with('user')->orderByDesc('funded_at')->orderByDesc('id')])
            ->loadSum(['fundings as fundings_sum_amount' => fn ($q) => $q->confirmed()], 'amount');

        return view('pages.projects.show', [
            'project' => $project,
            // Montant confirmé par source (groupBy en base)
            'bySource' => $project->fundings()->confirmed()
                ->selectRaw('source_type, sum(amount) as total')
                ->groupBy('source_type')
                ->get()
                ->mapWithKeys(fn (Funding $f) => [$f->source_type->value => (float) $f->total]),
            'forecast' => $forecasts->for($project),
            'series' => $forecasts->series($project),
            'factors' => $scoring->factors($project),
            // Journal d'audit du projet et de ses financements
            'activities' => Activity::query()->with('causer')
                ->where(fn ($q) => $q->whereMorphedTo('subject', $project)
                    ->orWhere(fn ($q) => $q->where('subject_type', (new Funding)->getMorphClass())->whereIn('subject_id', $project->fundings->pluck('id'))))
                ->latest()->limit(15)->get(),
        ]);
    }

    public function edit(Project $project): View
    {
        Gate::authorize('update', $project);

        return view('pages.projects.form', $this->formData($project));
    }

    public function update(UpdateProjectRequest $request, Project $project, DonationService $donations): RedirectResponse
    {
        $data = $request->safe()->except(['image', 'remove_image']);

        // Nouvelle image ou retrait : l'ancien fichier est supprimé
        if ($request->hasFile('image') || $request->boolean('remove_image')) {
            $project->deleteImage();
            $data['image_path'] = $request->hasFile('image')
                ? $request->file('image')->store(Project::IMAGE_DIRECTORY, Project::IMAGE_DISK)
                : null;
        }

        try {
            $project->update($data);
        } catch (InvalidProjectTransition $e) {
            return back()->withErrors(['status' => $e->getMessage()])->withInput();
        }

        // Collecte déclarée échouée : les dons confirmés sont remboursés
        $message = "Projet « {$project->title} » mis à jour.";
        if ($project->status === ProjectStatus::Failed && $project->wasChanged('status')) {
            $message .= ' '.$donations->refundProject($project).' financement(s) remboursé(s).';
        }

        return to_route('admin.projects.show', $project)->with('status', $message);
    }

    public function destroy(Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        // Des dons payés en ligne doivent d'abord être remboursés (statut « Échoué »)
        if ($project->fundings()->confirmed()->whereNotNull('stripe_payment_intent_id')->exists()) {
            return back()->with('error', 'Ce projet a reçu des dons payés en ligne : passez-le à « Échoué » pour les rembourser avant de le supprimer.');
        }

        // Les financements sont supprimés par la cascade, l'image par le hook deleted du modèle
        $project->delete();

        return to_route('admin.projects.index')->with('status', "Projet « {$project->title} » supprimé.");
    }

    // ---------------------------------------------------------------- IA

    /** POST /admin/projets/{project}/score : recalcul immédiat du score de priorité (B1). */
    public function score(Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        ScoreProject::dispatchSync($project);

        return back()->with('status', "Score de priorité recalculé : {$project->fresh()->ai_priority_score}/100.");
    }

    /** POST /admin/projets/scores : recalcul de tous les projets (en queue). */
    public function scoreAll(): RedirectResponse
    {
        Gate::authorize('create', Project::class);

        $projects = Project::query()->whereNotIn('status', [ProjectStatus::Completed->value, ProjectStatus::Failed->value])->get();
        $projects->each(fn (Project $p) => ScoreProject::dispatch($p));

        return back()->with('status', "Recalcul des scores lancé pour {$projects->count()} projet(s).");
    }

    /** POST /admin/projets/description-ia (AJAX) : description proposée, jamais enregistrée automatiquement (B2). */
    public function describe(Request $request, ProjectAiService $ai): JsonResponse
    {
        Gate::authorize('create', Project::class);

        $input = $request->validate([
            'title' => ['required', 'string', 'min:5', 'max:150'],
            'category' => ['required', Rule::enum(ProjectCategory::class)],
            'target_budget' => ['nullable', 'numeric', 'gt:0'],
            'infrastructure_id' => ['nullable', 'integer', Rule::exists(Infrastructure::class, 'id')],
        ], [
            'title.required' => 'Saisissez d\'abord l\'intitulé du projet.',
            'title.min' => 'L\'intitulé doit contenir au moins :min caractères.',
            'category.required' => 'Choisissez d\'abord la catégorie.',
            'category.enum' => 'La catégorie choisie est invalide.',
        ]);

        $infrastructure = isset($input['infrastructure_id']) ? Infrastructure::find($input['infrastructure_id']) : null;

        return response()->json(['description' => $ai->describe([
            'title' => $input['title'],
            'category' => ProjectCategory::from($input['category'])->label(),
            'budget' => $input['target_budget'] ?? null,
            'infrastructure' => $infrastructure ? "{$infrastructure->code} · {$infrastructure->name}" : null,
        ])]);
    }

    // ---------------------------------------------------------------- Helpers

    /** @return array<string, mixed> */
    private function formData(Project $project): array
    {
        // Statuts proposés : ceux atteignables depuis le statut actuel (création : brouillon ou en collecte)
        $statuses = $project->exists
            ? [$project->status, ...$project->status->transitions()]
            : [ProjectStatus::Draft, ProjectStatus::Open];

        return [
            'project' => $project,
            'statuses' => collect($statuses)->mapWithKeys(fn (ProjectStatus $s) => [$s->value => $s->label()])->all(),
            'confirmedCount' => $project->exists ? $project->fundings()->where('status', FundingStatus::Confirmed->value)->count() : 0,
            'infrastructures' => Infrastructure::orderBy('code')
                ->get(['id', 'code', 'name'])
                ->mapWithKeys(fn (Infrastructure $i) => [$i->id => "{$i->code} · {$i->name}"])
                ->all(),
        ];
    }
}
