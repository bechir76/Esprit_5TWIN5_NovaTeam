<?php

namespace Database\Factories\Providers;

use App\Enums\FundingSource;
use App\Enums\IncidentType;
use App\Enums\InfrastructureType;
use App\Enums\ProjectCategory;
use Faker\Generator;
use Faker\Provider\Base;
use Illuminate\Support\Str;

/**
 * Données tunisiennes (Grand Tunis) pour les factories : communes, adresses, coordonnées,
 * noms, téléphones, textes d'incidents, ouvrages, projets, financeurs et laboratoires.
 * Seul endroit du projet qui contient des listes de données ; les factories l'utilisent via fake().
 */
class TunisiaProvider extends Base
{
    /** Communes du Grand Tunis : commune => [gouvernorat, latitude du centre, longitude du centre]. */
    protected const COMMUNES = [
        'Tunis' => ['Tunis', 36.800, 10.180],
        'Le Bardo' => ['Tunis', 36.809, 10.134],
        'La Marsa' => ['Tunis', 36.878, 10.320],
        'El Menzah' => ['Tunis', 36.840, 10.170],
        'Ariana' => ['Ariana', 36.862, 10.195],
        'La Soukra' => ['Ariana', 36.875, 10.247],
        'Raoued' => ['Ariana', 36.930, 10.185],
        'Ettadhamen' => ['Ariana', 36.840, 10.100],
        'Ben Arous' => ['Ben Arous', 36.753, 10.231],
        'Mégrine' => ['Ben Arous', 36.770, 10.233],
        'Radès' => ['Ben Arous', 36.768, 10.275],
        'El Mourouj' => ['Ben Arous', 36.731, 10.205],
        'Mornag' => ['Ben Arous', 36.705, 10.290],
        'La Manouba' => ['Manouba', 36.808, 10.097],
        'Den Den' => ['Manouba', 36.805, 10.113],
    ];

    /** Abréviation du gouvernorat dans les codes ouvrage (identique à Zone::GOVERNORATES). */
    protected const GOVERNORATE_CODES = ['Tunis' => 'TUN', 'Ariana' => 'ARI', 'Ben Arous' => 'BAR', 'Manouba' => 'MAN'];

    protected const SECTORS = ['', ' Nord', ' Sud', ' Centre', ' Est', ' Ouest'];

    protected const STREETS = [
        'Avenue Habib Bourguiba', 'Avenue de la Liberté', 'Rue de Marseille', 'Cité El Khadra', 'Cité Ennasr',
        'Avenue de Carthage', 'Rue Ibn Sina', 'Rue Mongi Slim', 'Avenue Taïeb Mhiri', 'Rue de Rome',
        'Avenue Hédi Chaker', 'Rue Ibn Khaldoun', 'Avenue de France', 'Cité Ennour', 'Avenue de l\'Indépendance',
        'Rue Farhat Hached', 'Avenue Mohamed V', 'Rue Ali Belhouane',
    ];

    protected const FIRST_NAMES = [
        'Mohamed', 'Ahmed', 'Mohamed Amine', 'Youssef', 'Karim', 'Hamza', 'Anis', 'Skander', 'Bilel', 'Oussama', 'Nizar', 'Slim',
        'Sana', 'Ines', 'Rim', 'Mariem', 'Fatma', 'Amel', 'Nour', 'Salma', 'Yasmine', 'Asma', 'Olfa', 'Hela',
    ];

    protected const LAST_NAMES = [
        'Ben Salah', 'Trabelsi', 'Gharbi', 'Jlassi', 'Mejri', 'Bouazizi', 'Chaabane', 'Hammami', 'Ben Ali', 'Khelifi',
        'Dridi', 'Ayari', 'Mabrouk', 'Ferchichi', 'Zouari', 'Belhadj', 'Sassi', 'Jebali', 'Masmoudi', 'Ouertani',
    ];

    protected const EMAIL_DOMAINS = ['gmail.com', 'yahoo.fr', 'topnet.tn'];

