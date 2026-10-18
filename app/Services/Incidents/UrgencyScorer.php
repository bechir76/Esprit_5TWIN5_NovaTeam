<?php

namespace App\Services\Incidents;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentType;
use App\Models\Incident;
use Illuminate\Support\Str;

/**
 * Score d'urgence de 1 à 10 calculé par le code (jamais par le modèle de langage) :
 * gravité, contamination (poids fort), délai dépassé, nombre de signalements, site sensible à proximité
 * (école, hôpital… d'après le nom ou la description de l'ouvrage, en lecture seule).
 */
class UrgencyScorer
{
    protected const SEVERITY_POINTS = ['faible' => 2, 'moyenne' => 4, 'elevee' => 6, 'critique' => 8];

    protected const SENSITIVE_SITES = ['école', 'ecole', 'lycée', 'collège', 'hôpital', 'hopital', 'clinique', 'dispensaire', 'crèche'];

    /** @return array{score: int, factors: list<string>} */
    public function score(Incident $incident): array
    {
        $factors = ["gravité {$incident->severity->label()}"];
        $score = self::SEVERITY_POINTS[$incident->severity->value];

        if ($incident->type === IncidentType::Contamination) {
            $score += 2;
            $factors[] = 'risque sanitaire (contamination)';
        }

        if ($incident->reports_count >= 4) {
            $score += 2;
            $factors[] = "{$incident->reports_count} signalements";
        } elseif ($incident->reports_count >= 2) {
            $score += 1;
            $factors[] = "{$incident->reports_count} signalements";
        }

        if ($incident->exists && $incident->isOverdue()) {
            $score += 1;
            $factors[] = 'délai cible dépassé';
        }

        $site = Str::lower(($incident->infrastructure?->name ?? '').' '.($incident->infrastructure?->description ?? ''));
        if (Str::contains($site, self::SENSITIVE_SITES)) {
            $score += 1;
            $factors[] = 'site sensible desservi (école, hôpital…)';
        }

        return ['score' => max(1, min(10, $score)), 'factors' => $factors];
    }

    /** Gravité la plus élevée en premier (tri des incidents d'un même score). */
    public static function severityRank(IncidentSeverity $severity): int
    {
        return self::SEVERITY_POINTS[$severity->value];
    }
}
