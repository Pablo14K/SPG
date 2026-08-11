<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Agenda;
use App\Servicios\Auditoria;
use App\Servicios\Calendario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/**
 * El portal de la clienta.
 *
 * Todo pasa por el id de cliente de la sesión: ninguna acción confía en un id
 * que venga del formulario. La cita tiene que ser suya, siempre.
 */
class PortalController extends Controller
{
    /**
     * Criterio de «cita vigente» para el portal.
     *
     * Antes se pedía `fecha_hora >= NOW()`, y por eso una cita que ya había
     * empezado —o cualquier cita de hoy pasada la hora— desaparecía del portal
     * aunque siguiera en curso. Ahora vale mientras no haya terminado, y las
     * de hoy se muestran todo el día.
     */
    private const VIGENTE = "ec.bloquea_agenda = 1
        AND (DATE_ADD(v.fecha_hora, INTERVAL v.duracion_min MINUTE) >= NOW()
             OR DATE(v.fecha_hora) = CURDATE())";

    public function index(): View
    {
        $idc = $this->cliente();

        $proxima = DB::selectOne(
            'SELECT v.*, (v.fecha_hora <= NOW()) AS en_curso
               FROM vw_agenda_citas v
               JOIN cita c ON c.id_cita = v.id_cita
               JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
              WHERE c.id_cliente = ? AND ' . self::VIGENTE . '
              ORDER BY v.fecha_hora LIMIT 1', [$idc]
        );

        // Si la están atendiendo ahora, cuánto va: es lo primero que quiere saber
        $enCurso = null;
        if ($proxima && (int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita = ?', [(int) $proxima->id_cita]) === 5) {
            $enCurso = $this->detalleAtencion($idc, (int) $proxima->id_cita);
        }

        return view('portal.index', ['proxima' => $proxima, 'enCurso' => $enCurso]);
    }

    // ---------- Reservar ----------

    public function disponibilidad(Request $request): JsonResponse
    {
        $this->cliente();

        $servicios = array_map('intval', (array) $request->query('servicios', []));
        $idUsuario = ((int) $request->query('id_usuario', 0)) ?: null;
        $duracion = Agenda::duracion($servicios);

        if ($duracion <= 0) {
            return response()->json(['ok' => false, 'motivo' => 'Elegí primero el o los servicios.']);
        }

        $fecha = (string) $request->query('fecha', '');
        if ($fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return response()->json(['ok' => true, 'duracion' => $duracion,
                'horas' => Agenda::slots($idUsuario, $fecha, $duracion)]);
        }

        return response()->json(['ok' => true, 'duracion' => $duracion,
            'dias' => Agenda::diasConCupo($idUsuario, date('Y-m-d'), (int) config('spg.agenda.dias_vista', 60), $duracion)]);
    }

    public function reservar(): View
    {
        $this->cliente();

        return view('portal.reservar', [
            'profs' => Agenda::profesionales(),
            'servicios' => DB::select(
                'SELECT id_servicio, nombre, precio, duracion_min, requiere_exclusividad
                   FROM servicio WHERE activo = 1 ORDER BY nombre'
            ),
        ]);
    }

    public function guardarReserva(Request $request): RedirectResponse
    {
        $idc = $this->cliente();
        $idUsuario = (int) $request->input('id_usuario', 0);
        $fecha = str_replace('T', ' ', trim((string) $request->input('fecha_hora', '')));
        if (strlen($fecha) === 16) {
            $fecha .= ':00';
        }
        $servicios = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('servicios', []))
        )));
        $obs = trim((string) $request->input('observaciones', '')) ?: null;
        $volver = redirect()->route('portal.reservar');

        $error = null;
        if ($fecha === '' || ! $servicios) {
            $error = 'Elegí al menos un servicio y la fecha/hora.';
        } elseif ($idUsuario && ! $this->personalActivo($idUsuario)) {
            $error = 'Ese profesional no está disponible.';
        } elseif (! strtotime($fecha)) {
            $error = 'La fecha y hora no son válidas.';
        } elseif (strtotime($fecha) < time()) {
            $error = 'No se puede reservar en una fecha que ya pasó.';
        } elseif (strtotime($fecha) > strtotime('+6 months')) {
            $error = 'Solo se puede reservar con hasta 6 meses de anticipación.';
        }
        if ($error) {
            flash($error, 'error');

            return $volver;
        }

        $dur = Agenda::duracion($servicios);
        if ($dur <= 0) {
            flash('Los servicios elegidos no están disponibles.', 'error');

            return $volver;
        }

        // La clienta puede pedir que la atiendan dos a la vez: lavado con una
        // y pedicura con otra.
        $porServicio = (array) $request->input('prof_servicio', []);
        $asignacion = [];
        foreach ($servicios as $sid) {
            $asignacion[$sid] = (int) ($porServicio[$sid] ?? 0);
        }

        if (! $idUsuario) {
            $delPrincipal = Agenda::duracion(array_keys(array_filter($asignacion, fn ($p) => $p === 0)));
            $idUsuario = Agenda::profesionalLibre($fecha, $delPrincipal ?: $dur) ?? 0;
            if (! $idUsuario) {
                flash('Ese horario se ocupó recién. Elegí otro, por favor.', 'warning');

                return $volver;
            }
        }

        foreach (array_unique(array_values($asignacion)) as $idAyuda) {
            if ($idAyuda > 0 && ! $this->personalActivo((int) $idAyuda)) {
                flash('Uno de los profesionales que elegiste ya no está disponible.', 'error');

                return $volver;
            }
        }

        if ($problema = Agenda::validarReparto($asignacion, $idUsuario, $fecha)) {
            flash($problema, 'warning');

            return $volver;
        }
        $dur = Agenda::duracionReparto($asignacion, $idUsuario) ?: $dur;

        try {
            Agenda::agendar($idc, $idUsuario, $fecha, $dur, $obs, $asignacion);
            flash('¡Tu cita fue reservada!');
        } catch (Throwable $ex) {
            // Llegó hasta acá y perdió: otra persona tomó el hueco justo cuando
            // esta reserva pasaba por el candado del procedimiento.
            flash(str_contains($ex->getMessage(), 'disponible')
                ? Agenda::motivoHuecoPerdido($idUsuario, $fecha, $dur)
                : 'No se pudo reservar la cita.', 'error');

            return $volver;
        }

        return redirect()->route('portal.citas');
    }

    // ---------- Mis citas ----------

    public function citas(): View
    {
        $idc = $this->cliente();

        $prox = DB::select(
            'SELECT v.*, (v.fecha_hora <= NOW()) AS en_curso
               FROM vw_agenda_citas v
               JOIN cita c ON c.id_cita = v.id_cita
               JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
              WHERE c.id_cliente = ? AND ' . self::VIGENTE . '
              ORDER BY v.fecha_hora', [$idc]
        );

        $ids = array_map(fn ($p) => (int) $p->id_cita, $prox);
        $excluir = $ids ? ' AND v.id_cita NOT IN (' . implode(',', $ids) . ')' : '';

        return view('portal.citas', [
            'prox' => $prox,
            'pasadas' => DB::select(
                "SELECT v.* FROM vw_agenda_citas v JOIN cita c ON c.id_cita = v.id_cita
                  WHERE c.id_cliente = ? $excluir ORDER BY v.fecha_hora DESC LIMIT 50", [$idc]
            ),
            // Para el enlace de «agendar en mi calendario». Se resuelve acá y
            // no en la vista: es una consulta, y en la vista correría una vez
            // por cita.
            'lugar' => Calendario::lugar(),
        ]);
    }

    public function cancelar(Request $request): RedirectResponse
    {
        $idc = $this->cliente();
        $id = (int) $request->input('id_cita', 0);
        $volver = redirect()->route('portal.citas');

        // La cita tiene que ser del cliente que está en sesión
        $cita = DB::selectOne('SELECT id_cita, fecha_hora, id_estado_cita FROM cita WHERE id_cita = ? AND id_cliente = ?',
            [$id, $idc]);

        if (! $cita) {
            flash('No podés cancelar esa cita.', 'error');

            return $volver;
        }
        if ((int) $cita->id_estado_cita === 3) {
            flash('Esa cita ya estaba cancelada.', 'warning');

            return $volver;
        }
        if (in_array((int) $cita->id_estado_cita, [4, 5], true)) {
            flash('Esa cita ya está en curso o fue atendida: hablá con el salón si necesitás cambiarla.', 'warning');

            return $volver;
        }
        if (strtotime((string) $cita->fecha_hora) < time()) {
            flash('No se puede cancelar una cita que ya pasó.', 'warning');

            return $volver;
        }

        try {
            Agenda::cancelar($id);
            flash('Tu cita fue cancelada.');
        } catch (Throwable) {
            flash('No se pudo cancelar la cita.', 'error');
        }

        return $volver;
    }

    // ---------- La cita en curso, vista desde el sillón ----------

    public function atencion(Request $request): View|RedirectResponse
    {
        $idc = $this->cliente();
        $d = $this->detalleAtencion($idc, (int) $request->query('id', 0));

        if (! $d) {
            flash('Esa cita no es tuya o no existe.', 'error');

            return redirect()->route('portal.citas');
        }

        return view('portal.atencion', $d);
    }

    /** Solo los números, para que la pantalla se refresque sola sin recargar. */
    public function atencionJson(Request $request): JsonResponse
    {
        $idc = $this->cliente();
        $d = $this->detalleAtencion($idc, (int) $request->query('id', 0));

        if (! $d) {
            return response()->json(['ok' => false]);
        }

        return response()->json([
            'ok' => true,
            'estado' => $d['cita']->estado,
            'enCurso' => $d['enCurso'],
            'total' => money($d['total']),
            'sena' => $d['sena'] > 0 ? money($d['sena']) : null,
            'aPagar' => money($d['aPagar']),
            'servicios' => array_map(fn ($s) => [
                'nombre' => $s->nombre, 'quien' => $s->quien,
                'precio' => money($s->precio), 'hecho' => (bool) (int) $s->hecho,
            ], $d['servicios']),
            'productos' => array_map(fn ($p) => [
                'nombre' => $p->nombre,
                'cantidad' => cant(producto_fraccionado((array) $p)
                        ? stock_a_consumo((array) $p, (float) $p->cantidad)
                        : (float) $p->cantidad) . ' ' . unidad_consumo((array) $p),
            ], $d['productos']),
            'pedidos' => count($d['pedidos']),
        ]);
    }

    /**
     * La clienta pide algo más. No se agrega a la cita: queda como pedido para
     * que el profesional lo confirme — es quien sabe si hay tiempo y producto.
     */
    public function pedir(Request $request): RedirectResponse
    {
        $idc = $this->cliente();
        $id = (int) $request->input('id_cita', 0);
        $texto = trim((string) $request->input('pedido', ''));
        $volver = redirect()->route('portal.atencion', ['id' => $id]);

        $cita = DB::selectOne('SELECT id_cita, id_estado_cita, id_usuario FROM cita WHERE id_cita = ? AND id_cliente = ?',
            [$id, $idc]);
        if (! $cita) {
            flash('Esa cita no es tuya.', 'error');

            return redirect()->route('portal.citas');
        }
        if (! in_array((int) $cita->id_estado_cita, [1, 2, 5], true)) {
            flash('Esa cita ya está cerrada: pedíselo directamente al salón.', 'warning');

            return $volver;
        }
        if ($texto === '') {
            flash('Escribí qué querés agregar.', 'error');

            return $volver;
        }

        try {
            DB::insert('INSERT INTO cita_pedido (id_cita, observaciones) VALUES (?,?)',
                [$id, mb_substr($texto, 0, 300)]);
            // Se audita a nombre del profesional: la clienta no tiene cuenta de
            // personal y `auditoria.id_usuario` es NOT NULL.
            Auditoria::registrarComo((int) $cita->id_usuario, 'PEDIDO', 'Portal', 'cita', $id,
                'La clienta pidió desde el portal: ' . $texto);
            flash('Listo, le avisamos a quien te está atendiendo. Te lo va a confirmar en el momento.');
        } catch (Throwable) {
            flash('No se pudo enviar el pedido.', 'error');
        }

        return $volver;
    }

    // ---------- Promociones, valoraciones y recordatorios ----------

    public function promociones(): View
    {
        $idc = $this->cliente();

        return view('portal.promociones', [
            'fid' => DB::selectOne('SELECT * FROM vw_cliente_fidelizacion WHERE id_cliente = ?', [$idc]),
            'promos' => DB::select(
                'SELECT * FROM descuento
                  WHERE activo = 1 AND (fecha_inicio IS NULL OR fecha_inicio <= CURDATE())
                                  AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
                  ORDER BY nombre'
            ),
        ]);
    }

    public function valoraciones(): View
    {
        $idc = $this->cliente();

        return view('portal.valoraciones', [
            'pendientes' => DB::select(
                "SELECT c.id_cita, c.fecha_hora,
                        (SELECT GROUP_CONCAT(s.nombre SEPARATOR ', ')
                           FROM cita_servicio cs JOIN servicio s ON s.id_servicio = cs.id_servicio
                          WHERE cs.id_cita = c.id_cita) AS servicios
                   FROM cita c
                  WHERE c.id_cliente = ? AND c.id_estado_cita = 4
                    AND NOT EXISTS (SELECT 1 FROM calificacion cal WHERE cal.id_cita = c.id_cita)
                  ORDER BY c.fecha_hora DESC", [$idc]
            ),
            'hechas' => DB::select(
                'SELECT cal.*, c.fecha_hora FROM calificacion cal JOIN cita c ON c.id_cita = cal.id_cita
                  WHERE c.id_cliente = ? ORDER BY cal.fecha DESC', [$idc]
            ),
        ]);
    }

    public function calificar(Request $request): RedirectResponse
    {
        $idc = $this->cliente();
        $idCita = (int) $request->input('id_cita', 0);
        $puntaje = (int) $request->input('puntaje', 0);
        $comentario = trim((string) $request->input('comentario', '')) ?: null;
        $volver = redirect()->route('portal.valoraciones');

        $ok = DB::scalar('SELECT COUNT(*) FROM cita WHERE id_cita = ? AND id_cliente = ? AND id_estado_cita = 4',
            [$idCita, $idc]);
        if (! $ok || $puntaje < 1 || $puntaje > 5) {
            flash('No se pudo registrar la valoración.', 'error');

            return $volver;
        }

        try {
            DB::insert('INSERT INTO calificacion (id_cita,puntaje,comentario) VALUES (?,?,?)',
                [$idCita, $puntaje, $comentario]);
            flash('¡Gracias por tu valoración!');
        } catch (Throwable) {
            flash('Esa cita ya fue calificada.', 'error');
        }

        return $volver;
    }

    public function preferencias(Request $request): View|RedirectResponse
    {
        $idc = $this->cliente();

        if ($request->isMethod('post')) {
            $dias = entero($request->input('dias_antes'), 1);
            $activo = $request->boolean('activo') ? 1 : 0;

            if ($dias < 0 || $dias > 15) {
                flash('La anticipación tiene que estar entre 0 y 15 días.', 'error');

                return redirect()->route('portal.preferencias');
            }

            DB::statement(
                'INSERT INTO preferencia_recordatorio (id_cliente, dias_antes, activo) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE dias_antes = VALUES(dias_antes), activo = VALUES(activo)',
                [$idc, $dias, $activo]
            );

            flash($activo
                ? 'Listo: te vamos a avisar ' . ($dias === 0 ? 'el mismo día de tu cita.' : $dias . ' día(s) antes de tu cita.')
                : 'Desactivamos los recordatorios por correo.');

            return redirect()->route('portal.preferencias');
        }

        return view('portal.preferencias', [
            'pref' => DB::selectOne('SELECT dias_antes, activo FROM preferencia_recordatorio WHERE id_cliente = ?', [$idc])
                ?: (object) ['dias_antes' => 1, 'activo' => 1],
        ]);
    }

    // -----------------------------------------------------------------

    /**
     * El id de cliente de la sesión. Puede faltar si la ficha se creó después
     * del login, así que se reintenta antes de rendirse.
     */
    private function cliente(): int
    {
        $idc = (int) session('id_cliente', 0);
        if (! $idc) {
            $idc = (int) (DB::scalar('SELECT id_cliente FROM cliente WHERE id_usuario = ? LIMIT 1',
                [(int) session('uid')]) ?: 0);
            session(['id_cliente' => $idc ?: null]);
        }
        if (! $idc) {
            abort(403, 'Tu usuario no está vinculado a una ficha de cliente. Contactá al salón.');
        }

        return $idc;
    }

    private function personalActivo(int $idUsuario): bool
    {
        return (bool) DB::scalar(
            'SELECT COUNT(*) FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.id_usuario = ? AND u.activo = 1 AND r.es_personal = 1', [$idUsuario]
        );
    }

    /**
     * Lo que le están haciendo y cuánto va. Lo usan la pantalla y el JSON que
     * la refresca, así que el armado vive en un solo lugar.
     */
    private function detalleAtencion(int $idCliente, int $idCita): ?array
    {
        $cita = DB::selectOne(
            "SELECT c.id_cita, c.fecha_hora, c.id_estado_cita, ec.nombre AS estado,
                    CONCAT(pe.nombre,' ',pe.apellido) AS profesional
               FROM cita c
               JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
               JOIN usuario u  ON u.id_usuario = c.id_usuario
               JOIN persona pe ON pe.id_persona = u.id_persona
              WHERE c.id_cita = ? AND c.id_cliente = ?", [$idCita, $idCliente]
        );
        if (! $cita) {
            return null;
        }

        $servicios = DB::select(
            "SELECT s.nombre, s.precio,
                    CONCAT(pe.nombre,' ',pe.apellido) AS quien,
                    (SELECT COUNT(*) FROM servicio_realizado sr
                      WHERE sr.id_cita = cs.id_cita AND sr.id_servicio = cs.id_servicio) AS hecho
               FROM cita_servicio cs
               JOIN servicio s ON s.id_servicio = cs.id_servicio
               JOIN cita c     ON c.id_cita = cs.id_cita
               JOIN usuario u  ON u.id_usuario = COALESCE(cs.id_usuario, c.id_usuario)
               JOIN persona pe ON pe.id_persona = u.id_persona
              WHERE cs.id_cita = ? ORDER BY s.nombre", [$idCita]
        );

        $total = 0.0;
        foreach ($servicios as $s) {
            $total += (float) $s->precio;
        }

        // Lo que ya señó se descuenta: es plata que ya puso
        $sena = (float) DB::scalar('SELECT fn_cita_sena(?)', [$idCita]);

        return [
            'cita' => $cita,
            'servicios' => $servicios,
            'productos' => DB::select(
                'SELECT p.nombre, pu.cantidad, p.unidad_medida, p.contenido, p.unidad_consumo
                   FROM producto_utilizado pu
                   JOIN producto p ON p.id_producto = pu.id_producto
                   JOIN servicio_realizado sr ON sr.id_servicio_realizado = pu.id_servicio_realizado
                  WHERE sr.id_cita = ? ORDER BY p.nombre', [$idCita]
            ),
            'total' => $total,
            'sena' => $sena,
            'aPagar' => max(0.0, $total - $sena),
            'enCurso' => (int) $cita->id_estado_cita === 5,
            'pedidos' => DB::select(
                'SELECT observaciones, fecha_registro FROM cita_pedido
                  WHERE id_cita = ? ORDER BY fecha_registro DESC', [$idCita]
            ),
        ];
    }
}
