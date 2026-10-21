<?php

namespace App\Livewire;

use App\Enums\InfrastructureStatus;
use App\Enums\InfrastructureType;
use App\Enums\RiskLevel;
use App\Models\Infrastructure;
use App\Models\Zone;
use App\Services\Ai\InfrastructureAiService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Liste admin des infrastructures (/admin/infrastructures) : recherche en direct, filtres type / état / zone /
 * risque / inspection en retard, tri par score de risque et pagination sans rechargement. Les filtres restent dans
 * l'URL (?q=…&type=…) : la page reste partageable et fonctionne aussi sans JavaScript (formulaire GET).
 * B2 : « Rechercher en langage naturel » traduit une phrase en ces mêmes filtres (InfrastructureAiService::parseSearch),
 * affichés sous forme de puces. Requête : scope Infrastructure::filter().
 */
class InfrastructureTable extends Component
{
    use WithPagination;

    /** Propriétés de filtre et clé correspondante dans Infrastructure::normalizeFilters(). */
    protected const FILTERS = ['q' => 'q', 'type' => 'type', 'status' => 'status', 'zone' => 'zone', 'risk' => 'risk', 'inspection' => 'inspection', 'minAge' => 'min_age', 'sort' => 'sort'];

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $zone = '';

    /** Niveau de risque (faible, moyen, eleve, critique). */
    #[Url(except: '')]
    public string $risk = '';

    /** « overdue » : inspections en retard seulement. */
    #[Url(except: '')]
    public string $inspection = '';

    /** Âge minimum en années (recherche en langage naturel). */
    #[Url(as: 'age', except: '')]
    public string $minAge = '';

    /** « risk » : du plus au moins à risque ; vide : par code. */
    #[Url(except: '')]
    public string $sort = '';

    /** Phrase saisie dans « Rechercher en langage naturel ». */
    public string $sentence = '';

    /** Dernière phrase interprétée et origine de l'interprétation (« ia » ou « regles »). */
    public ?string $interpreted = null;

    public ?string $interpretedBy = null;

    /** Un filtre ou le tri modifié : retour à la première page. */
    public function updated(string $property): void
    {
        if (array_key_exists($property, self::FILTERS)) {
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
        $this->reset('q', 'type', 'status', 'zone', 'risk', 'inspection', 'minAge', 'interpreted', 'interpretedBy');
        $this->resetPage();
    }

    /** B2 : la phrase devient un jeu de filtres autorisés (jamais de SQL), qui remplace les filtres courants. */
    public function interpret(InfrastructureAiService $ai): void
    {
        $this->validate(['sentence' => ['required', 'string', 'max:200']], [
            'sentence.required' => 'Décrivez les ouvrages recherchés, par exemple « réservoirs critiques à Ariana ».',
            'sentence.max' => 'La recherche ne doit pas dépasser 200 caractères.',
        ]);

        $key = 'ai-infrastructure-search:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, config('ai.rate_limit'))) {
            $this->addError('sentence', 'Trop de recherches : réessayez dans '.RateLimiter::availableIn($key).' secondes.');

            return;
        }
        RateLimiter::hit($key, 60);

        ['filters' => $filters, 'source' => $source] = $ai->parseSearch($this->sentence);

        $this->q = '';
        foreach (self::FILTERS as $property => $filter) {
            if ($property !== 'q') {
                $this->{$property} = (string) ($filters[$filter] ?? '');
            }
        }

        $this->interpreted = $this->sentence;
        $this->interpretedBy = $source;
        $this->resetPage();
    }

    /** Retire une puce (un filtre) de la recherche interprétée. */
    public function removeFilter(string $property): void
    {
        if (array_key_exists($property, self::FILTERS)) {
            $this->{$property} = '';
            $this->resetPage();
        }
    }

    /**
     * Filtres nettoyés (Infrastructure::normalizeFilters) : une valeur hors enum, hors bornes ou non numérique est ignorée.
     *
     * @return array{q: ?string, type: ?string, status: ?string, zone: ?int, inspection: ?string, risk: ?string, min_age: ?int, sort: ?string}
     */
    protected function filters(): array
    {
        $input = [];
        foreach (self::FILTERS as $property => $filter) {
            $input[$filter] = $this->{$property};
        }

        return Infrastructure::normalizeFilters($input);
    }

    /**
     * Puces des filtres actifs : propriété => libellé.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $zones
     * @return array<string, string>
     */
    protected function chips(array $filters, array $zones): array
    {
        return array_filter([
            'type' => $filters['type'] ? 'Type : '.InfrastructureType::from($filters['type'])->label() : null,
            'status' => $filters['status'] ? 'État : '.InfrastructureStatus::from($filters['status'])->label() : null,
            'zone' => $filters['zone'] ? 'Zone : '.($zones[$filters['zone']] ?? '?') : null,
            'risk' => $filters['risk'] ? 'Risque : '.RiskLevel::from($filters['risk'])->label() : null,
            'inspection' => $filters['inspection'] ? 'Inspection en retard' : null,
            'minAge' => $filters['min_age'] ? "Âge ≥ {$filters['min_age']} ans" : null,
            'sort' => $filters['sort'] ? 'Tri : risque décroissant' : null,
        ]);
    }

    public function render(): View
    {
        Gate::authorize('viewAny', Infrastructure::class);

        $filters = $this->filters();
        $zones = Zone::options();

        return view('livewire.infrastructure-table', [
            'infrastructures' => Infrastructure::query()
                ->with('zone')
                ->filter($filters)
                ->sorted($filters['sort'])
                ->paginate(10)
                ->withQueryString(),
            'zones' => $zones,
            'chips' => $this->chips($filters, $zones),
            'filtered' => array_filter(array_diff_key($filters, ['sort' => true])) !== [],
            // Export CSV avec les filtres et le tri actifs (InfrastructurePolicy::export)
            'exportUrl' => auth()->user()->can('export', Infrastructure::class)
                ? route('admin.infrastructures.export', array_filter($filters))
                : null,
        ]);
    }
}
