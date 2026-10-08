<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
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
            return view('back.profile.edit', ['user' => $request->user()]);
        }

        // Espace citoyen : « Mes signalements » et « Mes contributions »
        return view('front.profile.edit', [
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
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

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
