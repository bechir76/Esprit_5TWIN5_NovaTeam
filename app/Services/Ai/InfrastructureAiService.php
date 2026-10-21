<?php

namespace App\Services\Ai;

use App\Enums\RiskLevel;
use App\Models\Infrastructure;

/**
 * Fonctionnalités IA du module infrastructures. Le score de risque est calculé par le code (RiskScorer) ;
 * le LLM rédige seulement l'explication (2 à 3 phrases) et 3 recommandations à partir du score et des facteurs.
 * La réponse JSON est validée avant usage, sinon le repli (texte généré par des règles) est utilisé.
 * Aucune donnée personnelle n'est envoyée : uniquement des caractéristiques techniques de l'ouvrage.
 */
class InfrastructureAiService
{
    /** Recommandation de repli par facteur, à partir des valeurs brutes. */
    protected const ADVICE = [
        'age' => 'Programmer un diagnostic structurel de l\'ouvrage (:value ans) et inscrire son renouvellement au plan pluriannuel.',
        'status' => 'Planifier une intervention corrective : l\'ouvrage est dans un état dégradé.',
        'material' => 'Contrôler l\'état du matériau (:value) et prévoir le remplacement progressif des parties dégradées.',
        'inspection' => 'Réaliser l\'inspection de l\'ouvrage dans les 15 jours et mettre à jour son état.',
        'incidents' => 'Analyser les incidents des 12 derniers mois pour identifier une cause commune et clore les incidents ouverts.',
        'clusters' => 'Rechercher la panne commune derrière les incidents liés avant toute réparation ponctuelle.',
        'water_quality' => 'Renforcer les prélèvements d\'eau et contrôler la désinfection en sortie d\'ouvrage.',
    ];

    protected const GENERIC_ADVICE = [
        'Maintenir le plan d\'inspection et mettre à jour la fiche après chaque visite.',
        'Suivre l\'évolution du score de risque après chaque nouvel incident ou analyse.',
        'Vérifier la disponibilité des pièces de rechange et des équipes d\'astreinte du secteur.',
    ];

    public function __construct(private LlmClient $llm) {}

    // ---------------------------------------------------------------- B1 : explication du score de risque

    /**
     * Explication et 3 recommandations rédigées par le LLM (source « ia »), ou par des règles (source « regles »).
     *
     * @param  array<string, array{value: mixed, normalized: float, points: float, label: string}>  $factors
     * @return array{summary: string, recommendations: list<string>, source: string}
     */
    public function explainRisk(Infrastructure $infrastructure, int $score, RiskLevel $level, array $factors): array
    {
        $answer = null;

        if (config('infrastructures.ai.enabled', true)) {
            $answer = $this->llm->generateJson(
                'Tu aides la régie des eaux du Grand Tunis à entretenir son réseau d\'eau potable. Réponds en français. '
                .'Le score de risque est déjà calculé : ne le modifie pas et n\'invente aucun chiffre. '
                .'Explique en 2 ou 3 phrases pourquoi l\'ouvrage a ce niveau de risque, à partir des facteurs fournis uniquement, '
                .'puis donne exactement 3 recommandations concrètes et courtes, de la plus urgente à la moins urgente.',
                json_encode($this->prompt($infrastructure, $score, $level, $factors), JSON_UNESCAPED_UNICODE),
                [
                    'type' => 'object',
                    'properties' => [
                        'summary' => ['type' => 'string'],
                        'recommendations' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['summary', 'recommendations'],
                    'additionalProperties' => false,
                ],
                (int) config('infrastructures.ai.max_tokens', 1024),
            );
        }

        return $this->validAnswer($answer) ?? $this->explainWithRules($infrastructure, $score, $level, $factors);
    }

    /**
     * Données envoyées au LLM : caractéristiques techniques, score et facteurs (ni nom, ni code, ni texte libre).
     *
     * @param  array<string, array{points: float, label: string}>  $factors
     * @return array<string, mixed>
     */
    public function prompt(Infrastructure $infrastructure, int $score, RiskLevel $level, array $factors): array
    {
        return [
            'ouvrage' => [
                'type' => $infrastructure->type->label(),
                'age_ans' => $infrastructure->age(),
                'materiau' => $infrastructure->material,
                'etat' => $infrastructure->status->label(),
            ],
            'score_risque' => $score,
            'niveau' => $level->label(),
            'facteurs' => collect($factors)->sortByDesc('points')
                ->map(fn (array $f) => ['description' => $f['label'], 'points' => $f['points']])
                ->values()->all(),
        ];
    }

    /**
     * Repli sans API : explication et recommandations tirées des facteurs qui pèsent le plus.
     *
     * @param  array<string, array{value: mixed, normalized: float, points: float, label: string}>  $factors
     * @return array{summary: string, recommendations: list<string>, source: string}
     */
    public function explainWithRules(Infrastructure $infrastructure, int $score, RiskLevel $level, array $factors): array
    {
        $main = collect($factors)->filter(fn (array $f) => $f['points'] > 0)->sortByDesc('points');
        $labels = $main->take(3)->pluck('label')->values();
        $list = $labels->count() > 1 ? $labels->slice(0, -1)->implode(', ').' et '.$labels->last() : ($labels->first() ?? 'aucun facteur notable');

        $summary = 'Risque '.mb_strtolower($level->label())." ({$score}/100) pour cet ouvrage (".mb_strtolower($infrastructure->type->label())
            ."). Principaux facteurs : {$list}. ".match ($level) {
                RiskLevel::Critique => 'L\'ouvrage doit être traité en priorité.',
                RiskLevel::Eleve => 'Une action est à programmer dans les prochaines semaines.',
                RiskLevel::Moyen => 'Un suivi renforcé est conseillé.',
                RiskLevel::Faible => 'Aucune action urgente : maintenir le plan d\'inspection.',
            };

        $recommendations = $main->filter(fn (array $f) => $f['normalized'] >= 0.3)
            ->map(fn (array $f, string $key) => str_replace(':value', mb_strtolower((string) $f['value']), self::ADVICE[$key]))
            ->values()
            ->merge(self::GENERIC_ADVICE)
            ->unique()
            ->take(3)
            ->values()
            ->all();

        return ['summary' => $summary, 'recommendations' => $recommendations, 'source' => 'regles'];
    }

    // ---------------------------------------------------------------- Validation de la réponse JSON

    /**
     * Réponse valide : explication de 40 à 600 caractères et au moins 3 recommandations de 10 à 300 caractères, sans HTML.
     *
     * @param  array<string, mixed>|null  $answer
     * @return array{summary: string, recommendations: list<string>, source: string}|null
     */
    private function validAnswer(?array $answer): ?array
    {
        $summary = is_string($answer['summary'] ?? null) ? trim(strip_tags($answer['summary'])) : '';
        $recommendations = collect(is_array($answer['recommendations'] ?? null) ? $answer['recommendations'] : [])
            ->filter(fn ($r) => is_string($r))
            ->map(fn (string $r) => trim(strip_tags($r)))
            ->filter(fn (string $r) => mb_strlen($r) >= 10 && mb_strlen($r) <= 300)
            ->take(3)
            ->values();

        if (mb_strlen($summary) < 40 || mb_strlen($summary) > 600 || $recommendations->count() < 3) {
            return null;
        }

        return ['summary' => $summary, 'recommendations' => $recommendations->all(), 'source' => 'ia'];
    }
}
