<?php

namespace App\Services\Ai;

/** Pas de clé API ou vision désactivée : la photo n'est pas analysée. */
class NullIncidentVisionClient implements IncidentVisionClient
{
    public function describeImage(string $system, string $prompt, string $base64, string $mediaType, array $schema): ?array
    {
        return null;
    }
}
