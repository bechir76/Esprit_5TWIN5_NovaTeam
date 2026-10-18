<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

/*
| Routes d'authentification de Laravel Breeze (espace citoyen), avec des URIs françaises.
| Les noms de routes sont ceux de Breeze. La connexion du back office (admin.login) est dans web.php.
*/

Route::middleware('guest')->group(function () {
    Route::get('inscription', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('inscription', [RegisteredUserController::class, 'store']);

    Route::get('connexion', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('connexion', [AuthenticatedSessionController::class, 'store']);

    Route::get('mot-de-passe-oublie', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('mot-de-passe-oublie', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reinitialiser-mot-de-passe/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reinitialiser-mot-de-passe', [NewPasswordController::class, 'store'])
        ->name('password.store');

    // Deuxième étape de la connexion quand la 2FA est activée (front et back office)
    Route::get('connexion/deux-facteurs', [TwoFactorChallengeController::class, 'create'])
        ->name('two-factor.login');

    Route::post('connexion/deux-facteurs', [TwoFactorChallengeController::class, 'store'])
        ->name('two-factor.login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verifier-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verifier-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/notification-verification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirmer-mot-de-passe', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirmer-mot-de-passe', [ConfirmablePasswordController::class, 'store']);

    Route::put('profil/mot-de-passe', [PasswordController::class, 'update'])->name('password.update');

    // Authentification à deux facteurs (formulaires du profil front et back office)
    Route::post('profil/deux-facteurs', [TwoFactorController::class, 'store'])->name('two-factor.enable');
    Route::post('profil/deux-facteurs/confirmer', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::post('profil/deux-facteurs/codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('two-factor.recovery-codes');
    Route::delete('profil/deux-facteurs', [TwoFactorController::class, 'destroy'])->name('two-factor.disable');

    Route::post('deconnexion', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
