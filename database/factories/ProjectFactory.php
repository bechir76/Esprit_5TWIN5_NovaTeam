<?php

namespace Database\Factories;

use App\Enums\FundingStatus;
use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Funding;
use App\Models\Infrastructure;
use App\Models\Project;
use Database\Factories\Concerns\UsesTunisiaProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Par défaut : projet public en collecte (le slug est généré par le modèle).
 *
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    use UsesTunisiaProvider;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => fake()->randomElement(ProjectCategory::cases()),
            // Intitulé et description selon la catégorie (« Remplacement de la conduite principale de Mégrine »)
            'title' => fn (array $attributes) => fake()->tunisianProjectTitle($attributes['category']),
            'description' => fn (array $attributes) => fake()->tunisianProjectDescription($attributes['category']),
            // Budget en dinars tunisiens, arrondi au millier
            'target_budget' => fake()->numberBetween(40, 400) * 1000,
            'start_date' => fake()->dateTimeBetween('-3 months', '-2 weeks'),
            'end_date' => fake()->dateTimeBetween('+1 month', '+6 months'),
            'status' => ProjectStatus::Open,
            'image_path' => null,
            'is_public' => true,
            'infrastructure_id' => Infrastructure::factory(),
        ];
    }

    /** Brouillon non publié. */
    public function draft(): static
    {
        return $this->state(['status' => ProjectStatus::Draft, 'is_public' => false]);
    }

    /** Collecte publique ouverte. */
    public function open(): static
    {
        return $this->state(['status' => ProjectStatus::Open, 'is_public' => true]);
    }

    /** Alias de open() (nom utilisé par les tests des autres modules). */
    public function collecting(): static
    {
        return $this->open();
    }

    /** Financé : un financement municipal confirmé couvre le budget. */
    public function funded(): static
    {
        return $this->state(['status' => ProjectStatus::Funded, 'is_public' => true])->withTargetCovered();
    }

    /** Collecte publique à 85 % de l'objectif (budget élevé : les petits dons ne la font pas basculer). */
    public function almostFunded(): static
    {
        return $this->open()->state(['target_budget' => fake()->numberBetween(150, 300) * 1000])
            ->afterCreating(fn (Project $project) => Funding::factory()->municipal()->confirmed()->for($project)->create([
                'amount' => round((float) $project->target_budget * 0.85),
                'funded_at' => $project->start_date,
            ]));
    }

    /** Collecte échue sans objectif atteint (les dons sont à rembourser). */
    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => ProjectStatus::Failed,
            'is_public' => true,
            'start_date' => fake()->dateTimeBetween('-8 months', '-5 months'),
            'end_date' => fake()->dateTimeBetween('-2 months', '-1 week'),
        ]);
    }

    /** En travaux : financé, collecte terminée. */
    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => ProjectStatus::InProgress,
            'is_public' => true,
            'start_date' => fake()->dateTimeBetween('-10 months', '-6 months'),
            'end_date' => fake()->dateTimeBetween('-3 months', '-1 month'),
        ])->withTargetCovered();
    }

    /** Terminé : financé, travaux réceptionnés. */
    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => ProjectStatus::Completed,
            'is_public' => true,
            'start_date' => fake()->dateTimeBetween('-18 months', '-12 months'),
            'end_date' => fake()->dateTimeBetween('-10 months', '-6 months'),
        ])->withTargetCovered();
    }

    /** Collecte dont la date limite est passée. */
    public function expired(): static
    {
        return $this->state(['start_date' => today()->subMonths(4), 'end_date' => today()->subDays(3)]);
    }

    public function hidden(): static
    {
        return $this->state(['is_public' => false]);
    }

    /** Un financement municipal confirmé couvre tout le budget. */
    protected function withTargetCovered(): static
    {
        return $this->afterCreating(fn (Project $project) => Funding::factory()->municipal()->for($project)->create([
            'amount' => $project->target_budget,
            'funded_at' => $project->start_date,
            'status' => FundingStatus::Confirmed,
        ]));
    }
}
