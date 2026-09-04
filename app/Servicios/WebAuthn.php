<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Ingreso con huella (WebAuthn), en PHP puro.
 *
 * Decodificador CBOR mínimo, extracción de la clave pública COSE (ES256 y
 * RS256) a PEM, y verificación de la firma con OpenSSL. **Sin librerías
 * externas**: es de las pocas cosas del sistema que no se puede hacer con
 * PHP, MySQL, HTML y CSS solos —el acceso al sensor lo da el navegador—, así
 * que al menos la parte del servidor no agrega dependencias.
 *
 * Pensado para autenticadores de plataforma: Windows Hello, Touch ID, huella
 * de Android.
 *
 * OJO AL PUBLICAR: el navegador solo habilita WebAuthn sobre HTTPS, con la
 * única excepción de `localhost`. Además el `rpId` queda atado al dominio, así
 * que **las credenciales registradas en desarrollo no sirven en producción**:
 * cada persona vuelve a registrar su huella la primera vez.
 */
class WebAuthn
{
    // -----------------------------------------------------------------
    //  Base64 en su variante para URL (sin +, / ni =)
    // -----------------------------------------------------------------

    public static function b64urlEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function b64urlDecode(string $txt): string
    {
        $txt = strtr($txt, '-_', '+/');
        $pad = strlen($txt) % 4;
        if ($pad) {
            $txt .= str_repeat('=', 4 - $pad);
        }

        return base64_decode($txt) ?: '';
    }

    /** Identidad de la parte confiante: el dominio, sin el puerto. */
    public static function rpId(): string
    {
        return preg_replace('/:\d+$/', '', request()->getHost());
    }

    public static function origin(): string
    {
        return request()->getSchemeAndHttpHost();
    }

    /** ¿Hay alguna huella registrada en el sistema? */
    public static function hayAlguna(): bool
    {
        return (bool) DB::scalar('SELECT COUNT(*) FROM credencial_webauthn');
    }

    /**
     * Guarda la credencial de una cuenta, reemplazando sólo la que esa misma
     * cuenta ya tenía. El bloqueo evita que dos registros simultáneos de la
     * misma cuenta terminen acumulando credenciales.
     */
    public static function guardarCredencial(int $idUsuario, string $credentialId, string $publicKey): void
    {
        DB::transaction(function () use ($idUsuario, $credentialId, $publicKey): void {
            DB::selectOne('SELECT id_usuario FROM usuario WHERE id_usuario = ? FOR UPDATE', [$idUsuario]);
            DB::delete('DELETE FROM credencial_webauthn WHERE id_usuario = ?', [$idUsuario]);
            DB::insert(
                'INSERT INTO credencial_webauthn (id_usuario, credential_id, public_key, etiqueta) VALUES (?,?,?,?)',
                [$idUsuario, $credentialId, $publicKey, 'Dispositivo']
            );
            DB::statement(
                'INSERT INTO preferencia_usuario (id_usuario, biometrico_activo, biometrico_pregunt) VALUES (?,1,1)
                 ON DUPLICATE KEY UPDATE biometrico_activo = 1, biometrico_pregunt = 1', [$idUsuario]
            );
        });
    }

    // -----------------------------------------------------------------
    //  Ceremonias
    // -----------------------------------------------------------------

    /** Un desafío nuevo, guardado en la sesión para comprobarlo después. */
    public static function nuevoDesafio(): string
    {
        $ch = self::b64urlEncode(random_bytes(32));
        session(['webauthn_challenge' => $ch]);

        return $ch;
    }

    /**
     * Comprueba el clientDataJSON: que sea la ceremonia esperada, que el
     * desafío sea el que mandamos y que el origen sea el nuestro. Sin esto,
     * una firma capturada en otro sitio podría reusarse acá.
     */
    public static function verificarClientData(string $clientDataJSON, string $tipoEsperado): bool
    {
        return self::motivoClientData($clientDataJSON, $tipoEsperado) === null;
    }

