<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Connexion Breeze, utilisée par le front (/connexion) et le back office (/admin/login) :
 * identifiants, compte actif (is_active), rôle du personnel pour le back office et limitation des tentatives.
 */
class LoginRequest extends FormRequest
{
    /** Tentatives échouées autorisées par e-mail et IP avant blocage temporaire. */
    public const MAX_ATTEMPTS = 5;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * Les identifiants sont vérifiés avant la connexion : un compte suspendu, ou un citoyen sur le back office,
     * n'ouvre jamais de session.
     *
     * Avec la 2FA activée, la session n'est pas encore ouverte : l'utilisateur est mémorisé
     * (login.id) et doit saisir un code TOTP ou de récupération (TwoFactorChallengeController).
     *
     * @param  bool  $staffOnly  true pour le back office : le rôle citoyen est refusé
     * @return bool true si l'utilisateur est connecté, false si un code 2FA est attendu
     *
     * @throws ValidationException
     */
    public function authenticate(bool $staffOnly = false): bool
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::validate($this->only('email', 'password'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        /** @var User $user */
        $user = Auth::getLastAttempted();

        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => trans('auth.suspended')]);
        }

        if ($staffOnly && ! $user->isStaff()) {
            throw ValidationException::withMessages(['email' => trans('auth.staff_only')]);
        }

        if ($user->hasTwoFactorEnabled()) {
            $this->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $this->boolean('remember'),
                'login.staff' => $staffOnly,
            ]);

            return false;
        }

        Auth::login($user, $this->boolean('remember'));

        return true;
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
