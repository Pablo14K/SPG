<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Agenda;
use App\Servicios\Auditoria;
use App\Servicios\Calendario;
use App\Servicios\Notificaciones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/**
 * Reprogramar, cancelar o agendar en el calendario **desde el enlace del
 * correo, sin iniciar sesión**.
 *
 * La mayoría de las clientas que agendan en el local no tienen cuenta: el
 * token del correo ES la credencial. Por eso estas rutas no llevan el
 * middleware de sesión, y por eso el token es largo, de un solo uso para
 * cancelar, y vence a los 30 días.
 *
 * Como no hay sesión, lo que se hace acá no se puede auditar con el usuario
 * de turno: se atribuye a la cuenta de la clienta si la tiene y, si no, al
 * profesional de la cita, aclarándolo en el detalle.
 */
class CitaTokenController extends Controller
{
    public function ver(Request $request): View
    {
        $codigo = (string) $request->query('t', '');
        $cita = Notificaciones::citaPorToken($codigo);

        if (! $cita) {
            return view('cita_token.ver', ['cita' => null, 'codigo' => '', 'profs' => [], 'servicios' => [],
                'cal' => null, 'urlGoogle' => null, 'ctx' => null, 'yaCambio' => false]);
        }

        $cal = DB::selectOne(
            'SELECT id_cita, fecha_hora, duracion_min, servicios, profesional
               FROM vw_agenda_citas WHERE id_cita = ?', [$cita->id_cita]
        );

        // Lo que el selector de horarios necesita para preguntar por ESTA cita:
        // sus servicios, su profesional, su local y para cuántas personas es.
        // Reprogramar no pregunta nada de eso —ya está decidido—, sólo cuándo.
        $ctx = $this->contexto((int) $cita->id_cita);

        return view('cita_token.ver', [
            'cita' => $cita,
            'codigo' => $codigo,
            'profs' => Agenda::profesionales(),
            'servicios' => DB::select(
                'SELECT s.id_servicio, s.nombre, s.precio, s.duracion_min
                   FROM cita_servicio cs JOIN servicio s ON s.id_servicio = cs.id_servicio
                  WHERE cs.id_cita = ?', [$cita->id_cita]
            ),
            'cal' => $cal,
            'ctx' => $ctx,
            // **Ya usó su único cambio.** Se decide acá y no en la vista: el
            // servidor lo rechaza igual, pero ofrecer un formulario que va a
            // contestar que no es prometer algo que no se puede cumplir.
            'yaCambio' => (int) $cita->id_estado_cita === 2,
            // El .ics no alcanza en el celular: Android lo baja como archivo y
            // no lo abre. Se ofrecen las dos vías.
            'urlGoogle' => $cal ? Calendario::urlGoogle($cal, Calendario::lugar()) : null,
        ]);
    }

