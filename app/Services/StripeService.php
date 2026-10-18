<?php

namespace App\Services;

use App\Models\Funding;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Seul point d'accès à l'API Stripe (Checkout, remboursements, signature des webhooks).
 * Les montants sont stockés en DT ; Stripe n'acceptant pas le dinar en Checkout, ils sont convertis
 * en EUR au taux fixe de config/projects.php (PAYMENT_CURRENCY=tnd pour facturer en millimes).
 * Aucune donnée de carte ne transite par l'application.
 */
class StripeService
{
    public function isConfigured(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    /**
     * Crée la Checkout Session d'un don « pending » (metadata funding_id).
     *
     * @return array{id: string, url: string}
     */
    public function createCheckoutSession(Funding $funding, string $successUrl, string $cancelUrl): array
    {
        [$currency, $unitAmount] = self::chargeAmount((float) $funding->amount);

        $session = $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $unitAmount,
                    'product_data' => ['name' => 'Don AquaSecure · '.$funding->project->title],
                ],
            ]],
            'client_reference_id' => (string) $funding->id,
            'metadata' => ['funding_id' => (string) $funding->id],
            'payment_intent_data' => ['metadata' => ['funding_id' => (string) $funding->id]],
            // {CHECKOUT_SESSION_ID} est remplacé par Stripe
            'success_url' => $successUrl.'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'expires_at' => now()->addMinutes(config('projects.payment.checkout_expires_minutes'))->timestamp,
        ], ['idempotency_key' => 'checkout-funding-'.$funding->id]);

        return ['id' => $session->id, 'url' => $session->url];
    }

    /** Rembourse intégralement le paiement d'un don ; renvoie l'identifiant du remboursement Stripe. */
    public function refund(Funding $funding): string
    {
        return $this->client()->refunds->create(
            ['payment_intent' => $funding->stripe_payment_intent_id],
            ['idempotency_key' => 'refund-funding-'.$funding->id],
        )->id;
    }

    /**
     * Événement webhook dont la signature (en-tête Stripe-Signature) est vérifiée.
     *
     * @throws SignatureVerificationException|\UnexpectedValueException
     */
    public function constructEvent(string $payload, ?string $signature): Event
    {
        return Webhook::constructEvent($payload, (string) $signature, (string) config('services.stripe.webhook_secret'));
    }

    /**
     * Devise et montant dans la plus petite unité Stripe : centimes d'euro ou millimes de dinar.
     *
     * @return array{0: string, 1: int}
     */
    public static function chargeAmount(float $amountDt): array
    {
        if (config('projects.payment.currency') === 'tnd') {
            return ['tnd', (int) round($amountDt * 1000)];
        }

        return ['eur', (int) round($amountDt * config('projects.payment.tnd_to_eur_rate') * 100)];
    }

    protected function client(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret'));
    }
}
