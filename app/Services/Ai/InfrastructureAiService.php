<?php

namespace App\Services\Ai;

use App\Enums\InfrastructureStatus;
use App\Enums\InfrastructureType;
use App\Enums\RiskLevel;
use App\Models\Infrastructure;
use App\Models\Zone;
use Illuminate\Support\Str;

/**
 * Fonctionnalités IA du module infrastructures. Les calculs restent dans le code ; le LLM rédige ou traduit :
 * B1 explication du score de risque (RiskScorer) et 3 recommandations, B2 phrase de recherche => filtres autorisés,
 * B3 texte du plan de maintenance d'une zone (ouvrages choisis et classés par MaintenancePlanner).
 * Chaque réponse JSON est validée avant usage, sinon le repli (règles ou mots-clés) est utilisé.
 * Aucune donnée personnelle n'est envoyée : caractéristiques techniques des ouvrages, noms des zones et
 * phrase de recherche dont les e-mails et numéros de téléphone sont masqués.
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

    // ---------------------------------------------------------------- B2 : recherche en langage naturel

    /**
     * Phrase libre => filtres autorisés de la liste (type, état, zone, niveau de risque, inspection en retard,
     * âge minimum, tri). Le LLM ne renvoie que ce JSON, validé par Infrastructure::normalizeFilters() ;
     * aucune requête SQL n'est générée. Repli : détection de mots-clés.
     *
     * @return array{filters: array{type: ?string, status: ?string, zone: ?int, risk: ?string, inspection: ?string, min_age: ?int, sort: ?string}, source: string}
     */
    public function parseSearch(string $query): array
    {
        $query = self::maskContacts(mb_substr(trim($query), 0, 200));
        $zones = Zone::query()->orderBy('name')->pluck('name', 'id');
        $answer = null;

        if ($query !== '' && config('infrastructures.ai.enabled', true)) {
            $answer = $this->llm->generateJson(
                'Tu convertis une recherche en français sur les ouvrages d\'un réseau d\'eau potable en filtres JSON. '
                .'Utilise uniquement les valeurs autorisées du schéma ; chaîne vide, 0 ou false quand la phrase ne précise rien. '
                .'« risque élevé / critique » est un niveau de risque ; « état critique » ou « ouvrages critiques » est un état. N\'invente aucun filtre.',
                json_encode([
                    'recherche' => $query,
                    'zones' => $zones->map(fn (string $name, int $id) => ['id' => $id, 'nom' => $name])->values()->all(),
                    'types' => InfrastructureType::options(),
                    'etats' => InfrastructureStatus::options(),
                    'niveaux_de_risque' => RiskLevel::options(),
                ], JSON_UNESCAPED_UNICODE),
                [
                    'type' => 'object',
                    'properties' => [
                        'type' => ['type' => 'string', 'enum' => ['', ...array_column(InfrastructureType::cases(), 'value')]],
                        'status' => ['type' => 'string', 'enum' => ['', ...array_column(InfrastructureStatus::cases(), 'value')]],
                        'zone_id' => ['type' => 'integer'],
                        'risk_level' => ['type' => 'string', 'enum' => ['', ...array_column(RiskLevel::cases(), 'value')]],
                        'inspection_overdue' => ['type' => 'boolean'],
                        'min_age' => ['type' => 'integer'],
                        'sort' => ['type' => 'string', 'enum' => ['', 'risk']],
                    ],
                    'required' => ['type', 'status', 'zone_id', 'risk_level', 'inspection_overdue', 'min_age', 'sort'],
                    'additionalProperties' => false,
                ],
                512,
            );
        }

        $filters = $this->validSearch($answer, $zones->keys()->all());

        return $filters !== null
            ? ['filters' => $filters, 'source' => 'ia']
            : ['filters' => $this->searchWithRules($query, $zones->all()), 'source' => 'regles'];
    }

    /**
     * Repli sans API : mots-clés (types, états, niveaux de risque, zones, « en retard », « plus de 40 ans », « les plus à risque »).
     *
     * @param  array<int, string>  $zones  id => nom
     * @return array{type: ?string, status: ?string, zone: ?int, risk: ?string, inspection: ?string, min_age: ?int, sort: ?string}
     */
    public function searchWithRules(string $query, array $zones): array
    {
        $text = ' '.Str::lower(Str::ascii($query)).' ';

        $type = collect([
            'reservoir' => '/reservoir|chateau d.eau/',
            'conduite' => '/conduite|canalisation|tuyau|reseau de distribution/',
            'pompage' => '/pompage|pompe/',
            'traitement' => '/traitement|potabilisation/',
            'forage' => '/forage|puits/',
        ])->search(fn (string $pattern) => preg_match($pattern, $text) === 1) ?: null;

        $risk = null;
        if (preg_match('/risque\s+(tres\s+)?(eleve|critique|moyen|faible)/', $text, $m)) {
            $risk = ['eleve' => 'eleve', 'critique' => 'critique', 'moyen' => 'moyen', 'faible' => 'faible'][$m[2]];
        }
        // « critique » hors « risque critique » : état de l'ouvrage
        $withoutRisk = preg_replace('/risque\s+(tres\s+)?\w+/', ' ', $text);
        $status = match (true) {
            preg_match('/critique/', $withoutRisk) === 1 => 'critique',
            preg_match('/a surveiller|surveill/', $withoutRisk) === 1 => 'a_surveiller',
            preg_match('/bon etat|en bon/', $withoutRisk) === 1 => 'bon',
            default => null,
        };

        $minAge = null;
        if (preg_match('/(?:plus de|de plus de|au moins|depassant|>)\s*(\d{1,3})\s*ans|(\d{1,3})\s*ans\s*(?:et plus|ou plus|minimum)/', $text, $m)) {
            $minAge = (int) ($m[1] !== '' ? $m[1] : $m[2]);
        }

        // Zone : nom le plus long présent dans la phrase
        $zone = collect($zones)->sortByDesc(fn (string $name) => mb_strlen($name))
            ->search(fn (string $name) => str_contains($text, ' '.Str::lower(Str::ascii($name)).' ') || str_contains($text, Str::lower(Str::ascii($name))));

        $filters = Infrastructure::normalizeFilters([
            'type' => $type,
            'status' => $status,
            'zone' => $zone === false ? null : (string) $zone,
            'risk' => $risk,
            'inspection' => preg_match('/retard|pas (ete )?inspecte|jamais inspecte|inspection depassee/', $text) ? 'overdue' : null,
            'min_age' => $minAge !== null ? (string) $minAge : null,
            'sort' => preg_match('/plus (a )?risque|plus risque|par risque|plus dangereu|prioritaire/', $text) ? 'risk' : null,
        ]);

        return array_diff_key($filters, ['q' => true]);
    }

    /**
     * Réponse valide : objet JSON dont chaque valeur est autorisée (enum, zone existante, âge de 1 à 150 ans).
     *
     * @param  array<string, mixed>|null  $answer
     * @param  list<int>  $zoneIds
     * @return array{type: ?string, status: ?string, zone: ?int, risk: ?string, inspection: ?string, min_age: ?int, sort: ?string}|null
     */
    private function validSearch(?array $answer, array $zoneIds): ?array
    {
        if (! is_array($answer) || array_diff(['type', 'status', 'zone_id', 'risk_level'], array_keys($answer)) !== []) {
            return null;
        }

        $zone = is_int($answer['zone_id']) && in_array($answer['zone_id'], $zoneIds, true) ? (string) $answer['zone_id'] : null;

        $filters = Infrastructure::normalizeFilters([
            'type' => is_string($answer['type']) ? $answer['type'] : null,
            'status' => is_string($answer['status']) ? $answer['status'] : null,
            'zone' => $zone,
            'risk' => is_string($answer['risk_level']) ? $answer['risk_level'] : null,
            'inspection' => ($answer['inspection_overdue'] ?? false) === true ? 'overdue' : null,
            'min_age' => is_int($answer['min_age'] ?? null) ? (string) $answer['min_age'] : null,
            'sort' => is_string($answer['sort'] ?? null) ? $answer['sort'] : null,
        ]);

        return array_diff_key($filters, ['q' => true]);
    }

    /** E-mails et numéros de téléphone retirés d'un texte libre avant l'envoi au LLM. */
    private static function maskContacts(string $text): string
    {
        $text = preg_replace('/[\w.+-]+@[\w-]+(\.[\w-]+)+/u', '[e-mail masqué]', $text) ?? $text;

        return preg_replace('/(\+?216[\s.-]?)?\d(?:[\s.-]?\d){7}/u', '[téléphone masqué]', $text) ?? $text;
    }

    // ---------------------------------------------------------------- B3 : plan de maintenance d'une zone

    /**
     * Plan priorisé : les ouvrages et leur ordre viennent du code (MaintenancePlanner) ; le LLM rédige un résumé,
     * puis une action et sa justification pour chaque ouvrage, repéré par son rang (ni code, ni nom envoyés).
     *
     * @param  list<array<string, mixed>>  $items
     * @return array{summary: string, actions: list<array<string, mixed>>, source: string}
     */
    public function maintenancePlan(Zone $zone, array $items): array
    {
        if ($items === []) {
            return ['summary' => "Aucun ouvrage dans la zone {$zone->name} : pas de plan de maintenance.", 'actions' => [], 'source' => 'regles'];
        }

        $answer = null;

        if (config('infrastructures.ai.enabled', true)) {
            $answer = $this->llm->generateJson(
                'Tu aides la régie des eaux du Grand Tunis à planifier la maintenance de son réseau. Réponds en français. '
                .'Les ouvrages sont déjà sélectionnés et classés par priorité : ne change ni la liste ni l\'ordre et n\'invente aucun chiffre. '
                .'Rédige un résumé du plan en 1 ou 2 phrases, puis pour chaque rang une action concrète et une justification courte fondée sur les données fournies.',
                json_encode([
                    'zone' => ['nom' => $zone->name, 'population_desservie' => $zone->population_served],
                    'ouvrages' => collect($items)->map(fn (array $i) => [
                        'rang' => $i['rank'],
                        'type' => $i['type'],
                        'etat' => $i['status'],
                        'score_risque' => $i['risk_score'],
                        'niveau_risque' => $i['risk_level'],
                        'jours_retard_inspection' => $i['days_overdue'],
                        'incidents_ouverts' => $i['open_incidents'],
                        'incidents_12_mois' => $i['recent_incidents'],
                        'facteurs_principaux' => $i['main_factors'],
                    ])->all(),
                ], JSON_UNESCAPED_UNICODE),
                [
                    'type' => 'object',
                    'properties' => [
                        'summary' => ['type' => 'string'],
                        'actions' => ['type' => 'array', 'items' => [
                            'type' => 'object',
                            'properties' => ['rank' => ['type' => 'integer'], 'action' => ['type' => 'string'], 'justification' => ['type' => 'string']],
                            'required' => ['rank', 'action', 'justification'],
                            'additionalProperties' => false,
                        ]],
                    ],
                    'required' => ['summary', 'actions'],
                    'additionalProperties' => false,
                ],
                (int) config('infrastructures.ai.max_tokens', 1024) * 2,
            );
        }

        return $this->validPlan($answer, $items) ?? $this->maintenancePlanWithRules($zone, $items);
    }

    /**
     * Repli sans API : action déduite de la situation de chaque ouvrage, justification à partir des chiffres.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array{summary: string, actions: list<array<string, mixed>>, source: string}
     */
    public function maintenancePlanWithRules(Zone $zone, array $items): array
    {
        $actions = collect($items)->map(function (array $i) {
            $action = match (true) {
                $i['days_overdue'] > 0 => "Réaliser l'inspection en retard ({$i['days_overdue']} j) et mettre à jour l'état de l'ouvrage.",
                $i['status'] === 'Critique' => 'Planifier une intervention corrective : l\'ouvrage est dans un état critique.',
                $i['open_incidents'] > 0 => "Traiter les {$i['open_incidents']} incident(s) ouvert(s) et rechercher une cause commune.",
                $i['recommendation'] !== null => $i['recommendation'],
                default => 'Inspection préventive selon le plan d\'inspections.',
            };

            $facts = array_filter([
                $i['risk_score'] !== null ? "risque {$i['risk_score']}/100 (".mb_strtolower((string) $i['risk_level']).')' : 'risque non évalué',
                'état « '.mb_strtolower($i['status']).' »',
                $i['days_overdue'] > 0 ? "inspection en retard de {$i['days_overdue']} j" : null,
                $i['open_incidents'] > 0 ? "{$i['open_incidents']} incident(s) ouvert(s)" : null,
                $i['recent_incidents'] > 0 ? "{$i['recent_incidents']} incident(s) en 12 mois" : null,
            ]);

            return $i + ['action' => $action, 'justification' => 'Priorité n° '.$i['rank'].' : '.implode(', ', $facts).'.'];
        })->all();

        $first = $items[0];

        return [
            'summary' => "Plan de maintenance de la zone {$zone->name} : ".count($items).' ouvrage(s) prioritaire(s), en commençant par '
                ."{$first['code']} ({$first['name']}).",
            'actions' => $actions,
            'source' => 'regles',
        ];
    }

    /**
     * Réponse valide : résumé de 20 à 600 caractères et, pour chaque rang fourni, une action (10 à 300) et une justification (10 à 400).
     *
     * @param  array<string, mixed>|null  $answer
     * @param  list<array<string, mixed>>  $items
     * @return array{summary: string, actions: list<array<string, mixed>>, source: string}|null
     */
    private function validPlan(?array $answer, array $items): ?array
    {
        $summary = is_string($answer['summary'] ?? null) ? trim(strip_tags($answer['summary'])) : '';
        $byRank = collect(is_array($answer['actions'] ?? null) ? $answer['actions'] : [])
            ->filter(fn ($a) => is_array($a) && is_int($a['rank'] ?? null) && is_string($a['action'] ?? null) && is_string($a['justification'] ?? null))
            ->keyBy('rank');

        if (mb_strlen($summary) < 20 || mb_strlen($summary) > 600) {
            return null;
        }

        $actions = [];
        foreach ($items as $item) {
            $action = trim(strip_tags((string) ($byRank[$item['rank']]['action'] ?? '')));
            $justification = trim(strip_tags((string) ($byRank[$item['rank']]['justification'] ?? '')));

            if (mb_strlen($action) < 10 || mb_strlen($action) > 300 || mb_strlen($justification) < 10 || mb_strlen($justification) > 400) {
                return null;
            }

            $actions[] = $item + ['action' => $action, 'justification' => $justification];
        }

        return ['summary' => $summary, 'actions' => $actions, 'source' => 'ia'];
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
