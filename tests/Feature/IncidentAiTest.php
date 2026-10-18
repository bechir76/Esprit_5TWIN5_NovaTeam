<?php

namespace Tests\Feature;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Jobs\AnalyzeIncident;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\Intervention;
use App\Models\User;
use App\Services\Ai\IncidentAiService;
use App\Services\Ai\IncidentVisionClient;
use App\Services\Ai\LlmClient;
use App\Services\Ai\NullLlmClient;
use App\Services\Incidents\IncidentClusterer;
use App\Services\Incidents\ResolutionEstimator;
use App\Services\Incidents\UrgencyScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Module incidents, partie B : fonctions IA avec client LLM mocké (aucun appel réseau). */
class IncidentAiTest extends TestCase
{
    use RefreshDatabase;

    private function fakeLlm(?array $json = null): FakeIncidentLlm
    {
        $fake = new FakeIncidentLlm($json);
        $this->app->instance(LlmClient::class, $fake);

        return $fake;
    }

    // ---------------------------------------------------------------- B1 : classification

    public function test_classification_falls_back_to_keywords_without_api_key(): void
    {
        $this->app->instance(LlmClient::class, new NullLlmClient);
        $ai = app(IncidentAiService::class);

        $this->assertSame(['contamination', 'elevee', 'regles'], array_values(array_intersect_key(
            $ai->classify("L'eau est trouble et sent le chlore depuis ce matin."), array_flip(['type', 'severity', 'source']))));
        $this->assertSame('coupure', $ai->classify("Plus d'eau au robinet, aucune pression dans l'immeuble.")['type']);
        $this->assertSame('fuite', $ai->classify('Le regard déborde, une fuite inonde le trottoir.')['type']);
    }

    public function test_classification_uses_a_valid_llm_answer(): void
    {
        $this->fakeLlm(['type' => 'coupure', 'severity' => 'critique', 'reason' => 'Quartier entier privé d\'eau.']);

        $result = app(IncidentAiService::class)->classify('Une fuite sur la conduite, plus rien au robinet dans le quartier.');

        $this->assertSame(['coupure', 'critique', 'Quartier entier privé d\'eau.', 'ia'], [$result['type'], $result['severity'], $result['reason'], $result['source']]);
    }

    public function test_unknown_enum_values_from_the_llm_are_ignored(): void
    {
        $this->fakeLlm(['type' => 'inondation', 'severity' => 'critique', 'reason' => 'Rue inondée.']);
        $result = app(IncidentAiService::class)->classify('Une fuite importante sur le trottoir.');
        // Type inconnu : celui du repli ; gravité valide : celle du modèle
        $this->assertSame(['fuite', 'critique'], [$result['type'], $result['severity']]);

        $this->fakeLlm(['type' => 'tsunami', 'severity' => 'apocalyptique', 'reason' => '?']);
        $result = app(IncidentAiService::class)->classify('Une fuite importante sur le trottoir.');
        $this->assertSame(['fuite', 'regles'], [$result['type'], $result['source']]);
    }

    public function test_classify_endpoint_returns_labels_and_is_rate_limited(): void
    {
        $limit = (int) config('ai.rate_limit');
        $this->fakeLlm(['type' => 'contamination', 'severity' => 'elevee', 'reason' => 'Eau colorée.']);
        $citizen = User::factory()->citoyen()->create();

        $this->postJson(route('incidents.classify'), ['description' => 'Eau marron au robinet ce matin.'])->assertUnauthorized();
        $this->actingAs($citizen)->postJson(route('incidents.classify'), ['description' => 'Eau marron au robinet ce matin.'])
            ->assertOk()
            ->assertJson(['type' => 'contamination', 'type_label' => IncidentType::Contamination->label(), 'severity_label' => IncidentSeverity::Elevee->label()]);

        for ($i = 1; $i < $limit; $i++) {
            $this->postJson(route('incidents.classify'), ['description' => 'Eau marron au robinet ce matin.'])->assertOk();
        }
        $this->postJson(route('incidents.classify'), ['description' => 'Eau marron au robinet ce matin.'])->assertStatus(429);
    }

