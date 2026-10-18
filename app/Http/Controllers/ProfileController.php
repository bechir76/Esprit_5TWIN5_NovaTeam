<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Profil de l'utilisateur connecté (Breeze), partagé par le front (/profil) et le back office (/admin/profil) :
 * seule la vue change selon la route. Le mot de passe est modifié par Auth\PasswordController.
 */
class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        if ($request->routeIs('admin.*')) {
            return view('pages.profile.staff', ['user' => $request->user()]);
        }

        // Espace citoyen : « Mes signalements » et « Mes contributions »
        return view('pages.profile.citizen', [
            'user' => $request->user(),
            'reports' => $request->user()->reportedIncidents()->latest()->get(),
            'contributions' => $request->user()->fundings()->with('project')->orderByDesc('funded_at')->orderByDesc('id')->get(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->except(['avatar', 'remove_avatar']));

        // Nouvelle photo ou retrait : l'ancien fichier est supprimé
        if ($request->hasFile('avatar') || $request->boolean('remove_avatar')) {
            $user->deleteAvatar();
            $user->avatar_path = $request->hasFile('avatar')
                ? $request->file('avatar')->store(User::AVATAR_DIRECTORY, User::AVATAR_DISK)
                : null;
        }

        $emailChanged = $user->isDirty('email');

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        // Nouvelle adresse : nouveau lien de vérification (MustVerifyEmail)
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            return back()->with('status', 'Profil mis à jour. Un lien de vérification a été envoyé à votre nouvelle adresse.');
        }

        return back()->with('status', 'Profil mis à jour.');
    }

    /**
     * Delete the user's account.
     *
     * Réservé aux citoyens : les comptes du personnel sont supprimés par un administrateur
     * (Utilisateurs), qui ne peut pas supprimer le sien.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($user->isStaff()) {
            throw ValidationException::withMessages([
                'password' => 'Les comptes du personnel sont supprimés par un administrateur.',
            ])->errorBag('userDeletion');
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home')->with('status', 'Votre compte a été supprimé.');
    }
}
