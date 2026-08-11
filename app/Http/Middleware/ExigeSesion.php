<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Servicios\Sesion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige sesión iniciada. Además relee el rol desde la base una vez por
 * petición: si el Administrador se lo cambia a alguien, tiene que valer al
 * instante y no recién cuando esa persona vuelva a entrar.
 */
class ExigeSesion
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Sesion::activa()) {
            return redirect()->route('login');
        }

        Sesion::refrescarRol();

        return $next($request);
    }
}
