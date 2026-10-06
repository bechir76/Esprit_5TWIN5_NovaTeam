<?php

namespace App\Models;

use App\Enums\AnalysisStatus;
use Database\Factories\WaterAnalysisFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Analyse de la qualité de l'eau réalisée sur une infrastructure.
 * Elle regroupe plusieurs mesures (Measurement). Son statut est
 * recalculé automatiquement dès qu'une mesure est enregistrée.
 */
class WaterAnalysis extends Model
{
    /** @use HasFactory<WaterAnalysisFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'infrastructure_id',
        'sampled_at',
        'sampling_point',
        'laboratory',
        'sampled_by',
        'status',
        'notes',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'sampled_at' => 'datetime',
        'status'     => AnalysisStatus::class,
    ];

    // ---------------------------------------------------------------- Hooks

    protected static function booted(): void
    {
        // Générer la référence automatiquement si elle n'est pas fournie
        static::creating(function (self $analysis): void {
            if (empty($analysis->reference)) {
                $analysis->reference = self::generateReference();
            }
        });
    }

    // ---------------------------------------------------------------- Relations

    /** @return BelongsTo<Infrastructure, $this> */
    public function infrastructure(): BelongsTo
    {
        return $this->belongsTo(Infrastructure::class);
    }

    /**
     * Technicien ayant effectué le prélèvement (nullable).
     *
     * @return BelongsTo<User, $this>
     */
    public function sampledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sampled_by');
    }

    /** @return HasMany<Measurement, $this> */
    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }

    // ---------------------------------------------------------------- Accesseurs

    /**
     * Pourcentage de mesures conformes (0–100), ou null si aucune mesure.
     *
     * @return int|null
     */
    public function getComplianceRateAttribute(): ?int
    {
        $total = $this->measurements->count();

        if ($total === 0) {
            return null;
        }

        return (int) round($this->measurements->where('is_compliant', true)->count() / $total * 100);
    }

    // ---------------------------------------------------------------- Recalcul du statut

    /**
     * Recalcule le statut de l'analyse à partir de ses mesures.
     * Si au moins une mesure est non conforme → non_conforme.
     * Si toutes les mesures sont conformes → validée (sinon : on laisse en_attente s'il n'y a pas de mesures).
     */
    public function recalculateStatus(): void
    {
        $measurements = $this->measurements()->get();

        if ($measurements->isEmpty()) {
            // Pas encore de mesures : on reste en attente
            $this->update(['status' => AnalysisStatus::EnAttente]);

            return;
        }

        $hasNonCompliant = $measurements->contains('is_compliant', false);

        $this->update([
            'status' => $hasNonCompliant ? AnalysisStatus::NonConforme : AnalysisStatus::Validee,
        ]);
    }

    // ---------------------------------------------------------------- Génération de la référence

    /**
     * Génère une référence unique de la forme ANA-2026-0001.
     * Basée sur le max(id) courant pour garantir l'unicité même en SQLite.
     */
    public static function generateReference(): string
    {
        $year = now()->year;
        $prefix = "ANA-{$year}-";

        // On prend le numéro le plus élevé déjà attribué pour cette année
        $lastRef = self::query()
            ->where('reference', 'like', "{$prefix}%")
            ->orderByDesc('reference')
            ->value('reference');

        $seq = $lastRef ? ((int) substr($lastRef, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    // ---------------------------------------------------------------- Scopes

    /** Recherche par référence, point de prélèvement ou laboratoire. */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $q) => $q->where(fn (Builder $q) => $q
            ->where('reference', 'like', "%{$term}%")
            ->orWhere('sampling_point', 'like', "%{$term}%")
            ->orWhere('laboratory', 'like', "%{$term}%")));
    }

    /** Filtrage par statut. */
    public function scopeOfStatus(Builder $query, AnalysisStatus|string|null $status): void
    {
        $query->when($status, fn (Builder $q) => $q->where(
            'status',
            $status instanceof AnalysisStatus ? $status->value : $status
        ));
    }

    /** Analyses ayant au moins une mesure non conforme. */
    public function scopeNonCompliant(Builder $query): void
    {
        $query->whereHas('measurements', fn (Builder $q) => $q->where('is_compliant', false));
    }

    /**
     * Analyses rattachées à une zone (via l'infrastructure).
     *
     * @param Builder<WaterAnalysis> $query
     */
    public function scopeInZone(Builder $query, Zone|int|string|null $zone): void
    {
        $query->when($zone, fn (Builder $q) => $q->whereHas('infrastructure',
            fn (Builder $infra) => $infra->where('zone_id', $zone instanceof Zone ? $zone->getKey() : $zone)
        ));
    }
}
