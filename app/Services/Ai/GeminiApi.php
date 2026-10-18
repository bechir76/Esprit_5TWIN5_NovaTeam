<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Appel REST generateContent de Google Gemini (clé dans l'en-tête x-goog-api-key, jamais dans l'URL).
 * Modèle de secours si le modèle principal est surchargé (429 / 503). Toute erreur renvoie null.
 */
class GeminiApi
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function __construct(
        private string $apiKey,
        private string $model,
        private ?string $fallbackModel,
        private float $timeout,
    ) {}

    /**
     * Contenu du premier candidat ({role, parts}), ou null (erreur, blocage, réponse vide).
     *
     * @param  array<string, mixed>  $payload  corps generateContent (contents, systemInstruction…)
     * @return array<string, mixed>|null
     */
    public function generate(array $payload): ?array
    {
        foreach (array_unique(array_filter([$this->model, $this->fallbackModel])) as $model) {
            try {
                $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                    ->timeout($this->timeout)
                    ->acceptJson()
                    ->post(sprintf(self::ENDPOINT, $model), $payload);
            } catch (Throwable $e) {
                Log::warning('IA Gemini indisponible : '.$e->getMessage());

                return null;
            }

            // Surcharge temporaire : on essaie le modèle de secours
            if (in_array($response->status(), [429, 500, 503], true)) {
                Log::info("IA Gemini : {$model} surchargé ({$response->status()}).");

                continue;
            }

            if ($response->failed()) {
                Log::warning("IA Gemini : erreur {$response->status()} ".$response->json('error.message'));

                return null;
            }

            $content = $response->json('candidates.0.content');
            $finish = $response->json('candidates.0.finishReason');

            return is_array($content) && in_array($finish, [null, 'STOP', 'MAX_TOKENS'], true) ? $content : null;
        }

        return null;
    }

    /** Texte des parties (hors raisonnement interne du modèle). */
    public static function text(?array $content): ?string
    {
        $text = collect($content['parts'] ?? [])
            ->filter(fn ($part) => isset($part['text']) && ! ($part['thought'] ?? false))
            ->pluck('text')
            ->implode('');

        return trim($text) !== '' ? trim($text) : null;
    }

    /** @return array<string, mixed>|null JSON décodé du texte de la réponse */
    public static function json(?array $content): ?array
    {
        $data = json_decode((string) self::text($content), true);

        return is_array($data) ? $data : null;
    }

    /** @return array<string, mixed> */
    public static function system(string $system): array
    {
        return ['parts' => [['text' => $system]]];
    }
}
