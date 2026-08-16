<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Auditoria;
use App\Servicios\Seguridad;
use App\Servicios\Sesion;
use App\Servicios\Sucursales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Mi cuenta y el cambio de contraseña en dos pasos.
 *
 * Saber la contraseña actual NO alcanza: si alguien se sienta en una
 * computadora con la sesión abierta, o si la contraseña se filtró, con un solo
 * paso le cambian la clave a la dueña de la cuenta y la dejan afuera. Por eso
 * hace falta además el código que llega al correo — algo que se sabe más algo
 * que se tiene.
 *
 * El primer paso NO toca la base: valida, deja la contraseña nueva ya hasheada
 * en la sesión y manda el código. Recién el segundo la aplica, así un pedido
 * que nunca se confirma no cambia nada.
 */
class CuentaController extends Controller
{
    private const MINUTOS = 30;

    public function index(): View
    {
        $uid = (int) session('uid');

        return view('cuenta.index', [
            'perfil' => DB::selectOne(
                'SELECT u.username, pe.nombre, pe.apellido, pe.email, pe.telefono, r.nombre AS rol
                   FROM usuario u
                   JOIN persona pe ON pe.id_persona = u.id_persona
                   JOIN rol r ON r.id_rol = u.id_rol
                  WHERE u.id_usuario = ?', [$uid]
            ),
            'pendiente' => (bool) session('cambio_pass'),
            'bioActivo' => (int) DB::scalar('SELECT COUNT(*) FROM credencial_webauthn WHERE id_usuario = ?', [$uid]),
            'tema' => Sesion::tema(),
            // En qué local está trabajando y a cuáles puede pasarse. La
            // clienta no tiene ninguno: elige al agendar, no al entrar.
            'sucursalActiva' => Sucursales::nombreActiva(),
            'misSucursales' => Sesion::esCliente() ? [] : Sucursales::delUsuario(),
            'idSucursalActiva' => Sucursales::activa(),
        ]);
    }

    /**
     * Cambia el tema de la interfaz.
     *
     * Es un POST y no un enlace porque cambia algo guardado. Se aplica en el
     * acto: `guardarTema` deja el valor en la sesión, así que la pantalla que
     * se dibuja después del redirect ya sale con el tema nuevo.
     */
    public function tema(Request $request): RedirectResponse
    {
        $tema = (string) $request->input('tema', '');

        if (! Sesion::guardarTema((int) session('uid'), $tema)) {
            flash('Ese tema no existe.', 'error');

            return redirect()->route('cuenta.index');
        }

        flash('Tema ' . mb_strtolower(Sesion::TEMAS[$tema]) . ' aplicado.');

        return redirect()->route('cuenta.index');
    }

    /** Paso 1: valida y manda el código. No escribe la contraseña todavía. */
    public function password(Request $request): RedirectResponse
    {
        $uid = (int) session('uid');
        $actual = (string) $request->input('actual', '');
        $nueva = (string) $request->input('nueva', '');
        $nueva2 = (string) $request->input('nueva2', '');
        $volver = redirect()->route('cuenta.index');

        $u = DB::selectOne(
            'SELECT u.password_hash, pe.email, pe.nombre
               FROM usuario u JOIN persona pe ON pe.id_persona = u.id_persona
              WHERE u.id_usuario = ?', [$uid]
        );

        $error = null;
        if (! $u || ! Hash::check($actual, (string) $u->password_hash)) {
            $error = 'La contraseña actual no es correcta.';
        } elseif (strlen($nueva) < 6) {
            $error = 'La nueva contraseña debe tener al menos 6 caracteres.';
        } elseif ($nueva !== $nueva2) {
            $error = 'Las contraseñas nuevas no coinciden.';
        } elseif ($nueva === $actual) {
            $error = 'La contraseña nueva tiene que ser distinta de la actual.';
        } elseif (trim((string) $u->email) === '') {
            // Sin correo no hay segundo factor posible, y dejarlo pasar sin él
            // sería justamente el agujero que esto viene a tapar.
            $error = 'Tu cuenta no tiene un correo cargado, así que no podemos mandarte el código '
                   . 'de confirmación. Pedile al Administrador que te lo cargue.';
        }
        if ($error) {
            flash($error, 'error');

            return $volver;
        }

        // En texto plano la contraseña nueva no se guarda en ningún lado, ni
        // siquiera por estos minutos: a la sesión va el hash.
        session(['cambio_pass' => [
            'hash' => Hash::make($nueva),
            'vence' => time() + self::MINUTOS * 60,
        ]]);

        $ok = Seguridad::enviarCodigo($uid, 'CAMBIO_PASSWORD', (string) $u->email, (string) $u->nombre);
        flash($ok
            ? 'Te mandamos un código a ' . $u->email . '. Escribilo para confirmar el cambio.'
            : 'No pudimos enviarte el código. Probá reenviarlo desde la próxima pantalla.',
            $ok ? 'success' : 'warning');

        return redirect()->route('cuenta.password_confirmar');
    }

