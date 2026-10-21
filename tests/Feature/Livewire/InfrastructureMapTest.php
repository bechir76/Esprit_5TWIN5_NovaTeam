<?php

namespace Tests\Feature\Livewire;

use App\Livewire\InfrastructureMap;
use App\Models\Infrastructure;
use App\Models\User;
use App\Models\Zone;
use App\Services\Ai\InfrastructureAiService;
use App\Services\Ai\NullLlmClient;
use App\Services\Infrastructures\RiskAssessment;
use App\Services\Infrastructures\RiskScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/** A7 : carte Leaflet du réseau (site citoyen et console), filtres Livewire sans rechargement. */
class InfrastructureMapTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = Zone::factory()->create(['name' => 'La Soukra']);
        $assessment = new RiskAssessment(new RiskScorer, new InfrastructureAiService(new NullLlmClient));

        $critical = Infrastructure::factory()->for($this->zone)->reservoir()->critical()->create([
            'name' => 'Réservoir de La Soukra', 'material' => 'Fonte grise', 'commissioned_year' => 1965, 'last_inspection_at' => null,
        ]);
        $assessment->assess($critical, notify: false);
        // Autre zone au nom fixe : la factory pourrait sinon générer « La Soukra » (nom de zone unique)
        $other = Zone::factory()->create(['name' => 'Zone témoin']);
        Infrastructure::factory()->for($other)->forage()->good()->create(['name' => 'Forage de Mornag F3', 'last_inspection_at' => now()->subMonth()]);
        Infrastructure::factory()->for($this->zone)->hidden()->create(['name' => 'Ouvrage confidentiel']);
        Infrastructure::factory()->for($other)->withoutCoordinates()->create(['name' => 'Ouvrage sans coordonnées']);
    }

    /** @return list<string> */
    private static function names(array $markers): array
    {
        return collect($markers)->pluck('name')->sort()->values()->all();
    }

    public function test_public_map_shows_only_public_geolocated_infrastructures_with_popup_data(): void
    {
        $markers = Livewire::test(InfrastructureMap::class, ['public' => true])->instance()->markers();

        $this->assertSame(['Forage de Mornag F3', 'Réservoir de La Soukra'], self::names($markers));

        $reservoir = collect($markers)->firstWhere('name', 'Réservoir de La Soukra');
        $this->assertSame('Réservoir', $reservoir['type']);
        $this->assertSame('La Soukra', $reservoir['zone']);
        $this->assertSame('#E63946', $reservoir['color']);
        $this->assertSame(Infrastructure::firstWhere('name', 'Réservoir de La Soukra')->risk_level->label(), $reservoir['riskLabel']);
        $this->assertSame(now()->format('d/m/Y'), $reservoir['nextInspection']);
        $this->assertSame(route('incidents.create'), $reservoir['url']);
    }

    public function test_filters_send_new_markers_without_reloading(): void
    {
        $level = Infrastructure::firstWhere('name', 'Réservoir de La Soukra')->risk_level->value;
        $sent = fn (array $expected) => fn (string $event, array $params) => self::names($params['markers']) === $expected;

        Livewire::test(InfrastructureMap::class, ['public' => true])
            ->set('type', 'forage')
            ->assertDispatched('infrastructure-map-updated', $sent(['Forage de Mornag F3']))
            ->assertSee('1</span> ouvrage(s) affiché(s)', false)
            ->set('type', '')
            ->set('status', 'critique')
            ->assertDispatched('infrastructure-map-updated', $sent(['Réservoir de La Soukra']))
            ->set('status', '')
            ->set('zone', (string) $this->zone->id)
            ->assertDispatched('infrastructure-map-updated', $sent(['Réservoir de La Soukra']))
            ->set('zone', '')
            ->set('risk', $level)
            ->assertDispatched('infrastructure-map-updated', $sent(['Réservoir de La Soukra']))
            ->call('resetFilters')
            ->assertDispatched('infrastructure-map-updated', $sent(['Forage de Mornag F3', 'Réservoir de La Soukra']));
    }

    public function test_public_mode_cannot_be_switched_off_by_the_browser(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(InfrastructureMap::class, ['public' => true])->set('public', false);
    }

    public function test_admin_map_shows_hidden_infrastructures_and_is_refused_to_citizens(): void
    {
        $staff = User::factory()->technicien()->create();

        $markers = Livewire::actingAs($staff)->test(InfrastructureMap::class)->instance()->markers();
        $this->assertSame(['Forage de Mornag F3', 'Ouvrage confidentiel', 'Réservoir de La Soukra'], self::names($markers));
        $this->assertStringStartsWith(url('/admin/infrastructures/'), $markers[0]['url']);

        Livewire::actingAs(User::factory()->citoyen()->create())->test(InfrastructureMap::class)->assertForbidden();
    }

    public function test_pages_load_leaflet_and_send_markers_with_js_from(): void
    {
        $this->get(route('map'))->assertOk()
            ->assertSeeLivewire(InfrastructureMap::class)
            ->assertSee('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', false)
            ->assertSee('integrity="sha384-', false)
            ->assertSee('L.markerClusterGroup', false)
            ->assertSee("JSON.parse('", false)
            ->assertDontSee('Ouvrage confidentiel');

        // Console : la carte est chargée après le tableau (lazy)
        $this->actingAs(User::factory()->gestionnaire()->create())
            ->get(route('admin.infrastructures.index'))->assertOk()
            ->assertSee('Carte des ouvrages')
            ->assertSee('Chargement de la carte…');
    }
}
