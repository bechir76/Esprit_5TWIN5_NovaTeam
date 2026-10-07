<?php

namespace App\Enums;

/** Cycle de vie d'un projet : brouillon, collecte, financé, travaux, terminé. */
enum ProjectStatus: string
{
    case Brouillon = 'brouillon';
    case EnCollecte = 'en_collecte';
    case Finance = 'finance';
    case EnTravaux = 'en_travaux';
    case Termine = 'termine';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::EnCollecte => 'En collecte',
            self::Finance => 'Financé',
            self::EnTravaux => 'En travaux',
            self::Termine => 'Terminé',
        };
    }

    /** Variante de <x-status-badge>. */
    public function badge(): string
    {
        return match ($this) {
            self::Brouillon => 'b-mute',
            self::EnCollecte => 'b-info',
            self::Finance, self::Termine => 'b-ok',
            self::EnTravaux => 'b-warn',
        };
    }

    /** Projet ayant atteint son objectif de financement (financé, en travaux ou terminé). */
    public function isFunded(): bool
    {
        return in_array($this, [self::Finance, self::EnTravaux, self::Termine], true);
    }

    /** @return list<self> */
    public static function fundedCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => $status->isFunded()));
    }

    /** @return array<string, string> valeur => libellé, pour <x-form.select> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status) => [$status->value => $status->label()])->all();
    }
}
