<?php

namespace App\Services\Ai;

use App\Models\Project;

/**
 * Fonctionnalités IA du module projets. Le LLM rédige seulement (explication, description, reformulation)
 * ou appelle des outils en lecture seule ; les calculs restent dans le code (ProjectScoring, FundingForecast).
 * Chaque réponse JSON est validée avant usage, sinon le repli (texte gabarit ou règles) est utilisé.
 * Aucune donnée personnelle n'est envoyée au LLM.
 */
class ProjectAiService
{
    public const REFUSAL = 'Je ne peux répondre qu\'aux questions sur les projets de rénovation d\'AquaSecure et leurs financements.';

    public function __construct(
        private LlmClient $llm,
        private ProjectAssistantTools $tools,
    ) {}

    // ---------------------------------------------------------------- B1 : explication de la priorité

    /** @param  array<string, array{label: string, normalized: float}>  $factors */
    public function priorityReason(array $factors, int $score): string
    {
        $data = $this->llm->generateJson(
            'Tu aides une régie des eaux tunisienne à prioriser ses projets. Explique en une ou deux phrases courtes, en français, '
            .'pourquoi un projet a le score de priorité donné, à partir des facteurs fournis uniquement. Ne recalcule pas le score.',
            json_encode(['score' => $score, 'facteurs' => array_map(fn ($f) => $f['label'], $factors)], JSON_UNESCAPED_UNICODE),
            self::textSchema('reason'),
            512,
        );

        return self::validText($data, 'reason', 20, 400) ?? self::priorityTemplate($factors, $score);
    }

    /** @param  array<string, array{label: string, normalized: float}>  $factors */
    public static function priorityTemplate(array $factors, int $score): string
    {
        $level = $score >= 60 ? 'élevée' : ($score >= 35 ? 'moyenne' : 'faible');
        $main = collect($factors)->sortByDesc(fn ($f) => $f['normalized'] * ($f['weight'] ?? 1))->take(2)->pluck('label')->implode(' et ');

        return "Priorité {$level} ({$score}/100) : {$main}.";
    }

    // ---------------------------------------------------------------- B2 : description d'un projet

    /** @param  array{title: string, category: string, budget: float|int|string|null, infrastructure: ?string}  $input */
    public function describe(array $input): string
    {
        $data = $this->llm->generateJson(
            'Tu rédiges la description publique d\'un projet de rénovation du réseau d\'eau potable du Grand Tunis, en français, '
            .'en 3 ou 4 phrases factuelles : problème constaté, travaux prévus, bénéfices pour les habitants. Pas de chiffres inventés.',
            json_encode($input, JSON_UNESCAPED_UNICODE),
            self::textSchema('description'),
            1024,
        );

        return self::validText($data, 'description', 60, 1500) ?? self::descriptionTemplate($input);
    }

    /** @param  array{title: string, category: string, budget: float|int|string|null, infrastructure: ?string}  $input */
    public static function descriptionTemplate(array $input): string
    {
        $where = $input['infrastructure'] ? " sur l'ouvrage {$input['infrastructure']}" : '';
        $budget = $input['budget'] ? ' Le budget prévu est de '.Project::money($input['budget']).' DT.' : '';

        return 'Projet de '.mb_strtolower($input['category'])." « {$input['title']} »{$where}. "
            .'Les travaux visent à sécuriser la distribution d\'eau potable et à réduire les incidents signalés par les habitants du secteur.'
            .$budget.' Chaque dinar collecté est affecté à ce projet et les dépenses sont publiées à chaque étape.';
    }

    // ---------------------------------------------------------------- B3 : message de prévision

    /** @param  array{message: string, probability: string, reached: bool}  $forecast */
    public function forecastMessage(Project $project, array $forecast): string
    {
        if ($forecast['reached']) {
            return $forecast['message'];
        }

        $data = $this->llm->generateJson(
            'Reformule pour des citoyens, en une phrase encourageante et exacte en français, la prévision de financement fournie. '
            .'Garde la date et le sens (objectif atteint ou non) sans rien ajouter.',
            json_encode(['projet' => $project->title, 'prevision' => $forecast['message'], 'probabilite' => $forecast['probability']], JSON_UNESCAPED_UNICODE),
            self::textSchema('message'),
            256,
        );

        return self::validText($data, 'message', 10, 300) ?? $forecast['message'];
    }

    // ---------------------------------------------------------------- B5 : assistant

    public function answer(string $question): string
    {
        $question = trim(mb_substr($question, 0, 500));

        if ($question === '') {
            return 'Posez une question sur les projets, par exemple « Quels projets sont presque financés ? ».';
        }

        $answer = $this->llm->answerWithTools(
            'Tu es l\'assistant du site AquaSecure (projets de rénovation du réseau d\'eau du Grand Tunis). Réponds en français, brièvement, '
            .'uniquement à partir des résultats des outils. Montants en DT. Si la question ne porte pas sur les projets ou leurs financements, '
            .'réponds exactement : « '.self::REFUSAL.' »',
            $question,
            $this->tools->definitions(),
            fn (string $name, array $input) => $this->tools->run($name, $input),
        );

        return $answer !== null ? mb_substr($answer, 0, 1500) : $this->ruleBasedAnswer($question);
    }

    /** Repli sans LLM : reconnaissance de mots-clés et mêmes outils en lecture seule. */
    public function ruleBasedAnswer(string $question): string
    {
        $q = mb_strtolower($question);

        if (preg_match('/presque|bientôt|bientot|proche.*objectif/u', $q)) {
            $projects = $this->tools->listAlmostFunded(config('projects.almost_funded_percent'));

            return $projects
                ? 'Projets presque financés : '.collect($projects)->map(fn ($p) => "{$p['titre']} ({$p['avancement_pct']} %)")->implode(', ').'.'
                : 'Aucun projet n\'est financé à plus de '.config('projects.almost_funded_percent').' % pour le moment.';
        }

        if (preg_match('/collect|combien|montant|financ|objectif/u', $q)) {
            $stats = $this->tools->getProjectStats($q);

            if ($stats) {
                return collect($stats)->map(fn ($p) => "{$p['titre']} : ".Project::money($p['collecte_dt']).' DT collectés sur '.Project::money($p['objectif_dt'])." DT ({$p['avancement_pct']} %).")->implode(' ');
            }
        }

        if (preg_match('/projet|don|collecte|financ|travaux|réservoir|conduite|forage/u', $q)) {
            $open = $this->tools->listOpenProjects('');

            return $open
                ? 'Projets ouverts aux dons : '.collect($open)->map(fn ($p) => "{$p['titre']} ({$p['avancement_pct']} %, {$p['jours_restants']} j)")->implode(', ').'.'
                : 'Aucun projet n\'est ouvert aux dons pour le moment.';
        }

        return self::REFUSAL;
    }

    // ---------------------------------------------------------------- Validation des réponses JSON

    /** @return array<string, mixed> schéma {key: string} */
    private static function textSchema(string $key): array
    {
        return ['type' => 'object', 'properties' => [$key => ['type' => 'string']], 'required' => [$key], 'additionalProperties' => false];
    }

    /** Texte valide : chaîne de longueur attendue, sans balise HTML. */
    private static function validText(?array $data, string $key, int $min, int $max): ?string
    {
        $text = is_array($data) && is_string($data[$key] ?? null) ? trim(strip_tags($data[$key])) : null;

        return $text !== null && mb_strlen($text) >= $min && mb_strlen($text) <= $max ? $text : null;
    }
}
