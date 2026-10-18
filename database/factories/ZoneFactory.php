<?php

namespace Database\Factories;

use App\Models\Zone;
use Database\Factories\Concerns\UsesTunisiaProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    use UsesTunisiaProvider;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'municipality' => fake()->tunisianCommune(),
            // Nom et gouvernorat cohérents avec la commune (« Ariana Nord », gouvernorat Ariana)
            'name' => fn (array $attributes) => fake()->unique()->tunisianZoneName($attributes['municipality']),
            'governorate' => fn (array $attributes) => fake()->tunisianGovernorate($attributes['municipality']),
            'population_served' => fake()->numberBetween(5000, 150000),
            'description' => null,
        ];
    }
}
