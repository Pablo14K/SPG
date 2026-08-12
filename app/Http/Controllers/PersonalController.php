<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Auditoria;
use App\Servicios\Borrador;
use App\Servicios\Listado;
use App\Servicios\Notificaciones;
use App\Servicios\Permisos;
use App\Servicios\Persona;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * La mitad del módulo Seguridad que trata de la gente del salón: usuarios,
 * turnos, comisiones y asistencia. La otra mitad —sucursales, roles, contacto y
 * auditoría— está en `ConfiguracionController`, y la portada del módulo en
 * `SeguridadController`.
 *
 * Dos cosas que conviene tener presentes:
 *
 *  · **El turno es una plantilla, no una fecha**: un nombre, un horario y los
 *    días de la semana en que se trabaja. Se define una vez y se le asigna a
 *    cada persona. El modelo anterior guardaba una fila por día y por persona,
 *    así que el 1 de cada mes la agenda se quedaba sin horarios.
 *
 *  · **La asistencia se ficha, no se escribe.** La hora es la del clic, tomada
 *    del reloj de la base. Un fichaje que se puede tipear no prueba nada.
 */
class PersonalController extends Controller
{
    /** dia_semana va de 1 (lunes) a 7 (domingo), igual que date('N') y WEEKDAY()+1 */
    private const DIAS = [
        1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
        5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo',
    ];

    // ---------- Usuarios ----------

    public function usuarios(): View|StreamedResponse
    {
        $f = Listado::filtros([
            'q' => ['tipo' => 'texto', 'etiqueta' => 'Buscar', 'ph' => 'Nombre, usuario o email', 'ancho' => '250px'],
            'rol' => ['tipo' => 'select', 'etiqueta' => 'Rol', 'opciones' => ['' => 'Todos'] + $this->rolesPersonal()],
            'estado' => ['tipo' => 'select', 'etiqueta' => 'Estado',
                         'opciones' => ['' => 'Todos', '1' => 'Activos', '0' => 'Inactivos']],
            'turno' => ['tipo' => 'select', 'etiqueta' => 'Turno', 'ancho' => '190px',
                        'opciones' => ['' => 'Todos', '0' => '— Sin turno asignado —'] + $this->turnosOpciones()],
        ]);
        $f['csv'] = true;

        $w = ['r.es_personal = 1'];
        $par = [];
        if (Listado::hay($f, 'q')) {
            $w[] = Listado::likeVarias(["CONCAT(pe_u.nombre,' ',pe_u.apellido)", 'u.username', 'pe_u.email', 'pe_u.cedula'],
                Listado::valor($f, 'q'), 'q', $par);
        }
        if (Listado::hay($f, 'rol')) {
            $w[] = 'u.id_rol = :r';
            $par['r'] = (int) Listado::valor($f, 'rol');
        }
        if (Listado::hay($f, 'estado')) {
            $w[] = 'u.activo = :e';
            $par['e'] = (int) Listado::valor($f, 'estado');
        }
        if (Listado::hay($f, 'turno')) {
            $t = (int) Listado::valor($f, 'turno');
            if ($t === 0) {
                $w[] = 'NOT EXISTS (SELECT 1 FROM usuario_turno ut WHERE ut.id_usuario = u.id_usuario)';
            } else {
                $w[] = 'EXISTS (SELECT 1 FROM usuario_turno ut WHERE ut.id_usuario = u.id_usuario AND ut.id_turno = :t)';
                $par['t'] = $t;
            }
        }

        $desde = 'FROM usuario u
                  JOIN persona pe_u ON pe_u.id_persona = u.id_persona
                  JOIN rol r ON r.id_rol = u.id_rol
                  WHERE ' . implode(' AND ', $w);
        $cols = "u.id_usuario, pe_u.nombre, pe_u.apellido, u.username, pe_u.email, pe_u.telefono, u.activo,
                 r.nombre AS rol,
                 (SELECT GROUP_CONCAT(t.nombre ORDER BY t.hora_inicio SEPARATOR ' · ')
                    FROM usuario_turno ut JOIN turno_laboral t ON t.id_turno = ut.id_turno AND t.activo = 1
                   WHERE ut.id_usuario = u.id_usuario) AS turnos";
        $orden = 'ORDER BY pe_u.nombre, pe_u.apellido';

        if (Listado::pideExport()) {
            return Listado::exportar('personal',
                ['Nombre', 'Usuario', 'Email', 'Teléfono', 'Rol', 'Turnos', 'Estado'],
                array_map(fn ($r) => [$r->nombre . ' ' . $r->apellido, $r->username, $r->email,
                    $r->telefono, $r->rol, $r->turnos, $r->activo ? 'Activo' : 'Inactivo'],
                    DB::select("SELECT $cols $desde $orden", $par)),
                $f, 'Personal'
            );
        }

        $pag = Listado::paginacion((int) DB::scalar("SELECT COUNT(*) $desde", $par));

        return view('seguridad.usuarios', [
            'rows' => DB::select("SELECT $cols $desde $orden LIMIT {$pag['porPagina']} OFFSET {$pag['offset']}", $par),
            'f' => $f,
            'pag' => $pag,
        ]);
    }

    public function usuarioForm(int $id = 0): View|RedirectResponse
    {
        $u = $id ? DB::selectOne(
            'SELECT u.*, pe.nombre, pe.apellido, pe.cedula, pe.telefono, pe.email
               FROM usuario u JOIN persona pe ON pe.id_persona = u.id_persona
              WHERE u.id_usuario = ?', [$id]
        ) : null;

        if ($id && ! $u) {
            flash('Usuario no encontrado.', 'error');

            return redirect()->route('seguridad.usuarios');
        }

        return view('seguridad.usuario_form', [
            'u' => $u,
            'roles' => DB::select('SELECT * FROM rol WHERE es_personal = 1 AND activo = 1 ORDER BY id_rol'),
            'sucursales' => DB::select('SELECT id_sucursal, nombre FROM sucursal WHERE activo = 1 ORDER BY nombre'),
            'misSuc' => $id ? array_map(fn ($r) => (int) $r->id_sucursal,
                DB::select('SELECT id_sucursal FROM usuario_sucursal WHERE id_usuario = ?', [$id])) : [],
            'turnos' => $this->turnosDisponibles(),
            'misTurnos' => $id ? array_map(fn ($r) => (int) $r->id_turno,
                DB::select('SELECT id_turno FROM usuario_turno WHERE id_usuario = ?', [$id])) : [],
            'dias' => self::DIAS,
        ]);
    }

