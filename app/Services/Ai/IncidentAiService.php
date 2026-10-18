<?php

namespace App\Services\Ai;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentType;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\Intervention;
use App\Models\User;
use App\Services\Incidents\UrgencyScorer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fonctions IA du module incidents. Le modèle répond en JSON structuré, validé avant usage
 * (valeurs d'enum autorisées uniquement) ; chaque fonction a un repli sans API (mots-clés, calcul, gabarit).
 * Aucune donnée personnelle n'est envoyée : ni nom, ni e-mail, ni téléphone du déclarant ;
 * les e-mails et numéros présents dans les textes libres sont masqués.
 */
class IncidentAiService
{
    /** Mots-clés du repli de classification (B1), par type. */
    protected const TYPE_KEYWORDS = [
        'contamination' => ['trouble', 'chlore', 'jaune', 'marron', 'odeur', 'goût', 'gout', 'couleur', 'particule', 'boue', 'sale', 'mousse', 'malade'],
        'coupure' => ["plus d'eau", "pas d'eau", 'pression', 'coupure', 'coupée', 'coupee', 'robinet sec', "n'arrive", 'privé', 'prive'],
        'fuite' => ['fuite', 'déborde', 'deborde', 'jaillit', 'inond', 'écoule', 'ecoule', 'coule', 'suint', 'flaque', 'éclat', 'eclat', 'cassé', 'casse'],
    ];

    protected const SEVERITY_KEYWORDS = [
        'critique' => ['hôpital', 'hopital', 'école', 'ecole', 'enfant', 'malade', 'tout le quartier', 'quartier entier', 'toute la cité', 'danger'],
        'elevee' => ['inond', 'jaillit', 'marron', 'odeur', 'plusieurs jours', 'depuis hier', 'immeuble', 'toute la journée', 'rue'],
        'faible' => ['léger', 'leger', 'suint', 'goutte', 'petite', 'peu'],
    ];

    public function __construct(
        private LlmClient $llm,
        private IncidentVisionClient $vision,
        private UrgencyScorer $urgency,
    ) {}

    // ---------------------------------------------------------------- B1 : classification

    /**
     * Type et gravité proposés pour une description libre, avec une justification.
     * $author : déclarant (ou incident), dont l'identité est masquée dans le texte envoyé.
     *
     * @return array{type: string, severity: string, reason: string, source: string} source : « ia » ou « regles »
     */
    public function classify(string $description, Incident|User|null $author = null): array
    {
        $rules = $this->classifyWithRules($description);

        $answer = $this->llmJson(
            'Tu classes des signalements d\'incidents sur un réseau d\'eau potable (Grand Tunis). Réponds en français.',
            "Description du citoyen :\n".$this->mask($description, $author)."\n\nDonne le type (fuite, contamination ou coupure), "
                .'la gravité (faible, moyenne, elevee, critique) et une phrase de justification courte.',
            [
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => array_column(IncidentType::cases(), 'value')],
                    'severity' => ['type' => 'string', 'enum' => array_column(IncidentSeverity::cases(), 'value')],
                    'reason' => ['type' => 'string'],
                ],
                'required' => ['type', 'severity', 'reason'],
                'additionalProperties' => false,
            ],
        );

        // Réponse validée : une valeur d'enum inconnue est ignorée (on garde celle du repli)
        $type = IncidentType::tryFrom((string) ($answer['type'] ?? ''));
        $severity = IncidentSeverity::tryFrom((string) ($answer['severity'] ?? ''));

        if (! $type && ! $severity) {
            return $rules;
        }

        return [
            'type' => $type?->value ?? $rules['type'],
            'severity' => $severity?->value ?? $rules['severity'],
            'reason' => is_string($answer['reason'] ?? null) && trim($answer['reason']) !== '' ? Str::limit(trim($answer['reason']), 300) : $rules['reason'],
            'source' => 'ia',
        ];
    }

    /** @return array{type: string, severity: string, reason: string, source: string} */
    public function classifyWithRules(string $description): array
    {
        $text = Str::lower($description);
        $found = [];
        $scores = [];

        foreach (self::TYPE_KEYWORDS as $type => $keywords) {
            $matches = array_values(array_filter($keywords, fn (string $k) => str_contains($text, $k)));
            $scores[$type] = count($matches);
            $found = [...$found, ...$matches];
        }

        arsort($scores);
        $type = reset($scores) > 0 ? array_key_first($scores) : IncidentType::Fuite->value;

        $severity = IncidentSeverity::Moyenne->value;
        foreach (self::SEVERITY_KEYWORDS as $level => $keywords) {
            if ($hits = array_values(array_filter($keywords, fn (string $k) => str_contains($text, $k)))) {
                $severity = $level;
                $found = [...$found, ...$hits];
                break;
            }
        }

        // Une contamination présente au minimum un risque élevé
        if ($type === IncidentType::Contamination->value && in_array($severity, ['faible', 'moyenne'], true)) {
            $severity = IncidentSeverity::Elevee->value;
        }

        $reason = $found
            ? 'Mots-clés repérés : '.collect($found)->unique()->take(4)->map(fn ($k) => "« {$k} »")->implode(', ').'.'
            : 'Aucun mot-clé caractéristique : type et gravité par défaut, à vérifier.';

        return ['type' => $type, 'severity' => $severity, 'reason' => $reason, 'source' => 'regles'];
    }

    // ---------------------------------------------------------------- B2 : résumé et score d'urgence

    /**
     * Score d'urgence calculé par le code ; le modèle rédige le résumé et la justification.
     *
     * @return array{score: int, summary: string, reason: string}
     */
    public function summarize(Incident $incident): array
    {
        ['score' => $score, 'factors' => $factors] = $this->urgency->score($incident);

        $fallback = [
            'summary' => "{$incident->type->label()} de gravité {$incident->severity->label()} : ".Str::limit($incident->title, 90)
                .($incident->infrastructure ? " (ouvrage {$incident->infrastructure->code})" : '').'.',
            'reason' => "Urgence {$score}/10 : ".implode(', ', $factors).'.',
        ];

        $answer = $this->llmJson(
            'Tu aides la régie des eaux à trier les incidents. Réponds en français, en une phrase par champ.',
            "Incident : {$incident->type->label()}, gravité {$incident->severity->label()}.\nTitre : ".$this->mask($incident->title, $incident)
                ."\nDescription : ".$this->mask($incident->description, $incident)
                ."\nScore d'urgence calculé : {$score}/10. Facteurs : ".implode(', ', $factors)
                ."\n\nRédige un résumé en une phrase et une justification courte du score (sans le modifier).",
            [
                'type' => 'object',
                'properties' => ['summary' => ['type' => 'string'], 'justification' => ['type' => 'string']],
                'required' => ['summary', 'justification'],
                'additionalProperties' => false,
            ],
        );

        return [
            'score' => $score,
            'summary' => $this->text($answer['summary'] ?? null, 400) ?? $fallback['summary'],
            'reason' => $this->text($answer['justification'] ?? null, 400) ?? $fallback['reason'],
        ];
    }

    // ---------------------------------------------------------------- B3 : rapport d'intervention

    /** Rapport structuré (constat, actions, résultat, recommandations) à partir de mots-clés ; jamais enregistré automatiquement. */
    public function draftReport(string $keywords, Intervention $intervention): string
    {
        $incident = $intervention->incident;
        $keywords = $this->mask($keywords, $incident);

        $answer = $this->llmJson(
            'Tu rédiges des comptes rendus d\'intervention pour une régie des eaux tunisienne. Français, ton factuel, phrases courtes.',
            "Incident : {$incident->type->label()} ({$incident->severity->label()}) : ".$this->mask($incident->title, $incident)
                ."\nIntervention : {$intervention->title}, équipe {$intervention->team}, durée prévue {$intervention->formattedDuration()}."
                ."\nNotes de l'équipe : {$keywords}\n\nRédige le rapport en quatre parties.",
            [
                'type' => 'object',
                'properties' => [
                    'constat' => ['type' => 'string'], 'actions' => ['type' => 'string'],
                    'resultat' => ['type' => 'string'], 'recommandations' => ['type' => 'string'],
                ],
                'required' => ['constat', 'actions', 'resultat', 'recommandations'],
                'additionalProperties' => false,
            ],
        );

        $parts = [
            'Constat' => $this->text($answer['constat'] ?? null, 600) ?? "{$incident->type->label()} signalée : {$incident->title}.",
            'Actions' => $this->text($answer['actions'] ?? null, 600) ?? Str::ucfirst($keywords).'.',
            'Résultat' => $this->text($answer['resultat'] ?? null, 600) ?? 'À compléter par l\'équipe.',
            'Recommandations' => $this->text($answer['recommandations'] ?? null, 600) ?? 'Contrôle de l\'ouvrage lors de la prochaine inspection.',
        ];

        return collect($parts)->map(fn (string $text, string $title) => "{$title} : {$text}")->implode("\n");
    }

    // ---------------------------------------------------------------- B4 : diagnostic d'un groupe d'incidents liés

    /**
     * @param  Collection<int, Incident>  $incidents  incidents du groupe (titres et types seulement envoyés)
     * @param  Collection<int, Infrastructure>  $candidates  ouvrages proches (lecture seule)
     * @return array{diagnosis: string, infrastructure_id: ?int}
     */
    public function diagnoseCluster(Collection $incidents, Collection $candidates): array
    {
        $fallback = $this->clusterFallback($incidents, $candidates);

        $answer = $this->llmJson(
            'Tu aides une régie des eaux à repérer une panne commune derrière plusieurs signalements proches. Français, une ou deux phrases.',
            'Signalements (même secteur, quelques heures d\'écart) :'."\n"
                .$incidents->map(fn (Incident $i) => "- {$i->type->label()} : ".$this->mask($i->title, $i).' ('.$i->created_at->format('d/m H:i').')')->implode("\n")
                ."\n\nOuvrages proches :\n".($candidates->isEmpty() ? '- aucun' : $candidates->map(fn (Infrastructure $o) => "- id {$o->id} : {$o->type->label()} {$o->code} ({$o->name})")->implode("\n"))
                ."\n\nPropose un diagnostic court et, si possible, l'id de l'ouvrage suspect (null sinon).",
            [
                'type' => 'object',
                'properties' => ['diagnosis' => ['type' => 'string'], 'infrastructure_id' => ['type' => ['integer', 'null']]],
                'required' => ['diagnosis', 'infrastructure_id'],
                'additionalProperties' => false,
            ],
        );

        $diagnosis = $this->text($answer['diagnosis'] ?? null, 400);
        if ($diagnosis === null) {
            return $fallback;
        }

        // L'ouvrage proposé doit faire partie des candidats envoyés
        $id = $answer['infrastructure_id'] ?? null;

        return ['diagnosis' => $diagnosis, 'infrastructure_id' => $candidates->contains('id', $id) ? (int) $id : $fallback['infrastructure_id']];
    }

    // ---------------------------------------------------------------- B6 : analyse de la photo

    /** Description de la photo et cohérence avec le type choisi (affichée au personnel seulement), ou null. */
    public function analyzePhoto(Incident $incident): ?string
    {
        if (! $incident->photo_path || ! config('ai.incidents.vision') || ! Storage::disk(Incident::PHOTO_DISK)->exists($incident->photo_path)) {
            return null;
        }

        $disk = Storage::disk(Incident::PHOTO_DISK);
        $answer = $this->vision->describeImage(
            'Tu examines la photo jointe à un signalement d\'incident sur un réseau d\'eau potable. Ne décris jamais les personnes. Français.',
            "Type choisi par le citoyen : {$incident->type->label()}. Décris en une phrase ce que montre la photo et dis si elle est cohérente avec ce type.",
            base64_encode($disk->get($incident->photo_path)),
            $disk->mimeType($incident->photo_path) ?: 'image/jpeg',
            [
                'type' => 'object',
                'properties' => ['description' => ['type' => 'string'], 'consistent' => ['type' => 'boolean']],
                'required' => ['description', 'consistent'],
                'additionalProperties' => false,
            ],
        );

        $description = $this->text($answer['description'] ?? null, 400);

        return $description === null ? null
            : "Photo : {$description} ".(($answer['consistent'] ?? false) === true
                ? "(cohérente avec « {$incident->type->label()} »)"
                : "(à vérifier : peu cohérente avec « {$incident->type->label()} »)");
    }

    // ---------------------------------------------------------------- Données personnelles

    /**
     * Masque les e-mails, les numéros de téléphone et l'identité du déclarant (nom complet, puis chaque
     * partie du nom : « M. Trabelsi ») dans un texte libre.
     */
    public function mask(string $text, Incident|User|null $author = null): string
    {
        $user = $author instanceof Incident ? $author->user : $author;

        if ($user) {
            foreach (array_filter([$user->email, $user->phone, $user->name]) as $value) {
                $text = str_ireplace($value, '[masqué]', $text);
            }
            foreach (preg_split('/[\s\-]+/u', (string) $user->name) as $part) {
                if (mb_strlen($part) >= 3) {
                    $text = preg_replace('/\b'.preg_quote($part, '/').'\b/iu', '[masqué]', $text) ?? $text;
                }
            }
        }

        $text = preg_replace('/[\w.+-]+@[\w-]+(\.[\w-]+)+/u', '[e-mail masqué]', $text) ?? $text;

        return preg_replace('/(\+?216[\s.-]?)?\d(?:[\s.-]?\d){7}/u', '[téléphone masqué]', $text) ?? $text;
    }

    // ---------------------------------------------------------------- Outils

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>|null
     */
    private function llmJson(string $system, string $prompt, array $schema): ?array
    {
        if (! config('ai.incidents.enabled', true)) {
            return null;
        }

        return $this->llm->generateJson($system, $prompt, $schema, (int) config('ai.incidents.max_tokens', 1024));
    }

    private function text(mixed $value, int $limit): ?string
    {
        return is_string($value) && trim($value) !== '' ? Str::limit(trim($value), $limit) : null;
    }

    /**
     * @param  Collection<int, Incident>  $incidents
     * @param  Collection<int, Infrastructure>  $candidates
     * @return array{diagnosis: string, infrastructure_id: ?int}
     */
    private function clusterFallback(Collection $incidents, Collection $candidates): array
    {
        $byType = $incidents->countBy(fn (Incident $i) => $i->type->value);
        $dominant = IncidentType::from((string) $byType->sortDesc()->keys()->first());
        $hours = max(1, (int) ceil($incidents->min('created_at')->diffInMinutes($incidents->max('created_at')) / 60));

        // Ouvrage suspect : le plus cité par les signalements, sinon le plus proche des candidats
        $suspectId = $incidents->pluck('infrastructure_id')->filter()->countBy()->sortDesc()->keys()->first()
            ?? $candidates->first()?->id;
        $suspect = $candidates->firstWhere('id', $suspectId) ?? $incidents->firstWhere('infrastructure_id', $suspectId)?->infrastructure;

        $cause = match ($dominant) {
            IncidentType::Coupure => 'panne probable sur la conduite principale ou la station de pompage du secteur',
            IncidentType::Fuite => 'rupture probable d\'une conduite du secteur',
            IncidentType::Contamination => 'contamination probable à la source ou dans le réservoir du secteur',
        };

        return [
            'diagnosis' => "{$incidents->count()} signalements ({$byType->map(fn ($n, $t) => $n.' '.Str::lower(IncidentType::from($t)->label()))->implode(', ')}) en {$hours} h dans le même secteur : {$cause}"
                .($suspect ? ", ouvrage suspect {$suspect->code} ({$suspect->name})" : '').'.',
            'infrastructure_id' => $suspect?->id,
        ];
    }
}
