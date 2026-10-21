<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RiskLevel;
use App\Http\Controllers\Controller;
use App\Models\Infrastructure;
use Illuminate\View\View;

/** Page « Analyses IA » : ouvrages les plus à risque (score prédictif réel) ; les autres cartes restent à brancher par leurs modules. */
class AiInsightsController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.ai.insights', [
            'riskiest' => Infrastructure::query()->with('zone')->mostAtRisk()->limit(5)->get(),
            'highRiskCount' => Infrastructure::query()->whereIn('risk_level', [RiskLevel::Eleve->value, RiskLevel::Critique->value])->count(),
            'lastRiskRun' => Infrastructure::query()->max('risk_computed_at'),
        ]);
    }
}
