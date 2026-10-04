<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias « staff » : réserve le back office aux rôles admin, gestionnaire et technicien.
 */
class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isStaff(), 403, 'Accès réservé au personnel.');

        return $next($request);
    }
}
