<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectRequest;
use App\Http\Requests\Admin\UpdateProjectRequest;
use App\Models\Funding;
use App\Models\Infrastructure;
use App\Models\Project;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** CRUD des projets de rénovation (personnel). */
class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'category' => ['nullable', Rule::enum(ProjectCategory::class)],
            'zone' => ['nullable', 'integer'],
        ]);

        $projects = Project::query()
            ->with('infrastructure.zone')
            ->withSum('fundings', 'amount')
            ->search($filters['q'] ?? null)
            ->ofStatus($filters['status'] ?? null)
            ->ofCategory($filters['category'] ?? null)
            ->inZone($filters['zone'] ?? null)
            ->orderBy('end_date')
            ->paginate(10)
            ->withQueryString();

        return view('pages.projects.index', [
            'projects' => $projects,
            'filters' => $filters,
            'zones' => Zone::options(),
        ]);
    }

    public function create(Request $request): View
    {
        // ?infrastructure=ID : infrastructure présélectionnée
        $project = new Project([
            'infrastructure_id' => $request->integer('infrastructure') ?: null,
            'status' => ProjectStatus::Brouillon,
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

        return to_route('admin.projects.show', $project)->with('status', "Projet « {$project->title} » créé.");
    }

    public function show(Project $project): View
    {
        $project->load(['infrastructure.zone', 'fundings' => fn ($q) => $q->with('user')->orderByDesc('funded_at')->orderByDesc('id')])
            ->loadSum('fundings', 'amount');

        return view('pages.projects.show', [
            'project' => $project,
            // Montant par source (groupBy en base)
            'bySource' => $project->fundings()
                ->selectRaw('source_type, sum(amount) as total')
                ->groupBy('source_type')
                ->get()
                ->mapWithKeys(fn (Funding $f) => [$f->source_type->value => (float) $f->total]),
        ]);
    }

    public function edit(Project $project): View
    {
        return view('pages.projects.form', $this->formData($project));
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $data = $request->safe()->except(['image', 'remove_image']);

        // Nouvelle image ou retrait : l'ancien fichier est supprimé
        if ($request->hasFile('image') || $request->boolean('remove_image')) {
            $project->deleteImage();
            $data['image_path'] = $request->hasFile('image')
                ? $request->file('image')->store(Project::IMAGE_DIRECTORY, Project::IMAGE_DISK)
                : null;
        }

        $project->update($data);

        return to_route('admin.projects.show', $project)->with('status', "Projet « {$project->title} » mis à jour.");
    }

    public function destroy(Project $project): RedirectResponse
    {
        // Les financements sont supprimés par la cascade, l'image par le hook deleted du modèle
        $project->delete();

        return to_route('admin.projects.index')->with('status', "Projet « {$project->title} » supprimé.");
    }

    // ---------------------------------------------------------------- Helpers

    /** @return array<string, mixed> */
    private function formData(Project $project): array
    {
        return [
            'project' => $project,
            'infrastructures' => Infrastructure::orderBy('code')
                ->get(['id', 'code', 'name'])
                ->mapWithKeys(fn (Infrastructure $i) => [$i->id => "{$i->code} · {$i->name}"])
                ->all(),
        ];
    }
}
