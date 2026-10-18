<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\User;
use Illuminate\Database\Seeder;

class IncidentSeeder extends Seeder
{
    /**
     * Incidents signalés par les citoyens sur les ouvrages existants, plus les cas de démonstration du module :
     * doublons rattachés, un groupe de 4 incidents proches (incidents:cluster) et 2 délais cibles dépassés.
     */
    public function run(): void
    {
        $infrastructures = Infrastructure::all();
        $citoyens = User::where('role', Role::Citoyen)->get();

        // Incidents ouverts récents (dans leur délai cible) ; historique résolu / rejeté sur 6 mois
        Incident::factory()->count(15)->recent()->recycle($infrastructures)->recycle($citoyens)->create();
        Incident::factory()->count(5)->critical()->recent()->recycle($infrastructures)->recycle($citoyens)->create();
        Incident::factory()->count(10)->qualified()->recent()->recycle($infrastructures)->recycle($citoyens)->create();
        Incident::factory()->count(10)->resolved()->recycle($infrastructures)->recycle($citoyens)->create();
        Incident::factory()->count(3)->rejected()->recycle($infrastructures)->recycle($citoyens)->create();

        // Doublons : même type, à moins de 200 m, rattachés à l'incident principal
        Incident::factory()->fuite()->withDuplicates(3)->recycle($infrastructures)->recycle($citoyens)->create();
        Incident::factory()->coupure()->withDuplicates(1)->recycle($infrastructures)->recycle($citoyens)->create();

        // Groupe de 4 incidents proches (Ariana, < 500 m, < 6 h) : détecté par incidents:cluster
        Incident::factory()->count(4)->nearby(36.8665, 10.1647, now()->subHours(3))->recycle($infrastructures)->recycle($citoyens)->create();

        // Délais cibles dépassés (alerte déjà envoyée)
        Incident::factory()->count(2)->slaBreached()->recycle($infrastructures)->recycle($citoyens)->create();
    }
}
