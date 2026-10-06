<?php

namespace App\Http\Controllers;

use App\Enums\AnalysisStatus;
use App\Enums\WaterParameter;
use App\Models\WaterAnalysis;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Page publique /qualite-eau.
 * Pour chaque zone, affiche la dernière analyse validée avec ses mesures principales.
 */
class WaterQualityController extends Controller
{
    public function __invoke(Request $request): View
    {
        $selectedZone = $request->integer('zone') ?: null;

        $zones = Zone::orderBy('name')->get();

        // Dernières analyses validées par zone (une seule requête chargée en mémoire)
        $analysesQuery = WaterAnalysis::query()
            ->with(['measurements', 'infrastructure.zone'])
            ->ofStatus(AnalysisStatus::Validee)
            ->orderByDesc('sampled_at');

        if ($selectedZone) {
            $analysesQuery->inZone($selectedZone);
        }

        // On garde la dernière analyse validée par zone
        $lastByZone = $analysesQuery->get()
            ->groupBy(fn ($a) => $a->infrastructure->zone_id ?? 0)
            ->map(fn ($group) => $group->first())
            ->values();

        // Paramètres principaux à afficher sur la page publique
        $mainParams = [
            WaterParameter::Chlore,
            WaterParameter::Ph,
            WaterParameter::Turbidite,
            WaterParameter::Nitrate,
        ];

        return view('front.water-quality', [
            'zones'       => $zones,
            'lastByZone'  => $lastByZone,
            'mainParams'  => $mainParams,
            'selectedZone' => $selectedZone,
        ]);
    }
}
