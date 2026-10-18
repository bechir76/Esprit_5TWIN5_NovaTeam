<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Incident;
use App\Models\Intervention;
use App\Models\User;
use Illuminate\Database\Seeder;

class InterventionSeeder extends Seeder
{
    /** Interventions sur les incidents ouverts, confiées aux techniciens. */
    public function run(): void
    {
        $incidents = Incident::open()->get();
        $techniciens = User::where('role', Role::Technicien)->get();

        Intervention::factory()->count(30)->recycle($incidents)->recycle($techniciens)->create();
        Intervention::factory()->count(15)->inProgress()->recycle($incidents)->recycle($techniciens)->create();
        Intervention::factory()->count(15)->done()->recycle($incidents)->recycle($techniciens)->create();
    }
}
