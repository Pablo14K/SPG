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
 *  · **Cuánto hace que no se usa el sistema.** A los 30 minutos sin actividad
 *    se cierra sola, y se dice el motivo — ver el bloque de abajo.
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

        // **A los 30 minutos sin actividad se cierra, y se dice por qué.**
        //
        // Laravel ya vence la sesión por su cuenta con `SESSION_LIFETIME`, pero
        // cuando lo hace **no queda nada**: la persona cae en el ingreso sin
        // ninguna explicación, y eso se lee como que el sistema la echó o como
        // que se rompió algo. Comprobándolo acá la sesión todavía existe cuando
        // se decide cerrarla, así que se puede contar el motivo — que es lo
        // único que diferencia un cierre por seguridad de una falla.
        //
        // El plazo vive en `spg.sesion.inactividad_min` y `SESSION_LIFETIME` va
        // más alto a propósito, para que el archivo de sesión siga estando
        // cuando este control tiene que hablar.
        $limite = (int) config('spg.sesion.inactividad_min', 30) * 60;
        $ultima = (int) $request->session()->get('spg_ultima_actividad', 0);

        if ($ultima > 0 && (time() - $ultima) > $limite) {
            Sesion::cerrar();
            flash('Cerramos tu sesión: pasaron más de '
                . (int) config('spg.sesion.inactividad_min', 30)
                . ' minutos sin que se usara el sistema. Es por seguridad, para que nadie '
                . 'siga desde una computadora que quedó abierta. Entrá de nuevo para seguir.',
                'warning');

            return redirect()->route('login');
        }

        // **El refresco automático del portal NO cuenta como actividad.** Esa
        // pantalla se consulta sola cada 20 segundos mientras atienden a la
        // clienta: si contara, una pestaña abierta y olvidada mantendría la
        // sesión viva para siempre, que es justo lo que este control evita.
        if (! $request->routeIs('portal.atencion_json')) {
            $request->session()->put('spg_ultima_actividad', time());
        }

        Sesion::refrescarRol();

        return $next($request);
    }
}
