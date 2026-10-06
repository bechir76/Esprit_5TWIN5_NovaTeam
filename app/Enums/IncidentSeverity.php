<?php

namespace App\Enums;

/** Gravité d'un incident, de la gêne mineure au risque sanitaire. */
enum IncidentSeverity: string
{
    case Faible = 'faible';
    case Moyenne = 'moyenne';
    case Elevee = 'elevee';
    case Critique = 'critique';

    public function label(): string
    {
        return match ($this) {
            self::Faible => 'Faible',
            self::Moyenne => 'Moyenne',
            self::Elevee => 'Élevée',
            self::Critique => 'Critique',
        };
    }

    /** Description affichée dans le formulaire de signalement. */
    public function description(): string
    {
        return match ($this) {
            self::Faible => 'Faible : suintement, gêne mineure',
            self::Moyenne => 'Moyenne : écoulement visible, pression réduite',
            self::Elevee => 'Élevée : rue inondée, eau colorée ou odorante',
            self::Critique => 'Critique : risque sanitaire ou quartier entier privé d\'eau',
        };
    }

    /** Variante de <x-status-badge>. */
    public function badge(): string
    {
        return match ($this) {
            self::Faible => 'b-ok',
            self::Moyenne => 'b-warn',
            self::Elevee, self::Critique => 'b-crit',
        };
    }

    /** Couleur de la barre de gravité des tableaux (jetons CSS du template). */
    public function color(): string
    {
        return match ($this) {
            self::Faible => 'var(--teal)',
            self::Moyenne => 'var(--warn)',
            self::Elevee, self::Critique => 'var(--crit)',
        };
    }

    /** @return array<string, string> valeur => libellé, pour <x-form.select> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $severity) => [$severity->value => $severity->label()])->all();
    }
}
