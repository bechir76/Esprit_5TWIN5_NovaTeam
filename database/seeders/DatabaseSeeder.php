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
        ]);
    }
}
