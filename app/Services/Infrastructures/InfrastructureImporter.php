<?php

namespace App\Services\Infrastructures;

use App\Enums\CapacityUnit;
use App\Enums\InfrastructureStatus;
use App\Enums\InfrastructureType;
use App\Exceptions\ImportRolledBack;
use App\Http\Requests\Admin\StoreInfrastructureRequest;
use App\Models\Infrastructure;
use App\Models\Zone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Import d'infrastructures depuis un fichier CSV (séparateur ; ou ,, UTF-8 avec ou sans BOM, ou Windows-1252).
 * Chaque ligne est validée avec les règles, messages et attributs de StoreInfrastructureRequest ; les lignes valides
 * sont créées dans une seule transaction. Mode « tout ou rien » : la moindre erreur annule tout l'import.
 * Le type, la zone, l'unité et l'état acceptent la valeur technique ou le libellé (« reservoir » ou « Réservoir »).
 */
class InfrastructureImporter
{
    /** En-tête du fichier (libellés acceptés sans accents ni majuscules) => champ du formulaire. */
    public const COLUMNS = [
        'code' => 'code',
        'nom' => 'name',
        'type' => 'type',
        'zone' => 'zone_id',
        'materiau' => 'material',
        'capacite' => 'capacity',
        'unite' => 'capacity_unit',
        'annee_mise_en_service' => 'commissioned_year',
        'pression_nominale' => 'nominal_pressure',
        'etat' => 'status',
        'derniere_inspection' => 'last_inspection_at',
        'latitude' => 'latitude',
        'longitude' => 'longitude',
        'public' => 'is_public',
        'description' => 'description',
    ];

    public const REQUIRED_COLUMNS = ['code', 'nom', 'type', 'zone', 'capacite', 'annee_mise_en_service', 'etat'];

    public function __construct(private InfrastructureAlerts $alerts) {}

    /**
     * @return array{imported: list<array{line: int, code: string, name: string}>, errors: list<array{line: int, messages: list<string>}>, rolled_back: bool}
     */
    public function import(string $path, bool $strict = false): array
    {
        $report = ['imported' => [], 'errors' => [], 'rolled_back' => false];

        try {
            $rows = $this->read($path);
        } catch (RuntimeException $e) {
            $report['errors'][] = ['line' => 1, 'messages' => [$e->getMessage()]];

            return $report;
        }

        $request = new StoreInfrastructureRequest;
        $zones = Zone::query()->get(['id', 'name']);
        $created = [];

        try {
            DB::transaction(function () use ($rows, $request, $zones, $strict, &$report, &$created): void {
                foreach ($rows as $line => $row) {
                    $data = $this->toFormData($row, $zones);
                    $validator = Validator::make($data, $request->rules(), $request->messages(), $request->attributes());

                    if ($validator->fails()) {
                        $report['errors'][] = ['line' => $line, 'messages' => $validator->errors()->all()];

                        continue;
                    }

                    $infrastructure = Infrastructure::create($validator->validated());
                    $created[] = $infrastructure;
                    $report['imported'][] = ['line' => $line, 'code' => $infrastructure->code, 'name' => $infrastructure->name];
                }

                if ($strict && $report['errors'] !== []) {
                    throw new ImportRolledBack;
                }
            });
        } catch (ImportRolledBack) {
            $report['imported'] = [];
            $report['rolled_back'] = true;

            return $report;
        }

        // Après validation de la transaction : alerte pour les ouvrages importés à l'état critique
        foreach ($created as $infrastructure) {
            $this->alerts->statusChanged($infrastructure, null);
        }

        return $report;
    }

