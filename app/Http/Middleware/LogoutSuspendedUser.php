<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Déconnecte un utilisateur suspendu pendant sa session (ou via « se souvenir de moi »).
 */
class LogoutSuspendedUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route($request->is('admin', 'admin/*') ? 'admin.login' : 'login')
                ->withErrors(['email' => __('auth.suspended')]);
        }

        return $next($request);
    }
}
