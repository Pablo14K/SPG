<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Sucursales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ¿Cambió algo de lo que esta pantalla está mostrando?
 *
 * **El sistema navega a la vieja usanza**: cada pantalla es una foto del
 * momento en que se pidió. En un salón eso se nota — dos personas trabajando
 * sobre la misma agenda, una registra la atención y la otra sigue viendo la
 * cita como Programada hasta que se le ocurre recargar. Lo mismo con la caja
 * abierta en el otro mostrador, o con la cita que entra por el portal mientras
 * alguien mira el día.
 *
 * Esto contesta una sola cosa, y por eso es barato: **una huella** de lo que
 * la pantalla está mirando. Si la de ahora no es la que se llevó al dibujarla,
 * hay cambios. No devuelve datos —no hay nada que filtrar por permiso más allá
 * del acceso a la sección— ni intenta decir QUÉ cambió: eso lo contesta
 * recargar, que es lo que el navegador ya sabe hacer.
 *
 * **Por qué una huella y no `updated_at`:** ninguna de estas tablas lo tiene, y
 * agregarlo a `cita`, `caja` y `movimiento_caja` sería una columna en tres
 * tablas para una comodidad de pantalla. El conteo, el máximo id y la suma de
 * estados cubren lo que de verdad cambia —entró una cita, se atendió, se
 * canceló, se abrió o cerró un cajón— y se resuelven con un índice.
 *
 * Lo que NO hace, a propósito:
 *
 *   · **No recarga solo si hay algo escrito.** `app.js` decide eso: recargar
 *     encima de un formulario a medias es la peor forma de «tiempo real», y es
 *     exactamente la queja que este proyecto ya arregló dos veces con el
 *     borrador de las altas rápidas.
 *   · **No abre un websocket.** Una consulta cada veinte segundos sobre un
 *     `COUNT` indexado le cuesta menos al servidor que mantener la conexión, y
 *     no agrega ninguna pieza que haya que instalar y vigilar.
 */
class VivoController extends Controller
{
    /** Cada cuánto vuelve a preguntar el navegador, en segundos. */
    public const CADA = 20;

    public function estado(Request $request): JsonResponse
    {
        $seccion = (string) $request->query('s', '');
        $suc = (int) (Sucursales::activa() ?: 0);

        $huella = match ($seccion) {
            'agenda' => $this->agenda($request, $suc),
            'cajas' => $this->cajas($suc),
            'panel' => $this->agenda($request, $suc) . '|' . $this->cajas($suc),
            default => null,
        };

        if ($huella === null) {
            return response()->json(['ok' => false], 400);
        }

        return response()->json(['ok' => true, 'v' => md5($huella), 'cada' => self::CADA]);
    }

    /**
     * La agenda de un día: cuántas citas hay, cuál es la última y en qué
     * estados están.
     *
     * La suma de estados es lo que detecta lo que más pasa y no cambia el
     * conteo: que alguien marque una cita En proceso, la atienda o la cancele.
     */
    private function agenda(Request $request, int $suc): string
    {
        $dia = (string) $request->query('dia', '');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia)) {
            $dia = date('Y-m-d');
        }

        $r = DB::selectOne(
            'SELECT COUNT(*) AS n, COALESCE(MAX(id_cita),0) AS ult,
                    COALESCE(SUM(id_estado_cita),0) AS est
               FROM cita
              WHERE DATE(fecha_hora) = ? AND (? = 0 OR id_sucursal = ?)',
            [$dia, $suc, $suc]
        );

        return 'a:' . $dia . ':' . $r->n . ':' . $r->ult . ':' . $r->est;
    }

    /**
     * Los cajones: cuáles están abiertos y cuánto movimiento tuvieron hoy.
     *
     * Con dos puestos de cobro, que el otro abra o cierre cambia lo que esta
     * pantalla puede hacer —sin caja abierta no se cobra— así que es de lo
     * primero que hay que enterarse.
     */
    private function cajas(int $suc): string
    {
        $r = DB::selectOne(
            'SELECT COUNT(*) AS abiertas, COALESCE(SUM(c.id_caja),0) AS suma
               FROM caja c
              WHERE c.id_estado_caja = 1 AND (? = 0 OR c.id_sucursal = ?)',
            [$suc, $suc]
        );

        $movs = (int) DB::scalar(
            'SELECT COUNT(*) FROM movimiento_caja mc
               JOIN caja c ON c.id_caja = mc.id_caja
              WHERE DATE(mc.fecha) = CURDATE() AND (? = 0 OR c.id_sucursal = ?)',
            [$suc, $suc]
        );

        return 'c:' . $r->abiertas . ':' . $r->suma . ':' . $movs;
    }
}
