<?php

namespace Database\Factories;

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
            'status' => ProjectStatus::EnCollecte,
            'image_path' => null,
            'is_public' => true,
            'infrastructure_id' => Infrastructure::factory(),
        ];
    }

    /** Brouillon non publié. */
    public function draft(): static
    {
        return $this->state(['status' => ProjectStatus::Brouillon, 'is_public' => false]);
    }

    public function collecting(): static
    {
        return $this->state(['status' => ProjectStatus::EnCollecte, 'is_public' => true]);
    }

    /** Financé : un financement municipal couvre le budget. */
    public function funded(): static
    {
        return $this->state(['status' => ProjectStatus::Finance])
            ->afterCreating(fn (Project $project) => Funding::factory()->municipal()->for($project)->create([
                'amount' => $project->target_budget,
                'funded_at' => $project->start_date,
            ]));
    }

    /** Collecte terminée. */
    public function expired(): static
    {
        return $this->state(['start_date' => today()->subMonths(4), 'end_date' => today()->subDays(3)]);
    }

    public function hidden(): static
    {
        return $this->state(['is_public' => false]);
    }
}
