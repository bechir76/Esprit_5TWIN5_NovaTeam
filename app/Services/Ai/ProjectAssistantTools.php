<?php

namespace App\Services\Ai;

use App\Enums\ProjectCategory;
use App\Models\Project;

/**
 * Outils de l'assistant (liste blanche, lecture seule) : requêtes Eloquent sur les projets publiés,
 * jamais de SQL généré par le LLM, aucune donnée personnelle (ni nom ni e-mail de donateur).
 */
class ProjectAssistantTools
{
    /** @return list<array<string, mixed>> définitions envoyées au LLM */
    public function definitions(): array
    {
        return [
            [
                'name' => 'getProjectStats',
                'description' => 'Montant collecté, objectif, avancement, statut et jours restants des projets publiés dont le titre contient le texte donné.',
                'strict' => true,
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['query' => ['type' => 'string', 'description' => 'Mots du titre du projet, par exemple « réservoir La Soukra ».']],
                    'required' => ['query'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'listAlmostFunded',
                'description' => 'Projets en collecte financés à au moins min_percent % de leur objectif.',
                'strict' => true,
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['min_percent' => ['type' => 'integer', 'description' => 'Seuil en %, 80 par défaut.']],
                    'required' => ['min_percent'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'listOpenProjects',
                'description' => 'Projets ouverts aux dons, éventuellement d\'une catégorie (decontamination, renovation, surveillance, secheresse ; vide = toutes).',
                'strict' => true,
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['category' => ['type' => 'string', 'enum' => ['', ...array_map(fn ($c) => $c->value, ProjectCategory::cases())]]],
                    'required' => ['category'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /** Exécute un outil de la liste blanche ; un nom inconnu renvoie une erreur sans rien exécuter. */
    public function run(string $name, array $input): string
    {
        $result = match ($name) {
            'getProjectStats' => $this->getProjectStats((string) ($input['query'] ?? '')),
            'listAlmostFunded' => $this->listAlmostFunded((int) ($input['min_percent'] ?? config('projects.almost_funded_percent'))),
            'listOpenProjects' => $this->listOpenProjects((string) ($input['category'] ?? '')),
            default => ['error' => 'Outil inconnu.'],
        };

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Mots ignorés dans la recherche par titre. */
    private const STOP_WORDS = ['combien', 'été', 'collecté', 'collectés', 'collecte', 'pour', 'projet', 'projets', 'des', 'les', 'une', 'quel',
        'quelle', 'quels', 'montant', 'avec', 'sur', 'dans', 'est', 'sont', 'financement', 'financé', 'reste', 'objectif'];

    /** Projets dont le titre contient tous les mots significatifs, sinon au moins un. */
    public function getProjectStats(string $query): array
    {
        $words = collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(mb_substr($query, 0, 200))) ?: [])
            ->filter(fn ($w) => mb_strlen($w) >= 3 && ! in_array($w, self::STOP_WORDS, true))
            ->unique()->values();

        if ($words->isEmpty()) {
            return [];
        }

        $search = fn (bool $all) => Project::query()->published()->withCollected()
            ->where(fn ($q) => $words->each(fn ($w) => $all ? $q->where('title', 'like', "%{$w}%") : $q->orWhere('title', 'like', "%{$w}%")))
            ->limit(3)->get();

        $projects = $search(true);

        return ($projects->isNotEmpty() ? $projects : $search(false))->map(fn (Project $p) => $this->summary($p))->values()->all();
    }

    /** @return list<array<string, mixed>> */
    public function listAlmostFunded(int $minPercent): array
    {
        return Project::query()->open()->withCollected()->fundedAtLeast(max(1, min(99, $minPercent)))
            ->orderByProgress()->limit(5)->get()->map(fn (Project $p) => $this->summary($p))->values()->all();
    }

    /** @return list<array<string, mixed>> */
    public function listOpenProjects(string $category): array
    {
        return Project::query()->open()->withCollected()->ofCategory(ProjectCategory::tryFrom($category))
            ->orderBy('end_date')->limit(6)->get()->map(fn (Project $p) => $this->summary($p))->values()->all();
    }

    /** @return array<string, mixed> */
    private function summary(Project $project): array
    {
        return [
            'titre' => $project->title,
            'categorie' => $project->category->label(),
            'statut' => $project->status->label(),
            'collecte_dt' => $project->collected_amount,
            'objectif_dt' => (float) $project->target_budget,
            'avancement_pct' => $project->progress_percent,
            'jours_restants' => $project->days_left,
            'donateurs' => $project->donors_count,
        ];
    }
}
