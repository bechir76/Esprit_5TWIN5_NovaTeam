<?php

namespace App\Enums;

/** Statut d'une analyse d'eau : calculé automatiquement à partir des mesures. */
enum AnalysisStatus: string
{
    case EnAttente    = 'en_attente';
    case Validee      = 'validee';
    case NonConforme  = 'non_conforme';

    public function label(): string
    {
        return match ($this) {
            self::EnAttente   => 'En attente',
            self::Validee     => 'Validée',
            self::NonConforme => 'Non conforme',
        };
    }

    /** Variante de <x-status-badge>. */
    public function badge(): string
    {
        return match ($this) {
            self::EnAttente   => 'b-warn',
            self::Validee     => 'b-ok',
            self::NonConforme => 'b-crit',
        };
    }

    /** @return array<string, string> valeur => libellé, pour <x-form.select> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->all();
    }
}
