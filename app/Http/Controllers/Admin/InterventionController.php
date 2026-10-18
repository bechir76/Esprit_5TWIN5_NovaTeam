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
use App\Services\Ai\IncidentAiService;
use App\Services\Incidents\InterventionScheduler;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Console : interventions (liste Livewire, planning, CRUD), création depuis la fiche d'un incident
 * (?incident=ID, retour vers la fiche), conflits de planning (InterventionScheduler), rapport PDF et
 * rédaction du rapport assistée par l'IA. Autorisations : InterventionPolicy.
 */
class InterventionController extends Controller
{
    /** Liste : recherche, filtres, tri et pagination rendus par le composant Livewire InterventionTable. */
    public function index(): View
    {
        Gate::authorize('viewAny', Intervention::class);

        return view('pages.interventions.index');
    }

    /** Planning (calendrier semaine / mois) : un clic sur un créneau ouvre le formulaire prérempli. */
    public function planning(): View
    {
        Gate::authorize('viewAny', Intervention::class);

        return view('pages.interventions.planning', ['teams' => Intervention::TEAMS]);
    }

    /** Évènements du calendrier entre deux dates (FullCalendar), colorés par statut. */
    public function events(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Intervention::class);

        $validated = $request->validate(['start' => ['required', 'date'], 'end' => ['required', 'date'], 'team' => ['nullable', 'string']]);
        $colors = ['planifiee' => '#1CA7EC', 'en_cours' => '#F4A261', 'terminee' => '#2EC4B6', 'annulee' => '#9AA9B5'];

        $events = Intervention::query()->with('incident')
            ->whereBetween('scheduled_at', [Carbon::parse($validated['start'])->subHours(InterventionScheduler::MAX_HOURS), Carbon::parse($validated['end'])])
            ->when($validated['team'] ?? null, fn ($q, $team) => $q->where('team', $team))
            ->get()
            ->map(fn (Intervention $i) => [
                'id' => $i->id,
                'title' => "{$i->team} · {$i->title} ({$i->incident->reference})",
                'start' => $i->scheduled_at->toIso8601String(),
                'end' => $i->endsAt()->toIso8601String(),
                'color' => $colors[$i->status->value],
                'url' => route('admin.interventions.show', $i),
                'extendedProps' => ['status' => $i->status->label(), 'team' => $i->team],
            ]);

        return response()->json($events);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Intervention::class);

        $incident = Incident::find($request->integer('incident'));

        // ?date=… (clic sur un créneau du planning) : date prévue préremplie
        $intervention = new Intervention([
            'incident_id' => $incident?->id,
            'status' => InterventionStatus::Planifiee,
            'scheduled_at' => rescue(fn () => $request->filled('date') ? Carbon::parse($request->query('date')) : null, null, false),
            'team' => in_array($request->query('equipe'), Intervention::TEAMS, true) ? $request->query('equipe') : null,
        ]);

        return view('pages.interventions.form', $this->formData($intervention) + [
            'fromIncident' => $incident !== null,
        ]);
    }

    public function store(StoreInterventionRequest $request, InterventionScheduler $scheduler): RedirectResponse
    {
        // Conflit d'équipe revérifié dans la transaction ; peut aussi faire passer l'incident « en intervention »
        $intervention = $scheduler->create($request->safe()->except('retour'));

        return $this->redirectAfterSave($request, $intervention)->with('status', "Intervention « {$intervention->title} » planifiée.");
    }

    public function show(Intervention $intervention): View
    {
        Gate::authorize('view', $intervention);

        $intervention->load(['incident.infrastructure.zone', 'technician']);

        return view('pages.interventions.show', ['intervention' => $intervention]);
    }

    public function edit(Intervention $intervention): View
    {
        Gate::authorize('update', $intervention);

        return view('pages.interventions.form', $this->formData($intervention) + ['fromIncident' => false]);
    }

    public function update(UpdateInterventionRequest $request, Intervention $intervention, InterventionScheduler $scheduler): RedirectResponse
    {
        Gate::authorize('update', $intervention);

        $scheduler->update($intervention, $request->safe()->except('retour'));

        return $this->redirectAfterSave($request, $intervention)->with('status', "Intervention « {$intervention->title} » mise à jour.");
    }

    public function destroy(Request $request, Intervention $intervention): RedirectResponse
    {
        Gate::authorize('delete', $intervention);

        $incident = $intervention->incident;
        $intervention->delete();

        $redirect = $request->query('retour') === 'incident'
            ? to_route('admin.incidents.show', $incident)
            : to_route('admin.interventions.index');

        return $redirect->with('status', "Intervention « {$intervention->title} » supprimée.");
    }

    /** Rapport d'intervention en PDF (barryvdh/laravel-dompdf). */
    public function pdf(Intervention $intervention): Response
    {
        Gate::authorize('downloadReport', $intervention);

        $intervention->load(['incident.infrastructure.zone', 'technician']);

        return Pdf::loadView('pages.interventions.report-pdf', ['intervention' => $intervention])
            ->download("rapport-{$intervention->incident->reference}-{$intervention->id}.pdf");
    }

    /** B3 : brouillon de rapport structuré à partir de mots-clés ; inséré dans le champ, jamais enregistré seul. */
    public function draftReport(Request $request, Intervention $intervention, IncidentAiService $ai): JsonResponse
    {
        Gate::authorize('update', $intervention);

        $validated = $request->validate(['keywords' => ['required', 'string', 'min:5', 'max:500']]);

        return response()->json(['report' => $ai->draftReport($validated['keywords'], $intervention->load('incident'))]);
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
