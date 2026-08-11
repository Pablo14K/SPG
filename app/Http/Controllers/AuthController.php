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

        if (! Sesion::intentarLogin($datos['usuario'], $datos['password'])) {
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

        try {
            $idu = DB::transaction(function () use ($d, $username, $pass) {
                // Los datos personales van una sola vez, en `persona`; la cuenta
                // y la ficha de cliente la referencian.
                $idPersona = Persona::guardar(null, $d);
                // La cuenta nace INACTIVA hasta verificar el correo
                DB::insert('INSERT INTO usuario (id_persona,id_rol,username,password_hash,activo) VALUES (?,?,?,?,0)',
                    [$idPersona, (int) config('permisos.rol_cliente', 4), $username, Hash::make($pass)]);
                $idu = (int) DB::getPdo()->lastInsertId();
                DB::insert('INSERT INTO cliente (id_persona,id_usuario,activo) VALUES (?,?,1)', [$idPersona, $idu]);

                return $idu;
            });
        } catch (Throwable) {
            flash('No se pudo crear la cuenta. Intentá con otro usuario o email.', 'error');

            return $volver;
        }

        $enviado = Seguridad::enviarCodigo($idu, 'VERIFICACION', $d['email'], $d['nombre']);
        session(['verif_uid' => $idu, 'verif_email' => $d['email']]);

        flash($enviado
            ? 'Te enviamos un código de verificación a ' . $d['email'] . '.'
            : 'No pudimos enviarte el código todavía. Probá reenviarlo desde la próxima pantalla.',
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
