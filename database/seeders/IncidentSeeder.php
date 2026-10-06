<?php

namespace Database\Seeders;

use App\Enums\IncidentSeverity as Severity;
use App\Enums\IncidentStatus as Status;
use App\Enums\IncidentType as Type;
use App\Enums\Role;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Environ 40 incidents : ceux du template (références INC-2026-08xx/09xx), puis des incidents
 * générés sur les infrastructures et les citoyens existants, répartis sur les 6 derniers mois.
 */
class IncidentSeeder extends Seeder
{
    /** Nombre d'incidents générés par la factory, en plus de ceux du template. */
    protected const RANDOM_COUNT = 33;

    /**
     * Incidents du template : [référence, titre, type, code ouvrage, gravité, statut, signalé le, résolu le, adresse, lat, lng, déclaré par la citoyenne de test].
     */
    protected const TEMPLATE = [
        ['INC-2026-0871', 'Conduite éclatée avenue de Carthage', Type::Fuite, 'CND-TUN-021', Severity::Critique, Status::Resolu, '2026-09-27 06:55', '2026-09-27 19:30', 'Avenue de Carthage, Tunis', 36.7970, 10.1830, false,
            'Une conduite a éclaté sous la chaussée avenue de Carthage : l\'eau envahit la rue et les caves des immeubles voisins.'],
        ['INC-2026-0884', 'Pression faible aux étages', Type::Coupure, 'RES-BAR-002', Severity::Moyenne, Status::Resolu, '2026-09-29 18:30', '2026-09-30 12:15', 'Avenue de France, Mégrine', 36.7700, 10.2330, true,
            'Depuis deux jours, la pression est très faible le soir et l\'eau n\'arrive plus aux étages supérieurs de la résidence.'],
        ['INC-2026-0899', 'Suintement au pied du compteur', Type::Fuite, null, Severity::Faible, Status::Resolu, '2026-10-01 11:08', '2026-10-02 10:30', 'Rue Mongi Slim, La Manouba', 36.8080, 10.0970, true,
            'Un léger suintement au pied du compteur de mon immeuble laisse une flaque permanente dans l\'entrée.'],
        ['INC-2026-0907', 'Coupure prolongée cité El Khadra', Type::Coupure, 'CND-TUN-021', Severity::Moyenne, Status::EnIntervention, '2026-10-02 16:25', null, 'Cité El Khadra, Tunis', 36.8320, 10.1920, false,
            'Toute la cité El Khadra est privée d\'eau depuis le début de l\'après-midi, sans avis de coupure programmée.'],
        ['INC-2026-0912', 'Chute de pression secteur nord', Type::Coupure, 'PMP-ARI-003', Severity::Critique, Status::Qualifie, '2026-10-03 02:40', null, 'Avenue de l\'Indépendance, Ariana', 36.8590, 10.1880, false,
            'La pression s\'est effondrée cette nuit dans tout le secteur nord d\'Ariana, plusieurs rues n\'ont plus d\'eau du tout.'],
        ['INC-2026-0915', 'Eau trouble et odeur de terre', Type::Contamination, 'FOR-BAR-003', Severity::Critique, Status::Signale, '2026-10-03 07:12', null, 'Cité Ennour, Ben Arous', 36.7450, 10.2220, false,
            'L\'eau sort du robinet légèrement jaunâtre avec une odeur de terre mouillée depuis hier soir. Plusieurs voisins de l\'immeuble constatent la même chose.'],
        ['INC-2026-0918', 'Fuite importante rue Ibn Sina', Type::Fuite, 'CND-ARI-012', Severity::Elevee, Status::EnIntervention, '2026-10-03 09:02', null, 'Rue Ibn Sina, Ariana', 36.8660, 10.1640, true,
            'Une fuite importante s\'est déclarée rue Ibn Sina : l\'eau jaillit de la chaussée et inonde le trottoir devant l\'école.'],
    ];

    public function run(): void
    {
        $citizens = User::where('role', Role::Citoyen->value)->where('is_active', true)->get();
        $infrastructures = Infrastructure::all();
        $testCitizen = User::where('email', 'citoyen@aquasecure.tn')->first();

        // ------------------------------------------------------------ Incidents générés
        // Créés dans l'ordre chronologique pour que les références suivent les dates (INC-2026-0001…)

        $dates = collect(range(1, self::RANDOM_COUNT))
            ->map(fn () => fake()->dateTimeBetween('-6 months', '-10 days'))
            ->sort()
            ->values();

        foreach ($dates as $i => $date) {
            $factory = Incident::factory()->recycle($infrastructures)->recycle($citizens);

            // Environ un signalement sur sept n'est rattaché à aucun ouvrage
            if (fake()->boolean(15)) {
                $factory = $factory->withoutInfrastructure();
            }

            $factory->create(array_filter([
                'created_at' => $date,
                // La citoyenne de test a un signalement de plus (4 au total avec ceux du template)
                'user_id' => $i === 0 ? $testCitizen?->id : null,
            ]));
        }

        // ------------------------------------------------------------ Incidents du template

        foreach (self::TEMPLATE as [$reference, $title, $type, $code, $severity, $status, $reportedAt, $resolvedAt, $address, $lat, $lng, $byTestCitizen, $description]) {
            Incident::factory()->create([
                'reference' => $reference,
                'title' => $title,
                'description' => $description,
                'type' => $type,
                'severity' => $severity,
                'status' => $status,
                'address' => $address,
                'latitude' => $lat,
                'longitude' => $lng,
                'infrastructure_id' => $code ? Infrastructure::where('code', $code)->value('id') : null,
                'user_id' => $byTestCitizen && $testCitizen ? $testCitizen->id : $citizens->random()->id,
                'created_at' => $reportedAt,
                'resolved_at' => $resolvedAt,
                'updated_at' => $resolvedAt ?? $reportedAt,
            ]);
        }
    }
}
