<?php

namespace Database\Factories;

use App\Enums\FundingSource;
use App\Models\Funding;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Financements de projets : municipalités du Grand Tunis, associations, entreprises et citoyens.
 * Le projet est créé par défaut (ou recyclé) ; la date tombe entre le début de la collecte et aujourd'hui.
 * La référence est générée par le hook `creating` du modèle.
 *
 * @extends Factory<Funding>
 */
class FundingFactory extends Factory
{
    public const MUNICIPALITIES = [
        'Municipalité de Tunis', 'Municipalité de l\'Ariana', 'Municipalité de La Soukra', 'Municipalité de Raoued',
        'Municipalité de Ben Arous', 'Municipalité de Mégrine', 'Municipalité de Mornag', 'Municipalité de La Manouba',
    ];

    public const ASSOCIATIONS = [
        'Association Eau Vive', 'Association Tunisie Écologie', 'Réseau Enviro-Tunisie', 'Association des Amis du Belvédère',
        'Association Citoyenne d\'Ariana', 'Association Ghdir El Golla Durable',
    ];

    public const COMPANIES = [
        'Hydro Carthage SARL', 'Atlas Ingénierie', 'Groupe BTP Médina', 'Tunisie Canalisations SA',
        'Sahel Solaire', 'Cap Bon Industries (mécénat)',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $faker = fake('fr_FR');

        return [
            'project_id' => Project::factory(),
            'source_type' => $faker->randomElement([
                FundingSource::Municipalite,
                FundingSource::Association,
                FundingSource::Entreprise,
                FundingSource::Citoyen, FundingSource::Citoyen, FundingSource::Citoyen,
            ]),
            'user_id' => null,
            'donor_name' => fn (array $attributes) => self::donorFor(self::sourceOf($attributes)),
            'amount' => fn (array $attributes) => self::amountFor(self::sourceOf($attributes)),
            'funded_at' => fn (array $attributes) => self::dateFor($attributes['project_id']),
            'note' => null,
        ];
    }

    // ------------------------------------------------------------ États

    public function municipal(): static
    {
        return $this->state(['source_type' => FundingSource::Municipalite]);
    }

    public function association(): static
    {
        return $this->state(['source_type' => FundingSource::Association]);
    }

    public function company(): static
    {
        return $this->state(['source_type' => FundingSource::Entreprise]);
    }

    /** Contribution d'un citoyen connecté (créé, recyclé ou fourni). */
    public function fromCitizen(?User $user = null): static
    {
        return $this->state([
            'source_type' => FundingSource::Citoyen,
            'user_id' => $user?->id ?? User::factory()->citoyen(),
            'donor_name' => fn (array $attributes) => User::find($attributes['user_id'])?->name ?? fake('fr_FR')->name(),
            'amount' => fn () => fake()->randomElement([10, 20, 20, 30, 50, 50, 50, 100, 100, 150, 200, 500]),
        ]);
    }

    // ------------------------------------------------------------ Valeurs dépendant de la source et du projet

    /** @param array<string, mixed> $attributes */
    protected static function sourceOf(array $attributes): FundingSource
    {
        $source = $attributes['source_type'];

        return $source instanceof FundingSource ? $source : FundingSource::from($source);
    }

    public static function donorFor(FundingSource $source): string
    {
        $faker = fake('fr_FR');

        return match ($source) {
            FundingSource::Municipalite => $faker->randomElement(self::MUNICIPALITIES),
            FundingSource::Association => $faker->randomElement(self::ASSOCIATIONS),
            FundingSource::Entreprise => $faker->randomElement(self::COMPANIES),
            FundingSource::Citoyen => $faker->name(),
        };
    }

    /** Montants réalistes en TND : engagements municipaux élevés, petits dons citoyens. */
    protected static function amountFor(FundingSource $source): int
    {
        $faker = fake();

        return match ($source) {
            FundingSource::Municipalite => $faker->numberBetween(20, 120) * 1000,
            FundingSource::Association => $faker->numberBetween(5, 30) * 500,
            FundingSource::Entreprise => $faker->numberBetween(5, 40) * 1000,
            FundingSource::Citoyen => $faker->randomElement([10, 20, 20, 30, 50, 50, 50, 100, 100, 150, 200, 500]),
        };
    }

    /** Date entre le début de la collecte et aujourd'hui (ou la fin de la collecte si elle est passée). */
    protected static function dateFor(mixed $projectId): Carbon
    {
        $project = $projectId instanceof Project ? $projectId : Project::find($projectId);
        $start = $project?->start_date?->copy() ?? today()->subMonths(2);
        $end = $project?->end_date?->copy()->min(today()) ?? today();

        return $start->gte($end) ? $end : Carbon::instance(fake()->dateTimeBetween($start, $end))->startOfDay();
    }
}
