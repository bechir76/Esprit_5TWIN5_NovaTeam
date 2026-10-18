<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /** Mot de passe commun à tous les comptes générés : « password ». */
    protected static ?string $password;

    /**
     * Par défaut : un citoyen actif.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::Citoyen,
            'phone' => fake()->numerify('## ### ###'),
            'organization' => null,
            'address' => fake()->address(),
            'is_active' => true,
        ];
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }

    public function admin(): static
    {
        return $this->state(['role' => Role::Admin, 'organization' => fake()->randomElement(User::ORGANIZATIONS)]);
    }

    public function gestionnaire(): static
    {
        return $this->state(['role' => Role::Gestionnaire, 'organization' => fake()->randomElement(User::ORGANIZATIONS)]);
    }

    public function technicien(): static
    {
        return $this->state(['role' => Role::Technicien, 'organization' => fake()->randomElement(User::ORGANIZATIONS)]);
    }

    public function citoyen(): static
    {
        return $this->state(['role' => Role::Citoyen, 'organization' => null]);
    }

    public function suspended(): static
    {
        return $this->state(['is_active' => false]);
    }
}
