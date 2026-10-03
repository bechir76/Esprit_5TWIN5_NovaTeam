<?php

use Illuminate\Support\Facades\Route;

/*
| Les écrans sont des Route::view tant qu'il n'y a pas de contrôleurs.
| Les routes POST/PUT/DELETE utilisent une closure temporaire qui revient
| sur la page avec un message affiché dans le toast.
| Les noms suivent Route::resource (index, create, store, show, edit, update, destroy).
*/

$stub = fn (string $message) => fn () => back()->with('status', $message);

// ---------------------------------------------------------------- Front office

Route::view('/', 'front.home')->name('home');
Route::view('/carte', 'front.infrastructures.map')->name('map');

Route::view('/incidents', 'front.incidents.index')->name('incidents.index');
Route::view('/incidents/signaler', 'front.incidents.create')->name('incidents.create');
Route::post('/incidents', $stub('Signalement envoyé. Vous serez notifié de son traitement.'))->name('incidents.store');

Route::view('/projets', 'front.projects.index')->name('projects.index');
Route::view('/projets/{project}', 'front.projects.show')->name('projects.show');

Route::view('/connexion', 'auth.login')->name('login');
Route::post('/connexion', $stub('Connexion simulée : l\'authentification n\'est pas encore branchée.'));
Route::view('/inscription', 'auth.register')->name('register');
Route::post('/inscription', $stub('Inscription simulée : le compte n\'a pas été créé.'));
Route::view('/mot-de-passe-oublie', 'auth.forgot-password')->name('password.request');
Route::post('/mot-de-passe-oublie', $stub('Lien de réinitialisation envoyé.'))->name('password.email');
Route::view('/reinitialiser-mot-de-passe/{token}', 'auth.reset-password')->name('password.reset');
Route::post('/reinitialiser-mot-de-passe', $stub('Mot de passe réinitialisé.'))->name('password.store');
Route::post('/deconnexion', $stub('Déconnexion simulée.'))->name('logout');

Route::view('/profil', 'front.profile.edit')->name('profile.edit');
Route::put('/profil', $stub('Profil mis à jour.'))->name('profile.update');
Route::put('/profil/mot-de-passe', $stub('Mot de passe modifié.'))->name('password.update');

// ---------------------------------------------------------------- Back office

Route::prefix('admin')->name('admin.')->group(function () use ($stub) {
    Route::view('/login', 'back.auth.login')->name('login');
    Route::post('/login', $stub('Connexion simulée : l\'authentification n\'est pas encore branchée.'))->name('login.store');
    Route::post('/logout', $stub('Déconnexion simulée.'))->name('logout');

    Route::view('/', 'back.dashboard')->name('dashboard');

    Route::view('/infrastructures', 'back.infrastructures.index')->name('infrastructures.index');
    Route::view('/infrastructures/creer', 'back.infrastructures.create')->name('infrastructures.create');
    Route::post('/infrastructures', $stub('Infrastructure créée.'))->name('infrastructures.store');

    Route::view('/incidents', 'back.incidents.index')->name('incidents.index');
    Route::view('/incidents/{incident}', 'back.incidents.show')->name('incidents.show');
    Route::put('/incidents/{incident}', $stub('Statut de l\'incident mis à jour.'))->name('incidents.update');
    Route::delete('/incidents/{incident}', fn () => to_route('admin.incidents.index')->with('status', 'Incident supprimé.'))->name('incidents.destroy');

    Route::view('/interventions', 'back.interventions.index')->name('interventions.index');

    Route::view('/projets', 'back.projects.index')->name('projects.index');
    Route::view('/projets/creer', 'back.projects.create')->name('projects.create');
    Route::post('/projets', $stub('Projet enregistré.'))->name('projects.store');

    Route::view('/financements', 'back.fundings.index')->name('fundings.index');
    Route::view('/ia', 'back.ai.insights')->name('ai.insights');

    Route::view('/utilisateurs', 'back.users.index')->name('users.index');
    Route::view('/utilisateurs/creer', 'back.users.create')->name('users.create');
    Route::post('/utilisateurs', $stub('Utilisateur créé.'))->name('users.store');

    Route::view('/roles', 'back.roles.index')->name('roles.index');
    Route::put('/roles/{role}', $stub('Permissions enregistrées.'))->name('roles.update');

    Route::view('/profil', 'back.profile.edit')->name('profile.edit');
    Route::put('/profil', $stub('Profil mis à jour.'))->name('profile.update');
    Route::put('/profil/mot-de-passe', $stub('Mot de passe modifié.'))->name('password.update');

    Route::view('/parametres', 'back.settings.edit')->name('settings.edit');
    Route::put('/parametres', $stub('Seuils enregistrés.'))->name('settings.update');
});
