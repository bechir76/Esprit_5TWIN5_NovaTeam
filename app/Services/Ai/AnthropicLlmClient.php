<?php

namespace App\Services\Ai;

use Anthropic\Client;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Client Claude (SDK officiel anthropic-ai/sdk) : sorties JSON structurées et appels d'outils.
 * Repli serveur activé (fallbacks « default ») en cas de refus du modèle principal.
 * Toute erreur (réseau, délai, refus) renvoie null pour laisser l'appelant utiliser son repli.
 */
class AnthropicLlmClient implements LlmClient
{
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    public function __construct(
        private string $apiKey,
        private string $model,
        private float $timeout,
    ) {}

    public function generateJson(string $system, string $prompt, array $schema, int $maxTokens = 1024): ?array
    {
        try {
            $message = $this->client()->beta->messages->create(
                maxTokens: $maxTokens,
                messages: [['role' => 'user', 'content' => $prompt]],
                model: $this->model,
                system: $system,
                outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
                fallbacks: 'default',
                betas: [self::FALLBACK_BETA],
            );
        } catch (Throwable $e) {
            Log::warning('IA indisponible : '.$e->getMessage());

            return null;
        }

        if ($message->stopReason === 'refusal') {
            return null;
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);

                return is_array($data) ? $data : null;
            }
        }

        return null;
    }

    public function answerWithTools(string $system, string $question, array $tools, callable $runTool, int $maxTurns = 4): ?string
    {
        $messages = [['role' => 'user', 'content' => $question]];

        try {
            for ($turn = 0; $turn < $maxTurns; $turn++) {
                $response = $this->client()->beta->messages->create(
                    maxTokens: 2048,
                    messages: $messages,
                    model: $this->model,
                    system: $system,
                    tools: $tools,
                    outputConfig: ['effort' => 'low'],
                    fallbacks: 'default',
                    betas: [self::FALLBACK_BETA],
                );

                if ($response->stopReason === 'refusal') {
                    return null;
                }

                if ($response->stopReason !== 'tool_use') {
                    $text = '';
                    foreach ($response->content as $block) {
                        if ($block->type === 'text') {
                            $text .= $block->text;
                        }
                    }

                    return trim($text) !== '' ? trim($text) : null;
                }

                // Exécution côté serveur des outils demandés (liste blanche en lecture seule)
                $results = [];
                foreach ($response->content as $block) {
                    if ($block->type === 'tool_use') {
                        $results[] = ['type' => 'tool_result', 'toolUseID' => $block->id, 'content' => $runTool($block->name, (array) $block->input)];
                    }
                }

                $messages[] = ['role' => 'assistant', 'content' => $response->content];
                $messages[] = ['role' => 'user', 'content' => $results];
            }
        } catch (Throwable $e) {
            Log::warning('IA indisponible : '.$e->getMessage());
        }

        return null;
    }

    private function client(): Client
    {
        return new Client(apiKey: $this->apiKey, requestOptions: ['timeout' => $this->timeout, 'maxRetries' => 1]);
    }
}
