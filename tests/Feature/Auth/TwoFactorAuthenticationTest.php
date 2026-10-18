<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /** Utilisateur avec la 2FA activée et confirmée. */
    private function userWithTwoFactor(array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'two_factor_secret' => app(Google2FA::class)->generateSecretKey(),
            'two_factor_recovery_codes' => ['AAAAA-BBBBB', 'CCCCC-DDDDD'],
            'two_factor_confirmed_at' => now(),
        ]);
    }

    private function currentCode(User $user): string
    {
        return app(Google2FA::class)->getCurrentOtp($user->two_factor_secret);
    }

    public function test_user_can_enable_and_confirm_two_factor_authentication(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('profile.edit'))->post(route('two-factor.enable'))
            ->assertRedirect(route('profile.edit').'#deux-facteurs');

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertCount(8, $user->two_factor_recovery_codes);

        // QR code (SVG) affiché en attendant la confirmation
        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('<svg', false)->assertSee("Confirmer l'activation", false);

        $this->actingAs($user)->post(route('two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());

        $this->actingAs($user)->post(route('two-factor.confirm'), ['code' => $this->currentCode($user)])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('show_recovery_codes', true);
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_secret_and_recovery_codes_are_encrypted_in_database(): void
    {
        $user = $this->userWithTwoFactor();

        $raw = \DB::table('users')->where('id', $user->id)->first();
        $this->assertNotSame($user->two_factor_secret, $raw->two_factor_secret);
        $this->assertStringNotContainsString('AAAAA-BBBBB', $raw->two_factor_recovery_codes);
    }

    public function test_login_requires_the_totp_code_when_two_factor_is_enabled(): void
    {
        $user = $this->userWithTwoFactor();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.login'));
        $this->assertGuest();

        $this->get(route('two-factor.login'))->assertOk()->assertSee('Double authentification');

        $this->post(route('two-factor.login.store'), ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post(route('two-factor.login.store'), ['code' => $this->currentCode($user)])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_recovery_code_logs_in_once(): void
    {
        $user = $this->userWithTwoFactor();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
        $this->post(route('two-factor.login.store'), ['recovery_code' => 'aaaaa-bbbbb'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(['CCCCC-DDDDD'], $user->fresh()->two_factor_recovery_codes);

        // Le même code ne peut pas resservir
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
        $this->post(route('two-factor.login.store'), ['recovery_code' => 'AAAAA-BBBBB'])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_staff_goes_back_to_the_dashboard_after_the_challenge(): void
    {
        $user = $this->userWithTwoFactor(['role' => 'gestionnaire']);

        $this->post(route('admin.login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.login'));
        $this->post(route('two-factor.login.store'), ['code' => $this->currentCode($user)])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_challenge_is_rate_limited_and_requires_a_pending_login(): void
    {
        $this->get(route('two-factor.login'))->assertRedirect(route('login'));

        $user = $this->userWithTwoFactor();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('two-factor.login.store'), ['code' => '000000']);
        }

        // Même le bon code est refusé pendant le blocage
        $this->post(route('two-factor.login.store'), ['code' => $this->currentCode($user)])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_user_can_regenerate_recovery_codes_and_disable_with_password(): void
    {
        $user = $this->userWithTwoFactor();

        $this->actingAs($user)->post(route('two-factor.recovery-codes'))->assertSessionHas('show_recovery_codes', true);
        $this->assertNotContains('AAAAA-BBBBB', $user->fresh()->two_factor_recovery_codes);

        $this->actingAs($user)->delete(route('two-factor.disable'), ['password' => 'mauvais'])
            ->assertSessionHasErrorsIn('twoFactor', 'password');
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->actingAs($user)->delete(route('two-factor.disable'), ['password' => 'password'])->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertNull($user->two_factor_secret);

        // Connexion de nouveau sans code
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_two_factor_management_requires_authentication(): void
    {
        $this->post(route('two-factor.enable'))->assertRedirect(route('login'));
        $this->delete(route('two-factor.disable'))->assertRedirect(route('login'));
    }
}
