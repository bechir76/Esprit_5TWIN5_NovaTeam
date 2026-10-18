<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

/**
 * Authentification à deux facteurs depuis le profil (front /profil et back /admin/profil) :
 * activation (secret + QR code), confirmation par un premier code TOTP, nouveaux codes de récupération, désactivation.
 */
class TwoFactorController extends Controller
{
    public function __construct(private Google2FA $google2fa) {}

    /** Activation : nouveau secret, en attente de confirmation (QR code affiché sur le profil). */
    public function store(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'two_factor_secret' => $this->google2fa->generateSecretKey(),
            'two_factor_recovery_codes' => User::newRecoveryCodes(),
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->withFragment('deux-facteurs')
            ->with('status', 'Scannez le QR code puis saisissez le code affiché par votre application.');
    }

    /** Confirmation : le premier code TOTP prouve que l'application est bien configurée. */
    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']], [
            'code.required' => 'Saisissez le code à 6 chiffres de votre application.',
            'code.digits' => 'Le code doit contenir 6 chiffres.',
        ]);

        $user = $request->user();

        if (! $user->two_factor_secret || ! $this->google2fa->verifyKey($user->two_factor_secret, $request->input('code'))) {
            throw ValidationException::withMessages(['code' => 'Ce code est invalide ou expiré. Réessayez avec le code actuel.'])
                ->redirectTo(url()->previous().'#deux-facteurs');
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return back()->withFragment('deux-facteurs')
            ->with('status', 'Authentification à deux facteurs activée. Conservez vos codes de récupération.')
            ->with('show_recovery_codes', true);
    }

    /** Nouveaux codes de récupération (les anciens ne sont plus valides). */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasTwoFactorEnabled(), 403);

        $request->user()->forceFill(['two_factor_recovery_codes' => User::newRecoveryCodes()])->save();

        return back()->withFragment('deux-facteurs')
            ->with('status', 'Nouveaux codes de récupération générés.')
            ->with('show_recovery_codes', true);
    }

    /** Désactivation : mot de passe actuel requis. */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('twoFactor', ['password' => ['required', 'current_password']]);

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->withFragment('deux-facteurs')->with('status', 'Authentification à deux facteurs désactivée.');
    }
}