    /**
     * Los días y las horas libres para reprogramar ESTA cita, sin sesión.
     *
     * **Era el defecto de «los enlaces del correo no funcionan».** La pantalla
     * del enlace se abría bien —el token la deja pasar—, pero el selector de
     * horarios le pedía los días a `portal.disponibilidad`, que vive detrás
     * del middleware de sesión: la clienta que llega desde el correo **no
     * tiene sesión** —ése es el punto del token—, así que la consulta
     * contestaba con una redirección al ingreso, el calendario se quedaba
     * vacío y el botón «Reprogramar» nunca se habilitaba. Sin un solo error
     * en pantalla: la función apagada en silencio de siempre.
     *
     * Acá la credencial es el token, igual que en el resto de la pantalla, y
     * **lo que se consulta sale de la cita y no de la URL**: sus servicios,
     * su profesional, su local y para cuántas personas es. Reprogramar no
     * pregunta nada de eso; sólo cuándo.
     */
    public function disponibilidad(Request $request): JsonResponse
    {
        $cita = Notificaciones::citaPorToken((string) $request->query('t', ''));
        if (! $cita) {
            return response()->json(['ok' => false, 'motivo' => 'Ese enlace ya no sirve. Pedile uno nuevo al salón.']);
        }

        $ctx = $this->contexto((int) $cita->id_cita);
        $servicios = $ctx->servicios;
        $idUsuario = $ctx->id_usuario;
        $suc = $ctx->id_sucursal;
        $personas = $ctx->personas;
        $duracion = $ctx->duracion;

        if ($duracion <= 0) {
            return response()->json(['ok' => false, 'motivo' => 'Esa cita no tiene servicios cargados: hablá con el salón.']);
        }

        $fecha = (string) $request->query('fecha', '');
        if ($fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return response()->json(['ok' => true, 'duracion' => $duracion]
                + Agenda::horasDelDia($idUsuario, $fecha, $duracion, $suc, $servicios, $personas, [], null));
        }

        // Los días en que la clienta ya tiene esos servicios no se ofrecen,
        // como en el portal: el disparador los rechazaría al guardar, con
        // todo ya elegido. El día de ESTA cita entra en esa lista y es lo
        // correcto — moverla a otra hora del mismo día no es cambiar de día.
        $dias = array_values(array_diff(
            Agenda::diasConCupo($idUsuario, date('Y-m-d'),
                                (int) config('sgp.agenda.dias_vista', 60), $duracion, $suc, $servicios, $personas, []),
            Agenda::diasYaTomados((int) $cita->id_cliente, $servicios)
        ));

        return response()->json(['ok' => true, 'duracion' => $duracion, 'duracion_fija' => true,
            'dias' => $dias,
            'motivo' => $dias ? null
                : (Agenda::motivoSinCupo($duracion, $idUsuario, $suc, $servicios, $personas, [])
                    ?? Agenda::porQueNoHayDia($servicios, $personas, [], $suc))]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $codigo = (string) $request->input('t', '');
        $cita = Notificaciones::citaPorToken($codigo);

        if (! $cita) {
            flash('Ese enlace ya no sirve. Pedile uno nuevo al salón.', 'error');

            return redirect()->route('cita.token');
        }
        $volver = redirect()->route('cita.token', ['t' => $codigo]);

        // --- Cancelar desde el enlace ---
        if ($request->input('cancelar')) {
            if ((int) $cita->id_estado_cita === 3) {
                flash('Esa cita ya estaba cancelada.', 'warning');

                return $volver;
            }
            try {
                Agenda::cancelar((int) $cita->id_cita);
                // El token muere con la cancelación: no queda un enlace vivo
                DB::update('UPDATE token_cita SET usado = 1 WHERE codigo = ?', [$codigo]);
                Auditoria::registrarComo($this->auditor($cita), 'CANCELACION', 'Portal', 'cita',
                    (int) $cita->id_cita, 'La clienta canceló desde el enlace del correo');
                flash('Tu cita fue cancelada. ¡Te esperamos en otra ocasión!');
            } catch (Throwable) {
                flash('No se pudo cancelar la cita.', 'error');
            }

            return $volver;
        }

        // --- Reprogramar ---
        $nueva = str_replace('T', ' ', trim((string) $request->input('fecha_hora', '')));
        if (strlen($nueva) === 16) {
            $nueva .= ':00';
        }
        // **El profesional NO se toma del POST.** La pantalla dejó de ofrecer el
        // combo en la 7.97.0 —los horarios que muestra el selector se calculan
        // para quien te atiende, así que cambiarlo ahí los invalidaría— y el
        // servidor lo seguía aceptando: con el token en la mano se le podía
        // reasignar la cita a cualquier profesional activo. Es la cita la que
        // dice quién atiende.
        $idProf = (int) $cita->id_usuario;

        // **La misma cuenta que usó el selector.** Las dos salen de
        // `contexto()`: escritas aparte se separan, y ahí la pantalla ofrece un
        // horario que el guardado rechaza — el defecto que este proyecto ya
        // tiene anotado.
        $ctx = $this->contexto((int) $cita->id_cita);
        $dur = $ctx->duracion ?: 60;

        $error = null;
        if (in_array((int) $cita->id_estado_cita, [3, 4], true)) {
            $error = 'Esa cita ya está cerrada: hablá con el salón.';
        // **Un solo cambio, también desde el correo.** El portal lo hacía
        // cumplir desde la 7.66.0 y este camino se quedó afuera: el enlace
        // sigue llegando en cada recordatorio, así que la clienta podía
        // reprogramar la misma cita todas las veces que quisiera. `Reprogramada`
        // (estado 2) ES la marca de que el cambio ya se usó — `sp_reprogramar_cita`
        // la deja ahí desde siempre.
        } elseif ((int) $cita->id_estado_cita === 2) {
            $error = 'Ya cambiaste el día de esta cita una vez, que es el único cambio '
                   . 'que se puede hacer desde acá. Si necesitás moverla otra vez, escribinos.';
        } elseif ($nueva === '' || ! strtotime($nueva)) {
            $error = 'Elegí la nueva fecha y hora.';
        } elseif (strtotime($nueva) < time()) {
            $error = 'No se puede reprogramar a una fecha que ya pasó.';
        } elseif (! DB::scalar('SELECT COUNT(*) FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
                                 WHERE u.id_usuario = ? AND u.activo = 1 AND r.es_personal = 1', [$idProf])) {
            $error = 'Tu profesional ya no está disponible. Escribinos y lo vemos.';
        // **Y el local es el DE LA CITA, no el de la sesión.** `huecoLibre` cae
        // en `Sucursales::activa()` cuando no se lo dice, y acá eso es la sesión
        // de quien tenga el navegador abierto: la clienta del correo no tiene
        // ninguna, y alguien del salón que abra el enlace estando parado en otro
        // local hacía que la comprobación corriera contra los turnos de esa otra
        // sede. El turno es del local, así que sin esto el enlace podía rechazar
        // justo el horario que su propio calendario acababa de ofrecer.
        } elseif (! Agenda::huecoLibre($idProf, $nueva, $dur, (int) $cita->id_cita, $ctx->id_sucursal)) {
            $error = Agenda::motivoHuecoPerdido($idProf, $nueva, $dur, (int) $cita->id_cita);
        }
        if ($error) {
            flash($error, 'error');

            return $volver;
        }

        try {
            Agenda::reprogramar((int) $cita->id_cita, $nueva);
            Auditoria::registrarComo($this->auditor($cita), 'REPROGRAMACION', 'Portal', 'cita',
                (int) $cita->id_cita,
                'La clienta reprogramó desde el enlace del correo para ' . $nueva);
            flash('¡Listo! Tu cita quedó para el ' . fecha($nueva) . '.');
        } catch (Throwable) {
            flash('Ese horario se ocupó recién. Elegí otro, por favor.', 'error');
        }

        return $volver;
    }

    /**
     * El .ics para guardar la cita en el calendario del teléfono.
     *
     * Dos formas de entrar, igual que el resto: con el token del correo (para
     * quien no tiene cuenta) o con sesión iniciada, y ahí la cita tiene que
     * ser suya.
     */
    public function calendario(Request $request): Response
    {
        $codigo = (string) $request->query('t', '');
        $id = (int) $request->query('id', 0);

        if ($codigo !== '') {
            $tok = Notificaciones::citaPorToken($codigo);
            if (! $tok) {
                abort(410, 'Ese enlace ya no es válido. Pedile uno nuevo al salón.');
            }
            $id = (int) $tok->id_cita;
        } else {
            $idc = (int) session('id_cliente', 0);
            if (! $idc || ! DB::scalar('SELECT COUNT(*) FROM cita WHERE id_cita = ? AND id_cliente = ?', [$id, $idc])) {
                abort(403, 'Esa cita no es tuya.');
            }
        }

        $cita = DB::selectOne(
            'SELECT v.id_cita, v.fecha_hora, v.duracion_min, v.servicios, v.profesional, c.id_estado_cita
               FROM vw_agenda_citas v JOIN cita c ON c.id_cita = v.id_cita
              WHERE v.id_cita = ?', [$id]
        );
        if (! $cita) {
            abort(404, 'No encontramos esa cita.');
        }
        if ((int) $cita->id_estado_cita === 3) {
            abort(410, 'Esa cita fue cancelada, no tiene sentido agendarla.');
        }

        // Se respeta la anticipación que la clienta eligió; si no configuró
        // nada, dos horas antes.
        $dias = (int) (DB::scalar(
            'SELECT pr.dias_antes FROM preferencia_recordatorio pr
               JOIN cita c ON c.id_cliente = pr.id_cliente
              WHERE c.id_cita = ? AND pr.activo = 1', [$id]
        ) ?: 0);
        $aviso = $dias > 0 ? $dias * 24 * 60 : 120;
        $aviso = max(5, min($aviso, 7 * 24 * 60));

        $ics = Calendario::deCita($cita, $aviso, Calendario::lugar());

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="cita-' . date('Ymd-Hi', strtotime((string) $cita->fecha_hora)) . '.ics"',
        ]);
    }

