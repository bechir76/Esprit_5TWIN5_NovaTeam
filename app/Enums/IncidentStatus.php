<?php

namespace App\Enums;

/** Cycle de vie d'un incident : signalé, qualifié par la régie, en intervention, puis résolu ou rejeté. */
enum IncidentStatus: string
{
    case Signale = 'signale';
    case Qualifie = 'qualifie';
    case EnIntervention = 'en_intervention';
    case Resolu = 'resolu';
    case Rejete = 'rejete';

    public function label(): string
    {
        return match ($this) {
            self::Signale => 'Signalé',
            self::Qualifie => 'Qualifié',
            self::EnIntervention => 'En intervention',
            self::Resolu => 'Résolu',
            self::Rejete => 'Rejeté',
        };
    }

    /** Variante de <x-status-badge>. */
    public function badge(): string
    {
        return match ($this) {
            self::Signale => 'b-warn',
            self::Qualifie, self::EnIntervention => 'b-info',
            self::Resolu => 'b-ok',
            self::Rejete => 'b-mute',
        };
    }

    /** Incident encore à traiter (ni résolu, ni rejeté). */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Resolu, self::Rejete], true);
    }

    /** @return list<self> */
    public static function openCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => $status->isOpen()));
    }

    /** @return array<string, string> valeur => libellé, pour <x-form.select> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status) => [$status->value => $status->label()])->all();
    }
}
