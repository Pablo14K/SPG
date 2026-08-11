<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Servicios\Permisos;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige un módulo o submódulo concreto.
 *
 *     Route::get(...)->middleware('modulo:facturacion.timbrados');
 *
 * Es el reemplazo de los `requiere_modulo()` que había que escribir a mano al
 * principio de cada acción. Que sea middleware tiene una ventaja concreta: el
 * permiso queda declarado junto a la ruta y se ve de un vistazo cuál pide qué,
 * en vez de estar escondido en la primera línea de cada función.
 *
 * La clave fina es la que va en cada pantalla («personal.turnos»); el único
 * que pide el módulo padre es el landing del módulo.
 */
class ExigeModulo
{
    public function handle(Request $request, Closure $next, string $modulo): Response
    {
        if (! Permisos::puede($modulo)) {
            abort(403, 'Tu rol no tiene acceso a ' . Permisos::nombreModulo($modulo) . '.');
        }

        return $next($request);
    }
}
