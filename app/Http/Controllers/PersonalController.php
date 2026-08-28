<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Sucursales;
use App\Servicios\Auditoria;
use App\Servicios\Asistencia;
use App\Servicios\Borrador;
use App\Servicios\Listado;
use App\Servicios\Notificaciones;
use App\Servicios\Permisos;
use App\Servicios\Persona;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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

    // =================================================================
    //  Profesionales: la PERSONA, no la cuenta
    // =================================================================

    /**
     * Quiénes trabajan en el salón.
     *
     * **Es la persona, no el usuario, y esa es toda la diferencia.** Hasta la
     * 7.68.0 «Profesionales» abría la ficha de usuario, así que para cargar a
     * alguien había que inventarle una cuenta de sistema — y hay gente que
     * atiende y no entra al sistema nunca.
     *
     * Acá se cargan los datos de `persona`; la cuenta se crea después, desde
     * **Seguridad → Usuarios**, eligiendo a esta persona.
     */
    public function profesionales(): View|StreamedResponse
    {
        $f = Listado::filtros([
            'q' => ['tipo' => 'texto', 'etiqueta' => 'Buscar', 'ph' => 'Nombre, cédula o teléfono', 'ancho' => '250px'],
            'cuenta' => ['tipo' => 'select', 'etiqueta' => 'Cuenta', 'ancho' => '190px',
                         'opciones' => ['' => 'Todas', '1' => 'Con cuenta', '0' => 'Sin cuenta']],
        ]);
        $f['csv'] = true;

        // Quién es «del personal»: quien tiene cuenta con rol de personal, o
        // quien fue cargado acá y todavía no la tiene. Lo segundo se sabe
        // porque no es cliente ni proveedor — no hace falta columna nueva.
        $w = ["(EXISTS (SELECT 1 FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
                         WHERE u.id_persona = pe.id_persona AND r.es_personal = 1)
               OR (NOT EXISTS (SELECT 1 FROM cliente c WHERE c.id_persona = pe.id_persona)
               AND NOT EXISTS (SELECT 1 FROM proveedor pr WHERE pr.id_persona = pe.id_persona)
               AND NOT EXISTS (SELECT 1 FROM usuario u2 WHERE u2.id_persona = pe.id_persona)
               AND pe.es_personal = 1))"];
        $par = [];

        if (Listado::hay($f, 'q')) {
            $w[] = Listado::likeVarias(["CONCAT(pe.nombre,' ',COALESCE(pe.apellido,''))", 'pe.cedula', 'pe.telefono', 'pe.email'],
                Listado::valor($f, 'q'), 'q', $par);
        }
        if (Listado::hay($f, 'cuenta')) {
            $w[] = (Listado::valor($f, 'cuenta') === '1' ? '' : 'NOT ')
                . 'EXISTS (SELECT 1 FROM usuario u3 WHERE u3.id_persona = pe.id_persona)';
        }

        $desde = 'FROM persona pe WHERE ' . implode(' AND ', $w);
        $cols = "pe.id_persona, pe.nombre, pe.apellido, pe.cedula, pe.telefono, pe.email, pe.direccion,
                 (SELECT u.id_usuario FROM usuario u WHERE u.id_persona = pe.id_persona LIMIT 1) AS id_usuario,
                 (SELECT u.username FROM usuario u WHERE u.id_persona = pe.id_persona LIMIT 1) AS username,
                 (SELECT r.nombre FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
                   WHERE u.id_persona = pe.id_persona LIMIT 1) AS rol";
        $orden = 'ORDER BY pe.nombre, pe.apellido';

        if (Listado::pideExport()) {
            return Listado::exportar('profesionales',
                ['Nombre', 'Cédula', 'Teléfono', 'Email', 'Usuario'],
                array_map(fn ($r) => [trim($r->nombre . ' ' . $r->apellido), $r->cedula,
                    $r->telefono, $r->email, $r->username ?: 'sin cuenta'],
                    DB::select("SELECT $cols $desde $orden", $par)),
                $f, 'Profesionales'
            );
        }

        $pag = Listado::paginacion((int) DB::scalar("SELECT COUNT(*) $desde", $par));

        return view('seguridad.profesionales', [
            'rows' => DB::select("SELECT $cols $desde $orden LIMIT {$pag['porPagina']} OFFSET {$pag['offset']}", $par),
            'f' => $f,
            'pag' => $pag,
        ]);
    }

    public function profesionalForm(?int $id = null): View
    {
        return view('seguridad.profesional_form', [
            'id' => (int) $id,
            'p' => $id ? DB::selectOne('SELECT * FROM persona WHERE id_persona = ?', [$id]) : null,
            // **Qué servicios hace es de la PERSONA, no de su cuenta.** Una
            // manicurista que no entra a la computadora hace manicura igual,
            // así que el dato se carga acá y no en la ficha de usuario.
            'servicios' => DB::select('SELECT id_servicio, nombre FROM servicio WHERE activo = 1 ORDER BY nombre'),
            'misServicios' => $id ? array_map(fn ($r) => (int) $r->id_servicio,
                DB::select('SELECT id_servicio FROM persona_servicio WHERE id_persona = ?', [$id])) : [],
            'cuenta' => $id ? DB::selectOne(
                'SELECT u.id_usuario, u.username, u.activo, r.nombre AS rol
                   FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
                  WHERE u.id_persona = ? LIMIT 1', [$id]
            ) : null,
        ]);
    }

    public function profesionalGuardar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_persona');
        $d = [
            'nombre' => trim((string) $request->input('nombre', '')),
            'apellido' => trim((string) $request->input('apellido', '')),
            'cedula' => trim((string) $request->input('cedula', '')) ?: null,
            'telefono' => trim((string) $request->input('telefono', '')) ?: null,
            'email' => trim((string) $request->input('email', '')) ?: null,
            'direccion' => trim((string) $request->input('direccion', '')) ?: null,
            'fecha_nacimiento' => trim((string) $request->input('fecha_nacimiento', '')) ?: null,
        ];

        $error = match (true) {
            $d['nombre'] === '' || $d['apellido'] === '' => 'El nombre y el apellido son obligatorios.',
            default => Persona::error($d),
        };

        // La cédula es única a nivel de PERSONA, así que hay que avisarlo antes
        // de chocar contra el índice con un error feo.
        if (! $error && $d['cedula']) {
            $otra = Persona::porDocumento($d['cedula'], null, $id);
            if ($otra) {
                $error = 'Ya hay otra persona cargada con esa cédula.';
            }
        }

        if ($error) {
            flash($error, 'error');

            return back()->withInput();
        }

        $srvs = array_values(array_unique(array_map('intval', (array) $request->input('servicios', []))));

        $idPersona = DB::transaction(function () use ($id, $d, $srvs) {
            $idPersona = Persona::guardar($id ?: null, $d);

            // **Sin cuenta hay que poder distinguirla de una clienta.** `persona`
            // no dice a qué se dedica nadie, y una fila suelta —sin usuario, sin
            // cliente y sin proveedor— podría ser cualquier cosa. La marca es lo
            // único que la hace aparecer en esta lista.
            DB::update('UPDATE persona SET es_personal = 1 WHERE id_persona = ?', [$idPersona]);

            // Se reescribe entero: es una lista de casillas, así que lo que no
            // vino es lo que se destildó. **Ninguna marcada = hace todos**, que
            // es el criterio permisivo de `fn_usuario_hace_servicio`.
            DB::delete('DELETE FROM persona_servicio WHERE id_persona = ?', [$idPersona]);
            foreach ($srvs as $sv) {
                if ($sv > 0 && DB::scalar('SELECT COUNT(*) FROM servicio WHERE id_servicio = ?', [$sv])) {
                    DB::insert('INSERT IGNORE INTO persona_servicio (id_persona,id_servicio) VALUES (?,?)',
                        [$idPersona, $sv]);
                }
            }

            return $idPersona;
        });

        Auditoria::registrar($id ? 'EDICION' : 'ALTA', 'Personal', 'persona', $idPersona,
            trim($d['nombre'] . ' ' . $d['apellido']));

        flash($id ? 'Datos actualizados.' : 'Profesional cargado. Si va a entrar al sistema, creale la cuenta desde Seguridad → Usuarios.');

        return redirect()->route('seguridad.profesionales');
    }

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
                   WHERE ut.id_usuario = u.id_usuario) AS turnos,
                 -- **Lo que cada lista necesita, y no lo mismo para las dos.**
                 -- Usuarios pregunta «¿quién entra al sistema y con qué rol?»;
                 -- Profesionales, «¿quién trabaja y qué hace?». Con una sola
                 -- tabla de columnas mezcladas, ninguna de las dos preguntas se
                 -- contesta de un vistazo.
                 (SELECT GROUP_CONCAT(sv.nombre ORDER BY sv.nombre SEPARATOR ' · ')
                    FROM persona_servicio ps JOIN servicio sv ON sv.id_servicio = ps.id_servicio AND sv.activo = 1
                   WHERE ps.id_persona = u.id_persona) AS servicios,
                 (SELECT GROUP_CONCAT(su.nombre ORDER BY su.nombre SEPARATOR ' · ')
                    FROM usuario_sucursal usu JOIN sucursal su ON su.id_sucursal = usu.id_sucursal AND su.activo = 1
                   WHERE usu.id_usuario = u.id_usuario) AS sucursales";
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

    public function usuarioForm(Request $request, int $id = 0): View|RedirectResponse
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

        // **La cuenta se le crea a una persona que ya está cargada.** El
        // formulario dejó de pedir nombre y apellido: eso vive en `persona` y
        // se carga en Personal → Profesionales. Repetirlo acá era pedir dos
        // veces el mismo dato y arriesgarse a que quedaran distintos.
        //
        // Se ofrecen las personas del personal que TODAVÍA no tienen cuenta,
        // más la de esta ficha si se está editando.
        $personas = DB::select(
            "SELECT pe.id_persona, TRIM(CONCAT(pe.nombre,' ',COALESCE(pe.apellido,''))) AS nombre, pe.cedula
               FROM persona pe
              WHERE (pe.id_persona = ?
                     OR (pe.es_personal = 1
                         AND NOT EXISTS (SELECT 1 FROM usuario u WHERE u.id_persona = pe.id_persona)))
              ORDER BY pe.nombre, pe.apellido",
            [$u->id_persona ?? 0]
        );

        return view('seguridad.usuario_form', [
            'u' => $u,
            'personas' => $personas,
            // Se llega acá desde «crearle una cuenta» de Profesionales, con la
            // persona ya elegida: si no, hay que buscarla de nuevo en el combo.
            'personaSug' => (int) $request->query('persona', 0),
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
            // `id_sucursal` ya NO se pregunta: sale de la primera sucursal
            // marcada, más abajo. Ver el comentario del formulario.
            'id_sucursal' => null,
            'username' => trim((string) $request->input('username', '')),
        ];

        // **La persona se ELIGE, no se tipea.** Sus datos viven en `persona` y
        // se cargan en Personal -> Profesionales; pedirlos otra vez acá era
        // pedir dos veces el mismo dato y arriesgarse a que quedaran distintos.
        $idPersona = (int) $request->input('id_persona', 0);
        $per = $idPersona
            ? DB::selectOne('SELECT * FROM persona WHERE id_persona = ?', [$idPersona])
            : null;
        $pass = (string) $request->input('password', '');
        $sucs = array_values(array_unique(array_map('intval', (array) $request->input('sucursales', []))));
        $turnos = array_values(array_unique(array_map('intval', (array) $request->input('turnos', []))));
        $volver = $id ? redirect()->route('seguridad.usuario_form', $id) : redirect()->route('seguridad.usuario_form');

        $error = null;
        if (! $per) {
            $error = 'Elegí a quién le estás creando la cuenta. Si no está en la lista, cargala primero en Personal, Profesionales.';
        } elseif ($d['username'] === '') {
            $error = 'El nombre de usuario es obligatorio.';
        } elseif (! preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $d['username'])) {
            $error = 'El nombre de usuario debe tener entre 3 y 60 caracteres (letras, números, punto, guion o guion bajo).';
        } elseif (! $d['id_rol'] || ! DB::scalar('SELECT COUNT(*) FROM rol WHERE id_rol = ? AND es_personal = 1 AND activo = 1', [$d['id_rol']])) {
            $error = 'Elegí un rol de personal válido.';
        } elseif ($pass !== '' && strlen($pass) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif (! $id && $pass === '') {
            $error = 'La contraseña es obligatoria para un usuario nuevo.';
        } elseif (DB::scalar('SELECT COUNT(*) FROM usuario WHERE username = ? AND id_usuario <> ?', [$d['username'], $id])) {
            $error = 'Ese nombre de usuario ya está en uso.';
        } elseif (DB::scalar('SELECT COUNT(*) FROM usuario WHERE id_persona = ? AND id_usuario <> ?',
                             [$per->id_persona, $id])) {
            $error = 'Esa persona ya tiene una cuenta en el sistema.';
        } elseif ($id === (int) session('uid') && $d['id_rol'] !== (int) config('permisos.rol_admin', 1)) {
            $error = 'No podés quitarte a vos mismo el rol de Administrador: pedile a otro Administrador que lo haga.';
        } elseif (! $sucs) {
            // **Ahora es obligatorio, y antes no lo era.** Mientras existía el
            // selector de sucursal principal, una ficha sin ninguna marcada se
            // salvaba con eso; sin él, la persona queda sin ningún local al que
            // entrar y la pantalla de elegir sucursal le sale vacía, sin decir
            // por qué. Una cuenta que no puede entrar a ningún lado no sirve.
            $error = 'Marcá al menos una sucursal en la que trabaje.';
        } elseif ($choque = $this->turnosSePisan($turnos)) {
            // Dos turnos que se pisan la dejan comprometida en dos lugares a la
            // vez, y con turnos de locales distintos nadie lo miraba.
            $error = $choque;
        } else {
            $error = Persona::error($d);
        }
        if ($error) {
            flash($error, 'error');

            return $volver->withInput();
        }

        // La de la ficha es la primera marcada. No es un dato aparte: es la red
        // para `Sucursales::delUsuario()` —cuentas viejas sin asignaciones— y para
        // `Agenda::agendar()` cuando no hay sesión. En cuál está HOY lo decide la
        // sesión al entrar, que es lo que hace la 7.30.0.
        $d['id_sucursal'] = $sucs[0] ?? null;

        try {
            $r = DB::transaction(function () use ($id, $d, $pass, $sucs, $turnos, $per) {
                // **`persona` NO se toca desde acá.** Sus datos se editan en
                // Personal -> Profesionales; esta pantalla administra la cuenta.
                if ($id) {
                    DB::update(
                        'UPDATE usuario SET id_persona = :id_persona, id_rol = :id_rol,
                                            id_sucursal = :id_sucursal, username = :username
                          WHERE id_usuario = :id',
                        ['id_persona' => $per->id_persona, 'id_rol' => $d['id_rol'],
                         'id_sucursal' => $d['id_sucursal'], 'username' => $d['username'], 'id' => $id]
                    );
                    // Vacío quiere decir «no la cambies», no «dejala en null»:
                    // el formulario nunca trae la que hay cargada.
                    if ($pass !== '') {
                        DB::update('UPDATE usuario SET password_hash = ? WHERE id_usuario = ?',
                            [Hash::make($pass), $id]);
                    }
                    $idUsuario = $id;
                } else {
                    DB::insert(
                        'INSERT INTO usuario (id_persona,id_rol,id_sucursal,username,password_hash) VALUES (?,?,?,?,?)',
                        [$per->id_persona, $d['id_rol'], $d['id_sucursal'], $d['username'], Hash::make($pass)]
                    );
                    $idUsuario = (int) DB::getPdo()->lastInsertId();
                }

                // Quien tiene cuenta de personal es personal: así sigue
                // apareciendo en Profesionales.
                DB::update('UPDATE persona SET es_personal = 1 WHERE id_persona = ?', [$per->id_persona]);

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
                trim($per->nombre . ' ' . ($per->apellido ?? '')) . ' · ' . $d['username']);

            flash(($id ? 'Usuario actualizado.' : 'Usuario creado.')
                . (count($sucs) > 1 ? ' Trabaja en ' . count($sucs) . ' sucursales.' : '')
                . ($r['turnos']
                    ? " Trabaja {$r['turnos']} turno(s)."
                    : ' Sin turno asignado: no va a aparecer en la agenda hasta que se le asigne uno.'));
        } catch (Throwable $e) {
            // **Un `catch` que no supo traducir el error tiene que registrarlo.**
            // Sin esto el mensaje culpaba a un duplicado y el log quedaba vacío:
            // el problema real era una clave de `$d` que dejó de existir.
            Log::error('No se pudo guardar el usuario: ' . $e->getMessage()
                . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
            flash('No se pudo guardar el usuario. El detalle quedó registrado.', 'error');

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

            // El aviso dice DÓNDE resolverlo, que era lo que faltaba: antes
            // avisaba que quedaban citas y había que ir a buscarlas de a una
            // (AG-03). Sólo se ofrece el enlace a quien puede usarlo.
            $donde = $pendientes && Permisos::puede('citas.agenda')
                ? ' Pasáselas a otra persona desde Citas → Reasignar: '
                  . route('citas.reasignar', ['de' => $id])
                : '';

            flash('Estado del usuario actualizado.'
                . ($pendientes
                    ? " Ojo: quedan $pendientes cita(s) futura(s) con esa persona, y siguen ocupando "
                      . 'la agenda.' . $donde
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
            'flexibilidad_entrada_min' => (int) $request->input('flexibilidad_entrada_min', 15),
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
                            hora_inicio=:hora_inicio, hora_fin=:hora_fin,
                            flexibilidad_entrada_min=:flexibilidad_entrada_min WHERE id_turno=:id',
                        $d + ['id' => $id]
                    );
                } else {
                    DB::insert(
                        'INSERT INTO turno_laboral
                            (id_sucursal,nombre,hora_inicio,hora_fin,flexibilidad_entrada_min,activo)
                         VALUES (:id_sucursal,:nombre,:hora_inicio,:hora_fin,:flexibilidad_entrada_min,1)', $d
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
                . ', tolerancia de ' . $d['flexibilidad_entrada_min'] . ' minuto(s), '
                . mb_strtolower($this->diasTexto($dias)) . '. Asignáselo al personal desde su ficha.');
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
            'flexibilidad_entrada_min' => (int) $request->input('flexibilidad_entrada_min', 15),
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
                    'INSERT INTO turno_laboral
                        (id_sucursal,nombre,hora_inicio,hora_fin,flexibilidad_entrada_min,activo)
                     VALUES (:id_sucursal,:nombre,:hora_inicio,:hora_fin,:flexibilidad_entrada_min,1)', $d
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
        $ciudad = ciudad_elegida($request->input('ciudad'), $request->input('ciudad_otra'));

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
                        COALESCE(s.nombre,'Todos los servicios') AS servicio,
                        COALESCE(su.nombre,'Todas las sucursales') AS donde
                   FROM comision c
                   JOIN usuario u  ON u.id_usuario = c.id_usuario
                   JOIN persona pe_u ON pe_u.id_persona = u.id_persona
                   LEFT JOIN servicio s  ON s.id_servicio = c.id_servicio
                   LEFT JOIN sucursal su ON su.id_sucursal = c.id_sucursal
                  WHERE c.activo = 1
                    AND (:s = 0 OR c.id_sucursal IS NULL OR c.id_sucursal = :s2)
                  ORDER BY pe_u.nombre, c.vigente_desde DESC",
                ['s' => Sucursales::activa(), 's2' => Sucursales::activa()]
            ),
            'sucursales' => DB::select('SELECT id_sucursal, nombre FROM sucursal WHERE activo = 1 ORDER BY nombre'),
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
            'sucursales' => DB::select('SELECT id_sucursal, nombre FROM sucursal WHERE activo = 1 ORDER BY nombre'),
        ]);
    }

    public function comisionGuardar(Request $request): RedirectResponse
    {
        $d = [
            'id_usuario' => (int) $request->input('id_usuario', 0),
            // **La comision puede ser distinta segun el local**, por decision
            // del usuario. Vacio = vale en todas, que es lo que hay cargado de
            // antes y lo que espera un salon de un solo local.
            'id_sucursal' => ((int) $request->input('id_sucursal', 0)) ?: null,
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
                'INSERT INTO comision (id_usuario,id_sucursal,id_servicio,tipo,valor,vigente_desde)
                 VALUES (:id_usuario,:id_sucursal,:id_servicio,:tipo,:valor,:vigente_desde)', $d
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
        // La pantalla también es un punto de recuperación si el cron estuvo
        // detenido: al abrirla se materializan las faltas cuya tolerancia ya
        // venció, sin tocar fichajes o permisos que alguien ya registró.
        if ((string) $request->query('fecha', ahora_bd('Y-m-d')) === ahora_bd('Y-m-d')) {
            Asistencia::marcarEntradasVencidas();
        }

        // La fecha y la hora salen del reloj de la base, no del de PHP
        $fecha = (string) $request->query('fecha', ahora_bd('Y-m-d'));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || ! strtotime($fecha)) {
            $fecha = ahora_bd('Y-m-d');
        }
        $dia = (int) date('N', strtotime($fecha));

        $filas = DB::select(
            "SELECT ut.id_usuario, t.id_turno, t.nombre AS turno, t.hora_inicio, t.hora_fin,
                    t.flexibilidad_entrada_min,
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

        // **Cada fila dice si su turno todavía admite fichaje.** La regla vive
        // en `fueraDeFranja()` y la hace cumplir el servidor desde la 5.4.1,
        // pero la pantalla no la miraba: el botón se ofrecía igual y el
        // rechazo llegaba después de apretarlo. Un botón que no va a poder
        // hacer nada es peor que uno ausente — promete algo y no lo cumple.
        foreach ($filas as $f) {
            $tardiaJustificada = ! $f->hora_entrada
                && (int) ($f->justificada ?? -1) === 1
                && str_starts_with((string) ($f->observaciones ?? ''), 'Llegada tardía justificada:');
            $f->fuera = $fecha === ahora_bd('Y-m-d')
                ? $this->fueraDeFranja($f, ($f->hora_entrada || $tardiaJustificada) ? 'salida' : 'entrada') : null;
        }

        // **Los últimos registros, con filtros.** Eran sesenta filas fijas:
        // para saber si alguien faltó el mes pasado había que recorrerlas a
        // ojo, y a los seis meses de operación esa tabla no dice nada. Es la
        // misma forma que las demás listas del sistema — filtros arriba, tabla,
        // y un tope que ahora significa «los 60 de lo filtrado».
        $opProf = ['' => 'Todos'];
        foreach (DB::select(
            "SELECT DISTINCT u.id_usuario, CONCAT(pe.nombre,' ',pe.apellido) AS nombre
               FROM asistencia a
               JOIN usuario u  ON u.id_usuario = a.id_usuario
               JOIN persona pe ON pe.id_persona = u.id_persona
              ORDER BY pe.nombre, pe.apellido") as $o) {
            $opProf[(string) $o->id_usuario] = $o->nombre;
        }

        $campos = [];
        // Quien sólo ve lo suyo no elige de quién: hay una sola respuesta.
        if ($porOtros) {
            $campos['quien'] = ['tipo' => 'select', 'etiqueta' => 'Profesional',
                                'opciones' => $opProf, 'ancho' => '200px'];
        }
        $campos['estado'] = ['tipo' => 'select', 'etiqueta' => 'Estado', 'ancho' => '170px',
                             'opciones' => ['' => 'Todos', 'ok' => 'Presente',
                                            'con' => 'Con permiso', 'sin' => 'Sin aviso']];
        $campos['desde'] = ['tipo' => 'fecha', 'etiqueta' => 'Desde'];
        $campos['hasta'] = ['tipo' => 'fecha', 'etiqueta' => 'Hasta'];

        $fa = Listado::filtros($campos);

        $wa = [];
        $pa = [];
        if (! $porOtros) {
            $wa[] = 'a.id_usuario = ' . (int) session('uid');
        } elseif (Listado::hay($fa, 'quien')) {
            $wa[] = 'a.id_usuario = :quien';
            $pa['quien'] = (int) Listado::valor($fa, 'quien');
        }
        if (Listado::hay($fa, 'desde')) {
            $wa[] = 'a.fecha >= :d';
            $pa['d'] = Listado::valor($fa, 'desde');
        }
        if (Listado::hay($fa, 'hasta')) {
            $wa[] = 'a.fecha <= :h';
            $pa['h'] = Listado::valor($fa, 'hasta');
        }
        $est = (string) Listado::valor($fa, 'estado');
        if ($est === 'ok') {
            $wa[] = 'a.justificada IS NULL';
        } elseif ($est === 'con') {
            $wa[] = 'a.justificada = 1';
        } elseif ($est === 'sin') {
            $wa[] = 'a.justificada = 0';
        }

        return view('seguridad.asistencia', [
            'filas' => $filas,
            'fa' => $fa,
            'rows' => DB::select(
                "SELECT a.*, t.nombre AS turno, t.hora_inicio, t.hora_fin,
                        CONCAT(pe.nombre,' ',pe.apellido) AS profesional
                   FROM asistencia a
                   JOIN turno_laboral t ON t.id_turno = a.id_turno
                   JOIN usuario u       ON u.id_usuario = a.id_usuario
                   JOIN persona pe      ON pe.id_persona = u.id_persona
                  " . ($wa ? 'WHERE ' . implode(' AND ', $wa) : '') . '
                  ORDER BY a.fecha DESC, t.hora_inicio DESC LIMIT 60', $pa
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
        } elseif (! in_array($accion, ['entrada', 'salida', 'falta_con', 'falta_sin', 'justificar', 'limpiar'], true)) {
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
                'SELECT t.nombre, t.hora_inicio, t.hora_fin, t.flexibilidad_entrada_min
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
                // La justificación habilita una entrada tardía dentro del
                // turno, aun cuando ya venció la tolerancia configurada.
                $yaParaFranja = DB::selectOne(
                    'SELECT hora_entrada, justificada, observaciones
                       FROM asistencia WHERE id_usuario = ? AND id_turno = ? AND fecha = ?',
                    [$idQuien, $idTurno, $fecha]
                );
                $tardiaJustificada = $accion === 'entrada'
                    && $yaParaFranja
                    && ! $yaParaFranja->hora_entrada
                    && (int) $yaParaFranja->justificada === 1
                    && str_starts_with((string) ($yaParaFranja->observaciones ?? ''), 'Llegada tardía justificada:');
                $error = $tardiaJustificada
                    ? $this->fueraDeFranja($trabaja, 'salida')
                    : $this->fueraDeFranja($trabaja, $accion);
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
                $observacionTardia = $ya && (int) $ya->justificada === 1
                    ? (string) ($ya->observaciones ?? '') : null;
                $this->asistenciaGuardar($idQuien, $idTurno, $fecha, [
                    'hora_entrada' => $ahora, 'motivo_ausencia' => null, 'justificada' => null,
                    'observaciones' => $observacionTardia ?: null,
                ]);
                flash('Entrada de ' . $quien . ' registrada a las ' . substr($ahora, 0, 5) . '.'
                    . ($observacionTardia ? ' Quedó asentada como llegada tardía justificada.' : ''));
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
            } elseif ($accion === 'justificar') {
                // **Justificar es del Administrador.** Dar el permiso por una
                // falta es una decisión sobre el sueldo de esa persona, no una
                // tarea del mostrador: quien administra los turnos marca quién
                // vino, quien dirige el salón decide si esa ausencia estaba
                // autorizada. El botón ya no se dibuja para los demás; esto es
                // lo que lo hace cumplir.
                if (! Permisos::esAdmin()) {
                    flash('Sólo un Administrador puede dar el permiso por una falta.', 'error');

                    return $volver;
                }
                if ($ya && $ya->hora_entrada) {
                    flash($quien . ' ya tiene una entrada registrada; no hay una llegada tardía que justificar.', 'warning');

                    return $volver;
                }
                // **Al menos diez caracteres.** «ok» o un punto no explican
                // nada, y esto es lo único que queda escrito de por qué esa
                // falta no se descuenta: quien lo lea en tres meses tiene que
                // poder entenderlo.
                if (mb_strlen((string) $motivo) < 10) {
                    flash('Escribí el motivo con al menos 10 caracteres: es lo único que explica '
                        . 'por qué esa falta lleva permiso.', 'error');

                    return $volver;
                }
                $this->asistenciaGuardar($idQuien, $idTurno, $fecha, [
                    'hora_entrada' => null, 'hora_salida' => null, 'horas_extras' => 0,
                    'justificada' => 1,
                    'motivo_ausencia' => $motivo,
                    'observaciones' => 'Llegada tardía justificada: ' . $motivo,
                ]);
                Auditoria::registrar('JUSTIFICACION', 'Personal', 'asistencia', $idQuien,
                    $quien . ' justificó una llegada tardía del ' . $fecha . ': ' . $motivo);
                flash('Se justificó la llegada tardía de ' . $quien . '. Ya puede marcar la entrada dentro del turno.', 'success');
            } else {
                // **Marcar una falta la deja SIN AVISO, siempre.** Antes el
                // modal ofrecía «Con permiso» y «Sin aviso» **y** la fila tenía
                // además el botón «Justificar»: tres caminos para dos estados, y
                // obligaba a decidir el permiso en el momento de marcar, que es
                // justo cuando todavía no se sabe por qué no vino.
                //
                // Marcar es constatar; justificar es otra cosa y pasa después.
                // `falta_con` se sigue aceptando por si quedó algún formulario
                // viejo abierto, pero la pantalla ya no lo manda.
                $justificada = $accion === 'falta_con' ? 1 : 0;
                if ($justificada && ! Permisos::esAdmin()) {
                    $justificada = 0;
                }
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
        return Permisos::esAdmin() || Permisos::puede('personal.turnos');
    }

    /**
     * ¿Está fuera de la franja en que se puede fichar? Devuelve el mensaje o null.
     *
     * Se trabaja en minutos desde la medianoche y NO con texto: la franja puede
     * cruzar las 12 de la noche (un turno de 20:00 a 02:00), y ahí la
     * comparación de cadenas da cualquier cosa.
     */
    private function fueraDeFranja(object $turno, string $accion = 'entrada'): ?string
    {
        $enMinutos = fn (string $hms): int => (int) substr($hms, 0, 2) * 60 + (int) substr($hms, 3, 2);

        $ini = $enMinutos((string) $turno->hora_inicio);
        $fin = $enMinutos((string) $turno->hora_fin);
        if ($fin <= $ini) {
            $fin += 1440;   // el turno termina al día siguiente
        }

        $desde = $ini - (int) config('spg.fichaje.gracia_antes_min', 60);
        $hasta = $accion === 'entrada'
            ? $ini + (int) ($turno->flexibilidad_entrada_min ?? 15)
            : $fin + (int) config('spg.fichaje.gracia_despues_min', 120);
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

        // El nombre viene como `nombre` desde la consulta del fichaje y como
        // `turno` desde la lista de la pantalla: se acepta cualquiera de los
        // dos para que la misma función sirva a las dos.
        return 'El turno ' . ($turno->nombre ?? $turno->turno ?? '') . ' va de '
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
    /**
     * ¿Estos turnos dejan a la persona en dos lugares a la vez?
     *
     * **El turno ya dice en qué sucursal se trabaja** (`turno_laboral.id_sucursal`),
     * y una misma persona puede tener el lunes en un local y el martes en otro:
     * eso es correcto y es para lo que existe la tabla N:M.
     *
     * Lo que no puede pasar es que dos de sus turnos **se pisen el mismo día a
     * la misma hora**. **Se mira sin importar la sucursal**, que es justamente
     * el caso peligroso: dos turnos del mismo local ya se rechazan al crearlos
     * (`turnoValidar`), pero uno de cada local pasaba sin que nadie lo mirara —
     * y ahí la persona queda comprometida en dos lugares al mismo tiempo.
     */
    private function turnosSePisan(array $turnos): ?string
    {
        $turnos = array_values(array_unique(array_filter(array_map('intval', $turnos))));
        if (count($turnos) < 2) {
            return null;
        }

        $in = implode(',', array_fill(0, count($turnos), '?'));
        $choque = DB::selectOne(
            "SELECT a.nombre AS uno, b.nombre AS otro, da.dia_semana,
                    sa.nombre AS suc_uno, sb.nombre AS suc_otro
               FROM turno_laboral a
               JOIN turno_dia da ON da.id_turno = a.id_turno
               JOIN turno_laboral b ON b.id_turno > a.id_turno AND b.id_turno IN ($in)
               JOIN turno_dia db ON db.id_turno = b.id_turno AND db.dia_semana = da.dia_semana
               LEFT JOIN sucursal sa ON sa.id_sucursal = a.id_sucursal
               LEFT JOIN sucursal sb ON sb.id_sucursal = b.id_sucursal
              WHERE a.id_turno IN ($in)
                AND a.activo = 1 AND b.activo = 1
                AND a.hora_inicio < b.hora_fin AND b.hora_inicio < a.hora_fin
              ORDER BY da.dia_semana LIMIT 1",
            array_merge($turnos, $turnos)
        );

        if (! $choque) {
            return null;
        }

        $dia = mb_strtolower(self::DIAS[(int) $choque->dia_semana] ?? '');
        $donde = ($choque->suc_uno && $choque->suc_otro && $choque->suc_uno !== $choque->suc_otro)
            ? ' — y son de locales distintos (' . $choque->suc_uno . ' y ' . $choque->suc_otro
                . '), así que quedaría en dos lugares a la vez'
            : '';

        return 'Los turnos «' . $choque->uno . '» y «' . $choque->otro . '» se pisan el ' . $dia . $donde
            . '. Sacale uno de los dos.';
    }

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
        if ($d['flexibilidad_entrada_min'] < 0 || $d['flexibilidad_entrada_min'] > 180) {
            return 'La flexibilidad de entrada tiene que estar entre 0 y 180 minutos.';
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

        // Dos turnos de la misma sucursal tienen que dejar una pausa real. No
        // alcanza con que no se pisen: al terminar un turno hay que entregar
        // caja, ordenar el puesto y recibir al siguiente equipo.
        $in = implode(',', array_fill(0, count($dias), '?'));
        $existentes = DB::select(
            "SELECT t.nombre, t.hora_inicio, t.hora_fin, td.dia_semana
               FROM turno_laboral t
               JOIN turno_dia td ON td.id_turno = t.id_turno
              WHERE t.activo = 1 AND t.id_sucursal = ? AND t.id_turno <> ?
                AND td.dia_semana IN ($in)
              ORDER BY td.dia_semana, t.hora_inicio",
            array_merge([$d['id_sucursal'], $id], $dias)
        );
        $aMinutos = static function (string $hora): int {
            return (int) substr($hora, 0, 2) * 60 + (int) substr($hora, 3, 2);
        };
        $pausaMin = (int) config('spg.agenda.descanso_turnos_min', 60);
        $nuevoIni = $aMinutos($d['hora_inicio'] . ':00');
        $nuevoFin = $aMinutos($d['hora_fin'] . ':00');
        foreach ($existentes as $otro) {
            $otroIni = $aMinutos((string) $otro->hora_inicio);
            $otroFin = $aMinutos((string) $otro->hora_fin);
            $distancia = $nuevoFin <= $otroIni
                ? $otroIni - $nuevoFin
                : ($otroFin <= $nuevoIni ? $nuevoIni - $otroFin : -1);
            if ($distancia < $pausaMin) {
                return 'El turno «' . $otro->nombre . '» del '
                    . mb_strtolower(self::DIAS[(int) $otro->dia_semana] ?? '')
                    . ' queda a menos de ' . $pausaMin
                    . ' minutos. Dejá al menos ese espacio entre la salida y la próxima entrada.';
            }
        }

        if ($id && ! DB::scalar('SELECT COUNT(*) FROM turno_laboral WHERE id_turno = ? AND activo = 1', [$id])) {
            return 'Ese turno no existe o fue dado de baja.';
        }

        return null;
    }

    /** Turnos activos con sus días y cuánta gente los trabaja. */
    private function turnosDisponibles(): array
    {
        // **Los turnos son del local.** `turno_laboral.id_sucursal` existe desde
        // que hay sucursales, y esta lista los mostraba todos: quien administra
        // el segundo local veía —y podía asignar— los horarios de la casa
        // central, que es justo lo que la 7.39.0 impidió en la agenda. Un
        // empleado no arrastra su horario de otra sucursal, y la pantalla que
        // los asigna tampoco debería ofrecerlo.
        $turnos = DB::select(
            'SELECT t.id_turno, t.nombre, t.hora_inicio, t.hora_fin, t.flexibilidad_entrada_min,
                    t.id_sucursal, s.nombre AS sucursal,
                    (SELECT COUNT(*) FROM usuario_turno ut WHERE ut.id_turno = t.id_turno) AS asignados
               FROM turno_laboral t
               JOIN sucursal s ON s.id_sucursal = t.id_sucursal
              WHERE t.activo = 1 AND (:s = 0 OR t.id_sucursal = :s2)
              ORDER BY t.hora_inicio, t.nombre',
            ['s' => Sucursales::activa(), 's2' => Sucursales::activa()]
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
