<?php

namespace App\Enums;

/** Nature d'un incident signalé sur le réseau. */
enum IncidentType: string
{
    case Fuite = 'fuite';
    case Contamination = 'contamination';
    case Coupure = 'coupure';

    public function label(): string
    {
        return match ($this) {
            self::Fuite => 'Fuite',
            self::Contamination => 'Contamination',
            self::Coupure => 'Coupure',
        };
    }

    /** Libellé au pluriel (répartition du tableau de bord). */
    public function pluralLabel(): string
    {
        return match ($this) {
            self::Fuite => 'Fuites',
            self::Contamination => 'Contaminations',
            self::Coupure => 'Coupures',
        };
    }

    /** Pictogramme du template (💧 Fuite, ⚠️ Contamination, 🚱 Coupure). */
    public function icon(): string
    {
        return match ($this) {
            self::Fuite => '💧',
            self::Contamination => '⚠️',
            self::Coupure => '🚱',
        };
    }

    /** Variante de <x-status-badge>. */
    public function badge(): string
    {
        return match ($this) {
            self::Fuite => 'b-info',
            self::Contamination => 'b-crit',
            self::Coupure => 'b-warn',
        };
    }

    /** Couleur du type dans les graphiques (jetons CSS du template). */
    public function color(): string
    {
        return match ($this) {
            self::Fuite => 'var(--water)',
            self::Contamination => 'var(--crit)',
            self::Coupure => 'var(--warn)',
        };
    }

    /** Fond de la pastille des listes du site citoyen. */
    public function softColor(): string
    {
        return match ($this) {
            self::Fuite => 'var(--water-soft)',
            self::Contamination => 'var(--crit-soft)',
            self::Coupure => 'var(--warn-soft)',
        };
    }

    /** @return array<string, string> valeur => libellé, pour <x-form.select> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }
}
