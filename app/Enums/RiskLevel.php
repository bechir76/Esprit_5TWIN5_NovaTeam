<?php

namespace App\Enums;

/** Niveau du score de risque prédictif d'un ouvrage (seuils : config/infrastructures.php). */
enum RiskLevel: string
{
    case Faible = 'faible';
    case Moyen = 'moyen';
    case Eleve = 'eleve';
    case Critique = 'critique';

    public static function fromScore(int $score): self
    {
        $levels = config('infrastructures.risk.levels');

        return match (true) {
            $score >= $levels['critique'] => self::Critique,
            $score >= $levels['eleve'] => self::Eleve,
            $score >= $levels['moyen'] => self::Moyen,
            default => self::Faible,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Faible => 'Faible',
            self::Moyen => 'Moyen',
            self::Eleve => 'Élevé',
            self::Critique => 'Critique',
        };
    }

    /** Variante de <x-status-badge>. */
    public function badge(): string
    {
        return match ($this) {
            self::Faible => 'b-ok',
            self::Moyen => 'b-info',
            self::Eleve => 'b-warn',
            self::Critique => 'b-crit',
        };
    }

    /** Couleur de la jauge (jetons CSS du thème). */
    public function color(): string
    {
        return match ($this) {
            self::Faible => 'var(--color-teal)',
            self::Moyen => 'var(--color-water)',
            self::Eleve => 'var(--color-warn)',
            self::Critique => 'var(--color-crit)',
        };
    }
}
