<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InfrastructureStatus;
use App\Http\Controllers\Controller;
use App\Models\Infrastructure;
use Illuminate\View\View;

/** Tableau de bord de la console. Seuls les indicateurs des modules implémentés viennent de la base. */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('back.dashboard', [
            'atRiskCount' => Infrastructure::atRisk()->count(),
            'criticalCount' => Infrastructure::ofStatus(InfrastructureStatus::Critique)->count(),
        ]);
    }
}
