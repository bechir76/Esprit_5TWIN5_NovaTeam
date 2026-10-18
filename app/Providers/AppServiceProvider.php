<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserIsStaff;
use App\Models\User;
use App\Services\Ai\AnthropicIncidentVisionClient;
use App\Services\Ai\AnthropicLlmClient;
use App\Services\Ai\GeminiApi;
use App\Services\Ai\GeminiIncidentVisionClient;
use App\Services\Ai\GeminiLlmClient;
use App\Services\Ai\IncidentVisionClient;
use App\Services\Ai\NullIncidentVisionClient;
use App\Services\Ai\LlmClient;
use App\Services\Ai\NullLlmClient;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Client IA partagé (AI_PROVIDER) : Gemini ou Claude si la clé correspondante est configurée,
        // sinon repli sans appel réseau
        $gemini = fn () => new GeminiApi(config('ai.gemini.api_key'), config('ai.gemini.model'), config('ai.gemini.fallback_model'), config('ai.timeout'));
        $useGemini = fn () => config('ai.provider') === 'gemini' && filled(config('ai.gemini.api_key'));
        $useClaude = fn () => config('ai.provider') === 'anthropic' && filled(config('ai.api_key'));

        $this->app->bind(LlmClient::class, fn () => match (true) {
            $useGemini() => new GeminiLlmClient($gemini()),
            $useClaude() => new AnthropicLlmClient(config('ai.api_key'), config('ai.model'), config('ai.timeout')),
            default => new NullLlmClient,
        });

        // Module incidents : analyse de la photo des signalements (si la vision est activée)
        $this->app->bind(IncidentVisionClient::class, fn () => match (true) {
            ! config('ai.incidents.vision') => new NullIncidentVisionClient,
            $useGemini() => new GeminiIncidentVisionClient($gemini()),
            $useClaude() => new AnthropicIncidentVisionClient(config('ai.api_key'), config('ai.model'), config('ai.timeout')),
            default => new NullIncidentVisionClient,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // URIs françaises pour Route::resource (/utilisateurs/creer, /utilisateurs/{user}/modifier)
        Route::resourceVerbs(['create' => 'creer', 'edit' => 'modifier']);

        // Gestion des comptes et des rôles : administrateurs uniquement
        Gate::define('manage-users', fn (User $user) => $user->isAdmin());

        // Les actions des composants Livewire du back office (requêtes /livewire/update) repassent par le middleware « staff »
        Livewire::addPersistentMiddleware([EnsureUserIsStaff::class]);

        // Règle des mots de passe (inscription, réinitialisation, profil, CRUD utilisateurs)
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers());

        ResetPassword::toMailUsing(fn (User $user, string $token) => (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe AquaSecure')
            ->greeting('Bonjour '.$user->name.',')
            ->line('Vous recevez cet e-mail car une réinitialisation du mot de passe a été demandée pour votre compte.')
            ->action('Choisir un nouveau mot de passe', route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->line('Ce lien expire dans '.config('auth.passwords.users.expire').' minutes.')
            ->line('Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message.')
            ->salutation('L\'équipe AquaSecure'));

        // E-mail de vérification envoyé à l'inscription (User implémente MustVerifyEmail) ; lien signé valable 60 min
        VerifyEmail::toMailUsing(fn (User $user, string $url) => (new MailMessage)
            ->subject('Vérifiez votre adresse e-mail AquaSecure')
            ->greeting('Bonjour '.$user->name.',')
            ->line('Merci pour votre inscription. Confirmez votre adresse e-mail pour pouvoir signaler un incident et soutenir les projets.')
            ->action('Vérifier mon adresse e-mail', $url)
            ->line('Ce lien expire dans '.config('auth.verification.expire', 60).' minutes.')
            ->line('Si vous n\'avez pas créé de compte, ignorez ce message.')
            ->salutation('L\'équipe AquaSecure'));
    }
}
