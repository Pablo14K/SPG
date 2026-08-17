<?php
/**
 * Concurrencia de verdad: procesos separados que largan en el mismo instante.
 */

declare(strict_types=1);

use App\Servicios\Agenda;
use Illuminate\Support\Facades\DB;

/** @var int $DIA */

/** Cuentas distintas: dos sesiones del mismo usuario se desplazan entre sí. */
$CUENTAS = [
    ['admin', 'admin123'],
    ['recepcion', 'recepcion123'],
    ['marta', 'profesional123'],
    ['rocio', 'profesional123'],
    ['lucia', 'profesional123'],
    ['sofia', 'profesional123'],
    ['karen', 'profesional123'],
];

/**
 * Lanza N pedidos en paralelo. $trabajos = [[etq, usr, pass, metodo, uri, datos], ...]
 * Devuelve las salidas.
 */
function enParalelo(array $trabajos): array
{
    $largada = microtime(true) + 3.0;
    $php = PHP_BINARY;
    $script = '/app/_sim30/worker.php';
    $procs = [];

    foreach ($trabajos as $t) {
        [$etq, $usr, $pass, $met, $uri, $datos] = $t;
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' '
             . escapeshellarg($etq) . ' ' . escapeshellarg($usr) . ' ' . escapeshellarg($pass) . ' '
             . escapeshellarg($met) . ' ' . escapeshellarg($uri) . ' '
             . escapeshellarg(json_encode($datos)) . ' ' . escapeshellarg((string) $largada);
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tub, '/app');
        if (is_resource($p)) {
            $procs[] = [$p, $tub];
        }
    }

    $salidas = [];
    foreach ($procs as [$p, $tub]) {
        $out = stream_get_contents($tub[1]);
        $err = stream_get_contents($tub[2]);
        fclose($tub[1]);
        fclose($tub[2]);
        proc_close($p);
        foreach (explode("\n", trim((string) $out)) as $l) {
            $j = json_decode(trim($l), true);
            if (is_array($j)) {
                $salidas[] = $j;
            }
        }
        if (trim((string) $err) !== '') {
            sim_log(['tipo' => 'WORKER_ERR', 'err' => substr(trim((string) $err), 0, 300)]);
        }
    }

    return $salidas;
}

$hoy = date('Y-m-d');

