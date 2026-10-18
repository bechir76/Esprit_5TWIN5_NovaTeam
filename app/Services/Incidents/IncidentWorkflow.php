<?php

namespace App\Services\Incidents;

use App\Enums\IncidentStatus as Status;
use App\Exceptions\InvalidIncidentTransition;
use App\Models\Incident;
use App\Models\User;
use App\Notifications\IncidentStatusChanged;
use Illuminate\Support\Facades\DB;

/**
 * Workflow des statuts d'un incident : Signalé → Qualifié → En intervention → Résolu / Rejeté,
 * avec réouverture (Résolu / Rejeté → Qualifié). Une transition interdite lève InvalidIncidentTransition.
 * L'historique est écrit par IncidentObserver ; resolved_at est géré par le modèle.
 */
class IncidentWorkflow
{
    /** Statuts accessibles depuis chaque statut. */
    public const TRANSITIONS = [
        'signale' => ['qualifie', 'en_intervention', 'rejete'],
        'qualifie' => ['en_intervention', 'resolu', 'rejete'],
        'en_intervention' => ['qualifie', 'resolu', 'rejete'],
        'resolu' => ['qualifie'],
        'rejete' => ['qualifie'],
    ];

    public function canTransition(Incident $incident, Status $to): bool
    {
        return $incident->status === $to || in_array($to->value, self::TRANSITIONS[$incident->status->value], true);
    }

    /** @return list<Status> statuts proposés dans le formulaire « Traitement » (statut actuel compris) */
    public function availableStatuses(Incident $incident): array
    {
        return [$incident->status, ...array_map(fn (string $value) => Status::from($value), self::TRANSITIONS[$incident->status->value])];
    }

    /** Message d'erreur si la transition est refusée (interdite ou incompatible avec les interventions), sinon null. */
    public function refusal(Incident $incident, Status $to): ?string
    {
        if (! $this->canTransition($incident, $to)) {
            return (new InvalidIncidentTransition($incident->status, $to))->getMessage();
        }

        return $incident->status === $to ? null : $incident->statusConflict($to);
    }

    /**
     * Change le statut (transaction), historise le changement et notifie le déclarant.
     *
     * @throws InvalidIncidentTransition
     */
    public function transition(Incident $incident, Status $to, ?User $by = null, ?string $comment = null): Incident
    {
        if ($incident->status === $to) {
            return $incident;
        }

        $from = $incident->status;

        if ($reason = $this->refusal($incident, $to)) {
            throw new InvalidIncidentTransition($from, $to, $reason);
        }

        DB::transaction(function () use ($incident, $to, $by, $comment): void {
            $incident->statusChangedBy = $by;
            $incident->statusComment = $comment;
            $incident->update(['status' => $to]);
        });

        // Le déclarant est prévenu (sauf s'il a lui-même fait le changement)
        if ($incident->user && ! $incident->user->is($by)) {
            $incident->user->notify(new IncidentStatusChanged($incident, $from, $to));
        }

        return $incident;
    }
}
