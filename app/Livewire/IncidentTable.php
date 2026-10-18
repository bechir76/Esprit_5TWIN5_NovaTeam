<?php

namespace App\Livewire;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\Incident;
use App\Models\Zone;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Liste admin des incidents (/admin/incidents) : recherche en direct, filtres combinables (type, gravité,
 * statut, zone, période, avec / sans infrastructure, en retard) et tri. Les filtres restent dans l'URL
 * (?q=…&du=…) : la page est partageable, l'export CSV reprend les mêmes paramètres et le formulaire GET
 * fonctionne sans JavaScript. Requête : scopes Incident::filter() et Incident::sorted().
 */
class IncidentTable extends Component
{
    use WithPagination;

    /** Propriétés filtrantes (paramètres d'URL). */
    private const FILTERS = ['q', 'type', 'severity', 'status', 'zone', 'du', 'au', 'infrastructure', 'retard', 'tri'];

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $severity = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $zone = '';

    #[Url(except: '')]
    public string $du = '';

    #[Url(except: '')]
    public string $au = '';

    #[Url(except: '')]
    public string $infrastructure = '';

    #[Url(except: '')]
    public string $retard = '';

    #[Url(except: '')]
    public string $tri = '';

    /** Un filtre modifié : retour à la première page. */
    public function updated(string $property): void
    {
        if (in_array($property, self::FILTERS, true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(...self::FILTERS);
        $this->resetPage();
    }

    /**
     * Filtres nettoyés : une valeur hors enum, une date invalide ou une zone non numérique est ignorée.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $q = mb_substr(trim($this->q), 0, 100);
        $date = fn (string $value) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;

        return [
            'q' => $q !== '' ? $q : null,
            'type' => IncidentType::tryFrom($this->type)?->value,
            'severity' => IncidentSeverity::tryFrom($this->severity)?->value,
            'status' => IncidentStatus::tryFrom($this->status)?->value,
            'zone' => ctype_digit($this->zone) ? (int) $this->zone : null,
            'du' => $date($this->du),
            'au' => $date($this->au),
            'infrastructure' => in_array($this->infrastructure, ['avec', 'sans'], true) ? $this->infrastructure : null,
            'retard' => $this->retard === '1' ? '1' : null,
        ];
    }

    public function render(): View
    {
        $filters = $this->filters();
        $sort = in_array($this->tri, ['anciens', 'urgence', 'gravite'], true) ? $this->tri : null;

        return view('livewire.incident-table', [
            'incidents' => Incident::query()
                ->with(['infrastructure.zone', 'user'])
                ->filter($filters)
                ->sorted($sort)
                ->paginate(10)
                ->withQueryString(),
            'zones' => Zone::options(),
            'filtered' => array_filter($filters) !== [] || $sort !== null,
            // Lien d'export : mêmes paramètres que la liste affichée
            'exportUrl' => route('admin.incidents.export', array_filter($filters + ['tri' => $sort])),
        ]);
    }
}
