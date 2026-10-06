<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AnalysisStatus;
use App\Enums\InfrastructureStatus;
use App\Http\Controllers\Controller;
use App\Models\Infrastructure;
use App\Models\WaterAnalysis;
use Illuminate\View\View;

/** Tableau de bord de la console. */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        // Dernière analyse non conforme avec sa pire mesure (la plus éloignée du seuil)
        $lastNonCompliant = WaterAnalysis::query()
            ->with(['infrastructure', 'measurements'])
            ->ofStatus(AnalysisStatus::NonConforme)
            ->orderByDesc('sampled_at')
            ->first();

        $worstMeasurement = null;
        if ($lastNonCompliant) {
            $worstMeasurement = $lastNonCompliant->measurements
                ->filter(fn ($m) => ! $m->is_compliant)
                ->first();
        }

        return view('back.dashboard', [
            'atRiskCount'       => Infrastructure::atRisk()->count(),
            'criticalCount'     => Infrastructure::ofStatus(InfrastructureStatus::Critique)->count(),
            'lastNonCompliant'  => $lastNonCompliant,
            'worstMeasurement'  => $worstMeasurement,
        ]);
    }
}
