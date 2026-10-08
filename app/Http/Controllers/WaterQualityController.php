<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Page publique /qualite-eau.
 * Le filtre par zone et la liste des dernières analyses validées sont rendus par le composant
 * Livewire WaterQualityFilter (paramètre ?zone= lu dans l'URL), à partir de WaterAnalysis::latestValidatedByZone().
 */
class WaterQualityController extends Controller
{
    public function __invoke(): View
    {
        return view('front.water-quality');
    }
}
