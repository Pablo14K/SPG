<?php
/**
 * Cargas inapropiadas en los campos de dinero y cantidad.
 *
 * Lo que se busca no es que el sistema diga que no: es que **no se rompa** y
 * que **no guarde** lo que no corresponde. Un monto negativo que entra es peor
 * que un 500, porque no se nota.
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

use Illuminate\Support\Facades\DB;

echo "\n=== 1. Montos y cantidades ===\n";

$n = new Nav();
$suc = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');
if (! $n->entrar('admin', 'qa123456', $suc)) {
    hallazgo('CRITICO', 'ingreso', 'no se pudo entrar como admin');

    return;
}

// ---------------------------------------------------------------------
//  Apertura de caja con montos absurdos
// ---------------------------------------------------------------------
DB::update('UPDATE caja SET id_estado_caja = 2, fecha_cierre = NOW() WHERE id_estado_caja = 1');

$absurdos = [
    'negativo'        => '-50.000',
    'letras'          => 'abc',
    'vacío'           => '',
    'enorme'          => '999999999999999999999',
    'notación'        => '1e9',
    'con símbolos'    => 'Gs. 100.000!!',
    'sólo separador'  => '...',
    'coma decimal'    => '100,50',
];

foreach ($absurdos as $que => $valor) {
    $antes = (int) DB::scalar('SELECT COUNT(*) FROM caja');
    $n->post('/facturacion/caja/abrir', ['monto_inicial' => $valor]);
    revisar($n, 'abrir caja · ' . $que);
    $despues = (int) DB::scalar('SELECT COUNT(*) FROM caja');

    if ($despues > $antes) {
        $m = (float) DB::scalar('SELECT monto_inicial FROM caja ORDER BY id_caja DESC LIMIT 1');
        if ($m < 0) {
            hallazgo('CRITICO', 'abrir caja · ' . $que, 'se abrió con monto NEGATIVO: ' . $m);
        } elseif ($valor === 'abc' || $valor === '...' ) {
            ok('abrir caja · ' . $que, 'entró como ' . $m . ' (texto → cero)');
        } else {
            ok('abrir caja · ' . $que, 'entró como ' . $m);
        }
        // se cierra para el siguiente caso
        DB::update('UPDATE caja SET id_estado_caja = 2, fecha_cierre = NOW() WHERE id_estado_caja = 1');
    } else {
        ok('abrir caja · ' . $que, 'rechazado: ' . mb_substr($n->aviso(), 0, 60));
    }
}

// ---------------------------------------------------------------------
//  Movimiento de efectivo: sacar más de lo que hay, y montos raros
// ---------------------------------------------------------------------
$n->post('/facturacion/caja/abrir', ['monto_inicial' => '100.000']);
$caja = (int) DB::scalar('SELECT id_caja FROM caja WHERE id_estado_caja = 1 ORDER BY id_caja DESC LIMIT 1');
$tipo = (int) DB::scalar("SELECT id_tipo_mov_caja FROM tipo_movimiento_caja WHERE exige_documento = 0 AND signo = 'S' AND activo = 1 LIMIT 1");

if ($caja && $tipo) {
    $saldo = (float) DB::scalar('SELECT fn_caja_saldo(?)', [$caja]);
    $antes = (int) DB::scalar('SELECT COUNT(*) FROM movimiento_caja');

    $n->post('/facturacion/caja/movimiento', [
        'id_tipo_mov_caja' => $tipo, 'monto' => '9.999.999', 'concepto' => 'sacar de mas',
    ]);
    revisar($n, 'egreso mayor al saldo');

    $ahora = (float) DB::scalar('SELECT fn_caja_saldo(?)', [$caja]);
    if ($ahora < 0) {
        hallazgo('CRITICO', 'egreso mayor al saldo',
            'el cajón quedó en NEGATIVO: ' . $ahora . ' (había ' . $saldo . ')');
    } else {
        ok('egreso mayor al saldo', 'saldo sigue en ' . $ahora);
    }

    // Monto cero y negativo
    foreach (['0' => 'cero', '-1.000' => 'negativo'] as $v => $que) {
        $c1 = (int) DB::scalar('SELECT COUNT(*) FROM movimiento_caja');
        $n->post('/facturacion/caja/movimiento', [
            'id_tipo_mov_caja' => $tipo, 'monto' => $v, 'concepto' => 'prueba ' . $que,
        ]);
        revisar($n, 'movimiento · ' . $que);
        if ((int) DB::scalar('SELECT COUNT(*) FROM movimiento_caja') > $c1) {
            hallazgo('ALTO', 'movimiento · ' . $que, 'se registró un movimiento de ' . $v);
        } else {
            ok('movimiento · ' . $que, 'rechazado');
        }
    }

    // Concepto vacío y sólo espacios
    foreach (['' => 'vacío', '   ' => 'sólo espacios'] as $v => $que) {
        $c1 = (int) DB::scalar('SELECT COUNT(*) FROM movimiento_caja');
        $n->post('/facturacion/caja/movimiento', [
            'id_tipo_mov_caja' => $tipo, 'monto' => '1.000', 'concepto' => $v,
        ]);
        revisar($n, 'movimiento · concepto ' . $que);
        if ((int) DB::scalar('SELECT COUNT(*) FROM movimiento_caja') > $c1) {
            hallazgo('ALTO', 'movimiento · concepto ' . $que, 'entró sin concepto que lo explique');
        } else {
            ok('movimiento · concepto ' . $que, 'rechazado');
        }
    }

    // Tipo de movimiento inventado
    $c1 = (int) DB::scalar('SELECT COUNT(*) FROM movimiento_caja');
    $n->post('/facturacion/caja/movimiento', [
        'id_tipo_mov_caja' => 999999, 'monto' => '1.000', 'concepto' => 'tipo inventado',
    ]);
    revisar($n, 'movimiento · tipo inventado');
    if ((int) DB::scalar('SELECT COUNT(*) FROM movimiento_caja') > $c1) {
        hallazgo('ALTO', 'movimiento · tipo inventado', 'entró con un tipo que no existe');
    } else {
        ok('movimiento · tipo inventado', 'rechazado');
    }
}

// ---------------------------------------------------------------------
//  Arqueo: cerrar con un conteo absurdo
// ---------------------------------------------------------------------
$caja = (int) DB::scalar('SELECT id_caja FROM caja WHERE id_estado_caja = 1 ORDER BY id_caja DESC LIMIT 1');
if ($caja) {
    $n->post('/facturacion/caja/cerrar', ['id_caja' => $caja, 'monto_contado' => '-999']);
    revisar($n, 'cerrar caja · contado negativo');
    $sigue = (int) DB::scalar('SELECT id_estado_caja FROM caja WHERE id_caja = ?', [$caja]);
    if ($sigue === 2) {
        hallazgo('ALTO', 'cerrar caja · contado negativo', 'la caja se cerró con un conteo negativo');
    } else {
        ok('cerrar caja · contado negativo', 'rechazado');
    }

    // Sin motivo, con diferencia
    $n->post('/facturacion/caja/cerrar', ['id_caja' => $caja, 'monto_contado' => '1']);
    $sigue = (int) DB::scalar('SELECT id_estado_caja FROM caja WHERE id_caja = ?', [$caja]);
    if ($sigue === 2) {
        hallazgo('MEDIO', 'cerrar caja · diferencia sin motivo', 'cerró sin explicar la diferencia');
    } else {
        ok('cerrar caja · diferencia sin motivo', 'pide el motivo');
    }

    // Con motivo: tiene que cerrar
    $n->post('/facturacion/caja/cerrar', [
        'id_caja' => $caja, 'monto_contado' => '1', 'motivo_diferencia' => 'prueba de QA',
    ]);
    $sigue = (int) DB::scalar('SELECT id_estado_caja FROM caja WHERE id_caja = ?', [$caja]);
    ok('cerrar caja · con motivo', $sigue === 2 ? 'cerró' : 'NO cerró — revisar');

    // Cerrarla otra vez
    $n->post('/facturacion/caja/cerrar', [
        'id_caja' => $caja, 'monto_contado' => '50.000', 'motivo_diferencia' => 'segunda vez',
    ]);
    revisar($n, 'cerrar dos veces la misma caja');
    $contado = DB::scalar('SELECT monto_contado FROM caja WHERE id_caja = ?', [$caja]);
    if ((float) $contado !== 1.0) {
        hallazgo('ALTO', 'cerrar dos veces la misma caja',
            'el segundo cierre pisó el conteo del primero: quedó ' . $contado);
    } else {
        ok('cerrar dos veces la misma caja', 'el conteo del primero se conservó');
    }
}
