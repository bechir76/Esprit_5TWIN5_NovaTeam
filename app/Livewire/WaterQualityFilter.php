<?php

namespace App\Livewire;

use App\Enums\WaterParameter;
use App\Models\WaterAnalysis;
use App\Models\Zone;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Page publique /qualite-eau : filtre par zone en direct et dernière analyse validée de chaque zone
 * (WaterAnalysis::latestValidatedByZone()). La zone choisie reste dans l'URL (?zone=ID).
 */
class WaterQualityFilter extends Component
{
    #[Url(except: '')]
    public string $zone = '';

    public function resetZone(): void
    {
        $this->zone = '';
    }

    public function render(): View
    {
        $selectedZone = ctype_digit($this->zone) ? (int) $this->zone : null;

        // A1/A4 : charger les zones avec leur WQRI pour le feu tricolore public
        $zones = Zone::orderBy('name')->get();

        // Indexer les zones par id pour accès rapide dans la vue
        $zonesById = $zones->keyBy('id');

        return view('livewire.water-quality-filter', [
            'zones'        => $zones,
            'zonesById'    => $zonesById,
            'lastByZone'   => WaterAnalysis::latestValidatedByZone($selectedZone),
            'mainParams'   => WaterParameter::publicMain(),
            'selectedZone' => $selectedZone,
        ]);
    }
}