    /**
     * Lignes non vides indexées par leur numéro de ligne dans le fichier (l'en-tête est la ligne 1).
     *
     * @return array<int, array<string, string>>
     */
    public function read(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
        // Fichier enregistré par Excel en Windows-1252 : converti en UTF-8
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

        $header = fgetcsv($handle, 0, $delimiter, '"', '');
        if (! $header || $header === [null]) {
            throw new RuntimeException('Le fichier est vide.');
        }

        $columns = array_map(fn ($h) => Str::slug(trim((string) $h), '_'), $header);
        $unknown = array_diff(array_filter($columns), array_keys(self::COLUMNS));
        $missing = array_diff(self::REQUIRED_COLUMNS, $columns);

        if ($missing) {
            throw new RuntimeException('Colonnes obligatoires manquantes : '.implode(', ', $missing).'. Utilisez le fichier modèle.');
        }
        if ($unknown) {
            throw new RuntimeException('Colonnes inconnues : '.implode(', ', $unknown).'. Utilisez le fichier modèle.');
        }

        $rows = [];
        $line = 1;
        while (($values = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $line++;
            if ($values === [null] || trim(implode('', $values)) === '') {
                continue;
            }

            $rows[$line] = array_combine($columns, array_pad(array_slice($values, 0, count($columns)), count($columns), ''));

            if (count($rows) > (int) config('infrastructures.import_max_rows', 1000)) {
                throw new RuntimeException('Le fichier dépasse '.config('infrastructures.import_max_rows', 1000).' lignes : découpez-le en plusieurs fichiers.');
            }
        }
        fclose($handle);

        if ($rows === []) {
            throw new RuntimeException('Le fichier ne contient aucune ligne d\'infrastructure.');
        }

        return $rows;
    }

    /**
     * Ligne CSV => données du formulaire, préparées comme StoreInfrastructureRequest::prepareForValidation().
     *
     * @param  array<string, string>  $row
     * @param  Collection<int, Zone>  $zones
     * @return array<string, mixed>
     */
    public function toFormData(array $row, $zones): array
    {
        $data = [];
        foreach (self::COLUMNS as $column => $field) {
            $value = trim((string) ($row[$column] ?? ''));
            $data[$field] = $value === '' ? null : $value;
        }

        $type = self::enumFrom(InfrastructureType::cases(), $data['type']);
        $data['type'] = $type?->value ?? $data['type'];
        $data['status'] = self::enumFrom(InfrastructureStatus::cases(), $data['status'])?->value ?? $data['status'];
        $data['capacity_unit'] = $data['capacity_unit'] === null
            ? $type?->defaultUnit()->value
            : (self::enumFrom(CapacityUnit::cases(), $data['capacity_unit'])?->value ?? $data['capacity_unit']);

        // Zone : id ou nom (sans tenir compte des accents ni des majuscules) ; nom inconnu => 0, refusé par la règle exists
        if ($data['zone_id'] !== null && ! ctype_digit($data['zone_id'])) {
            $data['zone_id'] = $zones->first(fn (Zone $z) => Str::slug($z->name) === Str::slug($data['zone_id']))?->id ?? 0;
        }

        // Date au format français (15/03/2026) ou ISO
        if ($data['last_inspection_at'] !== null && preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $data['last_inspection_at'])) {
            $date = Carbon::createFromFormat('!d/m/Y', $data['last_inspection_at']);
            $data['last_inspection_at'] = $date ? $date->format('Y-m-d') : $data['last_inspection_at'];
        }

        // Visible sur la carte publique par défaut ; « non », « 0 », « false » la masquent
        $data['is_public'] = $data['is_public'] === null || ! in_array(Str::lower($data['is_public']), ['non', 'no', '0', 'false', 'faux'], true);

        return array_merge($data, StoreInfrastructureRequest::normalizeInput($data));
    }

    /**
     * Cas d'enum dont la valeur ou le libellé correspond (sans accents ni majuscules).
     *
     * @param  list<\BackedEnum>  $cases
     */
    private static function enumFrom(array $cases, ?string $value): ?\BackedEnum
    {
        if ($value === null) {
            return null;
        }

        $slug = Str::slug($value);

        foreach ($cases as $case) {
            if ($slug === Str::slug($case->value) || $slug === Str::slug($case->label())) {
                return $case;
            }
        }

        return null;
    }
}