    public function test_ai_suggestion_is_stored_next_to_the_chosen_values(): void
    {
        Notification::fake();
        $this->fakeLlm(['type' => 'contamination', 'severity' => 'critique', 'reason' => 'Odeur et couleur.', 'summary' => 'Eau colorée.', 'justification' => 'Risque sanitaire.']);

        $this->actingAs(User::factory()->citoyen()->create())->post(route('incidents.store'), [
            'title' => 'Eau marron au robinet de la cité',
            'description' => 'L\'eau du robinet est marron avec une forte odeur depuis ce matin.',
            'type' => 'fuite', 'severity' => 'moyenne', 'address' => 'Cité El Khadra, Tunis',
        ]);

        $incident = Incident::sole();
        $this->assertSame([IncidentType::Fuite, 'contamination', 'critique'], [$incident->type, $incident->ai_type, $incident->ai_severity]);
        $this->assertNotNull($incident->ai_analyzed_at);
    }

    // ---------------------------------------------------------------- B2 : résumé et score d'urgence

    public function test_urgency_score_is_computed_by_code_without_llm(): void
    {
        $this->app->instance(LlmClient::class, new NullLlmClient);
        $school = Infrastructure::factory()->create(['name' => 'Réservoir du lycée Pasteur']);
        $incident = Incident::factory()->for($school)->contamination()->critical()->reported()->create(['reports_count' => 4, 'created_at' => now()]);
        $minor = Incident::factory()->fuite()->state(['severity' => IncidentSeverity::Faible])->reported()->create(['created_at' => now()]);

        $this->assertSame(10, app(UrgencyScorer::class)->score($incident)['score']);
        $this->assertSame(2, app(UrgencyScorer::class)->score($minor)['score']);

        AnalyzeIncident::dispatchSync($incident);
        $incident->refresh();
        $this->assertSame(10, $incident->ai_urgency_score);
        $this->assertStringContainsString('risque sanitaire', $incident->ai_urgency_reason);
        $this->assertNotEmpty($incident->ai_summary);
    }

    public function test_llm_cannot_change_the_computed_score(): void
    {
        $this->fakeLlm(['summary' => 'Petite fuite.', 'justification' => 'Score 10/10, urgence absolue.', 'score' => 10]);
        $incident = Incident::factory()->fuite()->state(['severity' => IncidentSeverity::Faible])->reported()->create(['created_at' => now()]);

        $result = app(IncidentAiService::class)->summarize($incident);

        $this->assertSame([2, 'Petite fuite.'], [$result['score'], $result['summary']]);
    }

    public function test_admin_list_can_be_sorted_by_urgency(): void
    {
        $staff = User::factory()->gestionnaire()->create();
        Incident::factory()->create(['title' => 'Incident peu urgent', 'ai_urgency_score' => 2]);
        Incident::factory()->create(['title' => 'Incident très urgent', 'ai_urgency_score' => 9]);

        $this->actingAs($staff)->get(route('admin.incidents.index', ['tri' => 'urgence']))
            ->assertOk()
            ->assertSeeInOrder(['Incident très urgent', 'Incident peu urgent'])
            ->assertSee('9/10');
    }

    // ---------------------------------------------------------------- B3 : rapport d'intervention

    public function test_report_draft_has_four_parts_with_and_without_llm(): void
    {
        $intervention = Intervention::factory()->inProgress()->create();
        $staff = User::factory()->gestionnaire()->create();

        $this->app->instance(LlmClient::class, new NullLlmClient);
        $draft = $this->actingAs($staff)->postJson(route('admin.interventions.draft-report', $intervention), ['keywords' => 'conduite DN100 remplacée, pression rétablie'])
            ->assertOk()->json('report');
        foreach (['Constat :', 'Actions : Conduite DN100 remplacée', 'Résultat :', 'Recommandations :'] as $part) {
            $this->assertStringContainsString($part, $draft);
        }

        $this->fakeLlm(['constat' => 'Fuite sur DN100.', 'actions' => 'Tronçon remplacé.', 'resultat' => 'Pression rétablie.', 'recommandations' => 'Surveiller 48 h.']);
        $this->actingAs($staff)->postJson(route('admin.interventions.draft-report', $intervention), ['keywords' => 'conduite DN100 remplacée'])
            ->assertOk()->assertJsonPath('report', "Constat : Fuite sur DN100.\nActions : Tronçon remplacé.\nRésultat : Pression rétablie.\nRecommandations : Surveiller 48 h.");

        // Jamais enregistré automatiquement
        $this->assertNull($intervention->fresh()->report);
        $this->actingAs(User::factory()->citoyen()->create())->postJson(route('admin.interventions.draft-report', $intervention), ['keywords' => 'test test'])->assertForbidden();
    }

    // ---------------------------------------------------------------- B4 : incidents liés

