<?php

namespace App\Enums;

/** Nature d'un projet de rénovation. */
enum ProjectCategory: string
{
    case Decontamination = 'decontamination';
    case Renovation = 'renovation';
    case Surveillance = 'surveillance';
    case Secheresse = 'secheresse';

    public function label(): string
    {
        return match ($this) {
            self::Decontamination => 'Décontamination',
            self::Renovation => 'Rénovation',
            self::Surveillance => 'Surveillance',
            self::Secheresse => 'Sécheresse',
        };
    }

    /** Variante de <x-status-badge>. */
    public function badge(): string
    {
        return match ($this) {
            self::Decontamination => 'b-crit',
            self::Renovation => 'b-info',
            self::Surveillance => 'b-ok',
            self::Secheresse => 'b-warn',
        };
    }

    /** Dégradé de la vignette quand le projet n'a pas d'image (couleurs du template). */
    public function gradient(): string
    {
        return match ($this) {
            self::Decontamination => 'linear-gradient(135deg,#0E5A85,#2EC4B6)',
            self::Renovation => 'linear-gradient(135deg,#0B3C5D,#1CA7EC)',
            self::Surveillance => 'linear-gradient(135deg,#126D73,#7FD8C9)',
            self::Secheresse => 'linear-gradient(135deg,#3A5F7D,#F4A261)',
        };
    }

    /** @return array<string, string> valeur => libellé, pour <x-form.select> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $category) => [$category->value => $category->label()])->all();
    }
}
