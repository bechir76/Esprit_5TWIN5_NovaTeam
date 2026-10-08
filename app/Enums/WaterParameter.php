<?php

namespace App\Enums;

/**
 * Paramètres mesurés lors d'une analyse d'eau potable.
 * Chaque cas expose l'unité réglementaire et les seuils de conformité.
 */
enum WaterParameter: string
{
    case Chlore = 'chlore';
    case Ph = 'ph';
    case Turbidite = 'turbidite';
    case Nitrate = 'nitrate';
    case Conductivite = 'conductivite';
    case Ecoli = 'ecoli';

    public function label(): string
    {
        return match ($this) {
            self::Chlore => 'Chlore résiduel',
            self::Ph => 'pH',
            self::Turbidite => 'Turbidité',
            self::Nitrate => 'Nitrate',
            self::Conductivite => 'Conductivité',
            self::Ecoli => 'E. coli',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::Chlore => 'mg/L',
            self::Ph => '',
            self::Turbidite => 'NTU',
            self::Nitrate => 'mg/L',
            self::Conductivite => 'µS/cm',
            self::Ecoli => 'UFC/100 mL',
        };
    }

    /**
     * Valeur minimale réglementaire (null = pas de minimum).
     */
    public function min(): ?float
    {
        return match ($this) {
            self::Chlore => 0.1,
            self::Ph => 6.5,
            default => null,
        };
    }

    /**
     * Valeur maximale réglementaire (null = pas de maximum).
     */
    public function max(): ?float
    {
        return match ($this) {
            self::Chlore => 0.5,
            self::Ph => 8.5,
            self::Turbidite => 1.0,
            self::Nitrate => 50.0,
            self::Conductivite => 2500.0,
            self::Ecoli => 0.0,
        };
    }

    /**
     * Indique si une valeur mesurée respecte les seuils réglementaires.
     */
    public function isCompliant(float $value): bool
    {
        if ($this->min() !== null && $value < $this->min()) {
            return false;
        }

        if ($this->max() !== null && $value > $this->max()) {
            return false;
        }

        return true;
    }

    /** Description synthétique du seuil, affichée en aide dans le formulaire. */
    public function thresholdHint(): string
    {
        $unit = $this->unit();

        return match ($this) {
            self::Chlore => "Entre {$this->min()} et {$this->max()} {$unit}",
            self::Ph => "Entre {$this->min()} et {$this->max()}",
            self::Turbidite => "≤ {$this->max()} {$unit}",
            self::Nitrate => "≤ {$this->max()} {$unit}",
            self::Conductivite => "≤ {$this->max()} {$unit}",
            self::Ecoli => "= 0 {$unit}",
        };
    }

    /**
     * Paramètres principaux affichés sur la page publique /qualite-eau.
     *
     * @return list<self>
     */
    public static function publicMain(): array
    {
        return [self::Chlore, self::Ph, self::Turbidite, self::Nitrate];
    }

    /** @return array<string, string> valeur => libellé, pour <x-form.select> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $p) => [$p->value => $p->label()])
            ->all();
    }
}
