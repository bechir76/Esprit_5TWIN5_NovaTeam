<?php

namespace App\Livewire;

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Zone;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Liste admin des projets (/admin/projets) : recherche, filtres catégorie / statut / zone / % financé /
 * date limite, tri (échéance, score de priorité, avancement) et pagination sans rechargement.
 * Les filtres restent dans l'URL (?q=…&status=…). Montants : dons confirmés (withCollected), sans N+1.
 */
class ProjectTable extends Component
{
    use WithPagination;

    public const SORTS = [
        'deadline' => 'Échéance la plus proche',
        'score' => 'Score de priorité',
        'progress' => 'Avancement',
        'recent' => 'Plus récents',
    ];

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $zone = '';

    /** Avancement minimal (%). */
    #[Url(except: '')]
    public string $funded = '';

    /** Date limite au plus tard (Y-m-d). */
    #[Url(except: '')]
    public string $before = '';

    #[Url(except: 'deadline')]
    public string $sort = 'deadline';

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('q', 'status', 'category', 'zone', 'funded', 'before', 'sort');
        $this->resetPage();
    }

    public function render(): View
    {
        $q = mb_substr(trim($this->q), 0, 100);
        $before = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->before) ? $this->before : null;

        $query = Project::query()
            ->with('infrastructure.zone')
            ->withCollected()
            ->search($q !== '' ? $q : null)
            ->ofStatus(ProjectStatus::tryFrom($this->status))
            ->ofCategory(ProjectCategory::tryFrom($this->category))
            ->inZone(ctype_digit($this->zone) ? (int) $this->zone : null)
            ->fundedAtLeast(ctype_digit($this->funded) ? min(100, (int) $this->funded) : null)
            ->when($before, fn ($query, $date) => $query->whereDate('end_date', '<=', $date));

        match (array_key_exists($this->sort, self::SORTS) ? $this->sort : 'deadline') {
            'score' => $query->orderByRaw('ai_priority_score is null')->orderByDesc('ai_priority_score'),
            'progress' => $query->orderByProgress(),
            'recent' => $query->latest(),
            default => $query->orderBy('end_date'),
        };

        return view('livewire.project-table', [
            'projects' => $query->orderBy('id')->paginate(10)->withQueryString(),
            'zones' => Zone::options(),
            'sorts' => self::SORTS,
            'filtered' => $q !== '' || $this->status !== '' || $this->category !== '' || $this->zone !== '' || $this->funded !== '' || $before !== null,
        ]);
    }
}
