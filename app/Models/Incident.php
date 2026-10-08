<?php

namespace App\Models;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\InterventionStatus;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Incident signalé par un citoyen (ou saisi par le personnel), éventuellement rattaché à une infrastructure.
 * Il est traité par une ou plusieurs interventions (Intervention).
 *
 * La référence (INC-2026-0001) est générée à la création ; resolved_at suit le statut « résolu ».
 */
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    /** Disque et dossier des photos : storage/app/public/incidents (php artisan storage:link). */
    public const PHOTO_DISK = 'public';

    public const PHOTO_DIRECTORY = 'incidents';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'title',
        'description',
        'type',
        'severity',
        'status',
        'address',
        'latitude',
        'longitude',
        'photo_path',
        'infrastructure_id',
        'user_id',
        'resolved_at',
    ];

    /**
     * Valeurs par défaut, identiques à celles de la migration.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'signale',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => IncidentType::class,
        'severity' => IncidentSeverity::class,
        'status' => IncidentStatus::class,
        'latitude' => 'float',
        'longitude' => 'float',
        'resolved_at' => 'datetime',
    ];

    // ---------------------------------------------------------------- Hooks

    protected static function booted(): void
    {
        // Référence générée automatiquement si elle n'est pas fournie
        static::creating(function (self $incident): void {
            if (empty($incident->reference)) {
                $incident->reference = self::generateReference();
            }
        });

        // resolved_at suit le statut : renseigné au passage à « résolu », effacé sinon
        static::saving(function (self $incident): void {
            if ($incident->status !== IncidentStatus::Resolu) {
                $incident->resolved_at = null;
            } elseif ($incident->resolved_at === null) {
                $incident->resolved_at = now();
            }
        });

        // Pas de fichier orphelin : la photo est supprimée avec l'incident
        static::deleted(function (self $incident): void {
            $incident->deletePhoto();
        });
    }

    // ---------------------------------------------------------------- Relations

    /** @return BelongsTo<Infrastructure, $this> */
    public function infrastructure(): BelongsTo
    {
        return $this->belongsTo(Infrastructure::class);
    }

    /**
     * Déclarant (nullable : compte supprimé).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Intervention, $this> */
    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }

    // ---------------------------------------------------------------- Photo

    /** URL publique de la photo (lien public/storage), ou null. */
    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk(self::PHOTO_DISK)->url($this->photo_path) : null;
    }

    public function deletePhoto(): void
    {
        if ($this->photo_path) {
            Storage::disk(self::PHOTO_DISK)->delete($this->photo_path);
        }
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    // ---------------------------------------------------------------- Cohérence avec les interventions

    /**
     * Message d'erreur si le nouveau statut contredit les interventions de l'incident, sinon null :
     * pas de clôture tant qu'une intervention est planifiée ou en cours,
     * pas de retour à « signalé » / « qualifié » pendant une intervention en cours.
     */
    public function statusConflict(IncidentStatus $status): ?string
    {
        if (! $this->exists) {
            return null;
        }

        if (! $status->isOpen()) {
            $active = $this->interventions()->active()->count();

            if ($active > 0) {
                return "Impossible de passer l'incident au statut « {$status->label()} » : {$active} intervention(s) planifiée(s) ou en cours. Terminez-les ou annulez-les d'abord.";
            }
        }

        if (in_array($status, [IncidentStatus::Signale, IncidentStatus::Qualifie], true)
            && $this->interventions()->ofStatus(InterventionStatus::EnCours)->exists()) {
            return "Une intervention est en cours : l'incident doit rester « En intervention ».";
        }

        return null;
    }

    /** Message de confirmation après un changement de statut (contrôleur et composant Livewire IncidentStatus). */
    public function statusChangedMessage(): string
    {
        return "Incident {$this->reference} : statut « {$this->status->label()} ».";
    }

    // ---------------------------------------------------------------- Génération de la référence

    /**
     * Génère une référence unique de la forme INC-2026-0001,
     * à partir du numéro le plus élevé déjà attribué cette année (même principe que les analyses).
     */
    public static function generateReference(): string
    {
        $prefix = 'INC-'.now()->year.'-';

        $lastRef = self::query()
            ->where('reference', 'like', "{$prefix}%")
            ->orderByDesc('reference')
            ->value('reference');

        $seq = $lastRef ? ((int) substr($lastRef, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    // ---------------------------------------------------------------- Scopes

    /** Recherche par référence, titre, description ou adresse. */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $q) => $q->where(fn (Builder $q) => $q
            ->where('reference', 'like', "%{$term}%")
            ->orWhere('title', 'like', "%{$term}%")
            ->orWhere('description', 'like', "%{$term}%")
            ->orWhere('address', 'like', "%{$term}%")));
    }

    /** Un type ou une liste de types ; vide = tous. */
    public function scopeOfType(Builder $query, IncidentType|string|array|null $type): void
    {
        $types = array_map(fn ($t) => $t instanceof IncidentType ? $t->value : $t, array_filter((array) $type));
        $query->when($types, fn (Builder $q) => $q->whereIn('type', $types));
    }

    /** Une gravité ou une liste de gravités ; vide = toutes. */
    public function scopeOfSeverity(Builder $query, IncidentSeverity|string|array|null $severity): void
    {
        $severities = array_map(fn ($s) => $s instanceof IncidentSeverity ? $s->value : $s, array_filter((array) $severity));
        $query->when($severities, fn (Builder $q) => $q->whereIn('severity', $severities));
    }

    /** Un statut ou une liste de statuts ; vide = tous. */
    public function scopeOfStatus(Builder $query, IncidentStatus|string|array|null $status): void
    {
        $statuses = array_map(fn ($s) => $s instanceof IncidentStatus ? $s->value : $s, array_filter((array) $status));
        $query->when($statuses, fn (Builder $q) => $q->whereIn('status', $statuses));
    }

    /** Incidents ouverts : signalés, qualifiés ou en intervention. */
    public function scopeOpen(Builder $query): void
    {
        $query->ofStatus(IncidentStatus::openCases());
    }

    /** Incidents d'une zone (via l'infrastructure liée) ; vide = toutes. */
    public function scopeInZone(Builder $query, Zone|int|string|null $zone): void
    {
        $query->when($zone, fn (Builder $q) => $q->whereHas('infrastructure',
            fn (Builder $infra) => $infra->where('zone_id', $zone instanceof Zone ? $zone->getKey() : $zone)
        ));
    }

    /** @return array<int, string> id => « INC-2026-0001 · Titre », pour <x-form.select> */
    public static function options(?Builder $query = null): array
    {
        return ($query ?? self::query())
            ->orderByDesc('created_at')
            ->get(['id', 'reference', 'title'])
            ->mapWithKeys(fn (self $incident) => [$incident->id => "{$incident->reference} · {$incident->title}"])
            ->all();
    }
}
