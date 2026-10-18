<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Models\Funding;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class FundingSeeder extends Seeder
{
    /** Dons citoyens sur les collectes ouvertes : confirmés (dont anonymes et cofinancés), en attente de paiement. */
    public function run(): void
    {
        $open = Project::ofStatus(ProjectStatus::Open)->get();
        $citizens = User::where('role', Role::Citoyen)->where('is_active', true)->get();
        $testCitizen = User::where('email', 'citoyen@aquasecure.tn')->first();

        Funding::factory()->count(20)->fromCitizen()->confirmed()->recycle($open)->recycle($citizens)->create();
        Funding::factory()->count(5)->fromCitizen()->confirmed()->anonymous()->recycle($open)->recycle($citizens)->create();
        Funding::factory()->count(6)->matching()->recycle($open)->create();
        Funding::factory()->count(3)->fromCitizen()->pending()->recycle($open)->recycle($citizens)->create();

        // La citoyenne de test a deux dons confirmés (reçus PDF dans « Mes contributions »)
        if ($testCitizen) {
            Funding::factory()->count(2)->fromCitizen($testCitizen)->confirmed()->recycle($open)->create();
        }
    }
}
