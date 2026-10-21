<?php

/*
| Module Réseau et infrastructures : plan d'inspections et score de risque prédictif (0-100).
| Le score est calculé par le code (App\Services\Infrastructures\RiskScorer) ; l'IA rédige seulement
| l'explication et les recommandations (App\Services\Ai\InfrastructureAiService).
*/

return [

    // Fréquence d'inspection en mois : la plus courte entre celle du type et celle de l'état s'applique
    'inspection_months' => [
        'by_type' => [
            'reservoir' => 12,
            'conduite' => 24,
            'pompage' => 6,
            'traitement' => 6,
            'forage' => 12,
        ],
        'by_status' => [
            'bon' => 24,
            'a_surveiller' => 6,
            'critique' => 3,
        ],
    ],

    'risk' => [
        // Poids des facteurs (chaque facteur est normalisé entre 0 et 1, le score est ramené sur 100)
        'weights' => [
            'age' => 0.20,           // âge de l'ouvrage
            'status' => 0.20,        // état (bon, à surveiller, critique)
            'material' => 0.10,      // matériau (fonte grise, acier… plus fragiles)
            'inspection' => 0.10,    // inspection en retard
            'incidents' => 0.20,     // incidents ouverts, critiques et sur 12 mois
            'clusters' => 0.10,      // groupes d'incidents liés (même panne probable)
            'water_quality' => 0.10, // analyses d'eau non conformes
        ],

        // Âge à partir duquel le facteur « âge » est au maximum (années)
        'age_ceiling' => 60,

        'status_points' => ['bon' => 0.0, 'a_surveiller' => 0.5, 'critique' => 1.0],

        // Fragilité par matériau (0 à 1) ; matériau inconnu : default
        'material_points' => [
            'Fonte grise' => 1.0,
            'Acier' => 0.7,
            'PVC' => 0.5,
            'Béton armé' => 0.4,
            'Fonte ductile' => 0.3,
            'PEHD' => 0.2,
        ],
        'material_default' => 0.5,

        // Retard d'inspection (jours) à partir duquel le facteur est au maximum
        'inspection_overdue_ceiling_days' => 180,

        // Incidents : fenêtre d'observation, points par incident et plafond
        'incident_window_months' => 12,
        'incident_points' => ['recent' => 1, 'open' => 2, 'critical' => 3],
        'incident_ceiling' => 12,

        // Groupes d'incidents liés (cluster_key distincts) à partir duquel le facteur est au maximum
        'cluster_ceiling' => 2,

        // Analyses d'eau : fenêtre d'observation (mois) ; facteur = part d'analyses non conformes
        'water_window_months' => 12,

        // Seuils des niveaux (score minimal)
        'levels' => ['critique' => 70, 'eleve' => 45, 'moyen' => 25],
    ],

    // Statistiques du réseau : durée du cache (secondes), oublié à chaque modification d'un ouvrage
    'stats_cache_seconds' => 300,

    // Import CSV : nombre maximal de lignes par fichier (fichier de 2 Mo maximum)
    'import_max_rows' => 1000,

    // Plan de maintenance d'une zone : nombre d'actions proposées
    'maintenance_plan_size' => 5,

    // Texte de l'IA (explication + 3 recommandations)
    'ai' => [
        'enabled' => (bool) env('AI_INFRASTRUCTURES_ENABLED', true),
        'max_tokens' => 1024,
    ],

];
