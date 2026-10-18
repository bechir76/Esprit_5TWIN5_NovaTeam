<?php

namespace App\Models;

use App\Enums\IncidentStatus;
use App\Enums\InterventionStatus;
use Database\Factories\InterventionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Intervention d'une équipe terrain sur un incident.
 *
 * Lors de l'enregistrement, completed_at suit le statut « terminée » et
 * l'incident passe « en intervention » dès qu'une intervention démarre.
 */
class Intervention extends Model
{
    /** @use HasFactory<InterventionFactory> */
    use HasFactory;

    /** Équipes proposées dans le formulaire. */
    public const TEAMS = [
        'Équipe réseau · Tunis',
        'Équipe réseau · Ariana',
        'Équipe réseau · Ben Arous',
        'Équipe réseau · La Manouba',
        'Équipe qualité · Ben Arous',
        'Équipe qualité · Ariana',
        'Équipe d\'astreinte',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'incident_id',
        'title',
        'team',
        'technician_id',
        'scheduled_at',
        'estimated_hours',
        'status',
        'report',
        'completed_at',
        'started_at',
    ];

    /**
     * Valeurs par défaut, identiques à celles de la migration.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'planifiee',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => InterventionStatus::class,
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'started_at' => 'datetime',
        'estimated_hours' => 'decimal:1',
    ];

    // ---------------------------------------------------------------- Hooks

    protected static function booted(): void
    {
        static::saving(function (self $intervention): void {
            // completed_at suit le statut : renseigné à la fin de l'intervention, effacé sinon
            if ($intervention->status !== InterventionStatus::Terminee) {
                $intervention->completed_at = null;
            } elseif ($intervention->completed_at === null) {
                $intervention->completed_at = now();
            }

            // started_at : renseigné au démarrage (en cours ou terminée), effacé si l'intervention est replanifiée
            if (in_array($intervention->status, [InterventionStatus::Planifiee, InterventionStatus::Annulee], true)) {
                $intervention->started_at = null;
            } elseif ($intervention->started_at === null) {
                $intervention->started_at = min(now(), $intervention->completed_at ?? now());
            }
        });

        // Une intervention qui démarre fait passer l'incident signalé ou qualifié « en intervention »
        static::saved(function (self $intervention): void {
            $incident = $intervention->incident;

            if ($intervention->status === InterventionStatus::EnCours
                && in_array($incident->status, [IncidentStatus::Signale, IncidentStatus::Qualifie], true)) {
                $incident->statusComment = "Intervention « {$intervention->title} » démarrée.";
                $incident->update(['status' => IncidentStatus::EnIntervention]);
            }
        });
    }

    // ---------------------------------------------------------------- Créneau

    /** Fin prévue du créneau : date prévue + durée estimée. */
    public function endsAt(): Carbon
    {
        return $this->scheduled_at->copy()->addMinutes((int) round((float) $this->estimated_hours * 60));
    }

    /** Durée réelle en heures (démarrage → fin), ou null si l'intervention n'est pas terminée. */
    public function actualHours(): ?float
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        return round($this->started_at->diffInMinutes($this->completed_at) / 60, 1);
    }

    // ---------------------------------------------------------------- Relations

    /** @return BelongsTo<Incident, $this> */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /**
     * Technicien assigné (nullable).
     *
     * @return BelongsTo<User, $this>
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /** Durée lisible : « 3 h », « 1,5 h ». */
    public function formattedDuration(): string
    {
        $hours = (float) $this->estimated_hours;

        return number_format($hours, $hours == floor($hours) ? 0 : 1, ',', ' ').' h';
    }

    // ---------------------------------------------------------------- Scopes

    /** Recherche par titre, équipe ou référence de l'incident. */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $q) => $q->where(fn (Builder $q) => $q
            ->where('title', 'like', "%{$term}%")
            ->orWhere('team', 'like', "%{$term}%")
            ->orWhereHas('incident', fn (Builder $i) => $i->where('reference', 'like', "%{$term}%"))));
    }

    /** Un statut ou une liste de statuts ; vide = tous. */
    public function scopeOfStatus(Builder $query, InterventionStatus|string|array|null $status): void
    {
        $statuses = array_map(fn ($s) => $s instanceof InterventionStatus ? $s->value : $s, array_filter((array) $status));
        $query->when($statuses, fn (Builder $q) => $q->whereIn('status', $statuses));
    }

    /** Interventions prévues un jour donné (Y-m-d) ; vide = toutes. */
    public function scopeScheduledOn(Builder $query, ?string $date): void
    {
        $query->when($date, fn (Builder $q) => $q->whereDate('scheduled_at', $date));
    }

    /** Interventions planifiées ou en cours. */
    public function scopeActive(Builder $query): void
    {
        $query->ofStatus([InterventionStatus::Planifiee, InterventionStatus::EnCours]);
    }
}
