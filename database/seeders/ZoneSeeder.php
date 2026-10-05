<?php

namespace Database\Seeders;

use App\Models\Zone;
use Illuminate\Database\Seeder;

class ZoneSeeder extends Seeder
{
    /** Les 6 zones de desserte du template (Grand Tunis). */
    public const ZONES = [
        ['name' => 'Tunis Centre', 'municipality' => 'Tunis', 'governorate' => 'Tunis', 'population_served' => 210000,
            'description' => 'Médina, Bab Saadoun et centre-ville. Réseau ancien, en partie en fonte grise.'],
        ['name' => 'Ariana Nord', 'municipality' => 'Ariana', 'governorate' => 'Ariana', 'population_served' => 115000,
            'description' => 'Ariana Ville, Ennasr et Raoued, alimentés par la station de pompage Ariana Nord.'],
        ['name' => 'La Soukra', 'municipality' => 'La Soukra', 'governorate' => 'Ariana', 'population_served' => 130000,
            'description' => 'Secteur en forte croissance, desservi par le réservoir de La Soukra.'],
        ['name' => 'Ben Arous', 'municipality' => 'Ben Arous', 'governorate' => 'Ben Arous', 'population_served' => 90000,
            'description' => 'Ben Arous, Mornag et zones industrielles, avec des forages d\'appoint.'],
        ['name' => 'Mégrine', 'municipality' => 'Mégrine', 'governorate' => 'Ben Arous', 'population_served' => 30000,
            'description' => 'Mégrine et Sidi Rezig, alimentés par le réservoir de Mégrine.'],
        ['name' => 'La Manouba', 'municipality' => 'La Manouba', 'governorate' => 'Manouba', 'population_served' => 45000,
            'description' => 'Secteur alimenté par la station de traitement de Ghdir El Golla.'],
    ];

    public function run(): void
    {
        foreach (self::ZONES as $zone) {
            Zone::factory()->create($zone);
        }
    }
}
