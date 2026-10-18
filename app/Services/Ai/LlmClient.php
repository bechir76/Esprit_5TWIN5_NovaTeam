<?php

namespace App\Services\Ai;

/**
 * Accès au modèle de langage. Chaque méthode renvoie null en cas d'indisponibilité (pas de clé, délai,
 * refus, réponse invalide) : l'appelant utilise alors son repli. Mocké dans les tests (aucun appel réseau).
 */
interface LlmClient
{
    /**
     * Réponse JSON conforme au schéma (structured outputs), ou null.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>|null
     */
    public function generateJson(string $system, string $prompt, array $schema, int $maxTokens = 1024): ?array;

    /**
     * Réponse texte après appels d'outils d'une liste blanche ; $runTool(nom, entrée) exécute l'outil côté serveur.
     *
     * @param  list<array<string, mixed>>  $tools  définitions (name, description, inputSchema)
     * @param  callable(string, array<string, mixed>): string  $runTool
     */
    public function answerWithTools(string $system, string $question, array $tools, callable $runTool, int $maxTurns = 4): ?string;
}
