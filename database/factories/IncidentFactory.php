<?php

namespace Database\Factories;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * La référence (INC-2026-0001) est générée par le modèle.
 *
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'type' => fake()->randomElement(IncidentType::cases()),
            'severity' => fake()->randomElement(IncidentSeverity::cases()),
            'status' => IncidentStatus::Signale,
            'address' => fake()->address(),
            'latitude' => fake()->latitude(36.70, 36.95),
            'longitude' => fake()->longitude(10.05, 10.35),
            'photo_path' => null,
            'infrastructure_id' => Infrastructure::factory(),
            'user_id' => User::factory()->citoyen(),
            'created_at' => fake()->dateTimeBetween('-6 months'),
        ];
    }

    public function reported(): static
    {
        return $this->state(['status' => IncidentStatus::Signale]);
    }

    public function qualified(): static
    {
        return $this->state(['status' => IncidentStatus::Qualifie]);
    }

    public function inProgress(): static
    {
        return $this->state(['status' => IncidentStatus::EnIntervention]);
    }

    /** Résolu : la date de résolution est renseignée. */
    public function resolved(): static
    {
        return $this->state(['status' => IncidentStatus::Resolu, 'resolved_at' => now()]);
    }

    public function rejected(): static
    {
        return $this->state(['status' => IncidentStatus::Rejete]);
    }

    public function critical(): static
    {
        return $this->state(['severity' => IncidentSeverity::Critique]);
    }

    public function fuite(): static
    {
        return $this->state(['type' => IncidentType::Fuite]);
    }

    public function contamination(): static
    {
        return $this->state(['type' => IncidentType::Contamination]);
    }

    public function coupure(): static
    {
        return $this->state(['type' => IncidentType::Coupure]);
    }

    public function withoutInfrastructure(): static
    {
        return $this->state(['infrastructure_id' => null]);
    }
}
