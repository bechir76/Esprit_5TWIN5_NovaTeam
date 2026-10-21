<?php

namespace App\Enums;

/** État d'un ouvrage, mis à jour après chaque inspection. */
enum InfrastructureStatus: string
{
    case Bon = 'bon';
    case ASurveiller = 'a_surveiller';
    case Critique = 'critique';

    public function label(): string
    {
        return match ($this) {
            self::Bon => 'Bon',
            self::ASurveiller => 'À surveiller',
            self::Critique => 'Critique',
        };
    }

    /** Variante de <x-status-badge>. */
    public function badge(): string
    {
        return match ($this) {
            self::Bon => 'b-ok',
            self::ASurveiller => 'b-warn',
            self::Critique => 'b-crit',
        };
    }

    /** Couleur du repère sur les cartes (jetons CSS du template). */
    public function color(): string
    {
        return match ($this) {
            self::Bon => 'var(--teal)',
            self::ASurveiller => 'var(--warn)',
            self::Critique => 'var(--crit)',
        };
    }

    /** Couleur hexadécimale (repères Leaflet et graphiques Chart.js, qui ne lisent pas les variables CSS). */
    public function hex(): string
    {
        return match ($this) {
            self::Bon => '#2EC4B6',
            self::ASurveiller => '#F4A261',
            self::Critique => '#E63946',
        };
    }

    /** États comptés comme « ouvrages à risque ». */
    public function isAtRisk(): bool
    {
        return $this !== self::Bon;
    }

    /** @return array<string, string> valeur => libellé, pour <x-form.select> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status) => [$status->value => $status->label()])->all();
    }
}
