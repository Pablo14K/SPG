<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\DB;
use Throwable;

// El saldo, la apertura y el cierre los resuelve la base: acá sólo se llama.

/**
 * La caja del salón.
 *
 * Se trabaja con UNA sola caja abierta por vez en todo el salón, y sin caja
 * abierta no se mueve un guaraní: el movimiento quedaría fuera del arqueo y el
 * cierre no cerraría.
 *
 * El saldo lo calcula `fn_caja_saldo` en la base, y devuelve el EFECTIVO que
 * tiene que estar físicamente en el cajón para contarlo al cerrar: lo que
 * entra por tarjeta o transferencia se registra igual pero no toca el cajón.
 */
class Caja
{
    /**
     * `false` significa «todavía no se preguntó»; `null`, «no hay caja
     * abierta». Son dos cosas distintas: sin distinguirlas, cada pantalla que
     * mira la caja cerrada volvería a consultar la base.
     */
    private static object|false|null $cache = false;

    /** La caja abierta, con su saldo ya calculado por la base, o null. */
    public static function abierta(): ?object
    {
        if (self::$cache !== false) {
            return self::$cache;
        }
        try {
            self::$cache = DB::selectOne(
                "SELECT c.id_caja, c.id_usuario, c.fecha_apertura, c.monto_inicial,
                        CONCAT(pe_u.nombre,' ',pe_u.apellido) AS responsable,
                        fn_caja_saldo(c.id_caja) AS saldo
                   FROM caja c
                   JOIN usuario u   ON u.id_usuario = c.id_usuario
                   JOIN persona pe_u ON pe_u.id_persona = u.id_persona
                  WHERE c.id_estado_caja = 1
                    AND (:s = 0 OR c.id_sucursal = :s2)
                  ORDER BY c.fecha_apertura DESC LIMIT 1",
                // **La caja es del local, no del salón.** Cada sucursal cuenta
                // su propio cajón: sin este filtro, abrir la caja en un local
                // dejaba «caja abierta» en todos, y el arqueo de uno se comía
                // los cobros del otro. El 0 es la red para lo que corre sin
                // sesión —el cron, un comando—: ahí no se filtra.
                ['s' => Sucursales::activa(), 's2' => Sucursales::activa()]
            );
        } catch (Throwable) {
            self::$cache = null;
        }

        return self::$cache;
    }

    public static function olvidar(): void
    {
        self::$cache = false;
    }

    /**
     * Abre la caja de ESTA sucursal y devuelve su id.
     *
     * La caja es del local, no del salón: cada sucursal cuenta su propio
     * cajón. La sucursal va como parámetro y no deducida de quien abre,
     * porque la misma persona puede estar asignada a varias.
     */
    public static function abrir(int $idUsuario, float $montoInicial, ?int $idSucursal = null): int
    {
        $id = Bd::idDe('sp_abrir_caja', [$idUsuario, $montoInicial, $idSucursal ?? Sucursales::activa()]);
        self::olvidar();

        return $id;
    }

    /**
     * Cierra la caja con el arqueo: cuánto se contó y quién lo contó.
     *
     * **El conteo es lo que convierte el cierre en un arqueo.** Antes esto
     * sólo marcaba la caja como cerrada: el sistema sabía cuánto debería
     * haber y nunca preguntaba cuánto hay, así que no podía decir si cuadró.
     */
    public static function cerrar(int $idCaja, float $contado, int $idUsuario): void
    {
        Bd::procedimiento('sp_cerrar_caja', [$idCaja, $contado, $idUsuario]);
        self::olvidar();
    }

    /**
     * Sobrante (+) o faltante (−) de un arqueo. NULL si no se contó.
     *
     * **No se guarda: se calcula.** Es `contado − esperado`, o sea una
     * columna derivada, y guardarla la dejaría separarse del valor real el
     * día que se anule un movimiento viejo — la regla número dos.
     */
    public static function diferencia(int $idCaja): ?float
    {
        $d = Bd::funcion('fn_caja_diferencia(?)', [$idCaja]);

        return $d === null ? null : (float) $d;
    }

    /** Cuánto efectivo hay ahora mismo en el cajón. */
    public static function saldo(int $idCaja): float
    {
        return (float) Bd::funcion('fn_caja_saldo(?)', [$idCaja]);
    }

    /**
     * ¿Este medio de pago mueve el cajón?
     *
     * `fn_caja_saldo` sólo cuenta el efectivo: lo que entra por tarjeta o
     * transferencia va a la cuenta del salón, no al cajón que se cuenta al
     * cerrar. Lo mismo al salir — pagarle a un proveedor por transferencia no
     * saca un guaraní de la caja.
     */
    public static function esEfectivo(int $idMetodoPago): bool
    {
        return DB::scalar('SELECT tipo FROM metodo_pago WHERE id_metodo_pago = ?', [$idMetodoPago]) === 'EFECTIVO';
    }
}
