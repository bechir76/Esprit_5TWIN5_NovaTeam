<?php

namespace Database\Seeders;

use App\Models\Measurement;
use App\Models\WaterAnalysis;
use Illuminate\Database\Seeder;

class MeasurementSeeder extends Seeder
{
    /** Une mesure par paramètre pour chaque analyse (un paramètre est unique par analyse). */
    public function run(): void
    {
        WaterAnalysis::all()->each(fn (WaterAnalysis $analysis) => Measurement::factory()->eachParameter()->for($analysis)->create());
    }
}
