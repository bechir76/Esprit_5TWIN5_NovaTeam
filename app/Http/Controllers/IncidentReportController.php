<?php

namespace App\Http\Controllers;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Http\Requests\StoreIncidentReportRequest;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Site citoyen : suivi public des incidents (/incidents) et signalement (/incidents/signaler, connecté).
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

        return view('front.incidents.index', [
            'incidents' => $incidents,
            'filters' => $filters,
            'statuses' => $publicStatuses,
            // Compteurs des onglets : nombre d'incidents publics par statut
            'counts' => $public()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'zones' => Zone::options(),
        ]);
    }

    public function create(): View
    {
        return view('front.incidents.create', [
            'infrastructures' => Infrastructure::public()
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->mapWithKeys(fn (Infrastructure $i) => [$i->id => "{$i->code} · {$i->name}"])
                ->all(),
        ]);
    }

    public function store(StoreIncidentReportRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('photo');
        $data['user_id'] = $request->user()->id;
        $data['status'] = IncidentStatus::Signale;

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store(Incident::PHOTO_DIRECTORY, Incident::PHOTO_DISK);
        }

        $incident = Incident::create($data);

        return to_route('incidents.index')->with('status', "Incident {$incident->reference} signalé avec succès.");
    }
}
