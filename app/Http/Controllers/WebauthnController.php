<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Auditoria;
use App\Servicios\Sesion;
use App\Servicios\WebAuthn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/**
 * Las ceremonias de WebAuthn: activar la huella y entrar con ella.
 *
 * Todo se habla en JSON con el navegador (ver public/assets/js/webauthn.js).
 * La criptografía vive en App\Servicios\WebAuthn.
 */
class WebauthnController extends Controller
{
    /** Se pregunta UNA vez, después del primer ingreso con contraseña. */
    public function preguntar(): View|RedirectResponse
    {
        $uid = (int) session('uid');

        $tiene = (int) DB::scalar('SELECT COUNT(*) FROM credencial_webauthn WHERE id_usuario = ?', [$uid]);
        $preguntado = (int) (DB::scalar('SELECT biometrico_pregunt FROM preferencia_usuario WHERE id_usuario = ?', [$uid]) ?: 0);

        if ($tiene || $preguntado) {
            return redirect()->route(Sesion::inicio());
        }

        $u = DB::selectOne(
            'SELECT u.username, pe.email FROM usuario u JOIN persona pe ON pe.id_persona = u.id_persona
              WHERE u.id_usuario = ?', [$uid]
        );

        return view('webauthn.preguntar', [
            'email' => (string) ($u->email ?? ''),
            'username' => (string) ($u->username ?? ''),
        ]);
    }

    /**
     * «Ahora no»: no se vuelve a preguntar, pero se puede activar desde Mi cuenta.
     *
     * Contesta las dos formas a propósito. Si la pantalla llega por un envío
     * normal del formulario —sin JavaScript— devuelve el redirect y la persona
     * sigue trabajando; si la llama el fetch, el JSON de siempre. Esta es la
     * ÚNICA salida de esa pantalla, así que no puede depender de que el JS haya
     * cargado: cuando no cargaba, el usuario quedaba encerrado ahí.
     */
    public function marcarPreguntado(Request $request): JsonResponse|RedirectResponse
    {
        DB::statement(
            'INSERT INTO preferencia_usuario (id_usuario, biometrico_pregunt) VALUES (?,1)
             ON DUPLICATE KEY UPDATE biometrico_pregunt = 1', [(int) session('uid')]
        );

        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : redirect()->route(Sesion::inicio());
    }

    // ---------- Activar la huella ----------

