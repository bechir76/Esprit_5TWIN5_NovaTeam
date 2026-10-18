<?php

namespace Database\Factories;

use App\Enums\InfrastructureStatus;
use App\Enums\InfrastructureType;
use App\Models\Infrastructure;
use App\Models\Zone;
use Database\Factories\Concerns\UsesTunisiaProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Infrastructure>
 */
class InfrastructureFactory extends Factory
{
    use UsesTunisiaProvider;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$latitude, $longitude] = fake()->tunisianCoordinates();

        return [
            'zone_id' => Zone::factory(),
            'type' => fake()->randomElement(InfrastructureType::cases()),
            // Code, nom, capacité et unité cohérents avec le type (RES-ARI-104, « Réservoir Chotrana Haut », m³)
            'code' => fn (array $attributes) => fake()->unique()->infrastructureCode($attributes['type']),
            'name' => fn (array $attributes) => fake()->unique()->tunisianInfrastructureName($attributes['type']),
            'capacity' => fn (array $attributes) => fake()->infrastructureCapacity($attributes['type']),
            'capacity_unit' => fn (array $attributes) => InfrastructureType::from($attributes['type'] instanceof InfrastructureType ? $attributes['type']->value : $attributes['type'])->defaultUnit(),
            'material' => fake()->randomElement(Infrastructure::MATERIALS),
            'commissioned_year' => fake()->numberBetween(1965, (int) now()->year),
            'nominal_pressure' => fake()->optional()->randomFloat(1, 2, 10),
            'status' => fake()->randomElement(InfrastructureStatus::cases()),
            'last_inspection_at' => fake()->dateTimeBetween('-1 year'),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'description' => null,
            'is_public' => true,
        ];
    }

    public function good(): static
    {
        return $this->state(['status' => InfrastructureStatus::Bon]);
    }

    public function toWatch(): static
    {
        return $this->state(['status' => InfrastructureStatus::ASurveiller]);
    }

    public function critical(): static
    {
        return $this->state(['status' => InfrastructureStatus::Critique]);
    }

    public function reservoir(): static
    {
        return $this->state(['type' => InfrastructureType::Reservoir]);
    }

    public function conduite(): static
    {
        return $this->state(['type' => InfrastructureType::Conduite]);
    }

    public function forage(): static
    {
        return $this->state(['type' => InfrastructureType::Forage]);
    }

    /** Masquée sur la carte publique. */
    public function hidden(): static
    {
        return $this->state(['is_public' => false]);
    }

    public function withoutCoordinates(): static
    {
        return $this->state(['latitude' => null, 'longitude' => null]);
    }
}
