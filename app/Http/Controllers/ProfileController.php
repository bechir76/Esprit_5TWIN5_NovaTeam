<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Profil de l'utilisateur connecté, partagé par le front (/profil) et le back office (/admin/profil) :
 * seule la vue change selon la route.
 */
class ProfileController extends Controller
{
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

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back()->with('status', 'Profil mis à jour.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update(['password' => $validated['password']]);

        return back()->with('status', 'Mot de passe modifié.');
    }
}
