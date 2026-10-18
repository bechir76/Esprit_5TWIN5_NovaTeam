<?php

namespace Database\Factories;

use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city(),
            'municipality' => fake()->city(),
            'governorate' => fake()->randomElement(array_keys(Zone::GOVERNORATES)),
            'population_served' => fake()->numberBetween(8000, 250000),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