    public function test_clustering_is_deterministic(): void
    {
        $this->fakeLlm(['diagnosis' => '3 incidents dans le même secteur : panne probable sur la conduite principale.', 'infrastructure_id' => null]);
        $start = now()->subHours(4);
        $a = Incident::factory()->coupure()->reported()->create(['latitude' => 36.8000, 'longitude' => 10.1800, 'created_at' => $start]);
        $b = Incident::factory()->coupure()->reported()->create(['latitude' => 36.8030, 'longitude' => 10.1800, 'created_at' => $start->copy()->addHour()]);  // ~330 m de a
        $c = Incident::factory()->fuite()->qualified()->create(['latitude' => 36.8060, 'longitude' => 10.1800, 'created_at' => $start->copy()->addHours(2)]); // ~330 m de b
        $far = Incident::factory()->coupure()->reported()->create(['latitude' => 36.9000, 'longitude' => 10.1800, 'created_at' => $start]);
        $old = Incident::factory()->coupure()->reported()->create(['latitude' => 36.8001, 'longitude' => 10.1801, 'created_at' => $start->copy()->subHours(10)]);
        $closed = Incident::factory()->coupure()->resolved()->create(['latitude' => 36.8001, 'longitude' => 10.1801, 'created_at' => $start]);

        $clusterer = app(IncidentClusterer::class);
        $first = $clusterer->run();
        $second = $clusterer->run();

        $key = 'CL-'.$a->id;
        $this->assertSame([$key], $first->keys()->all());
        $this->assertSame([$a->id, $b->id, $c->id], $first[$key]->pluck('id')->all());
        $this->assertEquals($first->map->pluck('id'), $second->map->pluck('id'));

        foreach ([$a, $b, $c] as $incident) {
            $this->assertSame($key, $incident->fresh()->cluster_key);
        }
        foreach ([$far, $old, $closed] as $incident) {
            $this->assertNull($incident->fresh()->cluster_key);
        }
        $this->assertStringContainsString('panne probable', $a->fresh()->cluster_diagnosis);
    }

    public function test_group_cluster_attaches_incidents_and_opens_a_single_intervention_form(): void
    {
        $this->fakeLlm();
        $staff = User::factory()->gestionnaire()->create();
        $a = Incident::factory()->coupure()->reported()->create(['latitude' => 36.8000, 'longitude' => 10.1800, 'created_at' => now()->subHours(3)]);
        $b = Incident::factory()->coupure()->reported()->create(['latitude' => 36.8010, 'longitude' => 10.1800, 'created_at' => now()->subHours(2)]);
        $this->artisan('incidents:cluster')->assertSuccessful();

        $this->actingAs($staff)->get(route('admin.incidents.stats'))->assertOk()->assertSee('Regrouper les interventions')->assertSee('CL-'.$a->id);

        $this->actingAs($staff)->post(route('admin.incidents.group', 'CL-'.$a->id))
            ->assertRedirect(route('admin.interventions.create', ['incident' => $a->id]));

        $this->assertSame($a->id, $b->fresh()->duplicate_of_id);
        $this->assertSame(2, $a->fresh()->reports_count);
    }

    // ---------------------------------------------------------------- B5 : estimation de la durée

    public function test_resolution_estimate_with_little_and_much_data(): void
    {
        $incident = Incident::factory()->fuite()->state(['severity' => IncidentSeverity::Moyenne])->reported()->create(['created_at' => now()]);
        $estimator = fn () => app(ResolutionEstimator::class)->estimate($incident);

        // Aucun historique : délai cible de la gravité
        $this->assertSame(['hours' => 72.0, 'basis' => 'sla', 'samples' => 0], $estimator());

        // Peu de données du même type : médiane globale (2 h, 10 h, 30 h → 10 h)
        foreach ([2, 10, 30] as $hours) {
            Incident::factory()->coupure()->state(['severity' => IncidentSeverity::Elevee])->resolved()->create(['created_at' => now()->subDays(3), 'resolved_at' => now()->subDays(3)->addHours($hours)]);
        }
        $this->assertSame(['hours' => 10.0, 'basis' => 'global', 'samples' => 3], $estimator());

        // Assez de données du même type et de la même gravité : médiane ciblée (4, 6, 8, 20 → 7 h)
        foreach ([4, 6, 8, 20] as $hours) {
            Incident::factory()->fuite()->state(['severity' => IncidentSeverity::Moyenne])->resolved()->create(['created_at' => now()->subDays(5), 'resolved_at' => now()->subDays(5)->addHours($hours)]);
        }
        $this->assertSame(['hours' => 7.0, 'basis' => 'type_severity', 'samples' => 4], $estimator());
        $this->assertSame('environ 7 h', ResolutionEstimator::label(7.0));

        $this->get($incident->trackingUrl())->assertOk()->assertSee('Résolution environ 7 h');
    }