    /**
     * Lo que ESTA cita es, para el selector y para el guardado.
     *
     * Los dos tienen que medir igual, que es lo que se pidió verificar: «que
     * las fechas y horas del reagendado desde el link funcionen de la misma
     * manera que desde el módulo de Agendamiento». Escrito dos veces se
     * separa —uno calcula la duración con el reparto y el otro con la suma, o
     * uno filtra por el local de la cita y el otro por el de la sesión— y el
     * resultado es el defecto que este proyecto persigue desde siempre: la
     * pantalla ofrece un horario que el servidor después rechaza.
     *
     * Nada de esto sale de la URL: todo sale de la cita. Reprogramar no
     * pregunta qué se hace, ni con quién, ni dónde, ni para cuántas personas
     * —eso ya está decidido—; lo único que se elige es cuándo.
     */
    private function contexto(int $idCita): object
    {
        $c = DB::selectOne(
            'SELECT c.id_usuario, c.id_sucursal, c.personas,
                    (SELECT GROUP_CONCAT(cs.id_servicio) FROM cita_servicio cs
                      WHERE cs.id_cita = c.id_cita) AS servicios_ids
               FROM cita c WHERE c.id_cita = ?', [$idCita]
        );

        $servicios = array_values(array_filter(array_map('intval', explode(',', (string) ($c->servicios_ids ?? '')))));
        // Un servicio pedido para dos personas es una fila por persona: la
        // cita se mide con las dos (7.119.0). Se fija ANTES de quitar los
        // repetidos, que es de donde sale la cuenta.
        Agenda::vecesPorServicio([], $servicios);
        $servicios = array_values(array_unique($servicios));

        $idUsuario = ((int) ($c->id_usuario ?? 0)) ?: null;
        $suc = ((int) ($c->id_sucursal ?? 0)) ?: null;
        $personas = max(1, min(20, (int) ($c->personas ?? 1)));

        return (object) [
            'servicios' => $servicios,
            'servicios_ids' => (string) ($c->servicios_ids ?? ''),
            'id_usuario' => $idUsuario,
            'id_sucursal' => $suc,
            'personas' => $personas,
            'duracion' => Agenda::duracionPrevista($servicios, $personas, $suc, $idUsuario, []),
        ];
    }

    /**
     * A nombre de quién se audita lo que hace la clienta desde el enlace: su
     * propia cuenta si la tiene, y si no la del profesional de la cita
     * (`auditoria.id_usuario` es NOT NULL).
     */
    private function auditor(object $cita): int
    {
        $suyo = (int) (DB::scalar('SELECT id_usuario FROM cliente WHERE id_cliente = ?', [$cita->id_cliente]) ?: 0);

        return $suyo ?: (int) $cita->id_usuario;
    }
}
