<?php

namespace Database\Factories;

use App\Enums\InterventionStatus;
use App\Models\Incident;
use App\Models\Intervention;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Intervention>
 */
class InterventionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'incident_id' => Incident::factory(),
            'title' => fake()->sentence(3),
            'team' => fake()->randomElement(Intervention::TEAMS),
            'technician_id' => User::factory()->technicien(),
            'scheduled_at' => fake()->dateTimeBetween('-1 month', '+2 weeks'),
            'estimated_hours' => fake()->randomFloat(1, 0.5, 8),
            'status' => InterventionStatus::Planifiee,
            'report' => null,
            'completed_at' => null,
        ];
    }

    public function planned(): static
    {
        return $this->state(['status' => InterventionStatus::Planifiee]);
    }

    public function inProgress(): static
    {
        return $this->state(['status' => InterventionStatus::EnCours]);
    }

    /** Terminée : passée, avec compte rendu et date de fin. */
    public function done(): static
    {
        return $this->state([
            'status' => InterventionStatus::Terminee,
            'scheduled_at' => fake()->dateTimeBetween('-1 month', '-1 day'),
            'report' => fake()->paragraph(),
            'completed_at' => now(),
        ]);
    }
}
