<?php

namespace Database\Factories;

use App\Enums\WaterParameter;
use App\Models\Measurement;
use App\Models\WaterAnalysis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * L'unité et la conformité sont calculées par le modèle à partir du paramètre.
 *
 * @extends Factory<Measurement>
 */
class MeasurementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'water_analysis_id' => WaterAnalysis::factory(),
            'parameter' => fake()->randomElement(WaterParameter::cases()),
            // Valeur entre les seuils du paramètre (9 fois sur 10), sinon au-dessus du seuil maximal
            'value' => fn (array $attributes) => fake()->boolean(90)
                ? fake()->randomFloat(2, $attributes['parameter']->min() ?? 0, $attributes['parameter']->max())
                : $attributes['parameter']->max() + fake()->randomFloat(2, 0.5, 5),
        ];
    }

    /** Valeur dans les seuils réglementaires. */
    public function conforme(): static
    {
        return $this->state(['value' => fn (array $attributes) => fake()->randomFloat(2, $attributes['parameter']->min() ?? 0, $attributes['parameter']->max())]);
    }

    /** Valeur au-dessus du seuil maximal. */
    public function nonConforme(): static
    {
        return $this->state(['value' => fn (array $attributes) => $attributes['parameter']->max() + fake()->randomFloat(2, 0.5, 5)]);
    }

    /** Une mesure par paramètre (6 mesures) : un paramètre n'apparaît qu'une fois par analyse. */
    public function eachParameter(): static
    {
        return $this->forEachSequence(...array_map(fn (WaterParameter $parameter) => ['parameter' => $parameter], WaterParameter::cases()));
    }
}
