<?php

namespace Database\Seeders;

use App\Models\Infrastructure;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class InfrastructureSeeder extends Seeder
{
    /** Ouvrages répartis dans les zones existantes. */
    public function run(): void
    {
        Infrastructure::factory()->count(30)->recycle(Zone::all())->create();
    }
}
