<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InterventionStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInterventionRequest;
use App\Http\Requests\Admin\UpdateInterventionRequest;
use App\Models\Incident;
use App\Models\Intervention;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRUD des interventions (personnel).
 * Création depuis la liste ou depuis la fiche d'un incident (?incident=ID, retour vers la fiche).
 */
class InterventionController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(InterventionStatus::class)],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $interventions = Intervention::query()
            ->with(['incident.infrastructure', 'technician'])
            ->search($filters['q'] ?? null)
            ->ofStatus($filters['status'] ?? null)
            ->scheduledOn($filters['date'] ?? null)
            ->orderByDesc('scheduled_at')
            ->paginate(10)
            ->withQueryString();

        return view('pages.interventions.index', [
            'interventions' => $interventions,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        $incident = Incident::find($request->integer('incident'));

        $intervention = new Intervention([
            'incident_id' => $incident?->id,
            'status' => InterventionStatus::Planifiee,
        ]);

        return view('pages.interventions.form', $this->formData($intervention) + [
            'fromIncident' => $incident !== null,
        ]);
    }

    public function store(StoreInterventionRequest $request): RedirectResponse
    {
        // L'enregistrement peut aussi faire passer l'incident « en intervention » (hook saved du modèle)
        $intervention = DB::transaction(fn () => Intervention::create($request->validated()));

        return $this->redirectAfterSave($request, $intervention)->with('status', "Intervention « {$intervention->title} » planifiée.");
    }

    public function show(Intervention $intervention): View
    {
        $intervention->load(['incident.infrastructure.zone', 'technician']);

        return view('pages.interventions.show', ['intervention' => $intervention]);
    }

    public function edit(Intervention $intervention): View
    {
        return view('pages.interventions.form', $this->formData($intervention) + ['fromIncident' => false]);
    }

    public function update(UpdateInterventionRequest $request, Intervention $intervention): RedirectResponse
    {
        DB::transaction(fn () => $intervention->update($request->validated()));

        return $this->redirectAfterSave($request, $intervention)->with('status', "Intervention « {$intervention->title} » mise à jour.");
    }

    public function destroy(Request $request, Intervention $intervention): RedirectResponse
    {
        $incident = $intervention->incident;
        $intervention->delete();

        $redirect = $request->query('retour') === 'incident'
            ? to_route('admin.incidents.show', $incident)
            : to_route('admin.interventions.index');

        return $redirect->with('status', "Intervention « {$intervention->title} » supprimée.");
    }

    // ---------------------------------------------------------------- Helpers

    /** Retour vers la fiche de l'incident si le formulaire a été ouvert depuis celle-ci. */
    private function redirectAfterSave(Request $request, Intervention $intervention): RedirectResponse
    {
        return $request->input('retour') === 'incident'
            ? to_route('admin.incidents.show', $intervention->incident_id)
            : to_route('admin.interventions.show', $intervention);
    }

    /** @return array<string, mixed> */
    private function formData(Intervention $intervention): array
    {
        // Incidents ouverts, plus l'incident actuel s'il est déjà clôturé (modification)
        $incidents = Incident::options(Incident::query()
            ->where(fn ($q) => $q->open()->when($intervention->incident_id, fn ($q, $id) => $q->orWhere('id', $id))));

        return [
            'intervention' => $intervention,
            'incidents' => $incidents,
            'technicians' => User::where('role', Role::Technicien->value)
                ->where(fn ($q) => $q->where('is_active', true)->when($intervention->technician_id, fn ($q, $id) => $q->orWhere('id', $id)))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
            'teams' => array_combine(Intervention::TEAMS, Intervention::TEAMS),
        ];
    }
}
