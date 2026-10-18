<?php

/*
| Module Projets et financements : paramètres métier (dons, cofinancement, priorisation).
*/

return [

    // Dons citoyens en ligne (bornes : Funding::MIN_CONTRIBUTION / MAX_CONTRIBUTION, en DT).
    // Un don « pending » plus ancien n'est plus compté comme réservé (Checkout Session expirée).
    'donation' => [
        'pending_ttl_minutes' => 60,
    ],

    // Paiement Stripe : Stripe n'accepte pas le dinar tunisien en Checkout, les montants sont convertis en EUR
    // avec un taux fixe (1 DT = 0,29 EUR environ, à mettre à jour). PAYMENT_CURRENCY=tnd désactive la conversion.
    'payment' => [
        'currency' => env('PAYMENT_CURRENCY', 'eur'),
        'tnd_to_eur_rate' => (float) env('TND_TO_EUR_RATE', 0.29),
        'checkout_expires_minutes' => 30,
    ],

    // Cofinancement municipal 1 pour 1 des dons citoyens, plafonné par projet (DT)
    'matching' => [
        'enabled' => true,
        'donor_name' => 'Fonds municipal de cofinancement',
        'cap_per_project' => 5000,
    ],

    // Seuil de notification « projet à mi-parcours » et seuil « presque financé » (%)
    'milestone_percent' => 50,
    'almost_funded_percent' => 80,

    // Priorisation (score 0-100) : poids des facteurs, normalisés entre 0 et 1
    'scoring' => [
        'weights' => [
            'incidents' => 0.30,      // incidents récents sur l'ouvrage lié
            'severity' => 0.20,       // gravité moyenne de ces incidents
            'infrastructure' => 0.25, // état de l'ouvrage (bon, à surveiller, critique)
            'population' => 0.15,     // population desservie par la zone
            'cost' => 0.10,           // un projet moins coûteux est plus vite réalisable
        ],
        'incident_window_days' => 90,
        'incidents_ceiling' => 10,
        'population_ceiling' => 100000,
        'budget_ceiling' => 500000,
    ],

    // Prévision : rythme moyen des dons confirmés sur N jours
    'forecast_window_days' => 14,

];
