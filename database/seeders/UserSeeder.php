<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Un compte fixe par rôle (mot de passe « password »), 20 citoyens et 2 comptes suspendus.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Karim Trabelsi',
            'email' => 'admin@aquasecure.tn',
            'organization' => 'Régie des eaux d\'Ariana',
        ]);

        User::factory()->gestionnaire()->create([
            'name' => 'Amira Haddad',
            'email' => 'gestionnaire@aquasecure.tn',
            'organization' => 'Régie des eaux d\'Ariana',
        ]);

        User::factory()->technicien()->create([
            'name' => 'Nour Jlassi',
            'email' => 'technicien@aquasecure.tn',
            'organization' => 'Régie des eaux d\'Ariana',
        ]);

        User::factory()->citoyen()->create([
            'name' => 'Sana Ben Ali',
            'email' => 'citoyen@aquasecure.tn',
            'address' => '12 rue Ibn Sina, Ariana',
        ]);

        User::factory(20)->citoyen()->create();

        User::factory()->citoyen()->suspended()->create();
        User::factory()->technicien()->suspended()->create();
    }
}
