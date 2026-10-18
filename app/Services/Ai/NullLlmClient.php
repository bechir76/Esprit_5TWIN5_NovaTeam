<?php

namespace App\Services\Ai;

/** Aucune clé API : toutes les fonctions IA utilisent leur repli (règles ou texte gabarit). */
class NullLlmClient implements LlmClient
{
    public function generateJson(string $system, string $prompt, array $schema, int $maxTokens = 1024): ?array
    {
        return null;
    }

    public function answerWithTools(string $system, string $question, array $tools, callable $runTool, int $maxTurns = 4): ?string
    {
        return null;
    }
}
