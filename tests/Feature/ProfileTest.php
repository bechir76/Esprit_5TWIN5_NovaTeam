<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk()->assertSee($user->email)->assertSee('Supprimer mon compte');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->citoyen()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->citoyen()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }

    public function test_staff_cannot_delete_their_own_account_from_the_profile(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'password');

        $this->assertNotNull($user->fresh());
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_upload_replace_and_remove_a_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->citoyen()->create();
        $data = ['name' => $user->name, 'email' => $user->email];

        $this->actingAs($user)->put(route('profile.update'), $data + ['avatar' => UploadedFile::fake()->image('moi.jpg')])
            ->assertSessionHasNoErrors();
        $first = $user->fresh()->avatar_path;
        $this->assertStringStartsWith('avatars/', $first);
        Storage::disk('public')->assertExists($first);
        $this->actingAs($user)->get(route('profile.edit'))->assertSee($user->fresh()->avatarUrl());

        // Remplacement : l'ancien fichier est supprimé
        $this->actingAs($user)->put(route('profile.update'), $data + ['avatar' => UploadedFile::fake()->image('moi2.png')]);
        Storage::disk('public')->assertMissing($first);

        // Retrait
        $this->actingAs($user)->put(route('profile.update'), $data + ['remove_avatar' => '1']);
        $this->assertNull($user->fresh()->avatar_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_profile_photo_is_validated_and_deleted_with_the_account(): void
    {
        Storage::fake('public');
        $user = User::factory()->citoyen()->create();

        $this->actingAs($user)->put(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'avatar' => UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf')])
            ->assertSessionHasErrors(['avatar' => 'Le fichier doit être une image.']);

        $this->actingAs($user)->put(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'avatar' => UploadedFile::fake()->image('moi.jpg')]);
        $path = $user->fresh()->avatar_path;

        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password']);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_staff_can_upload_a_photo_from_the_back_office_profile(): void
    {
        Storage::fake('public');
        $user = User::factory()->gestionnaire()->create();

        $this->actingAs($user)->put(route('admin.profile.update'), ['name' => $user->name, 'email' => $user->email, 'avatar' => UploadedFile::fake()->image('moi.jpg')])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)->get(route('admin.dashboard'))->assertSee($user->fresh()->avatarUrl());
    }
}
