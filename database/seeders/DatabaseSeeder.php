<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ZoneSeeder::class,
            InfrastructureSeeder::class,
            // Les autres modules (incidents, projets, qualité de l'eau…) ajoutent leurs seeders ici,
            // après InfrastructureSeeder : ils peuvent ainsi rattacher leurs données aux utilisateurs
            // et aux infrastructures créés (Infrastructure::inRandomOrder()->first(), etc.).
            IncidentSeeder::class,
            InterventionSeeder::class,
            WaterAnalysisSeeder::class,
            ProjectSeeder::class,
            FundingSeeder::class,
        ]);
    }
}