    public function usuarioGuardar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_usuario', 0);
        $d = [
            'id_rol' => (int) $request->input('id_rol', 0),
            'id_sucursal' => ((int) $request->input('id_sucursal', 0)) ?: null,
            'username' => trim((string) $request->input('username', '')),
            'nombre' => trim((string) $request->input('nombre', '')),
            'apellido' => trim((string) $request->input('apellido', '')),
            'cedula' => trim((string) $request->input('cedula', '')) ?: null,
            'telefono' => trim((string) $request->input('telefono', '')) ?: null,
            'email' => trim((string) $request->input('email', '')),
        ];
        $pass = (string) $request->input('password', '');
        $sucs = array_values(array_unique(array_map('intval', (array) $request->input('sucursales', []))));
        $turnos = array_values(array_unique(array_map('intval', (array) $request->input('turnos', []))));
        $volver = $id ? redirect()->route('seguridad.usuario_form', $id) : redirect()->route('seguridad.usuario_form');

        $error = null;
        if ($d['username'] === '' || $d['nombre'] === '' || $d['apellido'] === '' || $d['email'] === '') {
            $error = 'Usuario, nombre, apellido y email son obligatorios.';
        } elseif (! preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $d['username'])) {
            $error = 'El nombre de usuario debe tener entre 3 y 60 caracteres (letras, números, punto, guion o guion bajo).';
        } elseif (! filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'El email no tiene un formato válido.';
        } elseif (! $d['id_rol'] || ! DB::scalar('SELECT COUNT(*) FROM rol WHERE id_rol = ? AND es_personal = 1 AND activo = 1', [$d['id_rol']])) {
            $error = 'Elegí un rol de personal válido.';
        } elseif ($pass !== '' && strlen($pass) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif (! $id && $pass === '') {
            $error = 'La contraseña es obligatoria para un usuario nuevo.';
        } elseif (DB::scalar('SELECT COUNT(*) FROM usuario WHERE username = ? AND id_usuario <> ?', [$d['username'], $id])) {
            $error = 'Ese nombre de usuario ya está en uso.';
        } elseif (DB::scalar('SELECT COUNT(*) FROM usuario u JOIN persona pe ON pe.id_persona = u.id_persona
                               WHERE pe.email = ? AND u.id_usuario <> ?', [$d['email'], $id])) {
            $error = 'Ya existe una cuenta con ese email.';
        } elseif ($d['cedula'] && DB::scalar('SELECT COUNT(*) FROM usuario u JOIN persona pe ON pe.id_persona = u.id_persona
                                               WHERE pe.cedula = ? AND u.id_usuario <> ?', [$d['cedula'], $id])) {
            $error = 'Ya existe un usuario con esa cédula.';
        } elseif ($id === (int) session('uid') && $d['id_rol'] !== (int) config('permisos.rol_admin', 1)) {
            $error = 'No podés quitarte a vos mismo el rol de Administrador: pedile a otro Administrador que lo haga.';
        } else {
            $error = Persona::error($d);
        }
        if ($error) {
            flash($error, 'error');

            return $volver->withInput();
        }

        // La sucursal principal tiene que estar entre las asignadas
        if ($d['id_sucursal'] && ! in_array((int) $d['id_sucursal'], $sucs, true)) {
            $sucs[] = (int) $d['id_sucursal'];
        }
        if (! $d['id_sucursal'] && $sucs) {
            $d['id_sucursal'] = $sucs[0];
        }

        try {
            $r = DB::transaction(function () use ($id, $d, $pass, $sucs, $turnos) {
                if ($id) {
                    $idPersona = (int) DB::scalar('SELECT id_persona FROM usuario WHERE id_usuario = ?', [$id]);
                    Persona::guardar($idPersona, $d);
                    DB::update(
                        'UPDATE usuario SET id_rol = :id_rol, id_sucursal = :id_sucursal, username = :username
                          WHERE id_usuario = :id',
                        ['id_rol' => $d['id_rol'], 'id_sucursal' => $d['id_sucursal'],
                         'username' => $d['username'], 'id' => $id]
                    );
                    if ($pass !== '') {
                        DB::update('UPDATE usuario SET password_hash = ? WHERE id_usuario = ?',
                            [Hash::make($pass), $id]);
                    }
                    $idUsuario = $id;
                } else {
                    // Si la cédula ya existe puede ser alguien cargado como
                    // cliente del salón: se le agrega la cuenta sobre la misma
                    // persona, en vez de duplicarla.
                    $idPersona = Persona::guardar(Persona::porDocumento($d['cedula']), $d);
                    DB::insert(
                        'INSERT INTO usuario (id_persona,id_rol,id_sucursal,username,password_hash) VALUES (?,?,?,?,?)',
                        [$idPersona, $d['id_rol'], $d['id_sucursal'], $d['username'], Hash::make($pass)]
                    );
                    $idUsuario = (int) DB::getPdo()->lastInsertId();
                }

                // Sucursales donde trabaja (un empleado puede estar en varias)
                DB::delete('DELETE FROM usuario_sucursal WHERE id_usuario = ?', [$idUsuario]);
                foreach ($sucs as $s) {
                    if ($s > 0 && DB::scalar('SELECT COUNT(*) FROM sucursal WHERE id_sucursal = ?', [$s])) {
                        DB::insert('INSERT IGNORE INTO usuario_sucursal (id_usuario,id_sucursal) VALUES (?,?)',
                            [$idUsuario, $s]);
                    }
                }

                // Turnos que trabaja. Es N:M: el mismo turno («Mañana, 08:00 a
                // 12:00, lunes a sábado») lo comparte todo el equipo.
                DB::delete('DELETE FROM usuario_turno WHERE id_usuario = ?', [$idUsuario]);
                $nTurnos = 0;
                foreach ($turnos as $t) {
                    if ($t > 0 && DB::scalar('SELECT COUNT(*) FROM turno_laboral WHERE id_turno = ? AND activo = 1', [$t])) {
                        DB::insert('INSERT IGNORE INTO usuario_turno (id_usuario,id_turno) VALUES (?,?)', [$idUsuario, $t]);
                        $nTurnos++;
                    }
                }

                return ['id' => $idUsuario, 'turnos' => $nTurnos];
            });

            Auditoria::registrar($id ? 'MODIFICACION' : 'ALTA', 'Personal', 'usuario', $r['id'],
                $d['nombre'] . ' ' . $d['apellido']);

            flash(($id ? 'Usuario actualizado.' : 'Usuario creado.')
                . (count($sucs) > 1 ? ' Trabaja en ' . count($sucs) . ' sucursales.' : '')
                . ($r['turnos']
                    ? " Trabaja {$r['turnos']} turno(s)."
                    : ' Sin turno asignado: no va a aparecer en la agenda hasta que se le asigne uno.'));
        } catch (Throwable) {
            flash('No se pudo guardar el usuario (¿usuario, email o cédula duplicado?).', 'error');

            return $volver->withInput();
        }

        return redirect()->route('seguridad.usuarios');
    }

    public function usuarioBaja(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_usuario', 0);
        $volver = redirect()->route('seguridad.usuarios');

        if ($id === (int) session('uid')) {
            flash('No podés desactivar tu propia cuenta.', 'warning');

            return $volver;
        }

        $u = DB::selectOne(
            'SELECT pe.nombre, pe.apellido, u.activo FROM usuario u
               JOIN persona pe ON pe.id_persona = u.id_persona WHERE u.id_usuario = ?', [$id]
        );
        if (! $u) {
            flash('Ese usuario no existe.', 'error');

            return $volver;
        }

        $daDeBaja = (int) $u->activo === 1;
        DB::update('UPDATE usuario SET activo = 1 - activo WHERE id_usuario = ?', [$id]);

        if ($daDeBaja) {
            // Al dar de baja a alguien que atiende, sus clientas se quedan sin
            // profesional: se les avisa con el enlace para reprogramar o
            // elegir a otro. Sin fechas, el aviso alcanza a todas las citas
            // futuras, que es exactamente lo que deja de poder atender.
            $pendientes = (int) DB::scalar(
                'SELECT COUNT(*) FROM cita c JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
                  WHERE c.id_usuario = ? AND ec.bloquea_agenda = 1 AND c.fecha_hora >= NOW()', [$id]
            );
            Auditoria::registrar('BAJA', 'Personal', 'usuario', $id,
                'Baja de ' . $u->nombre . ' ' . $u->apellido . " — quedaron $pendientes cita(s) pendiente(s)");

            $avisadas = Notificaciones::avisarProfesionalNoDisponible($id, null, null, 'baja del personal');

            flash('Estado del usuario actualizado.'
                . ($pendientes
                    ? " Ojo: quedan $pendientes cita(s) futura(s) con esa persona. Hay que reprogramarlas o "
                      . 'cambiarles el profesional.'
                    : '')
                . ($avisadas ? " Se le avisó a $avisadas clienta(s)." : ''),
                $pendientes ? 'warning' : 'success');

            return $volver;
        }

        flash('Estado del usuario actualizado.');

        return $volver;
    }

    // ---------- Turnos ----------

    public function turnos(Request $request): View
    {
        $gente = [];
        foreach (DB::select(
            "SELECT ut.id_turno, CONCAT(pe.nombre,' ',pe.apellido) AS nombre
               FROM usuario_turno ut
               JOIN usuario u  ON u.id_usuario = ut.id_usuario AND u.activo = 1
               JOIN persona pe ON pe.id_persona = u.id_persona
              ORDER BY pe.nombre, pe.apellido"
        ) as $g) {
            $gente[(int) $g->id_turno][] = $g->nombre;
        }

        $idEdit = (int) $request->query('editar', 0);
        $editar = $idEdit ? DB::selectOne('SELECT * FROM turno_laboral WHERE id_turno = ? AND activo = 1', [$idEdit]) : null;
        if ($editar) {
            $editar->dias = array_map(fn ($r) => (int) $r->dia_semana,
                DB::select('SELECT dia_semana FROM turno_dia WHERE id_turno = ?', [$idEdit]));
        }

        return view('seguridad.turnos', [
            'rows' => $this->turnosDisponibles(),
            'gente' => $gente,
            'sucursales' => DB::select('SELECT id_sucursal, nombre FROM sucursal WHERE activo = 1 ORDER BY nombre'),
            'dias' => self::DIAS,
            'editar' => $editar,
        ]);
    }

    public function turnoGuardar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_turno', 0);
        $d = [
            'id_sucursal' => (int) $request->input('id_sucursal', 0),
            'nombre' => trim((string) $request->input('nombre', '')),
            'hora_inicio' => substr(trim((string) $request->input('hora_inicio', '')), 0, 5),
            'hora_fin' => substr(trim((string) $request->input('hora_fin', '')), 0, 5),
        ];
        $dias = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('dias', [])),
            fn ($x) => $x >= 1 && $x <= 7
        )));
        $volver = redirect()->route('seguridad.turnos', $id ? ['editar' => $id] : []);

        if ($error = $this->turnoValidar($d, $dias, $id)) {
            flash($error, 'error');

            return $volver->withInput();
        }

        try {
            DB::transaction(function () use (&$id, $d, $dias) {
                if ($id) {
                    DB::update(
                        'UPDATE turno_laboral SET id_sucursal=:id_sucursal, nombre=:nombre,
                            hora_inicio=:hora_inicio, hora_fin=:hora_fin WHERE id_turno=:id',
                        $d + ['id' => $id]
                    );
                } else {
                    DB::insert(
                        'INSERT INTO turno_laboral (id_sucursal,nombre,hora_inicio,hora_fin,activo)
                         VALUES (:id_sucursal,:nombre,:hora_inicio,:hora_fin,1)', $d
                    );
                    $id = (int) DB::getPdo()->lastInsertId();
                }

                // Los días van uno por fila: nunca una lista adentro de una
                // columna. Así se pueden consultar e indexar.
                DB::delete('DELETE FROM turno_dia WHERE id_turno = ?', [$id]);
                foreach ($dias as $dia) {
                    DB::insert('INSERT IGNORE INTO turno_dia (id_turno,dia_semana) VALUES (?,?)', [$id, $dia]);
                }
            });

            Auditoria::registrar($request->input('id_turno') ? 'MODIFICACION' : 'ALTA', 'Personal', 'turno_laboral', $id,
                $d['nombre'] . ' ' . $d['hora_inicio'] . ' a ' . $d['hora_fin'] . ' · ' . $this->diasTexto($dias));

            flash('Turno «' . $d['nombre'] . '» guardado: ' . $d['hora_inicio'] . ' a ' . $d['hora_fin']
                . ', ' . mb_strtolower($this->diasTexto($dias)) . '. Asignáselo al personal desde su ficha.');
        } catch (Throwable) {
            flash('No se pudo guardar el turno.', 'error');

            return $volver->withInput();
        }

        return redirect()->route('seguridad.turnos');
    }

    public function turnoBaja(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_turno', 0);
        $t = DB::selectOne('SELECT id_turno, nombre FROM turno_laboral WHERE id_turno = ? AND activo = 1', [$id]);
        if (! $t) {
            flash('Ese turno no existe o ya fue dado de baja.', 'error');

            return redirect()->route('seguridad.turnos');
        }

        // Baja lógica: no se borra, porque la asistencia lo referencia
        $asignados = (int) DB::scalar('SELECT COUNT(*) FROM usuario_turno WHERE id_turno = ?', [$id]);
        DB::update('UPDATE turno_laboral SET activo = 0 WHERE id_turno = ?', [$id]);
        Auditoria::registrar('BAJA', 'Personal', 'turno_laboral', $id, 'Turno «' . $t->nombre . '» dado de baja');

        flash('Turno «' . $t->nombre . '» dado de baja.'
            . ($asignados ? " Lo trabajaban $asignados persona(s): revisá que les quede otro turno o no van a aparecer en la agenda." : ''),
            $asignados ? 'warning' : 'success');

        return redirect()->route('seguridad.turnos');
    }

    /** Alta rápida de turno desde la ficha del usuario o desde Asistencia. */
    public function turnoRapido(Request $request): RedirectResponse
    {
        $idUsuario = (int) $request->input('id_usuario', 0);
        // Igual que en la sucursal: la ficha a medio cargar vuelve con el
        // borrador, no se pierde por haber creado un turno.
        $destino = Borrador::conservar(
            $idUsuario
                ? redirect()->route('seguridad.usuario_form', $idUsuario)
                : redirect()->route('seguridad.usuario_form'),
            $request
        );

        $d = [
            'id_sucursal' => (int) $request->input('id_sucursal', 0),
            'nombre' => trim((string) $request->input('nombre', '')),
            'hora_inicio' => substr(trim((string) $request->input('hora_inicio', '')), 0, 5),
            'hora_fin' => substr(trim((string) $request->input('hora_fin', '')), 0, 5),
        ];
        $dias = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('dias', [])),
            fn ($x) => $x >= 1 && $x <= 7
        )));

        // Misma validación que la pantalla de Turnos: si se separaran, una
        // dejaría pasar lo que la otra rechaza.
        if ($error = $this->turnoValidar($d, $dias, 0)) {
            flash($error, 'error');

            return $destino;
        }

        try {
            $idTurno = DB::transaction(function () use ($d, $dias) {
                DB::insert(
                    'INSERT INTO turno_laboral (id_sucursal,nombre,hora_inicio,hora_fin,activo)
                     VALUES (:id_sucursal,:nombre,:hora_inicio,:hora_fin,1)', $d
                );
                $idTurno = (int) DB::getPdo()->lastInsertId();
                foreach ($dias as $dia) {
                    DB::insert('INSERT IGNORE INTO turno_dia (id_turno,dia_semana) VALUES (?,?)', [$idTurno, $dia]);
                }

                return $idTurno;
            });

            Auditoria::registrar('ALTA', 'Personal', 'turno_laboral', $idTurno,
                $d['nombre'] . ' ' . $d['hora_inicio'] . ' a ' . $d['hora_fin'] . ' · '
                . $this->diasTexto($dias) . ' (alta rápida)');

            flash('Turno «' . $d['nombre'] . '» creado: ' . $d['hora_inicio'] . ' a ' . $d['hora_fin']
                . ', ' . mb_strtolower($this->diasTexto($dias)) . '. Ya podés marcarlo acá abajo.');
        } catch (Throwable) {
            flash('No se pudo crear el turno.', 'error');
        }

        return $destino;
    }

    /** Alta rápida de sucursal desde la ficha del usuario. */
    public function sucursalRapida(Request $request): RedirectResponse
    {
        $idUsuario = (int) $request->input('id_usuario', 0);
        // Lo que la persona tenía cargado en la ficha viaja en `_borrador`: sin
        // esto, crear una sucursal le borraba nombre, apellido, usuario y email.
        $destino = Borrador::conservar(
            $idUsuario
                ? redirect()->route('seguridad.usuario_form', $idUsuario)
                : redirect()->route('seguridad.usuario_form'),
            $request
        );

        $nombre = trim((string) $request->input('nombre', ''));
        $ciudad = trim((string) $request->input('ciudad', '')) ?: null;

        if ($nombre === '') {
            flash('El nombre de la sucursal es obligatorio.', 'error');

            return $destino;
        }
        if (DB::scalar('SELECT COUNT(*) FROM sucursal WHERE nombre = ?', [$nombre])) {
            flash('Ya existe una sucursal con ese nombre.', 'error');

            return $destino;
        }

        try {
            DB::insert('INSERT INTO sucursal (nombre, ciudad, activo) VALUES (?,?,1)', [$nombre, $ciudad]);
            Auditoria::registrar('ALTA', 'Configuracion', 'sucursal', (int) DB::getPdo()->lastInsertId(), $nombre);
            flash('Sucursal «' . $nombre . '» creada. Ya podés asignarla.');
        } catch (Throwable) {
            flash('No se pudo crear la sucursal.', 'error');
        }

        return $destino;
    }

    // ---------- Comisiones ----------

    public function comisiones(): View
    {
        return view('seguridad.comisiones', [
            'rows' => DB::select(
                "SELECT c.*, CONCAT(pe_u.nombre,' ',pe_u.apellido) AS profesional,
                        COALESCE(s.nombre,'Todos los servicios') AS servicio
                   FROM comision c
                   JOIN usuario u  ON u.id_usuario = c.id_usuario
                   JOIN persona pe_u ON pe_u.id_persona = u.id_persona
                   LEFT JOIN servicio s ON s.id_servicio = c.id_servicio
                  WHERE c.activo = 1 ORDER BY pe_u.nombre, c.vigente_desde DESC"
            ),
        ]);
    }

    public function comisionForm(): View
    {
        return view('seguridad.comision_form', [
            'profs' => DB::select(
                "SELECT u.id_usuario, CONCAT(pe.nombre,' ',pe.apellido) AS nombre
                   FROM usuario u JOIN persona pe ON pe.id_persona = u.id_persona
                   JOIN rol r ON r.id_rol = u.id_rol
                  WHERE u.activo = 1 AND r.es_personal = 1 ORDER BY pe.nombre, pe.apellido"
            ),
            'servicios' => DB::select('SELECT id_servicio, nombre FROM servicio WHERE activo = 1 ORDER BY nombre'),
        ]);
    }

    public function comisionGuardar(Request $request): RedirectResponse
    {
        $d = [
            'id_usuario' => (int) $request->input('id_usuario', 0),
            'id_servicio' => ((int) $request->input('id_servicio', 0)) ?: null,   // NULL = todos
            'tipo' => (string) $request->input('tipo', 'PORCENTAJE'),
            'valor' => num($request->input('valor')),
            'vigente_desde' => (string) $request->input('vigente_desde', date('Y-m-d')) ?: date('Y-m-d'),
        ];
        $volver = redirect()->route('seguridad.comision_form');

        $error = null;
        if (! $d['id_usuario'] || ! DB::scalar(
            'SELECT COUNT(*) FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.id_usuario = ? AND u.activo = 1 AND r.es_personal = 1', [$d['id_usuario']]
        )) {
            $error = 'Elegí un profesional activo.';
        } elseif (! in_array($d['tipo'], ['PORCENTAJE', 'MONTO'], true)) {
            $error = 'Elegí si la comisión es un porcentaje o un monto fijo.';
        } elseif ($d['valor'] <= 0) {
            $error = 'El valor de la comisión tiene que ser mayor a cero.';
        } elseif ($d['tipo'] === 'PORCENTAJE' && $d['valor'] > 100) {
            $error = 'Una comisión en porcentaje no puede superar el 100%.';
        } elseif ($d['id_servicio'] && ! DB::scalar('SELECT COUNT(*) FROM servicio WHERE id_servicio = ?', [$d['id_servicio']])) {
            $error = 'Ese servicio no existe.';
        } elseif (! strtotime($d['vigente_desde'])) {
            $error = 'La fecha de vigencia no es válida.';
        }
        if ($error) {
            flash($error, 'error');

            return $volver->withInput();
        }

        try {
            DB::insert(
                'INSERT INTO comision (id_usuario,id_servicio,tipo,valor,vigente_desde)
                 VALUES (:id_usuario,:id_servicio,:tipo,:valor,:vigente_desde)', $d
            );
            Auditoria::registrar('ALTA', 'Personal', 'comision', (int) DB::getPdo()->lastInsertId(),
                'Comisión ' . $d['tipo'] . ' ' . $d['valor']);
            flash('Comisión registrada.');
        } catch (Throwable) {
            flash('Ya existe una comisión para ese profesional/servicio en esa fecha.', 'error');

            return $volver->withInput();
        }

        return redirect()->route('seguridad.comisiones');
    }

    // ---------- Asistencia ----------
    //
    //  La pantalla es el listado de quiénes trabajan ese día, sacado del turno
    //  que cada uno tiene asignado. No se escriben horarios a mano: se ficha con
    //  un botón y queda la hora del clic.

    public function asistencia(Request $request): View
    {
        // La fecha y la hora salen del reloj de la base, no del de PHP
        $fecha = (string) $request->query('fecha', ahora_bd('Y-m-d'));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || ! strtotime($fecha)) {
            $fecha = ahora_bd('Y-m-d');
        }
        $dia = (int) date('N', strtotime($fecha));

        $filas = DB::select(
            "SELECT ut.id_usuario, t.id_turno, t.nombre AS turno, t.hora_inicio, t.hora_fin,
                    s.nombre AS sucursal,
                    CONCAT(pe.nombre,' ',pe.apellido) AS profesional,
                    a.id_asistencia, a.hora_entrada, a.hora_salida, a.motivo_ausencia,
                    a.justificada, a.horas_extras, a.observaciones
               FROM usuario_turno ut
               JOIN turno_laboral t ON t.id_turno = ut.id_turno AND t.activo = 1
               JOIN turno_dia td    ON td.id_turno = t.id_turno AND td.dia_semana = :dia
               JOIN usuario u       ON u.id_usuario = ut.id_usuario AND u.activo = 1
               JOIN persona pe      ON pe.id_persona = u.id_persona
               JOIN sucursal s      ON s.id_sucursal = t.id_sucursal
               LEFT JOIN asistencia a ON a.id_usuario = ut.id_usuario
                                     AND a.id_turno   = t.id_turno
                                     AND a.fecha      = :fecha
              ORDER BY t.hora_inicio, pe.nombre, pe.apellido",
            ['dia' => $dia, 'fecha' => $fecha]
        );

        // Un Profesional solo se ve a sí mismo: la asistencia de sus compañeras
        // no es asunto suyo.
        $porOtros = $this->registraPorOtros();
        if (! $porOtros) {
            $filas = array_values(array_filter($filas, fn ($f) => (int) $f->id_usuario === (int) session('uid')));
        }

        return view('seguridad.asistencia', [
            'filas' => $filas,
            'rows' => DB::select(
                "SELECT a.*, t.nombre AS turno, t.hora_inicio, t.hora_fin,
                        CONCAT(pe.nombre,' ',pe.apellido) AS profesional
                   FROM asistencia a
                   JOIN turno_laboral t ON t.id_turno = a.id_turno
                   JOIN usuario u       ON u.id_usuario = a.id_usuario
                   JOIN persona pe      ON pe.id_persona = u.id_persona
                  " . ($porOtros ? '' : 'WHERE a.id_usuario = ' . (int) session('uid')) . '
                  ORDER BY a.fecha DESC, t.hora_inicio DESC LIMIT 60'
            ),
            'fecha' => $fecha,
            'dia' => $dia,
            'dias' => self::DIAS,
            'porOtros' => $porOtros,
            'yo' => (int) session('uid'),
            'hoy' => ahora_bd('Y-m-d'),
            'ahora' => ahora_bd('H:i:s'),
        ]);
    }

    /**
     * Ficha entrada, salida o falta.
     *
     * La hora nunca viene del formulario: es la del momento del clic, que es de
     * lo que se trata fichar.
     */
    public function asistenciaMarcar(Request $request): RedirectResponse
    {
        $accion = (string) $request->input('accion', '');
        $idTurno = (int) $request->input('id_turno', 0);
        $idQuien = (int) $request->input('id_usuario', 0);
        $fecha = (string) $request->input('fecha', ahora_bd('Y-m-d'));
        $motivo = trim((string) $request->input('motivo_ausencia', '')) ?: null;

        // Se puede fichar desde la pantalla de atención, que es donde se nota
        // que falta. En ese caso se vuelve ahí y no a Asistencia: el trabajo a
        // medio cargar estaba allá. Va el ID de la cita y la ruta la arma el
        // servidor — una URL de vuelta que venga del formulario sería un
        // redirect abierto.
        $volverCita = (int) $request->input('volver_cita', 0);
        $volver = $volverCita
            ? redirect()->route('citas.atender', ['id' => $volverCita])
            : redirect()->route('seguridad.asistencia', ['fecha' => $fecha]);

        $error = null;
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || ! strtotime($fecha)) {
            $error = 'La fecha no es válida.';
            $volver = redirect()->route('seguridad.asistencia');
        } elseif (! in_array($accion, ['entrada', 'salida', 'falta_con', 'falta_sin', 'limpiar'], true)) {
            $error = 'Acción no válida.';
        } elseif (! $idQuien || ! $idTurno) {
            $error = 'Elegí a quién y a qué turno corresponde.';
        } elseif ($idQuien !== (int) session('uid') && ! $this->registraPorOtros()) {
            $error = 'Solo podés registrar tu propia asistencia.';
        } elseif ($fecha > ahora_bd('Y-m-d')) {
            $error = 'No se puede registrar asistencia de un día que todavía no llegó.';
        }

        // Que la persona realmente trabaje ese turno ese día de la semana
        $trabaja = null;
        if (! $error) {
            $trabaja = DB::selectOne(
                'SELECT t.nombre, t.hora_inicio, t.hora_fin
                   FROM usuario_turno ut
                   JOIN turno_laboral t ON t.id_turno = ut.id_turno AND t.activo = 1
                   JOIN turno_dia td    ON td.id_turno = t.id_turno AND td.dia_semana = ?
                  WHERE ut.id_usuario = ? AND ut.id_turno = ?',
                [(int) date('N', strtotime($fecha)), $idQuien, $idTurno]
            );
            if (! $trabaja) {
                $error = 'Esa persona no trabaja ese turno ese día.';
            }
        }

        // La franja del turno, del lado del servidor. La pantalla ya deshabilita
        // el botón fuera de hora, pero eso es una ayuda visual: un POST armado a
        // mano la saltea y deja fichada una entrada a cualquier hora, que es
        // justo lo que el fichaje tiene que impedir.
        // Corregir un día pasado NO es fichar, así que la hora no puede salir
        // del reloj: quedaría registrado que entró a las 15:06 de un día en el
        // que nadie apretó nada. La pide, y tiene que caer dentro del turno.
        $horaPedida = trim((string) $request->input('hora', ''));
        $esCorreccion = $fecha < ahora_bd('Y-m-d');

        if (! $error && $trabaja && in_array($accion, ['entrada', 'salida'], true)) {
            if ($fecha === ahora_bd('Y-m-d')) {
                $error = $this->fueraDeFranja($trabaja);
            } elseif (! $this->registraPorOtros()) {
                // Fichar un día pasado no es fichar: es corregir la planilla.
                $error = 'No podés fichar una fecha que ya pasó. Pedile a quien administra '
                       . 'los turnos que corrija tu asistencia del ' . fecha($fecha, 'd/m/Y') . '.';
            } elseif ($horaPedida === '') {
                $error = 'Para corregir la planilla del ' . fecha($fecha, 'd/m/Y')
                       . ' indicá a qué hora fue: la del reloj de ahora no sirve, es de otro día.';
            } elseif (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $horaPedida)) {
                $error = 'La hora tiene que ir en formato HH:MM.';
            } elseif ($horaPedida . ':00' < (string) $trabaja->hora_inicio
                   || $horaPedida . ':00' > (string) $trabaja->hora_fin) {
                $error = 'Esa hora queda fuera del turno «' . $trabaja->nombre . '» ('
                       . substr((string) $trabaja->hora_inicio, 0, 5) . ' a '
                       . substr((string) $trabaja->hora_fin, 0, 5) . ').';
            }
        }

        if ($error) {
            flash($error, 'error');

            return $volver;
        }

        $quien = (string) DB::scalar(
            "SELECT CONCAT(pe.nombre,' ',pe.apellido) FROM usuario u
               JOIN persona pe ON pe.id_persona = u.id_persona WHERE u.id_usuario = ?", [$idQuien]);
        $ya = DB::selectOne('SELECT * FROM asistencia WHERE id_usuario = ? AND id_turno = ? AND fecha = ?',
            [$idQuien, $idTurno, $fecha]);
        // Hoy: la hora del clic. Un día pasado: la que indicó quien corrige —
        // el reloj de ahora pertenece a otro día y falsearía la planilla.
        $ahora = $esCorreccion && $horaPedida !== '' ? $horaPedida . ':00' : ahora_bd('H:i:s');

        try {
            if ($accion === 'limpiar') {
                if (! $ya) {
                    flash('No había nada registrado.', 'info');

                    return $volver;
                }
                DB::delete('DELETE FROM asistencia WHERE id_asistencia = ?', [(int) $ya->id_asistencia]);
                Auditoria::registrar('BAJA', 'Personal', 'asistencia', (int) $ya->id_asistencia,
                    'Se borró la asistencia de ' . $quien . ' del ' . $fecha);
                flash('Se borró el registro de ' . $quien . ' para ese turno.');

                return $volver;
            }

            if ($accion === 'entrada') {
                if ($ya && $ya->hora_entrada) {
                    flash($quien . ' ya tenía la entrada marcada a las ' . substr((string) $ya->hora_entrada, 0, 5) . '.', 'warning');

                    return $volver;
                }
                $this->asistenciaGuardar($idQuien, $idTurno, $fecha, [
                    'hora_entrada' => $ahora, 'motivo_ausencia' => null, 'justificada' => null,
                ]);
                flash('Entrada de ' . $quien . ' registrada a las ' . substr($ahora, 0, 5) . '.');
            } elseif ($accion === 'salida') {
                if (! $ya || ! $ya->hora_entrada) {
                    flash('Primero marcá la entrada de ' . $quien . '.', 'warning');

                    return $volver;
                }
                if ($ya->hora_salida) {
                    flash($quien . ' ya tenía la salida marcada a las ' . substr((string) $ya->hora_salida, 0, 5) . '.', 'warning');

                    return $volver;
                }
                // Vale para hoy y para una corrección: una salida anterior a la
                // entrada no es un caso posible en ninguno de los dos.
                if ($ahora <= (string) $ya->hora_entrada) {
                    flash('La salida no puede ser anterior a la entrada ('
                        . substr((string) $ya->hora_entrada, 0, 5) . ').', 'error');

                    return $volver;
                }

                // Lo trabajado de más respecto del turno queda como horas extras
                $extras = 0.0;
                if ($ahora > (string) $trabaja->hora_fin) {
                    $extras = round((strtotime($ahora) - strtotime((string) $trabaja->hora_fin)) / 3600, 2);
                    $extras = max(0.0, min(12.0, $extras));
                }

                $this->asistenciaGuardar($idQuien, $idTurno, $fecha, [
                    'hora_salida' => $ahora, 'horas_extras' => $extras,
                ]);
                flash('Salida de ' . $quien . ' registrada a las ' . substr($ahora, 0, 5) . '.'
                    . ($extras > 0 ? ' Se le contaron ' . cant($extras) . ' hora(s) extra.' : ''));
            } else {
                $justificada = $accion === 'falta_con' ? 1 : 0;
                if ($justificada && ! $motivo) {
                    flash('Escribí el motivo del permiso: es lo que justifica la falta.', 'error');

                    return $volver;
                }
                $this->asistenciaGuardar($idQuien, $idTurno, $fecha, [
                    'hora_entrada' => null, 'hora_salida' => null, 'horas_extras' => 0,
                    'justificada' => $justificada,
                    'motivo_ausencia' => $motivo ?: ($justificada ? 'Permiso' : 'Sin aviso'),
                ]);
                Auditoria::registrar('AUSENCIA', 'Personal', 'asistencia', $idQuien,
                    $quien . ' ausente el ' . $fecha . ' — ' . ($justificada ? 'con permiso' : 'sin permiso')
                    . ($motivo ? ': ' . $motivo : ''));
                flash('Se marcó a ' . $quien . ' como ausente ' . ($justificada ? 'con permiso.' : 'sin permiso.'), 'warning');
            }
        } catch (Throwable) {
            flash('No se pudo registrar la asistencia.', 'error');
        }

        return $volver;
    }

    // -----------------------------------------------------------------

    /**
     * ¿Quién puede fichar por otro? Marcar la entrada de una compañera es decir
     * que llegó, así que eso queda para quien administra los turnos. El
     * Profesional solo ficha lo suyo.
     */
    private function registraPorOtros(): bool
    {
        return Permisos::esAdmin() || Permisos::puede('seguridad.turnos');
    }

    /**
     * ¿Está fuera de la franja en que se puede fichar? Devuelve el mensaje o null.
     *
     * Se trabaja en minutos desde la medianoche y NO con texto: la franja puede
     * cruzar las 12 de la noche (un turno de 20:00 a 02:00), y ahí la
     * comparación de cadenas da cualquier cosa.
     */
    private function fueraDeFranja(object $turno): ?string
    {
        $enMinutos = fn (string $hms): int => (int) substr($hms, 0, 2) * 60 + (int) substr($hms, 3, 2);

        $ini = $enMinutos((string) $turno->hora_inicio);
        $fin = $enMinutos((string) $turno->hora_fin);
        if ($fin <= $ini) {
            $fin += 1440;   // el turno termina al día siguiente
        }

        $desde = $ini - (int) config('spg.fichaje.gracia_antes_min', 60);
        $hasta = $fin + (int) config('spg.fichaje.gracia_despues_min', 120);
        $ahoraM = $enMinutos(ahora_bd('H:i:s'));

        if ($hasta - $desde >= 1440) {
            return null;   // la franja cubre el día entero
        }

        // Se prueba el mismo instante hoy y mañana, para cubrir el caso en que
        // la ventana arrancó ayer.
        $dentro = ($ahoraM >= $desde && $ahoraM <= $hasta)
               || ($ahoraM + 1440 >= $desde && $ahoraM + 1440 <= $hasta);
        if ($dentro) {
            return null;
        }

        $reloj = fn (int $min): string => sprintf('%02d:%02d',
            intdiv(($min + 1440) % 1440, 60), (($min + 1440) % 1440) % 60);

        return 'El turno ' . $turno->nombre . ' va de '
            . substr((string) $turno->hora_inicio, 0, 5) . ' a ' . substr((string) $turno->hora_fin, 0, 5)
            . ', y son las ' . $reloj($ahoraM) . '. El fichaje se habilita desde las '
            . $reloj($desde) . ' hasta las ' . $reloj($hasta) . '.';
    }

    /**
     * Crea o completa la fila de (persona, turno, día). Solo toca las columnas
     * que se le pasan: fichar la salida no borra la entrada.
     */
    private function asistenciaGuardar(int $idUsuario, int $idTurno, string $fecha, array $campos): void
    {
        $existe = DB::scalar('SELECT id_asistencia FROM asistencia WHERE id_usuario = ? AND id_turno = ? AND fecha = ?',
            [$idUsuario, $idTurno, $fecha]);
        $campos['id_usuario_registro'] = (int) session('uid');

        if ($existe) {
            $sets = implode(', ', array_map(fn ($c) => "`$c` = :$c", array_keys($campos)));
            DB::update("UPDATE asistencia SET $sets WHERE id_asistencia = :id", $campos + ['id' => (int) $existe]);

            return;
        }

        $campos += ['id_usuario' => $idUsuario, 'id_turno' => $idTurno, 'fecha' => $fecha];
        $cols = implode(',', array_map(fn ($c) => "`$c`", array_keys($campos)));
        $vals = implode(',', array_map(fn ($c) => ":$c", array_keys($campos)));
        DB::insert("INSERT INTO asistencia ($cols) VALUES ($vals)", $campos);
    }

    /** Validación del turno, compartida por la pantalla y el alta rápida. */
    private function turnoValidar(array $d, array $dias, int $id): ?string
    {
        if ($d['nombre'] === '' || $d['hora_inicio'] === '' || $d['hora_fin'] === '') {
            return 'Completá el nombre del turno y el horario.';
        }
        if (mb_strlen($d['nombre']) > 60) {
            return 'El nombre del turno no puede superar los 60 caracteres.';
        }
        if (! preg_match('/^\d{2}:\d{2}$/', $d['hora_inicio']) || ! preg_match('/^\d{2}:\d{2}$/', $d['hora_fin'])) {
            return 'El horario no es válido.';
        }
        if ($d['hora_fin'] <= $d['hora_inicio']) {
            return 'La hora de fin tiene que ser posterior a la de inicio.';
        }
        if (! $dias) {
            return 'Marcá al menos un día de la semana: el turno son los días en que se trabaja.';
        }
        if (! $d['id_sucursal'] || ! DB::scalar('SELECT COUNT(*) FROM sucursal WHERE id_sucursal = ? AND activo = 1', [$d['id_sucursal']])) {
            return 'Elegí una sucursal activa.';
        }
        if (DB::scalar('SELECT COUNT(*) FROM turno_laboral WHERE id_sucursal = ? AND nombre = ? AND id_turno <> ?',
            [$d['id_sucursal'], $d['nombre'], $id])) {
            return 'Ya existe un turno con ese nombre en esa sucursal.';
        }

        // Dos turnos de la misma sucursal no pueden pisarse el horario en un
        // mismo día: si «Mañana» va de 08:00 a 12:00 y alguien carga otro de
        // 11:00 a 15:00 para el lunes, ese lunes hay dos turnos activos a las
        // 11:30 y la agenda no sabe cuál vale.
        $in = implode(',', array_fill(0, count($dias), '?'));
        $choque = DB::selectOne(
            "SELECT t.nombre, td.dia_semana
               FROM turno_laboral t
               JOIN turno_dia td ON td.id_turno = t.id_turno
              WHERE t.activo = 1 AND t.id_sucursal = ? AND t.id_turno <> ?
                AND td.dia_semana IN ($in)
                AND t.hora_inicio < ? AND ? < t.hora_fin
              ORDER BY td.dia_semana LIMIT 1",
            array_merge([$d['id_sucursal'], $id], $dias, [$d['hora_fin'], $d['hora_inicio']])
        );
        if ($choque) {
            return 'Se pisa con el turno «' . $choque->nombre . '» el '
                . mb_strtolower(self::DIAS[(int) $choque->dia_semana] ?? '')
                . '. Cambiá el horario o sacá ese día.';
        }

        if ($id && ! DB::scalar('SELECT COUNT(*) FROM turno_laboral WHERE id_turno = ? AND activo = 1', [$id])) {
            return 'Ese turno no existe o fue dado de baja.';
        }

        return null;
    }

    /** Turnos activos con sus días y cuánta gente los trabaja. */
    private function turnosDisponibles(): array
    {
        $turnos = DB::select(
            'SELECT t.id_turno, t.nombre, t.hora_inicio, t.hora_fin, t.id_sucursal, s.nombre AS sucursal,
                    (SELECT COUNT(*) FROM usuario_turno ut WHERE ut.id_turno = t.id_turno) AS asignados
               FROM turno_laboral t
               JOIN sucursal s ON s.id_sucursal = t.id_sucursal
              WHERE t.activo = 1 ORDER BY t.hora_inicio, t.nombre'
        );

        $dias = [];
        foreach (DB::select('SELECT id_turno, dia_semana FROM turno_dia ORDER BY dia_semana') as $d) {
            $dias[(int) $d->id_turno][] = (int) $d->dia_semana;
        }

        foreach ($turnos as $t) {
            $t->dias = $dias[(int) $t->id_turno] ?? [];
            $t->dias_texto = $this->diasTexto($t->dias);
        }

        return $turnos;
    }

    /** «Lunes a sábado» si son seguidos, si no la lista corta. */
    private function diasTexto(array $dias): string
    {
        if (! $dias) {
            return 'sin días';
        }
        sort($dias);
        $seguidos = $dias === range($dias[0], end($dias));
        if ($seguidos && count($dias) > 2) {
            return self::DIAS[$dias[0]] . ' a ' . mb_strtolower(self::DIAS[end($dias)]);
        }

        return implode(', ', array_map(fn ($d) => self::DIAS[$d] ?? '', $dias));
    }

    private function rolesPersonal(): array
    {
        $out = [];
        foreach (DB::select('SELECT id_rol, nombre FROM rol WHERE es_personal = 1 ORDER BY id_rol') as $r) {
            $out[(string) $r->id_rol] = $r->nombre;
        }

        return $out;
    }

    private function turnosOpciones(): array
    {
        $out = [];
        foreach (DB::select('SELECT id_turno, nombre FROM turno_laboral WHERE activo = 1 ORDER BY hora_inicio') as $t) {
            $out[(string) $t->id_turno] = $t->nombre;
        }

        return $out;
    }
}
