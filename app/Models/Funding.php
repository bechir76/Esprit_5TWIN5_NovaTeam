<?php

namespace App\Models;

use App\Enums\FundingSource;
use App\Enums\FundingStatus;
use Database\Factories\FundingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Financement d'un projet : municipalité, association, entreprise ou don citoyen (Stripe Checkout).
 *
 * Un don citoyen naît « pending » ; seul le webhook Stripe le confirme (DonationService).
 * Seuls les financements confirmés comptent dans le montant collecté. La référence (FIN-2026-0001)
 * est générée à la création, le numéro de reçu (REC-2026-000001) à la confirmation.
 */
class Funding extends Model
{
    /** @use HasFactory<FundingFactory> */
    use HasFactory, LogsActivity;

    /** Don citoyen en ligne, en DT. */
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
        'status',
        'stripe_session_id',
        'stripe_payment_intent_id',
        'stripe_refund_id',
        'paid_at',
        'receipt_number',
        'is_matching',
        'is_anonymous',
        'matched_funding_id',
    ];

    /**
     * Valeurs par défaut, identiques à celles de la migration.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'is_matching' => false,
        'is_anonymous' => false,
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'source_type' => FundingSource::class,
        'status' => FundingStatus::class,
        'amount' => 'decimal:2',
        'funded_at' => 'date',
        'paid_at' => 'datetime',
        'is_matching' => 'boolean',
        'is_anonymous' => 'boolean',
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

        // Garde-fou : statut du projet recalculé ; si le financement change de projet, l'ancien aussi
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

    /** Journal d'audit : qui, quand, avant / après. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('fundings')
            ->logOnly(['project_id', 'source_type', 'amount', 'funded_at', 'status', 'is_matching', 'is_anonymous'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // ---------------------------------------------------------------- Relations

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Citoyen donateur (nullable : financement saisi par le personnel ou compte supprimé).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cofinancement municipal créé pour ce don citoyen.
     *
     * @return HasOne<Funding, $this>
     */
    public function matching(): HasOne
    {
        return $this->hasOne(self::class, 'matched_funding_id');
    }

    // ---------------------------------------------------------------- Paiement

    /** Montant saisi par un citoyen : virgule décimale et espaces (milliers) acceptés. */
    public static function normalizeAmount(mixed $amount): mixed
    {
        return is_string($amount) ? str_replace([',', ' ', "\u{00A0}", "\u{202F}"], ['.', '', '', ''], $amount) : $amount;
    }

    /** Don en attente → encaissé (appelé par le webhook Stripe). */
    public function markConfirmed(?string $paymentIntentId = null): void
    {
        $this->update([
            'status' => FundingStatus::Confirmed,
            'paid_at' => now(),
            'stripe_payment_intent_id' => $paymentIntentId ?? $this->stripe_payment_intent_id,
            'receipt_number' => $this->receipt_number ?? self::generateReceiptNumber(),
        ]);
    }

    public function isConfirmed(): bool
    {
        return $this->status === FundingStatus::Confirmed;
    }

    /** Reçu disponible : don citoyen encaissé (même remboursé ensuite, le reçu reste consultable). */
    public function hasReceipt(): bool
    {
        return $this->receipt_number !== null && in_array($this->status, [FundingStatus::Confirmed, FundingStatus::Refunded], true);
    }

    /**
     * Nom affiché publiquement (transparence, fiche projet) : « Anonyme » sur demande,
     * prénom et initiale pour un citoyen, nom complet pour une institution.
     */
    public function publicDonorName(): string
    {
        if ($this->is_anonymous) {
            return 'Anonyme';
        }

        if ($this->source_type === FundingSource::Citoyen) {
            $parts = preg_split('/\s+/', trim($this->donor_name)) ?: [];

            return count($parts) > 1 ? $parts[0].' '.Str::upper(Str::substr(end($parts), 0, 1)).'.' : ($parts[0] ?? 'Citoyen');
        }

        return $this->donor_name;
    }

    // ---------------------------------------------------------------- Références

    /** FIN-2026-0001, à partir du numéro le plus élevé déjà attribué cette année. */
    public static function generateReference(): string
    {
        return self::nextNumber('reference', 'FIN-'.now()->year.'-', 4);
    }

    /** REC-2026-000001 : numéro unique du reçu fiscal d'un don encaissé. */
    public static function generateReceiptNumber(): string
    {
        return self::nextNumber('receipt_number', 'REC-'.now()->year.'-', 6);
    }

    protected static function nextNumber(string $column, string $prefix, int $digits): string
    {
        $last = self::query()->where($column, 'like', "{$prefix}%")->orderByDesc($column)->value($column);
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $seq, $digits, '0', STR_PAD_LEFT);
    }

    // ---------------------------------------------------------------- Scopes

    public function scopeConfirmed(Builder $query): void
    {
        $query->where('status', FundingStatus::Confirmed->value);
    }

    /** Dons en attente de paiement récents : réservent leur montant sur l'objectif. */
    public function scopeReserved(Builder $query): void
    {
        $query->where('status', FundingStatus::Pending->value)
            ->where('created_at', '>=', now()->subMinutes(config('projects.donation.pending_ttl_minutes')));
    }

    /** Un statut ou une liste de statuts ; vide = tous. */
    public function scopeOfStatus(Builder $query, FundingStatus|string|array|null $status): void
    {
        $statuses = array_map(fn ($s) => $s instanceof FundingStatus ? $s->value : $s, array_filter((array) $status));
        $query->when($statuses, fn (Builder $q) => $q->whereIn('status', $statuses));
    }

    /** Recherche par référence, numéro de reçu ou nom du financeur. */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $q) => $q->where(fn (Builder $q) => $q
            ->where('reference', 'like', "%{$term}%")
            ->orWhere('receipt_number', 'like', "%{$term}%")
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
