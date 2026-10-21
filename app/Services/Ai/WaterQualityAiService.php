<?php

namespace App\Services\Ai;

use App\Enums\WaterParameter;
use App\Enums\WqriLevel;
use App\Models\Infrastructure;
use App\Models\Measurement;
use App\Models\Zone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fonctions IA du module qualité de l'eau. Le service ne calcule pas : il rédige et explique.
 * Tous les calculs (WQRI, Z-score, conformité) restent dans le code déterministe.
 * Chaque méthode a un repli sans API (règles ou texte gabarit).
 *
 * B1 : explication d'une anomalie statistique (AnomalyDetector)
 * B2 : prédiction de dégradation (infrastructure + historique)
 * B3 : résumé exécutif mensuel pour la direction
 * B4 : explication pédagogique publique d'une mesure hors seuil
 * B5 : recommandation de fréquence d'échantillonnage
 * A1 : résumé WQRI d'une zone (WqriScorer)
 */
class WaterQualityAiService
{
    public function __construct(private LlmClient $llm) {}

    // ---------------------------------------------------------------- B1 : explication d'anomalie

    /**
     * Explique une anomalie statistique en langage naturel (pour le personnel).
     *
     * @param  array{is_anomaly: bool, z_score: ?float, historical_avg: ?float, historical_stddev: ?float, samples: int, reason: string}  $stats
     */
    public function explainAnomaly(Measurement $measurement, array $stats): string
    {
        if (! config('water_quality.ai.enabled', true) || ! $stats['is_anomaly']) {
            return $stats['reason'];
        }

        $param = $measurement->parameter instanceof WaterParameter
            ? $measurement->parameter
            : WaterParameter::from((string) $measurement->parameter);

        $answer = $this->llm->generateJson(
            'Tu analyses des résultats d\'analyses d\'eau potable pour une régie des eaux tunisienne. Réponds en français, en une phrase factuelle.',
            json_encode([
                'parametre' => $param->label(),
                'unite' => $param->unit(),
                'valeur_mesuree' => (float) $measurement->value,
                'moyenne_historique' => $stats['historical_avg'],
                'ecart_type' => $stats['historical_stddev'],
                'z_score' => $stats['z_score'],
                'seuil_reglementaire' => ['min' => $param->min(), 'max' => $param->max()],
                'conforme' => $measurement->is_compliant,
            ], JSON_UNESCAPED_UNICODE),
            self::textSchema('explication'),
            512,
        );

        return self::validText($answer, 'explication', 20, 400) ?? $stats['reason'];
    }

    // ---------------------------------------------------------------- B2 : prédiction de dégradation

    /**
     * Rédige une prédiction de dégradation de la qualité de l'eau pour une infrastructure.
     * Le LLM n'invente pas de données : il synthétise les facteurs fournis.
     */
    public function predictDegradation(Infrastructure $infrastructure): string
    {
        $windowMonths = (int) config('water_quality.prediction.window_months', 6);
        $since = now()->subMonths($windowMonths);

        // Analyses récentes
        $analyses = $infrastructure->waterAnalyses()
            ->where('sampled_at', '>=', $since)
            ->with('measurements')
            ->orderByDesc('sampled_at')
            ->limit(10)
            ->get();

        $nonCompliantCount = $analyses->filter(fn ($a) => $a->status?->value === 'non_conforme')->count();
        $totalAnalyses = $analyses->count();

        // Tendance par paramètre (moyenne des 3 dernières vs 3 précédentes)
        $trends = $this->parameterTrends($infrastructure->id, $since);

        $fallback = $this->degradationFallback($infrastructure, $nonCompliantCount, $totalAnalyses, $trends);

        if (! config('water_quality.ai.enabled', true)) {
            return $fallback;
        }

        $answer = $this->llm->generateJson(
            'Tu es un expert en qualité des eaux potables. Rédige en 2 ou 3 phrases une prédiction de dégradation potentielle, '
            .'basée uniquement sur les données fournies. Ne cite pas de noms de personnes. Réponds en français.',
            json_encode([
                'infrastructure' => [
                    'type' => $infrastructure->type->label(),
                    'age_ans' => $infrastructure->age(),
                    'etat' => $infrastructure->status->label(),
                    'score_risque' => $infrastructure->risk_score,
                ],
                'analyses_6_mois' => $totalAnalyses,
                'non_conformes' => $nonCompliantCount,
                'tendances_parametres' => $trends,
            ], JSON_UNESCAPED_UNICODE),
            self::textSchema('prediction'),
            768,
        );

        return self::validText($answer, 'prediction', 40, 600) ?? $fallback;
    }

