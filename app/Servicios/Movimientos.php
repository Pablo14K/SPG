<?php

namespace App\Servicios;

use Illuminate\Support\Facades\DB;

/**
 * Todo lo que movió plata, venga del cajón o del banco: las cuatro fuentes
 * que suman `fn_caja_saldo` y `fn_cuenta_saldo`, como consultas sueltas.
 *
 * **Una consulta por fuente, unidas con UNION.** Cada tabla nombra distinto
 * lo que pasó —un cobro tiene medio de pago, una liquidación tiene a quién
 * se le pagó— y forzarlas a un solo JOIN daría filas duplicadas y un `CASE`
 * de veinte líneas.
 *
 * **Vive acá y no en un controlador porque la leen tres pantallas**: el
 * listado de Movimientos con sus filtros, el modal del día de cada caja y el
 * modal del día de cada cuenta bancaria. Escrita dos veces, una de las dos
 * se queda atrás — que es el error que este proyecto ya se hizo con
 * `datos_demo.sql` y con el espejo de la agenda.
 *
 * Desde la 7.121.0 cada fila dice DÓNDE pasó: en un cajón —efectivo— o en
 * una cuenta bancaria. Un cobro por transferencia cae en la cuenta aunque se
 * haya registrado en el puesto de una caja: `id_caja` dice dónde se
 * atendió, `id_cuenta` dice a dónde fue la plata, y para esta pantalla lo
 * que importa es lo segundo.
 */
class Movimientos
{
    /** Los tipos de medio que caen en una cuenta y no en el cajón. */
    public const TIPOS_BANCARIOS = ['BANCO', 'CHEQUE', 'OTRO'];

