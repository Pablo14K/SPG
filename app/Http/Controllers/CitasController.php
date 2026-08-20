<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Agenda;
use App\Servicios\Auditoria;
use App\Servicios\Bd;
use App\Servicios\Borrador;
use App\Servicios\Caja;
use App\Servicios\Canje;
use App\Servicios\Notificaciones;
use App\Servicios\Permisos;
use App\Servicios\Persona;
use App\Servicios\Sucursales;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Citas.
 *
 * El motor de disponibilidad vive en App\Servicios\Agenda; acá está lo que
 * hace la pantalla. Las dos reglas que no hay que perder de vista:
 *
 *  · **Agendar y reprogramar van dentro de una transacción**, porque el
 *    procedimiento toma un candado sobre el profesional y lo suelta al
 *    confirmar. Sin ella, dos personas se quedan con el mismo horario.
 *  · **Registrar la atención saca de `cita_servicio` lo que se agendó y no se
 *    hizo**, porque `sp_emitir_factura` arma el detalle desde ahí: si queda un
 *    servicio no realizado, el cliente lo termina pagando.
 */
class CitasController extends Controller
{
    /** Estados: 1 Programada · 2 Reprogramada · 3 Cancelada · 4 Atendida · 5 En proceso · 6 Ausente */
    private const CERRADAS = [3, 4];

    public function index(): View
    {
        return view('citas.index', [
            'subs' => Permisos::tarjetasPermitidas([
                ['p' => 'citas.agenda', 'ruta' => 'citas.agenda', 'ic' => 'calendar-week',
                 't' => 'Agenda', 'd' => 'Citas del día y próximas'],
                ['p' => 'citas.agenda', 'ruta' => 'citas.form', 'ic' => 'calendar-plus',
                 't' => 'Nueva cita', 'd' => 'Agendar con control de disponibilidad'],
                ['p' => 'citas.ausencias', 'ruta' => 'citas.ausencias', 'ic' => 'calendar-x',
                 't' => 'Excepciones', 'd' => 'Feriados, licencias y bloqueos'],
            ]),
        ]);
    }

    /** Huecos reales para la pantalla de Nueva cita (lo consume el JS). */
    public function disponibilidad(Request $request): JsonResponse
    {
        $servicios = array_map('intval', (array) $request->query('servicios', []));
        $idUsuario = ((int) $request->query('id_usuario', 0)) ?: null;
        $duracion = Agenda::duracion($servicios);

        if ($duracion <= 0) {
            return response()->json(['ok' => false, 'motivo' => 'Elegí primero el o los servicios.']);
        }
        if ($idUsuario && ! DB::scalar(
            'SELECT COUNT(*) FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.id_usuario = ? AND u.activo = 1 AND r.es_personal = 1', [$idUsuario]
        )) {
            return response()->json(['ok' => false, 'motivo' => 'Ese profesional ya no está disponible.']);
        }

        $fecha = (string) $request->query('fecha', '');
        if ($fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return response()->json([
                'ok' => true, 'duracion' => $duracion,
                'horas' => Agenda::slots($idUsuario, $fecha, $duracion),
            ]);
        }

        return response()->json([
            'ok' => true, 'duracion' => $duracion,
            'motivo' => Agenda::motivoSinCupo($duracion, $idUsuario),
            'dias' => Agenda::diasConCupo($idUsuario, date('Y-m-d'), (int) config('spg.agenda.dias_vista', 60), $duracion),
        ]);
    }

    // -----------------------------------------------------------------
    //  Agenda del día
    // -----------------------------------------------------------------

