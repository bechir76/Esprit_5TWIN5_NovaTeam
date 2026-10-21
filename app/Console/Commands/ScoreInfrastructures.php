<?php

namespace App\Console\Commands;

use App\Models\Infrastructure;
use App\Services\Infrastructures\RiskAssessment;
use Illuminate\Console\Command;

/**
 * Chaque nuit (routes/console.php) et à la demande : score de risque prédictif d'un ouvrage (code ou id)
 * ou de tous les ouvrages.
 */
class ScoreInfrastructures extends Command
{
    protected $signature = 'infrastructures:score {infrastructure? : code (RES-ARI-004) ou id d\'un ouvrage ; vide = tous}';

    protected $description = 'Calcule le score de risque (0-100), son explication et 3 recommandations pour un ou tous les ouvrages';

    public function handle(RiskAssessment $assessment): int
    {
        $target = $this->argument('infrastructure');

        $infrastructures = $target === null
            ? Infrastructure::query()->orderBy('code')->get()
            : Infrastructure::query()->where('code', strtoupper($target))->orWhere('id', ctype_digit($target) ? (int) $target : 0)->get();

        if ($infrastructures->isEmpty()) {
            $this->error("Aucun ouvrage ne correspond à « {$target} ».");

            return self::FAILURE;
        }

        foreach ($infrastructures as $infrastructure) {
            $assessment->assess($infrastructure);
            $this->line("{$infrastructure->code} : {$infrastructure->risk_score}/100 ({$infrastructure->risk_level->label()}, {$infrastructure->risk_source})");
        }

        $this->info("{$infrastructures->count()} ouvrage(s) évalué(s).");

        return self::SUCCESS;
    }
}
