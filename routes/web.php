<?php

use App\Http\Controllers\Admin\AuthenticatedSessionController as AdminSessionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InfrastructureController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ZoneController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InfrastructureMapController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
| Les écrans des modules pas encore implémentés sont des Route::view.
| Leurs routes POST/PUT/DELETE utilisent une closure temporaire qui revient
| sur la page avec un message affiché dans le toast.
| Les noms suivent Route::resource (index, create, store, show, edit, update, destroy).
*/

$stub = fn (string $message) => fn () => back()->with('status', $message);

// ---------------------------------------------------------------- Front office

Route::get('/', HomeController::class)->name('home');
Route::get('/carte', InfrastructureMapController::class)->name('map');

Route::view('/incidents', 'front.incidents.index')->name('incidents.index');
Route::view('/incidents/signaler', 'front.incidents.create')->name('incidents.create');
Route::post('/incidents', $stub('Signalement envoyé. Vous serez notifié de son traitement.'))->name('incidents.store');

Route::view('/projets', 'front.projects.index')->name('projects.index');
Route::view('/projets/{project}', 'front.projects.show')->name('projects.show');

// Authentification (module utilisateurs)
Route::middleware('guest')->group(function () {
    Route::get('/connexion', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/connexion', [AuthenticatedSessionController::class, 'store']);
    Route::get('/inscription', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/inscription', [RegisteredUserController::class, 'store']);
    Route::get('/mot-de-passe-oublie', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/mot-de-passe-oublie', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/reinitialiser-mot-de-passe/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reinitialiser-mot-de-passe', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profil/mot-de-passe', [ProfileController::class, 'updatePassword'])->name('password.update');
});

// ---------------------------------------------------------------- Back office

Route::prefix('admin')->name('admin.')->group(function () use ($stub) {
    // Seule la connexion est publique : le reste du back office est réservé au personnel
    Route::get('/login', [AdminSessionController::class, 'create'])->name('login');
    Route::post('/login', [AdminSessionController::class, 'store'])->name('login.store');

    Route::middleware(['auth', 'staff'])->group(function () use ($stub) {
        Route::post('/logout', [AdminSessionController::class, 'destroy'])->name('logout');

        Route::get('/', DashboardController::class)->name('dashboard');

        // Réseau et infrastructures (module 1) : zones de desserte et ouvrages
        Route::resource('zones', ZoneController::class);
        Route::resource('infrastructures', InfrastructureController::class);

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

        // Utilisateurs et rôles : administrateurs uniquement (Gate « manage-users »)
        Route::middleware('can:manage-users')->group(function () {
            Route::patch('/utilisateurs/{user}/statut', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
            Route::resource('utilisateurs', UserController::class)
                ->parameters(['utilisateurs' => 'user'])
                ->except('show')
                ->names('users');

            Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        });

        Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profil/mot-de-passe', [ProfileController::class, 'updatePassword'])->name('password.update');

        Route::view('/parametres', 'back.settings.edit')->name('settings.edit');
        Route::put('/parametres', $stub('Seuils enregistrés.'))->name('settings.update');
    });
});
