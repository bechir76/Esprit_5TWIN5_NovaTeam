<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ZoneSeeder::class,
            InfrastructureSeeder::class,
            IncidentSeeder::class,
            InterventionSeeder::class,
            ProjectSeeder::class,
            FundingSeeder::class,
            WaterAnalysisSeeder::class,
            MeasurementSeeder::class,
            // Score de risque des ouvrages (après InfrastructureSeeder et les incidents / analyses qu'il prend en compte)
            InfrastructureRiskSeeder::class,
        ]);
    }
}
