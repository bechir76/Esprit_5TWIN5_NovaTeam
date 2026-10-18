<?php

namespace Database\Factories;

use App\Enums\FundingSource;
use App\Models\Funding;
use App\Models\Project;
use App\Models\User;
use Database\Factories\Concerns\UsesTunisiaProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * La référence (FIN-2026-0001) est générée par le modèle.
 *
 * @extends Factory<Funding>
 */
class FundingFactory extends Factory
{
    use UsesTunisiaProvider;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'source_type' => fake()->randomElement([FundingSource::Municipalite, FundingSource::Association, FundingSource::Entreprise]),
            // Financeur et montant en DT selon la source (« Municipalité de La Soukra », 35 000 DT)
            'donor_name' => fn (array $attributes) => fake()->tunisianDonor($attributes['source_type']),
            'amount' => fn (array $attributes) => fake()->fundingAmount($attributes['source_type']),
            'user_id' => null,
            'funded_at' => fake()->dateTimeBetween('-2 months'),
            'note' => null,
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

    /** Contribution d'un citoyen (petit don en DT). */
    public function fromCitizen(?User $user = null): static
    {
        return $this->state([
            'source_type' => FundingSource::Citoyen,
            'user_id' => $user ?? User::factory()->citoyen(),
            'donor_name' => $user?->name ?? fake()->tunisianName(),
        ]);
    }
}
