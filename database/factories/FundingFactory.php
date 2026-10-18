<?php

namespace Database\Factories;

use App\Enums\FundingSource;
use App\Models\Funding;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * La référence (FIN-2026-0001) est générée par le modèle.
 *
 * @extends Factory<Funding>
 */
class FundingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'source_type' => fake()->randomElement([FundingSource::Municipalite, FundingSource::Association, FundingSource::Entreprise]),
            'donor_name' => fake()->company(),
            'user_id' => null,
            'amount' => fake()->randomFloat(2, 500, 20000),
            'funded_at' => fake()->dateTimeBetween('-2 months'),
            'note' => fake()->optional()->sentence(),
        ];
    }

    public function municipal(): static
    {
        return $this->state(['source_type' => FundingSource::Municipalite]);
    }

    public function association(): static
    {
        return $this->state(['source_type' => FundingSource::Association]);
    }

    public function company(): static
    {
        return $this->state(['source_type' => FundingSource::Entreprise]);
    }

    /** Contribution d'un citoyen (5 à 500 TND). */
    public function fromCitizen(?User $user = null): static
    {
        return $this->state([
            'source_type' => FundingSource::Citoyen,
            'user_id' => $user ?? User::factory()->citoyen(),
            'donor_name' => $user?->name ?? fake()->name(),
            'amount' => fake()->randomFloat(2, 5, 500),
        ]);
    }
}
