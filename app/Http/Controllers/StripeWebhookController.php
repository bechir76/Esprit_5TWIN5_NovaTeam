<?php

namespace App\Http\Controllers;

use App\Services\DonationService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

/**
 * POST /stripe/webhook (sans authentification, exclu du CSRF) : la signature Stripe est vérifiée,
 * sinon 400. Le traitement est idempotent (un événement rejoué ne crée aucun doublon).
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeService $stripe, DonationService $donations): Response
    {
        try {
            $event = $stripe->constructEvent($request->getContent(), $request->header('Stripe-Signature'));
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response('Signature invalide.', 400);
        }

        $donations->handleEvent($event);

        return response('OK', 200);
    }
}
