<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IncidentStatus;
use App\Exceptions\InvalidIncidentTransition;
use App\Http\Controllers\Controller;
use App\Http\Controllers\IncidentReportController;
use App\Http\Requests\Admin\StoreIncidentRequest;
use App\Http\Requests\Admin\UpdateIncidentRequest;
use App\Http\Requests\Admin\UpdateIncidentStatusRequest;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Services\Incidents\IncidentClusterer;
use App\Services\Incidents\IncidentIntake;
use App\Services\Incidents\IncidentStatsService;
use App\Services\Incidents\IncidentWorkflow;
use App\Services\Incidents\ResolutionEstimator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Console : incidents (liste Livewire, fiche, CRUD), changement de statut par le workflow, carte,
 * statistiques, export CSV et regroupement des incidents liés. Autorisations : IncidentPolicy.
 */
class IncidentController extends Controller
{
    /** Liste : recherche, filtres, tri et pagination rendus par le composant Livewire IncidentTable. */
    public function index(): View
    {
        Gate::authorize('viewAny', Incident::class);

        return view('pages.incidents.index');
    }

    public function create(Request $request): View
    {
        Gate::authorize('viewAny', Incident::class);

        // ?infrastructure=ID (lien depuis la fiche d'un ouvrage) : infrastructure présélectionnée
        $incident = new Incident([
            'infrastructure_id' => $request->integer('infrastructure') ?: null,
        ]);

        return view('pages.incidents.form', $this->formData($incident));
    }

    public function store(StoreIncidentRequest $request, IncidentIntake $intake): RedirectResponse
    {
        $data = $request->safe()->except(['photo', 'duplicate_decision', 'duplicate_of']);
        // Saisi par le personnel : l'agent connecté est enregistré comme déclarant
        $data['user_id'] = $request->user()->id;

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store(Incident::PHOTO_DIRECTORY, Incident::PHOTO_DISK);
        }

        $incident = Incident::create($data);
        $intake->received($incident);