    public function opcionesRegistro(): JsonResponse
    {
        $uid = (int) session('uid');
        $u = DB::selectOne(
            'SELECT u.username, pe.nombre, pe.apellido, pe.email
               FROM usuario u JOIN persona pe ON pe.id_persona = u.id_persona
              WHERE u.id_usuario = ?', [$uid]
        );

        // Las que ya tiene se excluyen: si no, el mismo dispositivo podría
        // registrarse dos veces y quedarían credenciales huérfanas.
        $existentes = DB::select('SELECT credential_id FROM credencial_webauthn WHERE id_usuario = ?', [$uid]);

        return response()->json(['ok' => true, 'publicKey' => [
            'challenge' => WebAuthn::nuevoDesafio(),
            'rp' => ['name' => config('app.name'), 'id' => WebAuthn::rpId()],
            'user' => [
                'id' => WebAuthn::b64urlEncode('u' . $uid),
                'name' => $u->email ?: $u->username,
                'displayName' => trim($u->nombre . ' ' . $u->apellido),
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],     // ES256
                ['type' => 'public-key', 'alg' => -257],   // RS256
            ],
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',   // el sensor del propio equipo
                'userVerification' => 'required',
                // **`required` y no `preferred`, y de eso depende entrar sin
                // tipear el usuario.** Una credencial descubrible («resident
                // key») la guarda el propio autenticador junto con a quién
                // pertenece, así que el navegador la puede ofrecer sin que
                // nadie diga primero de quién es. Con `preferred` queda a
                // criterio del dispositivo, y si decide no guardarla el botón
                // de huella de la pantalla de ingreso no encuentra nada.
                //
                // Windows Hello, Touch ID y la huella de Android las admiten,
                // que son los autenticadores para los que está pensado esto
                // (`authenticatorAttachment: platform`).
                'residentKey' => 'required',
                'requireResidentKey' => true,
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'excludeCredentials' => array_map(
                fn ($r) => ['type' => 'public-key', 'id' => $r->credential_id], $existentes
            ),
        ],
        ]);
    }

    public function registrar(Request $request): JsonResponse
    {
        $uid = (int) session('uid');
        $p = $this->payload($request);

        try {
            [$credId, $pem] = WebAuthn::verificarRegistro(
                (string) ($p['clientDataJSON'] ?? ''),
                (string) ($p['attestationObject'] ?? '')
            );

            // **Cada cuenta tiene la suya, y no se pisan entre sí.**
            //
            // Antes esto borraba las credenciales de TODAS las demás cuentas,
            // así que Pablo registraba su huella y la de la clienta anterior
            // desaparecía: una persona le revocaba el acceso a otra sin
            // enterarse. Es el modelo equivocado — una credencial WebAuthn
            // apunta a una cuenta, y el navegador sabe cuál es cuál: al entrar
            // ofrece las guardadas y se entra a la que registró la elegida.
            //
            // Lo único que se reemplaza es lo que YA tenía ESTA cuenta, para
            // que quede una por cuenta: registrarla de nuevo es cambiar la
            // suya, no acumular. La transacción y el bloqueo de la cuenta hacen
            // que un registro concurrente tampoco deje dos credenciales o una
            // cuenta sin acceso si la inserción falla.
            WebAuthn::guardarCredencial($uid, $credId, $pem);

            Auditoria::registrar('BIOMETRICO_ALTA', 'Seguridad', 'credencial_webauthn', $uid,
                'Registró el ingreso con huella');

            $u = DB::selectOne(
                'SELECT u.username, pe.email FROM usuario u JOIN persona pe ON pe.id_persona = u.id_persona
                  WHERE u.id_usuario = ?', [$uid]
            );

            return response()->json([
                'ok' => true, 'email' => $u->email, 'username' => $u->username,
            ]);
        } catch (Throwable $ex) {
            return response()->json(['ok' => false, 'error' => $ex->getMessage()]);
        }
    }

    public function desactivar(): JsonResponse
    {
        $uid = (int) session('uid');

        DB::delete('DELETE FROM credencial_webauthn WHERE id_usuario = ?', [$uid]);
        DB::statement(
            'INSERT INTO preferencia_usuario (id_usuario, biometrico_activo, biometrico_pregunt) VALUES (?,0,1)
             ON DUPLICATE KEY UPDATE biometrico_activo = 0', [$uid]
        );

        Auditoria::registrar('BIOMETRICO_BAJA', 'Seguridad', 'credencial_webauthn', $uid,
            'Desactivó el ingreso con huella');

        return response()->json(['ok' => true]);
    }

    // ---------- Entrar con la huella (sin sesión) ----------

    public function opcionesLogin(Request $request): JsonResponse
    {
        $p = $this->payload($request);
        $login = trim((string) ($p['login'] ?? ''));

        // **Sin usuario también se puede entrar, y era lo que faltaba.** Antes
        // el botón de huella sólo aparecía si ESTE navegador recordaba una
        // cuenta en `localStorage`: en otra computadora, con los datos del sitio
        // borrados o en una ventana privada, había que tipear usuario y
        // contraseña otra vez — o sea que la huella servía justo cuando ya no
        // hacía falta.
        //
        // Con la lista de credenciales vacía, el navegador ofrece las que el
        // autenticador tenga guardadas para este sitio y la persona elige. El
        // sistema no necesita saber de quién es: la credencial que vuelve trae
        // su id, y `login()` la resuelve contra `credencial_webauthn`. Por eso
        // **sólo se entra a la cuenta que la registró** — el id apunta a una
        // sola fila y a un solo `id_usuario`.
        if ($login === '') {
            if (! WebAuthn::hayAlguna()) {
                return response()->json(['ok' => false, 'error' => 'Sin credenciales.']);
            }

            return response()->json(['ok' => true, 'publicKey' => [
                'challenge' => WebAuthn::nuevoDesafio(),
                'timeout' => 60000,
                'rpId' => WebAuthn::rpId(),
                'userVerification' => 'required',
                // Vacía a propósito: que el autenticador ofrezca las suyas.
                'allowCredentials' => [],
            ]]);
        }

        $u = DB::selectOne(
            'SELECT u.id_usuario FROM usuario u JOIN persona pe ON pe.id_persona = u.id_persona
              WHERE (u.username = ? OR pe.email = ?) AND u.activo = 1 LIMIT 1', [$login, $login]
        );
        // El mismo mensaje en los dos casos: decir «ese usuario no existe»
        // delataría qué cuentas están registradas.
        if (! $u) {
            return response()->json(['ok' => false, 'error' => 'Sin credenciales.']);
        }

        $creds = DB::select('SELECT credential_id FROM credencial_webauthn WHERE id_usuario = ?', [(int) $u->id_usuario]);
        if (! $creds) {
            return response()->json(['ok' => false, 'error' => 'Sin credenciales.']);
        }

        return response()->json(['ok' => true, 'publicKey' => [
            'challenge' => WebAuthn::nuevoDesafio(),
            'timeout' => 60000,
            'rpId' => WebAuthn::rpId(),
            'userVerification' => 'required',
            'allowCredentials' => array_map(
                fn ($c) => ['type' => 'public-key', 'id' => $c->credential_id], $creds
            ),
        ]]);
    }

    public function login(Request $request): JsonResponse
    {
        $p = $this->payload($request);

        $cred = DB::selectOne(
            'SELECT id_credencial, id_usuario, public_key FROM credencial_webauthn WHERE credential_id = ?',
            [(string) ($p['credentialId'] ?? '')]
        );
        if (! $cred) {
            return response()->json(['ok' => false, 'error' => 'Credencial desconocida.']);
        }

        $ok = WebAuthn::verificarAsercion(
            (string) ($p['clientDataJSON'] ?? ''),
            (string) ($p['authenticatorData'] ?? ''),
            (string) ($p['signature'] ?? ''),
            (string) $cred->public_key
        );
        if (! $ok) {
            return response()->json(['ok' => false, 'error' => 'No se pudo validar la huella.']);
        }

        if (! Sesion::iniciarPorId((int) $cred->id_usuario)) {
            return response()->json(['ok' => false, 'error' => 'La cuenta no está activa.']);
        }

        Auditoria::registrar('LOGIN_BIOMETRICO', 'Seguridad', 'usuario', (int) $cred->id_usuario,
            'Inicio de sesión con huella');

        return response()->json(['ok' => true, 'redirect' => route(Sesion::inicio())]);
    }

    /** El navegador manda todo dentro de un campo `payload` en JSON. */
    private function payload(Request $request): array
    {
        $d = json_decode((string) $request->input('payload', ''), true);

        return is_array($d) ? $d : [];
    }
}
