<?php

namespace App\Models;

use App\Enums\FundingStatus;
use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Events\ProjectStatusChanged;
use App\Exceptions\InvalidProjectTransition;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Projet de rénovation financé par des municipalités, associations, entreprises et citoyens (Funding),
 * éventuellement rattaché à une infrastructure.
 *
 * Cycle de vie contrôlé par ProjectStatus::transitions() : un changement interdit lève InvalidProjectTransition.
 * Seuls les financements confirmés comptent dans le montant collecté. Journal d'audit : spatie/laravel-activitylog.
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, LogsActivity;

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
        'ai_priority_score',
        'ai_priority_reason',
        'ai_scored_at',
        'milestone_notified_at',
    ];

    /**
     * Valeurs par défaut, identiques à celles de la migration.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
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
        'ai_priority_score' => 'integer',
        'ai_scored_at' => 'datetime',
        'milestone_notified_at' => 'datetime',
    ];

    // ---------------------------------------------------------------- Hooks

    protected static function booted(): void
    {
        static::saving(function (self $project): void {
            // Slug unique généré depuis le titre (à la création et quand le titre change)
            if (empty($project->slug) || $project->isDirty('title')) {
                $project->slug = self::uniqueSlug($project->title, $project->id);
            }

            // Transition de statut contrôlée (un nouveau projet peut être créé dans n'importe quel statut)
            if ($project->exists && $project->isDirty('status') && $project->getRawOriginal('status') !== null) {
                $from = ProjectStatus::from($project->getRawOriginal('status'));

                if (! $from->canTransitionTo($project->status)) {
                    throw new InvalidProjectTransition($from, $project->status);
                }
            }
        });

        // Notifications des donateurs (financé, échoué, travaux commencés…)
        static::updated(function (self $project): void {
            if ($project->wasChanged('status')) {
                ProjectStatusChanged::dispatch($project, ProjectStatus::from($project->getRawOriginal('status')));
            }
        });

        // Pas de fichier orphelin : l'image est supprimée avec le projet (les financements suivent par cascade)
        static::deleted(function (self $project): void {
            $project->deleteImage();
        });
    }

    /** Journal d'audit : qui, quand, avant / après pour les champs modifiables. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('projects')
            ->logOnly(['title', 'category', 'target_budget', 'start_date', 'end_date', 'status', 'is_public', 'infrastructure_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
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

    // ---------------------------------------------------------------- Montants (financements confirmés uniquement)

    /**
     * Montant collecté : agrégat withCollected() s'il est chargé, sinon somme des financements confirmés.
     */
    public function getCollectedAmountAttribute(): float
    {
        if (array_key_exists('fundings_sum_amount', $this->attributes)) {
            return (float) $this->attributes['fundings_sum_amount'];
        }

        if ($this->relationLoaded('fundings')) {
            return (float) $this->fundings->where('status', FundingStatus::Confirmed)->sum('amount');
        }

        return (float) $this->fundings()->confirmed()->sum('amount');
    }

    /** Nombre de donateurs (financements confirmés). */
    public function getDonorsCountAttribute(): int
    {
        if (array_key_exists('donors_count', $this->attributes)) {
            return (int) $this->attributes['donors_count'];
        }

        return $this->fundings()->confirmed()->count();
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

    /**
     * Reste à collecter ; avec $reserved, les dons en attente de paiement récents sont déduits
     * (deux dons simultanés ne peuvent pas dépasser l'objectif).
     */
    public function remainingAmount(bool $reserved = false): float
    {
        $taken = (float) $this->fundings()->confirmed()->sum('amount');

        if ($reserved) {
            $taken += (float) $this->fundings()->reserved()->sum('amount');
        }

        return max(0, round((float) $this->target_budget - $taken, 2));
    }

    public function hasReachedTarget(): bool
    {
        return round((float) $this->fundings()->confirmed()->sum('amount'), 2) >= round((float) $this->target_budget, 2);
    }

    /** Fin de collecte dépassée. */
    public function isExpired(): bool
    {
        return $this->end_date->lt(today());
    }

    /** Publié sur le site citoyen (public et sorti du brouillon). */
    public function isPublished(): bool
    {
        return $this->is_public && $this->status !== ProjectStatus::Draft;
    }

    /** Ouvert aux dons citoyens : public, en collecte, non échu. */
    public function acceptsContributions(): bool
    {
        return $this->contributionRefusal() === null;
    }

    /**
     * Raison pour laquelle le projet refuse un don citoyen, ou null s'il l'accepte.
     * Partagé par StoreContributionRequest, le composant Livewire ProjectContribution et DonationService.
     */
    public function contributionRefusal(): ?string
    {
        if (! $this->is_public || $this->status !== ProjectStatus::Open) {
            return "Ce projet n'accepte pas de contributions : il est « {$this->status->label()} ».";
        }

        if ($this->isExpired()) {
            return 'La collecte de ce projet est terminée depuis le '.$this->end_date->format('d/m/Y').'.';
        }

        return null;
    }

    /**
     * Citoyens ayant donné au projet (dons encaissés ou remboursés) : destinataires des notifications.
     *
     * @return Collection<int, User>
     */
    public static function donorsOf(self $project): Collection
    {
        return User::query()->whereIn('id', $project->fundings()
            ->ofStatus([FundingStatus::Confirmed, FundingStatus::Refunded])
            ->where('is_matching', false)
            ->whereNotNull('user_id')
            ->select('user_id'))->get();
    }

    /** Montant lisible sans unité : 168400 => « 168 400 », 50.5 => « 50,50 ». */
    public static function money(float|int|string|null $amount): string
    {
        $amount = (float) $amount;

        return number_format($amount, $amount == floor($amount) ? 0 : 2, ',', ' ');
    }

    // ---------------------------------------------------------------- Cycle de vie

    /** Change de statut en respectant ProjectStatus::transitions() (sinon InvalidProjectTransition). */
    public function transitionTo(ProjectStatus $status): void
    {
        if (! $this->status->canTransitionTo($status)) {
            throw new InvalidProjectTransition($this->status, $status);
        }

        if ($this->status !== $status) {
            $this->update(['status' => $status]);
        }
    }

    /**
     * Recalcule le statut après un changement de financement confirmé :
     * « en collecte » → « financé » quand l'objectif est atteint, « financé » → « en collecte » s'il ne l'est plus.
     * Les autres statuts (brouillon, échoué, en travaux, terminé) ne sont jamais modifiés ici.
     */
    public function refreshStatus(): void
    {
        $reached = $this->hasReachedTarget();

        match (true) {
            $this->status === ProjectStatus::Open && $reached => $this->transitionTo(ProjectStatus::Funded),
            $this->status === ProjectStatus::Funded && ! $reached => $this->transitionTo(ProjectStatus::Open),
            default => null,
        };
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

    /** Montant collecté (dons confirmés) et nombre de donateurs, sans requête par projet. */
    public function scopeWithCollected(Builder $query): void
    {
        $query->withSum(['fundings as fundings_sum_amount' => fn (Builder $q) => $q->confirmed()], 'amount')
            ->withCount(['fundings as donors_count' => fn (Builder $q) => $q->confirmed()]);
    }

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
        $query->where('is_public', true)->ofStatus(ProjectStatus::Open);
    }

    /** Projets visibles sur le site citoyen : publics et sortis du brouillon. */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_public', true)->where('status', '!=', ProjectStatus::Draft->value);
    }

    /** Projets d'une zone (via l'infrastructure liée) ; vide = toutes. */
    public function scopeInZone(Builder $query, Zone|int|string|null $zone): void
    {
        $query->when($zone, fn (Builder $q) => $q->whereHas('infrastructure',
            fn (Builder $infra) => $infra->where('zone_id', $zone instanceof Zone ? $zone->getKey() : $zone)
        ));
    }

    /** Projets dont le financement confirmé atteint au moins $percent % de l'objectif. */
    public function scopeFundedAtLeast(Builder $query, int|float|null $percent): void
    {
        $query->when($percent, fn (Builder $q) => $q->whereRaw(self::progressSql().' * 100 >= ?', [$percent]));
    }

    /** Tri par avancement du financement, le plus avancé d'abord. */
    public function scopeOrderByProgress(Builder $query, string $direction = 'desc'): void
    {
        $query->orderByRaw(self::progressSql().' '.($direction === 'asc' ? 'asc' : 'desc'));
    }

    /** Avancement (0 à 1+) en SQL ; « * 1.0 » évite la division entière de SQLite. */
    protected static function progressSql(): string
    {
        return "(select coalesce(sum(fundings.amount), 0) from fundings where fundings.project_id = projects.id and fundings.status = '"
            .FundingStatus::Confirmed->value."') * 1.0 / projects.target_budget";
    }

    /** @return array<int, string> id => titre, pour <x-form.select> et les filtres */
    public static function options(?Builder $query = null): array
    {
        return ($query ?? self::query())->orderBy('title')->pluck('title', 'id')->all();
    }
}