// =========================================================================
// A. Cinco reservas simultáneas sobre el mismo hueco
// =========================================================================
$prof = (int) (DB::scalar('SELECT u.id_usuario FROM usuario u JOIN usuario_turno ut ON ut.id_usuario=u.id_usuario
                            WHERE u.activo=1 ORDER BY RAND() LIMIT 1') ?: 10);
$servicio = 15;   // Depilación de cejas, 20 min
$dur = Agenda::duracion([$servicio]);
$hueco = null;
foreach (Agenda::diasConCupo($prof, date('Y-m-d', strtotime('+1 day')), 14, $dur) as $d) {
    foreach (Agenda::slotsProfesional($prof, $d, $dur) as $h) {
        $fh = $d . ' ' . substr($h, 0, 5) . ':00';
        if (Agenda::huecoLibre($prof, $fh, $dur)) { $hueco = $fh; break 2; }
    }
}

if ($hueco) {
    $clientes = array_map(fn ($r) => (int) $r->id_cliente,
        DB::select('SELECT id_cliente FROM cliente WHERE activo=1 ORDER BY RAND() LIMIT 5'));
    $trabajos = [];
    foreach ($clientes as $i => $cid) {
        [$u, $p] = $CUENTAS[$i % count($CUENTAS)];
        $trabajos[] = ["res$i", $u, $p, 'POST', '/citas/guardar',
            ['id_cliente' => $cid, 'servicios' => [$servicio], 'id_usuario' => $prof,
             'fecha_hora' => substr($hueco, 0, 16)]];
    }
    $antes = (int) DB::scalar('SELECT COUNT(*) FROM cita WHERE id_usuario=? AND fecha_hora=?', [$prof, $hueco]);
    $sal = enParalelo($trabajos);
    $desp = (int) DB::scalar('SELECT COUNT(*) FROM cita c JOIN estado_cita e ON e.id_estado_cita=c.id_estado_cita
                               WHERE c.id_usuario=? AND c.fecha_hora=? AND e.bloquea_agenda=1', [$prof, $hueco]);
    sim_log(['tipo' => 'CONC', 'caso' => 'A_AGENDA', 'hueco' => $hueco, 'prof' => $prof,
             'antes' => $antes, 'despues' => $desp, 'salidas' => $sal]);
    sim_check($desp <= 1, 'CONC_A_DOBLE_RESERVA',
        "Cinco reservas simultáneas sobre $hueco dejaron $desp citas ocupando la franja del profesional $prof", 'CRITICO');
}

// =========================================================================
// B. Dos emisiones simultáneas del MISMO comprobante
// =========================================================================
$cita = DB::selectOne('SELECT c.id_cita FROM cita c
                        WHERE c.id_estado_cita=4
                          AND NOT EXISTS (SELECT 1 FROM factura f WHERE f.id_cita=c.id_cita AND f.id_estado_factura=1)
                          AND EXISTS (SELECT 1 FROM cita_servicio cs WHERE cs.id_cita=c.id_cita)
                        ORDER BY RAND() LIMIT 1');
if ($cita) {
    $idc = (int) $cita->id_cita;
    $trabajos = [];
    for ($i = 0; $i < 3; $i++) {
        [$u, $p] = $CUENTAS[$i];
        $trabajos[] = ["emi$i", $u, $p, 'POST', '/facturacion/emitir',
            ['id_cita' => $idc, 'id_tipo_comprobante' => 8, 'id_condicion_venta' => 1]];
    }
    $sal = enParalelo($trabajos);
    $cuantas = (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_cita=? AND id_estado_factura=1', [$idc]);
    sim_log(['tipo' => 'CONC', 'caso' => 'B_EMISION_MISMA_CITA', 'cita' => $idc, 'facturas' => $cuantas, 'salidas' => $sal]);
    sim_check($cuantas <= 1, 'CONC_B_DOBLE_COMPROBANTE',
        "Tres emisiones simultáneas de la cita #$idc dejaron $cuantas comprobantes vigentes", 'CRITICO');
}

// =========================================================================
// B2. Emisiones simultáneas de citas DISTINTAS: carrera por el correlativo
// =========================================================================
$citas = DB::select('SELECT c.id_cita FROM cita c
                      WHERE c.id_estado_cita=4
                        AND NOT EXISTS (SELECT 1 FROM factura f WHERE f.id_cita=c.id_cita AND f.id_estado_factura=1)
                        AND EXISTS (SELECT 1 FROM cita_servicio cs WHERE cs.id_cita=c.id_cita)
                      ORDER BY RAND() LIMIT 4');
if (count($citas) >= 2) {
    $tim = (int) DB::scalar('SELECT fn_timbrado_vigente(8, CURDATE())');
    $antes = (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_timbrado=?', [$tim]);
    $trabajos = [];
    foreach ($citas as $i => $c) {
        [$u, $p] = $CUENTAS[$i % count($CUENTAS)];
        $trabajos[] = ["cor$i", $u, $p, 'POST', '/facturacion/emitir',
            ['id_cita' => (int) $c->id_cita, 'id_tipo_comprobante' => 8, 'id_condicion_venta' => 1]];
    }
    $sal = enParalelo($trabajos);
    $desp = (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_timbrado=?', [$tim]);
    $dup = (int) DB::scalar('SELECT COUNT(*) FROM (SELECT nro_correlativo FROM factura WHERE id_timbrado=?
                              GROUP BY nro_correlativo HAVING COUNT(*)>1) x', [$tim]);
    $perdidas = count($citas) - ($desp - $antes);
    sim_log(['tipo' => 'CONC', 'caso' => 'B2_CORRELATIVO', 'intentos' => count($citas),
             'emitidas' => $desp - $antes, 'duplicados' => $dup, 'salidas' => $sal]);
    sim_check($dup === 0, 'CONC_B2_CORRELATIVO_DUPLICADO',
        "Emisiones simultáneas produjeron $dup correlativo(s) repetido(s) en el timbrado $tim", 'CRITICO');
    if ($perdidas > 0) {
        sim_incidente('CONC_B2_EMISION_PERDIDA',
            "De " . count($citas) . " emisiones simultáneas, $perdidas fallaron por la carrera del correlativo "
            . "(fn_siguiente_correlativo usa MAX(nro_correlativo) sin candado; salva el índice único uq_factura_nro)", 'ALTO');
    }
}

// =========================================================================
// C. Dos cobros simultáneos sobre la MISMA factura
// =========================================================================
$f = DB::selectOne('SELECT id_factura, fn_factura_saldo(id_factura) AS saldo, fn_factura_total(id_factura) AS total
                      FROM factura WHERE id_estado_factura=1 AND fn_factura_saldo(id_factura) > 1000
                     ORDER BY RAND() LIMIT 1');
if ($f) {
    $idf = (int) $f->id_factura;
    $monto = (string) (int) $f->saldo;
    $base = ['id_factura' => $idf, 'metodo' => ['1'], 'monto' => [$monto], 'referencia' => [''],
             'marca' => [''], 'tipo_tarjeta' => [''], 'cuotas' => ['1'], 'ultimos_4' => [''],
             'nro_boleta' => [''], 'cod_autorizacion' => [''], 'banco' => [''], 'nro_cheque' => [''],
             'nro_operacion' => [''], 'fecha_emision' => ['']];
    $trabajos = [];
    for ($i = 0; $i < 3; $i++) {
        [$u, $p] = $CUENTAS[$i];
        $trabajos[] = ["cob$i", $u, $p, 'POST', '/facturacion/cobrar', $base];
    }
    $sal = enParalelo($trabajos);
    $cobrado = (float) DB::scalar('SELECT COALESCE(SUM(monto),0) FROM cobro WHERE id_factura=? AND id_estado_cobro=1', [$idf]);
    $total = (float) $f->total;
    $saldo = (float) DB::scalar('SELECT fn_factura_saldo(?)', [$idf]);
    sim_log(['tipo' => 'CONC', 'caso' => 'C_COBRO', 'factura' => $idf, 'total' => $total,
             'cobrado' => $cobrado, 'saldo' => $saldo, 'salidas' => $sal]);
    sim_check($saldo >= -0.01, 'CONC_C_SOBRECOBRO',
        "Tres cobros simultáneos de la factura #$idf dejaron el saldo en $saldo (total $total, cobrado $cobrado)", 'CRITICO');
}

// =========================================================================
// D. Dos consumos simultáneos del último stock
// =========================================================================
$prod = DB::selectOne('SELECT p.id_producto, fn_producto_stock(p.id_producto, 1) AS stock
                         FROM producto p WHERE p.activo=1 AND fn_producto_stock(p.id_producto, 1) BETWEEN 1 AND 40
                        ORDER BY RAND() LIMIT 1');
if ($prod) {
    $idp = (int) $prod->id_producto;
    $st = (float) $prod->stock;
    $cant = (string) (int) max(1, floor($st));
    $trabajos = [];
    for ($i = 0; $i < 3; $i++) {
        [$u, $p] = $CUENTAS[$i];
        $trabajos[] = ["stk$i", $u, $p, 'POST', '/inventario/cargar-stock',
            ['id_producto' => $idp, 'modo' => 'movimiento', 'id_tipo_movimiento' => 4,
             'cantidad' => $cant, 'precio_unitario' => '0', 'referencia' => 'CONC' . $DIA]];
    }
    $sal = enParalelo($trabajos);
    $fin = (float) DB::scalar('SELECT fn_producto_stock(?, 1)', [$idp]);
    sim_log(['tipo' => 'CONC', 'caso' => 'D_STOCK', 'producto' => $idp, 'antes' => $st,
             'pedido' => $cant, 'despues' => $fin, 'salidas' => $sal]);
    sim_check($fin >= -0.0001, 'CONC_D_STOCK_NEGATIVO',
        "Tres salidas simultáneas de $cant sobre un stock de $st dejaron el producto $idp en $fin", 'CRITICO');
}

// =========================================================================
// E. Dos altas simultáneas del mismo cliente (misma cédula)
// =========================================================================
$ced = (string) (4900000 + $DIA);
$trabajos = [];
for ($i = 0; $i < 3; $i++) {
    [$u, $p] = $CUENTAS[$i];
    $trabajos[] = ["cli$i", $u, $p, 'POST', '/clientes/guardar',
        ['nombre' => 'Simultanea', 'apellido' => 'Prueba' . $DIA, 'cedula' => $ced,
         'telefono' => '0981' . $ced, 'email' => 'sim' . $DIA . '@correo.com.py']];
}
$sal = enParalelo($trabajos);
$cuantos = (int) DB::scalar('SELECT COUNT(*) FROM persona WHERE cedula = ?', [$ced]);
$clis = (int) DB::scalar('SELECT COUNT(*) FROM cliente c JOIN persona pe ON pe.id_persona=c.id_persona WHERE pe.cedula = ?', [$ced]);
sim_log(['tipo' => 'CONC', 'caso' => 'E_CLIENTE', 'cedula' => $ced, 'personas' => $cuantos, 'clientes' => $clis, 'salidas' => $sal]);
sim_check($cuantos <= 1 && $clis <= 1, 'CONC_E_CLIENTE_DUPLICADO',
    "Tres altas simultáneas con la cédula $ced dejaron $cuantos persona(s) y $clis cliente(s)", 'ALTO');

// =========================================================================
// F. Dos aperturas de caja simultáneas
// =========================================================================
$abierta = DB::selectOne('SELECT id_caja FROM caja WHERE id_estado_caja=1 LIMIT 1');
if ($abierta) {
    // Se cierra primero para poder probar la carrera de apertura
    $a = new Nav();
    if ($a->entrar('admin', 'admin123')) {
        $a->post('/facturacion/caja/cerrar', ['id_caja' => (int) $abierta->id_caja])->seguir();
        $a->salir();
    }
}
if (! DB::selectOne('SELECT id_caja FROM caja WHERE id_estado_caja=1 LIMIT 1')) {
    $trabajos = [];
    for ($i = 0; $i < 3; $i++) {
        [$u, $p] = $CUENTAS[$i];
        $trabajos[] = ["caj$i", $u, $p, 'POST', '/facturacion/caja/abrir', ['monto_inicial' => '150000']];
    }
    $sal = enParalelo($trabajos);
    $n = (int) DB::scalar('SELECT COUNT(*) FROM caja WHERE id_estado_caja=1');
    sim_log(['tipo' => 'CONC', 'caso' => 'F_CAJA', 'abiertas' => $n, 'salidas' => $sal]);
    sim_check($n <= 1, 'CONC_F_CAJAS_ABIERTAS',
        "Tres aperturas simultáneas dejaron $n cajas abiertas a la vez", 'CRITICO');
    // Se dejan las sobrantes cerradas para no romper el resto de la simulación
    if ($n > 1) {
        $ids = DB::select('SELECT id_caja FROM caja WHERE id_estado_caja=1 ORDER BY id_caja');
        $a = new Nav();
        if ($a->entrar('admin', 'admin123')) {
            foreach (array_slice($ids, 1) as $c) {
                $a->post('/facturacion/caja/cerrar', ['id_caja' => (int) $c->id_caja])->seguir();
            }
            $a->salir();
        }
    }
}

// =========================================================================
// G. Cancelar y reprogramar la MISMA cita a la vez
// =========================================================================
$c2 = DB::selectOne('SELECT id_cita, id_usuario FROM cita WHERE id_estado_cita IN (1,2) AND fecha_hora > NOW() ORDER BY RAND() LIMIT 1');
if ($c2) {
    $idc = (int) $c2->id_cita;
    $sal = enParalelo([
        ['can', $CUENTAS[0][0], $CUENTAS[0][1], 'POST', '/citas/cancelar', ['id_cita' => $idc, 'dia' => $hoy]],
        ['rep', $CUENTAS[1][0], $CUENTAS[1][1], 'POST', '/citas/reprogramar',
            ['id_cita' => $idc, 'nueva_fecha' => date('Y-m-d', strtotime('+9 day')) . ' 10:00', 'dia' => $hoy]],
    ]);
    $est = (int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita=?', [$idc]);
    sim_log(['tipo' => 'CONC', 'caso' => 'G_CITA', 'cita' => $idc, 'estado_final' => $est, 'salidas' => $sal]);
    if ($est === 2) {
        $cancelada = (int) DB::scalar("SELECT COUNT(*) FROM auditoria WHERE tabla_afectada='cita' AND id_registro=? AND accion='CANCELACION'", [$idc]);
        if ($cancelada) {
            sim_incidente('CONC_G_CANCELA_PERDIDA',
                "La cita #$idc quedó REPROGRAMADA aunque la cancelación se registró en auditoría: "
                . 'el cliente cree que la canceló y sigue ocupando agenda', 'ALTO');
        }
    }
}

// =========================================================================
// H. Anular el mismo cobro dos veces a la vez
// =========================================================================
$cob = DB::selectOne('SELECT id_cobro, id_factura, monto FROM cobro WHERE id_estado_cobro=1 AND id_factura IS NOT NULL ORDER BY RAND() LIMIT 1');
if ($cob) {
    // Hace falta caja abierta
    if (! DB::selectOne('SELECT id_caja FROM caja WHERE id_estado_caja=1 LIMIT 1')) {
        $a = new Nav();
        if ($a->entrar('admin', 'admin123')) { $a->post('/facturacion/caja/abrir', ['monto_inicial' => '150000'])->seguir(); $a->salir(); }
    }
    $sal = enParalelo([
        ['an0', $CUENTAS[0][0], $CUENTAS[0][1], 'POST', '/facturacion/anular-cobro', ['id_cobro' => (int) $cob->id_cobro, 'motivo' => 'Doble anulación A']],
        ['an1', $CUENTAS[1][0], $CUENTAS[1][1], 'POST', '/facturacion/anular-cobro', ['id_cobro' => (int) $cob->id_cobro, 'motivo' => 'Doble anulación B']],
    ]);
    $filas = (int) DB::scalar("SELECT COUNT(*) FROM auditoria WHERE tabla_afectada='cobro' AND id_registro=? AND accion='ANULACION'", [(int) $cob->id_cobro]);
    sim_log(['tipo' => 'CONC', 'caso' => 'H_ANULA_COBRO', 'cobro' => (int) $cob->id_cobro, 'auditorias' => $filas, 'salidas' => $sal]);
    if ($filas > 1) {
        sim_incidente('CONC_H_AUDITORIA_DUPLICADA',
            'La anulación simultánea del cobro #' . $cob->id_cobro . " dejó $filas filas de auditoría por un solo hecho", 'BAJO');
    }
}

sim_log(['tipo' => 'CONC_FIN', 'dia' => $DIA]);


// ===========================================================================
//  I. DOS LOCALES A LA VEZ — lo que la simulación de 60 días no podía probar
//
//  El aislamiento por sucursal es de la 7.30.0, y bajo carga es donde importa:
//  dos cajas abriéndose al mismo tiempo en locales distintos tienen que quedar
//  las dos abiertas —son cajones distintos—, y el mismo producto descontado a
//  la vez en los dos locales no puede pisarse: el candado de `trg_movinv_bi`
//  se mudó de `producto` a `producto_sucursal` justo para eso.
// ===========================================================================

$suc2 = (int) (DB::scalar("SELECT id_sucursal FROM sucursal WHERE nombre='Peluqueria San Lorenzo' AND activo=1") ?: 0);
$suc1 = (int) (DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo=1') ?: 1);

if ($suc2 && $suc2 !== $suc1) {

    // --- I.1 Dos cajas, una por local, al mismo tiempo ---------------------
    // No es una carrera por el mismo recurso: es la comprobación de que NO se
    // estorban. Si queda una sola abierta, el aislamiento no existe.
    DB::statement('UPDATE caja SET id_estado_caja = 2, fecha_cierre = NOW(), monto_final = monto_inicial
                    WHERE id_estado_caja = 1');
    $sal = enParalelo([
        ['cj1', 'admin', 'admin123', 'POST', '/facturacion/caja/abrir', ['monto_inicial' => '120000']],
        ['cj2', 'recepcion', 'recepcion123', 'POST', '/facturacion/caja/abrir', ['monto_inicial' => '130000']],
    ]);
    $ab1 = (int) DB::scalar('SELECT COUNT(*) FROM caja WHERE id_estado_caja=1 AND id_sucursal=?', [$suc1]);
    $ab2 = (int) DB::scalar('SELECT COUNT(*) FROM caja WHERE id_estado_caja=1 AND id_sucursal=?', [$suc2]);
    sim_log(['tipo' => 'CONC', 'caso' => 'I1_CAJA_DOS_LOCALES', 'suc1' => $ab1, 'suc2' => $ab2, 'salidas' => $sal]);
    if ($ab1 > 1 || $ab2 > 1) {
        sim_incidente('CONC_I1_CAJA_DOBLE',
            "Quedaron $ab1 y $ab2 cajas abiertas: en un mismo local no puede haber más de una", 'CRITICO');
    }

    // --- I.2 El mismo producto, descontado a la vez en los dos locales ------
    $p = DB::selectOne(
        'SELECT ps.id_producto FROM producto_sucursal ps
          WHERE ps.id_sucursal = ? AND fn_producto_stock(ps.id_producto, ?) > 5
            AND EXISTS (SELECT 1 FROM producto_sucursal q WHERE q.id_producto = ps.id_producto AND q.id_sucursal = ?)
          ORDER BY RAND() LIMIT 1', [$suc2, $suc2, $suc1]);

    if ($p) {
        $idp = (int) $p->id_producto;
        $a0 = (float) DB::scalar('SELECT fn_producto_stock(?,?)', [$idp, $suc1]);
        $b0 = (float) DB::scalar('SELECT fn_producto_stock(?,?)', [$idp, $suc2]);

        $sal = enParalelo([
            ['s1', 'admin', 'admin123', 'POST', '/inventario/cargar-stock',
                ['modo' => 'movimiento', 'id_producto' => $idp, 'id_tipo_movimiento' => 4,
                 'cantidad' => '2', 'referencia' => 'CONC-S1', 'observaciones' => 'Salida simultanea local 1']],
            ['s2', 'recepcion', 'recepcion123', 'POST', '/inventario/cargar-stock',
                ['modo' => 'movimiento', 'id_producto' => $idp, 'id_tipo_movimiento' => 4,
                 'cantidad' => '2', 'referencia' => 'CONC-S2', 'observaciones' => 'Salida simultanea local 2']],
        ]);

        $a1 = (float) DB::scalar('SELECT fn_producto_stock(?,?)', [$idp, $suc1]);
        $b1 = (float) DB::scalar('SELECT fn_producto_stock(?,?)', [$idp, $suc2]);
        sim_log(['tipo' => 'CONC', 'caso' => 'I2_STOCK_DOS_LOCALES', 'producto' => $idp,
                 'suc1' => [$a0, $a1], 'suc2' => [$b0, $b1], 'salidas' => $sal]);

        if ($a1 < -0.0001 || $b1 < -0.0001) {
            sim_incidente('CONC_I2_STOCK_NEGATIVO',
                "El producto #$idp quedó en $a1 / $b1 tras dos salidas simultáneas en locales distintos", 'CRITICO');
        }
        // Y lo importante: lo descontado en total no puede pasar lo pedido.
        $bajo = ($a0 - $a1) + ($b0 - $b1);
        if ($bajo > 4.0001) {
            sim_incidente('CONC_I2_DOBLE_DESCUENTO',
                "El producto #$idp bajó $bajo en total cuando se pidieron 2 y 2", 'CRITICO');
        }
    }

    // --- I.3 Dos citas simultáneas para el MISMO profesional ---------------
    // Marta trabaja en los dos locales: dos recepciones distintas le agendan la
    // misma hora. Tiene que quedar una sola, venga de donde venga el pedido.
    $idMarta = (int) (DB::scalar("SELECT id_usuario FROM usuario WHERE username='marta'") ?: 0);
    $sv = DB::selectOne('SELECT id_servicio FROM servicio WHERE activo=1 ORDER BY RAND() LIMIT 1');
    $c1 = DB::selectOne('SELECT id_cliente FROM cliente WHERE activo=1 ORDER BY RAND() LIMIT 1');
    $c2 = DB::selectOne('SELECT id_cliente FROM cliente WHERE activo=1 AND id_cliente<>? ORDER BY RAND() LIMIT 1',
                        [(int) ($c1->id_cliente ?? 0)]);

    if ($idMarta && $sv && $c1 && $c2) {
        $fecha = date('Y-m-d', strtotime('+6 days'));
        $hora = '11:00';
        $antes = (int) DB::scalar(
            'SELECT COUNT(*) FROM cita c JOIN estado_cita e USING(id_estado_cita)
              WHERE c.id_usuario=? AND c.fecha_hora=? AND e.bloquea_agenda=1',
            [$idMarta, "$fecha $hora:00"]);

        $sal = enParalelo([
            ['m1', 'admin', 'admin123', 'POST', '/citas/guardar',
                ['id_cliente' => (int) $c1->id_cliente, 'id_usuario' => $idMarta,
                 'servicios' => [(int) $sv->id_servicio], 'fecha' => $fecha, 'hora' => $hora]],
            ['m2', 'recepcion', 'recepcion123', 'POST', '/citas/guardar',
                ['id_cliente' => (int) $c2->id_cliente, 'id_usuario' => $idMarta,
                 'servicios' => [(int) $sv->id_servicio], 'fecha' => $fecha, 'hora' => $hora]],
        ]);

        $despues = (int) DB::scalar(
            'SELECT COUNT(*) FROM cita c JOIN estado_cita e USING(id_estado_cita)
              WHERE c.id_usuario=? AND c.fecha_hora=? AND e.bloquea_agenda=1',
            [$idMarta, "$fecha $hora:00"]);
        sim_log(['tipo' => 'CONC', 'caso' => 'I3_DOBLE_RESERVA_MULTISUC',
                 'antes' => $antes, 'despues' => $despues, 'salidas' => $sal]);

        if ($despues - $antes > 1) {
            sim_incidente('CONC_I3_DOBLE_RESERVA',
                'El mismo profesional quedó con ' . ($despues - $antes)
                . ' citas a la misma hora, pedidas desde dos locales a la vez', 'CRITICO');
        }
    }
}

sim_log(['tipo' => 'CONC_MULTISUC_FIN', 'dia' => $DIA]);
