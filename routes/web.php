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
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\WaterQualityController;
use App\Models\Project;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
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

// Module 2 : suivi public des incidents ; le signalement est réservé aux utilisateurs connectés à l'e-mail vérifié
Route::get('/incidents', [IncidentReportController::class, 'index'])->name('incidents.index');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/incidents/signaler', [IncidentReportController::class, 'create'])->name('incidents.create');
    Route::post('/incidents', [IncidentReportController::class, 'store'])->name('incidents.store');
    Route::post('/incidents/ia/classer', [IncidentReportController::class, 'classify'])
        ->middleware('throttle:'.config('ai.rate_limit').',1')
        ->name('incidents.classify');
});
// Module 2 : carte publique et suivi d'un signalement (lien signé, sans connexion, ou déclarant connecté)
Route::get('/incidents/carte', [IncidentReportController::class, 'map'])->name('incidents.map');
Route::get('/incidents/{incident}/suivi', [IncidentReportController::class, 'track'])->name('incidents.track');

// Module 3 : projets publics (URL par slug), transparence et vérification des reçus (publiques)
Route::get('/projets', [ProjectController::class, 'index'])->name('projects.index');
Route::get('/projets-transparence', [ProjectController::class, 'transparency'])->name('projects.transparency');
Route::get('/projets/{project:slug}', [ProjectController::class, 'show'])->name('projects.show');
Route::get('/recus/{funding}/verifier', [ReceiptController::class, 'verify'])->middleware('signed')->name('receipts.verify');

// Dons Stripe Checkout : citoyen connecté à l'e-mail vérifié (ProjectPolicy::donate) ;
// /projets/{slug}/contributions est conservée comme alias du formulaire sans JavaScript
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/projects/{project:slug}/donate', [ProjectController::class, 'donate'])->name('projects.donate');
    Route::post('/projets/{project:slug}/contributions', [ProjectController::class, 'donate'])->name('projects.fundings.store');
    Route::get('/projects/{project:slug}/donate/success', [ProjectController::class, 'donateSuccess'])->name('projects.donate.success');
    Route::get('/projects/{project:slug}/donate/cancel', [ProjectController::class, 'donateCancel'])->name('projects.donate.cancel');
});

// Reçu PDF (donateur) et centre de notifications du citoyen
Route::middleware('auth')->group(function () {
    Route::get('/financements/{funding}/recu', [ReceiptController::class, 'download'])->name('fundings.receipt');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/lues', [NotificationController::class, 'markAllRead'])->name('notifications.read');
});

// Webhook Stripe : sans authentification ni CSRF, signature vérifiée par StripeWebhookController
Route::post('/stripe/webhook', StripeWebhookController::class)
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('stripe.webhook');

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
        // Carte, statistiques, export CSV et groupes d'incidents liés (déclarés avant la ressource)
        Route::get('/incidents/carte', [IncidentController::class, 'map'])->name('incidents.map');
        Route::get('/incidents/statistiques', [IncidentController::class, 'stats'])->name('incidents.stats');
        Route::get('/incidents/export', [IncidentController::class, 'export'])->name('incidents.export');
        Route::post('/incidents/groupes/recalculer', [IncidentController::class, 'recluster'])->name('incidents.recluster');
        Route::post('/incidents/groupes/{cluster}/regrouper', [IncidentController::class, 'groupCluster'])->name('incidents.group');
        Route::patch('/incidents/{incident}/statut', [IncidentController::class, 'updateStatus'])->name('incidents.status');
        Route::resource('incidents', IncidentController::class);
        // Planning (calendrier), rapport PDF et rédaction du rapport assistée par l'IA
        Route::get('/interventions/planning', [InterventionController::class, 'planning'])->name('interventions.planning');
        Route::get('/interventions/calendrier', [InterventionController::class, 'events'])->name('interventions.events');
        Route::get('/interventions/{intervention}/pdf', [InterventionController::class, 'pdf'])->name('interventions.pdf');
        Route::post('/interventions/{intervention}/ia/rapport', [InterventionController::class, 'draftReport'])
            ->middleware('throttle:'.config('ai.rate_limit').',1')
            ->name('interventions.draft-report');
        Route::resource('interventions', InterventionController::class);

        // Projets de rénovation et financements (module 3) : URL /admin/projets et /admin/financements,
        // réservés à l'administrateur et au gestionnaire (ProjectPolicy / FundingPolicy)
        Route::middleware('can:viewAny,'.Project::class)->group(function () {
            Route::post('/projets/scores', [AdminProjectController::class, 'scoreAll'])->name('projects.scores');
            Route::post('/projets/description-ia', [AdminProjectController::class, 'describe'])
                ->middleware('throttle:'.config('ai.rate_limit').',1')
                ->name('projects.describe');
            Route::post('/projets/{project}/score', [AdminProjectController::class, 'score'])->name('projects.score');
            Route::resource('projets', AdminProjectController::class)
                ->parameters(['projets' => 'project'])
                ->names('projects');

            Route::get('/financements/tableau-de-bord', [FundingController::class, 'dashboard'])->name('fundings.dashboard');
            Route::get('/financements/export', [FundingController::class, 'export'])->name('fundings.export');
            Route::resource('financements', FundingController::class)
                ->parameters(['financements' => 'funding'])
                ->names('fundings');
        });

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