    /** Paso 2: valida el código y recién ahí escribe la contraseña. */
    public function passwordConfirmar(Request $request): View|RedirectResponse
    {
        $uid = (int) session('uid');
        $pendiente = session('cambio_pass');

        // Sin pedido, o vencido, se vuelve al principio: no se puede confirmar
        // un cambio que no se pidió.
        if (! $pendiente || empty($pendiente['hash'])) {
            flash('No hay ningún cambio de contraseña pendiente.', 'warning');

            return redirect()->route('cuenta.index');
        }
        if (time() > (int) $pendiente['vence']) {
            session()->forget('cambio_pass');
            flash('El pedido venció. Volvé a cargar la contraseña nueva.', 'warning');

            return redirect()->route('cuenta.index');
        }

        $email = (string) DB::scalar(
            'SELECT pe.email FROM usuario u JOIN persona pe ON pe.id_persona = u.id_persona
              WHERE u.id_usuario = ?', [$uid]);

        if ($request->isMethod('post')) {
            if ($request->input('reenviar')) {
                $nombre = (string) DB::scalar(
                    'SELECT pe.nombre FROM usuario u JOIN persona pe ON pe.id_persona = u.id_persona
                      WHERE u.id_usuario = ?', [$uid]);
                $ok = Seguridad::enviarCodigo($uid, 'CAMBIO_PASSWORD', $email, $nombre);
                flash($ok ? 'Te reenviamos el código a ' . $email . '.' : 'No pudimos enviar el código.',
                    $ok ? 'success' : 'error');

                return redirect()->route('cuenta.password_confirmar');
            }

            if (! Seguridad::validarCodigo($uid, 'CAMBIO_PASSWORD', trim((string) $request->input('codigo', '')))) {
                flash('Código incorrecto o vencido.', 'error');

                return redirect()->route('cuenta.password_confirmar');
            }

            DB::update('UPDATE usuario SET password_hash = ? WHERE id_usuario = ?', [(string) $pendiente['hash'], $uid]);
            session()->forget('cambio_pass');

            // Contraseña nueva, sesión nueva: si alguien había copiado el
            // identificador de sesión, deja de servirle.
            session()->regenerate();

            Auditoria::registrar('CAMBIO_PASSWORD', 'Cuenta', 'usuario', $uid,
                'Cambió su contraseña, confirmado con código enviado al correo');
            flash('Contraseña actualizada. La próxima vez entrá con la nueva.');

            return redirect()->route('cuenta.index');
        }

        return view('cuenta.password_confirmar', [
            'email' => $email,
            'minutos' => max(1, (int) ceil(((int) $pendiente['vence'] - time()) / 60)),
        ]);
    }

    /** Abandonar el cambio pendiente: se quema el código y no cambia nada. */
    public function passwordCancelar(): RedirectResponse
    {
        session()->forget('cambio_pass');
        Seguridad::quemar((int) session('uid'), 'CAMBIO_PASSWORD');
        flash('Se canceló el cambio de contraseña. Tu contraseña sigue siendo la de antes.');

        return redirect()->route('cuenta.index');
    }
}
