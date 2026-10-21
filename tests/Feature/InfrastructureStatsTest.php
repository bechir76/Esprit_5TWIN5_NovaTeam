<?php

namespace Tests\Feature;

use App\Models\Infrastructure;
use App\Models\User;
use App\Models\Zone;
use App\Services\Infrastructures\InfrastructureStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** A8 : statistiques du réseau (InfrastructureStatsService avec cache, page Chart.js). */
class InfrastructureStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 21)->setTime(10, 0));
    }

    private function network(): array
    {
        $ariana = Zone::factory()->create(['name' => 'Ariana Nord']);
        $soukra = Zone::factory()->create(['name' => 'La Soukra']);

        // Ariana : 2 ouvrages de 20 et 40 ans, risque 30 et 70, 1 inspection en retard
        Infrastructure::factory()->for($ariana)->reservoir()->good()->create(['commissioned_year' => 2006, 'last_inspection_at' => '2026-10-01'])
            ->forceFill(['risk_score' => 30])->saveQuietly();
        Infrastructure::factory()->for($ariana)->conduite()->critical()->create(['commissioned_year' => 1986, 'last_inspection_at' => '2026-01-01'])
            ->forceFill(['risk_score' => 70])->saveQuietly();
        // La Soukra : 1 ouvrage de 10 ans, non évalué
        Infrastructure::factory()->for($soukra)->reservoir()->toWatch()->create(['commissioned_year' => 2016, 'last_inspection_at' => '2026-09-01']);

        return [$ariana, $soukra];
    }

    public function test_service_counts_by_status_and_type_and_averages_by_zone(): void
    {
        $this->network();
        $stats = app(InfrastructureStatsService::class)->compute();

        $this->assertSame(3, $stats['total']);
        $this->assertSame(23.3, $stats['averageAge']);
        $this->assertSame(50.0, $stats['averageRisk']);
        $this->assertSame(1, $stats['overdue']);
        $this->assertSame([['label' => 'Bon', 'count' => 1, 'color' => '#2EC4B6'], ['label' => 'À surveiller', 'count' => 1, 'color' => '#F4A261'], ['label' => 'Critique', 'count' => 1, 'color' => '#E63946']], $stats['byStatus']);
        $this->assertSame(['Réservoirs' => 2, 'Conduites principales' => 1], collect($stats['byType'])->filter(fn ($t) => $t['count'] > 0)->pluck('count', 'label')->all());
        $this->assertSame([
            ['zone' => 'Ariana Nord', 'count' => 2, 'averageAge' => 30.0, 'averageRisk' => 50.0, 'overdue' => 1],
            ['zone' => 'La Soukra', 'count' => 1, 'averageAge' => 10.0, 'averageRisk' => null, 'overdue' => 0],
        ], $stats['byZone']);
    }

    public function test_stats_are_cached_and_forgotten_when_an_infrastructure_changes(): void
    {
        [$ariana] = $this->network();
        $service = app(InfrastructureStatsService::class);

        $this->assertSame(3, $service->all()['total']);
        $this->assertTrue(Cache::has(InfrastructureStatsService::CACHE_KEY));

        Infrastructure::factory()->for($ariana)->create();
        $this->assertFalse(Cache::has(InfrastructureStatsService::CACHE_KEY));
        $this->assertSame(4, $service->all()['total']);

        Infrastructure::first()->delete();
        $this->assertSame(3, $service->all()['total']);
    }

    public function test_page_draws_the_charts_and_is_refused_to_citizens(): void
    {
        $this->network();

        $this->actingAs(User::factory()->technicien()->create())
            ->get(route('admin.infrastructures.stats'))->assertOk()
            ->assertSee(['Ouvrages par état', 'Ouvrages par type', 'Âge moyen par zone (ans)', 'Risque moyen par zone (/100)', 'Inspections en retard par zone'])
            ->assertSee('https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', false)
            ->assertSee('id="chartRisk"', false)
            ->assertSeeInOrder(['Ariana Nord', '2', '30,0 ans', '50,0', '1']);

        $this->actingAs(User::factory()->citoyen()->create())->get(route('admin.infrastructures.stats'))->assertForbidden();
    }
}
