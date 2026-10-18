<?php

namespace App\Models;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\InterventionStatus;
use App\Observers\IncidentObserver;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Incident signalé par un citoyen (ou saisi par le personnel), éventuellement rattaché à une infrastructure.
 * Il est traité par une ou plusieurs interventions (Intervention).
 *
 * La référence (INC-2026-0001) est générée à la création ; resolved_at suit le statut « résolu » ;
 * chaque changement de statut est historisé dans status_history (IncidentObserver).
 */
#[ObservedBy(IncidentObserver::class)]
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    /** Disque et dossier des photos : storage/app/public/incidents (php artisan storage:link). */
    public const PHOTO_DISK = 'public';

    public const PHOTO_DIRECTORY = 'incidents';

    /** Rayon de la Terre en mètres (formule de Haversine). */
    protected const EARTH_RADIUS = 6371000;

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
        'duplicate_of_id',
        'reports_count',
        'sla_breached_at',
        'cluster_key',
    ];

    /**
     * Valeurs par défaut, identiques à celles de la migration.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'signale',
        'reports_count' => 1,
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
        'status_history' => 'array',
        'reports_count' => 'integer',
        'sla_breached_at' => 'datetime',
        'ai_urgency_score' => 'integer',
        'ai_analyzed_at' => 'datetime',
    ];

    /**
     * Commentaire et auteur du prochain changement de statut, lus par IncidentObserver (non enregistrés).
     */
    public ?string $statusComment = null;

    public ?User $statusChangedBy = null;

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

    /**
     * Incident principal dont ce signalement est un doublon.
     *
     * @return BelongsTo<Incident, $this>
     */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    /**
     * Signalements rattachés à cet incident (doublons).
     *
     * @return HasMany<Incident, $this>
     */
    public function duplicates(): HasMany
    {
        return $this->hasMany(self::class, 'duplicate_of_id');
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

    /** Lien de suivi public signé, envoyé au citoyen (valable sans connexion, inviolable). */
    public function trackingUrl(): string
    {
        return URL::signedRoute('incidents.track', $this);
    }

    // ---------------------------------------------------------------- Délais (SLA)

    /** Délai cible de traitement en heures selon la gravité (config/incidents.php). */
    public function slaHours(): int
    {
        return (int) config('incidents.sla_hours.'.$this->severity->value, 72);
    }

    /** Échéance du délai cible : date du signalement + délai de la gravité. */
    public function slaDeadline(): Carbon
    {
        return $this->created_at->copy()->addHours($this->slaHours());
    }

    /** Incident encore ouvert dont le délai cible est dépassé (badge « En retard »). */
    public function isOverdue(): bool
    {
        return $this->status->isOpen() && ($this->sla_breached_at !== null || $this->slaDeadline()->isPast());
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

    /**
     * Toutes les interventions sont terminées ou annulées (au moins une terminée) alors que l'incident est ouvert :
     * l'administration peut proposer la résolution (jamais automatique).
     */
    public function canSuggestResolution(): bool
    {
        if (! $this->status->isOpen()) {
            return false;
        }

        $statuses = $this->relationLoaded('interventions') ? $this->interventions->pluck('status') : $this->interventions()->pluck('status');

        return $statuses->contains(InterventionStatus::Terminee)
            && $statuses->every(fn (InterventionStatus $status) => ! $status->isActive());
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

    /**
     * Incidents à moins de $meters mètres du point (formule de Haversine en SQL), avec la colonne « distance ».
     * Un pré-filtre rectangulaire limite le calcul aux lignes proches.
     */
    public function scopeNear(Builder $query, float $latitude, float $longitude, float $meters): void
    {
        self::ensureMathFunctions();

        $deltaLat = rad2deg($meters / self::EARTH_RADIUS);
        $deltaLng = rad2deg($meters / self::EARTH_RADIUS / max(cos(deg2rad($latitude)), 0.01));
        $least = DB::connection()->getDriverName() === 'sqlite' ? 'min' : 'least';
        $distance = self::EARTH_RADIUS." * acos({$least}(1, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))";

        $query->whereNotNull('latitude')->whereNotNull('longitude')
            ->whereBetween('latitude', [$latitude - $deltaLat, $latitude + $deltaLat])
            ->whereBetween('longitude', [$longitude - $deltaLng, $longitude + $deltaLng])
            ->select('incidents.*')
            ->selectRaw("{$distance} as distance", [$latitude, $longitude, $latitude])
            ->whereRaw("{$distance} <= ?", [$latitude, $longitude, $latitude, $meters]);
    }

    /** Incidents ouverts dont le délai cible est dépassé (marqués par incidents:check-sla). */
    public function scopeOverdue(Builder $query): void
    {
        $query->open()->whereNotNull('sla_breached_at');
    }

    /**
     * Filtres combinés de la liste admin et de l'export CSV : q, type, severity, status, zone, du, au,
     * infrastructure (« avec » / « sans »), retard ; une valeur vide ne filtre pas.
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $query->search($filters['q'] ?? null)
            ->ofType($filters['type'] ?? null)
            ->ofSeverity($filters['severity'] ?? null)
            ->ofStatus($filters['status'] ?? null)
            ->inZone($filters['zone'] ?? null)
            ->when($filters['du'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['au'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when(($filters['infrastructure'] ?? null) === 'avec', fn (Builder $q) => $q->whereNotNull('infrastructure_id'))
            ->when(($filters['infrastructure'] ?? null) === 'sans', fn (Builder $q) => $q->whereNull('infrastructure_id'))
            ->when($filters['retard'] ?? null, fn (Builder $q) => $q->overdue());
    }

    /** Tri de la liste admin : récents, anciens, urgence (score IA), gravité. */
    public function scopeSorted(Builder $query, ?string $sort): void
    {
        match ($sort) {
            'anciens' => $query->orderBy('created_at'),
            'urgence' => $query->orderByRaw('ai_urgency_score is null')->orderByDesc('ai_urgency_score')->orderByDesc('created_at'),
            'gravite' => $query->orderByRaw("case severity when 'critique' then 0 when 'elevee' then 1 when 'moyenne' then 2 else 3 end")->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };
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

    /**
     * SQLite ne fournit pas acos, sin, cos ni radians : ils sont ajoutés à la connexion (une seule fois).
     * MySQL et PostgreSQL ont ces fonctions nativement.
     */
    protected static function ensureMathFunctions(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = $connection->getPdo();
        self::$mathConnections ??= new \WeakMap;

        if (isset(self::$mathConnections[$pdo])) {
            return;
        }

        foreach (['acos' => 'acos', 'sin' => 'sin', 'cos' => 'cos', 'radians' => 'deg2rad'] as $name => $function) {
            $pdo->sqliteCreateFunction($name, $function, 1);
        }

        self::$mathConnections[$pdo] = true;
    }

    /** @var \WeakMap<\PDO, bool>|null Connexions SQLite où les fonctions mathématiques sont déjà ajoutées. */
    private static ?\WeakMap $mathConnections = null;
}
