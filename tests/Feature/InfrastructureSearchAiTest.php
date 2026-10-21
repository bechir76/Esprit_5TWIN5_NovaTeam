<?php

namespace Tests\Feature;

use App\Livewire\InfrastructureTable;
use App\Models\Infrastructure;
use App\Models\User;
use App\Models\Zone;
use App\Services\Ai\InfrastructureAiService;
use App\Services\Ai\LlmClient;
use App\Services\Ai\NullLlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * B2 : recherche en langage naturel. Le LLM (simulé) ne renvoie qu'un JSON de filtres autorisés, validé par le code ;
 * aucune requête SQL n'est générée. Sans clé API : détection de mots-clés.
 */
class InfrastructureSearchAiTest extends TestCase
{
    use RefreshDatabase;

    private Zone $ariana;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.provider' => 'anthropic', 'ai.api_key' => null]);
        $this->travelTo(now()->setDate(2026, 10, 21)->setTime(10, 0));

        $this->ariana = Zone::factory()->create(['name' => 'Ariana Nord']);
        $other = Zone::factory()->create(['name' => 'Mégrine']);

        Infrastructure::factory()->for($this->ariana)->reservoir()->critical()->create(['name' => 'Vieux réservoir d\'Ariana', 'commissioned_year' => 1970, 'last_inspection_at' => '2026-01-01'])
            ->forceFill(['risk_score' => 72, 'risk_level' => 'critique'])->saveQuietly();
        Infrastructure::factory()->for($this->ariana)->reservoir()->good()->create(['name' => 'Réservoir récent d\'Ariana', 'commissioned_year' => 2015, 'last_inspection_at' => '2026-10-01'])
            ->forceFill(['risk_score' => 12, 'risk_level' => 'faible'])->saveQuietly();
        Infrastructure::factory()->for($other)->conduite()->critical()->create(['name' => 'Conduite de Mégrine', 'commissioned_year' => 1960, 'last_inspection_at' => '2026-01-01']);
    }

    private function fakeLlm(?array $json): FakeInfrastructureLlm
    {
        $fake = new FakeInfrastructureLlm($json);
        $this->app->instance(LlmClient::class, $fake);

        return $fake;
    }

    private function table()
    {
        return Livewire::actingAs(User::factory()->technicien()->create())->test(InfrastructureTable::class);
    }

    public function test_llm_json_is_validated_then_applied_as_filter_chips(): void
    {
        $fake = $this->fakeLlm(['type' => 'reservoir', 'status' => '', 'zone_id' => $this->ariana->id, 'risk_level' => 'critique',
            'inspection_overdue' => true, 'min_age' => 40, 'sort' => 'risk']);

        $this->table()
            ->set('sentence', 'vieux réservoirs très risqués à Ariana pas inspectés, contact admin@exemple.tn 22 123 456')
            ->call('interpret')
            ->assertHasNoErrors()
            ->assertSet('type', 'reservoir')
            ->assertSet('zone', (string) $this->ariana->id)
            ->assertSet('risk', 'critique')
            ->assertSet('inspection', 'overdue')
            ->assertSet('minAge', '40')
            ->assertSet('sort', 'risk')
            ->assertSet('interpretedBy', 'ia')
            ->assertSee(['Type : Réservoir', 'Zone : Ariana Nord', 'Risque : Critique', 'Inspection en retard', 'Âge ≥ 40 ans', 'Tri : risque décroissant', 'interprété par l’IA'])
            ->assertSee('Vieux réservoir d&#039;Ariana', false)
            ->assertDontSee('Réservoir récent d&#039;Ariana', false)
            ->assertDontSee('Conduite de Mégrine')
            // Une puce retirée élargit la recherche
            ->call('removeFilter', 'risk')
            ->assertSet('risk', '');

        // Prompt : la phrase (contacts masqués) et les valeurs autorisées ; le schéma n'accepte que des enums
        $this->assertStringContainsString('[e-mail masqué]', $fake->prompts[0]);
        $this->assertStringContainsString('[téléphone masqué]', $fake->prompts[0]);
        $this->assertStringNotContainsString('admin@exemple.tn', $fake->prompts[0]);
        $this->assertStringContainsString('Ariana Nord', $fake->prompts[0]);
        $this->assertSame(['', 'reservoir', 'conduite', 'pompage', 'traitement', 'forage'], $fake->schemas[0]['properties']['type']['enum']);
    }

    public function test_unauthorised_values_are_discarded_and_no_sql_is_ever_run_from_the_answer(): void
    {
        $this->fakeLlm(['type' => 'piscine', 'status' => "critique'; DROP TABLE infrastructures; --", 'zone_id' => 999,
            'risk_level' => 'apocalyptique', 'inspection_overdue' => 'oui', 'min_age' => 999, 'sort' => 'name desc']);

        DB::enableQueryLog();
        $result = app(InfrastructureAiService::class)->parseSearch('ouvrages');

        $this->assertSame('ia', $result['source']);
        $this->assertSame(['type' => null, 'status' => null, 'zone' => null, 'inspection' => null, 'risk' => null, 'min_age' => null, 'sort' => null], $result['filters']);
        $this->assertFalse(collect(DB::getQueryLog())->contains(fn ($q) => str_contains($q['query'], 'DROP')));
        $this->assertSame(3, Infrastructure::count());
    }

    public function test_invalid_answer_or_missing_api_key_falls_back_to_keywords(): void
    {
        $this->fakeLlm(['autre' => 'format']);
        $result = app(InfrastructureAiService::class)->parseSearch('Réservoirs critiques de plus de 40 ans à Ariana Nord en retard d\'inspection, les plus à risque d\'abord');

        $this->assertSame('regles', $result['source']);
        $this->assertSame(['type' => 'reservoir', 'status' => 'critique', 'zone' => $this->ariana->id, 'inspection' => 'overdue', 'risk' => null, 'min_age' => 40, 'sort' => 'risk'], $result['filters']);

        // « risque élevé » est un niveau de risque, pas un état
        $this->app->instance(LlmClient::class, new NullLlmClient);
        $result = app(InfrastructureAiService::class)->parseSearch('conduites à risque élevé à Mégrine');
        $this->assertSame('regles', $result['source']);
        $this->assertSame('conduite', $result['filters']['type']);
        $this->assertSame('eleve', $result['filters']['risk']);
        $this->assertNull($result['filters']['status']);
        $this->assertSame(Zone::firstWhere('name', 'Mégrine')->id, $result['filters']['zone']);

        $this->table()->set('sentence', 'forages en bon état')->call('interpret')
            ->assertSet('type', 'forage')->assertSet('status', 'bon')->assertSet('interpretedBy', 'regles')
            ->assertSee('mots-clés')
            ->assertSee('Aucune infrastructure ne correspond à ces critères.');
    }

    public function test_sentence_is_validated_and_rate_limited(): void
    {
        config(['ai.rate_limit' => 1]);

        $this->table()
            ->call('interpret')->assertHasErrors(['sentence' => 'required'])
            ->set('sentence', str_repeat('a', 201))->call('interpret')->assertHasErrors(['sentence' => 'max'])
            ->set('sentence', 'réservoirs')->call('interpret')->assertHasNoErrors()->assertSet('type', 'reservoir')
            ->set('sentence', 'conduites')->call('interpret')->assertHasErrors('sentence')->assertSet('type', 'reservoir');
    }

    public function test_min_age_and_risk_filters_also_work_from_the_url_and_the_export(): void
    {
        Livewire::withQueryParams(['age' => '50'])->actingAs(User::factory()->technicien()->create())->test(InfrastructureTable::class)
            ->assertSee('Vieux réservoir d&#039;Ariana', false)
            ->assertSee('Conduite de Mégrine')
            ->assertDontSee('Réservoir récent d&#039;Ariana', false);

        $csv = $this->actingAs(User::factory()->gestionnaire()->create())
            ->get(route('admin.infrastructures.export', ['risk' => 'faible']))->streamedContent();
        $this->assertStringContainsString('Réservoir récent', $csv);
        $this->assertStringNotContainsString('Vieux réservoir', $csv);
    }
}
