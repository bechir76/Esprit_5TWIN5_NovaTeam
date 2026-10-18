<?php

namespace App\Services\Ai;

/** Analyse de la photo d'un incident par Gemini (image en ligne + réponse JSON conforme au schéma). */
class GeminiIncidentVisionClient implements IncidentVisionClient
{
    public function __construct(private GeminiApi $api) {}

    public function describeImage(string $system, string $prompt, string $base64, string $mediaType, array $schema): ?array
    {
        return GeminiApi::json($this->api->generate([
            'systemInstruction' => GeminiApi::system($system),
            'contents' => [['role' => 'user', 'parts' => [
                ['inlineData' => ['mimeType' => $mediaType, 'data' => $base64]],
                ['text' => $prompt],
            ]]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseJsonSchema' => $schema,
                'maxOutputTokens' => (int) config('ai.incidents.max_tokens', 1024) + 2048,
            ],
        ]));
    }
}
