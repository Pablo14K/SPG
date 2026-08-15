<?php
/**
 * Errores de usuario, datos inválidos y transiciones de estado imposibles.
 * Todo lo que el sistema DEBERÍA rechazar. Lo que pasa, es un hallazgo.
 */

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/** @var int $DIA */

/** Espera que la operación sea rechazada. */
$rechaza = function (Nav $n, string $m, string $uri, array $d, string $cod, string $desc, ?callable $verifica = null) {
    $n->req($m, $uri, $d)->seguir();
    $mal = false;
    if ($verifica) {
        $mal = ! $verifica();     // true => quedó registrado algo que no debía
    }
    $aceptó = $n->dice('success:') && ! $n->dice('error:');
    if ($mal || ($aceptó && ! $verifica)) {
        sim_incidente($cod, $desc . ' — el sistema lo ACEPTÓ. Respuesta: ' . ($n->flashTxt() ?: ('HTTP ' . $n->status)), 'ALTO');
    } else {
        sim_log(['tipo' => 'ANOM_OK', 'cod' => $cod, 'msg' => $n->flashTxt()]);
    }
};

$rec = new Nav();
if (! $rec->entrar('recepcion', 'recepcion123')) return;

$hoy = date('Y-m-d');
$manana = date('Y-m-d', strtotime('+2 day'));
$cli = (int) (DB::scalar('SELECT id_cliente FROM cliente WHERE activo=1 ORDER BY id_cliente LIMIT 1') ?: 1);

// =========================================================================
// A. Citas con datos inválidos
// =========================================================================
$antes = (int) DB::scalar('SELECT COUNT(*) FROM cita');
$casos = [
    ['A1_SIN_CLIENTE',   ['id_cliente' => 0, 'servicios' => [1], 'fecha_hora' => $manana . ' 10:00'], 'Cita sin cliente'],
    ['A2_SIN_SERVICIO',  ['id_cliente' => $cli, 'servicios' => [], 'fecha_hora' => $manana . ' 10:00'], 'Cita sin servicios'],
    ['A3_FECHA_BASURA',  ['id_cliente' => $cli, 'servicios' => [1], 'fecha_hora' => 'no-es-fecha'], 'Cita con fecha inválida'],
    ['A4_FECHA_VIEJA',   ['id_cliente' => $cli, 'servicios' => [1], 'fecha_hora' => '2020-01-05 10:00'], 'Cita con seis años de atraso'],
    ['A5_FECHA_LEJANA',  ['id_cliente' => $cli, 'servicios' => [1], 'fecha_hora' => date('Y-m-d', strtotime('+3 year')) . ' 10:00'], 'Cita a tres años vista'],
    ['A6_CLIENTE_FANTASMA', ['id_cliente' => 999999, 'servicios' => [1], 'fecha_hora' => $manana . ' 10:00'], 'Cita para un cliente inexistente'],
    ['A7_SERVICIO_FANTASMA', ['id_cliente' => $cli, 'servicios' => [99999], 'fecha_hora' => $manana . ' 10:00'], 'Cita con un servicio inexistente'],
    ['A8_PROF_FANTASMA', ['id_cliente' => $cli, 'servicios' => [1], 'id_usuario' => 99999, 'fecha_hora' => $manana . ' 10:00'], 'Cita con un profesional inexistente'],
    ['A9_HORA_IMPOSIBLE', ['id_cliente' => $cli, 'servicios' => [1], 'id_usuario' => 10, 'fecha_hora' => $manana . ' 03:00'], 'Cita a las 3 de la mañana con quien tiene turno'],
    ['A10_DOMINGO', ['id_cliente' => $cli, 'servicios' => [1], 'id_usuario' => 10, 'fecha_hora' => date('Y-m-d', strtotime('next sunday')) . ' 10:00'], 'Cita un domingo con quien tiene turno'],
];
foreach ($casos as [$cod, $d, $desc]) {
    $n0 = (int) DB::scalar('SELECT COUNT(*) FROM cita');
    $rec->post('/citas/guardar', $d)->seguir();
    $n1 = (int) DB::scalar('SELECT COUNT(*) FROM cita');
    if ($n1 > $n0) {
        sim_incidente($cod, $desc . ' — se GRABÓ la cita. ' . $rec->flashTxt(), 'ALTO');
    } else {
        sim_log(['tipo' => 'ANOM_OK', 'cod' => $cod, 'msg' => $rec->flashTxt()]);
    }
}

