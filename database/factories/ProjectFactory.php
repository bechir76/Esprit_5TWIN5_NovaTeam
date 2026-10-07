<?php

namespace Database\Factories;

use App\Enums\FundingSource;
use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Funding;
use App\Models\Infrastructure;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Projets de rénovation du Grand Tunis. Par défaut : projet public en collecte, ouvert depuis
 * quelques semaines, rattaché à une infrastructure créée (ou recyclée avec ->recycle($infrastructures)).
 *
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /** Intitulés et descriptions réalistes par catégorie. */
    protected const PROJECTS = [
        'decontamination' => [
            ['Traitement du nitrate au forage', 'Unité de dénitratation par échange d\'ions : le taux de nitrate dépasse régulièrement le seuil de 50 mg/L fixé par la norme NT 09.14.'],
            ['Nettoyage et désinfection du réservoir', 'Vidange complète, curage des dépôts de manganèse et désinfection avant remise en service et contrôle bactériologique.'],
            ['Désinfection UV en sortie de réservoir', 'Installation d\'un réacteur UV pour sécuriser la qualité bactériologique de l\'eau distribuée aux quartiers desservis.'],
        ],
        'renovation' => [
            ['Remplacement des conduites en fonte grise', 'Les conduites posées dans les années 1960 concentrent la majorité des fuites du secteur : remplacement par du PEHD sur 2 km.'],
            ['Étanchéité et réparation du radier', 'Fissures du radier et suintements constatés à l\'inspection : reprise du béton, nouveau revêtement d\'étanchéité et remplacement des vannes.'],
            ['Sectorisation du réseau de distribution', 'Pose de débitmètres et de vannes de secteur pour localiser rapidement les fuites invisibles et réduire les pertes.'],
        ],
        'surveillance' => [
            ['Capteurs de qualité connectés', 'Mesure en continu du chlore résiduel, de la turbidité et du pH, avec alertes automatiques transmises à la régie.'],
            ['Télésurveillance de la pression du réseau', 'Capteurs de pression télétransmis sur les points hauts du réseau pour anticiper les coupures et les casses.'],
        ],
        'secheresse' => [
            ['Station de pompage solaire', 'Panneaux photovoltaïques et variateurs pour maintenir la pression en été malgré les délestages électriques.'],
            ['Citernes de secours pour les quartiers hauts', 'Citernes mobiles et points de distribution d\'urgence pour les quartiers privés d\'eau lors des pics de consommation estivaux.'],
            ['Récupération et réutilisation des eaux de lavage', 'Recyclage des eaux de lavage des filtres pour économiser plusieurs centaines de mètres cubes par jour en période de sécheresse.'],
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $faker = fake('fr_FR');
        $category = $faker->randomElement(ProjectCategory::cases());
        [$title, $description] = $faker->randomElement(self::PROJECTS[$category->value]);
        $start = Carbon::instance($faker->dateTimeBetween('-3 months', '-2 weeks'))->startOfDay();

        return [
            'title' => $title.' '.$faker->randomElement(['de La Soukra', 'de Mornag', 'd\'Ariana Nord', 'de Ben Arous', 'de Mégrine', 'de Raoued', 'de La Manouba', 'du Belvédère']),
            'category' => $category,
            'description' => $description,
            // Budgets de 40 000 à 400 000 TND, arrondis au millier
            'target_budget' => $faker->numberBetween(40, 400) * 1000,
            'start_date' => $start,
            'end_date' => $start->copy()->addDays($faker->numberBetween(90, 240)),
            'status' => ProjectStatus::EnCollecte,
            'image_path' => null,
            'is_public' => true,
            'infrastructure_id' => Infrastructure::factory(),
        ];
    }

    // ------------------------------------------------------------ États

    /** Brouillon non publié. */
    public function draft(): static
    {
        return $this->state(['status' => ProjectStatus::Brouillon, 'is_public' => false]);
    }

    /** Collecte publique ouverte. */
    public function collecting(): static
    {
        return $this->state(['status' => ProjectStatus::EnCollecte, 'is_public' => true]);
    }

    /** Projet financé : un financement municipal couvre le budget objectif. */
    public function funded(): static
    {
        return $this->state(['status' => ProjectStatus::Finance, 'is_public' => true])
            ->afterCreating(function (Project $project): void {
                Funding::factory()->for($project)->create([
                    'source_type' => FundingSource::Municipalite,
                    'amount' => $project->target_budget,
                    'funded_at' => $project->start_date,
                ]);
            });
    }

    /** Collecte dont la date de fin est passée. */
    public function expired(): static
    {
        return $this->state(fn () => [
            'start_date' => today()->subMonths(4),
            'end_date' => today()->subDays(3),
        ]);
    }

    public function hidden(): static
    {
        return $this->state(['is_public' => false]);
    }

    public function withoutInfrastructure(): static
    {
        return $this->state(['infrastructure_id' => null]);
    }
}
