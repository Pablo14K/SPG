<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Agenda;
use App\Servicios\Auditoria;
use App\Servicios\Bd;
use App\Servicios\Calendario;
use App\Servicios\Canje;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     * empezado desaparecía del portal aunque siguiera en curso. Se agregó
     * entonces `OR DATE(v.fecha_hora) = CURDATE()`, y eso pasó al otro extremo:
     * **cualquier cita de hoy quedaba en «Próximas» hasta la medianoche**, aunque
     * hubiera terminado ocho horas antes. La clienta creía que todavía le
     * quedaba una cita por delante.
     *
     * Lo que hay que sostener son las dos cosas a la vez: que la cita no
     * desaparezca mientras está pasando, y que deje de anunciarse cuando ya
     * pasó. La segunda rama queda acotada a la cita que **está siendo
     * atendida** —«En proceso»— y a la que se pasó de hora sin que nadie la
     * tocara —«Atrasada», que es justamente la que la clienta necesita ver para
     * reclamar—. Una Programada cuya hora terminó ya no es próxima: es pasada.
     */
    private const VIGENTE = "ec.bloquea_agenda = 1
        AND (DATE_ADD(v.fecha_hora, INTERVAL v.duracion_min MINUTE) >= NOW()
             OR (DATE(v.fecha_hora) = CURDATE() AND c.id_estado_cita IN (5, 7)))";

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

    public function reservar(Request $request): View
    {
        $this->cliente();

        // **La clienta elige el local, no está atada a ninguno.**
        //
        // Por eso el portal entra normal y la sucursal se pregunta acá: la
        // misma persona se corta el pelo cerca del trabajo un martes y cerca
        // de su casa un sábado. Hasta que elija no se le muestran servicios ni
        // horarios, porque no serían de ningún lado en particular.
        $sucursales = DB::select('SELECT id_sucursal, nombre, direccion, ciudad FROM sucursal
                                   WHERE activo = 1 ORDER BY nombre');

        $elegida = (int) $request->query('sucursal', 0);
        if (! $elegida && count($sucursales) === 1) {
            $elegida = (int) $sucursales[0]->id_sucursal;   // con una sola no se pregunta
        }
        if ($elegida && ! in_array($elegida, array_map(fn ($s) => (int) $s->id_sucursal, $sucursales), true)) {
            $elegida = 0;
        }

        return view('portal.reservar', [
            'sucursales' => $sucursales,
            'sucursal' => $elegida,
            'profs' => $elegida ? Agenda::profesionales($elegida) : [],
            // Sólo los servicios que ESE local publica. El catálogo es único
            // —«Corte de dama» es un servicio con un precio— y cada sucursal
            // marca cuáles ofrece, en `servicio_sucursal`.
            // **Sin ninguna fila, el servicio vale en TODAS.** Con `JOIN` a secas
            // pasaba lo contrario: un servicio que nadie marcó no se publicaba
            // en ningún lado, así que abrir la segunda sucursal la dejaba sin un
            // solo servicio y la clienta que la elegía no veía nada que
            // reservar. Es la convención del resto del sistema —los canjes y la
            // lista de servicios ya la usan— y acá estaba al revés.
            'servicios' => $elegida ? DB::select(
                'SELECT s.id_servicio, s.nombre, s.precio, s.duracion_min, s.requiere_exclusividad
                   FROM servicio s
                  WHERE s.activo = 1
                    AND (EXISTS (SELECT 1 FROM servicio_sucursal ss
                                  WHERE ss.id_servicio = s.id_servicio AND ss.id_sucursal = ?)
                         OR NOT EXISTS (SELECT 1 FROM servicio_sucursal ss2
                                         WHERE ss2.id_servicio = s.id_servicio))
                  ORDER BY s.nombre', [$elegida]
            ) : [],
            // Lo que ya canjeó y todavía puede usar. **No cambia nada del
            // motor de la agenda**: el servicio canjeado ocupa el mismo tiempo
            // y lo tiene que hacer un profesional que lo haga, en un horario
            // libre. Lo único que cambia es que no se cobra.
            'canjes' => Canje::deCliente($this->cliente(), true),
        ]);
    }

    public function guardarReserva(Request $request): RedirectResponse
    {
        $idc = $this->cliente();
        $idUsuario = (int) $request->input('id_usuario', 0);

        // **La sucursal que eligió la clienta.** El formulario la manda desde
        // que existe el selector, pero acá no se leía: la cita terminaba en la
        // sucursal de la FICHA del profesional, así que quien reservaba en el
        // segundo local generaba una cita en la casa central — y el día de la
        // cita nadie la esperaba donde ella fue. De ahí en cadena: el
        // comprobante se numera con el timbrado de esa otra sede y el cobro
        // entra a su cajón.
        $idSucursal = (int) $request->input('id_sucursal', 0);
        if ($idSucursal && ! DB::scalar('SELECT COUNT(*) FROM sucursal WHERE id_sucursal = ? AND activo = 1',
                                        [$idSucursal])) {
            flash('Esa sucursal no está disponible.', 'error');

            return redirect()->route('portal.reservar');
        }
        if (! $idSucursal) {
            // Con un solo local no hace falta elegir: se resuelve solo, igual
            // que al entrar. Con varios, la pantalla no deja llegar hasta acá.
            $idSucursal = (int) (DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1') ?: 0);
        }

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
        } elseif ($idUsuario && ! $this->personalActivo($idUsuario, $idSucursal)) {
            $error = 'Ese profesional no atiende en la sucursal que elegiste.';
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
            $idUsuario = Agenda::profesionalLibre($fecha, $delPrincipal ?: $dur, $idSucursal) ?? 0;
            if (! $idUsuario) {
                flash('Ese horario se ocupó recién. Elegí otro, por favor.', 'warning');

                return $volver;
            }
        }

        foreach (array_unique(array_values($asignacion)) as $idAyuda) {
            if ($idAyuda > 0 && ! $this->personalActivo((int) $idAyuda, $idSucursal)) {
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
            $idCita = Agenda::agendar($idc, $idUsuario, $fecha, $dur, $obs, $asignacion, $idSucursal ?: null);

            // Los canjes que eligió quedan atados a esta cita, y con eso el
            // servicio va **a cero** en el comprobante. Se comprueban contra
            // SU id de cliente, no contra el formulario: si no, cambiando un
            // campo oculto se gastaría el canje de otra persona.
            $usados = Canje::aplicarACita((array) $request->input('canjes', []), $idCita, $idc);

            flash('¡Tu cita fue reservada!'
                . ($usados ? ' Usaste ' . $usados . ' canje(s): ese servicio no se te cobra.' : ''));
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

    /**
     * La clienta registra que va a dejar una seña.
     *
     * **Esto NO es un pago**, y no puede serlo: el sistema no tiene pasarela
     * de pago ni la va a tener. Es un aviso — la plata la recibe el salón, y
     * un profesional confirma desde la agenda, que es cuando se registra el
     * cobro de verdad con `sp_registrar_sena`. Hasta entonces no toca la caja
     * ni el saldo de nada, justamente para que un aviso no se confunda con
     * plata que entró.
     *
     * El profesional además puede registrar la seña directo, sin que exista
     * ninguna solicitud: este camino es un agregado, no un reemplazo.
     */
    public function senaRegistrar(Request $request): RedirectResponse
    {
        $idc = $this->cliente();
        $idCita = (int) $request->input('id_cita', 0);
        $monto = num($request->input('monto'));
        $volver = redirect()->route('portal.citas');

        // La cita se comprueba contra el cliente de la SESIÓN, no contra lo que
        // venga en el formulario: si no, cambiando el id oculto se le registra
        // una seña a la cita de otra persona.
        $cita = DB::selectOne(
            'SELECT c.id_cita, c.fecha_hora, c.id_estado_cita
               FROM cita c WHERE c.id_cita = ? AND c.id_cliente = ?', [$idCita, $idc]
        );

        $error = null;
        if (! $cita) {
            $error = 'Esa cita no es tuya.';
        } elseif (in_array((int) $cita->id_estado_cita, [3, 4, 6], true)) {
            $error = 'Esa cita ya está cerrada, no se le puede dejar una seña.';
        } elseif ($monto <= 0) {
            $error = 'Escribí cuánto vas a dejar de seña.';
        } elseif (DB::scalar(
            'SELECT COUNT(*) FROM sena_solicitud WHERE id_cita = ? AND id_cobro IS NULL AND rechazada_en IS NULL',
            [$idCita]
        )) {
            $error = 'Ya registraste una seña para esa cita y el salón todavía no la confirmó.';
        }
        if ($error) {
            flash($error, 'error');

            return $volver;
        }

        DB::insert('INSERT INTO sena_solicitud (id_cita, monto) VALUES (?,?)', [$idCita, $monto]);

        flash('Anotamos que vas a dejar ' . money($monto) . ' de seña para tu cita del '
            . fecha($cita->fecha_hora) . '. Se confirma en el salón cuando entregues el dinero.');

        return $volver;
    }

    public function citas(): View
    {
        $idc = $this->cliente();

        // `sena` es lo ya cobrado y confirmado; `sena_pedida`, lo que la
        // clienta registró y el salón todavía no confirmó. Son dos cosas
        // distintas y la pantalla las dice distinto: una es plata que ya
        // entró, la otra es un aviso de que va a entrar.
        $prox = DB::select(
            'SELECT v.*, (v.fecha_hora <= NOW()) AS en_curso,
                    fn_cita_sena(v.id_cita) AS sena,
                    (SELECT COALESCE(SUM(ss.monto),0) FROM sena_solicitud ss
                      WHERE ss.id_cita = v.id_cita
                        AND ss.id_cobro IS NULL AND ss.rechazada_en IS NULL) AS sena_pedida
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
            // Lo que puede llevarse con sus puntos, y lo que ya canjeó. El
            // programa de fidelización sólo sumaba: se acumulaban puntos y no
            // había forma de gastarlos.
            'canjeables' => Canje::catalogo(),
            'puntos' => Canje::puntos($idc),
            'misCanjes' => Canje::deCliente($idc),
        ]);
    }

    /**
     * La clienta canjea sus puntos por un servicio.
     *
     * No agenda nada: le queda **guardado** y lo usa cuando reserva. Separarlo
     * es lo que permite canjear hoy y decidir el día después, que es como la
     * gente usa un cupón.
     */
    public function canjear(Request $request): RedirectResponse
    {
        $idc = $this->cliente();
        $idServicio = (int) $request->input('id_servicio', 0);
        $volver = redirect()->route('portal.promociones');

        try {
            $idCanje = Canje::canjear($idc, $idServicio);
            $c = DB::selectOne(
                'SELECT s.nombre, cj.vence_en FROM canje cj
                   JOIN servicio s ON s.id_servicio = cj.id_servicio
                  WHERE cj.id_canje = ?', [$idCanje]
            );
            Auditoria::registrarComo(session('uid'), 'CANJE', 'Portal', 'canje', $idCanje,
                'Canje de ' . ($c->nombre ?? '') . ' por puntos');

            flash('¡Listo! Canjeaste ' . ($c->nombre ?? 'el servicio')
                . '. Lo podés usar al reservar, hasta el ' . fecha($c->vence_en ?? null, 'd/m/Y') . '.');
        } catch (Throwable $ex) {
            flash(Bd::traducir($ex, [
                'no alcanzan' => 'Todavía no te alcanzan los puntos para ese canje.',
                'no se puede canjear' => 'Ese servicio ya no se puede canjear por puntos.',
            ], 'No se pudo hacer el canje. El detalle quedó registrado.'), 'error');

            if (! str_contains($ex->getMessage(), 'alcanzan') && ! str_contains($ex->getMessage(), 'canjear')) {
                Log::error('Canje del portal', ['cliente' => $idc, 'error' => $ex->getMessage()]);
            }
        }

        return $volver;
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

    /**
     * ¿Atiende, y atiende ACÁ?
     *
     * Con `es_personal = 1` a secas se aceptaba a cualquiera del salón, así que
     * la clienta podía quedar agendada con alguien que trabaja en otro local.
     * La asignación real vive en `usuario_sucursal`, no en la ficha: la ficha
     * dice dónde trabaja habitualmente, no dónde está disponible hoy.
     */
    private function personalActivo(int $idUsuario, ?int $idSucursal = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
                 WHERE u.id_usuario = ? AND u.activo = 1 AND r.es_personal = 1';
        $par = [$idUsuario];

        if ($idSucursal) {
            $sql .= ' AND EXISTS (SELECT 1 FROM usuario_sucursal us
                                   WHERE us.id_usuario = u.id_usuario AND us.id_sucursal = ?)';
            $par[] = $idSucursal;
        }

        return (bool) DB::scalar($sql, $par);
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
