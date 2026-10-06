<?php

namespace Database\Factories;

use App\Enums\IncidentType;
use App\Enums\InterventionStatus;
use App\Models\Incident;
use App\Models\Intervention;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Interventions sur un incident qualifié, confiées à un technicien.
 * Avec ->recycle($techniciens), la factory réutilise les techniciens existants.
 *
 * @extends Factory<Intervention>
 */
class InterventionFactory extends Factory
{
    /** Intitulés par type d'incident. */
    public const TITLES = [
        'fuite' => ['Recherche de fuite au corrélateur', 'Réparation de la conduite', 'Remplacement d\'un tronçon de conduite', 'Remplacement de la vanne d\'isolement', 'Remplacement du compteur', 'Réfection de la bouche à clé'],
        'contamination' => ['Prélèvements qualité sur 3 points', 'Purge du réseau secondaire', 'Rechloration du réservoir', 'Contrôle du poste de chloration'],
        'coupure' => ['Diagnostic de la station de pompage', 'Réglage du surpresseur', 'Remise en service du réseau', 'Remplacement de la pompe n° 2'],
    ];

    /** Comptes rendus d'intervention terminée. */
    public const REPORTS = [
        'Intervention réalisée, réseau remis en eau et pression contrôlée à 3 bar.',
        'Tronçon endommagé remplacé, essai d\'étanchéité concluant. Chaussée à reprendre par la municipalité.',
        'Prélèvements conformes après purge ; chlore résiduel mesuré à 0,35 mg/L.',
        'Pièce défectueuse remplacée, aucun écoulement constaté après deux heures d\'observation.',
        'Contrôle sur place : aucune anomalie constatée sur le réseau public.',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $faker = fake('fr_FR');

        return [
            'incident_id' => Incident::factory()->qualified(),
            'title' => fn (array $attributes) => $faker->randomElement(self::TITLES[self::incidentTypeOf($attributes)->value]),
            'team' => $faker->randomElement(Intervention::TEAMS),
            'technician_id' => User::factory()->technicien(),
            // Prévue quelques heures à deux jours après le signalement
            'scheduled_at' => fn (array $attributes) => Carbon::make(Incident::find($attributes['incident_id'])?->created_at ?? now())
                ->addHours($faker->numberBetween(2, 48))
                ->setMinutes($faker->randomElement([0, 15, 30, 45])),
            'estimated_hours' => $faker->randomElement([1, 1.5, 2, 3, 4, 6, 8]),
            'status' => InterventionStatus::Planifiee,
            'report' => null,
            'completed_at' => null,
        ];
    }

    // ------------------------------------------------------------ États

    public function planned(): static
    {
        return $this->state(['status' => InterventionStatus::Planifiee]);
    }

    public function inProgress(): static
    {
        return $this->state(['status' => InterventionStatus::EnCours]);
    }

    /** Terminée : compte rendu et date de fin (durée estimée après le début prévu). */
    public function done(): static
    {
        return $this->state(fn () => [
            'status' => InterventionStatus::Terminee,
            'report' => fake('fr_FR')->randomElement(self::REPORTS),
            'completed_at' => fn (array $attributes) => Carbon::make($attributes['scheduled_at'])
                ->addMinutes((int) round((float) $attributes['estimated_hours'] * 60))
                ->min(now()),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => InterventionStatus::Annulee,
            'report' => 'Intervention annulée : doublon d\'une intervention déjà programmée.',
        ]);
    }

    public function withoutTechnician(): static
    {
        return $this->state(['technician_id' => null]);
    }

    // ------------------------------------------------------------ Helpers

    /** @param array<string, mixed> $attributes */
    protected static function incidentTypeOf(array $attributes): IncidentType
    {
        return Incident::find($attributes['incident_id'])?->type ?? IncidentType::Fuite;
    }
}
