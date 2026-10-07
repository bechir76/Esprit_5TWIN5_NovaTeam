<?php

namespace Database\Seeders;

use App\Enums\ProjectCategory as Category;
use App\Enums\ProjectStatus as Status;
use App\Models\Infrastructure;
use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * 12 projets rattachés aux ouvrages du template (InfrastructureSeeder), aux statuts variés.
 * Les dates sont relatives à aujourd'hui pour que les jours restants restent réalistes.
 * Les financements sont créés ensuite par FundingSeeder, en cohérence avec le statut.
 */
class ProjectSeeder extends Seeder
{
    /**
     * [titre, catégorie, code ouvrage (null = multi-sites), budget TND, statut, début (jours depuis aujourd'hui), fin, public, description].
     */
    public const PROJECTS = [
        ['Réhabilitation du réservoir de La Soukra', Category::Decontamination, 'RES-ARI-004', 220000, Status::EnCollecte, -150, 54, true,
            'Réservoir semi-enterré de 5 000 m³ construit en 1987. Fissures du radier et dépôts de manganèse détectés lors de l\'inspection de mars 2026 : vidange, désinfection, réparation du radier et capteurs de qualité connectés.'],
        ['Remplacement des conduites en fonte de Bab Saadoun', Category::Renovation, 'CND-TUN-021', 310000, Status::EnCollecte, -75, 107, true,
            'Les 2,4 km de conduites en fonte grise posées en 1962 sont responsables de 40 % des fuites du quartier Bab Saadoun. Elles seront remplacées par du PEHD avec reprise des branchements.'],
        ['Capteurs de qualité dans 12 écoles d\'Ariana', Category::Surveillance, null, 48000, Status::Finance, -160, 17, true,
            'Mesure en continu du chlore résiduel et de la turbidité aux points d\'eau de 12 écoles primaires, avec alertes transmises à la régie et affichage pour les familles.'],
        ['Station de pompage solaire de Raoued', Category::Secheresse, 'PMP-ARI-007', 180000, Status::EnCollecte, -40, 135, true,
            'Panneaux photovoltaïques et variateurs de vitesse pour maintenir la pression en été malgré les délestages électriques qui privent d\'eau les quartiers hauts de Raoued.'],
        ['Traitement du nitrate au forage de Mornag', Category::Decontamination, 'FOR-BAR-003', 150000, Status::EnCollecte, -210, 9, true,
            'Le taux de nitrate mesuré au forage F3 atteint 62 mg/L, au-dessus du seuil de 50 mg/L. Une unité de dénitratation par échange d\'ions sécurisera l\'eau distribuée.'],
        ['Sectorisation du réseau de Ben Arous', Category::Renovation, 'CND-BAR-017', 95000, Status::Brouillon, -10, 190, false,
            'Pose de débitmètres de secteur et de vannes motorisées sur la conduite DN 600 pour localiser les fuites invisibles, estimées à 6 m³/h.'],
        ['Remise à niveau de la station Ariana Nord', Category::Renovation, 'PMP-ARI-003', 260000, Status::EnTravaux, -320, -60, true,
            'Remplacement des deux groupes motopompes tombés en panne à répétition, nouvelle armoire électrique et télégestion de la station qui alimente le nord d\'Ariana.'],
        ['Étanchéité du réservoir de Mégrine', Category::Renovation, 'RES-BAR-002', 85000, Status::Termine, -430, -210, true,
            'Reprise complète de l\'étanchéité intérieure de la cuve de 8 000 m³ et remplacement des vannes d\'isolement. Réservoir remis en service après contrôle bactériologique.'],
        ['Télésurveillance de la conduite DN 300 Ariana Nord', Category::Surveillance, 'CND-ARI-012', 64000, Status::EnCollecte, -50, 75, true,
            'Capteurs de pression et de débit télétransmis le long de la conduite pour détecter les casses en quelques minutes au lieu de quelques heures.'],
        ['Récupération des eaux de lavage à Ghdir El Golla', Category::Secheresse, 'TRT-MAN-001', 120000, Status::Finance, -190, -5, true,
            'Recyclage des eaux de lavage des filtres de la station de traitement : environ 600 m³ économisés chaque jour pendant les mois de sécheresse.'],
        ['Désinfection UV au réservoir du Belvédère', Category::Decontamination, 'RES-TUN-001', 72000, Status::EnCollecte, -100, -3, true,
            'Réacteur UV en sortie du réservoir pour garantir la qualité bactériologique de l\'eau distribuée au centre de Tunis, en complément de la chloration.'],
        ['Citernes de secours pour les quartiers de La Manouba', Category::Secheresse, null, 40000, Status::Brouillon, -5, 150, false,
            'Citernes mobiles de 10 m³ et points de distribution d\'urgence pour les quartiers privés d\'eau lors des pics de consommation estivaux.'],
    ];

    public function run(): void
    {
        foreach (self::PROJECTS as [$title, $category, $code, $budget, $status, $start, $end, $public, $description]) {
            Project::factory()->create([
                'title' => $title,
                'category' => $category,
                'description' => $description,
                'target_budget' => $budget,
                'status' => $status,
                'start_date' => today()->addDays($start),
                'end_date' => today()->addDays($end),
                'is_public' => $public,
                'infrastructure_id' => $code ? Infrastructure::where('code', $code)->value('id') : null,
                'created_at' => today()->addDays($start)->setTime(9, 0),
            ]);
        }
    }
}
