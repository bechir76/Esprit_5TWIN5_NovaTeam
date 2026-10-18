<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Incident;
use App\Models\Intervention;
use App\Models\User;
use Illuminate\Database\Seeder;

class InterventionSeeder extends Seeder
{
    /**
     * Interventions sur les incidents ouverts, confiées aux techniciens. Le planning actif ne contient aucun
     * chevauchement pour une même équipe, mais plusieurs équipes travaillent aux mêmes heures.
     */
    public function run(): void
    {
        $incidents = Incident::open()->whereNull('duplicate_of_id')->get();
        $techniciens = User::where('role', Role::Technicien)->get();

        // Aujourd'hui : une intervention en cours par équipe (8 h)
        Intervention::factory()->count(count(Intervention::TEAMS))->planning(0)->inProgress()->recycle($incidents)->recycle($techniciens)->create();
        // Deux jours suivants : deux créneaux par jour et par équipe
        Intervention::factory()->count(4 * count(Intervention::TEAMS))->planning(1)->planned()->recycle($incidents)->recycle($techniciens)->create();

        Intervention::factory()->count(15)->completed()->recycle($incidents)->recycle($techniciens)->create();
        Intervention::factory()->count(3)->cancelled()->recycle($incidents)->recycle($techniciens)->create();
    }
}
