<?php

namespace App\Services\Incidents;

use App\Enums\InterventionStatus;
use App\Models\Intervention;
use App\Notifications\InterventionScheduled;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Création et modification des interventions avec détection des conflits de planning :
 * une même équipe ne peut pas avoir deux interventions planifiées / en cours qui se chevauchent
 * (date prévue + durée estimée). La vérification est refaite dans la transaction, avec verrou sur les
 * interventions de l'équipe, pour éviter deux créations simultanées en conflit.
 */
class InterventionScheduler
{
    /** Durée maximale d'une intervention (h) : borne la recherche des chevauchements. */
    public const MAX_HOURS = 72;

    /**
     * Interventions actives de l'équipe qui chevauchent le créneau [début, début + durée[.
     *
     * @return Collection<int, Intervention>
     */
    public function conflicts(string $team, Carbon $start, float $hours, ?int $ignoreId = null, bool $lock = false): Collection
    {
        $end = $start->copy()->addMinutes((int) round($hours * 60));

        return Intervention::query()
            ->with('incident')
            ->where('team', $team)
            ->active()
            ->when($ignoreId, fn ($q, $id) => $q->whereKeyNot($id))
            ->whereBetween('scheduled_at', [$start->copy()->subHours(self::MAX_HOURS), $end])
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->get()
            ->filter(fn (Intervention $other) => $other->scheduled_at->lt($end) && $other->endsAt()->gt($start))
            ->values();
    }

    /** Message de conflit lisible, ou null si l'équipe est disponible. */
    public function conflictMessage(string $team, Carbon $start, float $hours, ?int $ignoreId = null, bool $lock = false): ?string
    {
        $conflict = $this->conflicts($team, $start, $hours, $ignoreId, $lock)->first();

        return $conflict
            ? "L'équipe « {$team} » a déjà une intervention le ".$conflict->scheduled_at->format('d/m/Y').' de '
                .$conflict->scheduled_at->format('H:i').' à '.$conflict->endsAt()->format('H:i')
                ." ({$conflict->incident->reference} · {$conflict->title})."
            : null;
    }

    /**
     * @param  array<string, mixed>  $data  données validées (StoreInterventionRequest)
     *
     * @throws ValidationException conflit détecté au moment de l'enregistrement
     */
    public function create(array $data): Intervention
    {
        $intervention = DB::transaction(function () use ($data) {
            $this->assertAvailable($data);

            return Intervention::create($data);
        });

        $this->notifyCitizen($intervention);

        return $intervention;
    }

    /** @param  array<string, mixed>  $data */
    public function update(Intervention $intervention, array $data): Intervention
    {
        $wasScheduled = $intervention->scheduled_at;

        DB::transaction(function () use ($intervention, $data): void {
            $this->assertAvailable($data, $intervention->id);
            $intervention->update($data);
        });

        // Nouvelle date : le citoyen est prévenu
        if (! $intervention->scheduled_at->equalTo($wasScheduled)) {
            $this->notifyCitizen($intervention);
        }

        return $intervention;
    }

    /** @param  array<string, mixed>  $data */
    private function assertAvailable(array $data, ?int $ignoreId = null): void
    {
        $status = InterventionStatus::tryFrom($data['status'] instanceof InterventionStatus ? $data['status']->value : (string) $data['status']);

        if (! $status?->isActive()) {
            return;
        }

        $message = $this->conflictMessage($data['team'], Carbon::parse($data['scheduled_at']), (float) $data['estimated_hours'], $ignoreId, lock: true);

        if ($message) {
            throw ValidationException::withMessages(['team' => $message]);
        }
    }

    private function notifyCitizen(Intervention $intervention): void
    {
        $citizen = $intervention->incident->user;

        if ($citizen && $intervention->status->isActive() && $intervention->scheduled_at->isFuture()) {
            $citizen->notify(new InterventionScheduled($intervention));
        }
    }
}
