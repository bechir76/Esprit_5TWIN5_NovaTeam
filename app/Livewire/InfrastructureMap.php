<?php

namespace App\Livewire;

use App\Models\Infrastructure;
use App\Models\Zone;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Carte Leaflet du réseau (site citoyen /carte et liste admin) : repères colorés selon l'état, regroupés
 * (markercluster) et filtres sans rechargement. Les repères initiaux sont écrits avec Js::from, puis renvoyés par
 * l'événement « infrastructure-map-updated » à chaque changement de filtre.
 * $public (verrouillé) : ouvrages publics seulement, version réduite (nom, type, zone, état ; filtres type / état / zone).
 * Console : en plus code, risque, prochaine inspection et retard dans la popup, et filtre par niveau de risque.
 */
class InfrastructureMap extends Component
{
    #[Locked]
    public bool $public = false;

    public string $type = '';

    public string $status = '';

    public string $zone = '';

    public string $risk = '';

    public function mount(bool $public = false, string $type = '', string $status = '', string $zone = ''): void
    {
        $this->public = $public;
        $this->authorizeAccess();

        // Filtres initiaux passés par la page (?type=…&status=…&zone=…), nettoyés par Infrastructure::normalizeFilters()
        $filters = Infrastructure::normalizeFilters(compact('type', 'status', 'zone'));
        $this->type = $filters['type'] ?? '';
        $this->status = $filters['status'] ?? '';
        $this->zone = (string) ($filters['zone'] ?? '');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['type', 'status', 'zone', 'risk'], true)) {
            $this->dispatch('infrastructure-map-updated', markers: $this->markers());
        }
    }

    public function resetFilters(): void
    {
        $this->reset('type', 'status', 'zone', 'risk');
        $this->dispatch('infrastructure-map-updated', markers: $this->markers());
    }

    /**
     * Repères affichés : ouvrages géolocalisés filtrés (publics seulement côté citoyen).
     *
     * @return list<array<string, mixed>>
     */
    public function markers(): array
    {
        $this->authorizeAccess();

        return Infrastructure::query()
            ->with('zone')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($this->public, fn ($q) => $q->public())
            // Le filtre de risque n'existe que dans la console
            ->filter(Infrastructure::normalizeFilters($this->only(['type', 'status', 'zone']) + ['risk' => $this->public ? '' : $this->risk]))
            ->orderBy('name')
            ->get()
            ->map(fn (Infrastructure $i) => [
                'name' => $i->name,
                'type' => $i->type->label(),
                'zone' => $i->zone->name,
                'status' => $i->status->value,
                'statusLabel' => $i->status->label(),
                'color' => $i->status->hex(),
                'lat' => $i->latitude,
                'lng' => $i->longitude,
                'url' => $this->public ? route('incidents.create') : route('admin.infrastructures.show', $i),
            ] + ($this->public ? [] : [
                // Console seulement : ces informations désignent les ouvrages les plus fragiles du réseau
                'code' => $i->code,
                'riskScore' => $i->risk_score,
                'riskLabel' => $i->risk_level?->label(),
                'riskColor' => $i->risk_level?->hex(),
                'nextInspection' => $i->next_inspection_at?->format('d/m/Y'),
                'overdue' => $i->isInspectionOverdue(),
            ]))
            ->values()
            ->all();
    }

    /** Affiché le temps du chargement différé (<livewire:infrastructure-map lazy />). */
    public function placeholder(): string
    {
        return '<div class="card grid h-[300px] place-items-center text-muted">Chargement de la carte…</div>';
    }

    /** Côté console : InfrastructurePolicy::viewAny (le citoyen ne voit que la carte publique). */
    protected function authorizeAccess(): void
    {
        if (! $this->public) {
            Gate::authorize('viewAny', Infrastructure::class);
        }
    }

    public function render(): View
    {
        $markers = $this->markers();

        return view('livewire.infrastructure-map', [
            'markers' => $markers,
            'zones' => Zone::options(),
            'filtered' => $this->type.$this->status.$this->zone.($this->public ? '' : $this->risk) !== '',
        ]);
    }
}
