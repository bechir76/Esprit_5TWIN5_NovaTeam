<?php

use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\LogoutSuspendedUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'staff' => EnsureUserIsStaff::class,
        ]);

        $middleware->web(append: [
            LogoutSuspendedUser::class,
        ]);

        // Visiteur non connecté : page de connexion du back office pour /admin, sinon celle du front
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*')
            ? route('admin.login')
            : route('login'));

        // Utilisateur déjà connecté sur une page « guest » (connexion, inscription…)
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->isStaff()
            ? route('admin.dashboard')
            : route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
