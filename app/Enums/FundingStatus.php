<?php

namespace App\Enums;

/** État d'un financement : en attente du paiement Stripe, encaissé, remboursé ou échoué. */
enum FundingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Refunded = 'refunded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Confirmed => 'Confirmé',
            self::Refunded => 'Remboursé',
            self::Failed => 'Échoué',
        };
    }

    /** Variante de <x-status-badge>. */
    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'b-warn',
            self::Confirmed => 'b-ok',
            self::Refunded => 'b-mute',
            self::Failed => 'b-crit',
        };
    }

    /** @return array<string, string> valeur => libellé, pour <x-form.select> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status) => [$status->value => $status->label()])->all();
    }
}