    /** Titres et descriptions de signalements par type d'incident. */
    protected const INCIDENT_REPORTS = [
        'fuite' => [
            ['Fuite importante sur la chaussée', 'De l\'eau jaillit de la chaussée depuis ce matin et inonde le trottoir devant les commerces.'],
            ['Fuite au pied du compteur', 'Un suintement continu au pied du compteur de l\'immeuble forme une flaque depuis trois jours.'],
            ['Conduite qui fuit sous le trottoir', 'Le trottoir s\'affaisse et l\'eau remonte entre les dalles ; on entend l\'écoulement la nuit.'],
            ['Bouche à clé qui déborde', 'La bouche à clé devant l\'école déborde en continu et l\'eau ruisselle jusqu\'au carrefour.'],
            ['Fuite sur une conduite apparente', 'Une conduite apparente fuit le long du mur du parking, avec un jet visible à chaque reprise de pression.'],
            ['Eau qui coule devant la mosquée', 'Une fuite devant la mosquée du quartier rend la chaussée glissante depuis hier soir.'],
        ],
        'contamination' => [
            ['Eau trouble au robinet', 'L\'eau du robinet sort trouble et jaunâtre depuis hier soir ; plusieurs voisins constatent la même chose.'],
            ['Forte odeur de chlore', 'L\'eau a une odeur de chlore inhabituellement forte depuis deux jours, elle pique les yeux.'],
            ['Goût métallique de l\'eau', 'Un goût métallique persistant est apparu après les travaux de la semaine dernière dans la rue.'],
            ['Particules noires dans l\'eau', 'Des particules noires apparaissent dans les carafes et le filtre se bouche en un jour.'],
            ['Eau marron après la coupure', 'Après la coupure de ce matin, l\'eau est marron pendant plusieurs minutes à chaque ouverture du robinet.'],
        ],
        'coupure' => [
            ['Coupure d\'eau prolongée dans le quartier', 'Le quartier est privé d\'eau depuis plus de douze heures sans avis de coupure programmée.'],
            ['Pression très faible aux étages', 'La pression est si faible que l\'eau n\'arrive plus aux deuxième et troisième étages de la résidence.'],
            ['Coupures nocturnes répétées', 'L\'eau est coupée chaque nuit entre minuit et six heures depuis une semaine, sans explication.'],
            ['Plus d\'eau dans tout l\'immeuble', 'Depuis ce matin plus aucun robinet ne coule dans l\'immeuble, les rues voisines sont aussi touchées.'],
            ['Pas d\'eau pendant l\'Aïd', 'Coupure générale dans la cité depuis la veille de l\'Aïd, les familles s\'approvisionnent en bouteilles.'],
        ],
    ];

    /** Lieux utilisés dans les noms d'ouvrages. */
    protected const PLACES = [
        'Belvédère', 'Ennasr', 'El Menzah 6', 'Borj Louzir', 'Chotrana', 'Mnihla', 'Ghdir El Golla', 'Sidi Rezig',
        'Ezzahra', 'Hammam Lif', 'Fouchana', 'Oued Ellil', 'Douar Hicher', 'Bab Saadoun', 'Montplaisir', 'Lafayette',
    ];

    protected const INTERVENTION_TITLES = [
        'Recherche de fuite au corrélateur', 'Réparation de la conduite', 'Remplacement d\'un tronçon de conduite',
        'Remplacement de la vanne d\'isolement', 'Remplacement du compteur', 'Prélèvements qualité sur trois points',
        'Purge du réseau secondaire', 'Rechloration du réservoir', 'Diagnostic de la station de pompage', 'Réglage du surpresseur',
    ];

    protected const INTERVENTION_REPORTS = [
        'Intervention réalisée, réseau remis en eau et pression contrôlée à 3 bar.',
        'Tronçon endommagé remplacé, essai d\'étanchéité concluant. Chaussée à reprendre par la municipalité.',
        'Prélèvements conformes après purge ; chlore résiduel mesuré à 0,35 mg/L.',
        'Pièce défectueuse remplacée, aucun écoulement constaté après deux heures d\'observation.',
        'Contrôle sur place : aucune anomalie sur le réseau public, problème lié à l\'installation privée.',
    ];

