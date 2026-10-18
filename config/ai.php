<?php

/*
| Fonctionnalités IA du module projets (explication de priorité, description, prévision, assistant).
| Sans clé API, chaque fonction utilise un repli sans appel réseau (règles ou texte gabarit).
*/

return [

    'provider' => env('AI_PROVIDER', 'anthropic'),

    'api_key' => env('ANTHROPIC_API_KEY'),

    'model' => env('AI_MODEL', 'claude-opus-5-5'),

    // Délai maximal d'un appel (secondes) ; au-delà, le repli est utilisé
    'timeout' => (float) env('AI_TIMEOUT', 20),

    // Requêtes IA par minute et par utilisateur (routes et composants Livewire)
    'rate_limit' => (int) env('AI_RATE_LIMIT', 10),

    // Module incidents : classification, urgence, rapports, incidents liés, analyse de photo
    'incidents' => [
        'enabled' => (bool) env('AI_INCIDENTS_ENABLED', true),
        // Analyse de la photo jointe (le modèle Claude accepte les images)
        'vision' => (bool) env('AI_INCIDENTS_VISION', true),
        'max_tokens' => 1024,
    ],

];
