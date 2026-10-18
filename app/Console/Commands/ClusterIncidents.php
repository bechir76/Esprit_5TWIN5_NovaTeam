<?php

namespace App\Console\Commands;

use App\Services\Incidents\IncidentClusterer;
use Illuminate\Console\Command;

/** Toutes les 30 minutes (routes/console.php) : regroupe les incidents ouverts proches en lieu et en temps. */
class ClusterIncidents extends Command
{
    protected $signature = 'incidents:cluster';

    protected $description = 'Regroupe les incidents ouverts liés (proches en lieu et en temps) et propose un diagnostic';

    public function handle(IncidentClusterer $clusterer): int
    {
        $groups = $clusterer->run();

        foreach ($groups as $key => $group) {
            $this->line("{$key} : {$group->count()} incidents (".$group->pluck('reference')->implode(', ').')');
        }

        $this->info("{$groups->count()} groupe(s) d'incidents liés.");

        return self::SUCCESS;
    }
}
