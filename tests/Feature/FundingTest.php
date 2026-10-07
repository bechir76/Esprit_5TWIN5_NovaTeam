<?php

namespace Tests\Feature;

use App\Enums\FundingSource;
use App\Enums\ProjectStatus;
use App\Models\Funding;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FundingTest extends TestCase
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

    /** Projet en collecte ouvert depuis un mois, objectif 1 000 TND. */
    private function collectingProject(array $attributes = []): Project
    {
        return Project::factory()->collecting()->create(array_merge([
            'target_budget' => 1000,
            'start_date' => today()->subMonth(),
            'end_date' => today()->addMonth(),
        ], $attributes));
    }

    /** Données minimales valides d'un financement saisi par le personnel. */
    private function validData(Project $project, array $overrides = []): array
    {
        return array_merge([
            'project_id' => $project->id,
            'source_type' => 'municipalite',
            'donor_name' => 'Municipalité de La Soukra',
            'amount' => '400',
            'funded_at' => today()->format('Y-m-d'),
            'note' => 'Délibération du conseil municipal.',
        ], $overrides);
    }

    // ---------------------------------------------------------------- Accès au back office

    public function test_guest_and_citizen_cannot_access_funding_admin(): void
    {
        $funding = Funding::factory()->create();

        $this->get(route('admin.fundings.index'))->assertRedirect(route('admin.login'));
        $this->actingAs($this->citoyen())->get(route('admin.fundings.index'))->assertForbidden();
        $this->actingAs($this->citoyen())->post(route('admin.fundings.store'), $this->validData($funding->project))->assertForbidden();
        $this->actingAs($this->citoyen())->delete(route('admin.fundings.destroy', $funding))->assertForbidden();
        $this->assertDatabaseCount('fundings', 1);
    }

    // ---------------------------------------------------------------- CRUD

    public function test_staff_sees_real_kpis_and_progress(): void
    {
        Project::factory()->funded()->create(['title' => 'Capteurs dans 12 écoles', 'target_budget' => 48000]);
        $collecting = $this->collectingProject(['title' => 'Station de pompage de Raoued']);
        Funding::factory()->for($collecting)->fromCitizen()->create(['amount' => 250, 'donor_name' => 'Sana Ben Ali']);

        $this->actingAs($this->staff())->get(route('admin.fundings.index'))
            ->assertOk()
            ->assertSee('48 250')
            ->assertSee('Sana Ben Ali')
            ->assertSee('99 %')
            ->assertSee('Capteurs dans 12 écoles')
            ->assertSee('250 / 1 000 TND');
    }

    public function test_funding_index_can_be_filtered(): void
    {
        $soukra = $this->collectingProject(['title' => 'Réservoir de La Soukra']);
        $mornag = $this->collectingProject(['title' => 'Forage de Mornag']);
        Funding::factory()->for($soukra)->association()->create(['donor_name' => 'Association Eau Vive', 'funded_at' => today()->subDays(20)]);
        Funding::factory()->for($mornag)->company()->create(['donor_name' => 'Atlas Ingénierie', 'funded_at' => today()]);
        $staff = $this->staff();

        $this->actingAs($staff)->get(route('admin.fundings.index', ['projet' => $soukra->id]))
            ->assertSee('Association Eau Vive')->assertDontSee('Atlas Ingénierie');
        $this->actingAs($staff)->get(route('admin.fundings.index', ['source' => 'entreprise']))
            ->assertSee('Atlas Ingénierie')->assertDontSee('Association Eau Vive');
        $this->actingAs($staff)->get(route('admin.fundings.index', ['du' => today()->subDays(2)->format('Y-m-d')]))
            ->assertSee('Atlas Ingénierie')->assertDontSee('Association Eau Vive');
        $this->actingAs($staff)->get(route('admin.fundings.index', ['au' => today()->subDays(10)->format('Y-m-d')]))
            ->assertSee('Association Eau Vive')->assertDontSee('Atlas Ingénierie');
    }

    public function test_staff_can_create_a_funding_from_a_project_page(): void
    {
        $project = $this->collectingProject();

        $this->actingAs($this->staff())->get(route('admin.fundings.create', ['projet' => $project->id]))
            ->assertOk()
            ->assertSee('name="retour" value="projet"', false);

        $response = $this->actingAs($this->staff())->post(route('admin.fundings.store'), $this->validData($project, ['retour' => 'projet']));

        $funding = Funding::sole();
        $response->assertRedirect(route('admin.projects.show', $project))
            ->assertSessionHas('status', "Financement {$funding->reference} enregistré.");

        $this->assertMatchesRegularExpression('/^FIN-\d{4}-0001$/', $funding->reference);
        $this->assertSame(FundingSource::Municipalite, $funding->source_type);
        $this->assertSame('400.00', $funding->amount);
        $this->assertNull($funding->user_id);
        $this->assertTrue($funding->project->is($project));

        // Références successives
        $this->actingAs($this->staff())->post(route('admin.fundings.store'), $this->validData($project))
            ->assertRedirect(route('admin.fundings.show', Funding::latest('id')->first()));
        $this->assertMatchesRegularExpression('/^FIN-\d{4}-0002$/', Funding::latest('id')->first()->reference);
    }

    public function test_funding_validation_rules(): void
    {
        $project = $this->collectingProject();
        $staff = $this->staff();

        $this->actingAs($staff)->post(route('admin.fundings.store'), $this->validData($project, [
            'project_id' => 999,
            'source_type' => 'banque',
            'donor_name' => '',
            'amount' => '0',
        ]))->assertSessionHasErrors([
            'project_id' => 'Le projet choisi n\'existe pas.',
            'source_type' => 'La source choisie est invalide.',
            'donor_name' => 'Le nom du financeur est obligatoire.',
            'amount' => 'Le montant doit être supérieur à 0 TND.',
        ]);

        // Date entre le début de la collecte et aujourd'hui
        $this->actingAs($staff)->post(route('admin.fundings.store'), $this->validData($project, [
            'funded_at' => today()->addDay()->format('Y-m-d'),
        ]))->assertSessionHasErrors(['funded_at' => 'La date du financement ne peut pas être dans le futur.']);

        $this->actingAs($staff)->post(route('admin.fundings.store'), $this->validData($project, [
            'funded_at' => $project->start_date->copy()->subDay()->format('Y-m-d'),
        ]))->assertSessionHasErrors('funded_at');

        $this->assertDatabaseCount('fundings', 0);
    }

    public function test_staff_can_view_update_and_delete_a_funding(): void
    {
        $project = $this->collectingProject();
        $funding = Funding::factory()->for($project)->association()->create(['amount' => 300, 'donor_name' => 'Association Eau Vive']);
        $staff = $this->staff();

        $this->actingAs($staff)->get(route('admin.fundings.show', $funding))->assertOk()->assertSee($funding->reference)->assertSee('Association Eau Vive');
        $this->actingAs($staff)->get(route('admin.fundings.edit', $funding))->assertOk();

        $this->actingAs($staff)->put(route('admin.fundings.update', $funding), $this->validData($project, ['amount' => '350,5', 'donor_name' => 'Réseau Enviro-Tunisie']))
            ->assertRedirect(route('admin.fundings.show', $funding));
        $this->assertSame('350.50', $funding->fresh()->amount);
        $this->assertSame('Réseau Enviro-Tunisie', $funding->fresh()->donor_name);

        $this->actingAs($staff)->delete(route('admin.fundings.destroy', ['funding' => $funding, 'retour' => 'projet']))
            ->assertRedirect(route('admin.projects.show', $project));
        $this->assertModelMissing($funding);
    }

    // ---------------------------------------------------------------- Statut automatique du projet

    public function test_project_becomes_funded_when_target_is_reached(): void
    {
        $project = $this->collectingProject();

        $this->actingAs($this->staff())->post(route('admin.fundings.store'), $this->validData($project, ['amount' => '600']));
        $this->assertSame(ProjectStatus::EnCollecte, $project->fresh()->status);

        $this->actingAs($this->staff())->post(route('admin.fundings.store'), $this->validData($project, ['amount' => '400']));
        $this->assertSame(ProjectStatus::Finance, $project->fresh()->status);
    }

    public function test_status_is_recalculated_after_update_and_deletion(): void
    {
        $project = $this->collectingProject();
        $big = Funding::factory()->for($project)->municipal()->create(['amount' => 800]);
        $small = Funding::factory()->for($project)->association()->create(['amount' => 200]);
        $this->assertSame(ProjectStatus::Finance, $project->fresh()->status);

        // Suppression : l'objectif n'est plus atteint
        $this->actingAs($this->staff())->delete(route('admin.fundings.destroy', $small));
        $this->assertSame(ProjectStatus::EnCollecte, $project->fresh()->status);

        // Modification du montant : de nouveau financé
        $this->actingAs($this->staff())->put(route('admin.fundings.update', $big), $this->validData($project, ['amount' => '1000']));
        $this->assertSame(ProjectStatus::Finance, $project->fresh()->status);
    }

    public function test_moving_a_funding_recalculates_both_projects(): void
    {
        $from = $this->collectingProject();
        $to = $this->collectingProject();
        $funding = Funding::factory()->for($from)->municipal()->create(['amount' => 1000]);
        $this->assertSame(ProjectStatus::Finance, $from->fresh()->status);

        $this->actingAs($this->staff())->put(route('admin.fundings.update', $funding), $this->validData($to, ['amount' => '1000']));

        $this->assertSame(ProjectStatus::EnCollecte, $from->fresh()->status);
        $this->assertSame(ProjectStatus::Finance, $to->fresh()->status);
    }

    public function test_other_statuses_are_not_changed_automatically(): void
    {
        foreach ([ProjectStatus::Brouillon, ProjectStatus::EnTravaux, ProjectStatus::Termine] as $status) {
            $project = $this->collectingProject(['status' => $status]);
            $funding = Funding::factory()->for($project)->municipal()->create(['amount' => 1500]);
            $this->assertSame($status, $project->fresh()->status);

            $funding->delete();
            $this->assertSame($status, $project->fresh()->status);
        }
    }

    // ---------------------------------------------------------------- Contribution citoyenne

    public function test_citizen_can_contribute_to_a_collecting_project(): void
    {
        $citizen = $this->citoyen();
        $other = $this->citoyen();
        $project = $this->collectingProject();

        $response = $this->actingAs($citizen)->post(route('projects.fundings.store', $project), [
            'amount' => '50',
            // Champs non autorisés ignorés : contributeur, source et date fixés côté serveur
            'user_id' => $other->id,
            'source_type' => 'municipalite',
            'donor_name' => 'Faux nom',
            'funded_at' => '2020-01-01',
        ]);

        $funding = Funding::sole();
        $response->assertRedirect(route('projects.show', $project))
            ->assertSessionHas('status', "Merci ! Votre contribution de 50 TND ({$funding->reference}) est enregistrée. Paiement simulé.");

        $this->assertSame(FundingSource::Citoyen, $funding->source_type);
        $this->assertTrue($funding->user->is($citizen));
        $this->assertSame($citizen->name, $funding->donor_name);
        $this->assertSame('50.00', $funding->amount);
        $this->assertTrue($funding->funded_at->isToday());
        $this->assertTrue($citizen->fundings->first()->is($funding));
    }

    public function test_other_amount_replaces_the_preset_amount(): void
    {
        $citizen = $this->citoyen();
        $project = $this->collectingProject();

        $this->actingAs($citizen)->post(route('projects.fundings.store', $project), ['amount' => '20', 'amount_other' => '75,5'])
            ->assertSessionHasNoErrors();

        $this->assertSame('75.50', Funding::sole()->amount);
    }

    public function test_contribution_amount_limits(): void
    {
        $citizen = $this->citoyen();
        $project = $this->collectingProject(['target_budget' => 100000]);

        $this->actingAs($citizen)->post(route('projects.fundings.store', $project), ['amount' => '4.99'])
            ->assertSessionHasErrors(['amount' => 'La contribution minimale est de 5 TND.']);
        $this->actingAs($citizen)->post(route('projects.fundings.store', $project), ['amount' => '10001'])
            ->assertSessionHasErrors(['amount' => 'La contribution ne peut pas dépasser 10 000 TND.']);
        $this->actingAs($citizen)->post(route('projects.fundings.store', $project), ['amount' => 'beaucoup'])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('fundings', 0);

        $this->actingAs($citizen)->post(route('projects.fundings.store', $project), ['amount' => '10000'])->assertSessionHasNoErrors();
        $this->actingAs($citizen)->post(route('projects.fundings.store', $project), ['amount' => '5'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('fundings', 2);
    }

    public function test_contribution_to_a_project_not_collecting_is_rejected(): void
    {
        $citizen = $this->citoyen();

        foreach ([ProjectStatus::Finance, ProjectStatus::EnTravaux, ProjectStatus::Termine] as $status) {
            $project = $this->collectingProject(['status' => $status]);

            $this->actingAs($citizen)->post(route('projects.fundings.store', $project), ['amount' => '50'])
                ->assertSessionHasErrors(['project' => "Ce projet n'accepte pas de contributions : il est « {$status->label()} »."]);
        }

        // Brouillon et projet masqué : rejetés aussi
        $this->actingAs($citizen)->post(route('projects.fundings.store', $this->collectingProject(['status' => ProjectStatus::Brouillon])), ['amount' => '50'])
            ->assertSessionHasErrors('project');
        $this->actingAs($citizen)->post(route('projects.fundings.store', $this->collectingProject(['is_public' => false])), ['amount' => '50'])
            ->assertSessionHasErrors('project');

        $this->assertDatabaseCount('fundings', 0);
    }

    public function test_contribution_to_an_expired_project_is_rejected(): void
    {
        $project = Project::factory()->collecting()->expired()->create();

        $this->actingAs($this->citoyen())->post(route('projects.fundings.store', $project), ['amount' => '50'])
            ->assertSessionHasErrors(['project' => 'La collecte de ce projet est terminée depuis le '.$project->end_date->format('d/m/Y').'.']);

        $this->assertDatabaseCount('fundings', 0);
    }

    public function test_contribution_to_a_missing_project_returns_404(): void
    {
        $this->actingAs($this->citoyen())->post('/projets/projet-inexistant/contributions', ['amount' => '50'])->assertNotFound();
    }

    public function test_guest_cannot_contribute(): void
    {
        $project = $this->collectingProject();

        $this->post(route('projects.fundings.store', $project), ['amount' => '50'])->assertRedirect(route('login'));
        $this->assertDatabaseCount('fundings', 0);
    }

    public function test_citizen_contribution_can_complete_the_funding(): void
    {
        $project = $this->collectingProject();
        Funding::factory()->for($project)->municipal()->create(['amount' => 980]);

        $this->actingAs($this->citoyen())->post(route('projects.fundings.store', $project), ['amount' => '20']);

        $this->assertSame(ProjectStatus::Finance, $project->fresh()->status);
    }

    // ---------------------------------------------------------------- Profil, accueil, tableau de bord

    public function test_profile_lists_the_citizen_contributions(): void
    {
        $citizen = $this->citoyen();
        $project = $this->collectingProject(['title' => 'Réservoir de La Soukra']);
        $mine = Funding::factory()->for($project)->fromCitizen($citizen)->create(['amount' => 50]);
        Funding::factory()->for($project)->fromCitizen($citizen)->create(['amount' => 30]);
        Funding::factory()->for($project)->fromCitizen()->create(['amount' => 999]);

        $this->get(route('profile.edit'))->assertRedirect(route('login'));

        $this->actingAs($citizen)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Réservoir de La Soukra')
            ->assertSee($mine->reference)
            ->assertSee('80 TND')
            ->assertDontSee('999 TND');
    }

    public function test_home_and_dashboard_use_real_project_data(): void
    {
        $project = $this->collectingProject(['title' => 'Station de pompage solaire de Raoued']);
        Funding::factory()->for($project)->municipal()->create(['amount' => 600]);
        Project::factory()->funded()->create(['target_budget' => 4000]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Station de pompage solaire de Raoued')
            ->assertSee('60 % · ', false);

        $this->actingAs($this->staff())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('4 600')
            ->assertSee('Station de pompage solaire de Raoued')
            ->assertSee('600 / 1 000 TND');
    }
}
