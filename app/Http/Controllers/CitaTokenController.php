<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Agenda;
use App\Servicios\Auditoria;
use App\Servicios\Calendario;
use App\Servicios\Notificaciones;
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
                'cal' => null, 'urlGoogle' => null]);
        }

        $cal = DB::selectOne(
            'SELECT id_cita, fecha_hora, duracion_min, servicios, profesional
               FROM vw_agenda_citas WHERE id_cita = ?', [$cita->id_cita]
        );

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
            // El .ics no alcanza en el celular: Android lo baja como archivo y
            // no lo abre. Se ofrecen las dos vías.
            'urlGoogle' => $cal ? Calendario::urlGoogle($cal, Calendario::lugar()) : null,
        ]);
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
        $idProf = (int) $request->input('id_usuario', 0) ?: (int) $cita->id_usuario;

        $servicios = array_map(fn ($r) => (int) $r->id_servicio,
            DB::select('SELECT id_servicio FROM cita_servicio WHERE id_cita = ?', [$cita->id_cita]));
        $dur = Agenda::duracion($servicios) ?: 60;

        $error = null;
        if (in_array((int) $cita->id_estado_cita, [3, 4], true)) {
            $error = 'Esa cita ya está cerrada: hablá con el salón.';
        } elseif ($nueva === '' || ! strtotime($nueva)) {
            $error = 'Elegí la nueva fecha y hora.';
        } elseif (strtotime($nueva) < time()) {
            $error = 'No se puede reprogramar a una fecha que ya pasó.';
        } elseif (! DB::scalar('SELECT COUNT(*) FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
                                 WHERE u.id_usuario = ? AND u.activo = 1 AND r.es_personal = 1', [$idProf])) {
            $error = 'Ese profesional ya no está disponible. Elegí otro.';
        } elseif (! Agenda::huecoLibre($idProf, $nueva, $dur, (int) $cita->id_cita)) {
            $error = Agenda::motivoHuecoPerdido($idProf, $nueva, $dur, (int) $cita->id_cita);
        }
        if ($error) {
            flash($error, 'error');

            return $volver;
        }

        try {
            Agenda::reprogramar((int) $cita->id_cita, $nueva,
                $idProf !== (int) $cita->id_usuario ? $idProf : null);
            Auditoria::registrarComo($this->auditor($cita), 'REPROGRAMACION', 'Portal', 'cita',
                (int) $cita->id_cita,
                'La clienta reprogramó desde el enlace del correo para ' . $nueva
                . ($idProf !== (int) $cita->id_usuario ? ' y cambió de profesional' : ''));
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
