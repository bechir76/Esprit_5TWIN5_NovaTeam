<?php

namespace Tests\Feature;

use App\Enums\IncidentStatus;
use App\Livewire\ZoneMaintenancePlan;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\User;
use App\Models\Zone;
use App\Services\Ai\LlmClient;
use App\Services\Infrastructures\MaintenancePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * B3 : plan de maintenance d'une zone. Le code choisit et classe les ouvrages ; le LLM (simulé) rédige les actions,
 * validées avant usage ; repli par règles ; export PDF.
 */
class ZoneMaintenancePlanTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.provider' => 'anthropic', 'ai.api_key' => null]);
        $this->travelTo(now()->setDate(2026, 10, 21)->setTime(10, 0));
        $this->zone = Zone::factory()->create(['name' => 'La Soukra']);

        $make = fn (string $code, string $status, ?int $risk, string $inspection) => tap(
            Infrastructure::factory()->for($this->zone)->reservoir()->create(['code' => $code, 'name' => "Ouvrage {$code}", 'status' => $status, 'last_inspection_at' => $inspection]),
            fn (Infrastructure $i) => $i->forceFill(['risk_score' => $risk, 'risk_level' => $risk === null ? null : ($risk >= 70 ? 'critique' : ($risk >= 45 ? 'eleve' : 'faible'))])->saveQuietly(),
        );

        // Priorités : risque + retard (jusqu'à 30) + 5 par incident ouvert (jusqu'à 25) + 10 si critique
        $make('RES-ARI-001', 'bon', 20, '2026-10-01');            // 20
        $make('RES-ARI-002', 'critique', 72, '2026-01-01');       // 72 + 30 (retard de 203 j) + 10 = 112
        $mid = $make('RES-ARI-003', 'a_surveiller', 50, '2026-09-01'); // 50 + 2 incidents ouverts = 60
        $make('RES-ARI-004', 'bon', null, '2026-10-01');          // 0
        $make('RES-ARI-005', 'bon', 10, '2026-10-01');            // 10
        $make('RES-ARI-006', 'bon', 30, '2026-10-01');            // 30
        Incident::factory()->count(2)->for($mid)->create(['status' => IncidentStatus::Signale]);
        Infrastructure::factory()->for(Zone::factory()->create(['name' => 'Zone témoin']))->create(['code' => 'RES-TUN-099']); // autre zone
    }

    private function fakeLlm(?array $json): FakeInfrastructureLlm
    {
        $fake = new FakeInfrastructureLlm($json);
        $this->app->instance(LlmClient::class, $fake);

        return $fake;
    }

    /** Réponse valide pour les 5 rangs. */
    private static function answer(): array
    {
        return [
            'summary' => 'Priorité au réservoir critique dont l\'inspection a plus de six mois de retard, puis aux incidents ouverts.',
            'actions' => collect(range(1, 5))->map(fn (int $rank) => [
                'rank' => $rank, 'action' => "Action rédigée pour le rang {$rank}.", 'justification' => "Justification rédigée pour le rang {$rank}.",
            ])->all(),
        ];
    }

    public function test_code_selects_and_orders_the_top_five_infrastructures(): void
    {
        $items = app(MaintenancePlanner::class)->candidates($this->zone);

        $this->assertSame(['RES-ARI-002', 'RES-ARI-003', 'RES-ARI-006', 'RES-ARI-001', 'RES-ARI-005'], array_column($items, 'code'));
        $this->assertSame([1, 2, 3, 4, 5], array_column($items, 'rank'));
        $this->assertSame([112, 60, 30, 20, 10], array_column($items, 'priority'));
        $this->assertSame(2, $items[1]['open_incidents']);
        $this->assertSame(203, $items[0]['days_overdue']);
    }

    public function test_llm_writes_the_plan_in_the_code_order_without_codes_or_names(): void
    {
        $fake = $this->fakeLlm(self::answer());

        $plan = app(MaintenancePlanner::class)->generate($this->zone);

        $this->assertSame('ia', $plan['source']);
        $this->assertSame(self::answer()['summary'], $plan['summary']);
        $this->assertSame('RES-ARI-002', $plan['actions'][0]['code']);
        $this->assertSame('Action rédigée pour le rang 1.', $plan['actions'][0]['action']);
        $this->assertSame('ia', $this->zone->refresh()->maintenance_plan_source);
        $this->assertNotNull($this->zone->maintenance_plan_at);

        $this->assertStringContainsString('"rang":1', $fake->prompts[0]);
        $this->assertStringContainsString('"jours_retard_inspection":203', $fake->prompts[0]);
        $this->assertStringNotContainsString('RES-ARI', $fake->prompts[0]);
        $this->assertStringNotContainsString('Ouvrage ', $fake->prompts[0]);
    }

    public function test_incomplete_or_invalid_answer_falls_back_to_rules(): void
    {
        $missingRank = self::answer();
        array_pop($missingRank['actions']);
        $tooShort = self::answer();
        $tooShort['actions'][0]['action'] = 'Court';

        foreach ([$missingRank, $tooShort, ['summary' => 'x'], null] as $answer) {
            $this->fakeLlm($answer);
            $plan = app(MaintenancePlanner::class)->generate($this->zone);

            $this->assertSame('regles', $plan['source']);
            $this->assertCount(5, $plan['actions']);
            $this->assertSame('Plan de maintenance de la zone La Soukra : 5 ouvrage(s) prioritaire(s), en commençant par RES-ARI-002 (Ouvrage RES-ARI-002).', $plan['summary']);
            $this->assertSame('Réaliser l\'inspection en retard (203 j) et mettre à jour l\'état de l\'ouvrage.', $plan['actions'][0]['action']);
            $this->assertSame('Traiter les 2 incident(s) ouvert(s) et rechercher une cause commune.', $plan['actions'][1]['action']);
            $this->assertStringStartsWith('Priorité n° 1 : risque 72/100 (critique), état « critique », inspection en retard de 203 j', $plan['actions'][0]['justification']);
        }

        $empty = Zone::factory()->create(['name' => 'Zone vide']);
        $this->assertSame([], app(MaintenancePlanner::class)->generate($empty)['actions']);
    }

    public function test_livewire_button_generates_the_plan_with_loading_state_and_rate_limit(): void
    {
        config(['ai.rate_limit' => 1]);
        $staff = User::factory()->technicien()->create();

        $this->actingAs($staff)->get(route('admin.zones.show', $this->zone))->assertOk()
            ->assertSeeLivewire(ZoneMaintenancePlan::class)
            ->assertSee('Générer un plan de maintenance')
            ->assertSee('action="'.route('admin.zones.maintenance-plan', $this->zone).'"', false);

        $component = Livewire::actingAs($staff)->test(ZoneMaintenancePlan::class, ['zone' => $this->zone])
            ->assertSee('Génération en cours…')
            ->call('generate')
            ->assertHasNoErrors()
            ->assertSee('Plan de maintenance généré : 5 action(s) prioritaire(s).')
            ->assertSee('généré par des règles');

        $html = $component->html();
        $this->assertLessThan(strpos($html, 'Ouvrage RES-ARI-003'), strpos($html, 'Ouvrage RES-ARI-002'));
        $this->assertStringContainsString(e('Réaliser l\'inspection en retard (203 j)'), $html);

        $component->call('generate')->assertHasErrors('plan');
    }

    public function test_route_without_javascript_pdf_export_and_policy(): void
    {
        $manager = User::factory()->gestionnaire()->create();

        $this->actingAs($manager)->get(route('admin.zones.maintenance-plan.pdf', $this->zone))
            ->assertRedirect(route('admin.zones.show', $this->zone))
            ->assertSessionHas('error', 'Générez d\'abord le plan de maintenance de la zone.');

        $this->actingAs($manager)->post(route('admin.zones.maintenance-plan', $this->zone))
            ->assertRedirect(route('admin.zones.show', $this->zone))
            ->assertSessionHas('status', 'Plan de maintenance généré : 5 action(s) prioritaire(s).');

        $view = view('pages.zones.maintenance-plan-pdf', ['zone' => $this->zone->refresh()])->render();
        $this->assertStringContainsString('Plan de maintenance', $view);
        $this->assertStringContainsString('Ouvrage RES-ARI-002', $view);
        $this->assertStringContainsString(e('Réaliser l\'inspection en retard (203 j)'), $view);

        $response = $this->actingAs($manager)->get(route('admin.zones.maintenance-plan.pdf', $this->zone));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('plan-maintenance-la-soukra.pdf', $response->headers->get('content-disposition'));

        $citizen = User::factory()->citoyen()->create();
        $this->actingAs($citizen)->post(route('admin.zones.maintenance-plan', $this->zone))->assertForbidden();
        $this->actingAs($citizen)->get(route('admin.zones.maintenance-plan.pdf', $this->zone))->assertForbidden();
        $this->assertFalse($citizen->can('planMaintenance', $this->zone));
    }
}
