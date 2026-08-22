<?php
/**
 * Coherencia: que los números cierren entre sí.
 *
 * No se prueba una pantalla: se recorre la base y se comprueba que lo que
 * dice una tabla coincida con lo que calcula la función. Un desajuste acá no
 * se ve en ningún lado — es el tipo de error que aparece meses después,
 * cuando alguien cuadra la caja a mano.
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

use Illuminate\Support\Facades\DB;

echo "\n=== 5. Coherencia de los números ===\n";

// ---------------------------------------------------------------------
//  1. Ningún saldo de factura puede ser negativo
// ---------------------------------------------------------------------
$neg = DB::select(
    'SELECT f.id_factura, fn_factura_saldo(f.id_factura) AS saldo
       FROM factura f WHERE fn_factura_saldo(f.id_factura) < -0.01 LIMIT 5');
if ($neg) {
    hallazgo('CRITICO', 'saldos negativos',
        count($neg) . ' factura(s), ej. #' . $neg[0]->id_factura . ' con ' . $neg[0]->saldo);
} else {
    ok('saldos de factura', 'ninguno negativo');
}

// ---------------------------------------------------------------------
//  2. Ningún stock negativo
// ---------------------------------------------------------------------
$stock = DB::select(
    'SELECT ps.id_producto, ps.id_sucursal, fn_producto_stock(ps.id_producto, ps.id_sucursal) AS s
       FROM producto_sucursal ps
      WHERE fn_producto_stock(ps.id_producto, ps.id_sucursal) < -0.0001 LIMIT 5');
if ($stock) {
    hallazgo('CRITICO', 'stock negativo',
        count($stock) . ' producto(s), ej. #' . $stock[0]->id_producto . ' con ' . $stock[0]->s);
} else {
    ok('stock', 'ninguno negativo');
}

// ---------------------------------------------------------------------
//  3. El saldo de caja coincide con sus partes
// ---------------------------------------------------------------------
$malas = 0;
foreach (DB::select('SELECT id_caja FROM caja') as $c) {
    $id = (int) $c->id_caja;
    $fn = (float) DB::scalar('SELECT fn_caja_saldo(?)', [$id]);
    $mano = (float) DB::scalar(
        "SELECT ca.monto_inicial
              + COALESCE((SELECT SUM(co.monto) FROM cobro co
                            JOIN metodo_pago mp ON mp.id_metodo_pago = co.id_metodo_pago
                           WHERE co.id_caja = ca.id_caja AND co.id_estado_cobro = 1 AND mp.tipo = 'EFECTIVO'), 0)
              + COALESCE((SELECT SUM(m.monto) FROM movimiento_caja m
                           WHERE m.id_caja = ca.id_caja AND m.tipo = 'INGRESO' AND m.activo = 1), 0)
              - COALESCE((SELECT SUM(m.monto) FROM movimiento_caja m
                           WHERE m.id_caja = ca.id_caja AND m.tipo = 'EGRESO' AND m.activo = 1), 0)
              - COALESCE((SELECT SUM(d.monto_aplicado) FROM detalle_pago_proveedor d
                            JOIN pago_proveedor pp ON pp.id_pago_proveedor = d.id_pago_proveedor
                            JOIN metodo_pago mp2 ON mp2.id_metodo_pago = pp.id_metodo_pago
                           WHERE pp.id_caja = ca.id_caja AND pp.id_estado_pago_proveedor = 1
                             AND mp2.tipo = 'EFECTIVO'), 0)
              - COALESCE((SELECT SUM(d2.monto) FROM detalle_pago_personal d2
                            JOIN pago_personal pg ON pg.id_pago_personal = d2.id_pago_personal
                            JOIN metodo_pago mp3 ON mp3.id_metodo_pago = pg.id_metodo_pago
                           WHERE pg.id_caja = ca.id_caja AND pg.id_estado_pago = 1
                             AND mp3.tipo = 'EFECTIVO'), 0)
           FROM caja ca WHERE ca.id_caja = ?", [$id]
    );
    if (abs($fn - $mano) > 0.01) {
        $malas++;
        if ($malas <= 3) {
            hallazgo('ALTO', 'saldo de caja #' . $id,
                'fn_caja_saldo=' . $fn . ' pero a mano da ' . $mano);
        }
    }
}
if ($malas === 0) {
    ok('saldo de caja', 'coincide con sus partes en todas');
}

// ---------------------------------------------------------------------
//  4. Un cobro no puede estar en una caja de otra sucursal
// ---------------------------------------------------------------------
$cruz = DB::select(
    'SELECT co.id_cobro, k.id_sucursal AS suc_caja, COALESCE(f.id_sucursal, t.id_sucursal) AS suc_doc
       FROM cobro co
       JOIN caja k ON k.id_caja = co.id_caja
       JOIN factura f ON f.id_factura = co.id_factura
       LEFT JOIN timbrado t ON t.id_timbrado = f.id_timbrado
      WHERE k.id_sucursal <> COALESCE(f.id_sucursal, t.id_sucursal) LIMIT 5');
if ($cruz) {
    hallazgo('ALTO', 'cobro en el cajón de otro local',
        count($cruz) . ' cobro(s), ej. #' . $cruz[0]->id_cobro
        . ' (caja de ' . $cruz[0]->suc_caja . ', documento de ' . $cruz[0]->suc_doc . ')');
} else {
    ok('cobros por sucursal', 'cada uno en el cajón de su documento');
}

// ---------------------------------------------------------------------
//  5. Correlativos: sin huecos ni repetidos por timbrado
// ---------------------------------------------------------------------
foreach (DB::select('SELECT DISTINCT id_timbrado FROM factura WHERE id_timbrado IS NOT NULL') as $t) {
    $r = DB::selectOne(
        'SELECT COUNT(*) AS n, COUNT(DISTINCT nro_correlativo) AS d,
                MIN(nro_correlativo) AS mn, MAX(nro_correlativo) AS mx
           FROM factura WHERE id_timbrado = ?', [(int) $t->id_timbrado]);
    if ((int) $r->n !== (int) $r->d) {
        hallazgo('CRITICO', 'timbrado #' . $t->id_timbrado,
            'correlativos REPETIDOS: ' . $r->n . ' facturas, ' . $r->d . ' números');
    } elseif ((int) $r->mx - (int) $r->mn + 1 !== (int) $r->n) {
        hallazgo('MEDIO', 'timbrado #' . $t->id_timbrado,
            'hay huecos: del ' . $r->mn . ' al ' . $r->mx . ' pero sólo ' . $r->n . ' facturas');
    }
}
ok('correlativos', 'revisados ' . count(DB::select('SELECT DISTINCT id_timbrado FROM factura')) . ' timbrado(s)');

// ---------------------------------------------------------------------
//  6. Citas: ninguna persona en dos lugares a la vez
// ---------------------------------------------------------------------
$solapes = DB::select(
    'SELECT a.id_cita AS a, b.id_cita AS b, a.id_usuario, a.fecha_hora
       FROM cita a
       JOIN estado_cita ea ON ea.id_estado_cita = a.id_estado_cita AND ea.bloquea_agenda = 1
       JOIN cita b ON b.id_usuario = a.id_usuario AND b.id_cita > a.id_cita
       JOIN estado_cita eb ON eb.id_estado_cita = b.id_estado_cita AND eb.bloquea_agenda = 1
      WHERE a.fecha_hora < DATE_ADD(b.fecha_hora, INTERVAL fn_cita_duracion(b.id_cita) MINUTE)
        AND b.fecha_hora < DATE_ADD(a.fecha_hora, INTERVAL fn_cita_duracion(a.id_cita) MINUTE)
      LIMIT 5');
if ($solapes) {
    hallazgo('ALTO', 'citas solapadas',
        count($solapes) . ' par(es), ej. #' . $solapes[0]->a . ' y #' . $solapes[0]->b
        . ' (profesional ' . $solapes[0]->id_usuario . ')');
} else {
    ok('citas solapadas', 'ninguna persona en dos sillones a la vez');
}

// ---------------------------------------------------------------------
//  7. Puntos: el saldo coincide con los movimientos
// ---------------------------------------------------------------------
$p = DB::select(
    'SELECT c.id_cliente, fn_cliente_puntos(c.id_cliente) AS fn,
            COALESCE((SELECT SUM(mp.puntos) FROM movimiento_punto mp
                       WHERE mp.id_cliente = c.id_cliente), 0) AS suma
       FROM cliente c
      HAVING ABS(fn - suma) > 0.01 LIMIT 5');
if ($p) {
    hallazgo('ALTO', 'puntos', count($p) . ' cliente(s) con el saldo desalineado');
} else {
    ok('puntos', 'el saldo coincide con los movimientos');
}

// ---------------------------------------------------------------------
//  8. Una sola caja abierta por sucursal
// ---------------------------------------------------------------------
$dobles = DB::select(
    'SELECT id_sucursal, COUNT(*) AS n FROM caja WHERE id_estado_caja = 1
      GROUP BY id_sucursal HAVING n > 1');
if ($dobles) {
    hallazgo('CRITICO', 'cajas abiertas',
        'la sucursal ' . $dobles[0]->id_sucursal . ' tiene ' . $dobles[0]->n . ' abiertas');
} else {
    ok('cajas abiertas', 'una por sucursal como máximo');
}

// ---------------------------------------------------------------------
//  9. Devoluciones: una sola vigente por nota de crédito
// ---------------------------------------------------------------------
$dev = DB::select(
    'SELECT id_factura, COUNT(*) AS n FROM movimiento_caja
      WHERE id_factura IS NOT NULL AND activo = 1 GROUP BY id_factura HAVING n > 1');
if ($dev) {
    hallazgo('CRITICO', 'devoluciones duplicadas',
        'la nota #' . $dev[0]->id_factura . ' se devolvió ' . $dev[0]->n . ' veces');
} else {
    ok('devoluciones', 'ninguna cargada dos veces');
}
