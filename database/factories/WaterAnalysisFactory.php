<?php

namespace Database\Factories;

use App\Enums\AnalysisStatus;
use App\Models\Infrastructure;
use App\Models\User;
use App\Models\WaterAnalysis;
use Database\Factories\Concerns\UsesTunisiaProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * La référence (ANA-2026-0001) est générée par le modèle ;
 * le statut est recalculé à chaque enregistrement de mesure.
 *
 * @extends Factory<WaterAnalysis>
 */
class WaterAnalysisFactory extends Factory
{
    use UsesTunisiaProvider;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'infrastructure_id' => Infrastructure::factory(),
            'sampled_at' => fake()->dateTimeBetween('-6 months'),
            'sampling_point' => fake()->samplingPoint(),
            'laboratory' => fake()->tunisianLaboratory(),
            'sampled_by' => User::factory()->technicien(),
            'status' => fake()->randomElement(AnalysisStatus::cases()),
            'notes' => null,
        ];
    }

    public function enAttente(): static
    {
        return $this->state(['status' => AnalysisStatus::EnAttente]);
    }
}
