<?php

namespace App\Services\WaterQuality;

use App\Enums\WaterParameter;
use App\Exceptions\ImportRolledBack;
use App\Models\Infrastructure;
use App\Models\WaterAnalysis;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Import CSV multi-paramètres pour les laboratoires (A6).
 *
 * Format attendu (séparateur ; ou ,) :
 *   infrastructure_code ; sampled_at ; sampling_point ; laboratory ;
 *   chlore ; ph ; turbidite ; nitrate ; conductivite ; ecoli ; notes
 *
 * Les colonnes de paramètres sont détectées automatiquement par leur en-tête (WaterParameter::value).
 * Les lignes avec des anomalies (valeur négative impossible, paramètre inconnu) sont signalées mais
 * n'interrompent pas l'import sauf si toutes les lignes sont invalides.
 * Rollback complet en cas d'erreur critique (infrastructure inconnue, date invalide).
 */
class WaterAnalysisImporter
{
    /** En-têtes obligatoires minimum. */
    protected const REQUIRED_HEADERS = ['infrastructure_code', 'sampled_at', 'sampling_point'];

    /** Séparateurs acceptés. */
    protected const SEPARATORS = [';', ',', "\t"];

    /**
     * @param  resource  $handle  fichier CSV ouvert en lecture
     * @return array{imported: int, skipped: int, errors: list<string>}
     *
     * @throws ImportRolledBack
     */
    public function import($handle, ?string $defaultLaboratory = null): array
    {
        $rows = $this->readCsv($handle);

        if ($rows->isEmpty()) {
            throw new ImportRolledBack('Le fichier CSV est vide ou illisible.');
        }

        $headers = $rows->shift(); // première ligne = en-têtes normalisées
        $paramCols = $this->detectParameterColumns($headers);
        $errors = [];
        $imported = 0;
        $skipped = 0;

        // Validation avant insertion (fail-fast sur les erreurs critiques)
        $this->validateRequiredHeaders($headers);

        $rows->each(function (array $row, int $line) use ($headers, $paramCols, $defaultLaboratory, &$errors, &$imported, &$skipped): void {
            $lineNumber = $line + 2; // +1 pour l'en-tête, +1 pour base 1
            $data = array_combine($headers, array_pad($row, count($headers), ''));

            try {
                $result = $this->importRow($data, $paramCols, $defaultLaboratory, $lineNumber, $errors);
                $result ? $imported++ : $skipped++;
            } catch (\Throwable $e) {
                throw new ImportRolledBack("Ligne {$lineNumber} : ".$e->getMessage());
            }
        });

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Importe une ligne et retourne true si une analyse a été créée, false si skippée.
     *
     * @param  array<string, string>  $data
     * @param  array<string, string>  $paramCols  colonne => WaterParameter::value
     * @param  list<string>  $errors
     */
    private function importRow(array $data, array $paramCols, ?string $defaultLaboratory, int $line, array &$errors): bool
    {
        $code = trim($data['infrastructure_code'] ?? '');
        $sampledAt = trim($data['sampled_at'] ?? '');
        $samplingPoint = trim($data['sampling_point'] ?? '');

        // Ligne entièrement vide : silencieusement ignorée
        if ($code === '' && $sampledAt === '' && $samplingPoint === '') {
            return false;
        }

        if ($code === '') {
            $errors[] = "Ligne {$line} : code infrastructure manquant.";

            return false;
        }

        $infrastructure = Infrastructure::where('code', $code)->first();
        if (! $infrastructure) {
            throw new ImportRolledBack("Infrastructure «{$code}» introuvable (ligne {$line}).");
        }

        try {
            $date = Carbon::parse($sampledAt);
        } catch (\Throwable) {
            throw new ImportRolledBack("Date invalide «{$sampledAt}» (ligne {$line}).");
        }

        if ($samplingPoint === '') {
            $errors[] = "Ligne {$line} : point de prélèvement manquant — ligne ignorée.";

            return false;
        }

        // Collecte des mesures valides de la ligne
        $measurements = [];
        foreach ($paramCols as $col => $paramValue) {
            $raw = trim($data[$col] ?? '');
            if ($raw === '') {
                continue;
            }

            $numeric = str_replace(',', '.', $raw);
            if (! is_numeric($numeric)) {
                $errors[] = "Ligne {$line}, {$col} : «{$raw}» n'est pas une valeur numérique — ignoré.";
                continue;
            }

            $float = (float) $numeric;
            if ($float < 0) {
                $errors[] = "Ligne {$line}, {$col} : valeur négative impossible ({$float}) — ignorée.";
                continue;
            }

            $param = WaterParameter::tryFrom($paramValue);
            if (! $param) {
                $errors[] = "Ligne {$line} : paramètre «{$col}» inconnu — ignoré.";
                continue;
            }

            // Anomalie physique : valeur hors plage physique (ex: pH > 14)
            if ($this->isPhysicallyImpossible($param, $float)) {
                $errors[] = "Ligne {$line}, {$col} : valeur {$float} {$param->unit()} physiquement impossible — ignorée.";
                continue;
            }

            $measurements[$paramValue] = $float;
        }

        if (empty($measurements)) {
            $errors[] = "Ligne {$line} : aucune mesure valide — ligne ignorée.";

            return false;
        }

        // Insertion atomique analyse + mesures
        DB::transaction(function () use ($infrastructure, $date, $samplingPoint, $data, $defaultLaboratory, $measurements): void {
            $analysis = WaterAnalysis::create([
                'infrastructure_id' => $infrastructure->id,
                'sampled_at' => $date,
                'sampling_point' => $samplingPoint,
                'laboratory' => $data['laboratory'] ?? $defaultLaboratory,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($measurements as $paramValue => $value) {
                $analysis->measurements()->create([
                    'parameter' => $paramValue,
                    'value' => $value,
                ]);
            }

            $analysis->recalculateStatus();
        });

        return true;
    }

    /**
     * Lit le CSV et retourne toutes les lignes (première = en-têtes normalisées).
     *
     * @return Collection<int, array<int, string>>
     */
    private function readCsv($handle): Collection
    {
        $rows = collect();
        $separator = null;
        $lineCount = 0;
        $maxRows = (int) config('water_quality.import.max_rows', 500);

        while (($row = fgetcsv($handle, 0, $separator ?? ';')) !== false) {
            if ($lineCount === 0 && $separator === null) {
                // Auto-détection du séparateur sur la première ligne
                $separator = $this->detectSeparator(implode(';', $row));
                if ($separator !== ';') {
                    rewind($handle);
                    continue;
                }
            }

            $rows->push($row);
            $lineCount++;

            if ($lineCount > $maxRows + 1) {
                throw new ImportRolledBack("Le fichier dépasse la limite de {$maxRows} lignes.");
            }
        }

        // Normaliser les en-têtes : trim + lowercase + remplacer espaces par _
        if ($rows->isNotEmpty()) {
            $headers = array_map(
                fn (string $h) => strtolower(preg_replace('/[\s\-]+/', '_', trim($h)) ?? trim($h)),
                $rows->first()
            );
            $rows->forget(0);
            $rows->prepend($headers);
        }

        return $rows->values();
    }

    /**
     * Colonnes du CSV qui correspondent à un WaterParameter (en-tête = valeur de l'enum).
     *
     * @param  array<int, string>  $headers
     * @return array<string, string>  colonne => WaterParameter::value
     */
    private function detectParameterColumns(array $headers): array
    {
        $paramValues = array_column(WaterParameter::cases(), 'value');

        return collect($headers)
            ->filter(fn (string $h) => in_array($h, $paramValues, true))
            ->mapWithKeys(fn (string $h) => [$h => $h])
            ->all();
    }

    /** Valide que les en-têtes obligatoires sont présentes. */
    private function validateRequiredHeaders(array $headers): void
    {
        $missing = array_diff(self::REQUIRED_HEADERS, $headers);

        if (! empty($missing)) {
            throw new ImportRolledBack('Colonnes obligatoires manquantes : '.implode(', ', $missing).'.');
        }
    }

    /** Détecte le séparateur CSV par comptage. */
    private function detectSeparator(string $firstLine): string
    {
        $counts = array_map(fn (string $s) => substr_count($firstLine, $s), self::SEPARATORS);
        $max = max($counts);

        return $max > 0 ? self::SEPARATORS[array_search($max, $counts, true)] : ';';
    }

    /** Valeurs physiquement impossibles (indépendant de la conformité réglementaire). */
    private function isPhysicallyImpossible(WaterParameter $param, float $value): bool
    {
        return match ($param) {
            WaterParameter::Ph => $value > 14.0 || $value < 0,
            WaterParameter::Turbidite => $value > 1000.0,
            WaterParameter::Chlore => $value > 10.0,
            WaterParameter::Nitrate => $value > 500.0,
            WaterParameter::Conductivite => $value > 100000.0,
            WaterParameter::Ecoli => $value > 100000.0,
        };
    }
}
