<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Agenda;
use App\Servicios\Auditoria;
use App\Servicios\Bd;
use App\Servicios\Borrador;
use App\Servicios\Caja;
use App\Servicios\Permisos;
use App\Servicios\Persona;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * Citas y agenda.
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

        $rows = DB::select(
            "SELECT v.*, fn_cita_sena(v.id_cita) AS sena
               FROM vw_agenda_citas v
               JOIN cita c ON c.id_cita = v.id_cita
              WHERE DATE(v.fecha_hora) = :d $soloMias
              ORDER BY v.fecha_hora", $par
        );

        // La seña mueve plata: el botón solo aparece si el rol maneja caja
        $puedeCobrar = Permisos::puede('facturacion.cobros');

        return view('citas.agenda', [
            'rows' => $rows,
            'dia' => $dia,
            'verTodo' => $verTodo,
            'puedeCobrar' => $puedeCobrar,
            'metodos' => $puedeCobrar
                ? DB::select('SELECT id_metodo_pago, nombre FROM metodo_pago WHERE activo = 1 ORDER BY id_metodo_pago')
                : [],
            'caja' => $puedeCobrar ? Caja::abierta() : null,
        ]);
    }

    public function form(Request $request): View
    {
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

            return redirect()->route('citas.form')->withInput();
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

            return redirect()->route('citas.form')->withInput();
        }

        // Sin profesional de preferencia se asigna el primero libre por el
        // bloque que le va a tocar (los servicios que no se repartieron a otro).
        if (! $idUsuario) {
            $delPrincipal = Agenda::duracion(array_keys(array_filter($asignacion, fn ($p) => $p === 0)));
            $idUsuario = Agenda::profesionalLibre($fecha, $delPrincipal ?: $dur) ?? 0;
            if (! $idUsuario) {
                flash('A esa hora no queda ningún profesional libre. Elegí otro horario.', 'warning');

                return redirect()->route('citas.form', ['cliente' => $idCliente])->withInput();
            }
        }

        foreach (array_unique(array_values($asignacion)) as $idAyuda) {
            if ($idAyuda > 0 && ! $this->esPersonalActivo((int) $idAyuda)) {
                flash('Uno de los profesionales elegidos ya no está activo.', 'error');

                return redirect()->route('citas.form', ['cliente' => $idCliente])->withInput();
            }
        }

        // Exclusividad + hueco de CADA profesional. Se vuelve a preguntar acá
        // porque entre que se dibujó la pantalla y se apretó el botón, otro
        // pudo tomar el horario.
        if ($problema = Agenda::validarReparto($asignacion, $idUsuario, $fecha)) {
            flash($problema, 'warning');

            return redirect()->route('citas.form', ['cliente' => $idCliente])->withInput();
        }

        // La cita dura el bloque más largo: los profesionales trabajan en
        // paralelo, no uno detrás del otro.
        $dur = Agenda::duracionReparto($asignacion, $idUsuario) ?: $dur;

        try {
            $idCita = Agenda::agendar($idCliente, $idUsuario, $fecha, $dur, $obs, $asignacion);
            $equipo = count(array_filter(array_values($asignacion))) > 0;
            Auditoria::registrar('ALTA', 'Citas', 'cita', $idCita,
                'Cita agendada para ' . $fecha . ($equipo ? ' con varios profesionales' : ''));
            flash('Cita agendada para el ' . fecha($fecha) . '.');
        } catch (Throwable $ex) {
            // Si el procedimiento dice «no disponible» acá, es porque otra
            // persona se quedó con el hueco entre nuestra verificación y el
            // candado: son milisegundos, pero pasa.
            $msg = $ex->getMessage();
            flash(str_contains($msg, 'disponible')
                ? Agenda::motivoHuecoPerdido($idUsuario, $fecha, $dur)
                : (str_contains($msg, 'habilitado')
                    ? 'El profesional no está habilitado para alguno de esos servicios.'
                    : 'No se pudo agendar la cita.'), 'error');

            return redirect()->route('citas.form', ['cliente' => $idCliente])->withInput();
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

    public function ausencias(): View
    {
        return view('citas.ausencias', [
            'rows' => DB::select(
                "SELECT a.*, ta.nombre AS tipo,
                        COALESCE(CONCAT(pe_u.nombre,' ',pe_u.apellido),'Todo el salón') AS quien
                   FROM ausencia_agenda a
                   JOIN tipo_ausencia ta ON ta.id_tipo_ausencia = a.id_tipo_ausencia
                   LEFT JOIN usuario u   ON u.id_usuario = a.id_usuario
                   LEFT JOIN persona pe_u ON pe_u.id_persona = u.id_persona
                  WHERE a.activo = 1 ORDER BY a.fecha_inicio DESC LIMIT 100"
            ),
            'profs' => Agenda::profesionales(),
            'tipos' => DB::select('SELECT * FROM tipo_ausencia ORDER BY nombre'),
        ]);
    }

    public function ausenciaGuardar(Request $request): RedirectResponse
    {
        $d = [
            'id_usuario' => ((int) $request->input('id_usuario', 0)) ?: null,
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
        } elseif (! strtotime($d['fecha_inicio']) || ! strtotime($d['fecha_fin'])) {
            $error = 'Las fechas no son válidas.';
        } elseif (strtotime($d['fecha_fin']) <= strtotime($d['fecha_inicio'])) {
            $error = 'La fecha de fin tiene que ser posterior a la de inicio.';
        }
        if ($error) {
            flash($error, 'error');

            return $volver->withInput();
        }

        // Avisar si el bloqueo pisa citas ya agendadas
        $choques = (int) DB::scalar(
            'SELECT COUNT(*) FROM cita c JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
              WHERE ec.bloquea_agenda = 1
                AND (:u1 IS NULL OR c.id_usuario = :u2)
                AND c.fecha_hora < :fin AND c.fecha_hora >= :ini',
            ['u1' => $d['id_usuario'], 'u2' => $d['id_usuario'],
             'ini' => $d['fecha_inicio'], 'fin' => $d['fecha_fin']]
        );

        try {
            DB::insert(
                'INSERT INTO ausencia_agenda (id_usuario,id_tipo_ausencia,fecha_inicio,fecha_fin,motivo)
                 VALUES (:id_usuario,:id_tipo_ausencia,:fecha_inicio,:fecha_fin,:motivo)', $d
            );
            Auditoria::registrar('ALTA', 'Citas', 'ausencia_agenda', (int) DB::getPdo()->lastInsertId(),
                'Excepción ' . $d['fecha_inicio'] . ' a ' . $d['fecha_fin']);

            flash('Excepción registrada.'
                . ($choques ? " Hay $choques cita(s) agendada(s) dentro de ese rango." : ''),
                $choques ? 'warning' : 'success');

            // TODO: avisar por correo a los clientes con cita en ese rango.
            // Se conecta al portar notificaciones.php (tarea de notificaciones).
        } catch (Throwable) {
            flash('No se pudo registrar la excepción.', 'error');
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
            'productos' => DB::select(
                'SELECT p.id_producto, p.nombre, p.unidad_medida, p.contenido, p.unidad_consumo,
                        fn_producto_stock(p.id_producto) AS stock
                   FROM producto p WHERE p.activo = 1 ORDER BY p.nombre'
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
        $diaCita = substr((string) $cita->fecha_hora, 0, 10);
        if ($this->usaTurnos((int) $cita->id_usuario) && ! $this->ficho((int) $cita->id_usuario, $diaCita)) {
            $quien = (string) DB::scalar(
                "SELECT CONCAT(pe.nombre,' ',pe.apellido) FROM usuario u
                   JOIN persona pe ON pe.id_persona = u.id_persona WHERE u.id_usuario = ?", [(int) $cita->id_usuario]);
            flash($quien . ' todavía no marcó su entrada del ' . fecha($diaCita, 'd/m/Y')
                . '. Fichá la entrada en Seguridad → Asistencia y volvé a intentarlo.', 'error');

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
                    $nuevoEnCita = DB::insert(
                        'INSERT IGNORE INTO cita_servicio (id_cita, id_servicio) VALUES (?,?)', [$idCita, $sid]
                    );
                    if ($nuevoEnCita) {
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

                    DB::insert(
                        'INSERT INTO servicio_realizado (id_cita,id_servicio,id_usuario,observaciones) VALUES (?,?,?,?)',
                        [$idCita, $sid, (int) $cita->id_usuario, $obs]
                    );
                    $nuevo = (int) DB::getPdo()->lastInsertId();
                    $idsSR[] = $nuevo;
                    $srPorServicio[$sid] = $nuevo;
                }

                // Los productos que se usan de a poco se cargan en su unidad de
                // consumo (30 ml) y hay que traducirlos a la de stock (0,03
                // frascos) ANTES de guardar: `producto_utilizado.cantidad` y el
                // disparador que descuenta el inventario trabajan siempre en
                // unidades de stock.
                $fraccion = [];
                foreach (array_unique(array_map('intval', $prodIds)) as $pid) {
                    if ($pid <= 0) {
                        continue;
                    }
                    $fila = DB::selectOne(
                        'SELECT unidad_medida, contenido, unidad_consumo FROM producto WHERE id_producto = ?', [$pid]
                    );
                    if ($fila) {
                        $fraccion[$pid] = (array) $fila;
                    }
                }

                $nProd = 0;
                foreach ($prodIds as $i => $pid) {
                    $pid = (int) $pid;
                    $cargado = num($prodCant[$i] ?? 0);
                    if ($pid <= 0 || $cargado <= 0) {
                        continue;
                    }

                    $c = isset($fraccion[$pid]) ? consumo_a_stock($fraccion[$pid], $cargado) : $cargado;
                    if ($c <= 0) {
                        // 1 ml de un bidón de 5.000 puede redondear a cero: no
                        // descuenta nada y la fila no aporta información.
                        throw new RuntimeException('cantidad_muy_chica');
                    }

                    $servElegido = (int) ($prodServ[$i] ?? 0);
                    if ($servElegido > 0 && ! isset($srPorServicio[$servElegido])) {
                        throw new RuntimeException('servicio_no_realizado');
                    }
                    $sr = $servElegido > 0 ? $srPorServicio[$servElegido] : $idsSR[0];

                    // El disparador descuenta el stock solo al INSERT. Si la fila
                    // ya existía (mismo producto y mismo servicio), un UPDATE
                    // sumaría la cantidad sin descontar nada, así que el
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
                            $pid, (int) $cita->id_usuario, 2, $c, null,
                            'SR#' . $sr, 'Consumo adicional durante el servicio',
                        ]);
                    } else {
                        DB::insert('INSERT INTO producto_utilizado (id_servicio_realizado,id_producto,cantidad) VALUES (?,?,?)',
                            [$sr, $pid, $c]);
                    }
                    $nProd++;
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
                        'quitados' => $quitados, 'productos' => $nProd];
            });

            Auditoria::registrar('ATENCION', 'Citas', 'servicio_realizado', $idCita,
                $resumen['servicios'] . ' servicio(s), ' . $resumen['productos'] . ' producto(s) consumido(s)');

            flash('Atención registrada: ' . $resumen['servicios'] . ' servicio(s).'
                . ($resumen['agregados'] ? " Se agregaron {$resumen['agregados']} servicio(s) que no estaban en la cita original." : '')
                . ($resumen['quitados'] ? " Se quitaron {$resumen['quitados']} servicio(s) agendado(s) que no se realizaron: no se van a facturar." : '')
                . ($resumen['productos'] ? ' El stock de los productos usados fue descontado.' : ''));
        } catch (RuntimeException $ex) {
            flash($ex->getMessage() === 'cantidad_muy_chica'
                ? 'Una de las cantidades es tan chica que no llega a descontar nada del stock. Revisala.'
                : 'Marcaste un producto en un servicio que no quedó como realizado. '
                  . 'Marcá ese servicio o elegí otro para el producto.', 'error');

            return $volver;
        } catch (Throwable $ex) {
            $msg = $ex->getMessage();
            flash(str_contains($msg, 'stock')
                ? 'No hay stock suficiente de alguno de los productos cargados.'
                : (str_contains($msg, 'habilitado')
                    ? 'El profesional no está habilitado para alguno de esos servicios.'
                    : 'No se pudo registrar la atención.'), 'error');

            return $volver;
        }

        return redirect()->route('citas.agenda', ['dia' => (string) $request->input('dia', date('Y-m-d'))]);
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
    private function veTodaLaAgenda(): bool
    {
        return Permisos::esAdmin() || Permisos::puede('seguridad.turnos');
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
