<?php

namespace Tests\Feature;

use App\Enums\FundingSource;
use App\Enums\FundingStatus;
use App\Enums\ProjectStatus;
use App\Models\Funding;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Financements côté back office : CRUD, recalcul du statut, export CSV, tableau financier, profil. */
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

    /** Projet en collecte ouvert depuis un mois, objectif 1 000 DT. */
    private function collectingProject(array $attributes = []): Project
    {
        return Project::factory()->open()->create(array_merge([
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
        $this->actingAs($this->citoyen())->get(route('admin.fundings.export'))->assertForbidden();
        $this->assertDatabaseCount('fundings', 1);
    }

    // ---------------------------------------------------------------- CRUD

    public function test_staff_sees_real_kpis_and_progress(): void
    {
        Project::factory()->funded()->create(['title' => 'Capteurs dans 12 écoles', 'target_budget' => 48000]);
        $collecting = $this->collectingProject(['title' => 'Station de pompage de Raoued']);
        Funding::factory()->for($collecting)->fromCitizen()->create(['amount' => 250, 'donor_name' => 'Sana Ben Ali']);
        // Un don en attente de paiement n'est pas compté
        Funding::factory()->for($collecting)->fromCitizen()->pending()->create(['amount' => 500]);

        $this->actingAs($this->staff())->get(route('admin.fundings.index'))
            ->assertOk()
            ->assertSee('48 250')
            ->assertSee('Sana Ben Ali')
            ->assertSee('99 %')
            ->assertSee('Capteurs dans 12 écoles')
            ->assertSee('250 / 1 000 DT');
    }

    public function test_funding_index_can_be_filtered(): void
    {
        $soukra = $this->collectingProject(['title' => 'Réservoir de La Soukra']);
        $mornag = $this->collectingProject(['title' => 'Forage de Mornag']);
        Funding::factory()->for($soukra)->association()->create(['donor_name' => 'Association Eau Vive', 'funded_at' => today()->subDays(20)]);
        Funding::factory()->for($mornag)->company()->create(['donor_name' => 'Atlas Ingénierie', 'funded_at' => today()]);
        Funding::factory()->for($mornag)->fromCitizen()->pending()->create(['donor_name' => 'Mariem Gharbi']);
        $staff = $this->staff();

        $this->actingAs($staff)->get(route('admin.fundings.index', ['projet' => $soukra->id]))
            ->assertSee('Association Eau Vive')->assertDontSee('Atlas Ingénierie');
        $this->actingAs($staff)->get(route('admin.fundings.index', ['source' => 'entreprise']))
            ->assertSee('Atlas Ingénierie')->assertDontSee('Association Eau Vive');
        $this->actingAs($staff)->get(route('admin.fundings.index', ['statut' => 'pending']))
            ->assertSee('Mariem Gharbi')->assertDontSee('Atlas Ingénierie');
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
        // Financement saisi par le personnel : encaissé
        $this->assertSame(FundingStatus::Confirmed, $funding->status);
        $this->assertNotNull($funding->paid_at);
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

        $this->actingAs($staff)->put(route('admin.fundings.update', $funding), $this->validData($project, ['amount' => '350,5', 'donor_name' => 'Réseau Enviro-Tunisie', 'is_anonymous' => '1']))
            ->assertRedirect(route('admin.fundings.show', $funding));
        $this->assertSame('350.50', $funding->fresh()->amount);
        $this->assertSame('Réseau Enviro-Tunisie', $funding->fresh()->donor_name);
        $this->assertTrue($funding->fresh()->is_anonymous);

        $this->actingAs($staff)->delete(route('admin.fundings.destroy', ['funding' => $funding, 'retour' => 'projet']))
            ->assertRedirect(route('admin.projects.show', $project));
        $this->assertModelMissing($funding);
    }

    public function test_online_donation_amount_cannot_be_edited_nor_deleted(): void
    {
        $project = $this->collectingProject();
        $donation = Funding::factory()->for($project)->fromCitizen()->confirmed()->create(['amount' => 50]);
        $staff = $this->staff();

        $this->actingAs($staff)->put(route('admin.fundings.update', $donation), $this->validData($project, ['source_type' => 'citoyen', 'amount' => '500']))
            ->assertSessionHasErrors(['amount' => 'Le montant, le projet et la source d\'un don payé en ligne ne peuvent pas être modifiés.']);

        $this->actingAs($staff)->delete(route('admin.fundings.destroy', $donation))->assertSessionHas('error');
        $this->assertModelExists($donation);
    }

    // ---------------------------------------------------------------- Statut automatique du projet

    public function test_project_becomes_funded_when_target_is_reached(): void
    {
        $project = $this->collectingProject();

        $this->actingAs($this->staff())->post(route('admin.fundings.store'), $this->validData($project, ['amount' => '600']));
        $this->assertSame(ProjectStatus::Open, $project->fresh()->status);

        $this->actingAs($this->staff())->post(route('admin.fundings.store'), $this->validData($project, ['amount' => '400']));
        $this->assertSame(ProjectStatus::Funded, $project->fresh()->status);
    }

    public function test_status_is_recalculated_after_update_and_deletion(): void
    {
        $project = $this->collectingProject();
        $big = Funding::factory()->for($project)->municipal()->create(['amount' => 800]);
        $small = Funding::factory()->for($project)->association()->create(['amount' => 200]);
        $this->assertSame(ProjectStatus::Funded, $project->fresh()->status);

        // Suppression : l'objectif n'est plus atteint
        $this->actingAs($this->staff())->delete(route('admin.fundings.destroy', $small));
        $this->assertSame(ProjectStatus::Open, $project->fresh()->status);

        // Modification du montant : de nouveau financé
        $this->actingAs($this->staff())->put(route('admin.fundings.update', $big), $this->validData($project, ['amount' => '1000']));
        $this->assertSame(ProjectStatus::Funded, $project->fresh()->status);
    }

    public function test_moving_a_funding_recalculates_both_projects(): void
    {
        $from = $this->collectingProject();
        $to = $this->collectingProject();
        $funding = Funding::factory()->for($from)->municipal()->create(['amount' => 1000]);
        $this->assertSame(ProjectStatus::Funded, $from->fresh()->status);

        $this->actingAs($this->staff())->put(route('admin.fundings.update', $funding), $this->validData($to, ['amount' => '1000']));

        $this->assertSame(ProjectStatus::Open, $from->fresh()->status);
        $this->assertSame(ProjectStatus::Funded, $to->fresh()->status);
    }

    public function test_other_statuses_are_not_changed_automatically(): void
    {
        foreach ([ProjectStatus::Draft, ProjectStatus::InProgress, ProjectStatus::Completed, ProjectStatus::Failed] as $status) {
            $project = $this->collectingProject(['status' => $status]);
            $funding = Funding::factory()->for($project)->municipal()->create(['amount' => 1500]);
            $this->assertSame($status, $project->fresh()->status);

            $funding->delete();
            $this->assertSame($status, $project->fresh()->status);
        }
    }

    public function test_pending_donations_do_not_fund_the_project(): void
    {
        $project = $this->collectingProject();
        Funding::factory()->for($project)->fromCitizen()->pending()->create(['amount' => 1000]);

        $this->assertSame(ProjectStatus::Open, $project->fresh()->status);
        $this->assertSame(0, $project->fresh()->progress_percent);
    }

    // ---------------------------------------------------------------- Export, tableau financier, profil

    public function test_staff_can_export_fundings_as_csv(): void
    {
        $project = $this->collectingProject(['title' => 'Réservoir de La Soukra']);
        Funding::factory()->for($project)->association()->create(['donor_name' => 'Association Eau Vive', 'amount' => 1500]);
        Funding::factory()->for($project)->company()->create(['donor_name' => 'Atlas Ingénierie']);

        $response = $this->actingAs($this->staff())->get(route('admin.fundings.export', ['source' => 'association']));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Référence;Date;Projet;Source;Financeur', $csv);
        $this->assertStringContainsString('Association Eau Vive', $csv);
        $this->assertStringContainsString('1500,00', $csv);
        $this->assertStringNotContainsString('Atlas Ingénierie', $csv);
    }

    public function test_financial_dashboard_shows_sources_almost_funded_and_anonymous_donors(): void
    {
        $almost = Project::factory()->almostFunded()->create(['title' => 'Capteurs connectés de Raoued']);
        Funding::factory()->for($almost)->fromCitizen()->anonymous()->create(['amount' => 300, 'donor_name' => 'Nom Caché']);
        // Collecte sans don récent proche de son échéance : en retard
        $this->collectingProject(['title' => 'Forage de Mornag', 'end_date' => today()->addDays(5)]);

        $this->actingAs($this->staff())->get(route('admin.fundings.dashboard'))
            ->assertOk()
            ->assertSee('Capteurs connectés de Raoued')
            ->assertSee('Forage de Mornag')
            ->assertSee('Anonyme')
            ->assertDontSee('Nom Caché')
            ->assertSee('monthlyChart', false);
    }

    public function test_profile_lists_the_citizen_contributions_with_receipts(): void
    {
        $citizen = $this->citoyen();
        $project = $this->collectingProject(['title' => 'Réservoir de La Soukra']);
        $mine = Funding::factory()->for($project)->fromCitizen($citizen)->confirmed()->create(['amount' => 50]);
        Funding::factory()->for($project)->fromCitizen($citizen)->confirmed()->create(['amount' => 30]);
        Funding::factory()->for($project)->fromCitizen($citizen)->pending()->create(['amount' => 70]);
        Funding::factory()->for($project)->fromCitizen()->create(['amount' => 999]);

        $this->get(route('profile.edit'))->assertRedirect(route('login'));

        $this->actingAs($citizen)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Réservoir de La Soukra')
            ->assertSee($mine->reference)
            ->assertSee($mine->fresh()->receipt_number)
            ->assertSee(route('fundings.receipt', $mine), false)
            ->assertSee('En attente')
            ->assertSee('80 DT')
            ->assertDontSee('999 DT');
    }

    public function test_citizen_user_relationship(): void
    {
        $citizen = $this->citoyen();
        $funding = Funding::factory()->fromCitizen($citizen)->create();

        $this->assertTrue($funding->user->is($citizen));
        $this->assertTrue($citizen->fundings->first()->is($funding));

        // Le compte supprimé laisse le don (user_id null)
        $citizen->delete();
        $this->assertNull($funding->fresh()->user_id);
    }

    public function test_home_and_dashboard_use_confirmed_project_data(): void
    {
        $project = $this->collectingProject(['title' => 'Station de pompage solaire de Raoued']);
        Funding::factory()->for($project)->municipal()->create(['amount' => 600]);
        Funding::factory()->for($project)->fromCitizen()->pending()->create(['amount' => 300]);
        Project::factory()->funded()->create(['target_budget' => 4000]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Station de pompage solaire de Raoued')
            ->assertSee('60 % · ', false);

        $this->actingAs($this->staff())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('4 600')
            ->assertSee('Station de pompage solaire de Raoued')
            ->assertSee('600 / 1 000 DT');
    }
}
