<?php

declare(strict_types=1);

namespace App\Servicios;

use App\Mail\CodigoSeguridad;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Códigos de un solo uso que llegan al correo.
 *
 * Los usan tres caminos distintos, cada uno con su tipo de token para que un
 * código no sirva para otra cosa:
 *
 *   VERIFICACION     · terminar de crear la cuenta
 *   RECUPERACION     · «me olvidé la contraseña»
 *   CAMBIO_PASSWORD  · segundo factor al cambiarla desde Mi cuenta
 *
 * El código vive 30 minutos y se quema al usarse. Al pedir uno nuevo, los
 * anteriores del mismo tipo se invalidan: si no, quedarían varios válidos a la
 * vez y bastaría con adivinar cualquiera.
 */
class Seguridad
{
    private const MINUTOS = 30;

    /**
     * Genera un código, lo guarda y lo manda por correo.
     * Devuelve true si el envío salió bien.
     *
     * @param  string|null  $dato  valor pendiente de confirmar (un teléfono nuevo, por ejemplo)
     */
    public static function enviarCodigo(int $idUsuario, string $tipo, ?string $email, string $nombre = '', ?string $dato = null): bool
    {
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $codigo = self::generar();

        DB::update('UPDATE token_seguridad SET usado = 1 WHERE id_usuario = ? AND tipo = ? AND usado = 0',
            [$idUsuario, $tipo]);
        DB::insert(
            'INSERT INTO token_seguridad (id_usuario,tipo,codigo,expira_en,canal,dato)
             VALUES (?,?,?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?, ?)',
            [$idUsuario, $tipo, $codigo, self::MINUTOS, 'EMAIL', $dato]
        );

        try {
            Mail::to($email)->send(new CodigoSeguridad($tipo, $codigo, $nombre, self::MINUTOS));

            return true;
        } catch (Throwable $e) {
            // El código ya está guardado: se puede reenviar sin generar otro
            report($e);

            return false;
        }
    }

    /** Valida un código; si es correcto lo quema y devuelve true. */
    public static function validarCodigo(int $idUsuario, string $tipo, string $codigo): bool
    {
        $id = DB::scalar(
            'SELECT id_token FROM token_seguridad
              WHERE id_usuario = ? AND tipo = ? AND codigo = ? AND usado = 0 AND expira_en >= NOW()
              ORDER BY id_token DESC LIMIT 1',
            [$idUsuario, $tipo, $codigo]
        );
        if (! $id) {
            return false;
        }

        DB::update('UPDATE token_seguridad SET usado = 1 WHERE id_token = ?', [$id]);

        return true;
    }

    /** Invalida los códigos pendientes de un tipo (al cancelar un pedido). */
    public static function quemar(int $idUsuario, string $tipo): void
    {
        DB::update('UPDATE token_seguridad SET usado = 1 WHERE id_usuario = ? AND tipo = ? AND usado = 0',
            [$idUsuario, $tipo]);
    }

    /** ¿Hay forma de mandar correo? Si no, no tiene sentido ofrecer el paso. */
    public static function correoConfigurado(): bool
    {
        return config('mail.default') !== null
            && (config('mail.default') === 'log' || (string) config('mail.mailers.smtp.username') !== '');
    }

    /** Seis dígitos: se dicta por teléfono sin equivocarse. */
    private static function generar(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
