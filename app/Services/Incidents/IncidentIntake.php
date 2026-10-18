<?php

namespace App\Services\Incidents;

use App\Enums\IncidentSeverity;
use App\Enums\Role;
use App\Jobs\AnalyzeIncident;
use App\Models\Incident;
use App\Models\User;
use App\Notifications\CriticalIncidentReported;
use Illuminate\Support\Facades\Notification;

/** Suites d'un nouveau signalement (site citoyen ou console) : analyse IA en queue et alerte des administrateurs. */
class IncidentIntake
{
    public function received(Incident $incident): void
    {
        AnalyzeIncident::dispatch($incident)->afterCommit();

        if ($incident->severity === IncidentSeverity::Critique) {
            Notification::send(
                User::query()->where('role', Role::Admin->value)->where('is_active', true)->get(),
                new CriticalIncidentReported($incident),
            );
        }

        app(IncidentStatsService::class)->forget();
    }
}
