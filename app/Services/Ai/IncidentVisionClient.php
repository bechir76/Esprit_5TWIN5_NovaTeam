<?php

namespace App\Services\Ai;

/**
 * Module incidents : analyse d'image par le modèle (photo jointe à un signalement).
 * Séparé de LlmClient (partagé avec le module projets) pour ne pas modifier son interface.
 * Renvoie null en cas d'indisponibilité ; mocké dans les tests.
 */
interface IncidentVisionClient
{
    /**
     * Réponse JSON conforme au schéma à partir d'une image encodée en base64, ou null.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>|null
     */
    public function describeImage(string $system, string $prompt, string $base64, string $mediaType, array $schema): ?array;
}