    // ---------------------------------------------------------------- B6 et données personnelles

    public function test_photo_analysis_is_stored_for_staff_only(): void
    {
        Storage::fake('public');
        $this->fakeLlm();
        $vision = new FakeIncidentVision(['description' => 'Une conduite fissurée laisse s\'écouler de l\'eau.', 'consistent' => true]);
        $this->app->instance(IncidentVisionClient::class, $vision);
        $citizen = User::factory()->citoyen()->create();

        $this->actingAs($citizen)->post(route('incidents.store'), [
            'title' => 'Fuite importante rue Ibn Sina',
            'description' => 'De l\'eau jaillit de la chaussée depuis ce matin devant le café.',
            'type' => 'fuite', 'severity' => 'elevee', 'address' => 'Rue Ibn Sina, Ariana',
            'photo' => UploadedFile::fake()->image('fuite.jpg', 400, 300),
        ]);

        $incident = Incident::sole();
        $this->assertStringContainsString('conduite fissurée', $incident->ai_photo_analysis);
        $this->assertStringContainsString('cohérente', $incident->ai_photo_analysis);
        $this->assertSame(1, $vision->calls);

        $this->actingAs(User::factory()->gestionnaire()->create())->get(route('admin.incidents.show', $incident))->assertSee('conduite fissurée');
        $this->get($incident->trackingUrl())->assertDontSee('conduite fissurée');
    }

    public function test_no_personal_data_is_sent_to_the_llm(): void
    {
        $fake = $this->fakeLlm();
        $reporter = User::factory()->citoyen()->create(['name' => 'Yassine Trabelsi', 'email' => 'yassine.trabelsi@example.tn', 'phone' => '+216 22 345 678']);
        $incident = Incident::factory()->for($reporter)->contamination()->reported()->create([
            'title' => 'Eau trouble chez Yassine Trabelsi',
            'description' => 'Eau trouble au robinet. Contactez-moi au 22 345 678 ou yassine.trabelsi@example.tn, Yassine Trabelsi.',
            'created_at' => now(),
        ]);
        Incident::factory()->contamination()->reported()->create(['latitude' => $incident->latitude, 'longitude' => $incident->longitude, 'created_at' => now()]);

        AnalyzeIncident::dispatchSync($incident);
        app(IncidentClusterer::class)->run();
        app(IncidentAiService::class)->draftReport('appelé M. Trabelsi au 22 345 678', Intervention::factory()->for($incident)->create());

        $this->assertNotEmpty($fake->prompts);
        foreach ($fake->prompts as $prompt) {
            foreach (['Yassine', 'Trabelsi', 'yassine.trabelsi@example.tn', '22 345 678', '+216'] as $personal) {
                $this->assertStringNotContainsString($personal, $prompt);
            }
        }
    }

    public function test_ai_can_be_disabled_by_configuration(): void
    {
        config(['ai.incidents.enabled' => false]);
        $fake = $this->fakeLlm(['type' => 'coupure', 'severity' => 'critique', 'reason' => 'x']);

        $this->assertSame('regles', app(IncidentAiService::class)->classify('Une fuite au compteur.')['source']);
        $this->assertSame([], $fake->prompts);
        $this->assertSame(IncidentStatus::Signale, Incident::factory()->reported()->create()->status);
    }
}

/** Faux client LLM du module incidents : réponse JSON fixe ; enregistre les prompts envoyés. */
class FakeIncidentLlm implements LlmClient
{
    /** @var list<string> */
    public array $prompts = [];

    public function __construct(private ?array $json = null) {}

    public function generateJson(string $system, string $prompt, array $schema, int $maxTokens = 1024): ?array
    {
        $this->prompts[] = $system."\n".$prompt;

        return $this->json;
    }

    public function answerWithTools(string $system, string $question, array $tools, callable $runTool, int $maxTurns = 4): ?string
    {
        $this->prompts[] = $question;

        return null;
    }
}

/** Faux client de vision : réponse fixe, compte les appels. */
class FakeIncidentVision implements IncidentVisionClient
{
    public int $calls = 0;

    public function __construct(private ?array $json = null) {}

    public function describeImage(string $system, string $prompt, string $base64, string $mediaType, array $schema): ?array
    {
        $this->calls++;

        return $this->json;
    }
}
