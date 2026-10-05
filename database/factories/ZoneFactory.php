<?php

namespace Database\Factories;

use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    /** Communes du Grand Tunis => gouvernorat (hors communes des zones fixes de ZoneSeeder). */
    protected const COMMUNES = [
        'Le Bardo' => 'Tunis', 'La Marsa' => 'Tunis', 'Carthage' => 'Tunis', 'Le Kram' => 'Tunis',
        'La Goulette' => 'Tunis', 'Sidi Hassine' => 'Tunis', 'El Omrane' => 'Tunis', 'El Menzah' => 'Tunis',
        'Raoued' => 'Ariana', 'Ettadhamen' => 'Ariana', 'Mnihla' => 'Ariana', 'Kalâat el-Andalous' => 'Ariana', 'Sidi Thabet' => 'Ariana',
        'Radès' => 'Ben Arous', 'Ezzahra' => 'Ben Arous', 'Hammam Lif' => 'Ben Arous', 'Fouchana' => 'Ben Arous',
        'Mornag' => 'Ben Arous', 'Hammam Chott' => 'Ben Arous', 'El Mourouj' => 'Ben Arous',
        'Den Den' => 'Manouba', 'Oued Ellil' => 'Manouba', 'Douar Hicher' => 'Manouba', 'Tebourba' => 'Manouba',
        'Mornaguia' => 'Manouba', 'Djedeida' => 'Manouba',
    ];

    /** Secteurs : une commune peut avoir plusieurs zones (« Radès Nord », « Radès Sud »…). */
    protected const SECTORS = ['', ' Nord', ' Sud', ' Est', ' Ouest', ' Centre'];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $faker = fake('fr_FR');
        $commune = $faker->randomElement(array_keys(self::COMMUNES));

        return [
            'name' => $faker->unique()->randomElement(
                collect(self::COMMUNES)->keys()->crossJoin(self::SECTORS)->map(fn ($pair) => implode('', $pair))->all()
            ),
            'municipality' => fn (array $attributes) => $this->communeOf($attributes['name']) ?? $commune,
            'governorate' => fn (array $attributes) => self::COMMUNES[$attributes['municipality']] ?? 'Tunis',
            'population_served' => $faker->numberBetween(8, 250) * 1000,
            'description' => $faker->optional(0.6)->randomElement([
                'Secteur résidentiel alimenté par gravité depuis le réservoir principal.',
                'Zone mixte habitat et activités, pression faible aux heures de pointe.',
                'Quartier récent raccordé au réseau en PEHD.',
                'Secteur ancien, conduites en fonte grise à renouveler.',
                'Zone industrielle et logements collectifs.',
            ]),
        ];
    }

    protected function communeOf(string $name): ?string
    {
        return collect(array_keys(self::COMMUNES))->first(
            fn (string $commune) => in_array($name, array_map(fn (string $sector) => $commune.$sector, self::SECTORS), true)
        );
    }
}