    /** Débuts d'intitulés de projets par catégorie (complétés par « de <lieu> »). */
    protected const PROJECT_TITLES = [
        'decontamination' => ['Traitement du nitrate au forage', 'Nettoyage et désinfection du réservoir', 'Désinfection UV en sortie du réservoir'],
        'renovation' => ['Remplacement de la conduite principale', 'Étanchéité du réservoir', 'Sectorisation du réseau de distribution'],
        'surveillance' => ['Capteurs de qualité connectés', 'Télésurveillance de la pression du réseau'],
        'secheresse' => ['Station de pompage solaire', 'Citernes de secours pour les quartiers hauts', 'Récupération des eaux de lavage'],
    ];

    protected const PROJECT_DESCRIPTIONS = [
        'decontamination' => 'Le taux mesuré dépasse régulièrement le seuil réglementaire : les travaux sécuriseront l\'eau distribuée aux familles du secteur.',
        'renovation' => 'Les équipements posés dans les années 1970 concentrent la majorité des fuites du secteur : ils seront remplacés et le réseau sectorisé.',
        'surveillance' => 'Mesure en continu de la pression et du chlore résiduel, avec alertes automatiques transmises à la SONEDE et à la municipalité.',
        'secheresse' => 'Maintenir la distribution en été malgré les délestages électriques et les pics de consommation des quartiers hauts.',
    ];

    protected const ASSOCIATIONS = [
        'Association Tunisie Écologie', 'Réseau Enviro-Tunisie', 'Association des Amis du Belvédère',
        'Association Citoyenne de l\'Ariana', 'Association Nour pour l\'environnement', 'Association Eau et Vie Tunisie',
    ];

    protected const COMPANIES = [
        'Hydro Carthage SARL', 'Groupe BTP Médina', 'Tunisie Canalisations SA', 'Sahel Solaire', 'Cap Bon Industries', 'Elloumi Ingénierie',
    ];

    protected const LABORATORIES = [
        'Laboratoire central SONEDE', 'Laboratoire régional SONEDE Ariana', 'Laboratoire régional SONEDE Ben Arous',
        'Institut Pasteur de Tunis', 'Laboratoire national de contrôle des eaux Tunis', 'Centre international des technologies de l\'environnement de Tunis',
    ];

    protected const SAMPLING_POINTS = [
        'Sortie réservoir', 'Entrée réseau de distribution', 'Robinet école Ibn Khaldoun', 'Robinet hôpital Charles Nicolle',
        'Borne fontaine place de la République', 'Sortie station de traitement', 'Sortie forage', 'Poste de chloration aval',
    ];

    /** Ajoute le provider au générateur s'il n'y est pas déjà (appelé par le trait UsesTunisiaProvider). */
    public static function register(Generator $faker): void
    {
        foreach ($faker->getProviders() as $provider) {
            if ($provider instanceof self) {
                return;
            }
        }

        $faker->addProvider(new self($faker));
    }

    // ---------------------------------------------------------------- Lieux

    public function tunisianCommune(): string
    {
        return static::randomElement(array_keys(self::COMMUNES));
    }

    /** Gouvernorat d'une commune du Grand Tunis (Tunis, Ariana, Ben Arous, Manouba). */
    public function tunisianGovernorate(?string $commune = null): string
    {
        return self::COMMUNES[$commune ?? $this->tunisianCommune()][0] ?? 'Tunis';
    }

    /** Nom de zone de desserte : commune + secteur (« Ariana Nord »). */
    public function tunisianZoneName(?string $commune = null): string
    {
        return ($commune ?? $this->tunisianCommune()).static::randomElement(self::SECTORS);
    }

