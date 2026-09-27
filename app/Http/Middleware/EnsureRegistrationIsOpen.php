<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ferme l'écran d'inscription quand l'instance ne veut plus d'inconnus.
 */
class EnsureRegistrationIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('sport.registration_open'), 404);

        return $next($request);
    }
}
