<?php

namespace Tests\Feature;

use App\Enums\AnalysisStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\RiskLevel;
use App\Livewire\InfrastructureRisk;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\Project;
use App\Models\User;
use App\Models\WaterAnalysis;
use App\Notifications\InfrastructureRiskCritical;
use App\Services\Ai\InfrastructureAiService;
use App\Services\Ai\LlmClient;
use App\Services\Ai\NullLlmClient;
use App\Services\Infrastructures\RiskAssessment;
use App\Services\Infrastructures\RiskScorer;
use App\Services\ProjectScoring;
use Database\Seeders\InfrastructureRiskSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module infrastructures, B1 : score de risque prédictif. Le score est calculé par le code ; le LLM est simulé
 * (FakeInfrastructureLlm ou Http::fake pour Gemini) ou absent (repli par règles). Aucun appel réseau.
 */
class InfrastructureAiTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_ANSWER = [
        'summary' => 'Le réservoir est en état critique, vieillissant et en fonte grise ; son inspection a trois mois de retard et plusieurs incidents récents le concernent.',
        'recommendations' => [
            'Réaliser l\'inspection en retard dans les quinze jours.',
            'Lancer un diagnostic structurel du réservoir.',
            'Analyser les incidents liés pour trouver la panne commune.',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Par défaut : aucune clé API, donc repli sans appel réseau
        config(['ai.provider' => 'anthropic', 'ai.api_key' => null]);
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 10, 21)->setTime(10, 0));
    }

    private function fakeLlm(?array $json): FakeInfrastructureLlm
    {
        $fake = new FakeInfrastructureLlm($json);
        $this->app->instance(LlmClient::class, $fake);

        return $fake;
    }

    /**
     * Réservoir critique de 30 ans en fonte grise, inspection en retard de 90 jours, 2 incidents récents (dont 1 critique
     * ouvert) d'un même groupe lié, 1 analyse non conforme sur 2. $criticalIncidents ajoute des incidents critiques ouverts.
     */
    private function riskyInfrastructure(int $criticalIncidents = 0): Infrastructure
    {
        $infrastructure = Infrastructure::factory()->reservoir()->critical()->create([
            'code' => 'RES-ARI-004',
            'name' => 'Réservoir de La Soukra',
            'material' => 'Fonte grise',
            'commissioned_year' => 1996,
            'last_inspection_at' => '2026-04-23', // tous les 3 mois (critique) : échéance le 23/07, 90 jours de retard
        ]);
        $reporter = User::factory()->citoyen()->create(['name' => 'Sana Ben Ali', 'email' => 'sana@example.tn']);

        Incident::factory()->for($infrastructure)->for($reporter)->critical()->create([
            'title' => 'Fuite signalée par Sana Ben Ali', 'status' => IncidentStatus::Signale, 'cluster_key' => 'c1', 'created_at' => now()->subDays(10),
        ]);
        Incident::factory()->for($infrastructure)->for($reporter)->resolved()->create([
            'severity' => IncidentSeverity::Faible, 'cluster_key' => 'c1', 'created_at' => now()->subMonths(2),
        ]);
        // Hors fenêtre de 12 mois et résolu : ignoré
        Incident::factory()->for($infrastructure)->for($reporter)->resolved()->critical()->create(['created_at' => now()->subYears(2)]);
        Incident::factory()->count($criticalIncidents)->for($infrastructure)->for($reporter)->critical()->create([
            'status' => IncidentStatus::Signale, 'created_at' => now()->subDays(5),
        ]);

        WaterAnalysis::factory()->for($infrastructure)->create(['status' => AnalysisStatus::Validee, 'sampled_at' => now()->subMonths(3)]);
        WaterAnalysis::factory()->for($infrastructure)->create(['status' => AnalysisStatus::NonConforme, 'sampled_at' => now()->subMonth()]);

        return $infrastructure->refresh();
    }

    // ---------------------------------------------------------------- Calcul du score (code)

    public function test_risk_score_is_computed_by_code_from_weighted_factors(): void
    {
        $infrastructure = $this->riskyInfrastructure();
        $scorer = app(RiskScorer::class);
        $factors = $scorer->factors($infrastructure);

        $this->assertSame(30, $factors['age']['value']);
        $this->assertSame(0.5, $factors['age']['normalized']);
        $this->assertSame(1.0, $factors['status']['normalized']);
        $this->assertSame(1.0, $factors['material']['normalized']);
        $this->assertSame(90, $factors['inspection']['value']);
        $this->assertSame(0.5, $factors['inspection']['normalized']);
        // 2 récents × 1 + 1 ouvert × 2 + 1 critique × 3 = 7 points sur 12
        $this->assertSame(2, $factors['incidents']['value']);
        $this->assertSame(0.583, $factors['incidents']['normalized']);
        $this->assertSame(1, $factors['clusters']['value']);
        $this->assertSame(0.5, $factors['water_quality']['normalized']);

        // 10 + 20 + 10 + 5 + 11,7 + 5 + 5 = 66,7
        $this->assertSame(67, $scorer->score($infrastructure, $factors));
        $this->assertSame(RiskLevel::Eleve, $scorer->level(67));
        $this->assertSame([RiskLevel::Faible, RiskLevel::Moyen, RiskLevel::Eleve, RiskLevel::Critique],
            [RiskLevel::fromScore(10), RiskLevel::fromScore(30), RiskLevel::fromScore(45), RiskLevel::fromScore(70)]);
    }

    // ---------------------------------------------------------------- Texte : IA, réponse invalide, sans clé

    public function test_llm_writes_only_the_explanation_and_receives_no_personal_data(): void
    {
        $fake = $this->fakeLlm(self::VALID_ANSWER);
        $infrastructure = $this->riskyInfrastructure();

        app(RiskAssessment::class)->assess($infrastructure);

        $infrastructure->refresh();
        $this->assertSame(67, $infrastructure->risk_score); // le score reste celui du code
        $this->assertSame(RiskLevel::Eleve, $infrastructure->risk_level);
        $this->assertSame('ia', $infrastructure->risk_source);
        $this->assertSame(self::VALID_ANSWER['summary'], $infrastructure->risk_summary);
        $this->assertSame(self::VALID_ANSWER['recommendations'], $infrastructure->risk_recommendations);
        $this->assertNotNull($infrastructure->risk_computed_at);

        // Prompt : score et facteurs techniques seulement
        $this->assertCount(1, $fake->prompts);
        $prompt = $fake->prompts[0];
        $this->assertStringContainsString('"score_risque":67', $prompt);
        $this->assertStringContainsString('fonte grise', $prompt);
        foreach (['Sana', 'Ben Ali', 'sana@example.tn', 'Fuite signalée', 'RES-ARI-004', 'La Soukra'] as $private) {
            $this->assertStringNotContainsString($private, $prompt);
        }
    }

    public function test_invalid_llm_answer_falls_back_to_rules(): void
    {
        $infrastructure = $this->riskyInfrastructure();

        foreach ([
            ['summary' => 'Trop court.', 'recommendations' => self::VALID_ANSWER['recommendations']],
            ['summary' => self::VALID_ANSWER['summary'], 'recommendations' => ['Une seule recommandation valable.']],
            ['summary' => self::VALID_ANSWER['summary'], 'recommendations' => 'pas une liste'],
            ['autre' => 'format inattendu'],
        ] as $answer) {
            $this->fakeLlm($answer);
            app(RiskAssessment::class)->assess($infrastructure);

            $this->assertSame('regles', $infrastructure->risk_source);
            $this->assertStringStartsWith('Risque élevé (67/100) pour cet ouvrage (réservoir). Principaux facteurs : état « critique »', $infrastructure->risk_summary);
            $this->assertCount(3, $infrastructure->risk_recommendations);
        }

        // Le repli s'appuie sur les facteurs qui pèsent le plus
        $this->assertSame('Planifier une intervention corrective : l\'ouvrage est dans un état dégradé.', $infrastructure->risk_recommendations[0]);
    }

    public function test_without_api_key_the_rules_are_used_without_network(): void
    {
        Http::preventStrayRequests();
        $this->assertInstanceOf(NullLlmClient::class, app(LlmClient::class));

        $infrastructure = app(RiskAssessment::class)->assess($this->riskyInfrastructure());

        $this->assertSame('regles', $infrastructure->risk_source);
        $this->assertSame(67, $infrastructure->risk_score);
        Http::assertNothingSent();
    }

    public function test_gemini_answer_is_validated_through_the_shared_client(): void
    {
        config(['ai.provider' => 'gemini', 'ai.gemini.api_key' => 'test-key', 'ai.gemini.model' => 'gemini-main']);
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['role' => 'model', 'parts' => [
            ['text' => json_encode(self::VALID_ANSWER, JSON_UNESCAPED_UNICODE)],
        ]], 'finishReason' => 'STOP']]])]);

        $infrastructure = app(RiskAssessment::class)->assess($this->riskyInfrastructure());

        $this->assertSame('ia', $infrastructure->risk_source);
        $this->assertSame(self::VALID_ANSWER['recommendations'], $infrastructure->risk_recommendations);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/models/gemini-main:generateContent')
            && isset($request['generationConfig']['responseJsonSchema']['properties']['recommendations'])
            && ! str_contains(json_encode($request->data(), JSON_UNESCAPED_UNICODE), 'Sana'));
    }

    // ---------------------------------------------------------------- Commande, historique et notification

    public function test_command_scores_one_or_all_infrastructures_and_notifies_once_when_risk_becomes_critical(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->gestionnaire()->create();
        $technician = User::factory()->technicien()->create();
        $risky = $this->riskyInfrastructure(criticalIncidents: 3);
        $other = Infrastructure::factory()->good()->create(['commissioned_year' => 2020, 'material' => 'PEHD', 'last_inspection_at' => now()->subMonth()]);

        $this->artisan('infrastructures:score', ['infrastructure' => 'res-ari-004'])->assertSuccessful();

        $this->assertSame(75, $risky->refresh()->risk_score);
        $this->assertSame(RiskLevel::Critique, $risky->risk_level);
        $this->assertNull($other->refresh()->risk_score);
        Notification::assertSentTo([$admin, $manager], InfrastructureRiskCritical::class);
        Notification::assertNotSentTo($technician, InfrastructureRiskCritical::class);

        // Tous les ouvrages ; le niveau était déjà critique : pas de seconde alerte
        $this->artisan('infrastructures:score')->assertSuccessful();
        $this->assertNotNull($other->refresh()->risk_score);
        Notification::assertSentToTimes($manager, InfrastructureRiskCritical::class, 1);

        // Historique du recalcul
        $activity = $risky->activities()->where('event', 'risk_computed')->latest('id')->first();
        $this->assertSame(75, $activity->properties['attributes']['risk_score']);
        $this->assertSame(75, $activity->properties['old']['risk_score']);

        $this->artisan('infrastructures:score', ['infrastructure' => 'XXX-XXX-000'])->assertFailed();
    }

    public function test_recalculate_button_runs_the_assessment_with_rate_limit(): void
    {
        $staff = User::factory()->technicien()->create();
        $infrastructure = $this->riskyInfrastructure();
        config(['ai.rate_limit' => 1]);

        $this->actingAs($staff)->get(route('admin.infrastructures.show', $infrastructure))
            ->assertOk()
            ->assertSeeLivewire(InfrastructureRisk::class)
            ->assertSee('Ouvrage pas encore évalué.')
            ->assertSee('action="'.route('admin.infrastructures.risk', $infrastructure).'"', false);

        $component = Livewire::actingAs($staff)->test(InfrastructureRisk::class, ['infrastructure' => $infrastructure])
            ->call('recompute')
            ->assertHasNoErrors()
            ->assertSee('Score de risque recalculé : 67/100 (élevé).')
            ->assertSee('Calcul en cours…');

        $this->assertSame(67, $infrastructure->refresh()->risk_score);
        $this->assertSame($staff->id, $infrastructure->activities()->where('event', 'risk_computed')->first()->causer_id);

        $component->call('recompute')->assertHasErrors('risk');
    }

    public function test_citizen_cannot_recalculate_and_route_works_without_javascript(): void
    {
        $infrastructure = $this->riskyInfrastructure();

        $this->actingAs(User::factory()->citoyen()->create())
            ->post(route('admin.infrastructures.risk', $infrastructure))
            ->assertForbidden();

        $this->actingAs(User::factory()->gestionnaire()->create())
            ->post(route('admin.infrastructures.risk', $infrastructure))
            ->assertRedirect(route('admin.infrastructures.show', $infrastructure))
            ->assertSessionHas('status', 'Score de risque recalculé : 67/100 (élevé).');

        Livewire::actingAs(User::factory()->citoyen()->create())
            ->test(InfrastructureRisk::class, ['infrastructure' => $infrastructure])
            ->call('recompute')
            ->assertForbidden();
    }

    // ---------------------------------------------------------------- Seeder, autres modules et pages

    public function test_seeder_scores_every_infrastructure_without_api_call(): void
    {
        config(['ai.provider' => 'gemini', 'ai.gemini.api_key' => 'test-key']);
        Http::preventStrayRequests();
        Infrastructure::factory()->count(3)->create();

        $this->seed(InfrastructureRiskSeeder::class);

        $this->assertSame(3, Infrastructure::whereNotNull('risk_score')->where('risk_source', 'regles')->count());
        Http::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_project_priority_uses_the_risk_score_when_available(): void
    {
        $infrastructure = $this->riskyInfrastructure();
        $project = Project::factory()->for($infrastructure)->create();
        $scoring = app(ProjectScoring::class);

        // Pas encore évalué : état de l'ouvrage (critique = 1)
        $this->assertSame(1.0, $scoring->factors($project)['infrastructure']['normalized']);

        app(RiskAssessment::class)->assess($infrastructure);
        $factor = $scoring->factors($project->refresh())['infrastructure'];

        $this->assertSame(0.67, $factor['normalized']);
        $this->assertSame('ouvrage à risque Élevé (67/100)', $factor['label']);
    }

    public function test_ai_page_and_dashboard_list_the_five_riskiest_infrastructures(): void
    {
        $staff = User::factory()->technicien()->create();
        $assessment = new RiskAssessment(new RiskScorer, new InfrastructureAiService(new NullLlmClient));
        $assessment->assess($this->riskyInfrastructure());
        Infrastructure::factory()->count(6)->good()->create(['commissioned_year' => 2024, 'material' => 'PEHD', 'last_inspection_at' => now()])
            ->each(fn (Infrastructure $i) => $assessment->assess($i));

        foreach (['admin.ai.insights', 'admin.dashboard'] as $route) {
            $response = $this->actingAs($staff)->get(route($route))->assertOk();
            $response->assertSeeInOrder(['Réservoir de La Soukra', 'État « critique »', '67']);
            $this->assertSame(5, substr_count($response->getContent(), 'font-mono text-[.76rem] text-muted">'));
        }
    }
}
