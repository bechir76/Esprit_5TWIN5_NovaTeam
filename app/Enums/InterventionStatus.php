<?php

namespace App\Enums;

/** Avancement d'une intervention terrain. */
enum InterventionStatus: string
{
    case Planifiee = 'planifiee';
    case EnCours = 'en_cours';
    case Terminee = 'terminee';
    case Annulee = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::Planifiee => 'Planifiée',
            self::EnCours => 'En cours',
            self::Terminee => 'Terminée',
            self::Annulee => 'Annulée',
        };
    }

    /** Variante de <x-status-badge>. */
    public function badge(): string
    {
        return match ($this) {
            self::Planifiee => 'b-info',
            self::EnCours => 'b-warn',
            self::Terminee => 'b-ok',
            self::Annulee => 'b-mute',
        };
    }

    /** Intervention prévue ou en cours : l'incident ne peut pas être clôturé. */
    public function isActive(): bool
    {
        return in_array($this, [self::Planifiee, self::EnCours], true);
    }

    /** @return array<string, string> valeur => libellé, pour <x-form.select> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status) => [$status->value => $status->label()])->all();
    }
}