    /**
     * Las cuatro fuentes, cada una como un SELECT con las mismas columnas.
     *
     * `$filtros($campoFecha, $sufijo, $alias)` devuelve el WHERE de esa
     * fuente; recibe el alias de la tabla principal para poder mirar su
     * `id_cuenta`. `$buscar($campos, $sufijo)` devuelve el LIKE, o vacío.
     *
     * **Cada fuente lleva sus PROPIOS marcadores.** La conexión abre PDO con
     * `ATTR_EMULATE_PREPARES` en `false`, así que MySQL prepara de verdad y no
     * admite `:cf` cuatro veces en la misma sentencia — por eso el sufijo.
     *
     * @param  callable(string,string,string):string  $filtros
     * @param  callable(array,string):string          $buscar
     * @return string[]
     */
    public static function partes(string $clase, callable $filtros, callable $buscar): array
    {
        // Dónde pasó: la cuenta si la plata fue al banco, el cajón si no. La
        // cuenta va primero a propósito — un cobro por transferencia se
        // registra en el puesto de una caja, pero la plata no está ahí.
        $donde = "COALESCE(CONCAT(cb.entidad, IF(cb.numero_cuenta IS NULL OR cb.numero_cuenta = '', '',
                                  CONCAT(' · ', cb.numero_cuenta))), cf.nombre)";
        $esBanco = 'IF(cb.id_cuenta IS NULL, 0, 1)';

        $partes = [];

        if ($clase === '' || $clase === 'cobro') {
            $partes[] = "SELECT 'cobro' AS clase, co.fecha AS cuando, $donde AS donde, $esBanco AS es_banco,
                                CONCAT('Cobro', COALESCE(CONCAT(' · ', pe.nombre, ' ', COALESCE(pe.apellido,'')), '')) AS detalle,
                                mp.nombre AS medio, co.monto AS monto, 1 AS signo,
                                TRIM(CONCAT_WS(' ', pu.nombre, pu.apellido)) AS quien,
                                1 AS activo, NULL AS motivo, co.id_cobro AS id_ref
                           FROM cobro co
                           LEFT JOIN caja c ON c.id_caja = co.id_caja
                           LEFT JOIN caja_fisica cf ON cf.id_caja_fisica = c.id_caja_fisica
                           LEFT JOIN cuenta_bancaria cb ON cb.id_cuenta = co.id_cuenta
                           JOIN metodo_pago mp ON mp.id_metodo_pago = co.id_metodo_pago
                           LEFT JOIN usuario u ON u.id_usuario = co.id_usuario
                           LEFT JOIN persona pu ON pu.id_persona = u.id_persona
                           LEFT JOIN factura fa ON fa.id_factura = co.id_factura
                           LEFT JOIN cita ci ON ci.id_cita = COALESCE(co.id_cita, fa.id_cita)
                           LEFT JOIN cliente cl ON cl.id_cliente = COALESCE(ci.id_cliente, fa.id_cliente)
                           LEFT JOIN persona pe ON pe.id_persona = cl.id_persona
                          WHERE co.id_estado_cobro = 1 AND " . $filtros('co.fecha', 'cobro', 'co')
                . $buscar(['pe.nombre', 'mp.nombre'], 'co');
        }

        if ($clase === '' || $clase === 'manual') {
            $partes[] = "SELECT 'manual' AS clase, mc.fecha AS cuando, $donde AS donde, $esBanco AS es_banco,
                                CONCAT(COALESCE(tmc.nombre, mc.tipo), ' · ', mc.concepto) AS detalle,
                                IF(cb.id_cuenta IS NULL, 'Efectivo', 'Banco') AS medio, mc.monto AS monto,
                                IF(mc.tipo = 'INGRESO', 1, -1) AS signo,
                                TRIM(CONCAT_WS(' ', pu.nombre, pu.apellido)) AS quien,
                                mc.activo AS activo, mc.anulado_motivo AS motivo,
                                mc.id_movimiento_caja AS id_ref
                           FROM movimiento_caja mc
                           LEFT JOIN caja c ON c.id_caja = mc.id_caja
                           LEFT JOIN caja_fisica cf ON cf.id_caja_fisica = c.id_caja_fisica
                           LEFT JOIN cuenta_bancaria cb ON cb.id_cuenta = mc.id_cuenta
                           LEFT JOIN tipo_movimiento_caja tmc ON tmc.id_tipo_mov_caja = mc.id_tipo_mov_caja
                           LEFT JOIN usuario u ON u.id_usuario = mc.id_usuario
                           LEFT JOIN persona pu ON pu.id_persona = u.id_persona
                          WHERE " . $filtros('mc.fecha', 'manual', 'mc')
                . $buscar(['mc.concepto', 'mc.nro_comprobante'], 'mc');
        }

        if ($clase === '' || $clase === 'prov') {
            $partes[] = "SELECT 'prov' AS clase, pp.fecha AS cuando, $donde AS donde, $esBanco AS es_banco,
                                CONCAT('Pago a proveedor · ', COALESCE(ppe.nombre, 'sin nombre')) AS detalle,
                                mp.nombre AS medio, fn_pago_proveedor_monto(pp.id_pago_proveedor) AS monto,
                                -1 AS signo,
                                TRIM(CONCAT_WS(' ', pu.nombre, pu.apellido)) AS quien,
                                1 AS activo, NULL AS motivo, pp.id_pago_proveedor AS id_ref
                           FROM pago_proveedor pp
                           LEFT JOIN caja c ON c.id_caja = pp.id_caja
                           LEFT JOIN caja_fisica cf ON cf.id_caja_fisica = c.id_caja_fisica
                           LEFT JOIN cuenta_bancaria cb ON cb.id_cuenta = pp.id_cuenta
                           JOIN metodo_pago mp ON mp.id_metodo_pago = pp.id_metodo_pago
                           LEFT JOIN proveedor pr ON pr.id_proveedor = pp.id_proveedor
                           LEFT JOIN persona ppe ON ppe.id_persona = pr.id_persona
                           LEFT JOIN usuario u ON u.id_usuario = pp.id_usuario
                           LEFT JOIN persona pu ON pu.id_persona = u.id_persona
                          WHERE pp.id_estado_pago_proveedor = 1 AND " . $filtros('pp.fecha', 'prov', 'pp')
                . $buscar(['ppe.nombre', 'pp.referencia'], 'pp');
        }

        if ($clase === '' || $clase === 'pers') {
            $partes[] = "SELECT 'pers' AS clase, pl.fecha AS cuando, $donde AS donde, $esBanco AS es_banco,
                                CONCAT('Liquidación · ', TRIM(CONCAT_WS(' ', ppe.nombre, ppe.apellido))) AS detalle,
                                COALESCE(mp.nombre, 'Efectivo') AS medio,
                                fn_pago_personal_monto(pl.id_pago_personal) AS monto, -1 AS signo,
                                TRIM(CONCAT_WS(' ', pur.nombre, pur.apellido)) AS quien,
                                1 AS activo, NULL AS motivo,
                                pl.id_pago_personal AS id_ref
                           FROM pago_personal pl
                           LEFT JOIN caja c ON c.id_caja = pl.id_caja
                           LEFT JOIN caja_fisica cf ON cf.id_caja_fisica = c.id_caja_fisica
                           LEFT JOIN cuenta_bancaria cb ON cb.id_cuenta = pl.id_cuenta
                           LEFT JOIN metodo_pago mp ON mp.id_metodo_pago = pl.id_metodo_pago
                           LEFT JOIN usuario u ON u.id_usuario = pl.id_usuario
                           LEFT JOIN persona ppe ON ppe.id_persona = u.id_persona
                           LEFT JOIN usuario ur ON ur.id_usuario = pl.id_usuario_registro
                           LEFT JOIN persona pur ON pur.id_persona = ur.id_persona
                          WHERE pl.id_estado_pago = 1 AND " . $filtros('pl.fecha', 'pers', 'pl')
                . $buscar(['ppe.nombre'], 'pl');
        }

        return $partes;
    }

    /**
     * El filtro «de qué local», que toda fuente lleva.
     *
     * Sale de la cuenta si la plata fue al banco y del cajón si no: no hace
     * falta columna propia en cada tabla, y es lo mismo que decide el aislamiento
     * por sucursal del resto de Tesorería.
     */
    public static function enSucursales(array $ids): string
    {
        return 'COALESCE(cb.id_sucursal, c.id_sucursal) IN (' . implode(',', $ids ?: [0]) . ')';
    }

    /**
     * Los movimientos de HOY de un cajón, o de una cuenta.
     *
     * **Es la pregunta del mostrador, no la del informe.** «¿Qué entró y salió
     * de acá hoy?» se contesta de un vistazo y sin salir de la pantalla; para
     * mirar los de la semana pasada está Movimientos, con sus filtros.
     *
     * Los de un cajón son SÓLO los que quedaron en el cajón: lo que se cobró
     * por transferencia en ese puesto se lista en la cuenta a la que fue.
     *
     * @return array<int, object>
     */
    public static function delDia(?int $idCajaFisica, ?int $idCuenta = null): array
    {
        $par = [];
        $filtros = function (string $campoFecha, string $suf, string $alias) use (&$par, $idCajaFisica, $idCuenta): string {
            if ($idCuenta) {
                $par["cu_$suf"] = $idCuenta;
                $w = "$alias.id_cuenta = :cu_$suf";
            } else {
                $par["cf_$suf"] = (int) $idCajaFisica;
                $w = "$alias.id_cuenta IS NULL AND c.id_caja_fisica = :cf_$suf";
            }

            return "$w AND DATE($campoFecha) = CURDATE()";
        };
        $buscar = fn (array $campos, string $suf): string => '';

        $partes = self::partes('', $filtros, $buscar);
        $union = '(' . implode(') UNION ALL (', $partes) . ')';

        return DB::select("SELECT * FROM ($union) t ORDER BY t.cuando DESC LIMIT 60", $par);
    }
}
