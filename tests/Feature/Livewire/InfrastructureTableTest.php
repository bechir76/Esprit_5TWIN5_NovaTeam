<?php

namespace Tests\Feature\Livewire;

use App\Livewire\InfrastructureTable;
use App\Models\Infrastructure;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InfrastructureTableTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    public function test_index_page_contains_the_component(): void
    {
        $this->actingAs($this->staff())
            ->get(route('admin.infrastructures.index'))
            ->assertOk()
            ->assertSeeLivewire(InfrastructureTable::class);
    }

    public function test_live_search_filters_by_name_or_code(): void
    {
        Infrastructure::factory()->create(['name' => 'Réservoir de La Soukra', 'code' => 'RES-ARI-004']);
        Infrastructure::factory()->create(['name' => 'Réservoir Belvédère', 'code' => 'RES-TUN-001']);

        Livewire::actingAs($this->staff())
            ->test(InfrastructureTable::class)
            ->assertSee('Réservoir de La Soukra')
            ->assertSee('Réservoir Belvédère')
            ->set('q', 'Soukra')
            ->assertSee('Réservoir de La Soukra')
            ->assertDontSee('Réservoir Belvédère')
            ->set('q', 'TUN-001')
            ->assertSee('Réservoir Belvédère')
            ->assertDontSee('Réservoir de La Soukra');
    }

    public function test_type_status_and_zone_filters(): void
    {
        $tunis = Zone::factory()->create(['name' => 'Tunis Centre']);
        $ariana = Zone::factory()->create(['name' => 'Ariana Nord']);
        Infrastructure::factory()->reservoir()->critical()->for($tunis)->create(['name' => 'Réservoir critique Tunis']);
        Infrastructure::factory()->reservoir()->good()->for($ariana)->create(['name' => 'Réservoir sain Ariana']);
        Infrastructure::factory()->conduite()->critical()->for($tunis)->create(['name' => 'Conduite critique Tunis']);

        Livewire::actingAs($this->staff())
            ->test(InfrastructureTable::class)
            ->set('type', 'reservoir')
            ->assertSee('Réservoir critique Tunis')
            ->assertSee('Réservoir sain Ariana')
            ->assertDontSee('Conduite critique Tunis')
            ->set('status', 'critique')
            ->assertSee('Réservoir critique Tunis')
            ->assertDontSee('Réservoir sain Ariana')
            ->set('type', '')
            ->set('zone', (string) $tunis->id)
            ->assertSee('Réservoir critique Tunis')
            ->assertSee('Conduite critique Tunis')
            ->assertDontSee('Réservoir sain Ariana')
            ->call('resetFilters')
            ->assertSet('status', '')
            ->assertSee('Réservoir sain Ariana');
    }

    public function test_filters_are_read_from_the_url_and_invalid_values_are_ignored(): void
    {
        Infrastructure::factory()->conduite()->create(['name' => 'Conduite DN 300 Ennasr']);
        Infrastructure::factory()->reservoir()->create(['name' => 'Réservoir de La Soukra']);

        Livewire::withQueryParams(['type' => 'conduite'])
            ->actingAs($this->staff())
            ->test(InfrastructureTable::class)
            ->assertSet('type', 'conduite')
            ->assertSee('Conduite DN 300 Ennasr')
            ->assertDontSee('Réservoir de La Soukra');

        Livewire::actingAs($this->staff())
            ->test(InfrastructureTable::class)
            ->set('type', 'chateau')
            ->assertSee('Conduite DN 300 Ennasr')
            ->assertSee('Réservoir de La Soukra');
    }

    public function test_pagination_and_page_reset_when_a_filter_changes(): void
    {
        Infrastructure::factory(12)->reservoir()->create();

        Livewire::actingAs($this->staff())
            ->test(InfrastructureTable::class)
            ->assertSee('Affichage 1 à 10 sur 12')
            ->call('gotoPage', 2)
            ->assertSee('Affichage 11 à 12 sur 12')
            ->set('type', 'reservoir')
            ->assertSee('Affichage 1 à 10 sur 12');
    }

    public function test_rows_keep_the_delete_modal_attributes(): void
    {
        $infrastructure = Infrastructure::factory()->create(['name' => 'Forage de Mornag F3']);

        Livewire::actingAs($this->staff())
            ->test(InfrastructureTable::class)
            ->assertSeeHtml('data-del="Forage de Mornag F3"')
            ->assertSeeHtml('data-action="'.route('admin.infrastructures.destroy', $infrastructure).'"');
    }
}
