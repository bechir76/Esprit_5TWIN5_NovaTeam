<?php

namespace App\Livewire;

use App\Enums\InterventionStatus;
use App\Models\Intervention;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Liste admin des interventions (/admin/interventions) : recherche en direct, filtres statut / date / équipe
 * et tri, conservés dans l'URL ; le formulaire GET fonctionne aussi sans JavaScript.
 */
class InterventionTable extends Component
{
    use WithPagination;

    private const FILTERS = ['q', 'status', 'date', 'team', 'tri'];

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $date = '';

    #[Url(except: '')]
    public string $team = '';

    #[Url(except: '')]
    public string $tri = '';

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

    public function render(): View
    {
        $q = mb_substr(trim($this->q), 0, 100);
        $status = InterventionStatus::tryFrom($this->status)?->value;
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) ? $this->date : null;
        $team = in_array($this->team, Intervention::TEAMS, true) ? $this->team : null;
        $sort = in_array($this->tri, ['anciennes', 'prochaines'], true) ? $this->tri : null;

        $query = Intervention::query()
            ->with(['incident.infrastructure', 'technician'])
            ->search($q !== '' ? $q : null)
            ->ofStatus($status)
            ->scheduledOn($date)
            ->when($team, fn ($query, $team) => $query->where('team', $team));

        match ($sort) {
            'anciennes' => $query->orderBy('scheduled_at'),
            // Prochaines interventions actives d'abord
            'prochaines' => $query->active()->where('scheduled_at', '>=', now()->startOfDay())->orderBy('scheduled_at'),
            default => $query->orderByDesc('scheduled_at'),
        };

        return view('livewire.intervention-table', [
            'interventions' => $query->paginate(10)->withQueryString(),
            'filtered' => array_filter([$q, $status, $date, $team, $sort]) !== [],
        ]);
    }
}
