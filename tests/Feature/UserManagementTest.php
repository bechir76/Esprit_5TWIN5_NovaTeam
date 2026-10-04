<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------ Authentification front

    public function test_visitor_can_register_as_citizen(): void
    {
        $response = $this->post(route('register'), [
            'first_name' => 'Sana',
            'last_name' => 'Ben Ali',
            'email' => 'sana@example.tn',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'terms' => '1',
            'role' => 'admin', // ignoré : le rôle n'est pas choisi par le visiteur
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();
        $user = User::firstWhere('email', 'sana@example.tn');
        $this->assertSame('Sana Ben Ali', $user->name);
        $this->assertSame(Role::Citoyen, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_registration_validates_password_and_terms(): void
    {
        $this->from(route('register'))->post(route('register'), [
            'first_name' => 'Sana',
            'last_name' => 'Ben Ali',
            'email' => 'sana@example.tn',
            'password' => 'court',
            'password_confirmation' => 'autre',
        ])->assertRedirect(route('register'))->assertSessionHasErrors(['password', 'terms']);

        $this->assertGuest();
    }

    public function test_citizen_can_login_with_remember_me_and_logout(): void
    {
        $user = User::factory()->citoyen()->create();

        $response = $this->post(route('login'), ['email' => $user->email, 'password' => 'password', 'remember' => '1']);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'mauvais'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_suspended_user_cannot_login(): void
    {
        $user = User::factory()->suspended()->create();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('auth.suspended')]);

        $this->assertGuest();
    }

    public function test_suspended_user_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->suspended()->create();

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS; $i++) {
            $this->post(route('login'), ['email' => $user->email, 'password' => 'mauvais']);
        }

        // Même le bon mot de passe est refusé pendant le blocage
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertStringContainsString('Trop de tentatives', session('errors')->first('email'));
    }

    public function test_reset_link_is_sent_and_password_can_be_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->get(route('password.reset', ['token' => $notification->token, 'email' => $user->email]))->assertOk();

            $this->post(route('password.store'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'Nouveau123',
                'password_confirmation' => 'Nouveau123',
            ])->assertRedirect(route('login'))->assertSessionHasNoErrors();

            return true;
        });

        $this->assertTrue(Hash::check('Nouveau123', $user->fresh()->password));
    }

    public function test_reset_link_request_does_not_reveal_unknown_emails(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'inconnu@example.tn'])
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    // ------------------------------------------------------------ Back office : accès

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    public function test_citizen_cannot_access_back_office(): void
    {
        $this->actingAs(User::factory()->citoyen()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_citizen_cannot_login_on_admin_login(): void
    {
        $user = User::factory()->citoyen()->create();

        $this->post(route('admin.login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('auth.staff_only')]);

        $this->assertGuest();
    }

    public function test_staff_login_redirects_to_dashboard(): void
    {
        $user = User::factory()->technicien()->create();

        $this->post(route('admin.login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('admin.dashboard'))->assertOk()->assertSee($user->name);
    }

    public function test_only_admin_can_manage_users(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();

        $this->actingAs($gestionnaire)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($gestionnaire)->get(route('admin.roles.index'))->assertForbidden();
        $this->actingAs($gestionnaire)->post(route('admin.users.store'), [])->assertForbidden();
    }

    // ------------------------------------------------------------ CRUD utilisateurs

    public function test_admin_can_list_search_and_filter_users(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Karim Trabelsi']);
        User::factory()->citoyen()->create(['name' => 'Mehdi Gharbi']);
        User::factory()->technicien()->suspended()->create(['name' => 'Walid Saidi']);

        $this->actingAs($admin)->get(route('admin.users.index', ['q' => 'gharbi']))
            ->assertOk()->assertSee('Mehdi Gharbi')->assertDontSee('Walid Saidi');

        $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'technicien', 'status' => 'suspended']))
            ->assertOk()->assertSee('Walid Saidi')->assertDontSee('Mehdi Gharbi');
    }

    public function test_users_index_is_paginated(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory(12)->create();

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'citoyen']));

        $response->assertOk()->assertSee('Affichage 1 à 10 sur 12');
        $this->assertSame(10, $response->viewData('users')->count());
        $response->assertSee('role=citoyen&amp;page=2', false);
    }

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Amira Haddad',
            'email' => 'amira@regie.tn',
            'phone' => '71 234 567',
            'organization' => 'Régie des eaux d\'Ariana',
            'role' => 'gestionnaire',
            'is_active' => '1',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ])->assertRedirect(route('admin.users.index'))->assertSessionHas('status');

        $user = User::firstWhere('email', 'amira@regie.tn');
        $this->assertSame(Role::Gestionnaire, $user->role);
        $this->assertTrue(Hash::check('Secret123', $user->password));
    }

    public function test_user_creation_validates_input(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->from(route('admin.users.create'))->post(route('admin.users.store'), [
            'name' => '',
            'email' => $admin->email,
            'role' => 'superadmin',
            'password' => 'Secret123',
            'password_confirmation' => 'Different123',
        ])->assertRedirect(route('admin.users.create'))
            ->assertSessionHasErrors(['name', 'email', 'role', 'password']);

        $this->assertSame(1, User::count());
    }

    public function test_admin_can_update_user_without_changing_password(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->citoyen()->create();
        $hash = $user->password;

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => 'Nouveau Nom',
            'email' => $user->email, // e-mail inchangé : la règle unique ignore l'utilisateur
            'role' => 'technicien',
            'is_active' => '0',
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect(route('admin.users.index'))->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Nouveau Nom', $user->name);
        $this->assertSame(Role::Technicien, $user->role);
        $this->assertFalse($user->is_active);
        $this->assertSame($hash, $user->password);
    }

    public function test_admin_can_suspend_reactivate_and_delete_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->patch(route('admin.users.toggle-status', $user))->assertSessionHas('status');
        $this->assertFalse($user->fresh()->is_active);

        $this->actingAs($admin)->patch(route('admin.users.toggle-status', $user));
        $this->assertTrue($user->fresh()->is_active);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $user))->assertRedirect(route('admin.users.index'));
        $this->assertModelMissing($user);
    }

    public function test_admin_cannot_suspend_demote_or_delete_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch(route('admin.users.toggle-status', $admin))->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertSessionHas('error');
        $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'citoyen',
            'is_active' => '0',
        ])->assertSessionHasErrors(['role', 'is_active']);

        $admin->refresh();
        $this->assertTrue($admin->is_active);
        $this->assertTrue($admin->isAdmin());
    }

    public function test_roles_page_shows_user_count_per_role(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory(3)->technicien()->create();

        $roles = $this->actingAs($admin)->get(route('admin.roles.index'))->assertOk()->viewData('roles');

        $this->assertSame(
            ['admin' => 1, 'gestionnaire' => 0, 'technicien' => 3, 'citoyen' => 0],
            $roles->mapWithKeys(fn ($row) => [$row['role']->value => $row['count']])->all(),
        );
    }

    // ------------------------------------------------------------ Profil

    public function test_user_can_update_profile_and_password(): void
    {
        $user = User::factory()->citoyen()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Sana Ben Ali',
            'email' => 'sana.new@example.tn',
            'phone' => '22 451 870',
            'address' => '12 rue Ibn Sina, Ariana',
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertSame('12 rue Ibn Sina, Ariana', $user->fresh()->address);

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'mauvais',
            'password' => 'Nouveau123',
            'password_confirmation' => 'Nouveau123',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'Nouveau123',
            'password_confirmation' => 'Nouveau123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Nouveau123', $user->fresh()->password));
    }

    public function test_staff_can_update_admin_profile(): void
    {
        $user = User::factory()->gestionnaire()->create();

        $this->actingAs($user)->get(route('admin.profile.edit'))->assertOk()->assertSee($user->email);

        $this->actingAs($user)->put(route('admin.profile.update'), [
            'name' => 'Amira Haddad',
            'email' => $user->email,
            'phone' => '71 000 000',
        ])->assertSessionHasNoErrors();

        $this->assertSame('71 000 000', $user->fresh()->phone);
    }
}
