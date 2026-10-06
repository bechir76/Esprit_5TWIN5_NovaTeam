<?php

namespace Database\Factories;

use App\Enums\WaterParameter;
use App\Models\Measurement;
use App\Models\WaterAnalysis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Measurement>
 */
class MeasurementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $param = fake()->randomElement(WaterParameter::cases());

        return [
            'water_analysis_id' => WaterAnalysis::factory(),
            'parameter'         => $param,
            'value'             => $this->valueFor($param, compliant: true),
            // unit et is_compliant sont calculés automatiquement par le hook saving()
        ];
    }

    // ---------------------------------------------------------------- Helpers

    /** Valeur dans les seuils (conforme) ou hors seuils (non conforme). */
    protected function valueFor(WaterParameter $param, bool $compliant): float
    {
        $faker = fake('fr_FR');

        return match ($param) {
            WaterParameter::Chlore       => $compliant ? $faker->randomFloat(2, 0.1, 0.5)  : $faker->randomFloat(2, 0.0, 0.09),
            WaterParameter::Ph           => $compliant ? $faker->randomFloat(1, 6.5, 8.5)  : $faker->randomFloat(1, 9.0, 10.0),
            WaterParameter::Turbidite    => $compliant ? $faker->randomFloat(2, 0.1, 1.0)  : $faker->randomFloat(1, 1.5, 5.0),
            WaterParameter::Nitrate      => $compliant ? $faker->randomFloat(1, 5.0, 49.9) : $faker->randomFloat(1, 51.0, 80.0),
            WaterParameter::Conductivite => $compliant ? $faker->numberBetween(200, 2500)   : $faker->numberBetween(2600, 3500),
            WaterParameter::Ecoli        => $compliant ? 0.0                                : $faker->numberBetween(1, 20),
        };
    }

    public function compliant(WaterParameter $param): static
    {
        return $this->state(fn () => [
            'parameter' => $param,
            'value'     => $this->valueFor($param, compliant: true),
        ]);
    }

    public function nonCompliant(WaterParameter $param): static
    {
        return $this->state(fn () => [
            'parameter' => $param,
            'value'     => $this->valueFor($param, compliant: false),
        ]);
    }
}
