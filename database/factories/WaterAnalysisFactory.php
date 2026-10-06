<?php

namespace Database\Factories;

use App\Enums\AnalysisStatus;
use App\Models\Infrastructure;
use App\Models\User;
use App\Models\WaterAnalysis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaterAnalysis>
 */
class WaterAnalysisFactory extends Factory
{
    protected const SAMPLING_POINTS = [
        'Sortie réservoir',
        'Entrée réseau distribution',
        'Robinet école Ibn Khaldoun',
        'Robinet hôpital Charles Nicolle',
        'Borne fontaine place de la République',
        'Sortie station de traitement',
        'Robinet immeuble Cité El Khadra',
        'Robinet cité ouvrière Den Den',
        'Prise réseau Ariana Centre',
        'Sortie forage',
        'Réseau secondaire Ettadhamen',
        'Poste de chloration aval',
    ];

    protected const LABS = [
        'Laboratoire central SONEDE',
        'Laboratoire régional Ariana',
        'Lab. Environnement & Eaux',
        'Institut National de la Santé',
        'Laboratoire régional Ben Arous',
        'Centre Technique des Eaux',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $faker = fake('fr_FR');

        return [
            // La référence est générée par le hook `creating` du modèle si elle est absente.
            // On la laisse vide ici pour que chaque INSERT produise une valeur séquentielle unique.
            'infrastructure_id' => Infrastructure::factory(),
            'sampled_at'        => $faker->dateTimeBetween('-6 months', 'now'),
            'sampling_point'    => $faker->randomElement(self::SAMPLING_POINTS),
            'laboratory'        => $faker->randomElement(self::LABS),
            'sampled_by'        => function () use ($faker) {
                $id = User::whereIn('role', ['technicien', 'gestionnaire', 'admin'])
                    ->inRandomOrder()
                    ->value('id');

                return $faker->optional(0.75)->passthrough($id);
            },
            'status'            => $faker->randomElement([
                AnalysisStatus::EnAttente,
                AnalysisStatus::Validee, AnalysisStatus::Validee,
                AnalysisStatus::NonConforme,
            ]),
            'notes'             => $faker->optional(0.4)->randomElement([
                'Prélèvement réalisé en période de faible consommation.',
                'Légère odeur de chlore signalée par riverains.',
                'Après travaux sur conduite DN 300.',
                'Contrôle de routine — aucune anomalie visuelle.',
                'Suite à signalement citoyen sur la qualité de l\'eau.',
            ]),
        ];
    }

    // ---------------------------------------------------------------- États

    public function enAttente(): static
    {
        return $this->state(['status' => AnalysisStatus::EnAttente]);
    }

    public function validee(): static
    {
        return $this->state(['status' => AnalysisStatus::Validee]);
    }

    public function nonConforme(): static
    {
        return $this->state(['status' => AnalysisStatus::NonConforme]);
    }
}
