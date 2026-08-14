<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Servicios\Sesion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige sesión iniciada. Además hace dos comprobaciones por petición, y las
 * dos existen porque el dato puede cambiar mientras la persona sigue adentro:
 *
 *  · **El rol**, que el Administrador puede cambiarle. A quien le sacaban el
 *    rol de Administrador le seguían funcionando sus permisos hasta que
 *    cerrara sesión.
 *  · **Si esta sesión sigue siendo la única de la cuenta.** Cuando alguien
 *    entra con el mismo usuario desde otro equipo, ésta queda desplazada y se
 *    cierra acá, avisando por qué.
 */
class ExigeSesion
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Sesion::activa()) {
            return redirect()->route('login');
        }

        // Una sola sesión por cuenta. Se cierra la vieja, no la nueva: quien
        // acaba de poner la contraseña es el que se queda adentro.
        if ($aviso = Sesion::desplazada()) {
            Sesion::cerrar();
            flash($aviso, 'warning');

            return redirect()->route('login');
        }

        Sesion::refrescarRol();

        return $next($request);
    }
}
