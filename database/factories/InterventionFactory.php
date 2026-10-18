<?php

namespace Database\Factories;

use App\Enums\InterventionStatus;
use App\Models\Incident;
use App\Models\Intervention;
use App\Models\User;
use Carbon\Carbon;
use Database\Factories\Concerns\UsesTunisiaProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;

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

    /** Terminée : passée, avec compte rendu ; fin après la durée estimée (± 1 h). */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $start = Carbon::instance(fake()->dateTimeBetween('-1 month', '-2 days'));

            return [
                'status' => InterventionStatus::Terminee,
                'scheduled_at' => $start,
                'report' => fake()->interventionReport(),
                'completed_at' => $start->copy()->addMinutes((int) round(((float) $attributes['estimated_hours'] + fake()->randomFloat(1, -0.5, 1)) * 60)),
            ];
        });
    }

    public function cancelled(): static
    {
        return $this->state(['status' => InterventionStatus::Annulee]);
    }

    /**
     * Planning de démonstration à partir du jour $fromDay (0 = aujourd'hui) : chaque équipe a une
     * intervention par demi-journée (8 h ou 13 h, 3 h maximum), donc jamais deux interventions de la
     * même équipe qui se chevauchent, alors que des équipes différentes travaillent en même temps.
     */
    public function planning(int $fromDay = 0): static
    {
        $teams = count(Intervention::TEAMS);

        return $this->state(new Sequence(fn (Sequence $sequence) => [
            'team' => Intervention::TEAMS[$sequence->index % $teams],
            'scheduled_at' => now()->startOfDay()
                ->addDays($fromDay + intdiv(intdiv($sequence->index, $teams), 2))
                ->setTime(intdiv($sequence->index, $teams) % 2 === 0 ? 8 : 13, 0),
            'estimated_hours' => fake()->randomElement([1, 1.5, 2, 3]),
        ]));
    }
}
