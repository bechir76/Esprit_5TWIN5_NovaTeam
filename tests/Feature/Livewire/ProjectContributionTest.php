<?php

namespace Tests\Feature\Livewire;

use App\Enums\FundingSource;
use App\Enums\FundingStatus;
use App\Enums\ProjectStatus;
use App\Livewire\ProjectContribution;
use App\Livewire\ProjectProgress;
use App\Models\Funding;
use App\Models\Project;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

/** Composants Livewire de la fiche publique : don (redirection Stripe simulée) et progression en temps réel. */
class ProjectContributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.secret' => 'sk_test_fake']);
        $this->partialMock(StripeService::class, fn (MockInterface $mock) => $mock->shouldReceive('createCheckoutSession')
            ->andReturnUsing(fn (Funding $f) => ['id' => 'cs_test_'.$f->id, 'url' => 'https://checkout.stripe.com/c/pay/cs_test_'.$f->id]));
    }

    /** Projet en collecte de 1 000 DT, sans financement, ouvert encore un mois. */
    private function project(array $attributes = []): Project
    {
        return Project::factory()->open()->create($attributes + [
            'target_budget' => 1000,
            'start_date' => today()->subMonth(),
            'end_date' => today()->addMonth(),
        ]);
    }

    public function test_show_page_contains_the_components(): void
    {
        $project = $this->project();

        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertSeeLivewire(ProjectContribution::class)
            ->assertSeeLivewire(ProjectProgress::class)
            ->assertSee('wire:poll.5s', false);
    }

    public function test_citizen_is_redirected_to_stripe_with_a_pending_donation(): void
    {
        $project = $this->project();
        $citizen = User::factory()->citoyen()->create();

        Livewire::actingAs($citizen)
            ->test(ProjectContribution::class, ['project' => $project])
            ->set('amountOther', '150,5')
            ->set('anonymous', true)
            ->assertHasNoErrors()
            ->call('contribute')
            ->assertHasNoErrors()
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_'.Funding::sole()->id);

        $funding = Funding::sole();
        $this->assertSame($citizen->id, $funding->user_id);
        $this->assertSame(FundingSource::Citoyen, $funding->source_type);
        $this->assertSame(FundingStatus::Pending, $funding->status);
        $this->assertSame('cs_test_'.$funding->id, $funding->stripe_session_id);
        $this->assertTrue($funding->is_anonymous);
        $this->assertEquals(150.5, $funding->amount);
    }

    public function test_quick_amount_is_used_when_no_other_amount_is_given(): void
    {
        Livewire::actingAs(User::factory()->citoyen()->create())
            ->test(ProjectContribution::class, ['project' => $this->project()])
            ->set('amount', '20')
            ->call('contribute');

        $this->assertEquals(20, Funding::sole()->amount);
    }

    public function test_amount_is_validated_in_real_time(): void
    {
        Livewire::actingAs(User::factory()->citoyen()->create())
            ->test(ProjectContribution::class, ['project' => $this->project()])
            ->set('amountOther', '3')
            ->assertHasErrors(['amount' => 'min'])
            ->set('amountOther', '20000')
            ->assertHasErrors(['amount' => 'max'])
            ->set('amountOther', '75')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('fundings', 0);
    }

    public function test_guest_sees_a_login_link_and_cannot_donate(): void
    {
        $project = $this->project();

        Livewire::test(ProjectContribution::class, ['project' => $project])
            ->assertSee('Connectez-vous pour contribuer')
            ->call('contribute')
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('fundings', 0);
    }

    public function test_project_not_open_or_expired_is_refused(): void
    {
        $citizen = User::factory()->citoyen()->create();

        Livewire::actingAs($citizen)
            ->test(ProjectContribution::class, ['project' => $this->project(['status' => ProjectStatus::Funded])])
            ->assertSee('Ce projet n\'accepte plus de dons', false)
            ->call('contribute')
            ->assertHasErrors('project');

        Livewire::actingAs($citizen)
            ->test(ProjectContribution::class, ['project' => Project::factory()->open()->expired()->create()])
            ->assertSee('la collecte est terminée')
            ->call('contribute')
            ->assertHasErrors('project');

        $this->assertDatabaseCount('fundings', 0);
    }

    public function test_staff_cannot_donate_online(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(ProjectContribution::class, ['project' => $this->project()])
            ->call('contribute')
            ->assertHasErrors('project');

        $this->assertDatabaseCount('fundings', 0);
    }

    public function test_progress_component_counts_confirmed_donations_only(): void
    {
        $project = $this->project();
        Funding::factory()->for($project)->fromCitizen()->confirmed()->create(['amount' => 250]);
        Funding::factory()->for($project)->fromCitizen()->pending()->create(['amount' => 500]);

        Livewire::test(ProjectProgress::class, ['project' => $project])
            ->assertSee('250 DT')
            ->assertSee('25 %')
            ->assertSee('1</strong> donateur', false);

        // Nouveau don confirmé : visible au prochain rafraîchissement (wire:poll)
        Funding::factory()->for($project)->municipal()->create(['amount' => 250]);
        Livewire::test(ProjectProgress::class, ['project' => $project])->assertSee('500 DT')->assertSee('50 %');
    }
}
