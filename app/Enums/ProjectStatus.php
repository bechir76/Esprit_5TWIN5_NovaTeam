<?php

namespace App\Enums;

/**
 * Cycle de vie d'un projet (financement « tout ou rien ») :
 * draft → open → funded → in_progress → completed, ou open → failed (dons remboursés).
 * Les transitions autorisées sont définies par transitions() et vérifiées par le modèle Project.
 */
enum ProjectStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Funded = 'funded';
    case Failed = 'failed';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Open => 'En collecte',
            self::Funded => 'Financé',
            self::Failed => 'Échoué',
            self::InProgress => 'En travaux',
            self::Completed => 'Terminé',
        };
    }

    /** Variante de <x-status-badge>. */
    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'b-mute',
            self::Open => 'b-info',
            self::Funded, self::Completed => 'b-ok',
            self::Failed => 'b-crit',
            self::InProgress => 'b-warn',
        };
    }

    /**
     * Statuts suivants autorisés. « funded → open » : un financement supprimé ou remboursé
     * peut faire repasser le projet sous l'objectif (recalcul automatique).
     *
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Open],
            self::Open => [self::Funded, self::Failed, self::Draft],
            self::Funded => [self::InProgress, self::Open],
            self::InProgress => [self::Completed],
            self::Failed, self::Completed => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return $to === $this || in_array($to, $this->transitions(), true);
    }

    /** Projet ayant atteint son objectif (financé, en travaux ou terminé). */
    public function isFunded(): bool
    {
        return in_array($this, [self::Funded, self::InProgress, self::Completed], true);
    }

    /** @return list<self> */
    public static function fundedCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => $status->isFunded()));
    }

    /** @return array<string, string> valeur => libellé, pour <x-form.select> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status) => [$status->value => $status->label()])->all();
    }
}
