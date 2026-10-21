<?php

/*
| Module Qualité de l'eau : WQRI, anomalies, alertes, bulletins PDF et import CSV.
*/

return [

    // A1 : Indice de Risque Sanitaire (WQRI 0-100) par zone
    'wqri' => [
        // Fenêtre d'observation pour les mesures et incidents (jours)
        'window_days' => 90,

        // Population à partir de laquelle le facteur population est au maximum
        'population_ceiling' => 150000,

        // Incidents de contamination à partir desquels le facteur incidents est au maximum
        'incident_ceiling' => 5,

        // Poids des facteurs (total doit être ≤ 1, normalisé automatiquement)
        'weights' => [
            'water_quality' => 0.40,
            'parameter_severity' => 0.25,
            'infrastructure_state' => 0.20,
            'incidents' => 0.10,
            'population' => 0.05,
        ],

        // Seuils des niveaux WQRI (score minimal pour atteindre le niveau)
        'levels' => [
            'critique' => 70,
            'eleve' => 45,
            'moyen' => 20,
        ],
    ],

    // A2 : Alertes de dépassement de seuil dynamiques
    'alerts' => [
        // Délai minimal entre deux alertes sur la même analyse (minutes)
        'cooldown_minutes' => 5,
    ],

    // B1 : Détection d'anomalies statistiques (Z-score)
    'anomaly' => [
        // Z-score absolu au-delà duquel une mesure est considérée anormale
        'z_score_threshold' => 2.5,

        // Nombre minimal d'échantillons historiques pour calculer le Z-score
        'min_samples' => 5,

        // Fenêtre historique pour le calcul de la moyenne et de l'écart-type (jours)
        'window_days' => 90,
    ],

    // B2 : Prédiction de dégradation
    'prediction' => [
        // Fenêtre d'observation pour les analyses récentes (mois)
        'window_months' => 6,
    ],

    // A5 : Bulletin PDF
    'bulletin' => [
        // Fenêtre pour les courbes d'évolution dans le PDF (jours)
        'history_days' => 30,
    ],

    // A6 : Import CSV multi-paramètres
    'import' => [
        // Nombre maximal de lignes par fichier (hors en-tête)
        'max_rows' => 500,
    ],

    // IA (B1–B5 + A1) : partagé avec config/ai.php mais peut être désactivé indépendamment
    'ai' => [
        'enabled' => (bool) env('AI_WATER_ENABLED', true),
    ],

];
