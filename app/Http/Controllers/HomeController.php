<?php

namespace App\Http\Controllers;

use App\Enums\AnalysisStatus;
use App\Enums\WaterParameter;
use App\Models\Infrastructure;
use App\Models\WaterAnalysis;
use Illuminate\View\View;

/** Accueil du site citoyen. */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        // Dernière analyse validée pour le bloc "Qualité mesurée aujourd'hui" du hero
        $latestAnalysis = WaterAnalysis::query()
            ->with(['measurements', 'infrastructure'])
            ->ofStatus(AnalysisStatus::Validee)
            ->orderByDesc('sampled_at')
            ->first();

        // Indexer les mesures par paramètre pour un accès direct dans la vue
        $latestMeasurements = $latestAnalysis
            ? $latestAnalysis->measurements->keyBy(
                fn ($m) => $m->parameter instanceof WaterParameter
                    ? $m->parameter->value
                    : $m->parameter
            )
            : collect();

        return view('front.home', [
            'infrastructureCount'  => Infrastructure::count(),
            'latestAnalysis'       => $latestAnalysis,
            'latestMeasurements'   => $latestMeasurements,
        ]);
    }
}
