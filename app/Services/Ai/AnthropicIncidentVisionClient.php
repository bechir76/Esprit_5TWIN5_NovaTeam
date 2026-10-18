<?php

namespace App\Services\Ai;

use Anthropic\Client;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Analyse de photo par Claude (SDK officiel anthropic-ai/sdk) : bloc image en base64 + consigne,
 * sortie JSON structurée. Mêmes réglages que AnthropicLlmClient (modèle, délai, repli serveur en cas de refus).
 */
class AnthropicIncidentVisionClient implements IncidentVisionClient
{
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    public function __construct(
        private string $apiKey,
        private string $model,
        private float $timeout,
    ) {}

    public function describeImage(string $system, string $prompt, string $base64, string $mediaType, array $schema): ?array
    {
        try {
            $message = (new Client(apiKey: $this->apiKey, requestOptions: ['timeout' => $this->timeout, 'maxRetries' => 1]))
                ->beta->messages->create(
                    maxTokens: 1024,
                    messages: [[
                        'role' => 'user',
                        'content' => [
                            ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $mediaType, 'data' => $base64]],
                            ['type' => 'text', 'text' => $prompt],
                        ],
                    ]],
                    model: $this->model,
                    system: $system,
                    outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
                    fallbacks: 'default',
                    betas: [self::FALLBACK_BETA],
                );
        } catch (Throwable $e) {
            Log::warning('Analyse de photo indisponible : '.$e->getMessage());

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
}
