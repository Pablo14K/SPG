<?php
/**
 * Foto del estado de la base, para comparar el ANTES contra el DESPUÉS.
 * Se corre con:  php _sim60/estado.php inicial|final
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

use Illuminate\Support\Facades\DB;

$etiqueta = (string) ($argv[1] ?? 'foto');

$conteos = [
    'persona', 'cliente', 'usuario', 'cita', 'cita_servicio', 'servicio_realizado',
    'producto_utilizado', 'factura', 'detalle_factura', 'cobro', 'cobro_tarjeta', 'cobro_banco',
    'caja', 'movimiento_caja', 'compra', 'detalle_compra', 'compra_cuota', 'pago_proveedor',
    'pago_personal', 'detalle_pago_personal', 'movimiento_inventario', 'movimiento_punto',
    'asistencia', 'auditoria', 'notificacion', 'calificacion', 'cita_pedido', 'token_cita',
    'token_seguridad', 'sena_solicitud', 'canje', 'servicio_canjeable', 'descuento',
    'servicio_descuento', 'ausencia', 'factura_electronica', 'preferencia_recordatorio',
];

$foto = ['etiqueta' => $etiqueta, 'cuando' => date('Y-m-d H:i:s'), 'tablas' => []];
foreach ($conteos as $t) {
    try {
        $foto['tablas'][$t] = (int) DB::scalar("SELECT COUNT(*) FROM `$t`");
    } catch (Throwable $e) {
        $foto['tablas'][$t] = 'ERROR';
    }
}

$escalares = [
    'citas_por_estado' => "SELECT ec.nombre AS k, COUNT(*) AS v FROM cita c JOIN estado_cita ec ON ec.id_estado_cita=c.id_estado_cita GROUP BY ec.nombre",
    'facturas_por_estado' => "SELECT ef.nombre AS k, COUNT(*) AS v FROM factura f JOIN estado_factura ef ON ef.id_estado_factura=f.id_estado_factura GROUP BY ef.nombre",
    'facturas_por_tipo' => "SELECT tc.nombre AS k, COUNT(*) AS v FROM factura f JOIN tipo_comprobante tc ON tc.id_tipo_comprobante=f.id_tipo_comprobante GROUP BY tc.nombre",
    'cobros_por_metodo' => "SELECT mp.nombre AS k, COUNT(*) AS v FROM cobro co JOIN metodo_pago mp ON mp.id_metodo_pago=co.id_metodo_pago WHERE co.id_estado_cobro=1 GROUP BY mp.nombre",
    'cajas_por_estado' => "SELECT ec.nombre AS k, COUNT(*) AS v FROM caja c JOIN estado_caja ec ON ec.id_estado_caja=c.id_estado_caja GROUP BY ec.nombre",
    'notif_por_estado' => "SELECT estado AS k, COUNT(*) AS v FROM notificacion GROUP BY estado",
    'auditoria_por_accion' => "SELECT accion AS k, COUNT(*) AS v FROM auditoria GROUP BY accion ORDER BY COUNT(*) DESC",
];
foreach ($escalares as $nombre => $sql) {
    $foto[$nombre] = [];
    try {
        foreach (DB::select($sql) as $r) {
            $foto[$nombre][(string) $r->k] = (int) $r->v;
        }
    } catch (Throwable $e) {
        $foto[$nombre] = ['ERROR' => $e->getMessage()];
    }
}

$unicos = [
    'facturado_total' => 'SELECT COALESCE(SUM(fn_factura_total(id_factura)),0) FROM factura f JOIN tipo_comprobante tc USING(id_tipo_comprobante) WHERE f.id_estado_factura=1 AND tc.signo=1',
    'acreditado_total' => 'SELECT COALESCE(SUM(fn_factura_total(id_factura)),0) FROM factura f JOIN tipo_comprobante tc USING(id_tipo_comprobante) WHERE f.id_estado_factura=1 AND tc.signo=-1',
    'cobrado_total' => 'SELECT COALESCE(SUM(monto),0) FROM cobro WHERE id_estado_cobro=1',
    'cobrado_efectivo' => "SELECT COALESCE(SUM(co.monto),0) FROM cobro co JOIN metodo_pago mp USING(id_metodo_pago) WHERE co.id_estado_cobro=1 AND mp.tipo='EFECTIVO'",
    'saldo_pendiente' => 'SELECT COALESCE(SUM(fn_factura_saldo(id_factura)),0) FROM factura f JOIN tipo_comprobante tc USING(id_tipo_comprobante) WHERE f.id_estado_factura=1 AND tc.signo=1',
    'puntos_totales' => 'SELECT COALESCE(SUM(puntos),0) FROM movimiento_punto',
    'pagado_proveedores' => 'SELECT COALESCE(SUM(monto),0) FROM pago_proveedor WHERE id_estado_pago=1',
    'pagado_personal' => 'SELECT COALESCE(SUM(monto),0) FROM pago_personal WHERE id_estado_pago=1',
    'egresos_manuales' => "SELECT COALESCE(SUM(monto),0) FROM movimiento_caja WHERE tipo='EGRESO'",
    'ingresos_manuales' => "SELECT COALESCE(SUM(monto),0) FROM movimiento_caja WHERE tipo='INGRESO'",
    'stock_negativo' => 'SELECT COUNT(*) FROM producto WHERE fn_producto_stock(id_producto) < 0',
    'stock_total' => 'SELECT COALESCE(SUM(fn_producto_stock(id_producto)),0) FROM producto',
    'clientes_con_puntos' => 'SELECT COUNT(*) FROM cliente WHERE fn_cliente_puntos(id_cliente) > 0',
    'puntos_max_cliente' => 'SELECT COALESCE(MAX(fn_cliente_puntos(id_cliente)),0) FROM cliente',
    'relacion_puntos' => 'SELECT puntos_cada_gs FROM configuracion WHERE id_configuracion=1',
];
foreach ($unicos as $nombre => $sql) {
    try {
        $foto[$nombre] = (float) DB::scalar($sql);
    } catch (Throwable $e) {
        $foto[$nombre] = 'ERROR: ' . $e->getMessage();
    }
}

file_put_contents(SIM_LOG . '/estado_' . $etiqueta . '.json', json_encode($foto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "estado_$etiqueta guardado\n";
