<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Alertas;
use App\Servicios\Pendientes;
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
            'asistencia' => $this->asistencia($request, $suc),
            'panel' => $this->agenda($request, $suc) . '|' . $this->cajas($suc) . '|' . $this->pendientes()
                . '|' . $this->alertas(),
            default => null,
        };

        if ($huella === null) {
            return response()->json(['ok' => false], 400);
        }

        return response()->json(['ok' => true, 'v' => md5($huella), 'cada' => self::CADA]);
    }

    /**
     * Lo que le falta cargar al salón.
     *
     * **Esta lista fallaba en silencio, que es lo peor que puede hacer un
     * aviso.** El panel es una foto del momento en que se pidió, así que a la
     * dueña que deja la pantalla abierta —el caso normal— le seguía diciendo
     * «todo en orden» **después** de que otra persona le sumara el rol
     * Profesional a una cuenta sin turno: ese aviso existe justamente para que
     * no se descubra el día de la cita, y llegaba recién cuando alguien
     * recargaba a mano.
     *
     * **La huella sale de la lista misma, no de un contador barato.** Se probó
     * al revés —contar `usuario_rol`, `usuario_turno`, `persona_servicio` y los
     * timbrados— y no alcanza: cada aviso nuevo habría que acordarse de sumarlo
     * acá, y el que se olvide vuelve a fallar callado. Hasheando lo que la
     * pantalla dibuja, la huella cambia exactamente cuando cambia el aviso.
     *
     * **Y es `mios()`, no `todo()`**: la lista se filtra por permiso, así que
     * dos personas mirando el panel no tienen por qué ver la misma. Con
     * `todo()`, a la recepcionista se le recargaría la pantalla por un timbrado
     * que ella no puede cargar ni ve.
     *
     * Son seis consultas chicas cada veinte segundos. Es más que un `COUNT`, y
     * sigue siendo mucho menos que sostener una conexión abierta.
     */
    private function pendientes(): string
    {
        $puntos = array_map(
            static fn (array $p): string => $p['nivel'] . '·' . $p['que'],
            Pendientes::mios()
        );

        return 'p:' . count($puntos) . ':' . md5(implode('|', $puntos));
    }

    /**
     * La campanita: lo que está pasando ahora. Misma idea que los pendientes,
     * hasheando lo que se dibuja y no un contador.
     */
    private function alertas(): string
    {
        $puntos = array_map(
            static fn (array $a): string => $a['nivel'] . '·' . $a['que'],
            Alertas::mias()
        );

        return 'al:' . count($puntos) . ':' . md5(implode('|', $puntos));
    }

    /**
     * La agenda de un día: cuántas citas hay, cuál es la última y en qué
     * estados están.
     *
     * La suma de estados es lo que detecta lo que más pasa y no cambia el
     * conteo: que alguien marque una cita En proceso, la atienda o la cancele.
     *
     * **Y lo que se cobró y se facturó de esas citas.** Sin eso, dos
     * administradores sobre la misma agenda podían cobrar dos veces: uno
     * cobraba la atención, y en la pantalla del otro —una foto de hace un
     * minuto— seguía el botón «Cobrar». La base lo topa igual (el cobro no
     * puede superar lo que vale la cita), pero el rechazo llegaba después del
     * clic y con un mensaje que no decía que ya estaba cobrada. Con el cobro
     * en la huella, la pantalla del otro se entera sola.
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

        $plata = DB::selectOne(
            'SELECT COUNT(*) AS n, COALESCE(SUM(co.monto),0) AS monto
               FROM cobro co
               JOIN cita c ON c.id_cita = co.id_cita
              WHERE DATE(c.fecha_hora) = ? AND (? = 0 OR c.id_sucursal = ?)',
            [$dia, $suc, $suc]
        );
        $facturas = DB::selectOne(
            'SELECT COUNT(*) AS n, COALESCE(MAX(f.id_factura),0) AS ult
               FROM factura f
               JOIN cita c ON c.id_cita = f.id_cita
              WHERE DATE(c.fecha_hora) = ? AND (? = 0 OR c.id_sucursal = ?)',
            [$dia, $suc, $suc]
        );

        return 'a:' . $dia . ':' . $r->n . ':' . $r->ult . ':' . $r->est
            . ':' . $plata->n . ':' . $plata->monto . ':' . $facturas->n . ':' . $facturas->ult;
    }

    /**
     * La asistencia de un día: quién fichó, a qué hora, y qué faltas hay.
     *
     * **Es la otra punta del mismo fichaje** (7.122.0). La profesional marca su
     * entrada desde su cuenta y quien administra tiene la planilla abierta en
     * otra computadora: sin esto la veía «Sin fichar» hasta recargar, y
     * apretar «Falta» sobre esa foto vieja le pisaba la entrada. La huella
     * cubre las tres cosas que cambian una fila: que aparezca, sus horas y si
     * se justificó.
     */
    private function asistencia(Request $request, int $suc): string
    {
        $fecha = (string) $request->query('fecha', '');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = ahora_bd('Y-m-d');
        }

        $r = DB::selectOne(
            "SELECT COUNT(*) AS n, COALESCE(MAX(a.id_asistencia), 0) AS ult,
                    COALESCE(SUM(a.hora_entrada IS NOT NULL), 0) AS entradas,
                    COALESCE(SUM(a.hora_salida IS NOT NULL), 0) AS salidas,
                    COALESCE(SUM(COALESCE(a.justificada, 9)), 0) AS just
               FROM asistencia a
               JOIN turno_laboral t ON t.id_turno = a.id_turno
              WHERE a.fecha = ? AND (? = 0 OR t.id_sucursal = ?)",
            [$fecha, $suc, $suc]
        );

        return 'as:' . $fecha . ':' . $r->n . ':' . $r->ult . ':' . $r->entradas
            . ':' . $r->salidas . ':' . $r->just;
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

        // LEFT JOIN: el movimiento de una cuenta bancaria no tiene caja
        // (7.121.0), y su local sale de la cuenta.
        $movs = (int) DB::scalar(
            'SELECT COUNT(*) FROM movimiento_caja mc
               LEFT JOIN caja c ON c.id_caja = mc.id_caja
               LEFT JOIN cuenta_bancaria cb ON cb.id_cuenta = mc.id_cuenta
              WHERE DATE(mc.fecha) = CURDATE() AND (? = 0 OR COALESCE(cb.id_sucursal, c.id_sucursal) = ?)',
            [$suc, $suc]
        );

        // **Y el saldo de las cuentas del local** (7.121.1): el panel lo
        // muestra al lado de las cajas, así que una transferencia que entra
        // desde el portal o un arqueo recién hecho tienen que refrescarlo.
        // Son dos o tres cuentas por local: `fn_cuenta_saldo` es barata acá.
        $ctas = DB::selectOne(
            'SELECT COUNT(*) AS cuantas, COALESCE(SUM(fn_cuenta_saldo(id_cuenta)), 0) AS saldo,
                    COALESCE((SELECT COUNT(*) FROM arqueo_cuenta a
                                JOIN cuenta_bancaria x ON x.id_cuenta = a.id_cuenta
                               WHERE x.activo = 1 AND (? = 0 OR x.id_sucursal = ?)), 0) AS arqueos
               FROM cuenta_bancaria WHERE activo = 1 AND (? = 0 OR id_sucursal = ?)',
            [$suc, $suc, $suc, $suc]
        );

        return 'c:' . $r->abiertas . ':' . $r->suma . ':' . $movs
            . ':' . $ctas->cuantas . ':' . $ctas->saldo . ':' . (int) $ctas->arqueos;
    }
}
