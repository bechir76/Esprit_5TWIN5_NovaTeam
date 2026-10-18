<?php

/*
| Module incidents et interventions : délais cibles (SLA), doublons, incidents liés et prévisions.
*/

return [

    // Délai cible de traitement par gravité, en heures (au-delà, l'incident ouvert est « En retard »)
    'sla_hours' => [
        'critique' => (int) env('INCIDENT_SLA_CRITICAL', 4),
        'elevee' => (int) env('INCIDENT_SLA_HIGH', 24),
        'moyenne' => (int) env('INCIDENT_SLA_MEDIUM', 72),
        'faible' => (int) env('INCIDENT_SLA_LOW', 168),
    ],

    // Doublons probables : même type, incident ouvert, à moins de 200 m, signalé depuis moins de 72 h
    'duplicates' => [
        'radius_meters' => 200,
        'window_hours' => 72,
    ],

    // Incidents liés (commande incidents:cluster) : à moins de 500 m et 6 h d'écart, au moins 2 incidents
    'clusters' => [
        'radius_meters' => 500,
        'window_hours' => 6,
        'min_size' => 2,
    ],

    // Prévision de durée : nombre minimal d'incidents résolus pour utiliser une médiane
    'estimate_min_samples' => 3,

    // Statistiques : durée du cache (secondes)
    'stats_cache_seconds' => 300,

];
