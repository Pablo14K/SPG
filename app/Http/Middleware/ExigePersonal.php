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
        // **La sucursal de la sesión tiene que seguir existiendo.**
        //
        // La marca vive en la sesión, y una sesión dura más que muchas cosas:
        // el Administrador puede dar de baja el local, o sacarle la asignación
        // a esa persona, y la sesión seguiría diciendo que trabaja ahí. Peor
        // todavía si la base se reimportó: la ficha de arriba mostraba el
        // nombre de una sucursal que ya no está, y los filtros apuntaban a un
        // id inexistente — o sea, pantallas vacías sin explicación.
        //
        // Se comprueba en cada petición, junto con el rol, que ya se relee acá
        // por el mismo motivo.
        if (Sucursales::activa() !== 0 && ! Sucursales::puedeEntrar(Sucursales::activa())) {
            Sucursales::salir();
            flash('La sucursal en la que estabas trabajando ya no está disponible. Elegí otra.', 'warning');
        }

        if (Sucursales::activa() === 0
            && ! $request->routeIs('sucursal.*')
            && ! Sucursales::resolverAlIngresar()) {
            return redirect()->route('sucursal.elegir');
        }

        return $next($request);
    }
}
