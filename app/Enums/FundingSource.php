<?php

namespace App\Enums;

/** Origine d'un financement. */
enum FundingSource: string
{
    case Municipalite = 'municipalite';
    case Association = 'association';
    case Entreprise = 'entreprise';
    case Citoyen = 'citoyen';

    public function label(): string
    {
        return match ($this) {
            self::Municipalite => 'Municipalité',
            self::Association => 'Association',
            self::Entreprise => 'Entreprise',
            self::Citoyen => 'Contribution citoyenne',
        };
    }

    /** Variante de <x-status-badge>. */
    public function badge(): string
    {
        return match ($this) {
            self::Municipalite => 'b-info',
            self::Association => 'b-ok',
            self::Entreprise => 'b-warn',
            self::Citoyen => 'b-mute',
        };
    }

    /** @return array<string, string> valeur => libellé, pour <x-form.select> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $source) => [$source->value => $source->label()])->all();
    }
}
