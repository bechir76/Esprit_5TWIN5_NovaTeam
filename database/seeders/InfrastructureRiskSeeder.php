<?php

namespace Database\Seeders;

use App\Models\Infrastructure;
use App\Services\Ai\InfrastructureAiService;
use App\Services\Ai\NullLlmClient;
use App\Services\Infrastructures\RiskAssessment;
use App\Services\Infrastructures\RiskScorer;
use Illuminate\Database\Seeder;

/**
 * Score de risque initial de chaque ouvrage, sans appel API (texte par règles) et sans notification.
 * Appelé après les seeders des incidents et des analyses d'eau pour tenir compte de leurs données.
 */
class InfrastructureRiskSeeder extends Seeder
{
    public function run(): void
    {
        $assessment = new RiskAssessment(new RiskScorer, new InfrastructureAiService(new NullLlmClient));

        Infrastructure::query()->each(fn (Infrastructure $infrastructure) => $assessment->assess($infrastructure, notify: false));
    }
}
