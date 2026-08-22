<?php
/**
 * Hacer las cosas en el orden equivocado, o dos veces.
 *
 * El mostrador tiene un orden —atender, emitir, cobrar— y nada obliga a
 * seguirlo desde afuera: los botones se pueden saltear armando el POST. Lo que
 * se busca es que el sistema no quede en un estado que no se pueda explicar.
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

use Illuminate\Support\Facades\DB;

echo "\n=== 3. Orden de las operaciones ===\n";

$suc = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');
$n = new Nav();
if (! $n->entrar('admin', 'qa123456', $suc)) {
    hallazgo('CRITICO', 'ingreso', 'no se pudo entrar');

    return;
}

DB::update('UPDATE caja SET id_estado_caja = 2, fecha_cierre = NOW() WHERE id_estado_caja = 1');
$n->post('/facturacion/caja/abrir', ['monto_inicial' => '500.000']);
$caja = (int) DB::scalar('SELECT id_caja FROM caja WHERE id_estado_caja = 1 ORDER BY id_caja DESC LIMIT 1');

// ---------------------------------------------------------------------
//  Una factura: cobrarla de más, anularla y cobrarla después
// ---------------------------------------------------------------------
$f = DB::selectOne(
    'SELECT f.id_factura, fn_factura_saldo(f.id_factura) AS saldo, f.id_estado_factura
       FROM factura f
       JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = f.id_tipo_comprobante
      WHERE f.id_estado_factura = 1 AND tc.signo = 1
      ORDER BY f.id_factura DESC LIMIT 1'
);

$efectivo = (int) DB::scalar("SELECT id_metodo_pago FROM metodo_pago WHERE tipo = 'EFECTIVO' AND activo = 1 LIMIT 1");

if ($f && $efectivo) {
    $id = (int) $f->id_factura;

    // Cobrar MÁS de lo que debe
    $n->post('/facturacion/cobrar', [
        'id_factura' => $id, 'metodo' => [$efectivo], 'monto' => ['999.999.999'],
    ]);
    revisar($n, 'cobrar más que el saldo');
    $saldo = (float) DB::scalar('SELECT fn_factura_saldo(?)', [$id]);
    if ($saldo < 0) {
        hallazgo('CRITICO', 'cobrar más que el saldo', 'saldo NEGATIVO: ' . $saldo);
    } else {
        ok('cobrar más que el saldo', 'saldo ' . $saldo);
    }

    // Cobrar cero y negativo
    foreach (['0' => 'cero', '-10.000' => 'negativo'] as $v => $que) {
        $c1 = (int) DB::scalar('SELECT COUNT(*) FROM cobro WHERE id_factura = ?', [$id]);
        $n->post('/facturacion/cobrar', ['id_factura' => $id, 'metodo' => [$efectivo], 'monto' => [$v]]);
        revisar($n, 'cobrar ' . $que);
        if ((int) DB::scalar('SELECT COUNT(*) FROM cobro WHERE id_factura = ?', [$id]) > $c1) {
            hallazgo('ALTO', 'cobrar ' . $que, 'se registró un cobro de ' . $v);
        } else {
            ok('cobrar ' . $que, 'rechazado');
        }
    }

    // Anular la factura y después intentar cobrarla
    $n->post('/facturacion/factura/anular', ['id_factura' => $id, 'motivo' => 'prueba de QA']);
    $est = (int) DB::scalar('SELECT id_estado_factura FROM factura WHERE id_factura = ?', [$id]);

    if ($est === 2) {
        $c1 = (int) DB::scalar('SELECT COUNT(*) FROM cobro WHERE id_factura = ?', [$id]);
        $n->post('/facturacion/cobrar', ['id_factura' => $id, 'metodo' => [$efectivo], 'monto' => ['1.000']]);
        revisar($n, 'cobrar una factura anulada');
        if ((int) DB::scalar('SELECT COUNT(*) FROM cobro WHERE id_factura = ?', [$id]) > $c1) {
            hallazgo('CRITICO', 'cobrar una factura anulada', 'entró plata contra un comprobante anulado');
        } else {
            ok('cobrar una factura anulada', 'rechazado');
        }

        // Anularla otra vez
        $n->post('/facturacion/factura/anular', ['id_factura' => $id, 'motivo' => 'otra vez']);
        revisar($n, 'anular dos veces');
        ok('anular dos veces', 'estado ' . DB::scalar('SELECT id_estado_factura FROM factura WHERE id_factura = ?', [$id]));

        // Nota de crédito sobre una factura anulada
        $nc1 = (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_tipo_comprobante = 5');
        $n->post('/facturacion/nota-credito', ['id_factura' => $id, 'motivo' => 'sobre una anulada']);
        revisar($n, 'nota de crédito sobre anulada');
        if ((int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_tipo_comprobante = 5') > $nc1) {
            hallazgo('ALTO', 'nota de crédito sobre anulada', 'se emitió sobre un comprobante ya anulado');
        } else {
            ok('nota de crédito sobre anulada', 'rechazado');
        }
    } else {
        ok('anular factura', 'no se anuló (estado ' . $est . ') — puede tener cobros');
    }
}

// ---------------------------------------------------------------------
//  Emitir dos veces sobre la misma cita
// ---------------------------------------------------------------------
$cita = DB::selectOne(
    'SELECT c.id_cita, c.id_cliente, c.id_usuario, c.id_sucursal FROM cita c
      WHERE EXISTS (SELECT 1 FROM cita_servicio cs WHERE cs.id_cita = c.id_cita)
        AND NOT EXISTS (SELECT 1 FROM factura f WHERE f.id_cita = c.id_cita)
      ORDER BY c.id_cita DESC LIMIT 1'
);

if ($cita) {
    $tipo = (int) DB::scalar(
        'SELECT id_tipo_comprobante FROM timbrado WHERE activo = 1
          AND CURDATE() BETWEEN fecha_inicio AND fecha_fin LIMIT 1');

    for ($i = 1; $i <= 2; $i++) {
        $n->post('/facturacion/emitir', [
            'id_cita' => $cita->id_cita, 'id_tipo_comprobante' => $tipo, 'id_condicion_venta' => 1,
        ]);
        revisar($n, 'emitir #' . $i);
    }
    $cuantas = (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_cita = ?', [(int) $cita->id_cita]);
    if ($cuantas > 1) {
        hallazgo('ALTO', 'emitir dos veces la misma cita',
            'quedaron ' . $cuantas . ' comprobantes por la misma atención');
    } else {
        ok('emitir dos veces la misma cita', $cuantas . ' comprobante');
    }
}

// ---------------------------------------------------------------------
//  Atender una cita cancelada
// ---------------------------------------------------------------------
$cancelada = DB::selectOne(
    'SELECT c.id_cita FROM cita c
      WHERE c.id_estado_cita = 3
        AND EXISTS (SELECT 1 FROM cita_servicio cs WHERE cs.id_cita = c.id_cita)
      ORDER BY c.id_cita DESC LIMIT 1'
);
if (! $cancelada) {
    // Se cancela una para poder probarlo
    $lib = DB::selectOne('SELECT id_cita FROM cita WHERE id_estado_cita IN (1,2) ORDER BY id_cita DESC LIMIT 1');
    if ($lib) {
        DB::update('UPDATE cita SET id_estado_cita = 3 WHERE id_cita = ?', [(int) $lib->id_cita]);
        $cancelada = $lib;
    }
}
if ($cancelada) {
    $sr1 = (int) DB::scalar('SELECT COUNT(*) FROM servicio_realizado WHERE id_cita = ?', [(int) $cancelada->id_cita]);
    $srv = (int) DB::scalar('SELECT id_servicio FROM cita_servicio WHERE id_cita = ? LIMIT 1', [(int) $cancelada->id_cita]);
    $n->post('/citas/atender', ['id_cita' => $cancelada->id_cita, 'servicios' => [$srv]]);
    revisar($n, 'atender una cita cancelada');
    if ((int) DB::scalar('SELECT COUNT(*) FROM servicio_realizado WHERE id_cita = ?', [(int) $cancelada->id_cita]) > $sr1) {
        hallazgo('ALTO', 'atender una cita cancelada', 'se registró la atención de una cita cancelada');
    } else {
        ok('atender una cita cancelada', 'rechazado');
    }
}

// ---------------------------------------------------------------------
//  Ids que no existen, en todo lo que los recibe
// ---------------------------------------------------------------------
$inventados = [
    ['/facturacion/cobrar', ['id_factura' => 999999, 'metodo' => [$efectivo], 'monto' => ['1.000']]],
    ['/facturacion/factura/anular', ['id_factura' => 999999, 'motivo' => 'x']],
    ['/facturacion/nota-credito', ['id_factura' => 999999, 'motivo' => 'x']],
    ['/citas/estado', ['id_cita' => 999999, 'id_estado_cita' => 5]],
    ['/citas/cancelar', ['id_cita' => 999999]],
    ['/facturacion/caja/cerrar', ['id_caja' => 999999, 'monto_contado' => '1.000']],
    ['/facturacion/sena', ['id_cita' => 999999, 'metodo' => [$efectivo], 'monto' => ['1.000']]],
    ['/inventario/compras/factura', ['id_compra' => 999999, 'nro_factura_proveedor' => '001-001-0000001']],
    ['/seguridad/usuarios/baja', ['id_usuario' => 999999]],
    ['/servicios/baja', ['id_servicio' => 999999]],
];

foreach ($inventados as [$uri, $datos]) {
    $n->post($uri, $datos);
    if ($n->codigo() >= 500) {
        hallazgo('CRITICO', 'id inventado · ' . $uri, 'HTTP ' . $n->codigo());
    } else {
        ok('id inventado · ' . $uri, 'HTTP ' . $n->codigo());
    }
}
