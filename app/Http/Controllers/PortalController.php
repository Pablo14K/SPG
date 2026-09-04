<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Acompanantes;
use App\Servicios\Agenda;
use Illuminate\Database\QueryException;
use App\Servicios\Auditoria;
use App\Servicios\Bd;
use App\Servicios\Calendario;
use App\Servicios\Canje;
use App\Servicios\Sena;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;
use Dompdf\Dompdf;

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
            'SELECT v.*, (ec.nombre = \'En proceso\') AS en_curso, ec.nombre AS estado_nombre
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
        $idCliente = $this->cliente();

        $servicios = array_map('intval', (array) $request->query('servicios', []));
        $idUsuario = ((int) $request->query('id_usuario', 0)) ?: null;
        $duracion = Agenda::duracion($servicios);

        if ($duracion <= 0) {
            return response()->json(['ok' => false, 'motivo' => 'Elegí primero el o los servicios.']);
        }

        // La sucursal que eligio: el turno es del local, asi que sin esto la
        // pantalla ofreceria los horarios de la sede equivocada.
        // **La sucursal viene de la URL, así que se comprueba.** Un id que no
        // existe —o negativo— dejaba al filtro sin ningún turno que mirar, el
        // salón parecía no usarlos y se ofrecía la jornada por defecto: días
        // que el guardado después rechaza. Sin sucursal válida no hay agenda
        // que mostrar, porque el turno es del local.
        $suc = (int) $request->query('sucursal', 0);
        if ($suc !== 0 && ! DB::scalar(
            'SELECT 1 FROM sucursal WHERE id_sucursal = ? AND activo = 1 LIMIT 1', [$suc])) {
            return response()->json(['ok' => false, 'motivo' => 'Elegí primero el local.']);
        }
        $suc = $suc ?: null;

        // **El turno elegido acota lo que se ofrece.** Con un turno puesto
        // —a mano con los botones, o deducido del profesional que la clienta
        // eligió— los días y las horas se recortan a esa franja. Es lo que hace
        // imposible el error de pedir a alguien de la mañana y a alguien de la
        // tarde: no se puede elegir lo que no se muestra.
        $turno = Agenda::turnoPorId((int) $request->query('turno', 0), $suc);

        $fecha = (string) $request->query('fecha', '');
        if ($fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return response()->json(['ok' => true, 'duracion' => $duracion,
                'horas' => Agenda::soloDelTurno(
                    Agenda::slots($idUsuario, $fecha, $duracion, null, $suc, $servicios), $turno, $duracion)]);
        }

        return response()->json(['ok' => true, 'duracion' => $duracion,
            // **Los días en que ya tiene esos servicios no se ofrecen.** La
            // regla es de la 7.14.0 y la hacía cumplir el disparador al
            // guardar, o sea con la clienta habiendo elegido todo. Sacándolos
            // de la lista, el rechazo deja de poder ocurrir.
            'dias' => array_values(array_diff(
                Agenda::diasDelTurno(
                    Agenda::diasConCupo($idUsuario, date('Y-m-d'),
                                        (int) config('spg.agenda.dias_vista', 60), $duracion, $suc, $servicios),
                    $turno),
                Agenda::diasYaTomados($idCliente, $servicios)
            )),
            // Si el calendario sale vacío porque lo elegido no entra en ningún
            // turno, hay que decirlo: «probá con otro profesional» manda a
            // recorrer uno por uno algo que ninguno puede dar.
            'motivo' => Agenda::motivoSinCupo($duracion, $idUsuario, $suc, $servicios)]);
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
            // Los turnos del local, para los botones de «¿a qué hora?». Elegir
            // uno acota en silencio los combos y la agenda, y con eso el error
            // de pedir a alguien de la mañana y a alguien de la tarde deja de
            // poder ocurrir: no se puede elegir lo que no se ofrece.
            'turnos' => $elegida ? Agenda::turnosDe($elegida) : [],
            // **Quién hace CADA servicio, para que el combo no ofrezca a quien
            // no lo hace.** El selector listaba al equipo entero para
            // cualquier servicio: la clienta podía pedir una coloración con
            // quien sólo hace uñas, y el «no» llegaba el día de la cita.
            //
            // Vale el criterio permisivo de siempre: quien no tiene ninguno
            // cargado los hace todos, así que un salón que no administre esto
            // sigue viendo a todo el equipo en todos los servicios.
            'haceServicio' => $elegida ? (function () {
                $out = [];
                foreach (DB::select(
                    'SELECT ps.id_servicio, u.id_usuario FROM persona_servicio ps
                       JOIN usuario u ON u.id_persona = ps.id_persona AND u.activo = 1'
                ) as $r) {
                    $out[(int) $r->id_servicio][] = (int) $r->id_usuario;
                }

                return $out;
            })() : [],
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
                'SELECT s.id_servicio, s.nombre, s.precio, s.duracion_min, s.requiere_exclusividad,
                        s.descripcion, s.imagen, s.sena_porcentaje,
                        -- **Lo que le van a descontar a ELLA**, con su nivel y las
                        -- promociones vigentes. Va en la consulta y no por tarjeta:
                        -- con quince servicios serían quince consultas por carga.
                        fn_servicio_descuento_monto(s.id_servicio, ?) AS descuento
                   FROM servicio s
                  WHERE s.activo = 1
                    AND (EXISTS (SELECT 1 FROM servicio_sucursal ss
                                  WHERE ss.id_servicio = s.id_servicio AND ss.id_sucursal = ?)
                         OR NOT EXISTS (SELECT 1 FROM servicio_sucursal ss2
                                         WHERE ss2.id_servicio = s.id_servicio))
                  ORDER BY s.nombre', [$this->cliente(), $elegida]
            ) : [],
            // Lo que ya canjeó y todavía puede usar. **No cambia nada del
            // motor de la agenda**: el servicio canjeado ocupa el mismo tiempo
            // y lo tiene que hacer un profesional que lo haga, en un horario
            // libre. Lo único que cambia es que no se cobra.
            'canjes' => Canje::deCliente($this->cliente(), true),
        ]);
    }

    /**
     * El texto que escribió el `SIGNAL` de un disparador.
     *
     * MariaDB lo devuelve envuelto: `SQLSTATE[45000]: <<Unknown error>>: 1644 …`.
     * Lo que le sirve a la clienta es lo último, que está escrito para que lo
     * lea una persona.
     */
    private static function mensajeDeLaBase(string $bruto): string
    {
        $p = strrpos($bruto, ': ');

        return $p === false ? '' : trim(substr($bruto, $p + 2));
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

        // **Un rechazo devuelve el formulario con lo que ya estaba cargado.**
        // Sin `withInput()` la clienta que elegía dos profesionales de turnos
        // opuestos —o que perdía el horario por unos segundos— volvía a una
        // pantalla en blanco y tenía que marcar los servicios, el profesional y
        // el día otra vez. El rechazo es correcto; hacerla empezar de cero es
        // el castigo de más.
        $volver = redirect()->route('portal.reservar', ['sucursal' => $idSucursal])->withInput();

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
            // **Si la clienta eligió a alguien, la cita es de esa persona.**
            //
            // Este bloque asignaba «cualquiera que esté libre» sin mirar el
            // reparto, así que eligiendo profesional para su único servicio la
            // cita quedaba a nombre de OTRA: `cita_servicio.id_usuario` tenía
            // al elegido y `cita.id_usuario` a un tercero — y la agenda muestra
            // el de la cita. Desde afuera, el sistema le cambiaba el
            // profesional sin decir nada.
            //
            // `principalDelReparto()` devuelve quien más minutos pone, que es
            // el criterio con el que la cita tiene dueño desde la 5.3.0.
            $idUsuario = Agenda::principalDelReparto($asignacion);
        }

        if (! $idUsuario) {
            // Nadie elegido en ningún servicio: recién ahí decide el sistema.
            // **Se le pasan los servicios**: sin eso podía tocar alguien que no
            // los hace, y el rechazo llegaba después nombrando a una persona que
            // la clienta ni había elegido.
            $sinDuenio = array_keys(array_filter($asignacion, fn ($p) => $p === 0));
            $delPrincipal = Agenda::duracion($sinDuenio);
            $idUsuario = Agenda::profesionalLibre($fecha, $delPrincipal ?: $dur, $idSucursal, $sinDuenio) ?? 0;
            if (! $idUsuario) {
                flash('No quedó nadie libre a esa hora que haga todo lo que elegiste. '
                    . 'Probá con otro horario, o pedí una persona en particular para cada servicio.', 'warning');

                return $volver;
            }
        }

        // **Lo que quedó en «que me atienda cualquiera» se resuelve acá.** Un 0
        // quiere decir «lo hace el dueño de la cita», así que si el principal no
        // hace ese servicio hay que darle otra persona — no rechazar la reserva
        // por algo que la clienta dejó justamente a criterio del salón.
        $asignacion = Agenda::completarReparto($asignacion, $idUsuario, $fecha, $idSucursal);

        foreach (array_unique(array_values($asignacion)) as $idAyuda) {
            if ($idAyuda > 0 && ! $this->personalActivo((int) $idAyuda, $idSucursal)) {
                flash('Uno de los profesionales que elegiste ya no está disponible.', 'error');

                return $volver;
            }
        }

        // **El turno se vuelve a comprobar acá.** La pantalla esconde a quien
        // no trabaja en la franja elegida, pero esconder no es el control: el
        // `id_turno` viaja en el POST y se puede cambiar. Sin esto, la clienta
        // podría mandar exactamente la combinación que el filtro existe para
        // impedir — alguien de la mañana y alguien de la tarde.
        $idTurno = (int) $request->input('id_turno', 0);
        if ($idTurno > 0 && ($turno = Agenda::turnoPorId($idTurno, $idSucursal))) {
            $hora = substr($fecha, 11, 5);
            if ($hora < $turno->desde || $hora >= $turno->hasta) {
                flash('Ese horario no entra en el turno que elegiste (' . $turno->nombre . ', '
                    . $turno->desde . ' a ' . $turno->hasta . '). Elegí otro, o sacá el filtro de turno.', 'warning');

                return $volver;
            }
            foreach (array_unique(array_filter(array_values($asignacion))) as $idProf) {
                if (! Agenda::trabajaEnTurno((int) $idProf, $idTurno)) {
                    flash('Uno de los profesionales que elegiste no trabaja en ese turno. '
                        . 'Elegí a otra persona, o cambiá el turno.', 'warning');

                    return $volver;
                }
            }
        }

        if ($problema = Agenda::validarReparto($asignacion, $idUsuario, $fecha)) {
            flash($problema, 'warning');

            return $volver;
        }
        $dur = Agenda::duracionReparto($asignacion, $idUsuario) ?: $dur;

        // **La clienta tampoco puede pisarse a sí misma.** La agenda cuidaba
        // al profesional y nada impedía reservar dos servicios a la misma hora
        // con gente distinta: el día de la cita hay que estar en dos sillones.
        //
        // Reservar PARA OTRA PERSONA sí puede superponerse —son dos personas—
        // y es lo que la casilla del formulario declara.
        $paraOtro = (bool) $request->input('para_otra_persona', 0);
        if ($choque = Agenda::citaDelClienteSePisa($idc, $fecha, $dur, 0, $paraOtro)) {
            flash($choque, 'error');

            return $volver;
        }

        try {
            $idCita = Agenda::agendar($idc, $idUsuario, $fecha, $dur, $obs, $asignacion, $idSucursal ?: null);

            // Los canjes que eligió quedan atados a esta cita, y con eso el
            // servicio va **a cero** en el comprobante. Se comprueban contra
            // SU id de cliente, no contra el formulario: si no, cambiando un
            // campo oculto se gastaría el canje de otra persona.
            // Para quién es y cuántas van. `sp_agendar_cita` no los recibe:
            // son datos del pedido, no de la disponibilidad — el sillón se
            // ocupa lo mismo, y meterlos en el procedimiento obligaría a
            // cambiar su firma para algo que no decide nada.
            $personas = max(1, min(20, (int) $request->input('personas', 1)));
            $nombrePara = trim((string) $request->input('nombre_para', ''));
            if ($paraOtro && $nombrePara === '') {
                $paraOtro = false;   // sin nombre no es «para otra persona»
            }
            DB::update(
                'UPDATE cita SET para_otra_persona = ?, nombre_para = ?, personas = ? WHERE id_cita = ?',
                [$paraOtro ? 1 : 0, $paraOtro ? mb_substr($nombrePara, 0, 120) : null, $personas, $idCita]
            );

            // Quiénes vienen, no sólo cuántas: el salón necesita saber a quién
            // esperar. La primera no se guarda — es la clienta que reservó.
            Acompanantes::guardar($idCita,
                (array) $request->input('acomp_nombre', []),
                (array) $request->input('acomp_apellido', []),
                $personas);

            $usados = Canje::aplicarACita((array) $request->input('canjes', []), $idCita, $idc);

            // **Si los servicios piden seña, la reserva no termina acá.**
            // La seña es lo que garantiza el horario, así que dejar a la
            // clienta en «Mis citas» con un aviso suelto es pedirle que se
            // acuerde sola. El monto lo fija el salón (`servicio.sena_porcentaje`)
            // y lo calcula la base: hasta acá la clienta escribía el que
            // quisiera y el salón se lo confirmaba de palabra.
            $senaPide = (float) DB::scalar('SELECT fn_cita_sena_requerida(?)', [$idCita]);

            // **La reserva no queda confirmada hasta que la seña esté cobrada,
            // y el horario se guarda mientras tanto.**
            //
            // Las dos mitades importan. Si la cita no se creara hasta cobrar, la
            // clienta perdería el lugar mientras hace la transferencia; si el
            // lugar quedara reservado para siempre, un sillón se bloquea por
            // alguien que nunca pagó. Se le guarda por un plazo y se le dice
            // cuál es — `Notificaciones::cancelarSenasVencidas()` la suelta
            // después y le avisa, así que no desaparece en silencio.
            $horas = (int) config('spg.agenda.sena_horas', 24);

            $canje = $usados ? ' Usaste ' . $usados . ' canje(s): ese servicio no se te cobra.' : '';

            // **Las tres condiciones se dicen ACÁ, que es cuando se decide.**
            // Enterarse después de que el cambio de día era uno solo, o de que
            // la seña no vuelve si no viene, es enterarse cuando ya no se puede
            // hacer nada distinto. Van juntas y en el mismo aviso porque son
            // las tres caras del mismo trato: le guardamos el lugar y por eso
            // pedimos algo a cambio.
            // **Sin asteriscos.** El mensaje se dibuja con `{{ }}`, que escapa
            // y no interpreta Markdown, así que los `**` salían tal cual en la
            // pantalla de la clienta.
            //
            // Y va en renglones, no en un párrafo corrido: el modal los separa
            // en frases, porque seis líneas seguidas no se leen.
            $reglas = "\nEl día lo podés cambiar una sola vez, desde «Mis citas»."
                . "\nSi no venís, la seña no se devuelve: es lo que cubre el horario "
                . 'que nadie más pudo usar.';

            flash($senaPide > 0
                ? 'Te guardamos el horario, pero tu cita todavía no está confirmada.'
                    . "\nPara confirmarla hace falta la seña de " . money($senaPide) . '. '
                    . 'Registrá el comprobante de la transferencia desde «Mis citas»'
                    . ($horas > 0 ? ', dentro de las ' . $horas . ' horas' : '')
                    . '. Si no llegamos a confirmarla soltamos el lugar y te avisamos.'
                    . $reglas . ($canje ? "\n" . trim($canje) : '')
                : "¡Tu cita fue reservada!\nEl día lo podés cambiar una sola vez, "
                    . 'desde «Mis citas».' . ($canje ? "\n" . trim($canje) : ''),
                // Las tres condiciones hay que leerlas antes de irse de la
                // pantalla, así que es ventana y no franja.
                $senaPide > 0 ? 'modal' : 'success');

            if ($senaPide > 0) {
                return redirect()->route('portal.citas', ['sena' => $idCita]);
            }
        } catch (Throwable $ex) {
            $msg = $ex->getMessage();

            // Llegó hasta acá y perdió: otra persona tomó el hueco justo cuando
            // esta reserva pasaba por el candado del procedimiento.
            if (str_contains($msg, 'disponible')) {
                flash(Agenda::motivoHuecoPerdido($idUsuario, $fecha, $dur), 'error');

                return $volver;
            }

            // **Lo que la BASE ya explicó se muestra tal cual.**
            //
            // Los disparadores levantan mensajes escritos para que los lea una
            // persona —«Ya hay "Corte de dama" agendado para esa clienta ese
            // mismo día»— y este `catch` los tapaba todos con un «No se pudo
            // reservar la cita» que manda a probar otro horario, cuando el
            // horario no tenía nada que ver. Es exactamente el defecto que este
            // proyecto ya anota: un mensaje que no distingue manda a mirar el
            // lugar equivocado.
            //
            // `Bd::traducir()` devuelve el texto del `SIGNAL` cuando lo hay, y
            // el genérico sólo cuando de verdad no se sabe qué pasó.
            $detalle = $ex instanceof QueryException
                ? Bd::traducir($ex, [], '')
                : (str_contains($msg, 'SQLSTATE[45000]') ? self::mensajeDeLaBase($msg) : '');

            if ($detalle !== '') {
                flash($detalle, 'warning');

                return $volver;
            }

            // Recién acá el genérico, y con el detalle en el log: si nadie sabe
            // qué pasó, al menos que quede escrito para quien lo mantiene.
            Log::error('Reserva del portal: ' . $msg);
            flash('No se pudo reservar la cita. Probá con otro horario; si vuelve a pasar, '
                . 'escribinos y la agendamos nosotros.', 'error');

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

        // **Cuánto pide el salón por esa cita.** Se pregunta a la base, que es
        // la autoridad: la pantalla propone el número pero lo que vale es esto.
        $pide = $cita ? (float) DB::scalar('SELECT fn_cita_sena_requerida(?)', [$idCita]) : 0.0;

        $error = null;
        if (! $cita) {
            $error = 'Esa cita no es tuya.';
        } elseif (in_array((int) $cita->id_estado_cita, [3, 4, 6], true)) {
            $error = 'Esa cita ya está cerrada, no se le puede dejar una seña.';
        } elseif ($monto <= 0) {
            $error = 'Escribí cuánto vas a dejar de seña.';
        } elseif ($pide > 0 && $monto + 0.01 < $pide) {
            // **Menos de lo que se pide no reserva nada.** Registrar Gs. 10.000
            // sobre una seña de 210.000 deja la cita igual de sin confirmar,
            // pero con un aviso pendiente que alguien tiene que ir a rechazar a
            // mano — y la clienta creyendo que ya la aseguró.
            $error = 'La seña de esta cita es de ' . money($pide) . ' y estás registrando '
                . money($monto) . '. Registrá el total: con menos, el horario no queda confirmado.';
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

        // **El comprobante de la transferencia, si lo tiene.** Las citas se
        // reservan desde afuera del local, así que la seña se transfiere y no
        // hay nada físico que entregar: sin el comprobante, quien confirma en
        // el mostrador tiene que creerle o llamar al banco.
        //
        // **Es opcional a propósito.** También existe la clienta que pasa por el
        // local y deja el efectivo: ahí el comprobante lo da el salón, no ella.
        $comprobante = null;
        if ($request->hasFile('comprobante')) {
            $archivo = $request->file('comprobante');

            if (! $archivo->isValid()) {
                flash('El comprobante no llegó completo. Probá de nuevo.', 'error');

                return $volver;
            }
            if ($archivo->getSize() > 3 * 1024 * 1024) {
                flash('El comprobante no puede pesar más de 3 MB. Sacale una foto más chica.', 'error');

                return $volver;
            }

            // **Se mira el contenido, no la extensión**, que la elige quien
            // sube el archivo. Se acepta la foto de la pantalla —que es como lo
            // manda casi todo el mundo— y el PDF que dan algunos bancos.
            $info = @getimagesize($archivo->getRealPath());
            $tipos = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];
            $esPdf = str_starts_with((string) @file_get_contents($archivo->getRealPath(), false, null, 0, 5), '%PDF-');

            if (! $esPdf && (! $info || ! isset($tipos[$info[2]]))) {
                flash('El comprobante tiene que ser una imagen (PNG, JPG o WEBP) o un PDF.', 'error');

                return $volver;
            }

            $comprobante = 'sena-' . $idCita . '-' . date('YmdHis') . '.'
                . ($esPdf ? 'pdf' : $tipos[$info[2]]);
            try {
                // **Fuera de `public/`**: es plata de una persona y no tiene por
                // qué quedar colgando de una URL que alguien adivine. Se sirve
                // desde el sistema, con la sesión ya comprobada.
                $archivo->move(storage_path('app/senas'), $comprobante);
            } catch (Throwable $e) {
                Log::error('No se pudo guardar el comprobante de seña: ' . $e->getMessage());
                flash('No se pudo guardar el comprobante. El detalle quedó registrado.', 'error');

                return $volver;
            }
        }

        DB::insert('INSERT INTO sena_solicitud (id_cita, monto, comprobante) VALUES (?,?,?)',
            [$idCita, $monto, $comprobante]);

        flash('Anotamos que vas a dejar ' . money($monto) . ' de seña para tu cita del '
            . fecha($cita->fecha_hora) . '.'
            . ($comprobante ? ' Recibimos tu comprobante.' : '')
            . ' Se confirma en el salón cuando el dinero esté acreditado.');

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
            // **Lo canjeado no se paga**, así que no entra en lo que se puede
            // señar. Una cita cuyos servicios están todos canjeados no tiene
            // nada que adelantar, y ofrecerle «dejar una seña» es pedirle plata
            // por algo que ya pagó con sus puntos. Si además pidió otro
            // servicio sin canje, ese sí se puede señar — por eso es una resta
            // y no un «tiene canje: no muestres nada».
            'SELECT v.*, (ec.nombre = \'En proceso\') AS en_curso, c.id_estado_cita, c.id_sucursal, c.id_usuario,
                    -- **Los servicios de la cita, por id.** Los necesita el
                    -- selector de horarios del modal de reprogramar: reprogramar
                    -- no pregunta qué se hace —eso ya está decidido— así que los
                    -- días libres se calculan con estos, fijos.
                    (SELECT GROUP_CONCAT(cs.id_servicio) FROM cita_servicio cs
                      WHERE cs.id_cita = v.id_cita) AS servicios_ids,
                    fn_cita_sena(v.id_cita) AS sena,
                    -- Cuánta seña pide el salón por esta cita. Sale de
                    -- `servicio.sena_porcentaje`: hasta acá el sistema no
                    -- podía contestarlo y la clienta anunciaba el monto
                    -- que quisiera.
                    fn_cita_sena_requerida(v.id_cita) AS sena_requerida,
                    (SELECT COALESCE(SUM(s.precio),0)
                       FROM cita_servicio cs JOIN servicio s ON s.id_servicio = cs.id_servicio
                      WHERE cs.id_cita = v.id_cita) AS total_lista,
                    -- Con el descuento aplicado: es lo que va a pagar.
                    fn_cita_total(v.id_cita) AS total_cita,
                    (SELECT COALESCE(SUM(s2.precio),0)
                       FROM canje cj JOIN servicio s2 ON s2.id_servicio = cj.id_servicio
                      WHERE cj.id_cita = v.id_cita) AS canjeado,
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

            // **A dónde transferir la seña, por sucursal.** No hay pasarela de
            // pagos y no la va a haber: la clienta transfiere por su cuenta,
            // así que lo único que el sistema puede hacer es decirle a qué
            // cuenta. Hasta la 7.67.0 eso dependía de que alguien contestara
            // el WhatsApp.
            //
            // Se trae indexado por sucursal porque cada cita puede ser de un
            // local distinto, y son las cuentas de ESE local las que valen.
            'cuentas' => $this->cuentasPorSucursal($prox),

            // **De dónde sale la seña de cada cita.** El total solo no se puede
            // comprobar: con tres servicios marcados no se sabe si es de uno o
            // de todos. Se resuelve acá y no en la vista, que ahí correría una
            // consulta por cita dentro del `foreach`.
            'desgloses' => $this->desglosesDeSena($prox),
        ]);
    }

    /**
     * El desglose de la seña de cada cita que la pide.
     *
     * Sólo de las que piden algo: para las demás el bloque no se dibuja, así
     * que traerlo sería una consulta al pedo por cita.
     *
     * @param  array<int, object>  $citas
     * @return array<int, array{filas: array<int, object>, total: float, lista: float}>
     */
    private function desglosesDeSena(array $citas): array
    {
        $out = [];
        foreach ($citas as $c) {
            if ((float) ($c->sena_requerida ?? 0) > 0) {
                $out[(int) $c->id_cita] = Sena::desglose((int) $c->id_cita);
            }
        }

        return $out;
    }

    /**
     * Las cuentas de cobro de los locales donde la clienta tiene cita.
     *
     * Una sola consulta para todas: en la vista, dentro del `foreach` de
     * citas, correría una por cita.
     */
    private function cuentasPorSucursal(array $citas): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(fn ($c) => (int) ($c->id_sucursal ?? 0), $citas)
        )));
        if (! $ids) {
            return [];
        }

        $filas = DB::select(
            'SELECT d.id_sucursal, d.entidad, d.titular, d.documento, d.tipo_cuenta,
                    d.numero_cuenta, d.alias, d.alias_tipo, d.observacion, m.nombre AS medio
               FROM dato_pago_sucursal d
               JOIN metodo_pago m ON m.id_metodo_pago = d.id_metodo_pago
              WHERE d.activo = 1 AND d.id_sucursal IN ('
                . implode(',', array_fill(0, count($ids), '?')) . ')
              ORDER BY d.orden, d.id_dato_pago', $ids
        );

        $por = [];
        foreach ($filas as $f) {
            $por[(int) $f->id_sucursal][] = $f;
        }

        return $por;
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

    /**
     * El equipo del salón: quién atiende y qué hace cada una.
     *
     * **Vive aparte de la pantalla de reservar**, que ya pide elegir
     * servicios, profesional, día y hora: el equipo entero desplegado ahí
     * compite con lo único que se viene a hacer. Esto se mira antes, una vez.
     */
    public function profesionales(Request $request): View
    {
        $sucursales = DB::select('SELECT id_sucursal, nombre FROM sucursal WHERE activo = 1 ORDER BY nombre');
        $elegida = (int) $request->query('sucursal', 0)
            ?: (int) ($sucursales[0]->id_sucursal ?? 0);

        // El equipo que atiende EN ESE LOCAL, con la misma regla que la agenda:
        // quien no tiene turno acá no atiende acá.
        $equipo = DB::select(
            "SELECT u.id_usuario, CONCAT(pe.nombre,' ',pe.apellido) AS nombre,
                    COALESCE((SELECT GROUP_CONCAT(s.nombre ORDER BY s.nombre SEPARATOR '|')
                                FROM persona_servicio ps
                                JOIN servicio s ON s.id_servicio = ps.id_servicio AND s.activo = 1
                               WHERE ps.id_persona = u.id_persona), '') AS servicios,
                    (SELECT ROUND(AVG(cal.puntaje),1) FROM calificacion cal
                       JOIN cita c ON c.id_cita = cal.id_cita
                      WHERE c.id_usuario = u.id_usuario) AS puntaje,
                    -- **En qué turnos atiende, y a qué hora.** «Hace mechas» no
                    -- alcanza para decidir: la clienta necesita saber si esa
                    -- persona está a la mañana o a la tarde antes de elegirla,
                    -- porque si no descubre el horario recién en el selector.
                    --
                    -- Se arma acá y no en la vista: son los turnos DE ESE LOCAL
                    -- —el mismo criterio con el que se decide quién aparece— y
                    -- resolverlo en PHP sería una consulta por profesional.
                    COALESCE((SELECT GROUP_CONCAT(DISTINCT
                                        CONCAT(tl2.nombre, ': ',
                                               TIME_FORMAT(tl2.hora_inicio, '%H:%i'), ' a ',
                                               TIME_FORMAT(tl2.hora_fin, '%H:%i'))
                                        ORDER BY tl2.hora_inicio SEPARATOR '|')
                                FROM usuario_turno ut2
                                JOIN turno_laboral tl2 ON tl2.id_turno = ut2.id_turno AND tl2.activo = 1
                               WHERE ut2.id_usuario = u.id_usuario
                                 AND (? = 0 OR tl2.id_sucursal = ?)), '') AS turnos
               FROM usuario u
               JOIN rol r ON r.id_rol = u.id_rol
               JOIN persona pe ON pe.id_persona = u.id_persona
              WHERE u.activo = 1 AND r.es_personal = 1
                AND EXISTS (SELECT 1 FROM usuario_turno ut
                             JOIN turno_laboral tl ON tl.id_turno = ut.id_turno
                            WHERE ut.id_usuario = u.id_usuario AND tl.activo = 1
                              AND (? = 0 OR tl.id_sucursal = ?))
              ORDER BY pe.nombre", [$elegida, $elegida, $elegida, $elegida]
        );

        return view('portal.profesionales', [
            'sucursales' => $sucursales,
            'sucursal' => $elegida,
            'equipo' => $equipo,
        ]);
    }

    /**
     * La clienta pide otro horario para su cita.
     *
     * **Desde el portal sólo se podía cancelar**, y son dos cosas distintas:
     * quien no puede el martes no quiere dejar de venir, quiere venir el
     * jueves. Obligarla a cancelar y volver a reservar le hace perder el lugar
     * —y la seña que ya dejó—, así que muchas terminaban llamando al salón.
     *
     * La reprogramación ya existía **por el enlace del correo**
     * (`CitaTokenController`); acá se ofrece a la clienta que sí tiene cuenta,
     * que hasta ahora era la única que no la tenía.
     *
     * Pasa por `Agenda::reprogramar()`, o sea con el mismo candado y la misma
     * comprobación de disponibilidad que el mostrador: la cita conserva su
     * profesional y su seña, y sólo cambia de hora.
     */
    public function reprogramar(Request $request): RedirectResponse
    {
        $idc = $this->cliente();
        $id = (int) $request->input('id_cita', 0);
        $volver = redirect()->route('portal.citas');

        $cita = DB::selectOne(
            'SELECT id_cita, fecha_hora, id_estado_cita, id_usuario FROM cita
              WHERE id_cita = ? AND id_cliente = ?', [$id, $idc]);
        if (! $cita) {
            flash('No podés reprogramar esa cita.', 'error');

            return $volver;
        }

        $nueva = str_replace('T', ' ', trim((string) $request->input('fecha_hora', '')));
        if (strlen($nueva) === 16) {
            $nueva .= ':00';
        }
        $motivo = trim((string) $request->input('motivo', ''));

        $servicios = array_map(fn ($r) => (int) $r->id_servicio,
            DB::select('SELECT id_servicio FROM cita_servicio WHERE id_cita = ?', [$id]));
        $dur = Agenda::duracion($servicios) ?: 60;
        $idProf = (int) $cita->id_usuario;

        $error = match (true) {
            in_array((int) $cita->id_estado_cita, [3, 4, 5], true)
                => 'Esa cita ya está cerrada o en curso: hablá con el salón.',

            // **Una sola vez, y el sistema ya lo sabía.** `sp_reprogramar_cita`
            // deja la cita en «Reprogramada» (estado 2) desde siempre, así que
            // ese estado ES la marca de que la clienta ya usó su cambio.
            //
            // El límite existe porque sin él una reserva se puede empujar hacia
            // adelante indefinidamente: el hueco queda tomado y nadie más lo
            // puede usar, que es exactamente lo que la seña vino a evitar.
            (int) $cita->id_estado_cita === 2
                => 'Ya cambiaste el día de esta cita una vez, y es el único cambio que '
                   . 'podemos hacer por el portal. Si necesitás otro, escribinos y lo vemos.',

            // El motivo no es burocracia: es lo que le deja al salón entender
            // por qué se mueven las citas — si es siempre el mismo horario, el
            // problema es el horario.
            $motivo === '' => 'Contanos por qué necesitás cambiarlo.',

            $nueva === '' || ! strtotime($nueva) => 'Elegí la nueva fecha y hora.',
            strtotime($nueva) < time() => 'No se puede reprogramar a una fecha que ya pasó.',
            // **El motivo se explica**, que es lo que separa «no se puede» de
            // «probá con otro día»: la misma función que usa el mostrador.
            ! Agenda::huecoLibre($idProf, $nueva, $dur, $id)
                => Agenda::motivoHuecoPerdido($idProf, $nueva, $dur, $id),
            default => null,
        };
        if ($error) {
            flash($error, 'error');

            return $volver;
        }

        try {
            Agenda::reprogramar($id, $nueva);

            // El motivo queda en la cita y en la auditoría: en la cita lo ve
            // quien atiende ese día, en la auditoría queda el rastro de quién
            // lo pidió y cuándo.
            DB::update(
                "UPDATE cita SET observaciones = TRIM(CONCAT(COALESCE(observaciones,''), ' ', ?))
                  WHERE id_cita = ?",
                ['[Cambio de día pedido por la clienta: ' . mb_substr($motivo, 0, 200) . ']', $id]
            );
            Auditoria::registrarComo((int) session('uid'), 'REPROGRAMACION', 'Portal', 'cita', $id,
                'La clienta reprogramó desde el portal para ' . $nueva . '. Motivo: ' . $motivo);

            flash('¡Listo! Tu cita quedó para el ' . fecha($nueva) . '. '
                . 'Tené en cuenta que este era el único cambio que se puede hacer desde acá.');
        } catch (Throwable) {
            flash('Ese horario se ocupó recién. Elegí otro, por favor.', 'error');
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
            // **La clienta ve el programa entero.** No está atada a un local
            // —elige al agendar— y el vale ya canjeado vale en cualquier sede,
            // así que filtrar acá le escondería premios que sí puede usar.
            'canjeables' => Canje::catalogo(true, 0),
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
            // **`id_sucursal` e `id_usuario` hacen falta abajo**, en la lista de
            // lo que la clienta puede pedir. La 7.57.0 la agregó leyendo los dos
            // campos y no los sumó acá: `$cita->id_sucursal` sobre una propiedad
            // que no existe es `ErrorException`, o sea **500 en cada carga de
            // esta pantalla** — la clienta no podía ver su atención en curso.
            "SELECT c.id_cita, c.fecha_hora, c.id_estado_cita, c.id_sucursal, c.id_usuario,
                    ec.nombre AS estado,
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
            // **Lo que puede pedir: lo que SUS profesionales hacen en ESTE
            // local.** El pedido era un texto libre, así que la clienta podía
            // pedir algo que en esa sucursal no se ofrece o que ninguna de las
            // personas que la está atendiendo hace — y el «no» llegaba después,
            // en el sillón. Con la lista, lo que aparece es lo que se le puede
            // dar.
            //
            // `usuario_servicio` vale con el criterio permisivo de siempre:
            // quien no tiene ninguno cargado los hace todos.
            'puedePedir' => DB::select(
                'SELECT DISTINCT s.id_servicio, s.nombre, s.precio, s.duracion_min
                   FROM servicio s
                   JOIN servicio_sucursal ss ON ss.id_servicio = s.id_servicio
                                            AND ss.id_sucursal = ?
                  WHERE s.activo = 1
                    AND NOT EXISTS (SELECT 1 FROM cita_servicio cs
                                     WHERE cs.id_cita = ? AND cs.id_servicio = s.id_servicio)
                    AND EXISTS (
                        SELECT 1 FROM cita_servicio cs2
                         WHERE cs2.id_cita = ?
                           AND fn_usuario_hace_servicio(COALESCE(cs2.id_usuario, ?), s.id_servicio) = 1
                    )
                  ORDER BY s.nombre',
                [(int) $cita->id_sucursal, $idCita, $idCita, (int) $cita->id_usuario]
            ),
            'total' => $total,
            'sena' => $sena,
            'aPagar' => max(0.0, $total - $sena),
            'enCurso' => (int) $cita->id_estado_cita === 5,

            // **Hasta que el pago esté cerrado.** La pantalla terminaba con la
            // atención, así que la clienta veía el detalle mientras la
            // atendían y después se quedaba sin saber si el cobro se registró
            // ni con qué comprobante — justo lo que va a querer mirar si algo
            // no cuadra. El comprobante se busca por la cita: puede no existir
            // todavía, y eso también es una respuesta.
            'comprobante' => DB::selectOne(
                'SELECT f.id_factura, fn_factura_nro(f.id_factura) AS nro, tc.nombre AS tipo,
                        fn_factura_total(f.id_factura) AS total,
                        fn_factura_saldo(f.id_factura) AS saldo
                   FROM factura f
                   JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = f.id_tipo_comprobante
                  WHERE f.id_cita = ? AND f.id_estado_factura = 1
                  ORDER BY f.id_factura DESC LIMIT 1', [$idCita]
            ),
            'cobrado' => (float) DB::scalar(
                'SELECT COALESCE(SUM(co.monto),0) FROM cobro co
                  WHERE co.id_estado_cobro = 1
                    AND (co.id_cita = :c1
                         OR co.id_factura IN (SELECT id_factura FROM factura
                                               WHERE id_cita = :c2 AND id_estado_factura = 1))',
                ['c1' => $idCita, 'c2' => $idCita]
            ),
            'pedidos' => DB::select(
                'SELECT observaciones, fecha_registro FROM cita_pedido
                  WHERE id_cita = ? ORDER BY fecha_registro DESC', [$idCita]
            ),
        ];
    }

    /** Descarga el comprobante propio de la clienta en PDF. */
    public function facturaDescargar(Request $request): Response|RedirectResponse
    {
        $f = DB::selectOne(
            'SELECT f.id_factura, fn_factura_nro(f.id_factura) AS nro, tc.nombre AS tipo,
                    fn_factura_total(f.id_factura) AS total, f.fecha_emision,
                    (SELECT GROUP_CONCAT(CONCAT(COALESCE(s.nombre,p.nombre), " x", df.cantidad) SEPARATOR ", ")
                       FROM detalle_factura df LEFT JOIN servicio s ON s.id_servicio = df.id_servicio
                       LEFT JOIN producto p ON p.id_producto = df.id_producto
                      WHERE df.id_factura = f.id_factura) AS detalle
               FROM factura f JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = f.id_tipo_comprobante
              WHERE f.id_factura = ? AND f.id_cliente = ? AND f.id_estado_factura = 1',
            [(int) $request->query('id', 0), $this->cliente()]
        );
        if (! $f) {
            abort(404);
        }
        $html = '<html><meta charset="utf-8"><style>body{font-family:Arial;padding:32px}h1{font-size:20px;border-bottom:1px solid #ccc;padding-bottom:10px}.total{font-size:18px;font-weight:bold;margin-top:24px}</style>'
            . '<h1>Comprobante ' . e($f->nro) . '</h1><p>' . e($f->tipo) . ' · ' . e(fecha($f->fecha_emision)) . '</p>'
            . '<p>' . e($f->detalle ?: 'Detalle no disponible') . '</p><p class="total">Total: ' . e(money($f->total)) . '</p></html>';
        $pdf = new Dompdf();
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="comprobante-' . str_replace('/', '-', (string) $f->nro) . '.pdf"',
        ]);
    }
}
