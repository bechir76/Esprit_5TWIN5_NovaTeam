<?php

namespace Tests\Feature;

use App\Livewire\ProjectAssistant;
use App\Models\Funding;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\Project;
use App\Models\User;
use App\Models\Zone;
use App\Services\Ai\LlmClient;
use App\Services\Ai\NullLlmClient;
use App\Services\Ai\ProjectAiService;
use App\Services\FundingForecast;
use App\Services\ProjectRecommender;
use App\Services\ProjectScoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fonctionnalités IA (B1 à B5) : les calculs sont déterministes, le LLM est simulé (FakeLlm) ou absent
 * (repli sans clé API). Aucun test ne dépend du réseau.
 */
class ProjectAiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Par défaut : aucune clé API, donc repli sans appel réseau
        config(['ai.api_key' => null]);
    }

    /** Remplace le client LLM par un faux qui enregistre les prompts. */
    private function fakeLlm(?array $json = null, ?callable $tools = null): FakeLlm
    {
        $fake = new FakeLlm($json, $tools);
        $this->app->instance(LlmClient::class, $fake);

        return $fake;
    }

    /** Ouvrage critique d'une zone de 60 000 habitants, avec 5 incidents critiques récents et un ancien. */
    private function scoredProject(): Project
    {
        $zone = Zone::factory()->create(['population_served' => 60000]);
        $infrastructure = Infrastructure::factory()->for($zone)->critical()->create();
        $reporter = User::factory()->citoyen()->create(['name' => 'Sana Ben Ali', 'email' => 'sana@example.tn']);

        Incident::factory()->count(5)->for($infrastructure)->for($reporter)->critical()->create(['created_at' => now()->subDays(10)]);
        Incident::factory()->for($infrastructure)->for($reporter)->critical()->create(['created_at' => now()->subDays(200)]);

        return Project::factory()->for($infrastructure)->create(['target_budget' => 250000]);
    }

    // ---------------------------------------------------------------- B1 : priorisation

    public function test_priority_score_is_computed_without_llm(): void
    {
        $project = $this->scoredProject();
        $scoring = app(ProjectScoring::class);
        $factors = $scoring->factors($project);

        // 0,30 × 5/10 + 0,20 × 4/4 + 0,25 × 1 + 0,15 × 0,6 + 0,10 × (1 − 0,5) = 0,74
        $this->assertSame(5, $factors['incidents']['value']);
        $this->assertSame(74, $scoring->score($project, $factors));

        $this->artisan('projects:score', ['--sync' => true])->assertSuccessful();

        $project->refresh();
        $this->assertSame(74, $project->ai_priority_score);
        $this->assertNotNull($project->ai_scored_at);
        // Repli sans clé : explication gabarit à partir des facteurs principaux
        $this->assertStringStartsWith('Priorité élevée (74/100) :', $project->ai_priority_reason);
        $this->assertStringContainsString('ouvrage « critique »', $project->ai_priority_reason);
    }

    public function test_llm_only_writes_the_explanation_and_receives_no_personal_data(): void
    {
        $fake = $this->fakeLlm(['reason' => 'Priorité élevée car 5 incidents critiques en 3 mois sur un ouvrage en état critique.']);
        $project = $this->scoredProject();

        $this->actingAs(User::factory()->gestionnaire()->create())
            ->post(route('admin.projects.score', $project))
            ->assertSessionHas('status', 'Score de priorité recalculé : 74/100.');

        // Le score reste celui du code, le texte vient du LLM
        $this->assertSame(74, $project->fresh()->ai_priority_score);
        $this->assertSame('Priorité élevée car 5 incidents critiques en 3 mois sur un ouvrage en état critique.', $project->fresh()->ai_priority_reason);
        $this->assertStringNotContainsString('Sana', $fake->prompts[0]);
        $this->assertStringNotContainsString('sana@example.tn', $fake->prompts[0]);
    }

    public function test_invalid_llm_answer_falls_back_to_the_template(): void
    {
        $this->fakeLlm(['reason' => 'ok']); // trop court : rejeté par la validation
        $project = $this->scoredProject();
        $scoring = app(ProjectScoring::class);
        $factors = $scoring->factors($project);

        $reason = app(ProjectAiService::class)->priorityReason($factors, 74);

        $this->assertStringStartsWith('Priorité élevée (74/100)', $reason);
    }

    // ---------------------------------------------------------------- B2 : description

    public function test_description_generation_falls_back_without_api_key_and_never_saves(): void
    {
        $admin = User::factory()->gestionnaire()->create();
        $infrastructure = Infrastructure::factory()->create();

        $this->actingAs($admin)->postJson(route('admin.projects.describe'), [
            'title' => 'Remplacement de la conduite de Mégrine',
            'category' => 'renovation',
            'target_budget' => '120000',
            'infrastructure_id' => $infrastructure->id,
        ])->assertOk()
            ->assertJsonPath('description', fn (string $text) => str_contains($text, '« Remplacement de la conduite de Mégrine »')
                && str_contains($text, $infrastructure->code)
                && str_contains($text, '120 000 DT'));

        $this->actingAs($admin)->postJson(route('admin.projects.describe'), ['title' => 'Abc'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'category']);

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_description_uses_a_valid_llm_answer(): void
    {
        $text = 'Les conduites en fonte grise du secteur concentrent la plupart des fuites. Elles seront remplacées par du PEHD et le réseau sera sectorisé.';
        $this->fakeLlm(['description' => $text]);

        $this->actingAs(User::factory()->gestionnaire()->create())
            ->postJson(route('admin.projects.describe'), ['title' => 'Remplacement de la conduite de Mégrine', 'category' => 'renovation'])
            ->assertOk()
            ->assertJson(['description' => $text]);

        // Citoyen : refusé
        $this->actingAs(User::factory()->citoyen()->create())
            ->postJson(route('admin.projects.describe'), ['title' => 'Remplacement de la conduite', 'category' => 'renovation'])
            ->assertForbidden();
    }

    // ---------------------------------------------------------------- B3 : prévision

    public function test_forecast_is_computed_from_the_last_14_days(): void
    {
        $project = Project::factory()->open()->create(['target_budget' => 1000, 'start_date' => today()->subMonth(), 'end_date' => today()->addDays(120)]);
        Funding::factory()->for($project)->municipal()->create(['amount' => 140, 'funded_at' => today()->subDays(3)]);
        Funding::factory()->for($project)->municipal()->create(['amount' => 500, 'funded_at' => today()->subDays(25)]);

        $forecast = app(FundingForecast::class)->for($project);

        // 140 DT en 14 jours = 10 DT / jour ; reste 360 DT → 36 jours
        $this->assertSame(10.0, $forecast['daily_rate']);
        $this->assertTrue($forecast['on_time']);
        $this->assertSame('haute', $forecast['probability']);
        $this->assertSame('Au rythme actuel, objectif atteint le '.today()->addDays(36)->format('d/m').'.', $forecast['message']);

        // Repli sans clé : le message calculé est affiché tel quel sur la page publique
        $this->get(route('projects.show', $project))->assertSee($forecast['message']);

        $series = app(FundingForecast::class)->series($project);
        $this->assertSame(1000.0, end($series['ideal']));
        $this->assertNull(end($series['cumulative']));
    }

    public function test_forecast_without_recent_donations_is_not_reached(): void
    {
        $project = Project::factory()->open()->create(['target_budget' => 1000, 'end_date' => today()->addDays(10)]);

        $forecast = app(FundingForecast::class)->for($project);

        $this->assertSame('faible', $forecast['probability']);
        $this->assertStringStartsWith('Au rythme actuel, objectif non atteint', $forecast['message']);
    }

    // ---------------------------------------------------------------- B4 : recommandations

    public function test_projects_are_recommended_from_the_citizen_reports(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $infrastructure = Infrastructure::factory()->create();
        Incident::factory()->for($infrastructure)->for($citizen)->create(['title' => 'Fuite importante rue Ibn Sina']);
        $repair = Project::factory()->open()->for($infrastructure)->create(['title' => 'Réparation de la conduite Ibn Sina']);
        $zone = Project::factory()->open()->for(Infrastructure::factory()->for($infrastructure->zone))->create();
        Project::factory()->open()->create(['ai_priority_score' => 90]);

        $recommendations = app(ProjectRecommender::class)->for($citizen);

        $this->assertTrue($recommendations[0]['project']->is($repair));
        $this->assertSame('Ce projet répare l\'ouvrage concerné par votre signalement « Fuite importante rue Ibn Sina ».', $recommendations[0]['reason']);
        $this->assertTrue($recommendations[1]['project']->is($zone));
        $this->assertStringContainsString('votre zone', $recommendations[1]['reason']);

        $this->actingAs($citizen)->get(route('projects.index'))
            ->assertSee('Recommandés pour vous')
            ->assertSee('Réparation de la conduite Ibn Sina');
    }

    // ---------------------------------------------------------------- B5 : assistant

    public function test_assistant_refuses_out_of_scope_questions(): void
    {
        $ai = app(ProjectAiService::class);

        $this->assertSame(ProjectAiService::REFUSAL, $ai->answer('Quelle est la météo à Tunis demain ?'));
        $this->assertSame(ProjectAiService::REFUSAL, $ai->answer('Écris-moi un poème sur la mer.'));
    }

    public function test_assistant_answers_with_whitelisted_tools_without_api_key(): void
    {
        Project::factory()->almostFunded()->create(['title' => 'Capteurs connectés de Raoued']);
        $soukra = Project::factory()->open()->create(['title' => 'Réservoir de La Soukra', 'target_budget' => 2000]);
        Funding::factory()->for($soukra)->municipal()->create(['amount' => 500]);
        $ai = app(ProjectAiService::class);

        $this->assertStringContainsString('Capteurs connectés de Raoued (85 %)', $ai->answer('Quels projets sont presque financés ?'));
        $this->assertStringContainsString('Réservoir de La Soukra : 500 DT collectés sur 2 000 DT (25 %).', $ai->answer('Combien a été collecté pour le réservoir de La Soukra ?'));
    }

    public function test_llm_assistant_can_only_call_whitelisted_read_only_tools(): void
    {
        Project::factory()->almostFunded()->create(['title' => 'Capteurs connectés de Raoued']);

        $fake = $this->fakeLlm(tools: function (array $definitions, callable $run): string {
            $result = json_decode($run('listAlmostFunded', ['min_percent' => 80]), true);
            $unknown = json_decode($run('deleteAllProjects', []), true);

            return 'Presque financé : '.$result[0]['titre'].' ('.$unknown['error'].')';
        });

        $answer = app(ProjectAiService::class)->answer('Quels projets sont presque financés ?');

        $this->assertSame('Presque financé : Capteurs connectés de Raoued (Outil inconnu.)', $answer);
        $this->assertSame(['getProjectStats', 'listAlmostFunded', 'listOpenProjects'], array_column($fake->toolDefinitions, 'name'));
        $this->assertDatabaseCount('projects', 1);
    }

    public function test_assistant_component_is_rate_limited(): void
    {
        config(['ai.rate_limit' => 2]);

        $component = Livewire::test(ProjectAssistant::class)
            ->set('question', 'Quels projets sont ouverts aux dons ?')->call('ask')->assertHasNoErrors()
            ->set('question', 'Quelle est la météo ?')->call('ask')->assertHasNoErrors()
            ->assertSee(ProjectAiService::REFUSAL);

        $component->set('question', 'Encore une question sur les projets')->call('ask')->assertHasErrors('question');
        $this->assertCount(4, $component->get('messages'));
    }

    public function test_null_client_is_used_without_api_key(): void
    {
        $this->assertInstanceOf(NullLlmClient::class, app(LlmClient::class));
    }
}

/** Faux client LLM : réponse JSON fixe, boucle d'outils simulée ; enregistre les prompts envoyés. */
class FakeLlm implements LlmClient
{
    /** @var list<string> */
    public array $prompts = [];

    /** @var list<array<string, mixed>> */
    public array $toolDefinitions = [];

    public function __construct(private ?array $json = null, private $tools = null) {}

    public function generateJson(string $system, string $prompt, array $schema, int $maxTokens = 1024): ?array
    {
        $this->prompts[] = $prompt;

        return $this->json;
    }

    public function answerWithTools(string $system, string $question, array $tools, callable $runTool, int $maxTurns = 4): ?string
    {
        $this->prompts[] = $question;
        $this->toolDefinitions = $tools;

        return $this->tools ? ($this->tools)($tools, $runTool) : null;
    }
}
