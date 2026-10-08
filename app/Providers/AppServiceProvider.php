<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserIsStaff;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
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
        //
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
    }
}
