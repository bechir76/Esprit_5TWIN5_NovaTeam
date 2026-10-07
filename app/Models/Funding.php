<?php

namespace App\Models;

use App\Enums\FundingSource;
use Database\Factories\FundingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Financement d'un projet : municipalité, association, entreprise ou contribution citoyenne.
 *
 * La référence (FIN-2026-0001) est générée à la création ; après chaque création,
 * modification ou suppression, le statut du projet est recalculé (Project::refreshStatus()).
 */
class Funding extends Model
{
    /** @use HasFactory<FundingFactory> */
    use HasFactory;

    /** Contribution citoyenne en ligne (paiement simulé), en TND. */
    public const MIN_CONTRIBUTION = 5;

    public const MAX_CONTRIBUTION = 10000;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'source_type',
        'donor_name',
        'user_id',
        'amount',
        'funded_at',
        'reference',
        'note',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'source_type' => FundingSource::class,
        'amount' => 'decimal:2',
        'funded_at' => 'date',
    ];

    // ---------------------------------------------------------------- Hooks

    protected static function booted(): void
    {
        // Référence générée automatiquement si elle n'est pas fournie
        static::creating(function (self $funding): void {
            if (empty($funding->reference)) {
                $funding->reference = self::generateReference();
            }
        });

        // Statut du projet recalculé ; si le financement change de projet, l'ancien est aussi recalculé
        static::saved(function (self $funding): void {
            $funding->unsetRelation('project');
            $funding->project->refreshStatus();

            if ($funding->wasChanged('project_id') && ($previous = Project::find($funding->getOriginal('project_id')))) {
                $previous->refreshStatus();
            }
        });

        static::deleted(function (self $funding): void {
            $funding->project?->refreshStatus();
        });
    }

    // ---------------------------------------------------------------- Relations

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Citoyen contributeur (nullable : financement saisi par le personnel ou compte supprimé).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ---------------------------------------------------------------- Génération de la référence

    /**
     * Génère une référence unique de la forme FIN-2026-0001,
     * à partir du numéro le plus élevé déjà attribué cette année (même principe que les incidents).
     */
    public static function generateReference(): string
    {
        $prefix = 'FIN-'.now()->year.'-';

        $lastRef = self::query()
            ->where('reference', 'like', "{$prefix}%")
            ->orderByDesc('reference')
            ->value('reference');

        $seq = $lastRef ? ((int) substr($lastRef, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    // ---------------------------------------------------------------- Scopes

    /** Recherche par référence ou nom du financeur. */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $q) => $q->where(fn (Builder $q) => $q
            ->where('reference', 'like', "%{$term}%")
            ->orWhere('donor_name', 'like', "%{$term}%")));
    }

    /** Une source ou une liste de sources ; vide = toutes. */
    public function scopeOfSource(Builder $query, FundingSource|string|array|null $source): void
    {
        $sources = array_map(fn ($s) => $s instanceof FundingSource ? $s->value : $s, array_filter((array) $source));
        $query->when($sources, fn (Builder $q) => $q->whereIn('source_type', $sources));
    }

    /** Financements d'un projet (modèle ou id) ; vide = tous. */
    public function scopeForProject(Builder $query, Project|int|string|null $project): void
    {
        $query->when($project, fn (Builder $q) => $q->where('project_id', $project instanceof Project ? $project->getKey() : $project));
    }

    /** Financements reçus entre deux dates (Y-m-d, bornes incluses, chacune facultative). */
    public function scopeFundedBetween(Builder $query, ?string $from, ?string $to): void
    {
        $query->when($from, fn (Builder $q) => $q->whereDate('funded_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('funded_at', '<=', $to));
    }
}