    // ---------------------------------------------------------------- B3 : résumé exécutif mensuel

    /**
     * Génère un résumé exécutif pour une période donnée (admin).
     *
     * @return array{summary: string, highlights: list<string>, source: string}
     */
    public function executiveSummary(Carbon $from, Carbon $to): array
    {
        $stats = $this->periodStats($from, $to);
        $fallback = $this->executiveSummaryFallback($stats, $from, $to);

        if (! config('water_quality.ai.enabled', true)) {
            return $fallback;
        }

        $answer = $this->llm->generateJson(
            'Tu rédiges le résumé exécutif mensuel qualité de l\'eau pour la direction d\'une régie des eaux tunisienne. '
            .'Utilise uniquement les données fournies. Ne cite pas de noms de personnes. Chiffres en DT pour les montants. Réponds en français.',
            json_encode([
                'periode' => ['du' => $from->format('d/m/Y'), 'au' => $to->format('d/m/Y')],
                'statistiques' => $stats,
            ], JSON_UNESCAPED_UNICODE),
            [
                'type' => 'object',
                'properties' => [
                    'summary' => ['type' => 'string'],
                    'highlights' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['summary', 'highlights'],
                'additionalProperties' => false,
            ],
            1024,
        );

        $summary = is_string($answer['summary'] ?? null) ? trim(strip_tags($answer['summary'])) : '';
        $highlights = collect(is_array($answer['highlights'] ?? null) ? $answer['highlights'] : [])
            ->filter(fn ($h) => is_string($h))
            ->map(fn (string $h) => trim(strip_tags($h)))
            ->filter(fn (string $h) => mb_strlen($h) >= 10)
            ->take(5)
            ->values()
            ->all();

        if (mb_strlen($summary) < 40 || empty($highlights)) {
            return $fallback;
        }

        return ['summary' => Str::limit($summary, 800), 'highlights' => $highlights, 'source' => 'ia'];
    }

    // ---------------------------------------------------------------- B4 : explication pédagogique publique

    /**
     * Génère un texte simple pour le citoyen expliquant une mesure hors seuil.
     * Repli par règles si l'IA est indisponible (aucun appel réseau).
     */
    public function publicExplanation(WaterParameter $parameter, float $value): string
    {
        // Repli rapide par règles : toujours disponible, plus fiable pour une page publique
        $ruleText = $this->publicExplanationByRules($parameter, $value);

        if (! config('water_quality.ai.enabled', true)) {
            return $ruleText;
        }

        $answer = $this->llm->generateJson(
            'Tu expliques simplement à un citoyen un résultat d\'analyse d\'eau. '
            .'1 ou 2 phrases maximum, sans jargon technique, en tutoyant. Réponds en français.',
            json_encode([
                'parametre' => $parameter->label(),
                'valeur' => $value,
                'unite' => $parameter->unit(),
                'seuil_max' => $parameter->max(),
                'seuil_min' => $parameter->min(),
                'conforme' => $parameter->isCompliant($value),
            ], JSON_UNESCAPED_UNICODE),
            self::textSchema('explication'),
            300,
        );

        return self::validText($answer, 'explication', 20, 300) ?? $ruleText;
    }

    /**
     * Repli sans API : explication par règles pour chaque paramètre (toujours disponible).
     */
    public function publicExplanationByRules(WaterParameter $parameter, float $value): string
    {
        $compliant = $parameter->isCompliant($value);

        if ($compliant) {
            return "Le taux de {$parameter->label()} est normal aujourd'hui.";
        }

        return match ($parameter) {
            WaterParameter::Chlore => $value > ($parameter->max() ?? 0.5)
                ? "Le taux de chlore est légèrement élevé ({$value} {$parameter->unit()}). Laisse couler l'eau froide 2 minutes avant de la boire."
                : "Le taux de chlore est insuffisant ({$value} {$parameter->unit()}). Préfère l'eau en bouteille jusqu'à la prochaine analyse.",

            WaterParameter::Ph => $value > ($parameter->max() ?? 8.5)
                ? "Le pH de l'eau est élevé ({$value}). L'eau reste consommable mais peut avoir un léger goût."
                : "Le pH de l'eau est bas ({$value}). Consulte la régie pour plus d'informations.",

            WaterParameter::Turbidite => "L'eau est légèrement trouble ({$value} {$parameter->unit()}). "
                ."Laisse couler l'eau quelques instants ; si le trouble persiste, signale-le à la régie.",

            WaterParameter::Nitrate => "Le taux de nitrate est élevé ({$value} {$parameter->unit()}). "
                ."Ce résultat concerne surtout les nourrissons de moins de 6 mois : consulte un médecin si nécessaire.",

            WaterParameter::Ecoli => "Une bactérie E. coli a été détectée. L'eau n'est pas recommandée pour la consommation directe. "
                ."Fais-la bouillir ou utilise de l'eau en bouteille. Une intervention est en cours.",

            WaterParameter::Conductivite => "La conductivité de l'eau est élevée ({$value} {$parameter->unit()}). "
                ."L'eau reste consommable mais peut contenir plus de minéraux qu'habituellement.",
        };
    }

    // ---------------------------------------------------------------- B5 : fréquence d'échantillonnage

    /**
     * Recommande une fréquence d'échantillonnage en jours pour une infrastructure.
     *
     * @return array{days: int, reason: string, source: string}
     */
    public function samplingFrequencyRecommendation(Infrastructure $infrastructure): array
    {
        $windowMonths = 3;
        $since = now()->subMonths($windowMonths);

        $recentAnalyses = $infrastructure->waterAnalyses()
            ->where('sampled_at', '>=', $since)
            ->count();

        $nonCompliant = $infrastructure->waterAnalyses()
            ->where('sampled_at', '>=', $since)
            ->where('status', 'non_conforme')
            ->count();

        $openContaminations = $infrastructure->incidents()
            ->where('type', 'contamination')
            ->open()
            ->count();

        $fallback = $this->frequencyFallback($infrastructure, $nonCompliant, $openContaminations);

        if (! config('water_quality.ai.enabled', true)) {
            return $fallback;
        }

        $answer = $this->llm->generateJson(
            'Tu conseilles une régie des eaux sur la fréquence des prélèvements d\'eau. '
            .'Réponds en JSON avec un nombre de jours entre prélèvements (entre 7 et 90) et une justification courte en français.',
            json_encode([
                'infrastructure' => ['type' => $infrastructure->type->label(), 'etat' => $infrastructure->status->label()],
                'analyses_3_mois' => $recentAnalyses,
                'non_conformes_3_mois' => $nonCompliant,
                'incidents_contamination_ouverts' => $openContaminations,
                'score_risque' => $infrastructure->risk_score,
            ], JSON_UNESCAPED_UNICODE),
            [
                'type' => 'object',
                'properties' => [
                    'jours' => ['type' => 'integer', 'minimum' => 7, 'maximum' => 90],
                    'justification' => ['type' => 'string'],
                ],
                'required' => ['jours', 'justification'],
                'additionalProperties' => false,
            ],
            512,
        );

        $days = is_int($answer['jours'] ?? null) && $answer['jours'] >= 7 && $answer['jours'] <= 90
            ? (int) $answer['jours']
            : null;
        $reason = self::validText($answer, 'justification', 10, 300);

        if ($days === null || $reason === null) {
            return $fallback;
        }

        return ['days' => $days, 'reason' => $reason, 'source' => 'ia'];
    }

    // ---------------------------------------------------------------- A1 : résumé WQRI

    /**
     * Rédige le résumé textuel de l'indice WQRI pour une zone.
     *
     * @param  array<string, array{normalized: float, weight: float, label: string}>  $factors
     */
    public function wqriSummary(Zone $zone, int $score, WqriLevel $level, array $factors): string
    {
        $fallback = $this->wqriFallback($zone, $score, $level, $factors);

        if (! config('water_quality.ai.enabled', true)) {
            return $fallback;
        }

        $answer = $this->llm->generateJson(
            'Tu expliques l\'indice de risque sanitaire d\'une zone de distribution d\'eau potable. '
            .'2 ou 3 phrases en français, fondées uniquement sur les données fournies.',
            json_encode([
                'zone' => $zone->name,
                'score_wqri' => $score,
                'niveau' => $level->label(),
                'facteurs' => collect($factors)
                    ->sortByDesc(fn ($f) => $f['normalized'] * $f['weight'])
                    ->map(fn ($f) => $f['label'])
                    ->values()
                    ->all(),
            ], JSON_UNESCAPED_UNICODE),
            self::textSchema('resume'),
            512,
        );

        return self::validText($answer, 'resume', 40, 500) ?? $fallback;
    }

    // ---------------------------------------------------------------- Helpers internes

    /** Données statistiques d'une période pour le résumé exécutif (B3). */
    private function periodStats(Carbon $from, Carbon $to): array
    {
        $analyses = DB::table('water_analyses')
            ->whereBetween('sampled_at', [$from, $to])
            ->select([
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'non_conforme' THEN 1 ELSE 0 END) as non_compliant"),
                DB::raw("SUM(CASE WHEN status = 'validee' THEN 1 ELSE 0 END) as validated"),
            ])
            ->first();

        $measurements = DB::table('measurements')
            ->join('water_analyses', 'measurements.water_analysis_id', '=', 'water_analyses.id')
            ->whereBetween('water_analyses.sampled_at', [$from, $to])
            ->select([
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN is_compliant = 0 THEN 1 ELSE 0 END) as non_compliant'),
                'parameter',
            ])
            ->groupBy('parameter')
            ->get()
            ->keyBy('parameter');

