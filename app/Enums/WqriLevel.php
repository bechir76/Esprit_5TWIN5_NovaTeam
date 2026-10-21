<?php

namespace App\Enums;

/** Niveau de risque sanitaire calculé par le WQRI (A1). */
enum WqriLevel: string
{
    case Critique = 'critique';
    case Eleve = 'eleve';
    case Moyen = 'moyen';
    case Faible = 'faible';

    public function label(): string
    {
        return match ($this) {
            self::Critique => 'Critique',
            self::Eleve => 'Élevé',
            self::Moyen => 'Moyen',
            self::Faible => 'Faible',
        };
    }

    /** Couleur du feu tricolore public (A4). */
    public function trafficLight(): string
    {
        return match ($this) {
            self::Critique => 'red',
            self::Eleve => 'orange',
            self::Moyen, self::Faible => 'green',
        };
    }

    /** Classe CSS pour la vue publique. */
    public function cssClass(): string
    {
        return match ($this) {
            self::Critique => 'wqri-red',
            self::Eleve => 'wqri-orange',
            self::Moyen => 'wqri-yellow',
            self::Faible => 'wqri-green',
        };
    }

    /** Variante de badge admin. */
    public function badge(): string
    {
        return match ($this) {
            self::Critique => 'b-crit',
            self::Eleve => 'b-warn',
            self::Moyen => 'b-info',
            self::Faible => 'b-ok',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $l) => [$l->value => $l->label()])->all();
    }
}
