<?php

namespace Database\Factories;

use App\Enums\CapacityUnit;
use App\Enums\InfrastructureStatus;
use App\Enums\InfrastructureType;
use App\Models\Infrastructure;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Infrastructure>
 */
class InfrastructureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'zone_id' => Zone::factory(),
            // Code ouvrage au format ABC-ABC-000 (ex. RES-ARI-004)
            'code' => fake()->unique()->regexify('[A-Z]{3}-[A-Z]{3}-[0-9]{3}'),
            'name' => 'Ouvrage '.fake()->city(),
            'type' => fake()->randomElement(InfrastructureType::cases()),
            'material' => fake()->randomElement(Infrastructure::MATERIALS),
            'capacity' => fake()->randomFloat(2, 1, 10000),
            'capacity_unit' => fake()->randomElement(CapacityUnit::cases()),
            'commissioned_year' => fake()->numberBetween(1960, (int) now()->year),
            'nominal_pressure' => fake()->optional()->randomFloat(1, 2, 10),
            'status' => fake()->randomElement(InfrastructureStatus::cases()),
            'last_inspection_at' => fake()->dateTimeBetween('-18 months'),
            'latitude' => fake()->latitude(36.70, 36.95),
            'longitude' => fake()->longitude(10.05, 10.35),
            'description' => fake()->optional()->sentence(),
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
