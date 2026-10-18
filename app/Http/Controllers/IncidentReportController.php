<?php

namespace App\Http\Controllers;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Http\Requests\StoreIncidentReportRequest;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\Zone;
use App\Services\Ai\IncidentAiService;
use App\Services\Incidents\DuplicateDetector;
use App\Services\Incidents\IncidentIntake;
use App\Services\Incidents\ResolutionEstimator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Site citoyen : suivi public des incidents (/incidents), carte, signalement (/incidents/signaler, connecté),
 * page de suivi d'un signalement (lien signé ou déclarant connecté) et suggestion IA du type et de la gravité.
 * Les incidents rejetés et l'identité des déclarants ne sont pas publiés.
 */
class IncidentReportController extends Controller
{
    public function index(Request $request): View
    {
        $publicStatuses = array_values(array_filter(IncidentStatus::cases(), fn (IncidentStatus $s) => $s !== IncidentStatus::Rejete));

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(IncidentType::class)],
            'status' => ['nullable', Rule::in(array_map(fn (IncidentStatus $s) => $s->value, $publicStatuses))],
            'zone' => ['nullable', 'integer'],
        ]);

        $public = fn () => Incident::query()->ofStatus($publicStatuses);

        $incidents = $public()
            ->with('infrastructure.zone')
            ->search($filters['q'] ?? null)
            ->ofType($filters['type'] ?? null)
            ->ofStatus($filters['status'] ?? null)
            ->inZone($filters['zone'] ?? null)
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('pages.incidents.public-index', [
            'incidents' => $incidents,
            'filters' => $filters,
            'statuses' => $publicStatuses,
            // Compteurs des onglets : nombre d'incidents publics par statut
            'counts' => $public()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'zones' => Zone::options(),
        ]);
    }

    /** Carte des incidents ouverts (Leaflet) : sans donnée personnelle. */
    public function map(): View
    {
        return view('pages.incidents.map', ['markers' => self::markers(fn (Incident $i) => route('incidents.index', ['q' => $i->reference]))]);
    }

    public function create(): View
    {
        return view('pages.incidents.report', [
            'infrastructures' => Infrastructure::public()
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->mapWithKeys(fn (Infrastructure $i) => [$i->id => "{$i->code} · {$i->name}"])
                ->all(),
        ]);
    }

    public function store(StoreIncidentReportRequest $request, DuplicateDetector $duplicates, IncidentIntake $intake): RedirectResponse
    {
        $data = $request->safe()->except(['photo', 'duplicate_decision', 'duplicate_of']);
        $data['user_id'] = $request->user()->id;
        $data['status'] = IncidentStatus::Signale;

        // Doublon probable (même type, < 200 m, < 72 h) : le citoyen choisit avant l'enregistrement
        $principal = null;
        if (isset($data['latitude'], $data['longitude'])) {
            $principal = $duplicates->findProbable($data['type'], (float) $data['latitude'], (float) $data['longitude']);

            if ($principal && ! $request->filled('duplicate_decision')) {
                return back()->withInput($request->except('photo'))->with('duplicate', [
                    'id' => $principal->id,
                    'reference' => $principal->reference,
                    'title' => $principal->title,
                    'status' => $principal->status->label(),
                    'created_at' => $principal->created_at->format('d/m/Y H:i'),
                    'distance' => (int) round($principal->distance),
                ]);
            }
        }

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store(Incident::PHOTO_DIRECTORY, Incident::PHOTO_DISK);
        }

        $incident = DB::transaction(function () use ($data, $request, $duplicates, $principal) {
            $incident = Incident::create($data);

            // « Je rejoins ce signalement » : rattachement à l'incident principal proposé
            if ($request->input('duplicate_decision') === 'join' && $principal && (int) $request->input('duplicate_of') === $principal->id) {
                $duplicates->attach($incident, $principal);
            }

            return $incident;
        });

        $intake->received($incident);

        $message = $incident->duplicate_of_id
            ? "Signalement {$incident->reference} rattaché à l'incident {$principal->reference}. Merci !"
            : "Incident {$incident->reference} signalé avec succès. Conservez ce lien pour suivre son traitement.";

        return redirect()->to($incident->trackingUrl())->with('status', $message);
    }

    /**
     * Suivi d'un signalement : lien signé (sans connexion) ou déclarant connecté ; jamais le rapport interne
     * ni le nom des agents.
     */
    public function track(Request $request, Incident $incident, ResolutionEstimator $estimator): View
    {
        abort_unless($request->hasValidSignature() || ($request->user() && Gate::forUser($request->user())->allows('track', $incident)), 403);

        $incident->load(['interventions' => fn ($q) => $q->orderBy('scheduled_at'), 'duplicateOf', 'infrastructure.zone']);

        return view('pages.incidents.track', [
            'incident' => $incident,
            'estimate' => $incident->status->isOpen() ? $estimator->estimate($incident) : null,
        ]);
    }

    /** B1 : suggestion du type et de la gravité à partir de la description (le citoyen garde la main). */
    public function classify(Request $request, IncidentAiService $ai): JsonResponse
    {
        $validated = $request->validate(['description' => ['required', 'string', 'min:15', 'max:3000']]);
        $suggestion = $ai->classify($validated['description'], $request->user());

        return response()->json($suggestion + [
            'type_label' => IncidentType::from($suggestion['type'])->label(),
            'severity_label' => IncidentSeverity::from($suggestion['severity'])->label(),
        ]);
    }

    /**
     * Repères de la carte des incidents ouverts (doublons exclus), sans donnée personnelle.
     *
     * @param  callable(Incident): string  $url
     * @return list<array<string, mixed>>
     */
    public static function markers(callable $url): array
    {
        return Incident::query()->open()->whereNull('duplicate_of_id')
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->orderByDesc('created_at')->get()
            ->map(fn (Incident $i) => [
                'reference' => $i->reference,
                'title' => $i->title,
                'type' => $i->type->value,
                'typeLabel' => $i->type->label(),
                'severity' => $i->severity->value,
                'severityLabel' => $i->severity->label(),
                'status' => $i->status->value,
                'statusLabel' => $i->status->label(),
                'color' => match ($i->severity) {
                    IncidentSeverity::Faible => '#2EC4B6', IncidentSeverity::Moyenne => '#F4A261',
                    IncidentSeverity::Elevee => '#E76F51', IncidentSeverity::Critique => '#E63946',
                },
                'lat' => $i->latitude,
                'lng' => $i->longitude,
                'date' => $i->created_at->format('d/m/Y H:i'),
                'url' => $url($i),
            ])->values()->all();
    }
}
