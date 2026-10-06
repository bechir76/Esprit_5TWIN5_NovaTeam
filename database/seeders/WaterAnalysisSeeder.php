<?php

namespace Database\Seeders;

use App\Enums\AnalysisStatus;
use App\Enums\InfrastructureType;
use App\Enums\WaterParameter;
use App\Models\Infrastructure;
use App\Models\Measurement;
use App\Models\User;
use App\Models\WaterAnalysis;
use Illuminate\Database\Seeder;

/**
 * Peuple les analyses de qualité de l'eau et leurs mesures.
 *
 * Données fixes  : 4 analyses emblématiques (forage Mornag F3 non conforme,
 *                  station Ghdir El Golla validée, réservoir Belvédère validé,
 *                  réservoir La Soukra non conforme).
 * Données auto   : 3 à 5 analyses par infrastructure de type réservoir,
 *                  traitement ou forage, réparties sur les 6 derniers mois,
 *                  prélevées par les techniciens existants.
 */
class WaterAnalysisSeeder extends Seeder
{
    /**
     * Analyses fixes — les mesures sont indexées par valeur string de l'enum.
     *
     * @var array<int, array<string, mixed>>
     */
    protected const FIXED = [
        [
            'code'           => 'FOR-BAR-003',
            'sampling_point' => 'Sortie forage',
            'sampled_at'     => '2026-09-21 07:30',
            'laboratory'     => 'Laboratoire central SONEDE',
            'status'         => AnalysisStatus::NonConforme,
            'notes'          => 'Nitrate à 62 mg/L — distribution suspendue depuis 07:40.',
            'measurements'   => [
                'chlore'       => 0.32,
                'ph'           => 7.4,
                'turbidite'    => 0.6,
                'nitrate'      => 62.0,  // NON CONFORME (> 50)
                'conductivite' => 1840.0,
                'ecoli'        => 0.0,
            ],
        ],
        [
            'code'           => 'TRT-MAN-001',
            'sampling_point' => 'Sortie station de traitement',
            'sampled_at'     => '2026-09-28 06:00',
            'laboratory'     => 'Laboratoire régional Ariana',
            'status'         => AnalysisStatus::Validee,
            'notes'          => 'Contrôle de routine post-maintenance.',
            'measurements'   => [
                'chlore'       => 0.45,
                'ph'           => 7.2,
                'turbidite'    => 0.3,
                'nitrate'      => 22.0,
                'conductivite' => 980.0,
                'ecoli'        => 0.0,
            ],
        ],
        [
            'code'           => 'RES-TUN-001',
            'sampling_point' => 'Sortie réservoir',
            'sampled_at'     => '2026-09-14 08:15',
            'laboratory'     => 'Laboratoire central SONEDE',
            'status'         => AnalysisStatus::Validee,
            'notes'          => null,
            'measurements'   => [
                'chlore'       => 0.38,
                'ph'           => 7.6,
                'turbidite'    => 0.5,
                'nitrate'      => 18.5,
                'conductivite' => 1120.0,
                'ecoli'        => 0.0,
            ],
        ],
        [
            'code'           => 'RES-ARI-004',
            'sampling_point' => 'Entrée réseau distribution',
            'sampled_at'     => '2026-10-01 07:00',
            'laboratory'     => 'Lab. Environnement & Eaux',
            'status'         => AnalysisStatus::NonConforme,
            'notes'          => 'Turbidité élevée suite aux travaux de réhabilitation.',
            'measurements'   => [
                'chlore'       => 0.12,
                'ph'           => 7.1,
                'turbidite'    => 1.8,   // NON CONFORME (> 1)
                'nitrate'      => 31.0,
                'conductivite' => 1340.0,
                'ecoli'        => 0.0,
            ],
        ],
    ];

    // ---------------------------------------------------------------- Points de prélèvement

    protected const SAMPLING_POINTS = [
        'Sortie réservoir',
        'Entrée réseau distribution',
        'Sortie station de traitement',
        'Sortie forage',
        'Poste de chloration aval',
        'Robinet école Ibn Khaldoun',
        'Robinet hôpital Charles Nicolle',
        'Borne fontaine place de la République',
        'Prise réseau secondaire',
        'Robinet immeuble collectif',
    ];

    // ---------------------------------------------------------------- Laboratoires

    protected const LABS = [
        'Laboratoire central SONEDE',
        'Laboratoire régional Ariana',
        'Lab. Environnement & Eaux',
        'Institut National de la Santé',
        'Laboratoire régional Ben Arous',
        'Centre Technique des Eaux',
    ];

