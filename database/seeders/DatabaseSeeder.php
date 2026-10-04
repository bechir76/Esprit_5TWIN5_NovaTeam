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
            // Les autres modules (infrastructures, incidents, projets…) ajoutent leurs seeders
            // après UserSeeder : ils peuvent ainsi rattacher leurs données aux utilisateurs créés.
        ]);
    }
}
