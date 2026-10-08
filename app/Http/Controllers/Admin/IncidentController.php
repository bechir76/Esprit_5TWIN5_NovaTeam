<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreIncidentRequest;
use App\Http\Requests\Admin\UpdateIncidentRequest;
use App\Http\Requests\Admin\UpdateIncidentStatusRequest;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** CRUD des incidents (personnel) et changement de statut depuis la fiche. */
class IncidentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(IncidentType::class)],
            'severity' => ['nullable', Rule::enum(IncidentSeverity::class)],
            'status' => ['nullable', Rule::enum(IncidentStatus::class)],
            'zone' => ['nullable', 'integer'],
            'depuis' => ['nullable', 'date'],
        ]);

        $incidents = Incident::query()
            ->with(['infrastructure.zone', 'user'])
            ->search($filters['q'] ?? null)
            ->ofType($filters['type'] ?? null)
            ->ofSeverity($filters['severity'] ?? null)
            ->ofStatus($filters['status'] ?? null)
            ->inZone($filters['zone'] ?? null)
            ->when($filters['depuis'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('back.incidents.index', [
            'incidents' => $incidents,
            'filters' => $filters,
            'zones' => Zone::options(),
        ]);
    }

    public function create(Request $request): View
    {
        // ?infrastructure=ID (lien depuis la fiche d'un ouvrage) : infrastructure présélectionnée
        $incident = new Incident([
            'infrastructure_id' => $request->integer('infrastructure') ?: null,
        ]);

        return view('back.incidents.form', $this->formData($incident));
    }

    public function store(StoreIncidentRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('photo');
        // Saisi par le personnel : l'agent connecté est enregistré comme déclarant
        $data['user_id'] = $request->user()->id;

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store(Incident::PHOTO_DIRECTORY, Incident::PHOTO_DISK);
        }

        $incident = Incident::create($data);

        return to_route('admin.incidents.show', $incident)->with('status', "Incident {$incident->reference} créé.");
    }

    public function show(Incident $incident): View
    {
        $incident->load(['infrastructure.zone', 'user', 'interventions' => fn ($q) => $q->with('technician')->orderBy('scheduled_at')]);

        return view('back.incidents.show', ['incident' => $incident]);
    }

    public function edit(Incident $incident): View
    {
        return view('back.incidents.form', $this->formData($incident));
    }

    public function update(UpdateIncidentRequest $request, Incident $incident): RedirectResponse
    {
        $data = $request->safe()->except(['photo', 'remove_photo']);

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

    /** PATCH /admin/incidents/{incident}/statut : resolved_at est géré par le modèle. */
    public function updateStatus(UpdateIncidentStatusRequest $request, Incident $incident): RedirectResponse
    {
        $incident->update($request->validated());

        return to_route('admin.incidents.show', $incident)
            ->with('status', $incident->statusChangedMessage());
    }

    public function destroy(Incident $incident): RedirectResponse
    {
        // Les interventions sont supprimées par la cascade, la photo par le hook deleted du modèle
        $incident->delete();

        return to_route('admin.incidents.index')->with('status', "Incident {$incident->reference} supprimé.");
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
