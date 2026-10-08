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

        return view('livewire.water-quality-filter', [
            'zones' => Zone::orderBy('name')->get(),
            'lastByZone' => WaterAnalysis::latestValidatedByZone($selectedZone),
            'mainParams' => WaterParameter::publicMain(),
            'selectedZone' => $selectedZone,
        ]);
    }
}
