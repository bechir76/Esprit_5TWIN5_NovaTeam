<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'Nouveau123',
                'password_confirmation' => 'Nouveau123',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('Nouveau123', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'Nouveau123',
                'password_confirmation' => 'Nouveau123',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('profile.edit'));
    }

    public function test_staff_can_update_password_from_the_back_office(): void
    {
        $user = User::factory()->technicien()->create();

        $this->actingAs($user)
            ->from(route('admin.profile.edit'))
            ->put(route('admin.password.update'), [
                'current_password' => 'password',
                'password' => 'Nouveau123',
                'password_confirmation' => 'Nouveau123',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.profile.edit'));

        $this->assertTrue(Hash::check('Nouveau123', $user->refresh()->password));
    }
}
