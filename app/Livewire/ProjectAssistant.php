<?php

namespace App\Livewire;

use App\Services\Ai\ProjectAiService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Assistant de questions sur les projets (page /projets) : le LLM n'appelle que des outils en lecture seule
 * (ProjectAssistantTools) ; sans clé API, réponses par règles. Questions hors module refusées poliment.
 * Limité à config('ai.rate_limit') questions par minute et par utilisateur (ou adresse IP).
 */
class ProjectAssistant extends Component
{
    public string $question = '';

    /** @var list<array{role: string, text: string}> */
    public array $messages = [];

    public function ask(ProjectAiService $ai): void
    {
        $this->validate(['question' => ['required', 'string', 'max:500']], [
            'question.required' => 'Posez votre question.',
            'question.max' => 'La question ne doit pas dépasser 500 caractères.',
        ]);

        $key = 'ai-assistant:'.(auth()->id() ?? request()->ip());

        if (RateLimiter::tooManyAttempts($key, config('ai.rate_limit'))) {
            $this->addError('question', 'Trop de questions : réessayez dans '.RateLimiter::availableIn($key).' secondes.');

            return;
        }

        RateLimiter::hit($key, 60);

        $this->messages[] = ['role' => 'me', 'text' => $this->question];
        $this->messages[] = ['role' => 'bot', 'text' => $ai->answer($this->question)];
        // Historique limité aux 10 derniers messages
        $this->messages = array_slice($this->messages, -10);
        $this->question = '';
    }

    public function render(): View
    {
        return view('livewire.project-assistant');
    }
}