    public function agenda(Request $request): View
    {
        $dia = (string) $request->query('dia', date('Y-m-d'));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia) || ! strtotime($dia)) {
            $dia = date('Y-m-d');
        }
        $verTodo = $this->veTodaLaAgenda();

        $par = ['d' => $dia];
        $soloMias = '';
        if (! $verTodo) {
            $soloMias = ' AND c.id_usuario = :yo';
            $par['yo'] = (int) session('uid');
        }

        // **La agenda es la del local en el que se está trabajando.**
        // Sin esto, el Administrador que entra a una sucursal veía también las
        // citas de la otra mezcladas en la misma grilla, y el mismo horario
        // aparecía ocupado por alguien que atiende a treinta cuadras.
        $soloMias .= Sucursales::filtro('c', $par);

        // El comprobante viene en la misma consulta porque la agenda tiene que
        // poder contestar «¿esto ya se cobró?» sin salir de la pantalla: una
        // cita queda Atendida y el dinero todavía no entró, y como la clienta
        // no siempre pide factura, nadie se acuerda de pasar por Facturación.
        // Se mira sólo el comprobante NO anulado (estado 1). `nro_comprobante`
        // NO es una columna de `factura`: lo arma `fn_factura_nro()`.
        $rows = DB::select(
            "SELECT v.*, fn_cita_sena(v.id_cita) AS sena,
                    (SELECT ss.id_solicitud FROM sena_solicitud ss
                      WHERE ss.id_cita = v.id_cita AND ss.id_cobro IS NULL AND ss.rechazada_en IS NULL
                      ORDER BY ss.id_solicitud LIMIT 1) AS id_solicitud,
                    (SELECT ss.monto FROM sena_solicitud ss
                      WHERE ss.id_cita = v.id_cita AND ss.id_cobro IS NULL AND ss.rechazada_en IS NULL
                      ORDER BY ss.id_solicitud LIMIT 1) AS sena_pedida,
                    -- El comprobante que adjuntó: es lo que deja confirmar sin
                    -- llamar al banco ni creerle de palabra.
                    (SELECT ss.comprobante FROM sena_solicitud ss
                      WHERE ss.id_cita = v.id_cita AND ss.id_cobro IS NULL AND ss.rechazada_en IS NULL
                      ORDER BY ss.id_solicitud LIMIT 1) AS sena_comprobante,
                    f.id_factura,
                    -- **Cuánto vale la cita**, para que el modal de cobro lo diga
                    -- antes y no después. Es la MISMA expresión con la que la base
                    -- topea la seña, así que la pantalla no puede ofrecer un monto
                    -- que el procedimiento vaya a rechazar.
                    (SELECT COALESCE(SUM(s2.precio),0) FROM cita_servicio cs2
                       JOIN servicio s2 ON s2.id_servicio = cs2.id_servicio
                      WHERE cs2.id_cita = v.id_cita) AS total_cita,
                    CASE WHEN f.id_factura IS NULL THEN NULL
                         ELSE fn_factura_nro(f.id_factura) END AS nro_comprobante,
                    CASE WHEN f.id_factura IS NULL THEN NULL
                         ELSE fn_factura_saldo(f.id_factura) END AS saldo
               FROM vw_agenda_citas v
               JOIN cita c ON c.id_cita = v.id_cita
               LEFT JOIN factura f ON f.id_cita = v.id_cita AND f.id_estado_factura = 1
              WHERE DATE(v.fecha_hora) = :d $soloMias
              ORDER BY (c.id_estado_cita IN (4, 3, 6)) ASC, v.fecha_hora", $par
        );

        // La seña mueve plata: el botón solo aparece si el rol maneja caja
        $puedeCobrar = Permisos::puede('facturacion.cobros');

        return view('citas.agenda', [
            'rows' => $rows,
            'dia' => $dia,
            'verTodo' => $verTodo,
            'puedeCobrar' => $puedeCobrar,
            'metodos' => $puedeCobrar
                ? DB::select(// `tipo` lo necesita el componente de cobro para saber qué detalle pedir:
                // tarjeta, banco o nada. Sin él la pantalla revienta al dibujarse.
                "SELECT id_metodo_pago, nombre, tipo FROM metodo_pago
                  WHERE activo = 1 ORDER BY (tipo = 'EFECTIVO') DESC, nombre")
                : [],
            'caja' => $puedeCobrar ? Caja::abierta() : null,
            // Emitir el comprobante es de `facturacion.facturas`, no de cobros:
            // son dos permisos distintos y quien sólo cobra no debería emitir.
            'puedeFacturar' => Permisos::puede('facturacion.facturas'),
        ]);
    }

    public function form(Request $request): View
    {
        // **El formulario se limpia solo.**
        //
        // Lo que llena los campos es `old()`, y `old()` sirve para una sola
        // cosa: que un intento fallido vuelva con lo que la persona ya había
        // cargado, para corregir y reintentar. Pero el borrador de un alta
        // rápida —crear una clienta sin salir de acá— también deja escrito
        // `_old_input`, y ese sí sobrevive a que la persona abandone la
        // pantalla: al volver a «Nueva cita» aparecían la clienta y los
        // servicios de la cita anterior, que es justo lo que no se quiere.
        //
        // Se distingue por el rastro que deja cada camino: el error redirige
        // con `spg_form_error`. Sin esa marca, la visita es nueva y se olvida
        // lo que haya quedado. No hace falta ningún botón.
        if (! $request->session()->get('spg_form_error')) {
            $request->session()->forget('_old_input');
        }

        return view('citas.form', [
            'clientes' => DB::select(
                'SELECT c.id_cliente, pe.nombre, pe.apellido, pe.cedula, pe.telefono
                   FROM cliente c JOIN persona pe ON pe.id_persona = c.id_persona
                  WHERE c.activo = 1 ORDER BY pe.apellido, pe.nombre'
            ),
            'profs' => Agenda::profesionales(),
            'servicios' => DB::select(
                'SELECT s.id_servicio, s.nombre, s.precio, s.duracion_min, s.requiere_exclusividad,
                        cs.nombre AS categoria
                   FROM servicio s
                   JOIN categoria_servicio cs ON cs.id_categoria_servicio = s.id_categoria_servicio
                  WHERE s.activo = 1 ORDER BY cs.nombre, s.nombre'
            ),
            'sel_cliente' => (int) $request->query('cliente', 0),
            // **Los canjes también se usan desde el mostrador.** Hasta la
            // 7.28.0 el campo `canjes[]` existía sólo en el portal, así que a
            // la clienta que canjeaba en el local —que es la mayoría: no tiene
            // cuenta— se le descontaban los puntos y no tenía dónde gastar el
            // vale. En 60 días se hicieron 5 canjes y se usó 0.
            //
            // Vienen los de TODAS las clientas porque la clienta se elige en
            // esta misma pantalla; el JS muestra los de la elegida.
            'canjes' => Canje::disponiblesDelSalon(),
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $idCliente = (int) $request->input('id_cliente', 0);
        $idUsuario = (int) $request->input('id_usuario', 0);   // 0 = sin preferencia
        $fecha = str_replace('T', ' ', trim((string) $request->input('fecha_hora', '')));
        if (strlen($fecha) === 16) {
            $fecha .= ':00';
        }
        $servicios = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('servicios', []))
        )));
        $obs = trim((string) $request->input('observaciones', '')) ?: null;

        $error = null;
        if (! $idCliente || $fecha === '' || ! $servicios) {
            $error = 'Elegí cliente, al menos un servicio y la fecha/hora.';
        } elseif (! DB::scalar('SELECT COUNT(*) FROM cliente WHERE id_cliente = ? AND activo = 1', [$idCliente])) {
            $error = 'Ese cliente no existe o está inactivo.';
        } elseif ($idUsuario && ! $this->esPersonalActivo($idUsuario)) {
            $error = 'Ese profesional no está activo.';
        } elseif (! strtotime($fecha)) {
            $error = 'La fecha y hora no son válidas.';
        } elseif (strtotime($fecha) < strtotime('-1 day')) {
            $error = 'No se pueden agendar citas con más de un día de atraso.';
        } elseif (strtotime($fecha) > strtotime('+1 year')) {
            $error = 'No se pueden agendar citas con más de un año de anticipación.';
        }
        if ($error) {
            flash($error, 'error');

            return redirect()->route('citas.form')->with("spg_form_error", true)->withInput();
        }

        // A quién le toca cada servicio: la pantalla manda prof_servicio[id],
        // y 0 (o nada) significa «lo hace el profesional principal».
        $porServicio = (array) $request->input('prof_servicio', []);
        $asignacion = [];
        foreach ($servicios as $sid) {
            $asignacion[$sid] = (int) ($porServicio[$sid] ?? 0);
        }

        $dur = Agenda::duracion($servicios);
        if ($dur <= 0) {
            flash('Los servicios elegidos no son válidos.', 'error');

            return redirect()->route('citas.form')->with("spg_form_error", true)->withInput();
        }

        // Sin profesional de preferencia se asigna el primero libre por el
        // bloque que le va a tocar (los servicios que no se repartieron a otro).
        if (! $idUsuario) {
            $delPrincipal = Agenda::duracion(array_keys(array_filter($asignacion, fn ($p) => $p === 0)));

            if (! $delPrincipal) {
                // **Todos los servicios se repartieron**: al principal no le
                // queda nada que hacer, así que no se busca a nadie de afuera
                // —eso metía a la propietaria en citas en las que no atendía—.
                // La cita queda a nombre de quien más trabajo tiene adentro.
                $idUsuario = Agenda::principalDelReparto($asignacion);
            } else {
                $idUsuario = Agenda::profesionalLibre($fecha, $delPrincipal) ?? 0;
            }

            if (! $idUsuario) {
                flash('A esa hora no queda ningún profesional libre. Elegí otro horario.', 'warning');

                return redirect()->route('citas.form', ['cliente' => $idCliente])->with("spg_form_error", true)->withInput();
            }
        }

        foreach (array_unique(array_values($asignacion)) as $idAyuda) {
            if ($idAyuda > 0 && ! $this->esPersonalActivo((int) $idAyuda)) {
                flash('Uno de los profesionales elegidos ya no está activo.', 'error');

                return redirect()->route('citas.form', ['cliente' => $idCliente])->with("spg_form_error", true)->withInput();
            }
        }

        // Exclusividad + hueco de CADA profesional. Se vuelve a preguntar acá
        // porque entre que se dibujó la pantalla y se apretó el botón, otro
        // pudo tomar el horario.
        if ($problema = Agenda::validarReparto($asignacion, $idUsuario, $fecha)) {
            flash($problema, 'warning');

            return redirect()->route('citas.form', ['cliente' => $idCliente])->with("spg_form_error", true)->withInput();
        }

        // La cita dura el bloque más largo: los profesionales trabajan en
        // paralelo, no uno detrás del otro.
        $dur = Agenda::duracionReparto($asignacion, $idUsuario) ?: $dur;

        try {
            $idCita = Agenda::agendar($idCliente, $idUsuario, $fecha, $dur, $obs, $asignacion);
            $equipo = count(array_filter(array_values($asignacion))) > 0;
            Auditoria::registrar('ALTA', 'Citas', 'cita', $idCita,
                'Cita agendada para ' . $fecha . ($equipo ? ' con varios profesionales' : ''));

            // Los canjes que se marcaron quedan atados a esta cita, y con eso
            // el servicio va **a cero** en el comprobante. `aplicarACita()`
            // comprueba contra la clienta de la cita y contra los servicios
            // que la cita tiene de verdad, así que un canje marcado sin marcar
            // su servicio no se gasta: se avisa y queda para la próxima.
            $pedidos = array_unique(array_filter(array_map('intval', (array) $request->input('canjes', []))));
            $usados = Canje::aplicarACita($pedidos, $idCita, $idCliente);
            $sobraron = count($pedidos) - $usados;

            flash('Cita agendada para el ' . fecha($fecha) . '.'
                . ($usados ? ' Se usó ' . $usados . ' canje(s): ese servicio no se cobra.' : '')
                . ($sobraron > 0
                    ? ' Ojo: ' . $sobraron . ' canje(s) NO se aplicaron porque su servicio no quedó en la cita. '
                      . 'La clienta los conserva.'
                    : ''),
                $sobraron > 0 ? 'warning' : 'success');
        } catch (Throwable $ex) {
            // Si el procedimiento dice «no disponible» acá, es porque otra
            // persona se quedó con el hueco entre nuestra verificación y el
            // candado: son milisegundos, pero pasa.
            $msg = $ex->getMessage();

            // El disparador que impide repetir el mismo servicio el mismo día
            // ya arma un mensaje pensado para quien atiende —nombra el
            // servicio y dice qué hacer—, así que se muestra tal cual en vez
            // de reemplazarlo por uno genérico. Se recorta lo que MariaDB le
            // pega adelante y atrás (el SQLSTATE y la consulta entera).
            if (str_contains($msg, 'No se repite el mismo servicio')) {
                $desde = strpos($msg, 'Esa clienta');
                $hasta = strpos($msg, 'cancela la otra cita primero.');
                flash($desde !== false && $hasta !== false
                    ? substr($msg, $desde, $hasta - $desde + 28)
                    : 'Esa clienta ya tiene ese servicio agendado para ese mismo día.', 'warning');

                return redirect()->route('citas.form', ['cliente' => $idCliente])->with("spg_form_error", true)->withInput();
            }

            if (! str_contains($msg, 'disponible') && ! str_contains($msg, 'habilitado')) {
                Log::error('Agendar cita: ' . $msg);
            }

            flash(str_contains($msg, 'disponible')
                ? Agenda::motivoHuecoPerdido($idUsuario, $fecha, $dur)
                : (str_contains($msg, 'habilitado')
                    ? 'El profesional no está habilitado para alguno de esos servicios.'
                    : 'No se pudo agendar la cita. El detalle quedó registrado.'), 'error');

            return redirect()->route('citas.form', ['cliente' => $idCliente])->with("spg_form_error", true)->withInput();
        }

        return redirect()->route('citas.agenda', ['dia' => substr($fecha, 0, 10)]);
    }

    public function estado(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_cita', 0);
        $estado = (int) $request->input('id_estado_cita', 0);
        $dia = (string) $request->input('dia', date('Y-m-d'));
        $volver = redirect()->route('citas.agenda', ['dia' => $dia]);

        $cita = DB::selectOne('SELECT id_cita, id_usuario, id_estado_cita FROM cita WHERE id_cita = ?', [$id]);
        if (! $cita) {
            flash('Esa cita no existe.', 'error');

            return $volver;
        }
        if ($this->citaAjena($cita)) {
            abort(403, 'Esa cita es de otro profesional.');
        }
        if (in_array((int) $cita->id_estado_cita, self::CERRADAS, true)) {
            flash('Esa cita ya está cerrada: no se le puede cambiar el estado.', 'warning');

            return $volver;
        }

        // El 1 permite deshacer un «Ausente» marcado por error: si no, la cita
        // quedaba sin ninguna acción posible y había que tocar la base a mano.
        $nombres = [1 => 'Programada', 5 => 'En proceso', 6 => 'Ausente'];
        if (! isset($nombres[$estado])) {
            flash('Estado no válido.', 'error');

            return $volver;
        }

        DB::update('UPDATE cita SET id_estado_cita = ? WHERE id_cita = ?', [$estado, $id]);
        Auditoria::registrar('MODIFICACION', 'Citas', 'cita', $id, 'Estado cambiado a ' . $nombres[$estado]);
        flash('Estado de la cita actualizado.');

        return $volver;
    }

    public function cancelar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_cita', 0);
        $dia = (string) $request->input('dia', date('Y-m-d'));
        $volver = redirect()->route('citas.agenda', ['dia' => $dia]);

        $cita = DB::selectOne('SELECT id_usuario, id_estado_cita FROM cita WHERE id_cita = ?', [$id]);
        if (! $cita) {
            flash('Esa cita no existe.', 'error');

            return $volver;
        }
        if ($this->citaAjena($cita)) {
            abort(403, 'Esa cita es de otro profesional.');
        }
        if ((int) $cita->id_estado_cita === 3) {
            flash('Esa cita ya estaba cancelada.', 'warning');

            return $volver;
        }
        if ((int) $cita->id_estado_cita === 4) {
            flash('No se puede cancelar una cita ya atendida.', 'warning');

            return $volver;
        }

        try {
            Agenda::cancelar($id);
            Auditoria::registrar('CANCELACION', 'Citas', 'cita', $id, 'Cita cancelada');
            flash('Cita cancelada.');
        } catch (Throwable) {
            flash('No se pudo cancelar la cita.', 'error');
        }

        return $volver;
    }

    public function reprogramar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_cita', 0);
        $nueva = str_replace('T', ' ', trim((string) $request->input('nueva_fecha', '')));
        $dia = (string) $request->input('dia', date('Y-m-d'));
        $volver = redirect()->route('citas.agenda', ['dia' => $dia]);

        $cita = DB::selectOne('SELECT id_usuario, id_estado_cita FROM cita WHERE id_cita = ?', [$id]);
        if (! $cita) {
            flash('Esa cita no existe.', 'error');

            return $volver;
        }
        if ($this->citaAjena($cita)) {
            abort(403, 'Esa cita es de otro profesional.');
        }
        if (in_array((int) $cita->id_estado_cita, self::CERRADAS, true)) {
            flash('Solo se pueden reprogramar citas que no estén canceladas ni atendidas.', 'warning');

            return $volver;
        }
        if ($nueva === '' || ! strtotime($nueva)) {
            flash('Elegí la nueva fecha y hora.', 'error');

            return $volver;
        }
        if (strtotime($nueva) < strtotime('-1 day')) {
            flash('No se puede reprogramar a una fecha pasada.', 'error');

            return $volver;
        }

        try {
            Agenda::reprogramar($id, $nueva);
            Auditoria::registrar('MODIFICACION', 'Citas', 'cita', $id, 'Reprogramada para ' . $nueva);
            flash('Cita reprogramada para el ' . fecha($nueva) . '.');
        } catch (Throwable $ex) {
            flash(str_contains($ex->getMessage(), 'disponible')
                ? 'El profesional no está disponible en el nuevo horario.'
                : 'No se pudo reprogramar.', 'error');

            return $volver;
        }

        return redirect()->route('citas.agenda', ['dia' => substr($nueva, 0, 10)]);
    }

    // -----------------------------------------------------------------
    //  Alta rápida de cliente, sin salir de Nueva cita
    // -----------------------------------------------------------------

    public function clienteRapido(Request $request): RedirectResponse
    {
        $d = [
            'nombre' => trim((string) $request->input('nombre', '')),
            'apellido' => trim((string) $request->input('apellido', '')),
            'cedula' => trim((string) $request->input('cedula', '')) ?: null,
            'telefono' => trim((string) $request->input('telefono', '')) ?: null,
            'email' => trim((string) $request->input('email', '')) ?: null,
        ];
        // El formulario de la cita viaja en `_borrador`: registrar un cliente no
        // puede borrar los servicios y el horario que ya se habían elegido.
        $volver = Borrador::conservar(redirect()->route('citas.form'), $request, $request->except(['_borrador', '_token']));

        if ($d['nombre'] === '' || $d['apellido'] === '') {
            flash('Para registrar al cliente necesito al menos nombre y apellido.', 'error');

            return $volver;
        }
        if ($d['email'] && ! filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            flash('El email del cliente no tiene un formato válido.', 'error');

            return $volver;
        }
        if ($err = Persona::error($d)) {
            flash($err, 'error');

            return $volver;
        }

        // Si la cédula ya está cargada puede ser la misma persona registrada
        // como empleada: en ese caso se le crea la ficha de cliente sobre esa
        // persona, en vez de duplicarla.
        $idPersona = Persona::porDocumento($d['cedula']);
        if ($idPersona && DB::scalar('SELECT COUNT(*) FROM cliente WHERE id_persona = ?', [$idPersona])) {
            flash('Ya existe un cliente con esa cédula: buscalo en la lista.', 'warning');

            return $volver;
        }

        try {
            $idc = DB::transaction(function () use ($idPersona, $d) {
                $idPersona = Persona::guardar($idPersona, $d);
                DB::insert('INSERT INTO cliente (id_persona, activo) VALUES (?,1)', [$idPersona]);

                return (int) DB::getPdo()->lastInsertId();
            });
            Auditoria::registrar('ALTA', 'Clientes', 'cliente', $idc, 'Alta rápida desde Nueva cita');
            flash('Cliente ' . $d['nombre'] . ' ' . $d['apellido'] . ' registrado y seleccionado.');

            return Borrador::conservar(redirect()->route('citas.form', ['cliente' => $idc]), $request);
        } catch (Throwable) {
            flash('No se pudo registrar al cliente.', 'error');

            return $volver;
        }
    }

    // -----------------------------------------------------------------
    //  Excepciones de agenda (feriados, licencias, bloqueos)
    // -----------------------------------------------------------------

    /**
     * Pasarle a otro profesional las citas futuras de alguien (AG-03).
     *
     * Sin esto, dar de baja a una persona —o cargarle una licencia larga—
     * dejaba sus citas **ocupando la agenda igual**, y había que abrirlas de a
     * una para cambiarles el profesional. El aviso a las clientas sí salía,
     * pero el horario seguía reservado a nombre de alguien que no iba a estar.
     */
    public function reasignar(Request $request): View
    {
        $de = (int) $request->query('de', 0);

        // **Se muestran también las de quien está inactivo**, que es el caso
        // que motiva la pantalla: `Agenda::profesionales()` ya no lo devuelve.
        $origen = $de ? DB::selectOne(
            "SELECT u.id_usuario, CONCAT(pe.nombre,' ',pe.apellido) AS nombre, u.activo
               FROM usuario u JOIN persona pe ON pe.id_persona = u.id_persona
              WHERE u.id_usuario = ?", [$de]
        ) : null;

        return view('citas.reasignar', [
            'de' => $de,
            'origen' => $origen,
            // Quién tiene citas futuras: el selector de origen sale de acá y no
            // de la lista de profesionales, justamente para que aparezca quien
            // ya está dado de baja.
            // OJO con `ONLY_FULL_GROUP_BY`, que está activo: todo lo que va en
            // el SELECT y no es agregado tiene que estar en el GROUP BY, con la
            // MISMA expresión. Agrupar sólo por `u.id_usuario` da error 1055 —
            // y no se ve leyendo el código, se ve al abrir la pantalla.
            'conCitas' => DB::select(
                "SELECT u.id_usuario, CONCAT(pe.nombre,' ',pe.apellido) AS nombre, u.activo,
                        COUNT(*) AS pendientes
                   FROM cita c
                   JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
                   JOIN usuario u  ON u.id_usuario = c.id_usuario
                   JOIN persona pe ON pe.id_persona = u.id_persona
                  WHERE ec.bloquea_agenda = 1 AND c.fecha_hora >= NOW()
                  GROUP BY u.id_usuario, pe.nombre, pe.apellido, u.activo
                  ORDER BY u.activo, pe.nombre"
            ),
            'citas' => $de ? DB::select(
                "SELECT c.id_cita, c.fecha_hora, fn_cita_duracion(c.id_cita) AS dur,
                        CONCAT(pe_cl.nombre,' ',pe_cl.apellido) AS cliente,
                        (SELECT GROUP_CONCAT(s.nombre SEPARATOR ', ') FROM cita_servicio cs
                          JOIN servicio s ON s.id_servicio = cs.id_servicio
                         WHERE cs.id_cita = c.id_cita) AS servicios
                   FROM cita c
                   JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
                   JOIN cliente cl ON cl.id_cliente = c.id_cliente
                   JOIN persona pe_cl ON pe_cl.id_persona = cl.id_persona
                  WHERE c.id_usuario = ? AND ec.bloquea_agenda = 1 AND c.fecha_hora >= NOW()
                  ORDER BY c.fecha_hora", [$de]
            ) : [],
            'profs' => Agenda::profesionales(),
        ]);
    }

    public function reasignarGuardar(Request $request): RedirectResponse
    {
        $de = (int) $request->input('de', 0);
        $a = (int) $request->input('a', 0);
        $elegidas = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('citas', []))
        )));
        $volver = redirect()->route('citas.reasignar', ['de' => $de]);

        if (! $a || ! $this->esPersonalActivo($a)) {
            flash('Elegí a quién le pasás las citas.', 'error');

            return $volver;
        }
        if ($a === $de) {
            flash('Esa es la misma persona: elegí otra.', 'error');

            return $volver;
        }
        if (! $elegidas) {
            flash('No marcaste ninguna cita.', 'warning');

            return $volver;
        }

        $nombre = (string) DB::scalar(
            "SELECT CONCAT(pe.nombre,' ',pe.apellido) FROM usuario u
               JOIN persona pe ON pe.id_persona = u.id_persona WHERE u.id_usuario = ?", [$a]
        );

        // **Una por una, y las que no entran se dicen por su nombre.** Mover en
        // bloque sin mirar la disponibilidad sería vender dos veces el mismo
        // horario del que las recibe.
        $movidas = 0;
        $ocupadas = [];
        foreach ($elegidas as $idCita) {
            $suya = (int) DB::scalar(
                'SELECT COUNT(*) FROM cita c JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
                  WHERE c.id_cita = ? AND c.id_usuario = ? AND ec.bloquea_agenda = 1 AND c.fecha_hora >= NOW()',
                [$idCita, $de]
            );
            if (! $suya) {
                continue;   // un id inventado en el POST no hace nada
            }

            try {
                if (Agenda::reasignar($idCita, $a)) {
                    $movidas++;
                    Auditoria::registrar('MODIFICACION', 'Citas', 'cita', $idCita,
                        'Cita reasignada a ' . $nombre);

                    continue;
                }
            } catch (Throwable $ex) {
                Log::error('No se pudo reasignar la cita ' . $idCita, ['error' => $ex->getMessage()]);
            }

            $cuando = DB::scalar('SELECT fecha_hora FROM cita WHERE id_cita = ?', [$idCita]);
            $ocupadas[] = fecha($cuando, 'd/m H:i');
        }

        if ($movidas) {
            flash($movidas . ' cita(s) pasaron a ' . $nombre . '. El horario no cambió, '
                . 'así que la clienta no tiene que hacer nada.');
        }
        if ($ocupadas) {
            flash($nombre . ' no está libre en ' . count($ocupadas) . ' de esos horarios ('
                . implode(', ', array_slice($ocupadas, 0, 6))
                . (count($ocupadas) > 6 ? '…' : '')
                . '). Esas quedan como estaban: pasalas a otra persona o reprogramalas.', 'warning');
        }

        return $volver;
    }

    public function ausencias(): View
    {
        return view('citas.ausencias', [
            'rows' => DB::select(
                "SELECT a.*, ta.nombre AS tipo,
                        COALESCE(CONCAT(pe_u.nombre,' ',pe_u.apellido),'Todo el salón') AS quien,
                        COALESCE(su.nombre,'Todas las sucursales') AS donde
                   FROM ausencia_agenda a
                   JOIN tipo_ausencia ta ON ta.id_tipo_ausencia = a.id_tipo_ausencia
                   LEFT JOIN usuario u   ON u.id_usuario = a.id_usuario
                   LEFT JOIN persona pe_u ON pe_u.id_persona = u.id_persona
                   LEFT JOIN sucursal su  ON su.id_sucursal = a.id_sucursal
                  WHERE a.activo = 1
                    AND (:s = 0 OR a.id_sucursal IS NULL OR a.id_sucursal = :s2)
                  ORDER BY a.fecha_inicio DESC LIMIT 100",
                ['s' => Sucursales::activa(), 's2' => Sucursales::activa()]
            ),
            'profs' => Agenda::profesionales(),
            'sucursales' => DB::select('SELECT id_sucursal, nombre FROM sucursal WHERE activo = 1 ORDER BY nombre'),
            'tipos' => DB::select('SELECT * FROM tipo_ausencia ORDER BY nombre'),
        ]);
    }

    public function ausenciaGuardar(Request $request): RedirectResponse
    {
        $d = [
            'id_usuario' => ((int) $request->input('id_usuario', 0)) ?: null,
            // **Quien la registra indica el local.** Vacio = en todas, que es
            // como se sigue cargando un feriado del salon; una sucursal, solo
            // ahi. Antes no se preguntaba y toda ausencia valia en todas.
            'id_sucursal' => ((int) $request->input('id_sucursal', 0)) ?: null,
            'id_tipo_ausencia' => (int) $request->input('id_tipo_ausencia', 0),
            'fecha_inicio' => str_replace('T', ' ', trim((string) $request->input('fecha_inicio', ''))),
            'fecha_fin' => str_replace('T', ' ', trim((string) $request->input('fecha_fin', ''))),
            'motivo' => trim((string) $request->input('motivo', '')) ?: null,
        ];
        $volver = redirect()->route('citas.ausencias');

        $error = null;
        if (! $d['id_tipo_ausencia'] || ! $d['fecha_inicio'] || ! $d['fecha_fin']) {
            $error = 'Completá el tipo y el rango de fechas.';
        } elseif (! DB::scalar('SELECT COUNT(*) FROM tipo_ausencia WHERE id_tipo_ausencia = ?', [$d['id_tipo_ausencia']])) {
            $error = 'Elegí un tipo de excepción válido.';
        } elseif ($d['id_usuario'] && ! $this->esPersonalActivo((int) $d['id_usuario'])) {
            $error = 'Ese profesional no está activo.';
        } elseif ($d['id_sucursal'] && ! DB::scalar(
            'SELECT COUNT(*) FROM sucursal WHERE id_sucursal = ? AND activo = 1', [$d['id_sucursal']])) {
            $error = 'Esa sucursal no está disponible.';
        } elseif (! strtotime($d['fecha_inicio']) || ! strtotime($d['fecha_fin'])) {
            $error = 'Las fechas no son válidas.';
        } elseif (strtotime($d['fecha_fin']) <= strtotime($d['fecha_inicio'])) {
            $error = 'La fecha de fin tiene que ser posterior a la de inicio.';
        }
        if ($error) {
            flash($error, 'error');

            return $volver->with("spg_form_error", true)->withInput();
        }

        // Avisar si el bloqueo pisa citas ya agendadas
        $choques = (int) DB::scalar(
            'SELECT COUNT(*) FROM cita c JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
              WHERE ec.bloquea_agenda = 1
                AND (:u1 IS NULL OR c.id_usuario = :u2)
                AND (:s1 IS NULL OR c.id_sucursal = :s2)
                AND c.fecha_hora < :fin AND c.fecha_hora >= :ini',
            ['u1' => $d['id_usuario'], 'u2' => $d['id_usuario'],
             's1' => $d['id_sucursal'], 's2' => $d['id_sucursal'],
             'ini' => $d['fecha_inicio'], 'fin' => $d['fecha_fin']]
        );

        try {
            DB::insert(
                'INSERT INTO ausencia_agenda (id_usuario,id_sucursal,id_tipo_ausencia,fecha_inicio,fecha_fin,motivo)
                 VALUES (:id_usuario,:id_sucursal,:id_tipo_ausencia,:fecha_inicio,:fecha_fin,:motivo)', $d
            );
            Auditoria::registrar('ALTA', 'Citas', 'ausencia_agenda', (int) DB::getPdo()->lastInsertId(),
                'Excepción ' . $d['fecha_inicio'] . ' a ' . $d['fecha_fin']);

            // A cada clienta que tenía cita en ese rango se le avisa, con el
            // enlace del correo para reprogramar o cambiar de profesional. El
            // aviso entra en la cola de `notificacion` y lo despacha el cron:
            // avisar acá mismo dejaría la pantalla esperando al servidor SMTP.
            $avisadas = Notificaciones::avisarProfesionalNoDisponible(
                $d['id_usuario'], $d['fecha_inicio'], $d['fecha_fin'],
                (string) ($d['motivo'] ?? '')
            );

            flash('Excepción registrada.'
                . ($choques ? " Hay $choques cita(s) agendada(s) dentro de ese rango." : '')
                . ($avisadas ? " Se le avisó a $avisadas clienta(s) para que reprogramen." : ''),
                $choques ? 'warning' : 'success');
        } catch (Throwable) {
            flash('No se pudo registrar la excepción. Revisá que las fechas sean válidas y que el rango '
                . 'no esté ya cargado; si sigue igual, el detalle quedó en el registro del sistema.', 'error');
        }

        return $volver;
    }

    // -----------------------------------------------------------------
    //  Registrar la atención
    //
    //  Acá se anota qué servicios se hicieron y qué productos se gastaron. El
    //  consumo descuenta el stock solo, por el disparador de la base.
    // -----------------------------------------------------------------

    public function atender(Request $request): View|RedirectResponse
    {
        $id = (int) $request->query('id', 0);

        $cita = DB::selectOne(
            "SELECT c.*, ec.nombre AS estado,
                    CONCAT(pe_cl.nombre,' ',pe_cl.apellido) AS cliente,
                    CONCAT(pe_u.nombre,' ',pe_u.apellido) AS profesional
               FROM cita c
               JOIN cliente cl    ON cl.id_cliente = c.id_cliente
               JOIN persona pe_cl ON pe_cl.id_persona = cl.id_persona
               JOIN usuario u     ON u.id_usuario = c.id_usuario
               JOIN persona pe_u  ON pe_u.id_persona = u.id_persona
               JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
              WHERE c.id_cita = ?", [$id]
        );
        if (! $cita) {
            flash('Cita no encontrada.', 'error');

            return redirect()->route('citas.agenda');
        }
        if ($this->citaAjena($cita)) {
            abort(403, 'Esa cita es de otro profesional.');
        }

        return view('citas.atender', [
            'cita' => $cita,
            // Para avisar ANTES de que cargue todo y le rebote al guardar.
            'fichaje' => $this->estadoFichaje($cita),
            // Todos los servicios activos: los agendados vienen marcados y el
            // resto se puede sumar sobre la marcha si la clienta pide algo más.
            'servicios' => DB::select(
                'SELECT s.id_servicio, s.nombre, s.precio, cs.nombre AS categoria,
                        (SELECT COUNT(*) FROM cita_servicio x
                          WHERE x.id_cita = :c1 AND x.id_servicio = s.id_servicio) AS agendado,
                        (SELECT COUNT(*) FROM servicio_realizado sr
                          WHERE sr.id_cita = :c2 AND sr.id_servicio = s.id_servicio) AS ya
                   FROM servicio s
                   JOIN categoria_servicio cs ON cs.id_categoria_servicio = s.id_categoria_servicio
                  WHERE s.activo = 1 ORDER BY cs.nombre, s.nombre',
                ['c1' => $id, 'c2' => $id]
            ),
            // **A qué servicio se le imputa el producto: sólo a los de ESTA
            // cita.** El selector ofrecía el catálogo entero, así que se podía
            // cargar el shampoo «en Pedicura» cuando la clienta no pidió
            // pedicura — y ahí el consumo queda colgado de un servicio que no
            // existe en la cita, con lo que ni el costo ni la comisión salen
            // donde corresponde. Son los que pidió más los que ya se
            // registraron: los dos son servicios reales de esta atención.
            'servDeLaCita' => DB::select(
                'SELECT DISTINCT s.id_servicio, s.nombre
                   FROM servicio s
                  WHERE EXISTS (SELECT 1 FROM cita_servicio cs
                                 WHERE cs.id_cita = :c3 AND cs.id_servicio = s.id_servicio)
                     OR EXISTS (SELECT 1 FROM servicio_realizado sr
                                 WHERE sr.id_cita = :c4 AND sr.id_servicio = s.id_servicio)
                  ORDER BY s.nombre',
                ['c3' => $id, 'c4' => $id]
            ),
            'productos' => DB::select(
                'SELECT p.id_producto, p.nombre, p.unidad_medida, p.contenido, p.unidad_consumo,
                        fn_producto_stock(p.id_producto, ps.id_sucursal) AS stock
                   FROM producto p
                   JOIN producto_sucursal ps ON ps.id_producto = p.id_producto AND ps.id_sucursal = ?
                  WHERE p.activo = 1 AND ps.activo = 1 ORDER BY p.nombre',
                [Sucursales::activa() ?: 1]
            ),
            'usados' => DB::select(
                'SELECT p.nombre, pu.cantidad, p.unidad_medida, p.contenido, p.unidad_consumo,
                        s.nombre AS servicio
                   FROM producto_utilizado pu
                   JOIN producto p ON p.id_producto = pu.id_producto
                   JOIN servicio_realizado sr ON sr.id_servicio_realizado = pu.id_servicio_realizado
                   JOIN servicio s ON s.id_servicio = sr.id_servicio
                  WHERE sr.id_cita = ? ORDER BY s.nombre, p.nombre', [$id]
            ),
            // Si ya se facturó no se puede seguir agregando: la factura quedaría corta
            'factura' => DB::selectOne(
                'SELECT id_factura, fn_factura_nro(id_factura) AS nro FROM factura
                  WHERE id_cita = ? AND id_estado_factura = 1 LIMIT 1', [$id]
            ),
            // Lo que la clienta pidió desde su celular mientras la atienden
            'pedidos' => DB::select(
                'SELECT id_pedido, observaciones, fecha_registro, atendido
                   FROM cita_pedido WHERE id_cita = ? ORDER BY atendido, fecha_registro DESC', [$id]
            ),
        ]);
    }

    public function atenderGuardar(Request $request): RedirectResponse
    {
        $idCita = (int) $request->input('id_cita', 0);
        $realizados = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('servicios', []))
        )));
        $prodIds = (array) $request->input('producto', []);
        $prodCant = (array) $request->input('cantidad', []);
        $prodServ = (array) $request->input('servicio_de', []);   // a qué servicio se imputa cada producto
        $obs = trim((string) $request->input('observaciones', '')) ?: null;
        $volver = redirect()->route('citas.atender', ['id' => $idCita]);

        $cita = DB::selectOne('SELECT id_cita, id_usuario, id_estado_cita, fecha_hora FROM cita WHERE id_cita = ?', [$idCita]);
        if (! $cita) {
            flash('Cita no encontrada.', 'error');

            return redirect()->route('citas.agenda');
        }
        if ($this->citaAjena($cita)) {
            abort(403, 'Esa cita es de otro profesional.');
        }

        // Sin fichaje de entrada no se atiende: la asistencia dejaría de
        // reflejar quién estuvo de verdad en el salón, y la comisión se le
        // cargaría igual a quien no trabajó.
        $fichaje = $this->estadoFichaje($cita);
        if (! $fichaje['ok']) {
            if ($fichaje['futura']) {
                // No falta fichar: falta que llegue el día. Mandarla a fichar
                // era mandarla a algo que Asistencia rechaza.
                flash('Esa cita es del ' . fecha($fichaje['dia'], 'd/m/Y')
                    . ', todavía no llegó ese día: recién se le puede registrar la atención cuando se atienda.', 'error');

                return $volver;
            }

            $quien = (string) DB::scalar(
                "SELECT CONCAT(pe.nombre,' ',pe.apellido) FROM usuario u
                   JOIN persona pe ON pe.id_persona = u.id_persona WHERE u.id_usuario = ?", [(int) $cita->id_usuario]);
            flash($quien . ' todavía no marcó su entrada del ' . fecha($fichaje['dia'], 'd/m/Y')
                . ($fichaje['turno']
                    ? '. Marcá la entrada con el botón de arriba y volvé a guardar.'
                    : '. Corregí la asistencia de ese día en Seguridad → Asistencia y volvé a intentarlo.'), 'error');

            return $volver;
        }

        if ((int) $cita->id_estado_cita === 3) {
            flash('Esa cita está cancelada: no se le puede registrar atención.', 'error');

            return redirect()->route('citas.agenda');
        }
        if (DB::scalar('SELECT COUNT(*) FROM factura WHERE id_cita = ? AND id_estado_factura = 1', [$idCita])) {
            flash('Esa cita ya fue facturada: no se le pueden agregar más servicios ni productos.', 'warning');

            return redirect()->route('citas.agenda');
        }
        if (! $realizados) {
            flash('Marcá al menos un servicio realizado.', 'error');

            return $volver;
        }

        // Solo servicios activos que existan de verdad
        $in = implode(',', array_fill(0, count($realizados), '?'));
        $validos = array_map(fn ($r) => (int) $r->id_servicio,
            DB::select("SELECT id_servicio FROM servicio WHERE activo = 1 AND id_servicio IN ($in)", $realizados));
        if (! $validos) {
            flash('Los servicios elegidos no son válidos.', 'error');

            return $volver;
        }

        try {
            $resumen = DB::transaction(function () use ($idCita, $cita, $validos, $prodIds, $prodCant, $prodServ, $obs) {
                $idsSR = [];
                $srPorServicio = [];
                $agregados = 0;

                foreach ($validos as $sid) {
                    // Un servicio agregado durante la atención se suma también a
                    // la cita, así la factura y el historial quedan coherentes.
                    //
                    // Se pregunta ANTES de insertar: `DB::insert()` devuelve si
                    // la consulta corrió, no si escribió una fila, así que con
                    // INSERT IGNORE daba `true` aunque el servicio ya estuviera
                    // en la cita y el aviso terminaba diciendo «se agregaron N
                    // servicios que no estaban» cada vez.
                    $yaEnCita = (bool) DB::scalar(
                        'SELECT COUNT(*) FROM cita_servicio WHERE id_cita = ? AND id_servicio = ?', [$idCita, $sid]
                    );
                    if (! $yaEnCita) {
                        DB::insert(
                            'INSERT IGNORE INTO cita_servicio (id_cita, id_servicio) VALUES (?,?)', [$idCita, $sid]
                        );
                        $agregados++;
                    }

                    $ya = DB::scalar(
                        'SELECT id_servicio_realizado FROM servicio_realizado
                          WHERE id_cita = ? AND id_servicio = ? LIMIT 1', [$idCita, $sid]
                    );
                    if ($ya) {
                        $idsSR[] = (int) $ya;
                        $srPorServicio[$sid] = (int) $ya;

                        continue;
                    }

                    // **Quien hizo el servicio es quien tenía el servicio, no
                    // el de la cita.** El reparto entre profesionales existe
                    // desde la 5.3.0 y se quedaba en `cita_servicio`: acá se
                    // escribía siempre `$cita->id_usuario`, así que la manicura
                    // que hizo Lucía quedaba a nombre de Marta. Y como
                    // `fn_comision_servicio` sale de `servicio_realizado`, **la
                    // comisión se le pagaba a quien no trabajó**, y las columnas
                    // «Generado» y «Comisión» del informe del equipo atribuían
                    // mal el trabajo.
                    //
                    // Sin reparto, `cita_servicio.id_usuario` es NULL y sigue
                    // valiendo el de la cita, que es el caso de siempre.
                    $deQuien = DB::scalar(
                        'SELECT id_usuario FROM cita_servicio
                          WHERE id_cita = ? AND id_servicio = ? AND id_usuario IS NOT NULL LIMIT 1',
                        [$idCita, $sid]
                    );

                    DB::insert(
                        'INSERT INTO servicio_realizado (id_cita,id_servicio,id_usuario,observaciones) VALUES (?,?,?,?)',
                        [$idCita, $sid, (int) ($deQuien ?: $cita->id_usuario), $obs]
                    );
                    $nuevo = (int) DB::getPdo()->lastInsertId();
                    $idsSR[] = $nuevo;
                    $srPorServicio[$sid] = $nuevo;
                }

                // Los servicios que se agendaron pero NO se hicieron tienen que
                // salir de la cita: `sp_emitir_factura` arma el detalle desde
                // `cita_servicio`, así que si queda uno que no se realizó, el
                // cliente lo termina pagando. Solo se quitan los que no tienen
                // atención registrada.
                $enCita = array_map(fn ($r) => (int) $r->id_servicio,
                    DB::select('SELECT id_servicio FROM cita_servicio WHERE id_cita = ?', [$idCita]));
                $conAtencion = array_map(fn ($r) => (int) $r->id_servicio,
                    DB::select('SELECT DISTINCT id_servicio FROM servicio_realizado WHERE id_cita = ?', [$idCita]));

                $quitados = 0;
                foreach (array_diff($enCita, $validos, $conAtencion) as $sid) {
                    $quitados += DB::delete('DELETE FROM cita_servicio WHERE id_cita = ? AND id_servicio = ?', [$idCita, $sid]);
                }

                DB::update('UPDATE cita SET id_estado_cita = 4 WHERE id_cita = ?', [$idCita]);

                return ['servicios' => count($idsSR), 'agregados' => $agregados,
                        'quitados' => $quitados,
                        'sr' => $srPorServicio, 'primerSR' => $idsSR[0] ?? 0];
            });

            // **El consumo va DESPUÉS y por separado, y ése es el arreglo de
            // IN-02.** Antes iba todo en la misma transacción, así que un
            // producto sin stock abortaba también los servicios que no tenían
            // nada que ver: **69 de 204 atenciones (34 %) murieron así**, y la
            // cita quedaba sin cerrar, sin poder facturarse, para terminar
            // Atrasada o Ausente. Lo que ya se hizo no se puede perder porque
            // falte un frasco.
            //
            // Cada línea se intenta sola: las que entran descuentan, y las que
            // no se informan por su nombre para que alguien las cargue después.
            $consumo = $this->descontarConsumo($idCita, (int) $cita->id_usuario,
                $prodIds, $prodCant, $prodServ, $resumen['sr'], (int) $resumen['primerSR']);

            Auditoria::registrar('ATENCION', 'Citas', 'servicio_realizado', $idCita,
                $resumen['servicios'] . ' servicio(s), ' . $consumo['ok'] . ' producto(s) consumido(s)'
                . ($consumo['fallidos'] ? ' — ' . count($consumo['fallidos']) . ' sin descontar' : ''));

            flash('Atención registrada: ' . $resumen['servicios'] . ' servicio(s).'
                . ($resumen['agregados'] ? " Se agregaron {$resumen['agregados']} servicio(s) que no estaban en la cita original." : '')
                . ($resumen['quitados'] ? " Se quitaron {$resumen['quitados']} servicio(s) agendado(s) que no se realizaron: no se van a facturar." : '')
                . ($consumo['ok'] ? ' El stock de los productos usados fue descontado.' : ''));

            // El aviso del consumo va aparte y en amarillo: la atención quedó
            // registrada —eso es lo importante— pero el inventario no refleja
            // lo que se usó, y alguien lo tiene que acomodar.
            if ($consumo['fallidos']) {
                flash('Lo que sí quedó pendiente es el descuento de stock de '
                    . implode('; ', $consumo['fallidos'])
                    . '. La atención está registrada igual'
                    . (Permisos::puede('inventario.stock')
                        ? ': ajustá el stock desde Inventario → Stock cuando puedas.'
                        : ': avisale a quien maneja el inventario para que lo ajuste.'), 'warning');
            }
        } catch (QueryException $ex) {
            // Acá sólo llegan los errores de **los servicios**: desde IN-02 el
            // consumo de productos se descuenta aparte, línea por línea, y sus
            // fallas se informan sin tumbar nada (ver `descontarConsumo`).
            //
            // OJO CON EL ORDEN: este catch va ANTES que cualquiera de
            // `RuntimeException`. `QueryException` hereda de `PDOException`,
            // que hereda de `RuntimeException`, así que al revés el de abajo se
            // come todos los errores de la base y los muestra con un mensaje
            // que no tiene nada que ver.
            //
            // Lo que no se supo traducir se registra: «No se pudo registrar la
            // atención» a secas no le dice nada a nadie, y sin esto en el log
            // no queda rastro de qué pasó. Ya costó una vuelta entera.
            $amable = Bd::traducir($ex, [
                'habilitado' => 'El profesional no está habilitado para alguno de esos servicios.',
            ], '');
            if ($amable === '') {
                Log::error('Atención cita ' . $idCita . ': ' . $ex->getMessage());
                $amable = 'No se pudo registrar la atención. El detalle quedó en el registro del sistema.';
            }
            flash($amable, 'error');

            return $volver;
        } catch (Throwable $ex) {
            Log::error('Atención cita ' . $idCita . ' (' . get_class($ex) . '): ' . $ex->getMessage()
                . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
            flash('No se pudo registrar la atención. El detalle quedó en el registro del sistema.', 'error');

            return $volver;
        }

        return redirect()->route('citas.agenda', ['dia' => (string) $request->input('dia', date('Y-m-d'))]);
    }

    /**
     * Descuenta el consumo de productos de una atención ya registrada.
     *
     * **Cada línea va en su propia transacción, a propósito** (IN-02). El
     * consumo depende del stock, que es de otra persona y de otro momento;
     * los servicios dependen sólo de lo que se hizo. Atarlos hacía que un
     * frasco vacío borrara el trabajo de la tarde: 69 de 204 atenciones.
     *
     * Devuelve cuántas líneas entraron y la lista de las que no, ya escrita
     * para mostrarle a la persona («Shampoo x 1 L: no hay stock suficiente»).
     *
     * @return array{ok:int, fallidos:list<string>}
     */
    private function descontarConsumo(int $idCita, int $idUsuario, array $prodIds,
        array $prodCant, array $prodServ, array $srPorServicio, int $primerSR): array
    {
        // Los productos que se usan de a poco se cargan en su unidad de consumo
        // (30 ml) y hay que traducirlos a la de stock (0,03 frascos) ANTES de
        // guardar: `producto_utilizado.cantidad` y el disparador que descuenta
        // el inventario trabajan siempre en unidades de stock.
        $ficha = [];
        foreach (array_unique(array_map('intval', $prodIds)) as $pid) {
            if ($pid <= 0) {
                continue;
            }
            $fila = DB::selectOne(
                'SELECT nombre, unidad_medida, contenido, unidad_consumo FROM producto WHERE id_producto = ?', [$pid]
            );
            if ($fila) {
                $ficha[$pid] = (array) $fila;
            }
        }

        $ok = 0;
        $fallidos = [];

        foreach ($prodIds as $i => $pid) {
            $pid = (int) $pid;
            $cargado = num($prodCant[$i] ?? 0);
            if ($pid <= 0 || $cargado <= 0) {
                continue;
            }
            $nombre = $ficha[$pid]['nombre'] ?? ('producto #' . $pid);

            $c = isset($ficha[$pid]) ? consumo_a_stock($ficha[$pid], $cargado) : $cargado;
            if ($c <= 0) {
                // 1 ml de un bidón de 5.000 puede redondear a cero: no
                // descuenta nada y la fila no aporta información.
                $fallidos[] = $nombre . ' (la cantidad es tan chica que no llega a descontar nada)';

                continue;
            }

            $servElegido = (int) ($prodServ[$i] ?? 0);
            if ($servElegido > 0 && ! isset($srPorServicio[$servElegido])) {
                $fallidos[] = $nombre . ' (estaba marcado en un servicio que no quedó como realizado)';

                continue;
            }
            $sr = $servElegido > 0 ? $srPorServicio[$servElegido] : $primerSR;
            if (! $sr) {
                $fallidos[] = $nombre . ' (no hay ningún servicio al que cargarlo)';

                continue;
            }

            try {
                DB::transaction(function () use ($sr, $pid, $c, $idUsuario) {
                    // El disparador descuenta el stock solo al INSERT. Si la
                    // fila ya existía (mismo producto y mismo servicio), un
                    // UPDATE sumaría la cantidad sin descontar nada, así que el
                    // movimiento se registra a mano.
                    $yaPU = DB::selectOne(
                        'SELECT id_producto_utilizado FROM producto_utilizado
                          WHERE id_servicio_realizado = ? AND id_producto = ? LIMIT 1', [$sr, $pid]
                    );
                    if ($yaPU) {
                        DB::update('UPDATE producto_utilizado SET cantidad = cantidad + ? WHERE id_producto_utilizado = ?',
                            [$c, (int) $yaPU->id_producto_utilizado]);
                        // Precio en NULL, igual que el movimiento que genera el
                        // disparador: así las dos filas se ven iguales en el libro.
                        Bd::procedimiento('sp_registrar_movimiento_inventario', [
                            $pid, Sucursales::activa() ?: 1, $idUsuario, 2, $c, null,
                            'SR#' . $sr, 'Consumo adicional durante el servicio',
                        ]);
                    } else {
                        DB::insert('INSERT INTO producto_utilizado (id_servicio_realizado,id_producto,cantidad) VALUES (?,?,?)',
                            [$sr, $pid, $c]);
                    }
                });
                $ok++;
            } catch (QueryException $ex) {
                // **«No habilitado en esa sucursal» no es lo mismo que «sin
                // stock», y decirlo así mandaba a comprar lo que ya hay.** El
                // candado se mudó a `producto_sucursal` en la 7.33.0: el
                // producto existe y puede estar lleno en otro local, lo que
                // falta es traerlo a éste. Sin nombrar ese camino, quien
                // atiende no tiene forma de saber qué hacer.
                $fallidos[] = $nombre . ': ' . Bd::traducir($ex, [
                    'habilitado en esa sucursal' => 'no se maneja en esta sucursal — traelo desde '
                        . 'Inventario → Productos, con el filtro «Sólo en otras sucursales» y el botón «Traer acá»',
                    'stock' => 'no hay stock suficiente',
                ], 'no se pudo descontar (el detalle quedó registrado)');

                if (! str_contains($ex->getMessage(), 'stock')
                    && ! str_contains($ex->getMessage(), 'habilitado en esa sucursal')) {
                    Log::error('Consumo de la cita ' . $idCita . ', producto ' . $pid . ': ' . $ex->getMessage());
                }
            } catch (Throwable $ex) {
                $fallidos[] = $nombre . ': no se pudo descontar (el detalle quedó registrado)';
                Log::error('Consumo de la cita ' . $idCita . ', producto ' . $pid . ': ' . $ex->getMessage());
            }
        }

        return ['ok' => $ok, 'fallidos' => $fallidos];
    }

    /**
     * La clienta pidió algo desde el portal y el profesional ya lo resolvió
     * (lo agregó, o le explicó que no se puede).
     */
    public function pedidoVisto(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_pedido', 0);
        $p = DB::selectOne(
            'SELECT cp.id_pedido, cp.id_cita, c.id_usuario
               FROM cita_pedido cp JOIN cita c ON c.id_cita = cp.id_cita
              WHERE cp.id_pedido = ?', [$id]
        );
        if (! $p) {
            flash('Ese pedido no existe.', 'error');

            return redirect()->route('citas.agenda');
        }
        if ($this->citaAjena($p)) {
            abort(403, 'Esa cita es de otro profesional.');
        }

        DB::update('UPDATE cita_pedido SET atendido = 1 WHERE id_pedido = ?', [$id]);
        flash('Pedido marcado como resuelto.');

        return redirect()->route('citas.atender', ['id' => (int) $p->id_cita]);
    }

    // -----------------------------------------------------------------

    /** ¿El profesional fichó su entrada ese día? */
    /**
     * ¿Se puede registrar la atención de esta cita, y si no, por qué?
     *
     * Son DOS cosas distintas y antes se contestaban con el mismo mensaje:
     *
     *  · La cita es de un día que todavía no llegó. Ahí no falta fichar —
     *    faltan días—. Decir «fichá la entrada» mandaba a Seguridad →
     *    Asistencia, que contesta «no se puede registrar asistencia de un día
     *    que todavía no llegó»: la persona daba vueltas sin salida. Con el mes
     *    simulado pasaba en 83 de las 172 citas.
     *  · La cita es de hoy y falta el fichaje de verdad. Eso sí se resuelve, y
     *    desde esta misma pantalla.
     *
     * Devuelve `turno` cuando el fichaje se puede hacer acá, para dibujar el
     * botón; si viene null, hay que ir a Asistencia (por ejemplo, una cita de
     * ayer, que ya no es fichar sino corregir la planilla).
     */
    private function estadoFichaje(object $cita): array
    {
        $dia = substr((string) $cita->fecha_hora, 0, 10);
        $hoy = ahora_bd('Y-m-d');
        $idU = (int) $cita->id_usuario;

        if (! $this->usaTurnos($idU) || $this->ficho($idU, $dia)) {
            return ['ok' => true];
        }

        if ($dia > $hoy) {
            return ['ok' => false, 'futura' => true, 'dia' => $dia, 'turno' => null];
        }

        // Sólo se ficha el día en curso. Un día pasado se corrige desde
        // Asistencia, que es lo que ya hace `asistenciaMarcar`.
        $turno = $dia === $hoy
            ? DB::selectOne(
                'SELECT t.id_turno, t.nombre, t.hora_inicio, t.hora_fin
                   FROM usuario_turno ut
                   JOIN turno_laboral t ON t.id_turno = ut.id_turno AND t.activo = 1
                   JOIN turno_dia td    ON td.id_turno = t.id_turno AND td.dia_semana = ?
                  WHERE ut.id_usuario = ? ORDER BY t.hora_inicio LIMIT 1',
                [(int) date('N', strtotime($dia)), $idU]
            )
            : null;

        return ['ok' => false, 'futura' => false, 'dia' => $dia, 'turno' => $turno];
    }

    private function ficho(int $idUsuario, string $fecha): bool
    {
        return (bool) DB::scalar(
            'SELECT COUNT(*) FROM asistencia
              WHERE id_usuario = ? AND fecha = ? AND hora_entrada IS NOT NULL', [$idUsuario, $fecha]
        );
    }

    /**
     * ¿Tiene turno asignado? Si no tiene ninguno, el salón todavía no usa la
     * agenda de turnos y no se le puede exigir fichaje (mismo criterio
     * permisivo que fn_verificar_disponibilidad).
     */
    private function usaTurnos(int $idUsuario): bool
    {
        return (bool) DB::scalar(
            'SELECT COUNT(*) FROM usuario_turno ut
               JOIN turno_laboral t ON t.id_turno = ut.id_turno AND t.activo = 1
              WHERE ut.id_usuario = ?', [$idUsuario]
        );
    }

    /**
     * ¿Este rol ve la agenda de todo el salón, o solo la propia?
     *
     * El Profesional atiende: le sirve su columna, no la de sus compañeras.
     * Quien coordina el salón necesita verlo todo, y eso se detecta por el
     * permiso de turnos —quien organiza los turnos organiza la agenda—, no por
     * una lista fija de id de rol.
     */
    /** La regla vive en Permisos: la comparten la agenda y el panel. */
    private function veTodaLaAgenda(): bool
    {
        return Permisos::veTodaLaAgenda();
    }

    /**
     * Limitar la agenda a las citas propias no alcanza si después se puede
     * entrar a la de otro escribiendo el id en la URL.
     */
    private function citaAjena(?object $cita): bool
    {
        if (! $cita) {
            return false;
        }

        return ! $this->veTodaLaAgenda() && (int) $cita->id_usuario !== (int) session('uid');
    }

    private function esPersonalActivo(int $idUsuario): bool
    {
        return (bool) DB::scalar(
            'SELECT COUNT(*) FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.id_usuario = ? AND u.activo = 1 AND r.es_personal = 1', [$idUsuario]
        );
    }
}
