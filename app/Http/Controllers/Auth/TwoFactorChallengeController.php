<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

/**
 * Deuxième étape de la connexion (front et back office) quand la 2FA est activée :
 * mot de passe déjà vérifié par LoginRequest (session login.id), code TOTP ou code de récupération attendu.
 */
class TwoFactorChallengeController extends Controller
{
    /** Tentatives autorisées avant blocage temporaire. */
    public const MAX_ATTEMPTS = 5;

    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return to_route('login');
        }

        return view('pages.auth.two-factor-challenge');
    }

    public function store(Request $request, Google2FA $google2fa): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return to_route('login')->withErrors(['email' => 'Session expirée : reconnectez-vous.']);
        }

        $request->validate([
            'code' => ['nullable', 'required_without:recovery_code', 'digits:6'],
            'recovery_code' => ['nullable', 'string', 'max:20'],
        ], [
            'code.required_without' => 'Saisissez le code de votre application ou un code de récupération.',
            'code.digits' => 'Le code doit contenir 6 chiffres.',
        ]);

        $key = 'two-factor:'.$user->getKey().'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages(['code' => trans('auth.throttle', ['seconds' => RateLimiter::availableIn($key)])]);
        }

        $valid = $request->filled('code')
            ? $google2fa->verifyKey($user->two_factor_secret, $request->input('code'))
            : $user->useRecoveryCode($request->input('recovery_code'));

        if (! $valid) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages([
                $request->filled('code') ? 'code' : 'recovery_code' => 'Ce code est invalide.',
            ]);
        }

        RateLimiter::clear($key);
        $staff = $request->session()->pull('login.staff', false);
        $remember = $request->session()->pull('login.remember', false);
        $request->session()->forget('login.id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return $staff
            ? redirect()->intended(route('admin.dashboard'))
            : redirect()->intended(route('home', absolute: false))->with('status', 'Bienvenue, '.$user->name.'.');
    }

    /** Utilisateur dont le mot de passe a été vérifié et qui attend la saisie du code. */
    private function pendingUser(Request $request): ?User
    {
        $user = User::find($request->session()->get('login.id'));

        return $user?->hasTwoFactorEnabled() && $user->is_active ? $user : null;
    }
}
