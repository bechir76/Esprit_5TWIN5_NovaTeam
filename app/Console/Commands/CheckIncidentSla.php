<?php

namespace App\Console\Commands;

use App\Enums\IncidentSeverity;
use App\Enums\Role;
use App\Models\Incident;
use App\Models\User;
use App\Notifications\IncidentSlaBreached;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Toutes les 15 minutes (routes/console.php) : marque les incidents ouverts dont le délai cible est dépassé
 * (config/incidents.php) et prévient les administrateurs, une seule fois par incident (idempotent).
 */
class CheckIncidentSla extends Command
{
    protected $signature = 'incidents:check-sla';

    protected $description = 'Marque les incidents en dépassement de délai (SLA) et notifie les administrateurs une seule fois';

    public function handle(): int
    {
        $admins = User::query()->where('role', Role::Admin->value)->where('is_active', true)->get();
        $breached = 0;

        foreach (IncidentSeverity::cases() as $severity) {
            $incidents = Incident::query()->open()->ofSeverity($severity)->whereNull('sla_breached_at')
                ->where('created_at', '<=', now()->subHours((int) config('incidents.sla_hours.'.$severity->value)))
                ->get();

            foreach ($incidents as $incident) {
                // Mise à jour conditionnelle : un passage concurrent ne notifie pas deux fois
                $marked = Incident::query()->whereKey($incident->id)->whereNull('sla_breached_at')->update(['sla_breached_at' => now()]);

                if ($marked === 1) {
                    $breached++;
                    Notification::send($admins, new IncidentSlaBreached($incident));
                }
            }
        }

        $this->info("{$breached} incident(s) en dépassement de délai.");

        return self::SUCCESS;
    }
}
