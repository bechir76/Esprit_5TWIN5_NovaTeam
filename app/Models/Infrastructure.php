<?php

namespace App\Models;

use App\Enums\CapacityUnit;
use App\Enums\InfrastructureStatus;
use App\Enums\InfrastructureType;
use App\Enums\RiskLevel;
use App\Services\Infrastructures\InfrastructureStatsService;
use Database\Factories\InfrastructureFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Ouvrage du réseau (réservoir, conduite, station, forage), rattaché à une zone.
 *
 * Les autres modules référencent cette table avec une colonne infrastructure_id
 * (voir CLAUDE.md) et ajoutent ici leur relation hasMany, par exemple :
 *
 *   public function incidents(): HasMany     { return $this->hasMany(Incident::class); }      // module incidents ✓
 *   public function projects(): HasMany      { return $this->hasMany(Project::class); }       // module projets ✓
 *   public function waterAnalyses(): HasMany { return $this->hasMany(WaterAnalysis::class); } // module qualité de l'eau ✓
 *
 * Plan d'inspections : next_inspection_at est recalculée à l'enregistrement (config/infrastructures.php).
 * Score de risque (risk_*) : App\Services\Infrastructures\RiskAssessment. Historique : spatie/laravel-activitylog.
 */
class Infrastructure extends Model
{
    /** @use HasFactory<InfrastructureFactory> */
    use HasFactory, LogsActivity;

    /** Matériaux proposés dans le formulaire. */
    public const MATERIALS = ['Béton armé', 'Fonte ductile', 'Fonte grise', 'PEHD', 'PVC', 'Acier'];

    /** Emprise des cartes schématiques (Grand Tunis) ; un ouvrage hors emprise est placé au bord. */
    public const MAP_BOUNDS = ['lat' => [36.70, 36.95], 'lng' => [10.05, 10.35]];

    /** Format du code ouvrage : type, gouvernorat, numéro (RES-ARI-004). */
    public const CODE_PATTERN = '/^[A-Z]{3}-[A-Z]{3}-\d{3}$/';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'zone_id',
        'code',
        'name',
        'type',
        'material',
        'capacity',
        'capacity_unit',
        'commissioned_year',
        'nominal_pressure',
        'status',
        'last_inspection_at',
        'latitude',
        'longitude',
        'description',
        'is_public',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => InfrastructureType::class,
        'status' => InfrastructureStatus::class,
        'capacity_unit' => CapacityUnit::class,
        'capacity' => 'decimal:2',
        'nominal_pressure' => 'decimal:2',
        'commissioned_year' => 'integer',
        'last_inspection_at' => 'date',
        'latitude' => 'float',
        'longitude' => 'float',
        'is_public' => 'boolean',
        'next_inspection_at' => 'date',
        'inspection_overdue_notified_at' => 'datetime',
        'risk_score' => 'integer',
        'risk_level' => RiskLevel::class,
        'risk_factors' => 'array',
        'risk_recommendations' => 'array',
        'risk_computed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Prochaine inspection recalculée quand la date, l'état ou le type changent ;
        // l'alerte de retard pourra être renvoyée pour la nouvelle échéance
        static::saving(function (self $infrastructure): void {
            if ($infrastructure->next_inspection_at === null || $infrastructure->isDirty(['last_inspection_at', 'status', 'type'])) {
                $infrastructure->next_inspection_at = $infrastructure->computeNextInspection();
            }

            if ($infrastructure->isDirty('next_inspection_at')) {
                $infrastructure->inspection_overdue_notified_at = null;
            }
        });

