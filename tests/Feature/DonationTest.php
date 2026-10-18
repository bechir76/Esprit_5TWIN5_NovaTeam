<?php

namespace Tests\Feature;

use App\Enums\FundingSource;
use App\Enums\FundingStatus;
use App\Enums\ProjectStatus;
use App\Http\Controllers\ReceiptController;
use App\Models\Funding;
use App\Models\Project;
use App\Models\User;
use App\Notifications\DonationConfirmed;
use App\Notifications\ProjectFailed;
use App\Notifications\ProjectFunded;
use App\Notifications\ProjectMilestoneReached;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Dons Stripe Checkout (A1 à A5, A7, A8) : Stripe est simulé (création de session et remboursement) ;
 * la vérification de signature du webhook est réelle (en-tête Stripe-Signature calculé avec le secret de test).
 */
class DonationTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_secret';

    /** @var list<string> Remboursements demandés à Stripe (simulé). */
    private array $refunds = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.secret' => 'sk_test_fake', 'services.stripe.webhook_secret' => self::WEBHOOK_SECRET]);

        $this->partialMock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createCheckoutSession')->andReturnUsing(fn (Funding $funding) => [
                'id' => 'cs_test_'.$funding->id,
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_'.$funding->id,
            ]);
            $mock->shouldReceive('refund')->andReturnUsing(function (Funding $funding) {
                $this->refunds[] = $funding->reference;

                return 're_test_'.$funding->id;
            });
        });
    }

    // ---------------------------------------------------------------- Helpers

    private function citizen(): User
    {
        return User::factory()->citoyen()->create();
    }

    private function openProject(array $attributes = []): Project
    {
        return Project::factory()->open()->create(array_merge([
            'target_budget' => 1000,
            'start_date' => today()->subMonth(),
            'end_date' => today()->addMonth(),
        ], $attributes));
    }

    /** Don lancé par la route POST, puis renvoi du don « pending » créé. */
    private function donate(User $user, Project $project, string $amount, array $extra = []): TestResponse
    {
        return $this->actingAs($user)->post(route('projects.donate', $project), ['amount' => $amount] + $extra);
    }

    /** Envoie un événement Checkout signé au webhook. */
    private function webhook(string $type, Funding $funding, array $session = []): TestResponse
    {
        [$currency, $amount] = StripeService::chargeAmount((float) $funding->amount);

        $payload = json_encode([
            'id' => 'evt_'.uniqid(),
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => array_merge([
                'id' => $funding->stripe_session_id,
                'object' => 'checkout.session',
                'payment_status' => 'paid',
                'amount_total' => $amount,
                'currency' => $currency,
                'payment_intent' => 'pi_test_'.$funding->id,
                'metadata' => ['funding_id' => (string) $funding->id],
            ], $session)],
        ]);

        return $this->signedWebhook($payload);
    }

    private function signedWebhook(string $payload, ?string $secret = null): TestResponse
    {
        $timestamp = time();
        $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret ?? self::WEBHOOK_SECRET);

        return $this->call('POST', route('stripe.webhook'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $payload);
    }

    // ---------------------------------------------------------------- Don et webhook

    public function test_donation_is_pending_until_a_valid_webhook_confirms_it(): void
    {
        Notification::fake();
        $citizen = $this->citizen();
        $project = $this->openProject();

        // Champs non autorisés ignorés : donateur, source et montant viennent du serveur
        $this->donate($citizen, $project, '50', ['user_id' => 999, 'source_type' => 'municipalite', 'anonymous' => '1'])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_'.Funding::sole()->id);

        $funding = Funding::sole();
        $this->assertSame(FundingStatus::Pending, $funding->status);
        $this->assertSame(FundingSource::Citoyen, $funding->source_type);
        $this->assertTrue($funding->user->is($citizen));
        $this->assertSame($citizen->name, $funding->donor_name);
        $this->assertTrue($funding->is_anonymous);
        $this->assertSame(0, $project->fresh()->progress_percent);

        // La page de retour n'affiche qu'un message : le don reste en attente
        $this->actingAs($citizen)->get(route('projects.donate.success', $project))->assertSessionHas('status');
        $this->assertSame(FundingStatus::Pending, $funding->fresh()->status);

        $this->webhook('checkout.session.completed', $funding)->assertOk();

        $funding->refresh();
        $this->assertSame(FundingStatus::Confirmed, $funding->status);
        $this->assertNotNull($funding->paid_at);
        $this->assertSame('pi_test_'.$funding->id, $funding->stripe_payment_intent_id);
        $this->assertMatchesRegularExpression('/^REC-\d{4}-000001$/', $funding->receipt_number);
        Notification::assertSentTo($citizen, DonationConfirmed::class);
    }

    public function test_replayed_webhook_does_not_create_duplicates(): void
    {
        Notification::fake();
        $citizen = $this->citizen();
        $project = $this->openProject();
        $this->donate($citizen, $project, '100');
        $funding = Funding::sole();

        $this->webhook('checkout.session.completed', $funding)->assertOk();
        $receipt = $funding->fresh()->receipt_number;
        $this->webhook('checkout.session.completed', $funding->fresh())->assertOk();

        $this->assertSame($receipt, $funding->fresh()->receipt_number);
        // Un don + un seul cofinancement
        $this->assertSame(2, Funding::count());
        $this->assertSame(1, Funding::where('is_matching', true)->count());
        Notification::assertSentToTimes($citizen, DonationConfirmed::class, 1);
    }

    public function test_invalid_signature_is_rejected_with_400(): void
    {
        $this->donate($this->citizen(), $this->openProject(), '50');
        $funding = Funding::sole();
        $payload = json_encode(['id' => 'evt_1', 'object' => 'event', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => $funding->stripe_session_id]]]);

        $this->signedWebhook($payload, 'whsec_autre_secret')->assertStatus(400);
        $this->call('POST', route('stripe.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $payload)->assertStatus(400);

        $this->assertSame(FundingStatus::Pending, $funding->fresh()->status);
    }

    public function test_webhook_with_unexpected_amount_or_metadata_is_ignored(): void
    {
        $this->donate($this->citizen(), $this->openProject(), '50');
        $funding = Funding::sole();

        $this->webhook('checkout.session.completed', $funding, ['amount_total' => 1])->assertOk();
        $this->webhook('checkout.session.completed', $funding, ['metadata' => ['funding_id' => '999']])->assertOk();

        $this->assertSame(FundingStatus::Pending, $funding->fresh()->status);
    }

    public function test_expired_checkout_session_marks_the_donation_failed(): void
    {
        $project = $this->openProject();
        $this->donate($this->citizen(), $project, '600');
        $funding = Funding::sole();

        $this->webhook('checkout.session.expired', $funding, ['payment_status' => 'unpaid'])->assertOk();

        $this->assertSame(FundingStatus::Failed, $funding->fresh()->status);
        // Le montant réservé est libéré
        $this->assertSame(1000.0, $project->remainingAmount(reserved: true));
    }

    public function test_donation_is_refused_when_project_is_not_open(): void
    {
        $citizen = $this->citizen();

        foreach ([ProjectStatus::Funded, ProjectStatus::Failed, ProjectStatus::InProgress, ProjectStatus::Completed] as $status) {
            $this->donate($citizen, $this->openProject(['status' => $status]), '50')
                ->assertSessionHasErrors(['project' => "Ce projet n'accepte pas de contributions : il est « {$status->label()} »."]);
        }

        // Brouillon, projet masqué et collecte échue : refusés aussi
        $this->donate($citizen, $this->openProject(['status' => ProjectStatus::Draft, 'is_public' => false]), '50')->assertSessionHasErrors('project');
        $this->donate($citizen, $this->openProject(['is_public' => false]), '50')->assertSessionHasErrors('project');
        $expired = Project::factory()->open()->expired()->create();
        $this->donate($citizen, $expired, '50')
            ->assertSessionHasErrors(['project' => 'La collecte de ce projet est terminée depuis le '.$expired->end_date->format('d/m/Y').'.']);

        $this->assertDatabaseCount('fundings', 0);
    }

    public function test_donation_amount_limits_and_guest_access(): void
    {
        $citizen = $this->citizen();
        $project = $this->openProject(['target_budget' => 100000]);

        $this->post(route('projects.donate', $project), ['amount' => '50'])->assertRedirect(route('login'));
        $this->donate($citizen, $project, '4.99')->assertSessionHasErrors(['amount' => 'La contribution minimale est de 5 TND.']);
        $this->donate($citizen, $project, '10001')->assertSessionHasErrors(['amount' => 'La contribution ne peut pas dépasser 10 000 TND.']);

        // Le personnel ne donne pas en ligne (ProjectPolicy::donate)
        $this->actingAs(User::factory()->gestionnaire()->create())->post(route('projects.donate', $project), ['amount' => '50'])->assertForbidden();

        $this->assertDatabaseCount('fundings', 0);
    }

    public function test_simultaneous_donations_cannot_exceed_the_target(): void
    {
        $project = $this->openProject(['target_budget' => 1000]);
        Funding::factory()->for($project)->municipal()->create(['amount' => 300]);

        // Le premier don (en attente de paiement) réserve 600 DT : il n'en reste que 100
        $this->donate($this->citizen(), $project, '600')->assertRedirect();
        $this->donate($this->citizen(), $project, '500')
            ->assertSessionHasErrors(['project' => 'Il ne reste que 100 DT à collecter pour ce projet.']);
        $this->donate($this->citizen(), $project, '100')->assertRedirect();

        $this->assertSame(0.0, $project->remainingAmount(reserved: true));
        $this->assertSame(2, Funding::ofStatus(FundingStatus::Pending)->count());
    }

    public function test_payment_unavailable_without_stripe_keys(): void
    {
        config(['services.stripe.secret' => null]);
        $this->app->forgetInstance(StripeService::class);
        $this->app->bind(StripeService::class, fn () => new StripeService);

        $this->donate($this->citizen(), $this->openProject(), '50')
            ->assertSessionHasErrors(['project' => 'Le paiement en ligne n\'est pas encore configuré. Réessayez plus tard.']);
        $this->assertDatabaseCount('fundings', 0);
    }

    // ---------------------------------------------------------------- Statut et cofinancement

    public function test_project_becomes_funded_when_confirmed_total_reaches_target(): void
    {
        Notification::fake();
        config(['projects.matching.enabled' => false]);
        $citizen = $this->citizen();
        $project = $this->openProject(['target_budget' => 1000]);
        Funding::factory()->for($project)->municipal()->create(['amount' => 950]);

        $this->donate($citizen, $project, '50');
        $this->assertSame(ProjectStatus::Open, $project->fresh()->status);

        $this->webhook('checkout.session.completed', Funding::latest('id')->first());

        $this->assertSame(ProjectStatus::Funded, $project->fresh()->status);
        Notification::assertSentTo($citizen, ProjectFunded::class);
    }

    public function test_matching_funding_is_created_one_for_one_and_capped(): void
    {
        config(['projects.matching.cap_per_project' => 150]);
        $project = $this->openProject(['target_budget' => 10000]);
        $citizen = $this->citizen();

        $this->donate($citizen, $project, '100');
        $this->webhook('checkout.session.completed', Funding::latest('id')->first());
        $this->donate($citizen, $project, '100');
        $this->webhook('checkout.session.completed', Funding::where('status', FundingStatus::Pending->value)->sole());

        $matchings = Funding::where('is_matching', true)->orderBy('id')->get();
        $this->assertCount(2, $matchings);
        $this->assertSame('100.00', $matchings[0]->amount);
        // Plafond de 150 DT par projet : le second cofinancement est limité à 50 DT
        $this->assertSame('50.00', $matchings[1]->amount);
        $this->assertSame(FundingSource::Municipalite, $matchings[0]->source_type);
        $this->assertSame(FundingStatus::Confirmed, $matchings[0]->status);
        $this->assertNotNull($matchings[0]->matched_funding_id);
        $this->assertSame(350.0, $project->fresh()->collected_amount);

        // Plafond atteint : plus de cofinancement
        $this->donate($citizen, $project, '100');
        $this->webhook('checkout.session.completed', Funding::where('status', FundingStatus::Pending->value)->sole());
        $this->assertSame(2, Funding::where('is_matching', true)->count());
    }

    public function test_half_funded_milestone_is_notified_once(): void
    {
        Notification::fake();
        config(['projects.matching.enabled' => false]);
        $citizen = $this->citizen();
        $project = $this->openProject(['target_budget' => 1000]);
        Funding::factory()->for($project)->municipal()->create(['amount' => 450]);

        $this->donate($citizen, $project, '60');
        $this->webhook('checkout.session.completed', Funding::latest('id')->first());
        $this->donate($citizen, $project, '20');
        $this->webhook('checkout.session.completed', Funding::latest('id')->first());

        Notification::assertSentToTimes($citizen, ProjectMilestoneReached::class, 1);
        $this->assertNotNull($project->fresh()->milestone_notified_at);
    }

    // ---------------------------------------------------------------- Clôture et remboursements

    public function test_close_expired_marks_projects_failed_and_refunds_idempotently(): void
    {
        Notification::fake();
        $citizen = $this->citizen();
        $project = Project::factory()->open()->expired()->create(['target_budget' => 10000]);
        $donation = Funding::factory()->for($project)->fromCitizen($citizen)->confirmed()->create(['amount' => 80]);
        $matching = Funding::factory()->for($project)->matching()->create(['amount' => 80, 'matched_funding_id' => $donation->id]);
        $municipal = Funding::factory()->for($project)->municipal()->create(['amount' => 2000]);
        $reached = Project::factory()->open()->expired()->create(['target_budget' => 100]);
        Funding::factory()->for($reached)->municipal()->create(['amount' => 100]);
        $reached->update(['status' => ProjectStatus::Open]);

        $this->artisan('projects:close-expired')->assertSuccessful();

        $this->assertSame(ProjectStatus::Failed, $project->fresh()->status);
        $this->assertSame(FundingStatus::Refunded, $donation->fresh()->status);
        $this->assertSame('re_test_'.$donation->id, $donation->fresh()->stripe_refund_id);
        // Cofinancement et financement institutionnel : simplement « remboursés », sans appel Stripe
        $this->assertSame(FundingStatus::Refunded, $matching->fresh()->status);
        $this->assertSame(FundingStatus::Refunded, $municipal->fresh()->status);
        $this->assertSame([$donation->reference], $this->refunds);
        $this->assertSame(ProjectStatus::Funded, $reached->fresh()->status);
        Notification::assertSentTo($citizen, ProjectFailed::class);

        // Relancée : aucun nouveau remboursement
        $this->artisan('projects:close-expired')->assertSuccessful();
        $this->assertCount(1, $this->refunds);
    }

    public function test_payment_received_after_failure_is_refunded_immediately(): void
    {
        $citizen = $this->citizen();
        $project = $this->openProject();
        $this->donate($citizen, $project, '50');
        $funding = Funding::sole();
        $project->update(['status' => ProjectStatus::Failed]);

        $this->webhook('checkout.session.completed', $funding);

        $this->assertSame(FundingStatus::Refunded, $funding->fresh()->status);
    }

    // ---------------------------------------------------------------- Reçu PDF et vérification

    public function test_receipt_is_downloadable_by_its_donor_only(): void
    {
        $donor = $this->citizen();
        $funding = Funding::factory()->for($this->openProject())->fromCitizen($donor)->confirmed()->create(['amount' => 50]);

        $this->get(route('fundings.receipt', $funding))->assertRedirect(route('login'));
        $this->actingAs($donor)->get(route('fundings.receipt', $funding))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->citizen())->get(route('fundings.receipt', $funding))->assertForbidden();

        // Don en attente : pas encore de reçu
        $pending = Funding::factory()->fromCitizen($donor)->pending()->create();
        $this->actingAs($donor)->get(route('fundings.receipt', $pending))->assertForbidden();
    }

    public function test_signed_verification_url_is_valid_only_with_its_signature(): void
    {
        $project = $this->openProject(['title' => 'Réservoir de La Soukra']);
        $funding = Funding::factory()->for($project)->fromCitizen()->confirmed()->create(['amount' => 50, 'donor_name' => 'Sana Ben Ali']);
        $funding->update(['paid_at' => '2026-10-12 10:00:00']);

        $this->get(ReceiptController::verificationUrl($funding))
            ->assertOk()
            ->assertSee('Reçu authentique : 50 DT pour le projet Réservoir de La Soukra le 12/10')
            ->assertDontSee('Sana Ben Ali');

        $this->get(route('receipts.verify', $funding))->assertForbidden();
        $this->get(ReceiptController::verificationUrl($funding).'0')->assertForbidden();
    }

    // ---------------------------------------------------------------- Transparence et audit

    public function test_transparency_page_hides_personal_data(): void
    {
        $project = $this->openProject(['title' => 'Station de pompage de Raoued']);
        Funding::factory()->for($project)->fromCitizen()->confirmed()->create(['donor_name' => 'Mariem Gharbi', 'amount' => 70]);
        Funding::factory()->for($project)->fromCitizen()->confirmed()->anonymous()->create(['donor_name' => 'Karim Mejri', 'amount' => 90]);
        Funding::factory()->for($project)->fromCitizen()->pending()->create(['donor_name' => 'Salma Ayari']);

        $this->get(route('projects.transparency'))
            ->assertOk()
            ->assertSee('Station de pompage de Raoued')
            ->assertSee('Mariem G.')
            ->assertDontSee('Mariem Gharbi')
            ->assertSee('Anonyme')
            ->assertDontSee('Karim Mejri')
            ->assertDontSee('Salma');
    }

    public function test_project_and_funding_changes_are_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $project = $this->openProject();

        $this->actingAs($admin);
        $project->update(['target_budget' => 2000]);

        $activity = Activity::query()->where('subject_type', $project->getMorphClass())->latest('id')->first();
        $this->assertTrue($activity->causer->is($admin));
        $this->assertSame('updated', $activity->event);
        $this->assertSame('1000.00', $activity->properties['old']['target_budget']);
        $this->assertSame('2000.00', $activity->properties['attributes']['target_budget']);
    }
}
