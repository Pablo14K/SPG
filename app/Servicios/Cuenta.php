<?php

namespace App\Servicios;

use Illuminate\Support\Facades\DB;

/**
 * La cuenta bancaria DEL SALÓN (`cuenta_bancaria`): la que se administra en
 * «Tesorería → Cuenta bancaria», a la que caen las transferencias de las
 * clientas —las señas incluidas— y de la que salen las transferencias a
 * proveedores y al personal.
 *
 * **No confundirla con «cuentas por pagar»**, que es lo que el salón le debe a
 * un proveedor.
 *
 * **Es una CAJA dedicada al banco** (7.121.0, pedido del usuario): tiene su
 * saldo, sus movimientos y su arqueo —declarar cuánto dice el banco—, y la
 * caja de siempre queda para el efectivo. Hasta la 7.120.0 se llamaba
 * `dato_pago_sucursal` y era sólo «a dónde le decimos a la clienta que
 * transfiera»; el saldo que ganó en la 7.110.0 era un PISO porque el sistema
 * no veía lo que entraba. Ahora lo ve: `cobro.id_cuenta` dice a qué cuenta
 * cayó cada transferencia y `fn_cuenta_saldo` lo suma.
 *
 * Sigue siendo una cuenta del banco y no del sistema, así que puede haber
 * más de lo que dice —un depósito hecho por fuera— y por eso el control de
 * los pagos **avisa y no bloquea**, al revés que el efectivo, que es exacto.
 *
 * **NULL no es cero.** Una cuenta que nadie declaró no vale «está vacía»:
 * vale «no se sabe», y entonces no hay nada que avisar — y la campanita dice
 * que falta declararla.
 */
class Cuenta
{
    /** Los tipos de medio de pago cuya plata cae en una cuenta y no en el cajón. */
    public const TIPOS_BANCARIOS = Movimientos::TIPOS_BANCARIOS;

    /** ¿Ese medio de pago deja la plata en el banco (o la billetera)? */
    public static function esBancario(int $idMetodoPago): bool
    {
        if (! $idMetodoPago) {
            return false;
        }
        $tipo = (string) DB::scalar('SELECT tipo FROM metodo_pago WHERE id_metodo_pago = ?', [$idMetodoPago]);

        return in_array($tipo, self::TIPOS_BANCARIOS, true);
    }

    /**
     * Las cuentas de un local, con lo que el sistema puede afirmar de cada una.
     * `saldo` viene en NULL cuando nadie la declaró.
     *
     * Con `$soloActivas` en falso trae también las dadas de baja: es lo que
     * dibuja la pantalla que las administra, donde una cuenta desactivada se
     * tiene que poder volver a activar.
     */
    public static function deSucursal(int $sucursal, bool $soloActivas = true): array
    {
        if (! $sucursal) {
            return [];
        }

        return DB::select(
            'SELECT d.id_cuenta, d.id_sucursal, d.id_metodo_pago, d.entidad, d.titular, d.documento,
                    d.tipo_cuenta, d.numero_cuenta, d.alias, d.alias_tipo, d.observacion,
                    d.saldo_declarado, d.saldo_declarado_en, d.orden, d.activo, d.para_senas,
                    fn_cuenta_saldo(d.id_cuenta) AS saldo,
                    m.nombre AS medio, m.tipo
               FROM cuenta_bancaria d
               JOIN metodo_pago m ON m.id_metodo_pago = d.id_metodo_pago
              WHERE d.id_sucursal = ?' . ($soloActivas ? ' AND d.activo = 1' : '') . '
              ORDER BY d.activo DESC, d.orden, d.id_cuenta', [$sucursal]
        );
    }

    /**
     * Las cuentas que se le muestran a la clienta para transferir la seña,
     * agrupadas por local.
     *
     * **Sólo las marcadas con «Usar para señas»** (7.121.0). Hasta acá se le
     * mostraban todas las activas, y una cuenta puede existir para pagarle a
     * proveedores sin ser a la que el salón quiere que le transfieran.
     *
     * @param  int[]  $sucursales
     * @return array<int, array<int, object>>
     */
    public static function paraSenas(array $sucursales): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $sucursales))));
        if (! $ids) {
            return [];
        }

        $filas = DB::select(
            'SELECT d.id_sucursal, d.entidad, d.titular, d.documento, d.tipo_cuenta,
                    d.numero_cuenta, d.alias, d.alias_tipo, d.observacion, m.nombre AS medio
               FROM cuenta_bancaria d
               JOIN metodo_pago m ON m.id_metodo_pago = d.id_metodo_pago
              WHERE d.activo = 1 AND d.para_senas = 1 AND d.id_sucursal IN ('
                . implode(',', array_fill(0, count($ids), '?')) . ')
              ORDER BY d.orden, d.id_cuenta', $ids
        );

        $por = [];
        foreach ($filas as $f) {
            $por[(int) $f->id_sucursal][] = $f;
        }

        return $por;
    }

    /** Cuánto queda, o null si nadie declaró el saldo de esa cuenta. */
    public static function saldo(int $id): ?float
    {
        if (! $id) {
            return null;
        }

        $v = DB::scalar('SELECT fn_cuenta_saldo(?)', [$id]);

        return $v === null ? null : (float) $v;
    }

    /**
     * La cuenta que eligió la pantalla, validada contra el local del que tiene
     * que salir —o al que tiene que entrar— la plata.
     *
     * **El id viaja en el formulario, así que no se cree**: es la misma regla
     * con la que `cajaElegida()` comprueba el cajón. Devuelve 0 cuando no se
     * eligió ninguna, que es lo normal al pagar en efectivo.
     */
    public static function valida(int $id, int $sucursal): int
    {
        if (! $id || ! $sucursal) {
            return 0;
        }

        return (int) DB::scalar(
            'SELECT COUNT(*) FROM cuenta_bancaria
              WHERE id_cuenta = ? AND id_sucursal = ? AND activo = 1', [$id, $sucursal]
        ) ? $id : 0;
    }

    /**
     * La única cuenta activa del local, si hay exactamente una: es la que se
     * toma sin preguntar cuando la pantalla no mandó ninguna.
     */
    public static function unicaDe(int $sucursal): int
    {
        $activas = array_filter(self::deSucursal($sucursal), fn ($c) => (int) $c->activo === 1);

        return count($activas) === 1 ? (int) reset($activas)->id_cuenta : 0;
    }

    /**
     * El aviso cuando el pago se lleva más de lo que la cuenta tiene, o cadena
     * vacía si no hay nada que decir.
     *
     * **Avisa, no impide.** Ver el porqué arriba: puede haber más de lo que el
     * sistema sabe, nunca menos.
     */
    public static function aviso(int $id, float $monto): string
    {
        $saldo = self::saldo($id);
        if ($saldo === null || $monto <= $saldo + 0.01) {
            return '';
        }

        return 'Ojo: según lo declarado y lo que entró y salió desde entonces, en esa cuenta hay '
            . money($saldo) . ' y este pago es de ' . money($monto)
            . '. Puede haber un depósito que el sistema no vio — pero conviene comprobarlo'
            . ' en el banco antes de transferir.';
    }
}