    /**
     * Lo mismo, pero diciendo **cuál** de las cuatro comprobaciones falló.
     * Devuelve null si pasó todo.
     *
     * Las cuatro fallaban con el mismo «Datos del cliente inválidos», y eso es
     * exactamente el error que este proyecto tiene anotado como propio: un
     * mensaje que no distingue manda a mirar el lugar equivocado. Las causas no
     * se parecen en nada —una es de configuración, otra de sesión, otra es un
     * intento de reusar una firma— y cada una se arregla en otro lado:
     *
     *   · **origen distinto**: se entró por una dirección que no es la
     *     configurada (la IP de la red, otro subdominio), o el proxy no manda
     *     `X-Forwarded-Proto` y el sistema se cree en `http` mientras el
     *     navegador está en `https`;
     *   · **desafío que no coincide**: la sesión cambió entre pedir las opciones
     *     y mandar la respuesta — se abrió el registro, se dejó la pantalla
     *     abierta y la sesión venció, o hay dos pestañas peleando;
     *   · **sin desafío en la sesión**: la cookie no volvió. Con
     *     `SESSION_SECURE_COOKIE=true` sobre una conexión que el sistema ve en
     *     claro, no vuelve nunca;
     *   · **otra ceremonia**: llegó una respuesta de login a la ruta de registro
     *     o al revés.
     */
    /**
     * El `clientDataJSON` viaja en **base64url**, y hay que decodificarlo antes
     * de mirarlo.
     *
     * Es el defecto que tenía rota la huella desde que existe: el navegador
     * manda `bufToB64url(cred.response.clientDataJSON)` —o sea texto base64— y
     * el servidor le hacía `json_decode` directamente, que sobre base64 nunca
     * devuelve un arreglo. De ahí salía el «Datos del cliente inválidos» que no
     * se podía diagnosticar: la ceremonia del navegador estaba perfecta y lo
     * que fallaba era la lectura.
     *
     * **Y en el login rompe algo peor que un mensaje**: la firma se calcula
     * sobre `authenticatorData || SHA256(clientDataJSON)`, con los BYTES
     * originales. Hasheando el base64 el resultado no coincide nunca, así que
     * ninguna huella podía validar.
     *
     * Es tolerante a propósito: si algún cliente mandara el JSON en claro se usa
     * tal cual, en vez de destrozarlo con un base64_decode que no corresponde.
     */
    public static function clientData(string $recibido): string
    {
        $txt = trim($recibido);

        return str_starts_with($txt, '{') ? $txt : self::b64urlDecode($txt);
    }

    public static function motivoClientData(string $clientDataJSON, string $tipoEsperado): ?string
    {
        $cd = json_decode($clientDataJSON, true);
        if (! is_array($cd)) {
            return 'El navegador no mandó los datos de la ceremonia.';
        }
        if (($cd['type'] ?? '') !== $tipoEsperado) {
            return 'Llegó una respuesta de otra ceremonia («' . (string) ($cd['type'] ?? 'nada')
                . '» en vez de «' . $tipoEsperado . '»).';
        }

        $esperado = (string) session('webauthn_challenge', '');
        if ($esperado === '') {
            return 'Se perdió el desafío de la sesión: volvé a cargar la pantalla e intentá otra vez. '
                . 'Si vuelve a pasar, la cookie de sesión no está volviendo al servidor.';
        }
        if (! hash_equals($esperado, (string) ($cd['challenge'] ?? ''))) {
            return 'El desafío no es el que mandamos: la pantalla quedó abierta demasiado tiempo '
                . 'o hay otra pestaña registrando al mismo tiempo. Recargá e intentá de nuevo.';
        }

        $origen = (string) ($cd['origin'] ?? '');
        if ($origen !== self::origin()) {
            return 'El navegador dice que está en «' . ($origen ?: 'nada') . '» y el sistema se ve '
                . 'a sí mismo en «' . self::origin() . '». Entrá por la dirección configurada '
                . '(la de APP_URL); una IP de la red local no sirve para la huella.';
        }

        return null;
    }

