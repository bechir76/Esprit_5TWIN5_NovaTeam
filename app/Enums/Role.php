<?php

namespace App\Enums;

/**
 * Rôles des utilisateurs. Le personnel (admin, gestionnaire, technicien)
 * accède au back office ; le citoyen n'a accès qu'à l'espace public.
 */
enum Role: string
{
    case Admin = 'admin';
    case Gestionnaire = 'gestionnaire';
    case Technicien = 'technicien';
    case Citoyen = 'citoyen';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Gestionnaire => 'Gestionnaire réseau',
            self::Technicien => 'Technicien terrain',
            self::Citoyen => 'Citoyen',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Accès complet',
            self::Gestionnaire => 'Municipalités, régies',
            self::Technicien => 'Interventions',
            self::Citoyen => 'Espace public',
        };
    }

    /** Variante de <x-status-badge> (couleurs du template). */
    public function badge(): string
    {
        return match ($this) {
            self::Admin => 'b-crit',
            self::Gestionnaire => 'b-info',
            self::Technicien => 'b-warn',
            self::Citoyen => 'b-mute',
        };
    }

    /** Couleur de l'avatar. */
    public function color(): string
    {
        return match ($this) {
            self::Admin => '#0E4A72',
            self::Gestionnaire => '#1CA7EC',
            self::Technicien => '#F4A261',
            self::Citoyen => '#2EC4B6',
        };
    }

    public function isStaff(): bool
    {
        return $this !== self::Citoyen;
    }

    /** @return array<string, string> valeur => libellé, pour <x-form.select> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $role) => [$role->value => $role->label()])->all();
    }
}
