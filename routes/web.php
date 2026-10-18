<?php

use App\Http\Controllers\Admin\AuthenticatedSessionController as AdminSessionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FundingController;
use App\Http\Controllers\Admin\IncidentController;
use App\Http\Controllers\Admin\InfrastructureController;
use App\Http\Controllers\Admin\InterventionController;
use App\Http\Controllers\Admin\MeasurementController;
use App\Http\Controllers\Admin\ProjectController as AdminProjectController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WaterAnalysisController;
use App\Http\Controllers\Admin\ZoneController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IncidentReportController;
use App\Http\Controllers\InfrastructureMapController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\WaterQualityController;
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

// Module 4 : page publique qualité de l'eau
Route::get('/qualite-eau', WaterQualityController::class)->name('water-quality');

// Module 2 : suivi public des incidents ; le signalement est réservé aux utilisateurs connectés
Route::get('/incidents', [IncidentReportController::class, 'index'])->name('incidents.index');
Route::middleware('auth')->group(function () {
    Route::get('/incidents/signaler', [IncidentReportController::class, 'create'])->name('incidents.create');
    Route::post('/incidents', [IncidentReportController::class, 'store'])->name('incidents.store');
});

// Module 3 : projets publics (URL par slug) ; la contribution est réservée aux utilisateurs connectés
Route::get('/projets', [ProjectController::class, 'index'])->name('projects.index');
Route::get('/projets/{project:slug}', [ProjectController::class, 'show'])->name('projects.show');
Route::post('/projets/{project:slug}/contributions', [ProjectController::class, 'contribute'])
    ->middleware('auth')
    ->name('projects.fundings.store');

// Profil (Breeze) : partagé avec le back office, la vue dépend de la route
Route::middleware('auth')->group(function () {
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profil', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Authentification Laravel Breeze : connexion, inscription, mot de passe, vérification de l'e-mail
require __DIR__.'/auth.php';

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

        // Qualité de l'eau (module 4) : analyses (URL /admin/analyses) et mesures
        Route::resource('analyses', WaterAnalysisController::class)
            ->names('water-analyses');
        Route::resource('analyses.mesures', MeasurementController::class)
            ->except(['index', 'show'])
            ->names('measurements')
            ->shallow();
        // Page index des mesures non conformes
        Route::get('/mesures', [MeasurementController::class, 'index'])->name('measurements.index');

        // Incidents et interventions (module 2) : le statut de l'incident se change depuis sa fiche
        Route::patch('/incidents/{incident}/statut', [IncidentController::class, 'updateStatus'])->name('incidents.status');
        Route::resource('incidents', IncidentController::class);
        Route::resource('interventions', InterventionController::class);

        // Projets de rénovation et financements (module 3) : URL /admin/projets et /admin/financements
        Route::resource('projets', AdminProjectController::class)
            ->parameters(['projets' => 'project'])
            ->names('projects');
        Route::resource('financements', FundingController::class)
            ->parameters(['financements' => 'funding'])
            ->names('fundings');

        Route::view('/ia', 'pages.ai.insights')->name('ai.insights');

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
        Route::put('/profil/mot-de-passe', [PasswordController::class, 'update'])->name('password.update');

        Route::view('/parametres', 'pages.settings.edit')->name('settings.edit');
        Route::put('/parametres', $stub('Seuils enregistrés.'))->name('settings.update');
    });
});
