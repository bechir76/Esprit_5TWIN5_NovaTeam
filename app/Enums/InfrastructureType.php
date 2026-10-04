<?php

namespace App\Enums;

/** Types d'ouvrages du réseau d'eau potable. */
enum InfrastructureType: string
{
    case Reservoir = 'reservoir';
    case Conduite = 'conduite';
    case Pompage = 'pompage';
    case Traitement = 'traitement';
    case Forage = 'forage';

    public function label(): string
    {
        return match ($this) {
            self::Reservoir => 'Réservoir',
            self::Conduite => 'Conduite',
            self::Pompage => 'Station de pompage',
            self::Traitement => 'Station de traitement',
            self::Forage => 'Forage',
        };
    }

    /** Libellé au pluriel (filtres de la carte publique). */
    public function pluralLabel(): string
    {
        return match ($this) {
            self::Reservoir => 'Réservoirs',
            self::Conduite => 'Conduites principales',
            self::Pompage => 'Stations de pompage',
            self::Traitement => 'Stations de traitement',
            self::Forage => 'Forages',
        };
    }

    /** Préfixe du code ouvrage (RES-ARI-004). */
    public function codePrefix(): string
    {
        return match ($this) {
            self::Reservoir => 'RES',
            self::Conduite => 'CND',
            self::Pompage => 'PMP',
            self::Traitement => 'TRT',
            self::Forage => 'FOR',
        };
    }

    /** Unité de capacité habituelle pour ce type. */
    public function defaultUnit(): CapacityUnit
    {
        return match ($this) {
            self::Reservoir => CapacityUnit::CubicMeters,
            self::Conduite => CapacityUnit::Kilometers,
            self::Pompage, self::Traitement, self::Forage => CapacityUnit::LitersPerSecond,
        };
    }

    /** @return array<string, string> valeur => libellé, pour <x-form.select> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }
}
