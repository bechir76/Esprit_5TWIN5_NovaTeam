<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Infrastructure;
use App\Models\User;
use App\Models\WaterAnalysis;
use Illuminate\Database\Seeder;

class WaterAnalysisSeeder extends Seeder
{
    /** Analyses prélevées par les techniciens sur les ouvrages existants. */
    public function run(): void
    {
        WaterAnalysis::factory()->count(20)
            ->recycle(Infrastructure::all())
            ->recycle(User::where('role', Role::Technicien)->get())
            ->create();
    }
}
