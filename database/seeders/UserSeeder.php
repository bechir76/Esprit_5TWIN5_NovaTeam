<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /** Un compte de connexion par rôle (mot de passe « password »), puis des comptes générés. */
    public function run(): void
    {
        User::factory()->admin()->create(['email' => 'admin@aquasecure.tn']);
        User::factory()->gestionnaire()->create(['email' => 'gestionnaire@aquasecure.tn']);
        User::factory()->technicien()->create(['email' => 'technicien@aquasecure.tn']);
        User::factory()->citoyen()->create(['email' => 'citoyen@aquasecure.tn']);

        User::factory()->count(4)->technicien()->create();
        User::factory()->count(20)->citoyen()->create();
    }
}
