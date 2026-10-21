<?php

namespace App\Livewire;

use App\Models\Infrastructure;
use App\Models\Zone;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Liste admin des infrastructures (/admin/infrastructures) : recherche en direct, filtres type / état / zone /
 * inspection en retard, tri par score de risque et pagination sans rechargement. Les filtres restent dans l'URL (?q=…&type=…) : la page reste partageable
 * et fonctionne aussi sans JavaScript (formulaire GET). Requête : scope Infrastructure::filter().
 */
class InfrastructureTable extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $zone = '';

    /** « overdue » : inspections en retard seulement. */
    #[Url(except: '')]
    public string $inspection = '';

    /** « risk » : du plus au moins à risque ; vide : par code. */
    #[Url(except: '')]
    public string $sort = '';

    /** Un filtre ou le tri modifié : retour à la première page. */
    public function updated(string $property): void
    {
        if (in_array($property, ['q', 'type', 'status', 'zone', 'inspection', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function sortBy(string $sort): void
    {
        $this->sort = $sort === 'risk' && $this->sort !== 'risk' ? 'risk' : '';
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('q', 'type', 'status', 'zone', 'inspection');
        $this->resetPage();
    }

    /**
     * Filtres nettoyés (Infrastructure::normalizeFilters) : une valeur hors enum ou non numérique est ignorée.
     *
     * @return array{q: ?string, type: ?string, status: ?string, zone: ?int, inspection: ?string, sort: ?string}
     */
    protected function filters(): array
    {
        return Infrastructure::normalizeFilters($this->only(['q', 'type', 'status', 'zone', 'inspection', 'sort']));
    }

    public function render(): View
    {
        Gate::authorize('viewAny', Infrastructure::class);

        $filters = $this->filters();

        return view('livewire.infrastructure-table', [
            'infrastructures' => Infrastructure::query()
                ->with('zone')
                ->filter($filters)
                ->sorted($filters['sort'])
                ->paginate(10)
                ->withQueryString(),
            'zones' => Zone::options(),
            'filtered' => array_filter(array_diff_key($filters, ['sort' => true])) !== [],
            // Export CSV avec les filtres et le tri actifs (InfrastructurePolicy::export)
            'exportUrl' => auth()->user()->can('export', Infrastructure::class)
                ? route('admin.infrastructures.export', array_filter($filters))
                : null,
        ]);
    }
}
