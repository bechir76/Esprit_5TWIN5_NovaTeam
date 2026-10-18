<?php

namespace App\Services\Ai;

/**
 * Client Google Gemini (AI_PROVIDER=gemini) : sorties JSON conformes au schéma (responseJsonSchema)
 * et appels d'outils (functionDeclarations). Même contrat que AnthropicLlmClient : null en cas d'échec.
 */
class GeminiLlmClient implements LlmClient
{
    public function __construct(private GeminiApi $api) {}

    public function generateJson(string $system, string $prompt, array $schema, int $maxTokens = 1024): ?array
    {
        return GeminiApi::json($this->api->generate([
            'systemInstruction' => GeminiApi::system($system),
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseJsonSchema' => $schema,
                // Marge pour le raisonnement interne, compté dans les jetons de sortie
                'maxOutputTokens' => $maxTokens + 2048,
            ],
        ]));
    }

    public function answerWithTools(string $system, string $question, array $tools, callable $runTool, int $maxTurns = 4): ?string
    {
        $declarations = array_map(fn (array $tool) => [
            'name' => $tool['name'],
            'description' => $tool['description'] ?? '',
            'parametersJsonSchema' => $tool['inputSchema'] ?? ['type' => 'object', 'properties' => new \stdClass],
        ], $tools);

        $contents = [['role' => 'user', 'parts' => [['text' => $question]]]];

        for ($turn = 0; $turn < $maxTurns; $turn++) {
            $content = $this->api->generate([
                'systemInstruction' => GeminiApi::system($system),
                'contents' => $contents,
                'tools' => [['functionDeclarations' => $declarations]],
                'generationConfig' => ['maxOutputTokens' => 4096],
            ]);

            if ($content === null) {
                return null;
            }

            $calls = array_values(array_filter($content['parts'] ?? [], fn ($part) => isset($part['functionCall'])));

            if ($calls === []) {
                return GeminiApi::text($content);
            }

            // Tour du modèle renvoyé tel quel (signatures de raisonnement comprises), puis résultats des outils
            $contents[] = ['role' => 'model', 'parts' => $content['parts']];
            $contents[] = ['role' => 'user', 'parts' => array_map(fn (array $part) => ['functionResponse' => array_filter([
                'id' => $part['functionCall']['id'] ?? null,
                'name' => $part['functionCall']['name'],
                'response' => ['result' => $runTool($part['functionCall']['name'], (array) ($part['functionCall']['args'] ?? []))],
            ], fn ($value) => $value !== null)], $calls)];
        }

        return null;
    }
}
