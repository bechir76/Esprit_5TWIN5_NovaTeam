<?php

namespace App\Support;

/** Écriture de fichiers CSV destinés à Excel (export de la liste, fichier modèle d'import). */
class Csv
{
    /** Injection de formule : une cellule texte commençant par =, +, -, @, tabulation ou retour chariot est préfixée d'une apostrophe. */
    public static function cell(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /**
     * Une ligne CSV (séparateur ;) avec chaque cellule neutralisée.
     *
     * @param  resource  $handle
     * @param  list<mixed>  $cells
     */
    public static function row($handle, array $cells): void
    {
        fputcsv($handle, array_map(self::cell(...), $cells), ';');
    }
}