        // Statistiques du réseau (InfrastructureStatsService) recalculées après toute modification
        static::saved(fn () => Cache::forget(InfrastructureStatsService::CACHE_KEY));
        static::deleted(fn () => Cache::forget(InfrastructureStatsService::CACHE_KEY));
    }

    /** Journal d'audit : changements d'état et des données principales (les inspections et recalculs sont journalisés à part). */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('infrastructures')
            ->logOnly(['code', 'name', 'type', 'status', 'zone_id', 'material', 'last_inspection_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** @return BelongsTo<Zone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /** @return HasMany<Incident, $this> */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /** @return HasMany<WaterAnalysis, $this> */
    public function waterAnalyses(): HasMany
    {
        return $this->hasMany(WaterAnalysis::class);
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** Capacité lisible : « 5 000 m³ », « 4,1 km ». */
    public function formattedCapacity(): string
    {
        return self::formatNumber($this->capacity).' '.$this->capacity_unit->label();
    }

    /** Pression lisible : « 4,5 bar », ou null. */
    public function formattedPressure(): ?string
    {
        return $this->nominal_pressure === null ? null : self::formatNumber($this->nominal_pressure).' bar';
    }

    /** Âge en années (année en cours moins année de mise en service). */
    public function age(): int
    {
        return max(0, (int) now()->year - (int) $this->commissioned_year);
    }

    /** Fréquence d'inspection : la plus courte entre celle du type et celle de l'état. */
    public function inspectionIntervalMonths(): int
    {
        $months = config('infrastructures.inspection_months');

        return (int) min(
            $months['by_type'][$this->type?->value] ?? 12,
            $months['by_status'][$this->status?->value] ?? 12,
        );
    }

    /** Dernière inspection + fréquence ; jamais inspecté : à inspecter dès aujourd'hui. */
    public function computeNextInspection(): Carbon
    {
        return $this->last_inspection_at
            ? $this->last_inspection_at->copy()->addMonthsNoOverflow($this->inspectionIntervalMonths())
            : today();
    }

    public function isInspectionOverdue(): bool
    {
        return $this->next_inspection_at !== null && $this->next_inspection_at->lt(today());
    }

    /** Nombre de jours de retard de l'inspection (0 si à jour). */
    public function inspectionDaysOverdue(): int
    {
        return $this->isInspectionOverdue() ? (int) $this->next_inspection_at->diffInDays(today()) : 0;
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** Recherche par nom ou code ouvrage. */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $q) => $q->where(fn (Builder $q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('code', 'like', "%{$term}%")));
    }

    /** Un type ou une liste de types ; vide = tous. */
    public function scopeOfType(Builder $query, InfrastructureType|string|array|null $type): void
    {
        $types = array_map(fn ($t) => $t instanceof InfrastructureType ? $t->value : $t, array_filter((array) $type));
        $query->when($types, fn (Builder $q) => $q->whereIn('type', $types));
    }

    /** Un état ou une liste d'états ; vide = tous. */
    public function scopeOfStatus(Builder $query, InfrastructureStatus|string|array|null $status): void
    {
        $statuses = array_map(fn ($s) => $s instanceof InfrastructureStatus ? $s->value : $s, array_filter((array) $status));
        $query->when($statuses, fn (Builder $q) => $q->whereIn('status', $statuses));
    }

    /** Ouvrages d'une zone (modèle ou id) ; vide = toutes. */
    public function scopeInZone(Builder $query, Zone|int|string|null $zone): void
    {
        $query->when($zone, fn (Builder $q) => $q->where('zone_id', $zone instanceof Zone ? $zone->getKey() : $zone));
    }

    /**
     * Filtres de la liste du back office : q (recherche), type, status, zone ; une valeur vide ne filtre pas.
     * Utilisé par le composant Livewire InfrastructureTable (liste /admin/infrastructures).
     *
     * @param  array{q?: ?string, type?: ?string, status?: ?string, zone?: int|string|null, inspection?: ?string, risk?: ?string, min_age?: ?int}  $filters
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $query->search($filters['q'] ?? null)
            ->ofType($filters['type'] ?? null)
            ->ofStatus($filters['status'] ?? null)
            ->inZone($filters['zone'] ?? null)
            ->ofRiskLevel($filters['risk'] ?? null)
            ->olderThan($filters['min_age'] ?? null)
            ->when(($filters['inspection'] ?? null) === 'overdue', fn (Builder $q) => $q->inspectionOverdue());
    }

    /**
     * Filtres nettoyés (liste Livewire, carte, recherche en langage naturel et export CSV) :
     * une valeur hors enum, hors bornes ou non numérique est ignorée.
     *
     * @param  array<string, mixed>  $input
     * @return array{q: ?string, type: ?string, status: ?string, zone: ?int, inspection: ?string, risk: ?string, min_age: ?int, sort: ?string}
     */
    public static function normalizeFilters(array $input): array
    {
        $q = mb_substr(trim((string) ($input['q'] ?? '')), 0, 100);
        $zone = (string) ($input['zone'] ?? '');
        $age = (string) ($input['min_age'] ?? '');

        return [
            'q' => $q !== '' ? $q : null,
            'type' => InfrastructureType::tryFrom((string) ($input['type'] ?? ''))?->value,
            'status' => InfrastructureStatus::tryFrom((string) ($input['status'] ?? ''))?->value,
            'zone' => ctype_digit($zone) ? (int) $zone : null,
            'inspection' => ($input['inspection'] ?? null) === 'overdue' ? 'overdue' : null,
            'risk' => RiskLevel::tryFrom((string) ($input['risk'] ?? ''))?->value,
            'min_age' => ctype_digit($age) && (int) $age > 0 && (int) $age <= 150 ? (int) $age : null,
            'sort' => ($input['sort'] ?? null) === 'risk' ? 'risk' : null,
        ];
    }

    /** Niveau de risque calculé ; vide = tous. */
    public function scopeOfRiskLevel(Builder $query, RiskLevel|string|null $level): void
    {
        $value = $level instanceof RiskLevel ? $level->value : $level;
        $query->when($value, fn (Builder $q) => $q->where('risk_level', $value));
    }

    /** Ouvrages d'au moins $years ans (année de mise en service) ; vide = tous. */
    public function scopeOlderThan(Builder $query, ?int $years): void
    {
        $query->when($years, fn (Builder $q) => $q->where('commissioned_year', '<=', (int) now()->year - $years));
    }

    /** Tri de la liste : par risque décroissant (non évalués en dernier) ou par code. */
    public function scopeSorted(Builder $query, ?string $sort): void
    {
        if ($sort === 'risk') {
            $query->orderByRaw('risk_score is null')->orderByDesc('risk_score');
        }

        $query->orderBy('code');
    }

    /** Prochaine inspection dépassée. */
    public function scopeInspectionOverdue(Builder $query): void
    {
        $query->whereNotNull('next_inspection_at')->whereDate('next_inspection_at', '<', today());
    }

    /** Ouvrages évalués, du plus au moins à risque. */
    public function scopeMostAtRisk(Builder $query): void
    {
        $query->whereNotNull('risk_score')->orderByDesc('risk_score')->orderBy('code');
    }

    /** Ouvrages visibles sur la carte publique. */
    public function scopePublic(Builder $query): void
    {
        $query->where('is_public', true);
    }

    /** Ouvrages « à risque » : à surveiller ou critiques. */
    public function scopeAtRisk(Builder $query): void
    {
        $query->ofStatus(array_filter(InfrastructureStatus::cases(), fn (InfrastructureStatus $s) => $s->isAtRisk()));
    }

    /** 5000 => « 5 000 », 4.10 => « 4,1 ». */
    protected static function formatNumber(float|string $value): string
    {
        $value = (float) $value;
        $decimals = $value == floor($value) ? 0 : (round($value, 1) == $value ? 1 : 2);

        return number_format($value, $decimals, ',', ' ');
    }
}
