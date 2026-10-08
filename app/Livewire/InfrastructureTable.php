<?php

namespace App\Livewire;

use App\Enums\InfrastructureStatus;
use App\Enums\InfrastructureType;
use App\Models\Infrastructure;
use App\Models\Zone;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Liste admin des infrastructures (/admin/infrastructures) : recherche en direct, filtres type / état / zone
 * et pagination sans rechargement. Les filtres restent dans l'URL (?q=…&type=…) : la page reste partageable
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

    /** Un filtre modifié : retour à la première page. */
    public function updated(string $property): void
    {
        if (in_array($property, ['q', 'type', 'status', 'zone'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('q', 'type', 'status', 'zone');
        $this->resetPage();
    }

    /**
     * Filtres nettoyés : une valeur hors enum ou non numérique est ignorée.
     *
     * @return array{q: ?string, type: ?string, status: ?string, zone: ?int}
     */
    protected function filters(): array
    {
        $q = mb_substr(trim($this->q), 0, 100);

        return [
            'q' => $q !== '' ? $q : null,
            'type' => InfrastructureType::tryFrom($this->type)?->value,
            'status' => InfrastructureStatus::tryFrom($this->status)?->value,
            'zone' => ctype_digit($this->zone) ? (int) $this->zone : null,
        ];
    }

    public function render(): View
    {
        $filters = $this->filters();

        return view('livewire.infrastructure-table', [
            'infrastructures' => Infrastructure::query()
                ->with('zone')
                ->filter($filters)
                ->orderBy('code')
                ->paginate(10)
                ->withQueryString(),
            'zones' => Zone::options(),
            'filtered' => array_filter($filters) !== [],
        ]);
    }
}