        return to_route('admin.incidents.show', $incident)->with('status', "Incident {$incident->reference} créé.");
    }

    public function show(Incident $incident, ResolutionEstimator $estimator): View
    {
        Gate::authorize('view', $incident);

        $incident->load([
            'infrastructure.zone', 'user', 'duplicateOf', 'duplicates',
            'interventions' => fn ($q) => $q->with('technician')->orderBy('scheduled_at'),
        ]);

        return view('pages.incidents.show', [
            'incident' => $incident,
            'estimate' => $incident->status->isOpen() ? $estimator->estimate($incident) : null,
            'linked' => $incident->cluster_key
                ? Incident::query()->where('cluster_key', $incident->cluster_key)->whereKeyNot($incident->id)->orderBy('created_at')->get()
                : collect(),
        ]);
    }

    public function edit(Incident $incident): View
    {
        Gate::authorize('update', $incident);

        return view('pages.incidents.form', $this->formData($incident));
    }

    public function update(UpdateIncidentRequest $request, Incident $incident): RedirectResponse
    {
        Gate::authorize('update', $incident);

        $data = $request->safe()->except(['photo', 'remove_photo', 'duplicate_decision', 'duplicate_of']);

        // Nouvelle photo ou retrait : l'ancien fichier est supprimé
        if ($request->hasFile('photo') || $request->boolean('remove_photo')) {
            $incident->deletePhoto();
            $data['photo_path'] = $request->hasFile('photo')
                ? $request->file('photo')->store(Incident::PHOTO_DIRECTORY, Incident::PHOTO_DISK)
                : null;
        }

        $incident->update($data);

        return to_route('admin.incidents.show', $incident)->with('status', "Incident {$incident->reference} mis à jour.");
    }

    /** PATCH /admin/incidents/{incident}/statut : transition contrôlée par IncidentWorkflow (historique et notification). */
    public function updateStatus(UpdateIncidentStatusRequest $request, Incident $incident, IncidentWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('changeStatus', $incident);

        try {
            $workflow->transition($incident, IncidentStatus::from($request->validated('status')), $request->user(), $request->validated('comment'));
        } catch (InvalidIncidentTransition $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return to_route('admin.incidents.show', $incident)
            ->with('status', $incident->statusChangedMessage());
    }

    public function destroy(Incident $incident): RedirectResponse
    {
        Gate::authorize('delete', $incident);

        // Les interventions sont supprimées par la cascade, la photo par le hook deleted du modèle
        $incident->delete();

        return to_route('admin.incidents.index')->with('status', "Incident {$incident->reference} supprimé.");
    }

    // ---------------------------------------------------------------- Carte, statistiques, export

    /** Carte des incidents ouverts (repères colorés par gravité, lien vers la fiche). */
    public function map(): View
    {
        Gate::authorize('viewAny', Incident::class);

        return view('pages.incidents.map', [
            'markers' => IncidentReportController::markers(fn (Incident $i) => route('admin.incidents.show', $i)),
        ]);
    }

    /** Tableau de bord du module : indicateurs (cache court) et groupes d'incidents liés. */
    public function stats(IncidentStatsService $stats): View
    {
        Gate::authorize('viewAny', Incident::class);

        return view('pages.incidents.stats', [
            'stats' => $stats->all(),
            'clusters' => Incident::query()->open()->whereNotNull('cluster_key')->orderBy('created_at')->get()->groupBy('cluster_key'),
        ]);
    }

    /** Export CSV de la liste filtrée (mêmes filtres que la liste Livewire), réservé à l'administrateur. */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('export', Incident::class);

        $filters = $request->only(['q', 'type', 'severity', 'status', 'zone', 'du', 'au', 'infrastructure', 'retard']);
        $incidents = Incident::query()->with('infrastructure.zone')->filter($filters)->sorted($request->query('tri'))->get();

        return response()->streamDownload(function () use ($incidents): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM : accents corrects dans Excel
            fputcsv($out, ['Référence', 'Titre', 'Type', 'Gravité', 'Statut', 'Adresse', 'Ouvrage', 'Zone', 'Signalé le', 'Résolu le', 'Signalements', 'En retard', 'Urgence IA'], ';');

            foreach ($incidents as $i) {
                fputcsv($out, [
                    $i->reference, $i->title, $i->type->label(), $i->severity->label(), $i->status->label(), $i->address,
                    $i->infrastructure?->code, $i->infrastructure?->zone?->name, $i->created_at->format('d/m/Y H:i'),
                    $i->resolved_at?->format('d/m/Y H:i'), $i->reports_count, $i->isOverdue() ? 'oui' : 'non', $i->ai_urgency_score,
                ], ';');
            }

            fclose($out);
        }, 'incidents-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * B4 « Regrouper les interventions » : les incidents du groupe sont rattachés au plus ancien,
     * puis une seule intervention est planifiée pour lui (formulaire prérempli).
     */
    public function groupCluster(string $cluster): RedirectResponse
    {
        Gate::authorize('viewAny', Incident::class);

        $group = Incident::query()->open()->where('cluster_key', $cluster)->orderBy('created_at')->orderBy('id')->get();
        abort_if($group->count() < 2, 404);

        $principal = $group->first();

        DB::transaction(function () use ($group, $principal): void {
            foreach ($group->skip(1) as $incident) {
                if ($incident->duplicate_of_id === null) {
                    $incident->update(['duplicate_of_id' => $principal->id]);
                    $principal->increment('reports_count');
                }
            }
        });

        return to_route('admin.interventions.create', ['incident' => $principal->id])
            ->with('status', ($group->count() - 1)." incident(s) rattaché(s) à {$principal->reference} : planifiez une seule intervention pour le groupe.");
    }

    /** Recalcule les groupes d'incidents liés tout de suite (bouton du tableau de bord). */
    public function recluster(IncidentClusterer $clusterer): RedirectResponse
    {
        Gate::authorize('viewAny', Incident::class);

        $groups = $clusterer->run();

        return back()->with('status', "{$groups->count()} groupe(s) d'incidents liés détecté(s).");
    }

    // ---------------------------------------------------------------- Helpers

    /** @return array<string, mixed> */
    private function formData(Incident $incident): array
    {
        return [
            'incident' => $incident,
            'infrastructures' => Infrastructure::orderBy('code')
                ->get(['id', 'code', 'name'])
                ->mapWithKeys(fn (Infrastructure $i) => [$i->id => "{$i->code} · {$i->name}"])
                ->all(),
        ];
    }
}