    /** « de » avec élision : de Tunis, d'Ariana, d'El Mourouj, du Bardo. */
    public function deCommune(?string $commune = null): string
    {
        $commune ??= $this->tunisianCommune();

        return match (true) {
            str_starts_with($commune, 'Le ') => 'du '.substr($commune, 3),
            (bool) preg_match('/^[AEIOUÉ]/u', $commune) => 'd\''.$commune,
            default => 'de '.$commune,
        };
    }

    /** Adresse « rue, commune ». */
    public function tunisianAddress(?string $commune = null): string
    {
        return static::randomElement(self::STREETS).', '.($commune ?? $this->tunisianCommune());
    }

    /**
     * Coordonnées proches du centre de la commune (± 1 km environ), dans l'emprise du Grand Tunis.
     *
     * @return array{0: float, 1: float} [latitude, longitude]
     */
    public function tunisianCoordinates(?string $commune = null): array
    {
        [, $lat, $lng] = self::COMMUNES[$commune ?? $this->tunisianCommune()] ?? self::COMMUNES['Tunis'];

        return [
            round(min(36.95, max(36.70, $lat + static::randomFloat(4, -0.01, 0.01))), 6),
            round(min(10.35, max(10.05, $lng + static::randomFloat(4, -0.01, 0.01))), 6),
        ];
    }

    // ---------------------------------------------------------------- Personnes

    public function tunisianFirstName(): string
    {
        return static::randomElement(self::FIRST_NAMES);
    }

    public function tunisianLastName(): string
    {
        return static::randomElement(self::LAST_NAMES);
    }

    public function tunisianName(): string
    {
        return $this->tunisianFirstName().' '.$this->tunisianLastName();
    }

    /** Téléphone mobile tunisien : +216 XX XXX XXX (Ooredoo, Orange, Tunisie Télécom…). */
    public function tunisianPhone(): string
    {
        return '+216 '.static::randomElement(['2', '4', '5', '9']).static::numerify('# ### ###');
    }

    /** E-mail personnel à partir du nom (« sana.benali27@gmail.com »). */
    public function tunisianEmail(?string $name = null): string
    {
        return Str::slug($name ?? $this->tunisianName(), '.').static::numberBetween(1, 999).'@'.static::randomElement(self::EMAIL_DOMAINS);
    }

    /** E-mail professionnel du personnel (« karim.trabelsi@aquasecure.tn »). */
    public function tunisianStaffEmail(?string $name = null): string
    {
        return Str::slug($name ?? $this->tunisianName(), '.').static::numberBetween(1, 99).'@aquasecure.tn';
    }

    // ---------------------------------------------------------------- Incidents et interventions

    /**
     * Signalement réaliste pour un type d'incident (fuite, contamination, coupure).
     *
     * @return array{0: string, 1: string} [titre, description]
     */
    public function incidentReport(IncidentType|string $type): array
    {
        return static::randomElement(self::INCIDENT_REPORTS[$type instanceof IncidentType ? $type->value : $type]);
    }

    /** Description qui correspond à un titre de signalement (ou une description du même type). */
    public function incidentDescription(string $title, IncidentType|string $type = 'fuite'): string
    {
        foreach (self::INCIDENT_REPORTS as $reports) {
            foreach ($reports as [$candidate, $description]) {
                if ($candidate === $title) {
                    return $description;
                }
            }
        }

        return $this->incidentReport($type)[1];
    }

    public function interventionTitle(): string
    {
        return static::randomElement(self::INTERVENTION_TITLES);
    }

    public function interventionReport(): string
    {
        return static::randomElement(self::INTERVENTION_REPORTS);
    }

    // ---------------------------------------------------------------- Ouvrages

    /** Code ouvrage cohérent avec le type : RES-ARI-104, CND-TUN-212… (numéros 100 à 999). */
    public function infrastructureCode(InfrastructureType|string $type): string
    {
        $prefix = ($type instanceof InfrastructureType ? $type : InfrastructureType::from($type))->codePrefix();

        return $prefix.'-'.static::randomElement(self::GOVERNORATE_CODES).'-'.static::numberBetween(100, 999);
    }

