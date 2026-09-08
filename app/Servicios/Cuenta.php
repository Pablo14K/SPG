<?php

namespace App\Servicios;

use Illuminate\Support\Facades\DB;

/**
 * La cuenta bancaria DEL SALÓN: la que aparece en «Configuración → Datos de
 * pago» y de la que salen las transferencias a proveedores y al personal.
 *
 * **No confundirla con «cuentas por pagar»**, que es lo que el salón le debe a
 * un proveedor. Acá se contesta una sola pregunta: *¿hay plata en el banco para
 * pagar esto?*
 *
 * **Y la respuesta es un PISO, no un saldo.** El sistema conoce lo que SALE
 * —los pagos que él mismo registró— pero no lo que entra: una transferencia de
 * una clienta llega al banco sin pasar por acá. Así que `fn_cuenta_saldo` parte
 * de lo que el salón declaró haber visto y le resta lo que se pagó desde
 * entonces, con lo cual el número sólo puede quedar **por debajo** del real.
 *
 * Por eso esto **avisa y no bloquea**, al revés que el efectivo: el cajón es
 * exacto y rechazar un egreso mayor es correcto; acá bloquear con un número que
 * sabemos incompleto frenaría un pago legítimo.
 *
 * **NULL no es cero.** Una cuenta que nadie declaró no vale «está vacía»: vale
 * «no se sabe», y entonces no hay nada que avisar.
 */
class Cuenta
{
    /**
     * Las cuentas activas de un local, con lo que el sistema puede afirmar de
     * cada una. `saldo` viene en NULL cuando nadie la declaró.
     */
    public static function deSucursal(int $sucursal): array
    {
        if (! $sucursal) {
            return [];
        }

        return DB::select(
            'SELECT d.id_dato_pago, d.entidad, d.titular, d.numero_cuenta, d.alias,
                    d.saldo_declarado, d.saldo_declarado_en,
                    fn_cuenta_saldo(d.id_dato_pago) AS saldo,
                    m.nombre AS medio, m.tipo
               FROM dato_pago_sucursal d
               JOIN metodo_pago m ON m.id_metodo_pago = d.id_metodo_pago
              WHERE d.id_sucursal = ? AND d.activo = 1
              ORDER BY d.orden, d.id_dato_pago', [$sucursal]
        );
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
     * que salir la plata.
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
            'SELECT COUNT(*) FROM dato_pago_sucursal
              WHERE id_dato_pago = ? AND id_sucursal = ? AND activo = 1', [$id, $sucursal]
        ) ? $id : 0;
    }

    /**
     * El aviso cuando el pago se lleva más de lo que la cuenta declara tener,
     * o cadena vacía si no hay nada que decir.
     *
     * **Avisa, no impide.** Ver el porqué arriba: el saldo es un piso.
     */
    public static function aviso(int $id, float $monto): string
    {
        $saldo = self::saldo($id);
        if ($saldo === null || $monto <= $saldo + 0.01) {
            return '';
        }

        return 'Ojo: en esa cuenta el salón declaró ' . money($saldo)
            . ' y este pago es de ' . money($monto)
            . '. El sistema no ve lo que entra al banco, así que puede haber más'
            . ' — pero conviene comprobarlo antes de transferir.';
    }
}
