<?php

namespace Database\Factories;

use App\Enums\InfrastructureStatus;
use App\Enums\InfrastructureType;
use App\Models\Infrastructure;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Infrastructure>
 */
class InfrastructureFactory extends Factory
{
    /** Quartiers du Grand Tunis par gouvernorat (code de Zone::GOVERNORATES), utilisés dans les noms d'ouvrages. */
    protected const PLACES = [
        'TUN' => ['El Omrane', 'Cité El Khadra', 'Lafayette', 'Montplaisir', 'Le Bardo', 'Sidi Daoud', 'El Menzah 9', 'Bhar Lazreg'],
        'ARI' => ['Ennasr', 'Borj Louzir', 'Chotrana', 'Mnihla', 'Jardins d\'El Menzah', 'Raoued', 'Ettadhamen', 'Sidi Thabet'],
        'BAR' => ['Ezzahra', 'Radès', 'Hammam Lif', 'Fouchana', 'El Mourouj', 'Cité Ennour', 'Mornag', 'Sidi Rezig'],
        'MAN' => ['Den Den', 'Oued Ellil', 'Douar Hicher', 'Tebourba', 'Mornaguia', 'Djedeida'],
    ];

    /**
     * Code (RES-ARI-004), nom, capacité et matériau dépendent du type et de la zone :
     * ils sont calculés après les états (reservoir(), critical()…) et la création de la zone.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $faker = fake('fr_FR');

        return [
            'zone_id' => Zone::factory(),
            // Les stations de traitement sont plus rares que les réservoirs et les conduites
            'type' => $faker->randomElement([
                InfrastructureType::Reservoir, InfrastructureType::Reservoir, InfrastructureType::Reservoir,
                InfrastructureType::Conduite, InfrastructureType::Conduite, InfrastructureType::Conduite,
                InfrastructureType::Pompage, InfrastructureType::Pompage,
                InfrastructureType::Forage, InfrastructureType::Forage,
                InfrastructureType::Traitement,
            ]),
            'code' => fn (array $attributes) => sprintf('%s-%s-%03d',
                self::typeOf($attributes)->codePrefix(),
                self::governorateOf($attributes),
                // Numéros 100 à 999 : les ouvrages fixes du seeder utilisent 001 à 099
                $faker->unique()->numberBetween(100, 999)),
            'name' => fn (array $attributes) => $this->nameFor(self::typeOf($attributes), self::governorateOf($attributes)),
            'material' => fn (array $attributes) => $this->materialFor(self::typeOf($attributes)),
            'capacity' => fn (array $attributes) => $this->capacityFor(self::typeOf($attributes)),
            'capacity_unit' => fn (array $attributes) => self::typeOf($attributes)->defaultUnit(),
            'commissioned_year' => $faker->numberBetween(1960, (int) now()->year),
            'nominal_pressure' => fn (array $attributes) => in_array(self::typeOf($attributes), [InfrastructureType::Conduite, InfrastructureType::Pompage], true)
                ? $faker->randomFloat(1, 2, 10)
                : null,
            'status' => $faker->randomElement([
                InfrastructureStatus::Bon, InfrastructureStatus::Bon, InfrastructureStatus::Bon,
                InfrastructureStatus::ASurveiller, InfrastructureStatus::ASurveiller,
                InfrastructureStatus::Critique,
            ]),
            'last_inspection_at' => $faker->optional(0.9)->dateTimeBetween('-18 months', 'now'),
            // Autour de Tunis (emprise de la carte publique)
            'latitude' => $faker->randomFloat(6, 36.70, 36.95),
            'longitude' => $faker->randomFloat(6, 10.05, 10.35),
            'description' => $faker->optional(0.5)->randomElement([
                'Ouvrage inspecté par l\'équipe réseau, aucune anomalie majeure.',
                'Corrosion localisée relevée lors de la dernière inspection.',
                'Équipé d\'un capteur de pression télérelevé.',
                'Fuites récurrentes signalées par les riverains.',
                'Travaux de réhabilitation prévus dans le plan d\'investissement.',
            ]),
            'is_public' => $faker->boolean(90),
        ];
    }

    // ------------------------------------------------------------ États

    public function good(): static
    {
        return $this->state(['status' => InfrastructureStatus::Bon]);
    }

    public function toWatch(): static
    {
        return $this->state(['status' => InfrastructureStatus::ASurveiller]);
    }

    public function critical(): static
    {
        return $this->state(['status' => InfrastructureStatus::Critique]);
    }

    public function reservoir(): static
    {
        return $this->state(['type' => InfrastructureType::Reservoir]);
    }

    public function conduite(): static
    {
        return $this->state(['type' => InfrastructureType::Conduite]);
    }

    public function pompage(): static
    {
        return $this->state(['type' => InfrastructureType::Pompage]);
    }

    public function traitement(): static
    {
        return $this->state(['type' => InfrastructureType::Traitement]);
    }

    public function forage(): static
    {
        return $this->state(['type' => InfrastructureType::Forage]);
    }

    /** Masquée sur la carte publique. */
    public function hidden(): static
    {
        return $this->state(['is_public' => false]);
    }

    public function withoutCoordinates(): static
    {
        return $this->state(['latitude' => null, 'longitude' => null]);
    }

    // ------------------------------------------------------------ Valeurs dépendant du type

    /** @param array<string, mixed> $attributes */
    protected static function typeOf(array $attributes): InfrastructureType
    {
        $type = $attributes['type'];

        return $type instanceof InfrastructureType ? $type : InfrastructureType::from($type);
    }

    /** @param array<string, mixed> $attributes */
    protected static function governorateOf(array $attributes): string
    {
        return Zone::find($attributes['zone_id'])?->governorateCode() ?? 'TUN';
    }

    protected function nameFor(InfrastructureType $type, string $governorate): string
    {
        $faker = fake('fr_FR');
        $place = $faker->randomElement(self::PLACES[$governorate] ?? array_merge(...array_values(self::PLACES)));

        return match ($type) {
            InfrastructureType::Reservoir => "Réservoir {$place}",
            InfrastructureType::Conduite => 'Conduite DN '.$faker->randomElement([200, 300, 400, 600, 800])." {$place}",
            InfrastructureType::Pompage => "Station de pompage {$place}",
            InfrastructureType::Traitement => "Station de traitement {$place}",
            InfrastructureType::Forage => "Forage {$place} F".$faker->numberBetween(1, 9),
        };
    }

    protected function materialFor(InfrastructureType $type): ?string
    {
        return fake('fr_FR')->randomElement(match ($type) {
            InfrastructureType::Reservoir, InfrastructureType::Traitement => ['Béton armé'],
            InfrastructureType::Conduite => ['Fonte ductile', 'Fonte grise', 'PEHD', 'PVC', 'Acier'],
            InfrastructureType::Pompage => ['Acier', 'Béton armé'],
            InfrastructureType::Forage => ['Acier', 'PVC'],
        });
    }

    /** Capacité dans l'unité par défaut du type : m³, km ou L/s. */
    protected function capacityFor(InfrastructureType $type): float
    {
        $faker = fake('fr_FR');

        return match ($type) {
            InfrastructureType::Reservoir => $faker->numberBetween(2, 30) * 500,
            InfrastructureType::Conduite => $faker->randomFloat(1, 0.5, 12),
            InfrastructureType::Pompage => $faker->numberBetween(4, 60) * 5,
            InfrastructureType::Traitement => $faker->numberBetween(5, 40) * 100,
            InfrastructureType::Forage => $faker->numberBetween(10, 60),
        };
    }
}
