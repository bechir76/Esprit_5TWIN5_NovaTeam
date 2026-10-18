<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Funding;
use App\Models\Infrastructure;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Projets de rénovation à tous les statuts, rattachés aux ouvrages existants : collectes ouvertes,
     * 2 presque financés (85 %), financés, en travaux, terminé, brouillons et 1 échoué avec dons remboursés.
     */
    public function run(): void
    {
        $infrastructures = Infrastructure::all();
        $citizens = User::where('role', Role::Citoyen)->get();

        Project::factory()->count(4)->open()->recycle($infrastructures)
            ->has(Funding::factory()->count(2)->municipal())
            ->create();
        Project::factory()->count(2)->almostFunded()->recycle($infrastructures)->create();
        Project::factory()->count(2)->funded()->recycle($infrastructures)->create();
        Project::factory()->inProgress()->recycle($infrastructures)->create();
        Project::factory()->completed()->recycle($infrastructures)->create();
        Project::factory()->count(2)->draft()->recycle($infrastructures)->create();

        // Collecte échouée : dons citoyens remboursés et cofinancement annulé
        Project::factory()->failed()->recycle($infrastructures)
            ->has(Funding::factory()->count(6)->fromCitizen()->refunded()->recycle($citizens))
            ->has(Funding::factory()->matching()->refunded())
            ->create();
    }
}