    /**
     * Registro: valida la respuesta del autenticador y devuelve
     * [credentialId en b64url, clave pública en PEM].
     */
    public static function verificarRegistro(string $clientDataJSON, string $attestationObjectB64): array
    {
        $cd = self::clientData($clientDataJSON);

        $motivo = self::motivoClientData($cd, 'webauthn.create');
        if ($motivo !== null) {
            throw new RuntimeException($motivo);
        }

        $att = self::cborDecode(self::b64urlDecode($attestationObjectB64));
        $parsed = self::parseAuthData($att['authData']);

        if (! hash_equals(substr($parsed['rpIdHash'], 0, 32), hash('sha256', self::rpId(), true))) {
            throw new RuntimeException('El dominio no coincide con el de la credencial.');
        }
        if (! ($parsed['flags'] & 0x01)) {
            throw new RuntimeException('El autenticador no confirmó que estuvieras presente.');
        }
        if (! $parsed['cose']) {
            throw new RuntimeException('No se recibió la clave pública.');
        }

        [$pem] = self::coseAPem($parsed['cose']);

        return [self::b64urlEncode($parsed['credId']), $pem];
    }

    /** Aserción (login): verifica la firma con la clave guardada. */
    public static function verificarAsercion(string $clientDataJSON, string $authenticatorDataB64, string $signatureB64, string $publicKeyPem): bool
    {
        // Acá se devuelve un booleano a propósito: al entrar, decirle al
        // visitante POR QUÉ no validó le da información sobre credenciales que
        // no son suyas. Pero queda en el log, que es donde hace falta para
        // arreglarlo.
        $cd = self::clientData($clientDataJSON);

        $motivo = self::motivoClientData($cd, 'webauthn.get');
        if ($motivo !== null) {
            Log::warning('SPG: la huella no validó — ' . $motivo);

            return false;
        }

        $authData = self::b64urlDecode($authenticatorDataB64);
        if (strlen($authData) < 37) {
            return false;
        }
        if (! (ord($authData[32]) & 0x01)) {
            return false;   // el usuario no estaba presente
        }
        if (! hash_equals(substr($authData, 0, 32), hash('sha256', self::rpId(), true))) {
            return false;
        }

        $firmado = $authData . hash('sha256', $cd, true);

        return openssl_verify($firmado, self::b64urlDecode($signatureB64), $publicKeyPem, OPENSSL_ALGO_SHA256) === 1;
    }

    // -----------------------------------------------------------------
    //  CBOR — el subconjunto que hace falta para el attestationObject
    //  y la clave COSE
    // -----------------------------------------------------------------

    public static function cborDecode(string $data, int &$off = 0): mixed
    {
        $b = ord($data[$off++]);
        $major = $b >> 5;
        $info = $b & 0x1f;

        $largo = function (int $info) use ($data, &$off): int {
            if ($info < 24) {
                return $info;
            }
            if ($info === 24) {
                return ord($data[$off++]);
            }
            if ($info === 25) {
                $v = unpack('n', substr($data, $off, 2))[1];
                $off += 2;

                return $v;
            }
            if ($info === 26) {
                $v = unpack('N', substr($data, $off, 4))[1];
                $off += 4;

                return $v;
            }
            if ($info === 27) {
                $hi = unpack('N', substr($data, $off, 4))[1];
                $lo = unpack('N', substr($data, $off + 4, 4))[1];
                $off += 8;

                return ($hi << 32) | $lo;
            }
            throw new RuntimeException('CBOR: longitud no soportada');
        };

        return match ($major) {
            0 => $largo($info),                    // entero sin signo
            1 => -1 - $largo($info),               // entero negativo
            2, 3 => (function () use ($data, &$off, $largo, $info) {   // bytes / texto
                $n = $largo($info);
                $s = substr($data, $off, $n);
                $off += $n;

                return $s;
            })(),
            4 => (function () use ($data, &$off, $largo, $info) {      // arreglo
                $n = $largo($info);
                $a = [];
                for ($i = 0; $i < $n; $i++) {
                    $a[] = self::cborDecode($data, $off);
                }

                return $a;
            })(),
            5 => (function () use ($data, &$off, $largo, $info) {      // mapa
                $n = $largo($info);
                $m = [];
                for ($i = 0; $i < $n; $i++) {
                    $k = self::cborDecode($data, $off);
                    $m[$k] = self::cborDecode($data, $off);
                }

                return $m;
            })(),
            default => throw new RuntimeException('CBOR: tipo no soportado (' . $major . ')'),
        };
    }

