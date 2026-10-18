<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Funding;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class FundingSeeder extends Seeder
{
    /** Financements institutionnels et contributions citoyennes des projets existants. */
    public function run(): void
    {
        $projects = Project::all();

        Funding::factory()->count(40)->recycle($projects)->create();
        Funding::factory()->count(30)->fromCitizen()->recycle($projects)->recycle(User::where('role', Role::Citoyen)->get())->create();
    }
}