// A11: doble reserva exacta sobre una cita existente
$ocupada = DB::selectOne('SELECT c.id_cita,c.id_cliente,c.id_usuario,c.fecha_hora
                            FROM cita c JOIN estado_cita e ON e.id_estado_cita=c.id_estado_cita
                           WHERE e.bloquea_agenda=1 AND c.fecha_hora > NOW() ORDER BY c.fecha_hora LIMIT 1');
if ($ocupada) {
    $srv = array_map(fn ($r) => (int) $r->id_servicio, DB::select('SELECT id_servicio FROM cita_servicio WHERE id_cita=?', [(int) $ocupada->id_cita]));
    $n0 = (int) DB::scalar('SELECT COUNT(*) FROM cita');
    $rec->post('/citas/guardar', ['id_cliente' => $cli, 'servicios' => $srv ?: [1],
        'id_usuario' => (int) $ocupada->id_usuario, 'fecha_hora' => substr((string) $ocupada->fecha_hora, 0, 16)])->seguir();
    if ((int) DB::scalar('SELECT COUNT(*) FROM cita') > $n0) {
        sim_incidente('A11_DOBLE_RESERVA', 'Se agendó una segunda cita sobre el mismo horario del mismo profesional', 'CRITICO');
    } else {
        sim_log(['tipo' => 'ANOM_OK', 'cod' => 'A11_DOBLE_RESERVA', 'msg' => $rec->flashTxt()]);
    }
}

// =========================================================================
// B. Transiciones de estado imposibles
// =========================================================================
$atendida = DB::selectOne('SELECT id_cita, id_usuario FROM cita WHERE id_estado_cita = 4 ORDER BY RAND() LIMIT 1');
if ($atendida) {
    $id = (int) $atendida->id_cita;
    $rec->post('/citas/cancelar', ['id_cita' => $id, 'dia' => $hoy])->seguir();
    if ((int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita=?', [$id]) === 3) {
        sim_incidente('B1_CANCELA_ATENDIDA', 'Se canceló una cita ya ATENDIDA (#' . $id . ')', 'CRITICO');
    }
    $rec->post('/citas/estado', ['id_cita' => $id, 'id_estado_cita' => 6, 'dia' => $hoy])->seguir();
    if ((int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita=?', [$id]) === 6) {
        sim_incidente('B2_AUSENTE_ATENDIDA', 'Una cita ATENDIDA pasó a AUSENTE (#' . $id . ')', 'CRITICO');
    }
    $rec->post('/citas/reprogramar', ['id_cita' => $id, 'nueva_fecha' => $manana . ' 09:00', 'dia' => $hoy])->seguir();
    if ((int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita=?', [$id]) === 2) {
        sim_incidente('B3_REPROG_ATENDIDA', 'Una cita ATENDIDA se reprogramó (#' . $id . ')', 'CRITICO');
    }
}

$cancelada = DB::selectOne('SELECT id_cita FROM cita WHERE id_estado_cita = 3 ORDER BY RAND() LIMIT 1');
if ($cancelada) {
    $id = (int) $cancelada->id_cita;
    $rec->post('/citas/reprogramar', ['id_cita' => $id, 'nueva_fecha' => $manana . ' 09:30', 'dia' => $hoy])->seguir();
    $e = (int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita=?', [$id]);
    if ($e !== 3) {
        sim_incidente('B4_REPROG_CANCELADA', 'Una cita CANCELADA volvió al estado ' . $e . ' al reprogramarla (#' . $id . ')', 'CRITICO');
    }
    $rec->post('/citas/estado', ['id_cita' => $id, 'id_estado_cita' => 5, 'dia' => $hoy])->seguir();
    if ((int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita=?', [$id]) === 5) {
        sim_incidente('B5_ENPROCESO_CANCELADA', 'Una cita CANCELADA pasó a EN PROCESO (#' . $id . ')', 'ALTO');
    }
    // Atender una cancelada
    $rec->post('/citas/atender', ['id_cita' => $id, 'servicios' => [1], 'dia' => $hoy])->seguir();
    if ((int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita=?', [$id]) === 4) {
        sim_incidente('B6_ATIENDE_CANCELADA', 'Se registró atención sobre una cita CANCELADA (#' . $id . ')', 'CRITICO');
    }
}

// Ausente → Atendida (sin pasar por nada)
$ausente = DB::selectOne('SELECT id_cita, id_usuario FROM cita WHERE id_estado_cita = 6 ORDER BY RAND() LIMIT 1');
if ($ausente) {
    $id = (int) $ausente->id_cita;
    $rec->post('/citas/atender', ['id_cita' => $id, 'servicios' => [1], 'dia' => $hoy])->seguir();
    if ((int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita=?', [$id]) === 4) {
        sim_log(['tipo' => 'ANOM_NOTA', 'cod' => 'B7_AUSENTE_A_ATENDIDA',
                 'det' => 'Una cita marcada AUSENTE pasó a ATENDIDA registrando la atención (#' . $id . ')']);
    }
}

// =========================================================================
// C. Facturación fuera de secuencia
// =========================================================================
// Emitir sobre una cita NO atendida
$prog = DB::selectOne('SELECT id_cita FROM cita WHERE id_estado_cita IN (1,2) LIMIT 1');
if ($prog) {
    $n0 = (int) DB::scalar('SELECT COUNT(*) FROM factura');
    $rec->post('/facturacion/emitir', ['id_cita' => (int) $prog->id_cita, 'id_tipo_comprobante' => 8, 'id_condicion_venta' => 1])->seguir();
    if ((int) DB::scalar('SELECT COUNT(*) FROM factura') > $n0) {
        sim_incidente('C1_FACTURA_SIN_ATENDER', 'Se emitió un comprobante de una cita no atendida', 'CRITICO');
    }
}

// Emitir dos veces la misma cita
$conFactura = DB::selectOne('SELECT id_cita FROM factura WHERE id_estado_factura=1 AND id_cita IS NOT NULL ORDER BY RAND() LIMIT 1');
if ($conFactura) {
    $n0 = (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_cita = ?', [(int) $conFactura->id_cita]);
    $rec->post('/facturacion/emitir', ['id_cita' => (int) $conFactura->id_cita, 'id_tipo_comprobante' => 8, 'id_condicion_venta' => 1])->seguir();
    $n1 = (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_cita = ?', [(int) $conFactura->id_cita]);
    if ($n1 > $n0) {
        sim_incidente('C2_DOBLE_FACTURA', 'Se emitió un segundo comprobante para la cita #' . $conFactura->id_cita, 'CRITICO');
    }
}

// Cobrar más que el saldo / cero / negativo / método inexistente
$fac = DB::selectOne('SELECT id_factura, fn_factura_saldo(id_factura) AS saldo FROM factura
                       WHERE id_estado_factura=1 AND fn_factura_saldo(id_factura) > 0 ORDER BY RAND() LIMIT 1');
if ($fac) {
    $idf = (int) $fac->id_factura;
    $base = ['id_factura' => $idf, 'referencia' => [''], 'marca' => [''], 'tipo_tarjeta' => [''], 'cuotas' => ['1'],
             'ultimos_4' => [''], 'nro_boleta' => [''], 'cod_autorizacion' => [''], 'banco' => [''],
             'nro_cheque' => [''], 'nro_operacion' => [''], 'fecha_emision' => ['']];
    foreach ([
        ['C3_COBRO_EXCESO', ['metodo' => ['1'], 'monto' => [(string) ((int) $fac->saldo + 500000)]], 'Cobro por encima del saldo'],
        ['C4_COBRO_CERO',   ['metodo' => ['1'], 'monto' => ['0']], 'Cobro de cero'],
        ['C5_COBRO_NEG',    ['metodo' => ['1'], 'monto' => ['-50000']], 'Cobro negativo'],
        ['C6_METODO_FANTASMA', ['metodo' => ['999'], 'monto' => ['10000']], 'Cobro con un método inexistente'],
    ] as [$cod, $extra, $desc]) {
        $c0 = (float) DB::scalar('SELECT COALESCE(SUM(monto),0) FROM cobro WHERE id_factura=? AND id_estado_cobro=1', [$idf]);
        $rec->post('/facturacion/cobrar', array_merge($base, $extra))->seguir();
        $c1 = (float) DB::scalar('SELECT COALESCE(SUM(monto),0) FROM cobro WHERE id_factura=? AND id_estado_cobro=1', [$idf]);
        if (abs($c1 - $c0) > 0.001) {
            sim_incidente($cod, $desc . ' — quedó registrado (de ' . $c0 . ' a ' . $c1 . ')', 'CRITICO');
        } else {
            sim_log(['tipo' => 'ANOM_OK', 'cod' => $cod, 'msg' => $rec->flashTxt()]);
        }
    }
}

// Anular una factura que tiene cobros
$conCobro = DB::selectOne('SELECT f.id_factura FROM factura f
                            WHERE f.id_estado_factura=1
                              AND EXISTS (SELECT 1 FROM cobro c WHERE c.id_factura=f.id_factura AND c.id_estado_cobro=1)
                            ORDER BY RAND() LIMIT 1');
if ($conCobro) {
    $idf = (int) $conCobro->id_factura;
    $rec->post('/facturacion/anular-factura', ['id_factura' => $idf, 'motivo' => 'Prueba de anulación indebida'])->seguir();
    if ((int) DB::scalar('SELECT id_estado_factura FROM factura WHERE id_factura=?', [$idf]) === 2) {
        sim_incidente('C7_ANULA_CON_COBROS', 'Se anuló una factura que tenía cobros activos (#' . $idf . ')', 'CRITICO');
    }
}

// Nota de crédito sobre una nota de crédito y sobre una anulada
$nc = DB::selectOne('SELECT id_factura FROM factura WHERE id_tipo_comprobante=5 ORDER BY RAND() LIMIT 1');
if ($nc) {
    $n0 = (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_tipo_comprobante=5');
    $rec->post('/facturacion/nota-credito', ['id_factura' => (int) $nc->id_factura, 'motivo' => 'Prueba'])->seguir();
    if ((int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_tipo_comprobante=5') > $n0) {
        sim_incidente('C8_NC_SOBRE_NC', 'Se emitió una nota de crédito sobre otra nota de crédito', 'ALTO');
    }
}

// =========================================================================
// D. Seña
// =========================================================================
$canc = DB::selectOne('SELECT id_cita FROM cita WHERE id_estado_cita=3 ORDER BY RAND() LIMIT 1');
if ($canc) {
    $id = (int) $canc->id_cita;
    $c0 = (int) DB::scalar('SELECT COUNT(*) FROM cobro WHERE id_cita=?', [$id]);
    $rec->post('/facturacion/sena', ['id_cita' => $id, 'id_metodo_pago' => 1, 'monto' => '50000', 'dia' => $hoy])->seguir();
    if ((int) DB::scalar('SELECT COUNT(*) FROM cobro WHERE id_cita=?', [$id]) > $c0) {
        sim_incidente('D1_SENA_CANCELADA', 'Se cobró una seña sobre una cita CANCELADA (#' . $id . ')', 'CRITICO');
    }
}
$fut = DB::selectOne('SELECT c.id_cita,
                        (SELECT COALESCE(SUM(s.precio),0) FROM cita_servicio cs JOIN servicio s ON s.id_servicio=cs.id_servicio
                          WHERE cs.id_cita=c.id_cita) AS total
                        FROM cita c WHERE c.id_estado_cita IN (1,2) AND c.fecha_hora > NOW()
                          AND NOT EXISTS (SELECT 1 FROM cobro co WHERE co.id_cita=c.id_cita)
                        ORDER BY RAND() LIMIT 1');
if ($fut && (float) $fut->total > 0) {
    $id = (int) $fut->id_cita;
    // Negativa
    $rec->post('/facturacion/sena', ['id_cita' => $id, 'id_metodo_pago' => 1, 'monto' => '-100000', 'dia' => $hoy])->seguir();
    if ((int) DB::scalar('SELECT COUNT(*) FROM cobro WHERE id_cita=?', [$id]) > 0) {
        sim_incidente('D2_SENA_NEGATIVA', 'Se registró una seña negativa (#' . $id . ')', 'CRITICO');
    }
    // Mayor que el total de la cita (sólo una vez, para medir el efecto)
    if ($DIA === 38) {
        $exceso = (int) ((float) $fut->total * 3);
        $rec->post('/facturacion/sena', ['id_cita' => $id, 'id_metodo_pago' => 1, 'monto' => (string) $exceso, 'dia' => $hoy])->seguir();
        $reg = (float) DB::scalar('SELECT COALESCE(SUM(monto),0) FROM cobro WHERE id_cita=? AND id_estado_cobro=1', [$id]);
        if ($reg > (float) $fut->total) {
            sim_incidente('D3_SENA_MAYOR_QUE_TOTAL',
                'Se cobró una seña de ' . $reg . ' sobre una cita cuyos servicios suman ' . $fut->total
                . ' (cita #' . $id . '): el saldo de la futura factura queda negativo', 'ALTO');
            $idCobro = (int) DB::scalar('SELECT id_cobro FROM cobro WHERE id_cita=? ORDER BY id_cobro DESC LIMIT 1', [$id]);
            $rec->post('/facturacion/anular-cobro', ['id_cobro' => $idCobro, 'motivo' => 'Se cobró de más por error'])->seguir();
        }
    }
}
// Seña sobre una cita inexistente
$rec->post('/facturacion/sena', ['id_cita' => 999999, 'id_metodo_pago' => 1, 'monto' => '10000', 'dia' => $hoy])->seguir();

// =========================================================================
// E. Inventario
// =========================================================================
$p = DB::selectOne('SELECT id_producto, fn_producto_stock(id_producto) AS stock FROM producto WHERE activo=1 ORDER BY RAND() LIMIT 1');
if ($p) {
    $idp = (int) $p->id_producto;
    $st0 = (float) DB::scalar('SELECT fn_producto_stock(?)', [$idp]);
    foreach ([
        ['E1_SALIDA_EXCESO', ['id_producto' => $idp, 'modo' => 'movimiento', 'id_tipo_movimiento' => 4,
                              'cantidad' => (string) ($st0 + 1000), 'precio_unitario' => '0'], 'Salida mayor al stock'],
        ['E2_CANTIDAD_CERO', ['id_producto' => $idp, 'modo' => 'movimiento', 'id_tipo_movimiento' => 3,
                              'cantidad' => '0', 'precio_unitario' => '0'], 'Movimiento de cantidad cero'],
        ['E3_CANTIDAD_NEG',  ['id_producto' => $idp, 'modo' => 'movimiento', 'id_tipo_movimiento' => 3,
                              'cantidad' => '-15', 'precio_unitario' => '0'], 'Movimiento de cantidad negativa'],
        ['E4_PRODUCTO_FANTASMA', ['id_producto' => 999999, 'modo' => 'movimiento', 'id_tipo_movimiento' => 3,
                              'cantidad' => '5', 'precio_unitario' => '0'], 'Movimiento sobre un producto inexistente'],
        ['E5_PRECIO_NEG', ['id_producto' => $idp, 'modo' => 'movimiento', 'id_tipo_movimiento' => 3,
                              'cantidad' => '1', 'precio_unitario' => '-500'], 'Movimiento con precio negativo'],
        ['E6_FIJAR_NEGATIVO', ['id_producto' => $idp, 'modo' => 'fijar', 'stock_nuevo' => '-20'], 'Fijar el stock en negativo'],
    ] as [$cod, $d, $desc]) {
        $a = (float) DB::scalar('SELECT fn_producto_stock(?)', [$idp]);
        $rec->post('/inventario/cargar-stock', $d)->seguir();
        $b = (float) DB::scalar('SELECT fn_producto_stock(?)', [$idp]);
        if (abs($b - $a) > 0.0001) {
            sim_incidente($cod, $desc . ' — el stock cambió de ' . $a . ' a ' . $b, 'ALTO');
        } else {
            sim_log(['tipo' => 'ANOM_OK', 'cod' => $cod, 'msg' => $rec->flashTxt()]);
        }
    }
    if ((float) DB::scalar('SELECT fn_producto_stock(?)', [$idp]) < 0) {
        sim_incidente('E7_STOCK_NEGATIVO', 'El producto ' . $idp . ' quedó con stock negativo', 'CRITICO');
    }
}
// Compra sin líneas y con proveedor inexistente
$c0 = (int) DB::scalar('SELECT COUNT(*) FROM compra');
$rec->post('/inventario/compras/guardar', ['id_proveedor' => 999999, 'id_condicion_venta' => 1,
    'nombre' => ['X'], 'cantidad' => ['1'], 'precio' => ['1000']])->seguir();
$rec->post('/inventario/compras/guardar', ['id_proveedor' => (int) DB::scalar('SELECT MIN(id_proveedor) FROM proveedor'),
    'id_condicion_venta' => 1, 'nombre' => [], 'cantidad' => [], 'precio' => []])->seguir();
if ((int) DB::scalar('SELECT COUNT(*) FROM compra') > $c0) {
    sim_incidente('E8_COMPRA_INVALIDA', 'Se registró una compra inválida (proveedor inexistente o sin líneas)', 'ALTO');
}

// =========================================================================
// F. Caja
// =========================================================================
$caja = DB::selectOne('SELECT id_caja, id_usuario FROM caja WHERE id_estado_caja=1 ORDER BY id_caja DESC LIMIT 1');
if ($caja) {
    $n0 = (int) DB::scalar('SELECT COUNT(*) FROM caja WHERE id_estado_caja=1');
    $rec->post('/facturacion/caja/abrir', ['monto_inicial' => '100000'])->seguir();
    if ((int) DB::scalar('SELECT COUNT(*) FROM caja WHERE id_estado_caja=1') > $n0) {
        sim_incidente('F1_DOS_CAJAS', 'Quedó más de una caja abierta a la vez', 'CRITICO');
    }
    // Pago en efectivo por encima del efectivo disponible
    $cta = DB::selectOne('SELECT * FROM vw_cuenta_proveedor WHERE saldo > 0 LIMIT 1');
    if ($cta) {
        $enCaja = (float) DB::scalar('SELECT fn_caja_saldo(?)', [(int) $caja->id_caja]);
        $rec->post('/facturacion/proveedores/pagar', ['id_compra' => (int) $cta->id_compra,
            'id_metodo_pago' => 1, 'monto' => (string) (int) ($enCaja + 1000000), 'referencia' => 'X'])->seguir();
        $nuevo = (float) DB::scalar('SELECT fn_caja_saldo(?)', [(int) $caja->id_caja]);
        if ($nuevo < -0.01) {
            sim_incidente('F2_CAJA_NEGATIVA', 'La caja quedó en ' . $nuevo . ' tras un pago en efectivo', 'CRITICO');
        }
    }
    // Cerrar la caja desde una cuenta que no la abrió y no es admin
    $pro = new Nav();
    if ($pro->entrar('marta', 'profesional123')) {
        $pro->post('/facturacion/caja/cerrar', ['id_caja' => (int) $caja->id_caja])->seguir();
        if ((int) DB::scalar('SELECT id_estado_caja FROM caja WHERE id_caja=?', [(int) $caja->id_caja]) === 2) {
            sim_incidente('F3_CIERRE_AJENO', 'Una profesional cerró la caja que abrió otra persona', 'ALTO');
            $pro->salir();
            // Se vuelve a abrir para no dejar al salón sin caja el resto del día
            $rec->post('/facturacion/caja/abrir', ['monto_inicial' => '150000'])->seguir();
        } else {
            $pro->salir();
        }
    }
}

// =========================================================================
// G. Asistencia
// =========================================================================
$rec->post('/seguridad/asistencia', ['accion' => 'entrada', 'id_turno' => 3, 'id_usuario' => 10,
    'fecha' => date('Y-m-d', strtotime('+5 day'))])->seguir();
if ((int) DB::scalar('SELECT COUNT(*) FROM asistencia WHERE fecha = ?', [date('Y-m-d', strtotime('+5 day'))]) > 0) {
    sim_incidente('G1_ASISTENCIA_FUTURA', 'Se registró asistencia de un día que todavía no llegó', 'ALTO');
}
$rec->post('/seguridad/asistencia', ['accion' => 'salida', 'id_turno' => 4, 'id_usuario' => 11,
    'fecha' => date('Y-m-d', strtotime('-3 day')), 'hora' => '23:59'])->seguir();
$rec->post('/seguridad/asistencia', ['accion' => 'entrada', 'id_turno' => 4, 'id_usuario' => 10,
    'fecha' => $hoy])->seguir();   // turno que no le corresponde

// Un profesional intenta fichar por otro
$pro = new Nav();
if ($pro->entrar('rocio', 'profesional123')) {
    $pro->post('/seguridad/asistencia', ['accion' => 'entrada', 'id_turno' => 3, 'id_usuario' => 10, 'fecha' => $hoy])->seguir();
    if ($pro->dice('Entrada de')) {
        sim_incidente('G2_FICHA_POR_OTRO', 'Una profesional fichó la entrada de otra', 'ALTO');
    }
    $pro->salir();
}

// =========================================================================
// H. Timbrados y catálogos (Administrador)
// =========================================================================
$adm = new Nav();
if ($adm->entrar('admin', 'admin123')) {
    $t0 = (int) DB::scalar('SELECT COUNT(*) FROM timbrado');
    foreach ([
        ['H1_TIMBRADO_CORTO', ['nro_timbrado' => '123', 'establecimiento' => '001', 'punto_expedicion' => '001',
            'nro_desde' => '1', 'nro_hasta' => '100', 'fecha_inicio' => $hoy, 'fecha_fin' => date('Y-m-d', strtotime('+1 year')),
            'id_tipo_comprobante' => 1], 'Timbrado de 3 dígitos'],
        ['H2_TIMBRADO_RANGO', ['nro_timbrado' => '87654321', 'establecimiento' => '001', 'punto_expedicion' => '002',
            'nro_desde' => '500', 'nro_hasta' => '100', 'fecha_inicio' => $hoy, 'fecha_fin' => date('Y-m-d', strtotime('+1 year')),
            'id_tipo_comprobante' => 1], 'Timbrado con rango invertido'],
        ['H3_TIMBRADO_FECHAS', ['nro_timbrado' => '87654322', 'establecimiento' => '001', 'punto_expedicion' => '003',
            'nro_desde' => '1', 'nro_hasta' => '1000', 'fecha_inicio' => date('Y-m-d', strtotime('+1 year')), 'fecha_fin' => $hoy,
            'id_tipo_comprobante' => 1], 'Timbrado con fechas invertidas'],
        ['H4_TIMBRADO_TOPE', ['nro_timbrado' => '87654323', 'establecimiento' => '001', 'punto_expedicion' => '004',
            'nro_desde' => '1', 'nro_hasta' => '99999999', 'fecha_inicio' => $hoy, 'fecha_fin' => date('Y-m-d', strtotime('+1 year')),
            'id_tipo_comprobante' => 1], 'Timbrado con correlativo de 8 dígitos'],
    ] as [$cod, $d, $desc]) {
        $a = (int) DB::scalar('SELECT COUNT(*) FROM timbrado');
        $adm->post('/facturacion/timbrados/guardar', $d)->seguir();
        if ((int) DB::scalar('SELECT COUNT(*) FROM timbrado') > $a) {
            sim_incidente($cod, $desc . ' — se guardó', 'ALTO');
        } else {
            sim_log(['tipo' => 'ANOM_OK', 'cod' => $cod, 'msg' => $adm->flashTxt()]);
        }
    }

    // Servicio con precio negativo / duración negativa
    $s0 = (int) DB::scalar('SELECT COUNT(*) FROM servicio');
    $adm->post('/servicios/guardar', ['nombre' => 'Servicio inválido ' . $DIA, 'id_categoria' => 1,
        'precio' => '-90000', 'duracion_min' => '30', 'tasa_iva' => '10'])->seguir();
    $adm->post('/servicios/guardar', ['nombre' => 'Servicio inválido2 ' . $DIA, 'id_categoria' => 1,
        'precio' => '90000', 'duracion_min' => '-30', 'tasa_iva' => '10'])->seguir();
    $adm->post('/servicios/guardar', ['nombre' => 'Servicio IVA raro ' . $DIA, 'id_categoria' => 1,
        'precio' => '90000', 'duracion_min' => '30', 'tasa_iva' => '21'])->seguir();
    if ((int) DB::scalar('SELECT COUNT(*) FROM servicio') > $s0) {
        sim_incidente('H5_SERVICIO_INVALIDO', 'Se guardó un servicio con precio, duración o IVA inválidos', 'ALTO');
    }

    // Cliente con cédula duplicada / email inválido / nacimiento futuro
    $ced = (string) DB::scalar('SELECT cedula FROM persona WHERE cedula IS NOT NULL LIMIT 1');
    $cl0 = (int) DB::scalar('SELECT COUNT(*) FROM cliente');
    $adm->post('/clientes/guardar', ['nombre' => 'Duplicada', 'apellido' => 'Prueba', 'cedula' => $ced])->seguir();
    $adm->post('/clientes/guardar', ['nombre' => 'Correo', 'apellido' => 'Malo', 'email' => 'esto-no-es-mail'])->seguir();
    $adm->post('/clientes/guardar', ['nombre' => 'Nacida', 'apellido' => 'Manana',
        'fecha_nacimiento' => date('Y-m-d', strtotime('+30 day'))])->seguir();
    if ((int) DB::scalar('SELECT COUNT(*) FROM cliente') > $cl0) {
        sim_incidente('H6_CLIENTE_INVALIDO', 'Se guardó un cliente con cédula duplicada, email inválido o nacimiento futuro', 'MEDIO');
    }

    $adm->salir();
}

$rec->salir();
sim_log(['tipo' => 'ANOM_FIN', 'dia' => $DIA]);
