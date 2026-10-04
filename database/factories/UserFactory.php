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
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /** Communes du Grand Tunis utilisées pour les adresses. */
    protected const COMMUNES = ['Ariana', 'Tunis', 'Ben Arous', 'La Manouba', 'La Soukra', 'Raoued', 'Mégrine'];

    /**
     * Define the model's default state: un citoyen actif.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $faker = fake('fr_FR');

        return [
            'name' => $faker->firstName().' '.$faker->lastName(),
            'email' => $faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::Citoyen,
            'phone' => $faker->numerify($faker->randomElement(['2# ### ###', '5# ### ###', '9# ### ###'])),
            'organization' => null,
            'address' => $faker->streetAddress().', '.$faker->randomElement(self::COMMUNES),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->staff(Role::Admin);
    }

    public function gestionnaire(): static
    {
        return $this->staff(Role::Gestionnaire);
    }

    public function technicien(): static
    {
        return $this->staff(Role::Technicien);
    }

    public function citoyen(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Citoyen,
            'organization' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /** Personnel : rôle, organisation et numéro fixe professionnel. */
    protected function staff(Role $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
            'organization' => fake('fr_FR')->randomElement(User::ORGANIZATIONS),
            'phone' => fake('fr_FR')->numerify('71 ### ###'),
        ]);
    }
}
