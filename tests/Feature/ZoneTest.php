<?php

namespace Tests\Feature;

use App\Models\Infrastructure;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZoneTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->technicien()->create();
    }

    /** @return array<string, mixed> */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ariana Nord',
            'municipality' => 'Ariana',
            'governorate' => 'Ariana',
            'population_served' => '115 000',
            'description' => 'Ariana Ville et Ennasr.',
        ], $overrides);
    }

    public function test_staff_can_list_zones_with_infrastructure_count(): void
    {
        $zone = Zone::factory()->create(['name' => 'La Soukra']);
        Infrastructure::factory(3)->for($zone)->create();
        Zone::factory()->create(['name' => 'Mégrine']);

        $this->actingAs($this->staff())
            ->get(route('admin.zones.index'))
            ->assertOk()
            ->assertSee('La Soukra')
            ->assertSee('Mégrine')
            ->assertViewHas('zones', fn ($zones) => $zones->firstWhere('name', 'La Soukra')->infrastructures_count === 3);
    }

    public function test_zones_can_be_searched(): void
    {
        Zone::factory()->create(['name' => 'Tunis Centre']);
        Zone::factory()->create(['name' => 'La Manouba', 'municipality' => 'La Manouba', 'governorate' => 'Manouba']);

        $this->actingAs($this->staff())
            ->get(route('admin.zones.index', ['q' => 'Tunis']))
            ->assertSee('Tunis Centre')
            ->assertDontSee('La Manouba');
    }

    public function test_show_lists_the_zone_infrastructures(): void
    {
        $zone = Zone::factory()->create();
        Infrastructure::factory()->for($zone)->create(['name' => 'Réservoir Mégrine']);
        Infrastructure::factory()->create(['name' => 'Ouvrage d\'une autre zone']);

        $this->actingAs($this->staff())
            ->get(route('admin.zones.show', $zone))
            ->assertOk()
            ->assertSee('Réservoir Mégrine')
            ->assertDontSee('Ouvrage d&#039;une autre zone', false);
    }

    public function test_staff_can_create_a_zone(): void
    {
        $response = $this->actingAs($this->staff())->post(route('admin.zones.store'), $this->validData());

        $zone = Zone::firstWhere('name', 'Ariana Nord');
        $response->assertRedirect(route('admin.zones.show', $zone))->assertSessionHas('status');
        $this->assertSame(115000, $zone->population_served);
        $this->assertSame('ARI', $zone->governorateCode());
    }

    public function test_zone_creation_is_validated(): void
    {
        Zone::factory()->create(['name' => 'Ariana Nord']);

        $this->actingAs($this->staff())
            ->from(route('admin.zones.create'))
            ->post(route('admin.zones.store'), $this->validData([
                'municipality' => '',
                'governorate' => 'Paris',
                'population_served' => '-5',
            ]))
            ->assertRedirect(route('admin.zones.create'))
            ->assertSessionHasErrors(['name', 'municipality', 'governorate', 'population_served']);

        $this->assertSame(1, Zone::count());
    }

    public function test_staff_can_update_a_zone_keeping_its_name(): void
    {
        $zone = Zone::factory()->create(['name' => 'Ariana Nord']);

        $this->actingAs($this->staff())
            ->put(route('admin.zones.update', $zone), $this->validData(['population_served' => '120000']))
            ->assertRedirect(route('admin.zones.show', $zone))
            ->assertSessionHasNoErrors();

        $this->assertSame(120000, $zone->fresh()->population_served);
    }

    public function test_update_rejects_the_name_of_another_zone(): void
    {
        Zone::factory()->create(['name' => 'Mégrine']);
        $zone = Zone::factory()->create(['name' => 'Ben Arous']);

        $this->actingAs($this->staff())
            ->put(route('admin.zones.update', $zone), $this->validData(['name' => 'Mégrine']))
            ->assertSessionHasErrors(['name' => 'Une zone porte déjà ce nom.']);
    }

    public function test_empty_zone_can_be_deleted(): void
    {
        $zone = Zone::factory()->create();

        $this->actingAs($this->staff())
            ->delete(route('admin.zones.destroy', $zone))
            ->assertRedirect(route('admin.zones.index'))
            ->assertSessionHas('status');

        $this->assertModelMissing($zone);
    }

    public function test_zone_with_infrastructures_cannot_be_deleted(): void
    {
        $zone = Zone::factory()->create();
        Infrastructure::factory(2)->for($zone)->create();

        $this->actingAs($this->staff())
            ->from(route('admin.zones.show', $zone))
            ->delete(route('admin.zones.destroy', $zone))
            ->assertRedirect(route('admin.zones.show', $zone))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, '2 infrastructure(s)'));

        $this->assertModelExists($zone);
        $this->assertSame(2, $zone->infrastructures()->count());
    }

    public function test_citizen_cannot_access_zones(): void
    {
        $zone = Zone::factory()->create();

        $this->actingAs(User::factory()->citoyen()->create());
        $this->get(route('admin.zones.index'))->assertForbidden();
        $this->post(route('admin.zones.store'), $this->validData())->assertForbidden();
        $this->delete(route('admin.zones.destroy', $zone))->assertForbidden();

        $this->assertModelExists($zone);
    }
}
