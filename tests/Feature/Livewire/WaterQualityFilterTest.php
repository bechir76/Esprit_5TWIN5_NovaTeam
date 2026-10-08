<?php

namespace Tests\Feature\Livewire;

use App\Enums\AnalysisStatus;
use App\Livewire\WaterQualityFilter;
use App\Models\Infrastructure;
use App\Models\WaterAnalysis;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WaterQualityFilterTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Zone, 1: Zone} */
    private function twoZones(): array
    {
        $zoneA = Zone::factory()->create(['name' => 'Zone Alpha']);
        $zoneB = Zone::factory()->create(['name' => 'Zone Bêta']);

        WaterAnalysis::factory()->for(Infrastructure::factory()->for($zoneA)->create(['name' => 'Forage Alpha']))
            ->create(['status' => AnalysisStatus::Validee]);
        WaterAnalysis::factory()->for(Infrastructure::factory()->for($zoneB)->create(['name' => 'Réservoir Bêta']))
            ->create(['status' => AnalysisStatus::Validee]);

        return [$zoneA, $zoneB];
    }

    public function test_page_contains_the_component(): void
    {
        $this->get(route('water-quality'))
            ->assertOk()
            ->assertSee('Surveillance en temps réel')
            ->assertSeeLivewire(WaterQualityFilter::class);
    }

    public function test_zone_filter_updates_the_list_without_reload(): void
    {
        [$zoneA, $zoneB] = $this->twoZones();

        Livewire::test(WaterQualityFilter::class)
            ->assertSee('Forage Alpha')
            ->assertSee('Réservoir Bêta')
            ->set('zone', (string) $zoneA->id)
            ->assertSee('Forage Alpha')
            ->assertDontSee('Réservoir Bêta')
            ->set('zone', (string) $zoneB->id)
            ->assertSee('Réservoir Bêta')
            ->assertDontSee('Forage Alpha')
            ->call('resetZone')
            ->assertSee('Forage Alpha')
            ->assertSee('Réservoir Bêta');
    }

    public function test_zone_is_read_from_the_url(): void
    {
        [$zoneA] = $this->twoZones();

        Livewire::withQueryParams(['zone' => $zoneA->id])
            ->test(WaterQualityFilter::class)
            ->assertSee('Forage Alpha')
            ->assertDontSee('Réservoir Bêta');
    }

    public function test_only_validated_analyses_are_listed(): void
    {
        WaterAnalysis::factory()->for(Infrastructure::factory()->create(['name' => 'Forage en attente']))
            ->create(['status' => AnalysisStatus::EnAttente]);

        Livewire::test(WaterQualityFilter::class)
            ->assertDontSee('Forage en attente')
            ->assertSee('Aucune analyse validée disponible.');
    }
}
