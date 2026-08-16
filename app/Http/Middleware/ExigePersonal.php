<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Servicios\Sesion;
use App\Servicios\Sucursales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Solo personal: bloquea a la clienta en el panel de gestión y la manda a su
 * portal. No es un error, es que ese no es su lugar.
 */
class ExigePersonal
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Sesion::esCliente()) {
            return redirect()->route('portal.index');
        }

        // **Sin sucursal elegida no se ve nada del sistema.**
        //
        // Es lo que hace que el aislamiento sea real: la agenda, la caja y el
        // stock filtran por `Sucursales::activa()`, y si nadie eligió esa
        // función devuelve 0 — o sea, un filtro que no filtra. Antes que
        // mostrar la operación de todos los locales mezclada, se manda a
        // elegir. Con una sola sucursal esto no se nota: `Sesion::inicio()` ya
        // la dejó puesta al entrar.
        if (Sucursales::activa() === 0
            && ! $request->routeIs('sucursal.*')
            && ! Sucursales::resolverAlIngresar()) {
            return redirect()->route('sucursal.elegir');
        }

        return $next($request);
    }
}
