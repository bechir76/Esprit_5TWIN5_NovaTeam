<?php

namespace App\Models;

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Projet de rénovation financé par des municipalités, associations, entreprises et citoyens (Funding),
 * éventuellement rattaché à une infrastructure.
 *
 * Le slug est généré depuis le titre ; le statut passe de « en collecte » à « financé »
 * (et inversement) à chaque enregistrement ou suppression d'un financement (refreshStatus()).
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /** Disque et dossier des images : storage/app/public/projects (php artisan storage:link). */
    public const IMAGE_DISK = 'public';

    public const IMAGE_DIRECTORY = 'projects';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'category',
        'description',
        'target_budget',
        'start_date',
        'end_date',
        'status',
        'image_path',
        'is_public',
        'infrastructure_id',
    ];

    /**
     * Valeurs par défaut, identiques à celles de la migration.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'brouillon',
        'is_public' => false,
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'category' => ProjectCategory::class,
        'status' => ProjectStatus::class,
        'target_budget' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_public' => 'boolean',
    ];

    // ---------------------------------------------------------------- Hooks

    protected static function booted(): void
    {
        // Slug unique généré depuis le titre (à la création et quand le titre change)
        static::saving(function (self $project): void {
            if (empty($project->slug) || $project->isDirty('title')) {
                $project->slug = self::uniqueSlug($project->title, $project->id);
            }
        });

        // Pas de fichier orphelin : l'image est supprimée avec le projet (les financements suivent par cascade)
        static::deleted(function (self $project): void {
            $project->deleteImage();
        });
    }

    // ---------------------------------------------------------------- Relations

    /**
     * Ouvrage concerné (nullable : ouvrage supprimé ou projet multi-sites).
     *
     * @return BelongsTo<Infrastructure, $this>
     */
    public function infrastructure(): BelongsTo
    {
        return $this->belongsTo(Infrastructure::class);
    }

    /** @return HasMany<Funding, $this> */
    public function fundings(): HasMany
    {
        return $this->hasMany(Funding::class);
    }

    // ---------------------------------------------------------------- Accesseurs

    /**
     * Montant collecté : agrégat withSum('fundings', 'amount') s'il est chargé,
     * sinon somme des financements chargés, sinon requête.
     */
    public function getCollectedAmountAttribute(): float
    {
        if (array_key_exists('fundings_sum_amount', $this->attributes)) {
            return (float) $this->attributes['fundings_sum_amount'];
        }

        if ($this->relationLoaded('fundings')) {
            return (float) $this->fundings->sum('amount');
        }

        return (float) $this->fundings()->sum('amount');
    }

    /** Avancement du financement en %, plafonné à 100. */
    public function getProgressPercentAttribute(): int
    {
        $target = (float) $this->target_budget;

        if ($target <= 0) {
            return 0;
        }

        return (int) min(100, floor($this->collected_amount / $target * 100));
    }

    /** Jours restants avant la fin de la collecte (0 si la date est passée). */
    public function getDaysLeftAttribute(): int
    {
        return max(0, (int) today()->diffInDays($this->end_date, false));
    }

    /** Fin de collecte dépassée. */
    public function isExpired(): bool
    {
        return $this->end_date->lt(today());
    }

    /** Ouvert aux contributions citoyennes : public, en collecte, non échu. */
    public function acceptsContributions(): bool
    {
        return $this->is_public && $this->status === ProjectStatus::EnCollecte && ! $this->isExpired();
    }

    /** Montant lisible sans unité : 168400 => « 168 400 », 50.5 => « 50,50 ». */
    public static function money(float|int|string|null $amount): string
    {
        $amount = (float) $amount;

        return number_format($amount, $amount == floor($amount) ? 0 : 2, ',', ' ');
    }

    // ---------------------------------------------------------------- Statut automatique

    /**
     * Recalcule le statut après un changement de financement :
     * « en collecte » → « financé » quand l'objectif est atteint,
     * « financé » → « en collecte » s'il ne l'est plus. Les autres statuts ne sont pas modifiés.
     */
    public function refreshStatus(): void
    {
        $reached = round((float) $this->fundings()->sum('amount'), 2) >= round((float) $this->target_budget, 2);

        $status = match (true) {
            $this->status === ProjectStatus::EnCollecte && $reached => ProjectStatus::Finance,
            $this->status === ProjectStatus::Finance && ! $reached => ProjectStatus::EnCollecte,
            default => null,
        };

        if ($status) {
            $this->update(['status' => $status]);
        }
    }

    // ---------------------------------------------------------------- Image

    /** URL publique de l'image (lien public/storage), ou null. */
    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk(self::IMAGE_DISK)->url($this->image_path) : null;
    }

    public function deleteImage(): void
    {
        if ($this->image_path) {
            Storage::disk(self::IMAGE_DISK)->delete($this->image_path);
        }
    }

    // ---------------------------------------------------------------- Slug

    /** « Réhabilitation du réservoir » → « rehabilitation-du-reservoir », suffixé -2, -3… en cas de doublon. */
    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'projet';
        $slug = $base;

        for ($i = 2; self::query()->where('slug', $slug)->when($ignoreId, fn (Builder $q, $id) => $q->whereKeyNot($id))->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    // ---------------------------------------------------------------- Scopes

    /** Recherche par titre ou description. */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $q) => $q->where(fn (Builder $q) => $q
            ->where('title', 'like', "%{$term}%")
            ->orWhere('description', 'like', "%{$term}%")));
    }

    /** Un statut ou une liste de statuts ; vide = tous. */
    public function scopeOfStatus(Builder $query, ProjectStatus|string|array|null $status): void
    {
        $statuses = array_map(fn ($s) => $s instanceof ProjectStatus ? $s->value : $s, array_filter((array) $status));
        $query->when($statuses, fn (Builder $q) => $q->whereIn('status', $statuses));
    }

    /** Une catégorie ou une liste de catégories ; vide = toutes. */
    public function scopeOfCategory(Builder $query, ProjectCategory|string|array|null $category): void
    {
        $categories = array_map(fn ($c) => $c instanceof ProjectCategory ? $c->value : $c, array_filter((array) $category));
        $query->when($categories, fn (Builder $q) => $q->whereIn('category', $categories));
    }

    /** Projets ouverts au financement citoyen : publics et en collecte. */
    public function scopeOpen(Builder $query): void
    {
        $query->where('is_public', true)->ofStatus(ProjectStatus::EnCollecte);
    }

    /** Projets visibles sur le site citoyen : publics et sortis du brouillon. */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_public', true)->where('status', '!=', ProjectStatus::Brouillon->value);
    }

    /** Projets d'une zone (via l'infrastructure liée) ; vide = toutes. */
    public function scopeInZone(Builder $query, Zone|int|string|null $zone): void
    {
        $query->when($zone, fn (Builder $q) => $q->whereHas('infrastructure',
            fn (Builder $infra) => $infra->where('zone_id', $zone instanceof Zone ? $zone->getKey() : $zone)
        ));
    }

    /** Tri par avancement du financement (montant collecté / objectif), le plus avancé d'abord ; « * 1.0 » évite la division entière de SQLite. */
    public function scopeOrderByProgress(Builder $query): void
    {
        $query->orderByRaw('(select coalesce(sum(fundings.amount), 0) from fundings where fundings.project_id = projects.id) * 1.0 / projects.target_budget desc');
    }

    /** @return array<int, string> id => titre, pour <x-form.select> et les filtres */
    public static function options(?Builder $query = null): array
    {
        return ($query ?? self::query())->orderBy('title')->pluck('title', 'id')->all();
    }
}
