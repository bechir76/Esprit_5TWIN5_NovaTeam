<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Database\Factories\Concerns\UsesTunisiaProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    use UsesTunisiaProvider;

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
            'name' => fake()->tunisianName(),
            // E-mail dérivé du nom (« sana.benali27@gmail.com »)
            'email' => fn (array $attributes) => fake()->unique()->tunisianEmail($attributes['name']),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::Citoyen,
            'phone' => fake()->tunisianPhone(),
            'organization' => null,
            'address' => fake()->tunisianAddress(),
            'is_active' => true,
        ];
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
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
        return $this->state(['role' => Role::Citoyen, 'organization' => null]);
    }

    public function suspended(): static
    {
        return $this->state(['is_active' => false]);
    }

    /** Personnel : rôle, organisation et e-mail professionnel en @aquasecure.tn. */
    protected function staff(Role $role): static
    {
        return $this->state([
            'role' => $role,
            'organization' => fake()->randomElement(User::ORGANIZATIONS),
            'email' => fn (array $attributes) => fake()->unique()->tunisianStaffEmail($attributes['name']),
        ]);
    }
}
