<?php

namespace App\Models;

use App\Enums\CapacityUnit;
use App\Enums\InfrastructureStatus;
use App\Enums\InfrastructureType;
use Database\Factories\InfrastructureFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ouvrage du réseau (réservoir, conduite, station, forage), rattaché à une zone.
 *
 * Les autres modules référencent cette table avec une colonne infrastructure_id
 * (voir CLAUDE.md) et ajoutent ici leur relation hasMany, par exemple :
 *
 *   public function incidents(): HasMany     { return $this->hasMany(Incident::class); }      // module incidents
 *   public function projects(): HasMany      { return $this->hasMany(Project::class); }       // module projets
 *   public function waterAnalyses(): HasMany { return $this->hasMany(WaterAnalysis::class); } // module qualité de l'eau ✓
 */
class Infrastructure extends Model
{
    /** @use HasFactory<InfrastructureFactory> */
    use HasFactory;

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
    ];

    /** @return BelongsTo<Zone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /** @return HasMany<WaterAnalysis, $this> */
    public function waterAnalyses(): HasMany
    {
        return $this->hasMany(WaterAnalysis::class);
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
