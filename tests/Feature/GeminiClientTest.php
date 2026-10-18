<?php

namespace Tests\Feature;

use App\Services\Ai\GeminiApi;
use App\Services\Ai\GeminiIncidentVisionClient;
use App\Services\Ai\GeminiLlmClient;
use App\Services\Ai\IncidentVisionClient;
use App\Services\Ai\LlmClient;
use App\Services\Ai\NullLlmClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Client Google Gemini : requêtes HTTP simulées (Http::fake), aucun appel réseau. */
class GeminiClientTest extends TestCase
{
    private function api(): GeminiApi
    {
        return new GeminiApi('test-key', 'gemini-main', 'gemini-backup', 5);
    }

    /** Réponse generateContent avec les parties données. */
    private static function reply(array $parts, string $finish = 'STOP'): array
    {
        return ['candidates' => [['content' => ['role' => 'model', 'parts' => $parts], 'finishReason' => $finish]]];
    }

    public function test_provider_is_selected_from_configuration(): void
    {
        config(['ai.provider' => 'gemini', 'ai.gemini.api_key' => 'test-key']);
        $this->assertInstanceOf(GeminiLlmClient::class, app(LlmClient::class));
        $this->assertInstanceOf(GeminiIncidentVisionClient::class, app(IncidentVisionClient::class));

        // Fournisseur choisi sans clé : repli sans appel réseau
        config(['ai.gemini.api_key' => null]);
        $this->assertInstanceOf(NullLlmClient::class, app(LlmClient::class));
    }

    public function test_generate_json_sends_the_schema_and_key_in_a_header(): void
    {
        Http::fake(['*' => Http::response(self::reply([
            ['text' => 'raisonnement', 'thought' => true],
            ['text' => '{"type":"fuite","severity":"elevee"}'],
        ]))]);

        $schema = ['type' => 'object', 'properties' => ['type' => ['type' => 'string']]];
        $result = (new GeminiLlmClient($this->api()))->generateJson('Système', 'Question', $schema, 512);

        $this->assertSame(['type' => 'fuite', 'severity' => 'elevee'], $result);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/models/gemini-main:generateContent')
            && $request->header('x-goog-api-key') === ['test-key']
            && ! str_contains($request->url(), 'test-key')
            && $request['generationConfig']['responseJsonSchema'] === $schema
            && $request['systemInstruction']['parts'][0]['text'] === 'Système');
    }

    public function test_overloaded_model_falls_back_then_returns_null(): void
    {
        Http::fake([
            '*/gemini-main:*' => Http::response(['error' => ['message' => 'high demand']], 503),
            '*/gemini-backup:*' => Http::sequence()
                ->push(self::reply([['text' => '{"ok":true}']]))
                ->push(['error' => ['message' => 'quota']], 429),
        ]);
        $client = new GeminiLlmClient($this->api());

        $this->assertSame(['ok' => true], $client->generateJson('s', 'p', ['type' => 'object']));
        $this->assertNull($client->generateJson('s', 'p', ['type' => 'object']));
    }

    public function test_invalid_or_blocked_answers_return_null(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(self::reply([['text' => 'pas du JSON']]))
            ->push(self::reply([['text' => '{"a":1}']], 'SAFETY'))
            ->push(['error' => ['message' => 'API key not valid']], 400)]);
        $client = new GeminiLlmClient($this->api());

        $this->assertNull($client->generateJson('s', 'p', []));
        $this->assertNull($client->generateJson('s', 'p', []));
        $this->assertNull($client->generateJson('s', 'p', []));
    }

    public function test_tool_calls_are_run_and_answered(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(self::reply([['functionCall' => ['name' => 'listAlmostFunded', 'args' => ['min_percent' => 80]], 'thoughtSignature' => 'sig']]))
            ->push(self::reply([['text' => 'Deux projets sont presque financés.']]))]);

        $calls = [];
        $answer = (new GeminiLlmClient($this->api()))->answerWithTools('Système', 'Quels projets ?', [
            ['name' => 'listAlmostFunded', 'description' => 'Projets presque financés', 'inputSchema' => ['type' => 'object', 'properties' => ['min_percent' => ['type' => 'integer']]]],
        ], function (string $name, array $input) use (&$calls) {
            $calls[] = [$name, $input];

            return '[{"titre":"Réservoir"}]';
        });

        $this->assertSame('Deux projets sont presque financés.', $answer);
        $this->assertSame([['listAlmostFunded', ['min_percent' => 80]]], $calls);

        $second = Http::recorded()[1][0];
        $this->assertSame('listAlmostFunded', $second['tools'][0]['functionDeclarations'][0]['name']);
        // Tour du modèle renvoyé avec sa signature, puis le résultat de l'outil
        $this->assertSame('sig', $second['contents'][1]['parts'][0]['thoughtSignature']);
        $this->assertSame('[{"titre":"Réservoir"}]', $second['contents'][2]['parts'][0]['functionResponse']['response']['result']);
    }

    public function test_vision_sends_the_image_inline(): void
    {
        Http::fake(['*' => Http::response(self::reply([['text' => '{"description":"Conduite fissurée","consistent":true}']]))]);

        $result = (new GeminiIncidentVisionClient($this->api()))->describeImage('s', 'Décris', base64_encode('img'), 'image/jpeg', ['type' => 'object']);

        $this->assertSame(['description' => 'Conduite fissurée', 'consistent' => true], $result);
        Http::assertSent(fn (Request $request) => $request['contents'][0]['parts'][0]['inlineData'] === ['mimeType' => 'image/jpeg', 'data' => base64_encode('img')]);
    }
}
