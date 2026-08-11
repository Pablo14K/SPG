<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Servicios\Sesion;
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

        return $next($request);
    }
}
