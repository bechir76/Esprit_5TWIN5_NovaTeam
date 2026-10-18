<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\User;
use Illuminate\Database\Seeder;

class IncidentSeeder extends Seeder
{
    /** Incidents signalés par les citoyens sur les ouvrages existants. */
    public function run(): void
    {
        $infrastructures = Infrastructure::all();
        $citoyens = User::where('role', Role::Citoyen)->get();

        Incident::factory()->count(20)->recycle($infrastructures)->recycle($citoyens)->create();
        Incident::factory()->count(10)->qualified()->recycle($infrastructures)->recycle($citoyens)->create();
        Incident::factory()->count(10)->resolved()->recycle($infrastructures)->recycle($citoyens)->create();
    }
}
