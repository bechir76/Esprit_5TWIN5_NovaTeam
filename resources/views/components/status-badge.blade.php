@props(['value', 'dot' => false, 'variant' => null])

@php
    // Couleur du badge selon la valeur affichée (statut, gravité ou état) : b-ok, b-warn, b-crit, b-info, b-mute
    $variants = [
        'b-ok' => ['Bon', 'Conforme', 'Résolu', 'Résolue', 'Faible', 'Financé', 'Actif', 'Active', 'Terminée', 'Terminé', 'Reçu'],
        'b-warn' => ['À surveiller', 'Signalé', 'Moyenne', 'Moyen', 'En travaux', 'En attente'],
        'b-crit' => ['Critique', 'Élevée', 'Élevé', 'Suspendu', 'Urgente', 'En retard'],
        'b-info' => ['En cours', 'Qualifié', 'En intervention', 'En collecte', 'Assignée', 'Planifiée'],
        'b-mute' => ['Brouillon', 'Clôturé', 'Annulée'],
    ];
    $class = $variant;
    if (! $class) {
        foreach ($variants as $candidate => $values) {
            if (in_array($value, $values, true)) {
                $class = $candidate;
                break;
            }
        }
    }
@endphp

<span {{ $attributes->class(['badge', $class ?? 'b-mute', 'dot' => $dot]) }}>{{ $value }}</span>
