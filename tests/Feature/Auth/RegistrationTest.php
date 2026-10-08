<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertStatus(200)->assertSee('Créer mon compte');
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post(route('register'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'phone' => '22 451 870',
            'address' => '12 rue Ibn Sina, Ariana',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'terms' => '1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('home', absolute: false));

        $user = User::firstWhere('email', 'test@example.com');
        $this->assertSame(Role::Citoyen, $user->role);
        $this->assertSame('22 451 870', $user->phone);
        $this->assertSame('12 rue Ibn Sina, Ariana', $user->address);
    }
}
