<?php

namespace App\Console\Commands;

use App\Models\Infrastructure;
use App\Notifications\InfrastructureInspectionOverdue;
use App\Notifications\InfrastructureNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Chaque jour (routes/console.php) : repère les ouvrages dont l'inspection est en retard et prévient les
 * gestionnaires et administrateurs, une seule fois par échéance (idempotent).
 */
class CheckInfrastructureInspections extends Command
{
    protected $signature = 'infrastructures:check-inspections';

    protected $description = 'Notifie une seule fois les gestionnaires des inspections d\'ouvrages en retard';

    public function handle(): int
    {
        $recipients = InfrastructureNotification::recipients();
        $notified = 0;

        $overdue = Infrastructure::query()->with('zone')->inspectionOverdue()->whereNull('inspection_overdue_notified_at')->get();

        foreach ($overdue as $infrastructure) {
            $marked = Infrastructure::query()->whereKey($infrastructure->id)->whereNull('inspection_overdue_notified_at')
                ->update(['inspection_overdue_notified_at' => now()]);

            if ($marked === 1) {
                $notified++;
                Notification::send($recipients, new InfrastructureInspectionOverdue($infrastructure));
            }
        }

        $this->info("{$notified} inspection(s) en retard signalée(s).");

        return self::SUCCESS;
    }
}
