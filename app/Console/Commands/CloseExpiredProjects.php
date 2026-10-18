<?php

namespace App\Console\Commands;

use App\Services\DonationService;
use Illuminate\Console\Command;

/**
 * Chaque nuit (routes/console.php) : les projets en collecte échus sans objectif atteint passent à « échoué »
 * et leurs dons confirmés sont remboursés. Idempotente : relancée, elle ne rembourse que ce qui reste à rembourser.
 */
class CloseExpiredProjects extends Command
{
    protected $signature = 'projects:close-expired';

    protected $description = 'Clôture les collectes échues : projets échoués et dons remboursés';

    public function handle(DonationService $donations): int
    {
        $stats = $donations->closeExpired();

        $this->info("Projets échoués : {$stats['failed']} · financés : {$stats['funded']} · financements remboursés : {$stats['refunded']}");

        return self::SUCCESS;
    }
}