        $topViolations = $measurements
            ->filter(fn ($m) => $m->non_compliant > 0)
            ->sortByDesc('non_compliant')
            ->take(3)
            ->map(fn ($m) => [
                'parametre' => WaterParameter::tryFrom($m->parameter)?->label() ?? $m->parameter,
                'depassements' => (int) $m->non_compliant,
            ])
            ->values()
            ->all();

        $total = (int) ($analyses?->total ?? 0);
        $nonCompliant = (int) ($analyses?->non_compliant ?? 0);

        return [
            'analyses_total' => $total,
            'analyses_non_conformes' => $nonCompliant,
            'taux_conformite_pct' => $total > 0 ? (int) round(($total - $nonCompliant) / $total * 100) : null,
            'parametres_les_plus_depasses' => $topViolations,
        ];
    }

    /** Tendances par paramètre : moyenne récente vs ancienne (B2). */
    private function parameterTrends(int $infraId, Carbon $since): array
    {
        $half = $since->copy()->addDays((int) ($since->diffInDays(now()) / 2));

        $recent = DB::table('measurements')
            ->join('water_analyses', 'measurements.water_analysis_id', '=', 'water_analyses.id')
            ->where('water_analyses.infrastructure_id', $infraId)
            ->where('water_analyses.sampled_at', '>=', $half)
            ->select(['measurements.parameter', DB::raw('AVG(CAST(measurements.value AS REAL)) as avg_value')])
            ->groupBy('measurements.parameter')
            ->get()->keyBy('parameter');

        $older = DB::table('measurements')
            ->join('water_analyses', 'measurements.water_analysis_id', '=', 'water_analyses.id')
            ->where('water_analyses.infrastructure_id', $infraId)
            ->whereBetween('water_analyses.sampled_at', [$since, $half])
            ->select(['measurements.parameter', DB::raw('AVG(CAST(measurements.value AS REAL)) as avg_value')])
            ->groupBy('measurements.parameter')
            ->get()->keyBy('parameter');

        return collect($recent)->map(function ($r, string $param) use ($older) {
            $old = $older[$param] ?? null;
            $diff = $old ? round(((float) $r->avg_value - (float) $old->avg_value) / max(0.0001, abs((float) $old->avg_value)) * 100, 1) : null;

            return [
                'parametre' => WaterParameter::tryFrom($param)?->label() ?? $param,
                'valeur_recente' => round((float) $r->avg_value, 3),
                'tendance_pct' => $diff,
                'direction' => $diff === null ? 'stable' : ($diff > 5 ? 'hausse' : ($diff < -5 ? 'baisse' : 'stable')),
            ];
        })->values()->all();
    }

    // ---------------------------------------------------------------- Replis sans API

    private function degradationFallback(Infrastructure $infrastructure, int $nonCompliant, int $total, array $trends): string
    {
        if ($total === 0) {
            return "Aucune analyse récente sur {$infrastructure->type->label()} {$infrastructure->code}. Il est recommandé de programmer un prélèvement.";
        }

        $rate = (int) round($nonCompliant / $total * 100);
        $level = $rate >= 50 ? 'élevé' : ($rate >= 20 ? 'modéré' : 'faible');

        $worsening = collect($trends)
            ->filter(fn ($t) => $t['direction'] === 'hausse')
            ->pluck('parametre')
            ->implode(', ');

        $text = "Sur les 6 derniers mois, {$nonCompliant}/{$total} analyses non conformes ({$rate} %), risque {$level}.";
        if ($worsening) {
            $text .= " Tendance à la hausse sur : {$worsening}.";
        }
        if ($infrastructure->risk_score !== null && $infrastructure->risk_score >= 60) {
            $text .= " Score de risque élevé ({$infrastructure->risk_score}/100) : une dégradation dans les 3 prochains mois est probable si aucune action n'est engagée.";
        }

        return $text;
    }

    private function executiveSummaryFallback(array $stats, Carbon $from, Carbon $to): array
    {
        $period = $from->format('d/m/Y').' au '.$to->format('d/m/Y');
        $rate = $stats['taux_conformite_pct'] ?? null;
        $rateText = $rate !== null ? " Taux de conformité global : {$rate} %." : '';
        $total = $stats['analyses_total'];

        $summary = "Période du {$period} : {$total} analyse(s) enregistrée(s).{$rateText}";

        $highlights = [];
        if ($stats['analyses_non_conformes'] > 0) {
            $highlights[] = "{$stats['analyses_non_conformes']} analyse(s) non conforme(s) détectée(s).";
        }
        foreach ($stats['parametres_les_plus_depasses'] as $v) {
            $highlights[] = "{$v['parametre']} : {$v['depassements']} dépassement(s).";
        }
        if (empty($highlights)) {
            $highlights[] = 'Tous les paramètres analysés sont conformes sur la période.';
        }

        return ['summary' => $summary, 'highlights' => $highlights, 'source' => 'regles'];
    }

    /** @return array{days: int, reason: string, source: string} */
    private function frequencyFallback(Infrastructure $infrastructure, int $nonCompliant, int $openContaminations): array
    {
        $days = match (true) {
            $openContaminations > 0 || $nonCompliant >= 2 => 7,
            $nonCompliant === 1 || ($infrastructure->risk_score ?? 0) >= 60 => 14,
            $infrastructure->status->value === 'a_surveiller' => 21,
            $infrastructure->status->value === 'critique' => 7,
            default => 30,
        };

        $reason = match ($days) {
            7 => "Surveillance hebdomadaire recommandée : non-conformités récentes ou incidents de contamination ouverts.",
            14 => "Prélèvement bimensuel recommandé : anomalie récente ou score de risque élevé.",
            21 => "Prélèvement toutes les 3 semaines : ouvrage à surveiller sans incident récent.",
            default => "Fréquence mensuelle standard : situation nominale.",
        };

        return ['days' => $days, 'reason' => $reason, 'source' => 'regles'];
    }

    private function wqriFallback(Zone $zone, int $score, WqriLevel $level, array $factors): string
    {
        $main = collect($factors)
            ->sortByDesc(fn ($f) => $f['normalized'] * $f['weight'])
            ->take(2)
            ->pluck('label')
            ->implode(' et ');

        return "Indice WQRI de {$zone->name} : {$score}/100 (niveau {$level->label()}). "
            ."Principaux facteurs : {$main}. "
            .match ($level) {
                WqriLevel::Critique => 'Une action corrective immédiate est requise.',
                WqriLevel::Eleve => 'Une intervention est à planifier dans les prochaines semaines.',
                WqriLevel::Moyen => 'Un suivi renforcé des prélèvements est conseillé.',
                WqriLevel::Faible => 'Situation nominale : maintenir le plan de prélèvements.',
            };
    }

    // ---------------------------------------------------------------- Validation JSON

    /** @return array<string, mixed> */
    private static function textSchema(string $key): array
    {
        return ['type' => 'object', 'properties' => [$key => ['type' => 'string']], 'required' => [$key], 'additionalProperties' => false];
    }

    private static function validText(?array $data, string $key, int $min, int $max): ?string
    {
        $text = is_array($data) && is_string($data[$key] ?? null) ? trim(strip_tags($data[$key])) : null;

        return $text !== null && mb_strlen($text) >= $min && mb_strlen($text) <= $max ? $text : null;
    }
}
