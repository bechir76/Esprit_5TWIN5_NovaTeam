<?php

namespace Database\Factories;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Incidents du Grand Tunis. L'infrastructure et le déclarant sont créés par défaut ;
 * avec ->recycle($infrastructures)->recycle($citoyens), la factory réutilise les enregistrements existants.
 *
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    /** Adresses par commune (municipalité des zones), avec leurs coordonnées approximatives. */
    protected const ADDRESSES = [
        'Tunis' => [
            ['Avenue de la Liberté, Tunis', 36.8140, 10.1720],
            ['Rue de Marseille, Tunis', 36.8000, 10.1810],
            ['Cité El Khadra, Tunis', 36.8320, 10.1920],
            ['Avenue de Carthage, Tunis', 36.7970, 10.1830],
            ['Rue Bab Saadoun, Tunis', 36.8090, 10.1640],
        ],
        'Ariana' => [
            ['Avenue Habib Bourguiba, Ariana', 36.8620, 10.1950],
            ['Rue Ibn Sina, Ariana', 36.8660, 10.1640],
            ['Avenue de l\'Indépendance, Ariana', 36.8590, 10.1880],
            ['Route de Raoued, Raoued', 36.9300, 10.1850],
            ['Cité Ennasr 2, Ariana', 36.8580, 10.1640],
        ],
        'La Soukra' => [
            ['Avenue Taïeb Mhiri, La Soukra', 36.8750, 10.2470],
            ['Route de La Soukra, Chotrana', 36.8700, 10.2300],
            ['Avenue Habib Bourguiba, La Marsa', 36.8780, 10.3250],
        ],
        'Ben Arous' => [
            ['Avenue de France, Ben Arous', 36.7530, 10.2310],
            ['Cité Ennour, Ben Arous', 36.7450, 10.2220],
            ['Boulevard de l\'Environnement, El Mourouj', 36.7310, 10.2050],
            ['Avenue de la République, Radès', 36.7680, 10.2750],
        ],
        'Mégrine' => [
            ['Avenue de France, Mégrine', 36.7700, 10.2330],
            ['Rue de Sidi Rezig, Mégrine', 36.7630, 10.2400],
        ],
        'La Manouba' => [
            ['Rue Mongi Slim, La Manouba', 36.8080, 10.0970],
            ['Avenue Habib Bourguiba, Den Den', 36.8050, 10.1130],
        ],
    ];

    /** Titres et descriptions réalistes par type d'incident. */
    protected const REPORTS = [
        'fuite' => [
            ['Fuite importante sur la chaussée', 'De l\'eau jaillit de la chaussée depuis ce matin et inonde le trottoir devant les commerces du quartier.'],
            ['Fuite au pied du compteur', 'Un suintement continu au pied du compteur de l\'immeuble forme une flaque qui ne sèche plus depuis trois jours.'],
            ['Conduite qui fuit sous le trottoir', 'Le trottoir s\'affaisse et l\'eau remonte entre les dalles ; on entend le bruit de l\'écoulement la nuit.'],
            ['Bouche à clé qui déborde en continu', 'La bouche à clé devant le numéro 14 déborde en continu et l\'eau ruisselle jusqu\'au carrefour.'],
            ['Fuite visible sur une conduite apparente', 'Une conduite apparente fuit le long du mur du parking, avec un jet visible à chaque reprise de pression.'],
        ],
        'contamination' => [
            ['Eau trouble au robinet', 'L\'eau du robinet sort trouble et légèrement jaunâtre depuis hier soir ; plusieurs voisins constatent la même chose.'],
            ['Odeur de chlore très forte', 'L\'eau a une odeur de chlore inhabituellement forte depuis deux jours, elle pique les yeux à la douche.'],
            ['Goût métallique de l\'eau', 'Un goût métallique persistant est apparu après les travaux de la semaine dernière dans notre rue.'],
            ['Particules noires dans l\'eau', 'Des particules noires apparaissent dans les carafes et le filtre de la machine à café se bouche en un jour.'],
        ],
        'coupure' => [
            ['Coupure d\'eau prolongée dans le quartier', 'Le quartier est privé d\'eau depuis plus de douze heures sans aucun avis de coupure programmée.'],
            ['Pression très faible aux étages', 'La pression est si faible que l\'eau n\'arrive plus aux deuxième et troisième étages de la résidence.'],
            ['Coupures nocturnes répétées', 'L\'eau est coupée chaque nuit entre minuit et six heures depuis une semaine, sans explication de la régie.'],
            ['Plus d\'eau dans tout l\'immeuble', 'Depuis ce matin plus aucun robinet ne coule dans l\'immeuble, les voisins des rues voisines sont aussi touchés.'],
        ],
    ];

    /**
     * Titre, description, adresse et coordonnées dépendent du type et de l'infrastructure :
     * ils sont calculés après les états (fuite(), resolved()…) et l'association de l'infrastructure.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $faker = fake('fr_FR');

        return [
            // La référence est générée par le hook `creating` du modèle
            'infrastructure_id' => Infrastructure::factory(),
            'user_id' => User::factory()->citoyen(),
            // Les fuites sont les incidents les plus fréquents
            'type' => $faker->randomElement([
                IncidentType::Fuite, IncidentType::Fuite, IncidentType::Fuite,
                IncidentType::Coupure, IncidentType::Coupure,
                IncidentType::Contamination,
            ]),
            'title' => fn (array $attributes) => $faker->randomElement(self::REPORTS[self::typeOf($attributes)->value])[0],
            'description' => fn (array $attributes) => self::descriptionFor(self::typeOf($attributes), $attributes['title']),
            'severity' => $faker->randomElement([
                IncidentSeverity::Faible, IncidentSeverity::Faible,
                IncidentSeverity::Moyenne, IncidentSeverity::Moyenne, IncidentSeverity::Moyenne,
                IncidentSeverity::Elevee, IncidentSeverity::Elevee,
                IncidentSeverity::Critique,
            ]),
            'status' => $faker->randomElement([
                IncidentStatus::Signale, IncidentStatus::Signale,
                IncidentStatus::Qualifie, IncidentStatus::Qualifie,
                IncidentStatus::EnIntervention, IncidentStatus::EnIntervention,
                IncidentStatus::Resolu, IncidentStatus::Resolu, IncidentStatus::Resolu, IncidentStatus::Resolu,
                IncidentStatus::Rejete,
            ]),
            'address' => fn (array $attributes) => $this->placeFor($attributes)[0],
            'latitude' => fn (array $attributes) => $this->coordinatesFor($attributes)[0],
            'longitude' => fn (array $attributes) => $this->coordinatesFor($attributes)[1],
            'photo_path' => null,
            'created_at' => $faker->dateTimeBetween('-6 months', 'now'),
            // Résolution quelques heures à quelques jours après le signalement, jamais dans le futur
            'resolved_at' => fn (array $attributes) => self::statusOf($attributes) === IncidentStatus::Resolu
                ? Carbon::make($attributes['created_at'])->addHours($faker->numberBetween(2, 96))->min(now())
                : null,
            'updated_at' => fn (array $attributes) => $attributes['resolved_at'] ?? $attributes['created_at'],
        ];
    }

    // ------------------------------------------------------------ États

    public function reported(): static
    {
        return $this->state(['status' => IncidentStatus::Signale]);
    }

    public function qualified(): static
    {
        return $this->state(['status' => IncidentStatus::Qualifie]);
    }

    public function inProgress(): static
    {
        return $this->state(['status' => IncidentStatus::EnIntervention]);
    }

    public function resolved(): static
    {
        return $this->state(['status' => IncidentStatus::Resolu]);
    }

    public function rejected(): static
    {
        return $this->state(['status' => IncidentStatus::Rejete]);
    }

    public function critical(): static
    {
        return $this->state(['severity' => IncidentSeverity::Critique]);
    }

    public function fuite(): static
    {
        return $this->state(['type' => IncidentType::Fuite]);
    }

    public function contamination(): static
    {
        return $this->state(['type' => IncidentType::Contamination]);
    }

    public function coupure(): static
    {
        return $this->state(['type' => IncidentType::Coupure]);
    }

    /** Signalement non rattaché à un ouvrage précis. */
    public function withoutInfrastructure(): static
    {
        return $this->state(['infrastructure_id' => null]);
    }

    public function withoutCoordinates(): static
    {
        return $this->state(['latitude' => null, 'longitude' => null]);
    }

    // ------------------------------------------------------------ Valeurs dépendant du type et de l'ouvrage

    /** @param array<string, mixed> $attributes */
    protected static function typeOf(array $attributes): IncidentType
    {
        $type = $attributes['type'];

        return $type instanceof IncidentType ? $type : IncidentType::from($type);
    }

    /** @param array<string, mixed> $attributes */
    protected static function statusOf(array $attributes): IncidentStatus
    {
        $status = $attributes['status'];

        return $status instanceof IncidentStatus ? $status : IncidentStatus::from($status);
    }

    /** Description associée au titre tiré (ou une description du même type pour un titre libre). */
    protected static function descriptionFor(IncidentType $type, string $title): string
    {
        foreach (self::REPORTS[$type->value] as [$candidate, $description]) {
            if ($candidate === $title) {
                return $description;
            }
        }

        return fake('fr_FR')->randomElement(self::REPORTS[$type->value])[1];
    }

    /**
     * Adresse dans la commune de l'ouvrage lié (ou n'importe où dans le Grand Tunis).
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: string, 1: float, 2: float}
     */
    protected function placeFor(array $attributes): array
    {
        $municipality = $attributes['infrastructure_id']
            ? Infrastructure::with('zone')->find($attributes['infrastructure_id'])?->zone?->municipality
            : null;

        return fake('fr_FR')->randomElement(self::ADDRESSES[$municipality] ?? array_merge(...array_values(self::ADDRESSES)));
    }

    /**
     * Coordonnées de l'adresse, légèrement décalées pour ne pas superposer les repères.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: float, 1: float}
     */
    protected function coordinatesFor(array $attributes): array
    {
        foreach (self::ADDRESSES as $places) {
            foreach ($places as [$address, $lat, $lng]) {
                if ($address === $attributes['address']) {
                    return [round($lat + fake()->randomFloat(4, -0.004, 0.004), 6), round($lng + fake()->randomFloat(4, -0.004, 0.004), 6)];
                }
            }
        }

        return [round(fake()->randomFloat(6, 36.70, 36.95), 6), round(fake()->randomFloat(6, 10.05, 10.35), 6)];
    }
}
