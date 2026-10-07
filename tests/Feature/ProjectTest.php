<?php

namespace Tests\Feature;

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Funding;
use App\Models\Infrastructure;
use App\Models\Project;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- Helpers

    private function staff(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    private function citoyen(): User
    {
        return User::factory()->citoyen()->create();
    }

    /** Données minimales valides d'un projet. */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Réhabilitation du réservoir de La Soukra',
            'category' => 'decontamination',
            'description' => 'Vidange, désinfection et réparation du radier fissuré du réservoir.',
            'target_budget' => '220 000',
            'start_date' => '2026-10-01',
            'end_date' => '2026-12-31',
            'status' => 'en_collecte',
            'is_public' => '1',
            'infrastructure_id' => null,
        ], $overrides);
    }

    // ---------------------------------------------------------------- Accès

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $project = Project::factory()->create();

        $this->get(route('admin.projects.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.projects.show', $project))->assertRedirect(route('admin.login'));
        $this->post(route('admin.projects.store'), $this->validData())->assertRedirect(route('admin.login'));
        $this->assertDatabaseCount('projects', 1);
    }

    public function test_citizen_cannot_access_project_admin(): void
    {
        $citizen = $this->citoyen();
        $project = Project::factory()->create();

        $this->actingAs($citizen)->get(route('admin.projects.index'))->assertForbidden();
        $this->actingAs($citizen)->get(route('admin.projects.create'))->assertForbidden();
        $this->actingAs($citizen)->post(route('admin.projects.store'), $this->validData())->assertForbidden();
        $this->actingAs($citizen)->delete(route('admin.projects.destroy', $project))->assertForbidden();
        $this->assertModelExists($project);
    }

    // ---------------------------------------------------------------- CRUD

    public function test_staff_can_list_projects_with_real_progress(): void
    {
        $project = Project::factory()->create(['title' => 'Station de pompage solaire de Raoued', 'target_budget' => 1000]);
        Funding::factory()->for($project)->municipal()->create(['amount' => 250]);

        $this->actingAs($this->staff())->get(route('admin.projects.index'))
            ->assertOk()
            ->assertSee('Station de pompage solaire de Raoued')
            ->assertSee('250 · 25 %')
            ->assertSee($project->infrastructure->code);
    }

    public function test_project_index_can_be_searched_and_filtered(): void
    {
        $zone = Zone::factory()->create();
        $infrastructure = Infrastructure::factory()->for($zone)->create();
        Project::factory()->for($infrastructure)->create(['title' => 'Capteurs de qualité dans les écoles', 'category' => ProjectCategory::Surveillance]);
        Project::factory()->draft()->create(['title' => 'Sectorisation du réseau de Ben Arous', 'category' => ProjectCategory::Renovation]);
        $staff = $this->staff();

        $this->actingAs($staff)->get(route('admin.projects.index', ['q' => 'Capteurs']))
            ->assertSee('Capteurs de qualité dans les écoles')->assertDontSee('Sectorisation du réseau');
        $this->actingAs($staff)->get(route('admin.projects.index', ['status' => 'brouillon']))
            ->assertSee('Sectorisation du réseau')->assertDontSee('Capteurs de qualité dans les écoles');
        $this->actingAs($staff)->get(route('admin.projects.index', ['category' => 'surveillance']))
            ->assertSee('Capteurs de qualité dans les écoles')->assertDontSee('Sectorisation du réseau');
        $this->actingAs($staff)->get(route('admin.projects.index', ['zone' => $zone->id]))
            ->assertSee('Capteurs de qualité dans les écoles')->assertDontSee('Sectorisation du réseau');
    }

    public function test_staff_can_create_a_project_with_an_image(): void
    {
        Storage::fake('public');
        $infrastructure = Infrastructure::factory()->create();

        $this->actingAs($this->staff())->get(route('admin.projects.create', ['infrastructure' => $infrastructure->id]))
            ->assertOk()
            ->assertSee($infrastructure->code.' · '.$infrastructure->name);

        $response = $this->actingAs($this->staff())->post(route('admin.projects.store'), $this->validData([
            'infrastructure_id' => $infrastructure->id,
            'image' => UploadedFile::fake()->image('reservoir.jpg', 1200, 675),
            // Le slug n'est pas fourni par le formulaire : il est généré depuis le titre
            'slug' => 'slug-force',
        ]));

        $project = Project::sole();
        $response->assertRedirect(route('admin.projects.show', $project))
            ->assertSessionHas('status', 'Projet « Réhabilitation du réservoir de La Soukra » créé.');

        $this->assertSame('rehabilitation-du-reservoir-de-la-soukra', $project->slug);
        $this->assertSame(ProjectStatus::EnCollecte, $project->status);
        $this->assertSame(ProjectCategory::Decontamination, $project->category);
        $this->assertSame('220000.00', $project->target_budget);
        $this->assertTrue($project->is_public);
        $this->assertTrue($project->infrastructure->is($infrastructure));
        $this->assertStringStartsWith('projects/', $project->image_path);
        Storage::disk('public')->assertExists($project->image_path);
    }

    public function test_slug_collisions_are_suffixed(): void
    {
        $first = Project::factory()->create(['title' => 'Nettoyage du réservoir']);
        $second = Project::factory()->create(['title' => 'Nettoyage du réservoir']);
        $third = Project::factory()->create(['title' => 'Nettoyage du réservoir']);

        $this->assertSame('nettoyage-du-reservoir', $first->slug);
        $this->assertSame('nettoyage-du-reservoir-2', $second->slug);
        $this->assertSame('nettoyage-du-reservoir-3', $third->slug);

        // Le slug suit le titre et reste unique
        $third->update(['title' => 'Désinfection UV']);
        $this->assertSame('desinfection-uv', $third->fresh()->slug);
    }

    public function test_project_validation_rules(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->post(route('admin.projects.store'), $this->validData([
            'title' => 'Abc',
            'category' => 'inconnue',
            'target_budget' => '0',
            'status' => 'archive',
            'infrastructure_id' => 999,
            'image' => UploadedFile::fake()->create('plan.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasErrors([
            'title' => 'L\'intitulé doit contenir au moins 5 caractères.',
            'category' => 'La catégorie choisie est invalide.',
            'target_budget' => 'Le budget objectif doit être supérieur à 0 TND.',
            'status' => 'Le statut choisi est invalide.',
            'infrastructure_id' => 'L\'infrastructure choisie n\'existe pas.',
            'image' => 'L\'image doit être au format JPG ou PNG.',
        ]);

        $this->actingAs($staff)->post(route('admin.projects.store'), $this->validData([
            'title' => str_repeat('a', 151),
            'image' => UploadedFile::fake()->image('lourde.png')->size(3000),
        ]))->assertSessionHasErrors([
            'title' => 'L\'intitulé ne doit pas dépasser 150 caractères.',
            'image' => 'L\'image ne doit pas dépasser 2 Mo.',
        ]);

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_reversed_dates_are_rejected(): void
    {
        $this->actingAs($this->staff())->post(route('admin.projects.store'), $this->validData([
            'start_date' => '2026-12-31',
            'end_date' => '2026-10-01',
        ]))->assertSessionHasErrors(['end_date' => 'La date de fin doit être postérieure à la date de début.']);

        $this->actingAs($this->staff())->post(route('admin.projects.store'), $this->validData([
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-01',
        ]))->assertSessionHasErrors('end_date');

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_staff_can_view_a_project_with_its_fundings(): void
    {
        $project = Project::factory()->create(['target_budget' => 10000]);
        Funding::factory()->for($project)->municipal()->create(['donor_name' => 'Municipalité de La Soukra', 'amount' => 4000]);

        $this->actingAs($this->staff())->get(route('admin.projects.show', $project))
            ->assertOk()
            ->assertSee('Municipalité de La Soukra')
            ->assertSee('4 000 TND')
            ->assertSee('40 %')
            ->assertSee($project->infrastructure->zone->name)
            ->assertSee('Ajouter un financement')
            ->assertSee(route('admin.fundings.create', ['projet' => $project->id]), false);
    }

    public function test_staff_can_update_a_project_and_replace_its_image(): void
    {
        Storage::fake('public');
        $old = UploadedFile::fake()->image('old.jpg')->store(Project::IMAGE_DIRECTORY, Project::IMAGE_DISK);
        $project = Project::factory()->create(['image_path' => $old]);

        $this->actingAs($this->staff())->get(route('admin.projects.edit', $project))->assertOk()->assertSee($project->title);

        $this->actingAs($this->staff())->put(route('admin.projects.update', $project), $this->validData([
            'title' => 'Étanchéité du réservoir de Mégrine',
            'status' => 'en_travaux',
            'image' => UploadedFile::fake()->image('new.png'),
        ]))->assertRedirect(route('admin.projects.show', $project));

        $project->refresh();
        $this->assertSame('Étanchéité du réservoir de Mégrine', $project->title);
        $this->assertSame('etancheite-du-reservoir-de-megrine', $project->slug);
        $this->assertSame(ProjectStatus::EnTravaux, $project->status);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($project->image_path);

        $this->actingAs($this->staff())->put(route('admin.projects.update', $project), $this->validData(['remove_image' => '1']));
        $this->assertNull($project->fresh()->image_path);
    }

    public function test_deleting_a_project_removes_its_image_and_cascades_fundings(): void
    {
        Storage::fake('public');
        $image = UploadedFile::fake()->image('projet.jpg')->store(Project::IMAGE_DIRECTORY, Project::IMAGE_DISK);
        $project = Project::factory()->create(['image_path' => $image]);
        Funding::factory()->count(3)->for($project)->municipal()->create(['amount' => 100]);

        $this->actingAs($this->staff())->delete(route('admin.projects.destroy', $project))
            ->assertRedirect(route('admin.projects.index'))
            ->assertSessionHas('status');

        $this->assertModelMissing($project);
        $this->assertDatabaseCount('fundings', 0);
        Storage::disk('public')->assertMissing($image);
    }

    // ---------------------------------------------------------------- Relations et calculs

    public function test_project_relationships(): void
    {
        $infrastructure = Infrastructure::factory()->create();
        $project = Project::factory()->for($infrastructure)->create();
        $funding = Funding::factory()->for($project)->create();

        $this->assertTrue($project->infrastructure->is($infrastructure));
        $this->assertTrue($infrastructure->projects->first()->is($project));
        $this->assertTrue($project->fundings->first()->is($funding));
        $this->assertTrue($funding->project->is($project));

        // L'ouvrage supprimé ne supprime pas le projet (nullOnDelete)
        $infrastructure->delete();
        $this->assertNull($project->fresh()->infrastructure_id);
    }

    public function test_progress_is_capped_and_days_left_handles_expired_projects(): void
    {
        $project = Project::factory()->create([
            'target_budget' => 1000,
            'start_date' => today()->subMonth(),
            'end_date' => today()->addDays(10),
        ]);
        Funding::factory()->for($project)->municipal()->create(['amount' => 1500]);

        $project = Project::withSum('fundings', 'amount')->find($project->id);
        $this->assertSame(1500.0, $project->collected_amount);
        $this->assertSame(100, $project->progress_percent);
        $this->assertSame(10, $project->days_left);

        $expired = Project::factory()->expired()->create();
        $this->assertSame(0, $expired->days_left);
        $this->assertTrue($expired->isExpired());
        $this->assertSame(0, $expired->progress_percent);
    }

    public function test_open_scope_returns_public_collecting_projects(): void
    {
        $open = Project::factory()->collecting()->create();
        Project::factory()->collecting()->hidden()->create();
        Project::factory()->draft()->create();
        Project::factory()->funded()->create();

        $this->assertEquals([$open->id], Project::open()->pluck('id')->all());
    }

    // ---------------------------------------------------------------- Site citoyen

    public function test_public_list_shows_only_published_projects(): void
    {
        Project::factory()->collecting()->create(['title' => 'Station de pompage solaire de Raoued']);
        Project::factory()->funded()->create(['title' => 'Capteurs de qualité dans 12 écoles']);
        Project::factory()->draft()->create(['title' => 'Sectorisation du réseau de Ben Arous']);
        Project::factory()->hidden()->create(['title' => 'Projet interne masqué']);

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Station de pompage solaire de Raoued')
            ->assertSee('Capteurs de qualité dans 12 écoles')
            ->assertDontSee('Sectorisation du réseau de Ben Arous')
            ->assertDontSee('Projet interne masqué');
    }

    public function test_public_list_can_be_filtered_and_sorted(): void
    {
        Project::factory()->create(['title' => 'Forage de Mornag', 'category' => ProjectCategory::Decontamination, 'target_budget' => 1000, 'end_date' => today()->addDays(5)]);
        $almost = Project::factory()->create(['title' => 'Réservoir Belvédère', 'category' => ProjectCategory::Renovation, 'target_budget' => 1000, 'end_date' => today()->addDays(90)]);
        Funding::factory()->for($almost)->municipal()->create(['amount' => 900]);

        $this->get(route('projects.index', ['tri' => 'urgents']))->assertSeeInOrder(['Forage de Mornag', 'Réservoir Belvédère']);
        $this->get(route('projects.index', ['tri' => 'presque']))->assertSeeInOrder(['Réservoir Belvédère', 'Forage de Mornag']);
        $this->get(route('projects.index', ['categorie' => 'renovation']))->assertSee('Réservoir Belvédère')->assertDontSee('Forage de Mornag');
        $this->get(route('projects.index', ['tri' => 'inconnu']))->assertSessionHasErrors('tri');
    }

    public function test_public_project_page_shows_real_data(): void
    {
        $project = Project::factory()->create(['title' => 'Réhabilitation du réservoir de La Soukra', 'target_budget' => 220000]);
        Funding::factory()->for($project)->municipal()->create(['donor_name' => 'Municipalité de La Soukra', 'amount' => 120000]);
        Funding::factory()->for($project)->fromCitizen()->create(['amount' => 50]);

        $this->assertStringEndsWith('/projets/rehabilitation-du-reservoir-de-la-soukra', route('projects.show', $project));

        $this->get('/projets/rehabilitation-du-reservoir-de-la-soukra')
            ->assertOk()
            ->assertSee('120 050 TND')
            ->assertSee('collectés sur 220 000 TND')
            ->assertSee('54 %')
            ->assertSee('Municipalité de La Soukra')
            ->assertSee($project->infrastructure->zone->name)
            ->assertSee('Paiement simulé')
            ->assertSee('Connectez-vous pour contribuer');
    }

    public function test_draft_and_hidden_projects_are_not_published(): void
    {
        $this->get(route('projects.show', Project::factory()->draft()->create()))->assertNotFound();
        $this->get(route('projects.show', Project::factory()->hidden()->create()))->assertNotFound();
        $this->get('/projets/projet-inexistant')->assertNotFound();
    }
}