    public function run(): void
    {
        // Techniciens pour les prélèvements (peut être null)
        $technicianIds = User::where('role', 'technicien')->pluck('id')->all();

        // ------------------------------------------------------------ Analyses fixes

        foreach (self::FIXED as $data) {
            $infra = Infrastructure::where('code', $data['code'])->first();
            if (! $infra) {
                continue;
            }

            $analysis = WaterAnalysis::create([
                'reference'         => WaterAnalysis::generateReference(),
                'infrastructure_id' => $infra->id,
                'sampled_at'        => $data['sampled_at'],
                'sampling_point'    => $data['sampling_point'],
                'laboratory'        => $data['laboratory'],
                'sampled_by'        => $technicianIds ? fake()->randomElement($technicianIds) : null,
                'status'            => $data['status'],
                'notes'             => $data['notes'],
            ]);

            foreach ($data['measurements'] as $paramValue => $value) {
                Measurement::create([
                    'water_analysis_id' => $analysis->id,
                    'parameter'         => $paramValue,
                    'value'             => $value,
                ]);
            }

            // Le hook saved() recalcule le statut ; on force pour être sûr
            $analysis->recalculateStatus();
        }

        // ------------------------------------------------------------ Analyses aléatoires
        // Cible : réservoirs, stations de traitement, forages — 3 à 5 analyses chacun

        $targetTypes = [
            InfrastructureType::Reservoir,
            InfrastructureType::Traitement,
            InfrastructureType::Forage,
        ];

        $infrastructures = Infrastructure::whereIn('type', array_map(fn ($t) => $t->value, $targetTypes))
            ->get();

        foreach ($infrastructures as $infra) {
            $count = fake()->numberBetween(3, 5);

            for ($i = 0; $i < $count; $i++) {
                // Date aléatoire dans les 6 derniers mois, espacée de ~1 mois par itération
                $daysAgo    = fake()->numberBetween(($i * 30), min(($i * 30) + 50, 180));
                $sampledAt  = now()->subDays($daysAgo)->setTime(fake()->numberBetween(6, 10), 0);

                $analysis = WaterAnalysis::create([
                    'reference'         => WaterAnalysis::generateReference(),
                    'infrastructure_id' => $infra->id,
                    'sampled_at'        => $sampledAt,
                    'sampling_point'    => fake()->randomElement(self::SAMPLING_POINTS),
                    'laboratory'        => fake()->randomElement(self::LABS),
                    'sampled_by'        => $technicianIds ? fake()->optional(0.8)->randomElement($technicianIds) : null,
                    'status'            => AnalysisStatus::EnAttente, // recalculé ci-dessous
                    'notes'             => fake()->optional(0.35)->randomElement([
                        'Contrôle de routine.',
                        'Prélèvement après travaux sur réseau.',
                        'Suite à signalement citoyen.',
                        'Inspection trimestrielle.',
                        'Vérification post-chloration.',
                    ]),
                ]);

                // 4 à 6 paramètres sans doublon, 90 % de chances d'être conformes
                $params = $this->pickParams(fake()->numberBetween(4, 6));

                foreach ($params as $param) {
                    // 1 analyse sur 6 ≈ non conforme (15 %)
                    $compliant = fake()->boolean(85);
                    Measurement::create([
                        'water_analysis_id' => $analysis->id,
                        'parameter'         => $param->value,
                        'value'             => $this->sampleValue($param, $compliant),
                    ]);
                }

                // Le hook recalcule déjà via Measurement::saved(), on force quand même
                $analysis->refresh()->recalculateStatus();
            }
        }
    }

    // ---------------------------------------------------------------- Helpers

    /**
     * Tire $n paramètres uniques parmi tous les cas de l'enum.
     *
     * @return array<WaterParameter>
     */
    protected function pickParams(int $n): array
    {
        $all = WaterParameter::cases();
        shuffle($all);

        return array_slice($all, 0, min($n, count($all)));
    }

    /** Génère une valeur conforme ou non conforme selon le seuil de l'enum. */
    protected function sampleValue(WaterParameter $param, bool $compliant): float
    {
        return match ($param) {
            WaterParameter::Chlore       => $compliant ? fake()->randomFloat(2, 0.12, 0.48) : fake()->randomFloat(2, 0.51, 0.9),
            WaterParameter::Ph           => $compliant ? fake()->randomFloat(1, 6.6, 8.4)   : fake()->randomFloat(1, 9.0, 10.5),
            WaterParameter::Turbidite    => $compliant ? fake()->randomFloat(2, 0.10, 0.95) : fake()->randomFloat(1, 1.2, 4.0),
            WaterParameter::Nitrate      => $compliant ? fake()->randomFloat(1, 5.0, 49.0)  : fake()->randomFloat(1, 51.0, 75.0),
            WaterParameter::Conductivite => $compliant
                ? (float) fake()->numberBetween(250, 2499)
                : (float) fake()->numberBetween(2501, 3200),
            WaterParameter::Ecoli        => $compliant ? 0.0 : (float) fake()->numberBetween(1, 15),
        };
    }
}
