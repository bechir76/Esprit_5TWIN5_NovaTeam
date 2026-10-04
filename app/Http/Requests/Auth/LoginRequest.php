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
 * Connexion front et back office : identifiants, compte actif, rôle (back) et limitation des tentatives.
 */
class LoginRequest extends FormRequest
{
    /** Tentatives échouées autorisées par e-mail et IP avant blocage temporaire. */
    public const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    /**
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
     * Vérifie les identifiants puis connecte l'utilisateur.
     *
     * @param  bool  $staffOnly  true pour le back office : le rôle citoyen est refusé
     *
     * @throws ValidationException
     */
    public function authenticate(bool $staffOnly = false): void
    {
        $this->ensureIsNotRateLimited();

        $provider = Auth::guard('web')->getProvider();
        /** @var User|null $user */
        $user = $provider->retrieveByCredentials($this->only('email'));

        if (! $user || ! $provider->validateCredentials($user, $this->only('password'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($this->throttleKey());

        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => __('auth.suspended')]);
        }

        if ($staffOnly && ! $user->isStaff()) {
            throw ValidationException::withMessages(['email' => __('auth.staff_only')]);
        }

        Auth::guard('web')->login($user, $this->boolean('remember'));
    }

    /**
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
            'email' => __('auth.throttle', ['seconds' => $seconds]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