    /** Separa el authenticatorData en sus partes. */
    private static function parseAuthData(string $authData): array
    {
        $out = [
            'rpIdHash' => substr($authData, 0, 32),
            'flags' => ord($authData[32]),
            'signCount' => unpack('N', substr($authData, 33, 4))[1],
            'credId' => null,
            'cose' => null,
        ];

        // Bit AT: trae los datos de la credencial recién creada
        if ($out['flags'] & 0x40) {
            $off = 37 + 16;   // 37 de cabecera + 16 del AAGUID
            $credLen = unpack('n', substr($authData, $off, 2))[1];
            $off += 2;
            $out['credId'] = substr($authData, $off, $credLen);
            $off += $credLen;
            $out['cose'] = self::cborDecode($authData, $off);
        }

        return $out;
    }

    // -----------------------------------------------------------------
    //  ASN.1 / DER — para armar la clave pública en formato PEM, que es
    //  lo que entiende OpenSSL
    // -----------------------------------------------------------------

    /** Convierte una clave COSE en PEM. Devuelve [pem, alg(-7|-257)]. */
    private static function coseAPem(array $cose): array
    {
        $kty = $cose[1] ?? null;

        if ($kty === 2) {   // EC2 · ES256
            $oidEc = self::derTlv(0x06, "\x2a\x86\x48\xce\x3d\x02\x01");        // 1.2.840.10045.2.1
            $oidP256 = self::derTlv(0x06, "\x2a\x86\x48\xce\x3d\x03\x01\x07");  // 1.2.840.10045.3.1.7
            $punto = "\x04" . $cose[-2] . $cose[-3];
            $spki = self::derSeq(self::derSeq($oidEc . $oidP256) . self::derBitStr($punto));

            return [self::derPem($spki), -7];
        }

        if ($kty === 3) {   // RSA · RS256
            $oidRsa = self::derTlv(0x06, "\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01");  // 1.2.840.113549.1.1.1
            $clave = self::derSeq(self::derInt($cose[-1]) . self::derInt($cose[-2]));
            $spki = self::derSeq(self::derSeq($oidRsa . "\x05\x00") . self::derBitStr($clave));

            return [self::derPem($spki), -257];
        }

        throw new RuntimeException('Tipo de clave no soportado (kty=' . var_export($kty, true) . ')');
    }

    private static function derLen(int $n): string
    {
        if ($n < 128) {
            return chr($n);
        }
        $out = '';
        while ($n > 0) {
            $out = chr($n & 0xff) . $out;
            $n >>= 8;
        }

        return chr(0x80 | strlen($out)) . $out;
    }

    private static function derTlv(int $tag, string $val): string
    {
        return chr($tag) . self::derLen(strlen($val)) . $val;
    }

    private static function derSeq(string $v): string
    {
        return self::derTlv(0x30, $v);
    }

    private static function derBitStr(string $v): string
    {
        return self::derTlv(0x03, "\x00" . $v);
    }

    private static function derInt(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '') {
            $bytes = "\x00";
        }
        if (ord($bytes[0]) & 0x80) {
            $bytes = "\x00" . $bytes;   // que no se lea como negativo
        }

        return self::derTlv(0x02, $bytes);
    }

    private static function derPem(string $spki): string
    {
        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($spki), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }
}
