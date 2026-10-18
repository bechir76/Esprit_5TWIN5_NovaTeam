<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Project;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertStatus(200);
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('home', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_registration_sends_a_french_verification_email(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'first_name' => 'Sana', 'last_name' => 'Ben Ali', 'email' => 'sana@example.tn',
            'password' => 'Secret123', 'password_confirmation' => 'Secret123', 'terms' => '1',
        ]);

        $user = User::firstWhere('email', 'sana@example.tn');
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class, fn (VerifyEmail $notification) => $notification->toMail($user)->subject === 'Vérifiez votre adresse e-mail AquaSecure');
    }

    public function test_unverified_user_cannot_report_or_contribute(): void
    {
        $user = User::factory()->citoyen()->unverified()->create();
        $project = Project::factory()->collecting()->create();

        $this->actingAs($user)->get(route('incidents.create'))->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->post(route('projects.fundings.store', $project), ['amount' => '50'])->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->get(route('projects.show', $project))->assertSee('Vérifiez votre adresse e-mail pour contribuer');
        $this->assertDatabaseCount('fundings', 0);

        // Une fois l'adresse vérifiée, le signalement est accessible
        $user->markEmailAsVerified();
        $this->actingAs($user)->get(route('incidents.create'))->assertOk();
    }

    public function test_changing_email_requires_a_new_verification(): void
    {
        Notification::fake();
        $user = User::factory()->citoyen()->create();

        $this->actingAs($user)->put(route('profile.update'), ['name' => $user->name, 'email' => 'nouvelle@example.tn'])
            ->assertSessionHasNoErrors();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
