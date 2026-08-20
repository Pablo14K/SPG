<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Auditoria;
use App\Servicios\Contacto;
use App\Servicios\Persona;
use App\Servicios\Seguridad;
use App\Servicios\Sesion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{
    public function formulario(): View|RedirectResponse
    {
        if (Sesion::activa()) {
            return redirect()->route(Sesion::inicio());
        }

        return view('auth.login');
    }

    public function entrar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'usuario' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string'],
        ], [
            'usuario.required' => 'Ingresá tu usuario o email.',
            'password.required' => 'Ingresá tu contraseña.',
        ]);

        $r = Sesion::intentarLogin($datos['usuario'], $datos['password'], $request->boolean('forzar'));

        // La cuenta ya está abierta en otro equipo. No se la desplaza: se le
        // niega a quien llega, y se le ofrece entrar igual cerrando la otra —
        // si no, quien cierra el navegador sin salir queda afuera para siempre,
        // porque la marca se limpia recién al salir.
        if ($r === Sesion::OCUPADA) {
            $varios = (int) DB::scalar('SELECT COUNT(*) FROM sucursal WHERE activo = 1') > 1;

            return back()
                ->withInput($request->only('usuario'))
                ->with('spg_sesion_ocupada', true)
                // **Con varios locales hay que decir la consecuencia.** Una
                // cuenta es una identidad: entrar igual cierra la sesión de la
                // otra sucursal en medio de su jornada, y quien está ahí se
                // queda sin poder cobrar. La simulación de 30 días midió 34
                // ingresos rechazados y 9 días en que un local no abrió su
                // caja por esto. Con más de un local, cada persona necesita su
                // propia cuenta — compartir una las deja peleándose la sesión.
                ->withErrors(['usuario' => 'Esa cuenta ya tiene una sesión abierta en otro equipo. '
                    . 'Cerrala ahí y volvé a intentar, o marcá la casilla de abajo para entrar '
                    . 'igual y cerrar la otra.'
                    . ($varios ? ' Ojo: si la otra sesión es la de otra sucursal, esa sucursal '
                        . 'se queda sin poder cobrar hasta que vuelva a entrar. Con varios '
                        . 'locales conviene que cada persona tenga su propia cuenta.' : '')]);
        }

        if (! $r) {
            // Un solo mensaje para los dos casos: decir cuál de los dos está
            // mal le confirma a cualquiera qué cuentas existen.
            return back()
                ->withInput($request->only('usuario'))
                ->withErrors(['usuario' => 'Usuario o contraseña incorrectos.']);
        }

        // La primera vez se ofrece el ingreso con huella. Se pregunta UNA sola
        // vez: si dice que no, no se vuelve a insistir.
        $uid = (int) session('uid');
        $tiene = (int) DB::scalar('SELECT COUNT(*) FROM credencial_webauthn WHERE id_usuario = ?', [$uid]);
        $preguntado = (int) (DB::scalar('SELECT biometrico_pregunt FROM preferencia_usuario WHERE id_usuario = ?', [$uid]) ?: 0);
        if (! $tiene && ! $preguntado) {
            return redirect()->route('webauthn.preguntar');
        }

        return redirect()->route(Sesion::inicio());
    }

    public function salir(): RedirectResponse
    {
        Sesion::cerrar();

        return redirect()->route('login');
    }

    // -----------------------------------------------------------------
    //  Registro de clientas (autoservicio)
    // -----------------------------------------------------------------

    public function registro(): View|RedirectResponse
    {
        if (Sesion::activa()) {
            return redirect()->route(Sesion::inicio());
        }

        return view('auth.registro');
    }

    public function registrar(Request $request): RedirectResponse
    {
        if (Sesion::activa()) {
            return redirect()->route(Sesion::inicio());
        }

        $d = [
            'nombre' => trim((string) $request->input('nombre', '')),
            'apellido' => trim((string) $request->input('apellido', '')),
            'email' => trim((string) $request->input('email', '')),
            'telefono' => trim((string) $request->input('telefono', '')) ?: null,
        ];
        $username = trim((string) $request->input('username', ''));
        $pass = (string) $request->input('password', '');
        $pass2 = (string) $request->input('password2', '');
        $volver = redirect()->route('registro')->withInput();

        // El celular se guarda en formato internacional
        if ($d['telefono']) {
            $e164 = Contacto::aE164($d['telefono']);
            if (! $e164) {
                flash('El número de celular no se entiende. Escribilo como 0981123456 o +595981123456.', 'error');

                return $volver;
            }
            $d['telefono'] = $e164;
        }

        $error = null;
        if ($d['nombre'] === '' || $d['apellido'] === '' || $username === '' || $pass === '') {
            $error = 'Completá nombre, apellido, usuario y contraseña.';
        } elseif ($d['email'] === '') {
            // El código sale solo por correo: sin email la cuenta quedaría
            // inactiva para siempre.
            $error = 'Cargá tu email: ahí te mandamos el código de verificación. '
                   . 'El celular es opcional y nos sirve para avisarte de tus citas.';
        } elseif (! filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'El email no tiene un formato válido.';
        } elseif (strlen($pass) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif ($pass !== $pass2) {
            $error = 'Las contraseñas no coinciden.';
        } elseif (! preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $username)) {
            $error = 'El usuario puede tener letras, números, punto, guion o guion bajo (3 a 60).';
        } elseif (DB::scalar('SELECT COUNT(*) FROM usuario WHERE username = ?', [$username])) {
            $error = 'Ese nombre de usuario ya está en uso.';
        } elseif (DB::scalar('SELECT COUNT(*) FROM usuario u JOIN persona pe ON pe.id_persona = u.id_persona
                               WHERE pe.email = ?', [$d['email']])) {
            $error = 'Ya existe una cuenta con ese email.';
        } elseif ($d['telefono'] && DB::scalar('SELECT COUNT(*) FROM usuario u JOIN persona pe ON pe.id_persona = u.id_persona
                                                 WHERE pe.telefono = ?', [$d['telefono']])) {
            $error = 'Ya existe una cuenta con ese número de celular.';
        } else {
            $error = Persona::error($d);
        }
        if ($error) {
            flash($error, 'error');

            return $volver;
        }

        // ¿Esta persona YA ESTÁ en el salón, cargada desde el mostrador?
        //
        // Es el caso normal, no el raro: casi todas las clientas entran por
        // teléfono y las carga quien atiende, así que tienen `persona` y
        // `cliente` pero **no `usuario`**. Los controles de arriba miran
        // `usuario JOIN persona`, o sea sólo a quien ya tiene cuenta, así que
        // esa clienta pasaba el filtro y se le creaba una persona y un cliente
        // NUEVOS: quedaban dos fichas con el mismo correo y su historial, sus
        // puntos y su nivel se quedaban en la vieja. Además rompe la regla de
        // no repetir datos de personas.
        //
        // Quien controla el correo es quien puede activar la cuenta —el código
        // se manda ahí y hasta entonces el usuario nace inactivo—, así que
        // enlazar por correo no le regala la ficha a un tercero.
        $existente = DB::selectOne(
            'SELECT pe.id_persona, cl.id_cliente
               FROM persona pe
               LEFT JOIN cliente cl ON cl.id_persona = pe.id_persona
              WHERE pe.email = ?
                AND NOT EXISTS (SELECT 1 FROM usuario u WHERE u.id_persona = pe.id_persona)
              ORDER BY cl.id_cliente IS NULL, cl.id_cliente
              LIMIT 1', [$d['email']]
        );

        try {
            $idu = DB::transaction(function () use ($d, $username, $pass, $existente) {
                // Los datos personales van una sola vez, en `persona`; la cuenta
                // y la ficha de cliente la referencian.
                //
                // Al enlazar no se pisa lo que ya había con vacíos: `$d` puede
                // traer el teléfono en null y el salón tenerlo cargado.
                $datos = $existente ? array_filter($d, fn ($v) => trim((string) $v) !== '') : $d;
                $idPersona = Persona::guardar($existente?->id_persona, $datos);

                // La cuenta nace INACTIVA hasta verificar el correo
                DB::insert('INSERT INTO usuario (id_persona,id_rol,username,password_hash,activo) VALUES (?,?,?,?,0)',
                    [$idPersona, (int) config('permisos.rol_cliente', 4), $username, Hash::make($pass)]);
                $idu = (int) DB::getPdo()->lastInsertId();

                if ($existente?->id_cliente) {
                    // La ficha que ya tenía el salón pasa a ser la de su cuenta.
                    DB::update('UPDATE cliente SET id_usuario = ?, activo = 1 WHERE id_cliente = ?',
                        [$idu, (int) $existente->id_cliente]);
                } else {
                    DB::insert('INSERT INTO cliente (id_persona,id_usuario,activo) VALUES (?,?,1)', [$idPersona, $idu]);
                }

                return $idu;
            });
        } catch (Throwable $ex) {
            Log::error('Registro de ' . $d['email'] . ': ' . $ex->getMessage());
            flash('No se pudo crear la cuenta. Intentá con otro usuario o email.', 'error');

            return $volver;
        }

        $enviado = Seguridad::enviarCodigo($idu, 'VERIFICACION', $d['email'], $d['nombre']);
        session(['verif_uid' => $idu, 'verif_email' => $d['email']]);

        flash(($enviado
                ? 'Te enviamos un código de verificación a ' . $d['email'] . '.'
                : 'No pudimos enviarte el código todavía. Probá reenviarlo desde la próxima pantalla.')
            // Que lo sepa: si el salón ya la tenía cargada, sus citas y sus
            // puntos siguen ahí y no arranca de cero.
            . ($existente?->id_cliente
                ? ' Encontramos tu ficha en el salón, así que tus citas y tus puntos ya están en tu cuenta.'
                : ''),
            $enviado ? 'success' : 'warning');

        return redirect()->route('verificar');
    }

    public function verificar(): View|RedirectResponse
    {
        if (! session('verif_uid')) {
            return redirect()->route('login');
        }

        return view('auth.verificar', ['email' => (string) session('verif_email', '')]);
    }

    public function verificarGuardar(Request $request): RedirectResponse
    {
        $idu = (int) session('verif_uid', 0);
        if (! $idu) {
            return redirect()->route('login');
        }
        $email = (string) session('verif_email', '');
        $volver = redirect()->route('verificar');

        if ($request->input('reenviar')) {
            $nombre = (string) DB::scalar(
                'SELECT pe.nombre FROM usuario u JOIN persona pe ON pe.id_persona = u.id_persona
                  WHERE u.id_usuario = ?', [$idu]);
            $ok = Seguridad::enviarCodigo($idu, 'VERIFICACION', $email, $nombre);
            flash($ok ? 'Te reenviamos el código a ' . $email . '.'
                      : 'No pudimos enviar el código. Revisá los datos con el salón.', $ok ? 'success' : 'error');

            return $volver;
        }

        if (! Seguridad::validarCodigo($idu, 'VERIFICACION', trim((string) $request->input('codigo', '')))) {
            flash('Código incorrecto o vencido.', 'error');

            return $volver;
        }

        DB::update('UPDATE usuario SET activo = 1 WHERE id_usuario = ?', [$idu]);
        session()->forget(['verif_uid', 'verif_email']);

        if (! Sesion::iniciarPorId($idu)) {
            flash('Tu cuenta fue verificada. Iniciá sesión para continuar.');

            return redirect()->route('login');
        }

        Auditoria::registrar('VERIFICACION', 'Seguridad', 'usuario', $idu, 'Cuenta verificada por correo');
        flash('¡Cuenta verificada! Ya podés reservar tu cita.');

        return redirect()->route('portal.index');
    }

    // -----------------------------------------------------------------
    //  Recuperación de contraseña
    // -----------------------------------------------------------------

    public function recuperar(): View|RedirectResponse
    {
        if (Sesion::activa()) {
            return redirect()->route(Sesion::inicio());
        }

        return view('auth.recuperar');
    }

    public function recuperarEnviar(Request $request): RedirectResponse
    {
        $email = trim((string) $request->input('email', ''));

        $u = DB::selectOne(
            'SELECT u.id_usuario, pe.nombre FROM usuario u JOIN persona pe ON pe.id_persona = u.id_persona
              WHERE pe.email = ? AND u.activo = 1 LIMIT 1', [$email]
        );

        if ($u) {
            Seguridad::enviarCodigo((int) $u->id_usuario, 'RECUPERACION', $email, (string) $u->nombre);
            session(['recup_uid' => (int) $u->id_usuario, 'recup_email' => $email]);

            return redirect()->route('recuperar.codigo');
        }

        // No se revela si el email existe: si el mensaje fuera distinto,
        // cualquiera podría averiguar qué casillas están registradas.
        flash('Si ese email tiene una cuenta, te llega el código en unos minutos.', 'info');

        return redirect()->route('recuperar');
    }

    public function recuperarCodigo(): View|RedirectResponse
    {
        if (! session('recup_uid')) {
            return redirect()->route('recuperar');
        }

        return view('auth.recuperar_codigo', ['email' => (string) session('recup_email', '')]);
    }

    public function recuperarGuardar(Request $request): RedirectResponse
    {
        $idu = (int) session('recup_uid', 0);
        if (! $idu) {
            return redirect()->route('recuperar');
        }

        $codigo = trim((string) $request->input('codigo', ''));
        $nueva = (string) $request->input('nueva', '');
        $nueva2 = (string) $request->input('nueva2', '');
        $volver = redirect()->route('recuperar.codigo');

        if (strlen($nueva) < 6) {
            flash('La contraseña debe tener al menos 6 caracteres.', 'error');

            return $volver;
        }
        if ($nueva !== $nueva2) {
            flash('Las contraseñas no coinciden.', 'error');

            return $volver;
        }
        if (! Seguridad::validarCodigo($idu, 'RECUPERACION', $codigo)) {
            flash('Código incorrecto o vencido.', 'error');

            return $volver;
        }

        DB::update('UPDATE usuario SET password_hash = ? WHERE id_usuario = ?', [Hash::make($nueva), $idu]);
        session()->forget(['recup_uid', 'recup_email']);
        Auditoria::registrarComo($idu, 'RECUPERACION', 'Seguridad', 'usuario', $idu,
            'Contraseña restablecida por correo');
        flash('Tu contraseña fue restablecida. Ya podés iniciar sesión.');

        return redirect()->route('login');
    }
}
