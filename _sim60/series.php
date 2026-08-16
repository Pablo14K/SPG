<?php
/**
 * Series diarias para los gráficos del informe: cómo evolucionó el salón
 * a lo largo de los 60 días. Se saca de la base, no del registro.
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

use Illuminate\Support\Facades\DB;

const FIN_SIM = '2026-10-13';

/**
 * Serie diaria, recortada a la ventana simulada.
 *
 * La verificación dirigida de los hallazgos corrió DESPUÉS del día 60 y dejó
 * alguna fila con fecha posterior; sin el recorte, los gráficos mostrarían un
 * día 61 que no es parte de la simulación.
 */
function serie(string $sql): array
{
    $out = [];
    foreach (DB::select($sql) as $r) {
        $d = (string) $r->d;
        if ($d === '' || $d > FIN_SIM) {
            continue;
        }
        $out[$d] = (float) $r->v;
    }

    return $out;
}

$s = [
    'citas_por_dia' => serie('SELECT DATE(fecha_hora) d, COUNT(*) v FROM cita GROUP BY DATE(fecha_hora) ORDER BY d'),
    'citas_atendidas_por_dia' => serie('SELECT DATE(fecha_hora) d, COUNT(*) v FROM cita WHERE id_estado_cita=4 GROUP BY DATE(fecha_hora) ORDER BY d'),
    'citas_canceladas_por_dia' => serie('SELECT DATE(fecha_hora) d, COUNT(*) v FROM cita WHERE id_estado_cita=3 GROUP BY DATE(fecha_hora) ORDER BY d'),
    'citas_ausentes_por_dia' => serie('SELECT DATE(fecha_hora) d, COUNT(*) v FROM cita WHERE id_estado_cita=6 GROUP BY DATE(fecha_hora) ORDER BY d'),
    'facturado_por_dia' => serie('SELECT DATE(f.fecha_emision) d, COALESCE(SUM(fn_factura_total(f.id_factura)),0) v
                                    FROM factura f JOIN tipo_comprobante tc ON tc.id_tipo_comprobante=f.id_tipo_comprobante
                                   WHERE f.id_estado_factura=1 AND tc.signo=1
                                   GROUP BY DATE(f.fecha_emision) ORDER BY d'),
    'cobrado_por_dia' => serie('SELECT DATE(fecha) d, COALESCE(SUM(monto),0) v FROM cobro WHERE id_estado_cobro=1 GROUP BY DATE(fecha) ORDER BY d'),
    'comprobantes_por_dia' => serie('SELECT DATE(fecha_emision) d, COUNT(*) v FROM factura WHERE id_estado_factura=1 GROUP BY DATE(fecha_emision) ORDER BY d'),
    'clientes_acumulados' => serie('SELECT DATE(fecha_registro) d, COUNT(*) v FROM cliente GROUP BY DATE(fecha_registro) ORDER BY d'),
    'movimientos_inventario_por_dia' => serie('SELECT DATE(fecha) d, COUNT(*) v FROM movimiento_inventario GROUP BY DATE(fecha) ORDER BY d'),
    'auditoria_por_dia' => serie('SELECT DATE(fecha_hora) d, COUNT(*) v FROM auditoria GROUP BY DATE(fecha_hora) ORDER BY d'),
    'notificaciones_por_dia' => serie('SELECT DATE(fecha_generacion) d, COUNT(*) v FROM notificacion GROUP BY DATE(fecha_generacion) ORDER BY d'),
    'saldo_caja_por_dia' => serie('SELECT DATE(fecha_apertura) d, COALESCE(SUM(fn_caja_saldo(id_caja)),0) v FROM caja GROUP BY DATE(fecha_apertura) ORDER BY d'),
];

// Stock por producto, al cierre
$s['stock_final'] = [];
foreach (DB::select('SELECT p.id_producto, p.nombre, p.unidad_medida, p.stock_minimo,
                            fn_producto_stock(p.id_producto) AS stock,
                            COALESCE((SELECT SUM(m.cantidad) FROM movimiento_inventario m
                                       JOIN tipo_movimiento_inventario t ON t.id_tipo_movimiento=m.id_tipo_movimiento
                                      WHERE m.id_producto=p.id_producto AND t.signo="E"),0) AS entradas,
                            COALESCE((SELECT SUM(m.cantidad) FROM movimiento_inventario m
                                       JOIN tipo_movimiento_inventario t ON t.id_tipo_movimiento=m.id_tipo_movimiento
                                      WHERE m.id_producto=p.id_producto AND t.signo="S"),0) AS salidas
                       FROM producto p ORDER BY p.id_producto') as $p) {
    $s['stock_final'][] = [
        'id' => (int) $p->id_producto, 'nombre' => (string) $p->nombre,
        'unidad' => (string) $p->unidad_medida, 'minimo' => (float) $p->stock_minimo,
        'entradas' => (float) $p->entradas, 'salidas' => (float) $p->salidas,
        'teorico' => round((float) $p->entradas - (float) $p->salidas, 4),
        'sistema' => round((float) $p->stock, 4),
    ];
}

// Equipo: cuánto generó y cuánto le tocó a cada profesional
$s['equipo'] = [];
foreach (DB::select("SELECT u.id_usuario, u.username, CONCAT(pe.nombre,' ',pe.apellido) nombre, u.activo,
                            (SELECT COUNT(*) FROM cita c WHERE c.id_usuario=u.id_usuario) citas,
                            (SELECT COUNT(*) FROM servicio_realizado sr WHERE sr.id_usuario=u.id_usuario) servicios,
                            (SELECT COALESCE(SUM(s.precio),0) FROM servicio_realizado sr
                               JOIN servicio s ON s.id_servicio=sr.id_servicio WHERE sr.id_usuario=u.id_usuario) generado,
                            (SELECT COALESCE(SUM(fn_comision_servicio(sr.id_servicio_realizado)),0)
                               FROM servicio_realizado sr WHERE sr.id_usuario=u.id_usuario) comision,
                            (SELECT COUNT(*) FROM comision co WHERE co.id_usuario=u.id_usuario) tiene_comision
                       FROM usuario u JOIN persona pe ON pe.id_persona=u.id_persona
                       JOIN rol r ON r.id_rol=u.id_rol WHERE r.es_personal=1
                      ORDER BY generado DESC") as $u) {
    $s['equipo'][] = [
        'usuario' => (string) $u->username, 'nombre' => (string) $u->nombre,
        'activo' => (int) $u->activo, 'citas' => (int) $u->citas,
        'servicios' => (int) $u->servicios, 'generado' => (float) $u->generado,
        'comision' => (float) $u->comision, 'tiene_comision' => (int) $u->tiene_comision,
    ];
}

// Arqueo por caja
$s['cajas'] = [];
foreach (DB::select('SELECT c.id_caja, DATE(c.fecha_apertura) dia, c.monto_inicial, c.id_estado_caja,
                            fn_caja_saldo(c.id_caja) saldo FROM caja c ORDER BY c.id_caja') as $c) {
    $id = (int) $c->id_caja;
    $s['cajas'][] = [
        'id' => $id, 'dia' => (string) $c->dia, 'inicial' => (float) $c->monto_inicial,
        'estado' => (int) $c->id_estado_caja, 'saldo' => (float) $c->saldo,
        'cobros_efectivo' => (float) DB::scalar("SELECT COALESCE(SUM(co.monto),0) FROM cobro co JOIN metodo_pago mp ON mp.id_metodo_pago=co.id_metodo_pago WHERE co.id_caja=? AND co.id_estado_cobro=1 AND mp.tipo='EFECTIVO'", [$id]),
        'cobros_otros' => (float) DB::scalar("SELECT COALESCE(SUM(co.monto),0) FROM cobro co JOIN metodo_pago mp ON mp.id_metodo_pago=co.id_metodo_pago WHERE co.id_caja=? AND co.id_estado_cobro=1 AND mp.tipo<>'EFECTIVO'", [$id]),
        'egresos' => (float) DB::scalar("SELECT COALESCE(SUM(monto),0) FROM movimiento_caja WHERE id_caja=? AND tipo='EGRESO'", [$id]),
        'prov_efectivo' => (float) DB::scalar("SELECT COALESCE(SUM(d.monto_aplicado),0) FROM pago_proveedor pp JOIN detalle_pago_proveedor d ON d.id_pago_proveedor=pp.id_pago_proveedor JOIN metodo_pago mp ON mp.id_metodo_pago=pp.id_metodo_pago WHERE pp.id_caja=? AND pp.id_estado_pago_proveedor=1 AND mp.tipo='EFECTIVO'", [$id]),
        'pers_efectivo' => (float) DB::scalar("SELECT COALESCE(SUM(d.monto),0) FROM pago_personal pg JOIN detalle_pago_personal d ON d.id_pago_personal=pg.id_pago_personal JOIN metodo_pago mp ON mp.id_metodo_pago=pg.id_metodo_pago WHERE pg.id_caja=? AND pg.id_estado_pago=1 AND mp.tipo='EFECTIVO'", [$id]),
    ];
}

file_put_contents(SIM_LOG . '/series.json', json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "series.json guardado\n";
