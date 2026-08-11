<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Servicios\Permisos;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exclusivo del Administrador, sin importar lo que diga la matriz de roles.
 *
 * Es lo que protege la creación de cuentas: quien tenga `configuracion.roles`
 * puede editar la matriz —incluida la suya—, así que el alta de usuarios no
 * puede depender de esa misma matriz.
 */
class ExigeAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Permisos::esAdmin()) {
            abort(403, 'Esta sección es exclusiva del Administrador.');
        }

        return $next($request);
    }
}
