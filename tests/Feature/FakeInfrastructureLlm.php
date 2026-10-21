<?php

namespace Tests\Feature;

use App\Services\Ai\LlmClient;

/** Faux client LLM des tests du module infrastructures : renvoie la réponse donnée et enregistre les prompts et schémas. */
class FakeInfrastructureLlm implements LlmClient
{
    /** @var list<string> */
    public array $prompts = [];

    /** @var list<array<string, mixed>> */
    public array $schemas = [];

    public function __construct(private ?array $json = null) {}

    public function generateJson(string $system, string $prompt, array $schema, int $maxTokens = 1024): ?array
    {
        $this->prompts[] = $prompt;
        $this->schemas[] = $schema;

        return $this->json;
    }

    public function answerWithTools(string $system, string $question, array $tools, callable $runTool, int $maxTurns = 4): ?string
    {
        return null;
    }
}
