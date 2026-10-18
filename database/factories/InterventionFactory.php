<?php

namespace Database\Factories;

use App\Enums\InterventionStatus;
use App\Models\Incident;
use App\Models\Intervention;
use App\Models\User;
use Database\Factories\Concerns\UsesTunisiaProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Intervention>
 */
class InterventionFactory extends Factory
{
    use UsesTunisiaProvider;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'incident_id' => Incident::factory(),
            'title' => fake()->interventionTitle(),
            // Équipes de la liste du modèle (« Équipe réseau · Ariana »…), acceptées par le formulaire
            'team' => fake()->randomElement(Intervention::TEAMS),
            'technician_id' => User::factory()->technicien(),
            'scheduled_at' => fake()->dateTimeBetween('-1 month', '+2 weeks'),
            'estimated_hours' => fake()->randomElement([1, 1.5, 2, 3, 4, 6, 8]),
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
        return $this->state(fn () => [
            'status' => InterventionStatus::Terminee,
            'scheduled_at' => fake()->dateTimeBetween('-1 month', '-1 day'),
            'report' => fake()->interventionReport(),
            'completed_at' => now(),
        ]);
    }
}
