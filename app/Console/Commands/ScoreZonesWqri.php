<?php

namespace App\Console\Commands;

use App\Models\Zone;
use App\Services\WaterQuality\WqriAssessment;
use Illuminate\Console\Command;

/**
 * Recalcule l'indice de risque sanitaire (WQRI) de toutes les zones ou d'une seule (A1).
 * Planifiée quotidiennement dans le scheduler (routes/console.php).
 *
 * Usage :
 *   php artisan zones:score-wqri               -- toutes les zones
 *   php artisan zones:score-wqri --zone=3      -- une seule zone
 */
class ScoreZonesWqri extends Command
{
    protected $signature = 'zones:score-wqri {--zone= : ID d\'une zone spécifique}';

    protected $description = 'Calcule l\'indice de risque sanitaire WQRI de toutes les zones (ou d\'une seule).';

    public function handle(WqriAssessment $assessment): int
    {
        $zoneId = $this->option('zone');

        $zones = $zoneId
            ? Zone::where('id', $zoneId)->get()
            : Zone::all();

        if ($zones->isEmpty()) {
            $this->warn($zoneId ? "Zone {$zoneId} introuvable." : 'Aucune zone trouvée.');

            return self::FAILURE;
        }

        $bar = $this->output->createProgressBar($zones->count());
        $bar->start();

        foreach ($zones as $zone) {
            $assessment->computeZone($zone);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("WQRI calculé pour {$zones->count()} zone(s).");

        return self::SUCCESS;
    }
}
