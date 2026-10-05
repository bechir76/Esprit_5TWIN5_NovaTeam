<?php

namespace Tests\Feature;

use App\Enums\InfrastructureStatus;
use App\Enums\InfrastructureType;
use App\Models\Infrastructure;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfrastructureTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    /** @return array<string, mixed> */
    private function validData(Zone $zone, array $overrides = []): array
    {
        return array_merge([
            'code' => 'RES-ARI-012',
            'name' => 'Réservoir El Menzah 9',
            'type' => 'reservoir',
            'material' => 'Béton armé',
            'capacity' => '6000',
            'capacity_unit' => 'm3',
            'commissioned_year' => '1995',
            'nominal_pressure' => '4,5',
            'status' => 'a_surveiller',
            'last_inspection_at' => now()->subMonth()->format('Y-m-d'),
            'zone_id' => $zone->id,
            'latitude' => '36.8430',
            'longitude' => '10.1715',
            'description' => 'Fissures relevées sur le radier.',
            'is_public' => '1',
        ], $overrides);
    }

    // ------------------------------------------------------------ Liste

    public function test_staff_can_list_infrastructures_with_their_zone(): void
    {
        $zone = Zone::factory()->create(['name' => 'Ariana Nord']);
        Infrastructure::factory()->for($zone)->create(['code' => 'PMP-ARI-003', 'name' => 'Station de pompage Ariana Nord']);

        $this->actingAs($this->staff())
            ->get(route('admin.infrastructures.index'))
            ->assertOk()
            ->assertSee('PMP-ARI-003')
            ->assertSee('Station de pompage Ariana Nord')
            ->assertSee('Ariana Nord');
    }

    public function test_index_filters_by_search_type_status_and_zone(): void
    {
        $ariana = Zone::factory()->create();
        $tunis = Zone::factory()->create();
        Infrastructure::factory()->for($ariana)->reservoir()->critical()->create(['name' => 'Réservoir de La Soukra']);
        Infrastructure::factory()->for($ariana)->conduite()->good()->create(['name' => 'Conduite DN 300 Ennasr']);
        Infrastructure::factory()->for($tunis)->reservoir()->critical()->create(['name' => 'Réservoir Belvédère']);

        $this->actingAs($this->staff());

        $this->get(route('admin.infrastructures.index', ['q' => 'Soukra']))
            ->assertSee('Réservoir de La Soukra')->assertDontSee('Réservoir Belvédère');

        $this->get(route('admin.infrastructures.index', ['type' => 'conduite']))
            ->assertSee('Conduite DN 300 Ennasr')->assertDontSee('Réservoir de La Soukra');

        $this->get(route('admin.infrastructures.index', ['status' => 'critique', 'zone' => $tunis->id]))
            ->assertSee('Réservoir Belvédère')->assertDontSee('Réservoir de La Soukra')->assertDontSee('Conduite DN 300 Ennasr');
    }

    public function test_index_is_paginated_and_keeps_filters_in_links(): void
    {
        Infrastructure::factory(12)->reservoir()->create();

        $this->actingAs($this->staff())
            ->get(route('admin.infrastructures.index', ['type' => 'reservoir']))
            ->assertOk()
            ->assertSee('Affichage 1 à 10 sur 12')
            ->assertSee('type=reservoir&amp;page=2', false);
    }

    public function test_staff_can_view_an_infrastructure(): void
    {
        $infrastructure = Infrastructure::factory()->create(['name' => 'Forage de Mornag F3']);

        $this->actingAs($this->staff())
            ->get(route('admin.infrastructures.show', $infrastructure))
            ->assertOk()
            ->assertSee('Forage de Mornag F3')
            ->assertSee($infrastructure->zone->name);
    }

    // ------------------------------------------------------------ Création

    public function test_create_form_lists_zones(): void
    {
        Zone::factory()->create(['name' => 'Mégrine']);

        $this->actingAs($this->staff())
            ->get(route('admin.infrastructures.create'))
            ->assertOk()
            ->assertSee('Mégrine');
    }

    public function test_staff_can_create_an_infrastructure(): void
    {
        $zone = Zone::factory()->create();

        $response = $this->actingAs($this->staff())
            ->post(route('admin.infrastructures.store'), $this->validData($zone, ['code' => 'res-ari-012']));

        $infrastructure = Infrastructure::firstWhere('code', 'RES-ARI-012');
        $this->assertNotNull($infrastructure, 'Le code est enregistré en majuscules.');
        $response->assertRedirect(route('admin.infrastructures.show', $infrastructure))
            ->assertSessionHas('status');

        $this->assertSame(InfrastructureType::Reservoir, $infrastructure->type);
        $this->assertSame(InfrastructureStatus::ASurveiller, $infrastructure->status);
        $this->assertTrue($infrastructure->zone->is($zone));
        $this->assertSame('4.50', $infrastructure->nominal_pressure);
        $this->assertTrue($infrastructure->is_public);
    }

    public function test_creation_is_validated(): void
    {
        $zone = Zone::factory()->create();
        Infrastructure::factory()->create(['code' => 'RES-ARI-004']);

        $this->actingAs($this->staff())
            ->from(route('admin.infrastructures.create'))
            ->post(route('admin.infrastructures.store'), $this->validData($zone, [
                'code' => 'RES-ARI-004',          // déjà utilisé
                'name' => 'AB',                   // trop court
                'type' => 'chateau',              // hors enum
                'status' => 'inconnu',            // hors enum
                'capacity' => '0',                // doit être > 0
                'commissioned_year' => (string) (now()->year + 1),
                'last_inspection_at' => now()->addDay()->format('Y-m-d'),
                'latitude' => '36.8',
                'longitude' => '',                // les deux ensemble
                'zone_id' => 999,                 // inexistante
            ]))
            ->assertRedirect(route('admin.infrastructures.create'))
            ->assertSessionHasErrors([
                'code', 'name', 'type', 'status', 'capacity', 'commissioned_year',
                'last_inspection_at', 'longitude', 'zone_id',
            ]);

        $this->assertSame(1, Infrastructure::count());
    }

    public function test_code_must_follow_the_format_and_errors_are_in_french(): void
    {
        $zone = Zone::factory()->create();

        $this->actingAs($this->staff())
            ->post(route('admin.infrastructures.store'), $this->validData($zone, ['code' => 'RES-12', 'latitude' => '95', 'longitude' => '10']))
            ->assertSessionHasErrors([
                'code' => 'Le code doit respecter le format ABC-ABC-000 (type, gouvernorat, numéro), par exemple RES-ARI-004.',
                'latitude' => 'La latitude doit être comprise entre -90 et 90.',
            ]);
    }

    // ------------------------------------------------------------ Modification

    public function test_staff_can_update_an_infrastructure_keeping_its_code(): void
    {
        $infrastructure = Infrastructure::factory()->good()->create(['code' => 'RES-TUN-001']);
        $zone = Zone::factory()->create();

        $this->actingAs($this->staff())
            ->put(route('admin.infrastructures.update', $infrastructure), $this->validData($zone, [
                'code' => 'RES-TUN-001',
                'name' => 'Réservoir Belvédère',
                'status' => 'critique',
                'latitude' => '',
                'longitude' => '',
                'is_public' => '0',
            ]))
            ->assertRedirect(route('admin.infrastructures.show', $infrastructure))
            ->assertSessionHasNoErrors();

        $infrastructure->refresh();
        $this->assertSame('Réservoir Belvédère', $infrastructure->name);
        $this->assertSame(InfrastructureStatus::Critique, $infrastructure->status);
        $this->assertTrue($infrastructure->zone->is($zone));
        $this->assertNull($infrastructure->latitude);
        $this->assertFalse($infrastructure->is_public);
    }

    public function test_update_rejects_the_code_of_another_infrastructure(): void
    {
        Infrastructure::factory()->create(['code' => 'RES-TUN-001']);
        $infrastructure = Infrastructure::factory()->create(['code' => 'RES-TUN-002']);

        $this->actingAs($this->staff())
            ->put(route('admin.infrastructures.update', $infrastructure), $this->validData($infrastructure->zone, ['code' => 'RES-TUN-001']))
            ->assertSessionHasErrors('code');
    }

    // ------------------------------------------------------------ Suppression

    public function test_staff_can_delete_an_infrastructure(): void
    {
        $infrastructure = Infrastructure::factory()->create();

        $this->actingAs($this->staff())
            ->delete(route('admin.infrastructures.destroy', $infrastructure))
            ->assertRedirect(route('admin.infrastructures.index'))
            ->assertSessionHas('status');

        $this->assertModelMissing($infrastructure);
    }

    // ------------------------------------------------------------ Accès

    public function test_citizen_and_guest_cannot_access_the_crud(): void
    {
        $infrastructure = Infrastructure::factory()->create();
        $citizen = User::factory()->citoyen()->create();

        $this->get(route('admin.infrastructures.index'))->assertRedirect(route('admin.login'));

        $this->actingAs($citizen);
        $this->get(route('admin.infrastructures.index'))->assertForbidden();
        $this->get(route('admin.infrastructures.create'))->assertForbidden();
        $this->post(route('admin.infrastructures.store'), $this->validData($infrastructure->zone))->assertForbidden();
        $this->put(route('admin.infrastructures.update', $infrastructure), $this->validData($infrastructure->zone))->assertForbidden();
        $this->delete(route('admin.infrastructures.destroy', $infrastructure))->assertForbidden();

        $this->assertModelExists($infrastructure);
    }

    // ------------------------------------------------------------ Front office et tableau de bord

    public function test_public_map_shows_only_public_geolocated_infrastructures_and_filters(): void
    {
        $zone = Zone::factory()->create();
        Infrastructure::factory()->for($zone)->reservoir()->critical()->create(['name' => 'Réservoir de La Soukra']);
        Infrastructure::factory()->for($zone)->forage()->good()->create(['name' => 'Forage de Mornag F3']);
        Infrastructure::factory()->for($zone)->hidden()->create(['name' => 'Ouvrage confidentiel']);
        Infrastructure::factory()->for($zone)->withoutCoordinates()->create(['name' => 'Ouvrage sans coordonnées']);

        // Noms des ouvrages envoyés au JS de la carte (window.AQUA_SITES)
        $names = fn (array $query = []) => $this->get(route('map', $query))->assertOk()->viewData('sites')->pluck('name')->sort()->values()->all();

        $this->assertSame(['Forage de Mornag F3', 'Réservoir de La Soukra'], $names());
        $this->assertSame(['Réservoir de La Soukra'], $names(['status' => ['critique']]));
        $this->assertSame(['Forage de Mornag F3'], $names(['type' => ['forage'], 'zone' => $zone->id]));
        $this->assertSame([], $names(['zone' => $zone->id + 1]));

        $this->get(route('map'))->assertSee('window.AQUA_SITES', false)->assertDontSee('Ouvrage confidentiel');
    }

    public function test_home_and_dashboard_counts_come_from_the_database(): void
    {
        Infrastructure::factory(3)->good()->create();
        Infrastructure::factory(2)->toWatch()->create();
        Infrastructure::factory()->critical()->create();

        $this->get(route('home'))->assertOk()
            ->assertSeeInOrder(['<strong class="mono">6</strong>', 'infrastructures surveillées'], false);

        $this->actingAs($this->staff())->get(route('admin.dashboard'))->assertOk()
            ->assertSeeInOrder(['Ouvrages à risque', '3', 'dont 1 critique']);
    }
}
