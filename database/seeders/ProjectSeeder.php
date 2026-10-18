<?php

namespace Database\Seeders;

use App\Models\Infrastructure;
use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /** Projets de rénovation rattachés aux ouvrages existants. */
    public function run(): void
    {
        $infrastructures = Infrastructure::all();

        Project::factory()->count(8)->recycle($infrastructures)->create();
        Project::factory()->count(2)->funded()->recycle($infrastructures)->create();
        Project::factory()->count(2)->draft()->recycle($infrastructures)->create();
    }
}
