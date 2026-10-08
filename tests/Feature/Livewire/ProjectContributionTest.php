<?php

namespace Tests\Feature\Livewire;

use App\Enums\FundingSource;
use App\Enums\ProjectStatus;
use App\Livewire\ProjectContribution;
use App\Models\Funding;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectContributionTest extends TestCase
{
    use RefreshDatabase;

    /** Projet en collecte de 1 000 TND, sans financement, ouvert encore un mois. */
    private function project(array $attributes = []): Project
    {
        return Project::factory()->collecting()->create($attributes + [
            'target_budget' => 1000,
            'start_date' => today()->subMonth(),
            'end_date' => today()->addMonth(),
        ]);
    }

    public function test_show_page_contains_the_component(): void
    {
        $project = $this->project();

        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertSeeLivewire(ProjectContribution::class);
    }

    public function test_citizen_contribution_updates_amount_and_progress(): void
    {
        $project = $this->project();
        $citizen = User::factory()->citoyen()->create();

        Livewire::actingAs($citizen)
            ->test(ProjectContribution::class, ['project' => $project])
            ->assertSee('0 %')
            ->set('amountOther', '150,5')
            ->assertHasNoErrors()
            ->call('contribute')
            ->assertHasNoErrors()
            ->assertSee('150,50 TND')
            ->assertSee('15 %')
            ->assertSee('Merci ! Votre contribution de 150,50 TND')
            ->assertSet('amountOther', '');

        $funding = Funding::sole();
        $this->assertSame($citizen->id, $funding->user_id);
        $this->assertSame(FundingSource::Citoyen, $funding->source_type);
        $this->assertSame($citizen->name, $funding->donor_name);
        $this->assertEquals(150.5, $funding->amount);
        $this->assertTrue($funding->funded_at->isToday());
    }

    public function test_quick_amount_is_used_when_no_other_amount_is_given(): void
    {
        $project = $this->project();

        Livewire::actingAs(User::factory()->citoyen()->create())
            ->test(ProjectContribution::class, ['project' => $project])
            ->set('amount', '100')
            ->call('contribute')
            ->assertHasNoErrors()
            ->assertSee('10 %');

        $this->assertEquals(100, Funding::sole()->amount);
    }

    public function test_amount_is_validated_in_real_time(): void
    {
        Livewire::actingAs(User::factory()->citoyen()->create())
            ->test(ProjectContribution::class, ['project' => $this->project()])
            ->set('amountOther', '2')
            ->assertHasErrors(['amount' => 'min'])
            ->assertSee('La contribution minimale est de 5 TND.')
            ->set('amountOther', '20000')
            ->assertHasErrors(['amount' => 'max'])
            ->set('amountOther', 'beaucoup')
            ->assertHasErrors(['amount' => 'numeric'])
            ->set('amountOther', '25')
            ->assertHasNoErrors();
    }

    public function test_invalid_amount_is_refused(): void
    {
        $project = $this->project();

        Livewire::actingAs(User::factory()->citoyen()->create())
            ->test(ProjectContribution::class, ['project' => $project])
            ->set('amountOther', '10001')
            ->call('contribute')
            ->assertHasErrors(['amount' => 'max'])
            ->assertSee('0 %');

        $this->assertDatabaseCount('fundings', 0);
    }

    public function test_guest_sees_a_login_link_and_cannot_contribute(): void
    {
        $project = $this->project();

        Livewire::test(ProjectContribution::class, ['project' => $project])
            ->assertSee('Connectez-vous pour contribuer')
            ->assertDontSee('Contribuer au projet')
            ->call('contribute')
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('fundings', 0);
    }

    public function test_project_not_collecting_or_expired_is_refused(): void
    {
        $citizen = User::factory()->citoyen()->create();

        // Le formulaire n'est pas affiché : le message d'erreur est vérifié dans le sac d'erreurs du composant
        $funded = $this->project(['status' => ProjectStatus::Finance]);
        $component = Livewire::actingAs($citizen)
            ->test(ProjectContribution::class, ['project' => $funded])
            ->assertSee('Ce projet n\'accepte plus de contributions', false)
            ->call('contribute')
            ->assertHasErrors('project');
        $this->assertSame('Ce projet n\'accepte pas de contributions : il est « Financé ».', $component->errors()->first('project'));

        $expired = $this->project(['start_date' => today()->subMonths(3), 'end_date' => today()->subDay()]);
        $component = Livewire::actingAs($citizen)
            ->test(ProjectContribution::class, ['project' => $expired])
            ->call('contribute')
            ->assertHasErrors('project');
        $this->assertSame('La collecte de ce projet est terminée depuis le '.today()->subDay()->format('d/m/Y').'.', $component->errors()->first('project'));

        $this->assertDatabaseCount('fundings', 0);
    }

    public function test_reaching_the_target_switches_the_project_to_funded(): void
    {
        $project = $this->project();
        Funding::factory()->for($project)->create(['amount' => 950]);

        Livewire::actingAs(User::factory()->citoyen()->create())
            ->test(ProjectContribution::class, ['project' => $project])
            ->set('amount', '50')
            ->call('contribute')
            ->assertHasNoErrors()
            ->assertSee('100 %')
            ->assertSee('Ce projet n\'accepte plus de contributions', false);

        $this->assertSame(ProjectStatus::Finance, $project->fresh()->status);
    }
}