    /** Nom d'ouvrage plausible selon le type (« Réservoir de Chotrana Haut », « Forage d'Ezzahra F2 »). */
    public function tunisianInfrastructureName(InfrastructureType|string $type): string
    {
        $type = $type instanceof InfrastructureType ? $type : InfrastructureType::from($type);
        $place = static::randomElement(self::PLACES);

        return match ($type) {
            InfrastructureType::Reservoir => 'Réservoir '.$place.static::randomElement(['', ' Haut', ' Bas']),
            InfrastructureType::Conduite => 'Conduite DN '.static::randomElement([200, 400, 600, 800]).' '.$place,
            InfrastructureType::Pompage => 'Station de pompage '.$place.static::randomElement(['', ' 2']),
            InfrastructureType::Traitement => 'Station de traitement '.$place,
            InfrastructureType::Forage => 'Forage '.$place.' F'.static::numberBetween(1, 9),
        };
    }

    /** Capacité plausible dans l'unité du type : m³ (réservoir), km (conduite), L/s (stations et forages). */
    public function infrastructureCapacity(InfrastructureType|string $type): float
    {
        return match ($type instanceof InfrastructureType ? $type : InfrastructureType::from($type)) {
            InfrastructureType::Reservoir => static::numberBetween(2, 30) * 500,
            InfrastructureType::Conduite => static::randomFloat(1, 0.5, 12),
            InfrastructureType::Pompage => static::numberBetween(4, 60) * 5,
            InfrastructureType::Traitement => static::numberBetween(5, 40) * 100,
            InfrastructureType::Forage => static::numberBetween(10, 60),
        };
    }

    // ---------------------------------------------------------------- Projets et financements

    /** Intitulé de projet (« Remplacement de la conduite principale de Mégrine »). */
    public function tunisianProjectTitle(ProjectCategory|string|null $category = null): string
    {
        $category = $category instanceof ProjectCategory ? $category->value : ($category ?? static::randomElement(array_keys(self::PROJECT_TITLES)));

        return static::randomElement(self::PROJECT_TITLES[$category]).' '.$this->deCommune();
    }

    public function tunisianProjectDescription(ProjectCategory|string|null $category = null): string
    {
        $category = $category instanceof ProjectCategory ? $category->value : ($category ?? 'renovation');

        return self::PROJECT_DESCRIPTIONS[$category];
    }

    /** Financeur selon la source : « Municipalité de … », association, entreprise ou citoyen. */
    public function tunisianDonor(FundingSource|string $source): string
    {
        return match ($source instanceof FundingSource ? $source : FundingSource::from($source)) {
            FundingSource::Municipalite => 'Municipalité '.$this->deCommune(),
            FundingSource::Association => static::randomElement(self::ASSOCIATIONS),
            FundingSource::Entreprise => static::randomElement(self::COMPANIES),
            FundingSource::Citoyen => $this->tunisianName(),
        };
    }

    /** Montant en dinars tunisiens (DT) selon la source : engagements municipaux élevés, petits dons citoyens. */
    public function fundingAmount(FundingSource|string $source): float
    {
        return match ($source instanceof FundingSource ? $source : FundingSource::from($source)) {
            FundingSource::Municipalite => static::numberBetween(10, 60) * 1000,
            FundingSource::Association => static::numberBetween(4, 30) * 500,
            FundingSource::Entreprise => static::numberBetween(5, 30) * 1000,
            FundingSource::Citoyen => static::randomElement([10, 20, 20, 50, 50, 100, 150, 200, 500]),
        };
    }

    // ---------------------------------------------------------------- Qualité de l'eau

    public function tunisianLaboratory(): string
    {
        return static::randomElement(self::LABORATORIES);
    }

    public function samplingPoint(): string
    {
        return static::randomElement(self::SAMPLING_POINTS);
    }
}
