<?php

namespace App\Enums;

/** Unité de la capacité d'un ouvrage : volume (réservoir), débit (pompage, traitement, forage) ou longueur (conduite). */
enum CapacityUnit: string
{
    case CubicMeters = 'm3';
    case LitersPerSecond = 'l_s';
    case Kilometers = 'km';

    public function label(): string
    {
        return match ($this) {
            self::CubicMeters => 'm³',
            self::LitersPerSecond => 'L/s',
            self::Kilometers => 'km',
        };
    }

    /** @return array<string, string> valeur => libellé, pour <x-form.select> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $unit) => [$unit->value => $unit->label()])->all();
    }
}
