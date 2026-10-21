<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Carte publique du réseau (Leaflet) : ouvrages publics et géolocalisés uniquement, rendus par le composant
 * Livewire InfrastructureMap (public verrouillé). ?type, ?status et ?zone préremplissent les filtres.
 */
class InfrastructureMapController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('pages.infrastructures.map', [
            'initial' => collect(['type', 'status', 'zone'])
                ->mapWithKeys(fn (string $key) => [$key => is_string($request->query($key)) ? $request->query($key) : ''])
                ->all(),
        ]);
    }
}
