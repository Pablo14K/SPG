<?php
/**
 * Auditoría de cierre: se recalcula todo por fuera del sistema y se compara
 * contra lo que el sistema dice. Nada de confiar en sus propias funciones.
 */

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

$R = [];
$put = function (string $k, $v) use (&$R) { $R[$k] = $v; };
$mal = function (string $cod, string $det, string $sev = 'ALTO') { sim_incidente($cod, $det, $sev); };

// =========================================================================
// 1. INVENTARIO — stock recalculado a mano contra fn_producto_stock
// =========================================================================
$movs = DB::select('SELECT m.id_producto, m.id_sucursal, m.cantidad, t.signo
                      FROM movimiento_inventario m
                      JOIN tipo_movimiento_inventario t ON t.id_tipo_movimiento = m.id_tipo_movimiento');
$calc = [];
foreach ($movs as $m) {
    $calc[(int) $m->id_producto] = ($calc[(int) $m->id_producto] ?? 0.0)
        + ($m->signo === 'E' ? (float) $m->cantidad : -(float) $m->cantidad);
}
// **El stock es de (producto, sucursal), no del producto.** Desde la 7.33.0 el
// catálogo es único y lo que es de cada local es la existencia, así que sumar
// todos los movimientos y compararlos contra el stock de UNA sucursal daba una
// discrepancia crítica en cada corrida — siempre falsa. Un detector que grita
// lobo en todas las corridas deja de servir para detectar.
$calcSuc = [];
foreach ($movs as $m) {
    $k = (int) $m->id_producto . '-' . (int) $m->id_sucursal;
    $calcSuc[$k] = ($calcSuc[$k] ?? 0.0) + ($m->signo === 'E' ? (float) $m->cantidad : -(float) $m->cantidad);
}
$difStock = 0; $negativos = [];
foreach (DB::select('SELECT ps.id_producto, ps.id_sucursal, p.nombre, su.nombre AS local,
                            fn_producto_stock(ps.id_producto, ps.id_sucursal) AS stock
                       FROM producto_sucursal ps
                       JOIN producto p ON p.id_producto = ps.id_producto
                       JOIN sucursal su ON su.id_sucursal = ps.id_sucursal') as $p) {
    $mio = round($calcSuc[(int) $p->id_producto . '-' . (int) $p->id_sucursal] ?? 0.0, 4);
    $suyo = round((float) $p->stock, 4);
    if (abs($mio - $suyo) > 0.0001) {
        $difStock++;
        $mal('AUD_STOCK_DIFIERE',
            "«{$p->nombre}» en {$p->local}: recalculado $mio, el sistema dice $suyo", 'CRITICO');
    }
    if ($suyo < -0.0001) { $negativos[] = $p->nombre . ' en ' . $p->local . ' = ' . $suyo; }
}
if ($negativos) {
    $mal('AUD_STOCK_NEGATIVO', 'Productos con stock negativo: ' . implode(', ', $negativos), 'CRITICO');
}
$put('stock_diferencias', $difStock);
$put('stock_negativos', count($negativos));

// Consumo: cada producto_utilizado tiene que tener su movimiento de salida
$pu = (float) DB::scalar('SELECT COALESCE(SUM(cantidad),0) FROM producto_utilizado');
$sal = (float) DB::scalar("SELECT COALESCE(SUM(cantidad),0) FROM movimiento_inventario WHERE id_tipo_movimiento = 2");
$put('consumo_producto_utilizado', $pu);
$put('consumo_movimientos', $sal);
if (abs($pu - $sal) > 0.0001) {
    $mal('AUD_CONSUMO_DESCUADRE', "producto_utilizado suma $pu y los movimientos de consumo suman $sal", 'ALTO');
}

// =========================================================================
// 2. FACTURACIÓN — totales, saldos, numeración
// =========================================================================
$facs = DB::select('SELECT f.id_factura, f.id_cita, f.id_cliente, f.id_estado_factura, f.id_timbrado,
                           f.nro_correlativo, f.id_tipo_comprobante, tc.signo,
                           fn_factura_subtotal(f.id_factura) sub, fn_factura_descuento(f.id_factura) des,
                           fn_factura_total(f.id_factura) tot, fn_factura_saldo(f.id_factura) sal
                      FROM factura f JOIN tipo_comprobante tc ON tc.id_tipo_comprobante=f.id_tipo_comprobante');
$detalle = [];
foreach (DB::select('SELECT id_factura, cantidad, precio_unitario FROM detalle_factura') as $d) {
    $detalle[(int) $d->id_factura] = ($detalle[(int) $d->id_factura] ?? 0.0) + round((float) $d->cantidad * (float) $d->precio_unitario, 2);
}
$desc = [];
foreach (DB::select('SELECT id_factura, monto_aplicado FROM factura_descuento') as $d) {
    $desc[(int) $d->id_factura] = ($desc[(int) $d->id_factura] ?? 0.0) + (float) $d->monto_aplicado;
}
$cobPorFac = [];
foreach (DB::select('SELECT id_factura, monto FROM cobro WHERE id_estado_cobro=1 AND id_factura IS NOT NULL') as $c) {
    $cobPorFac[(int) $c->id_factura] = ($cobPorFac[(int) $c->id_factura] ?? 0.0) + (float) $c->monto;
}
$cobPorCita = [];
foreach (DB::select('SELECT id_cita, monto FROM cobro WHERE id_estado_cobro=1 AND id_cita IS NOT NULL') as $c) {
    $cobPorCita[(int) $c->id_cita] = ($cobPorCita[(int) $c->id_cita] ?? 0.0) + (float) $c->monto;
}

$errTot = 0; $saldoNeg = []; $sinDetalle = 0;
foreach ($facs as $f) {
    $id = (int) $f->id_factura;
    $sub = round($detalle[$id] ?? 0.0, 2);
    $de = round($desc[$id] ?? 0.0, 2);
    $tot = round(max($sub - $de, 0), 2);
    if (abs($sub - (float) $f->sub) > 0.01 || abs($tot - (float) $f->tot) > 0.01) {
        $errTot++;
        $mal('AUD_TOTAL_FACTURA', "Factura #$id: recalculado sub=$sub tot=$tot; el sistema dice sub={$f->sub} tot={$f->tot}", 'CRITICO');
    }
    if ($sub <= 0 && (int) $f->id_estado_factura === 1) { $sinDetalle++; }
    $miSaldo = round($tot - ($cobPorFac[$id] ?? 0.0) - ($f->id_cita ? ($cobPorCita[(int) $f->id_cita] ?? 0.0) : 0.0), 2);
    if (abs($miSaldo - (float) $f->sal) > 0.01) {
        $mal('AUD_SALDO_FACTURA', "Factura #$id: saldo recalculado $miSaldo, el sistema dice {$f->sal}", 'CRITICO');
    }
    if ((float) $f->sal < -0.01 && (int) $f->signo === 1) {
        $saldoNeg[] = "#$id = {$f->sal}";
    }
}
$put('facturas', count($facs));
$put('facturas_total_mal', $errTot);
$put('facturas_sin_detalle', $sinDetalle);
if ($sinDetalle) {
    $mal('AUD_FACTURA_SIN_DETALLE', "$sinDetalle comprobante(s) vigente(s) sin ningún renglón (subtotal 0)", 'ALTO');
}
if ($saldoNeg) {
    $mal('AUD_SALDO_NEGATIVO', 'Comprobantes con saldo negativo (se cobró de más): ' . implode(', ', array_slice($saldoNeg, 0, 8))
        . ' — total ' . count($saldoNeg), 'ALTO');
}
$put('facturas_saldo_negativo', count($saldoNeg));

// Numeración: sin repetidos y sin huecos por timbrado
foreach (DB::select('SELECT id_timbrado, COUNT(*) n, MIN(nro_correlativo) mn, MAX(nro_correlativo) mx,
                            COUNT(DISTINCT nro_correlativo) d FROM factura GROUP BY id_timbrado') as $t) {
    if ((int) $t->n !== (int) $t->d) {
        $mal('AUD_CORRELATIVO_REPETIDO', "Timbrado {$t->id_timbrado}: {$t->n} comprobantes y sólo {$t->d} números distintos", 'CRITICO');
    }
    $esperados = (int) $t->mx - (int) $t->mn + 1;
    if ($esperados !== (int) $t->n) {
        $huecos = $esperados - (int) $t->n;
        $mal('AUD_CORRELATIVO_HUECO', "Timbrado {$t->id_timbrado}: numeración de {$t->mn} a {$t->mx} con {$t->n} comprobantes → $huecos hueco(s). "
            . 'La SET no admite saltos en la numeración', 'ALTO');
    }
    $put('timbrado_' . $t->id_timbrado, "{$t->mn}..{$t->mx} n={$t->n}");
}

// Cobros sobre comprobantes anulados
$cobAnul = (int) DB::scalar('SELECT COUNT(*) FROM cobro c JOIN factura f ON f.id_factura=c.id_factura
                              WHERE c.id_estado_cobro=1 AND f.id_estado_factura=2');
$put('cobros_sobre_anuladas', $cobAnul);
if ($cobAnul) {
    $mal('AUD_COBRO_EN_ANULADA', "$cobAnul cobro(s) activo(s) sobre comprobantes anulados", 'CRITICO');
}

// Una cita con más de un comprobante vigente
$dobles = (int) DB::scalar('SELECT COUNT(*) FROM (SELECT id_cita FROM factura WHERE id_estado_factura=1 AND id_cita IS NOT NULL
                             GROUP BY id_cita HAVING COUNT(*)>1) x');
$put('citas_con_dos_comprobantes', $dobles);
if ($dobles) {
    $mal('AUD_DOBLE_COMPROBANTE', "$dobles cita(s) tienen más de un comprobante vigente", 'CRITICO');
}

// Comprobantes de citas NO atendidas
$noAtend = (int) DB::scalar('SELECT COUNT(*) FROM factura f JOIN cita c ON c.id_cita=f.id_cita
                              WHERE f.id_estado_factura=1 AND c.id_estado_cita <> 4');
$put('facturas_de_citas_no_atendidas', $noAtend);
if ($noAtend) {
    $mal('AUD_FACTURA_CITA_NO_ATENDIDA', "$noAtend comprobante(s) vigente(s) de citas que no están Atendidas", 'ALTO');
}

// =========================================================================
// 3. CAJA — arqueo recalculado
// =========================================================================
$difCaja = 0;
foreach (DB::select('SELECT id_caja, monto_inicial, id_estado_caja, fn_caja_saldo(id_caja) saldo FROM caja') as $c) {
    $id = (int) $c->id_caja;
    $ef = (float) DB::scalar("SELECT COALESCE(SUM(co.monto),0) FROM cobro co JOIN metodo_pago mp ON mp.id_metodo_pago=co.id_metodo_pago
                               WHERE co.id_caja=? AND co.id_estado_cobro=1 AND mp.tipo='EFECTIVO'", [$id]);
    $ing = (float) DB::scalar("SELECT COALESCE(SUM(monto),0) FROM movimiento_caja WHERE id_caja=? AND tipo='INGRESO'", [$id]);
    $egr = (float) DB::scalar("SELECT COALESCE(SUM(monto),0) FROM movimiento_caja WHERE id_caja=? AND tipo='EGRESO'", [$id]);
    $pp = (float) DB::scalar("SELECT COALESCE(SUM(d.monto_aplicado),0)
                                FROM pago_proveedor pp JOIN detalle_pago_proveedor d ON d.id_pago_proveedor=pp.id_pago_proveedor
                                JOIN metodo_pago mp ON mp.id_metodo_pago=pp.id_metodo_pago
                               WHERE pp.id_caja=? AND pp.id_estado_pago_proveedor=1 AND mp.tipo='EFECTIVO'", [$id]);
    // **Desde la 7.22.0 la liquidación al personal también sale del cajón**
    // (CJ-02). Se recalcula desde el detalle, no desde `pago_personal.monto`,
    // para no darle la razón al sistema con su propio número.
    $pe = (float) DB::scalar("SELECT COALESCE(SUM(d.monto),0)
                                FROM pago_personal pg JOIN detalle_pago_personal d ON d.id_pago_personal=pg.id_pago_personal
                                JOIN metodo_pago mp ON mp.id_metodo_pago=pg.id_metodo_pago
                               WHERE pg.id_caja=? AND pg.id_estado_pago=1 AND mp.tipo='EFECTIVO'", [$id]);
    $mio = round((float) $c->monto_inicial + $ef + $ing - $egr - $pp - $pe, 2);
    if (abs($mio - (float) $c->saldo) > 0.01) {
        $difCaja++;
        $mal('AUD_CAJA_DIFIERE', "Caja #$id: recalculada $mio, el sistema dice {$c->saldo}", 'CRITICO');
    }
    if ((float) $c->saldo < -0.01) {
        $mal('AUD_CAJA_NEGATIVA', "Caja #$id quedó en {$c->saldo}", 'CRITICO');
    }
}
$put('cajas', (int) DB::scalar('SELECT COUNT(*) FROM caja'));
$put('cajas_diferencia', $difCaja);
$put('cajas_abiertas_ahora', (int) DB::scalar('SELECT COUNT(*) FROM caja WHERE id_estado_caja=1'));
// **Una caja abierta por vez y POR SUCURSAL.** Dos sedes con su cajón abierto
// al mismo tiempo es lo correcto desde la 7.31.0 — son cajones distintos. Lo
// que no puede pasar es que un mismo local tenga dos.
$solapadas = (int) DB::scalar('SELECT COUNT(*) FROM caja a JOIN caja b ON b.id_caja > a.id_caja
                                WHERE a.id_sucursal = b.id_sucursal
                                  AND a.fecha_apertura < COALESCE(b.fecha_cierre, NOW())
                                  AND b.fecha_apertura < COALESCE(a.fecha_cierre, NOW())');
$put('cajas_solapadas', $solapadas);
if ($solapadas) {
    $mal('AUD_CAJAS_SOLAPADAS',
        "$solapadas par(es) de cajas del MISMO local estuvieron abiertas a la vez", 'ALTO');
}

// Cobros y pagos que quedaron FUERA de toda caja
$sinCaja = (int) DB::scalar('SELECT COUNT(*) FROM cobro WHERE id_caja IS NULL AND id_estado_cobro=1');
$put('cobros_sin_caja', $sinCaja);
if ($sinCaja) {
    $mal('AUD_COBRO_SIN_CAJA', "$sinCaja cobro(s) activo(s) sin caja: quedan fuera de todo arqueo", 'ALTO');
}

// Pagos al personal: ¿tocan la caja?
$pagoPers = (float) DB::scalar('SELECT COALESCE(SUM(monto),0) FROM detalle_pago_personal d
                                 JOIN pago_personal p ON p.id_pago_personal=d.id_pago_personal
                                WHERE p.id_estado_pago <> 4');
$put('pagado_al_personal', $pagoPers);
$put('movimiento_caja_filas', (int) DB::scalar('SELECT COUNT(*) FROM movimiento_caja'));

// Desde la 7.22.0 la liquidación guarda caja y medio de pago. Lo que se
// comprueba ahora es que no haya ninguna SIN esos datos, que es lo que dejaba
// la plata fuera del arqueo.
$persSinCaja = (int) DB::scalar('SELECT COUNT(*) FROM pago_personal WHERE id_estado_pago = 1 AND (id_caja IS NULL OR id_metodo_pago IS NULL)');
$put('liquidaciones_sin_caja_ni_medio', $persSinCaja);
if ($persSinCaja > 0) {
    $mal('AUD_PAGO_PERSONAL_FUERA_DE_CAJA',
        "$persSinCaja liquidación(es) vigentes sin caja o sin medio de pago: esa plata sale del salón y no entra al arqueo", 'ALTO');
}

// Y que lo pagado en efectivo se refleje realmente como egreso del cajón
$persEfectivo = (float) DB::scalar("SELECT COALESCE(SUM(d.monto),0)
                                      FROM pago_personal pg JOIN detalle_pago_personal d ON d.id_pago_personal=pg.id_pago_personal
                                      JOIN metodo_pago mp ON mp.id_metodo_pago=pg.id_metodo_pago
                                     WHERE pg.id_estado_pago=1 AND mp.tipo='EFECTIVO'");
$put('liquidado_en_efectivo', $persEfectivo);

// =========================================================================
// 4. AGENDA — solapes y horarios fuera de turno
// =========================================================================
$citas = DB::select('SELECT c.id_cita, c.id_usuario, c.fecha_hora, c.id_estado_cita, e.bloquea_agenda,
                            fn_cita_duracion_de(c.id_cita, c.id_usuario) dur
                       FROM cita c JOIN estado_cita e ON e.id_estado_cita=c.id_estado_cita
                      ORDER BY c.id_usuario, c.fecha_hora');
$porProf = [];
foreach ($citas as $c) {
    if (! (int) $c->bloquea_agenda || (int) $c->dur <= 0) continue;
    $porProf[(int) $c->id_usuario][] = [strtotime((string) $c->fecha_hora),
                                        strtotime((string) $c->fecha_hora) + (int) $c->dur * 60, (int) $c->id_cita];
}
$solapes = [];
foreach ($porProf as $p => $lista) {
    for ($i = 1; $i < count($lista); $i++) {
        if ($lista[$i][0] < $lista[$i - 1][1]) {
            $solapes[] = "prof $p: citas #{$lista[$i-1][2]} y #{$lista[$i][2]}";
        }
    }
}
$put('solapes_agenda', count($solapes));
if ($solapes) {
    $mal('AUD_SOLAPE_AGENDA', count($solapes) . ' solape(s) de agenda: ' . implode(' | ', array_slice($solapes, 0, 6)), 'CRITICO');
}

// Citas fuera del turno de quien las tiene (sólo para quien SÍ tiene turno)
$fuera = DB::select("SELECT c.id_cita, c.id_usuario, c.fecha_hora
                       FROM cita c JOIN estado_cita e ON e.id_estado_cita=c.id_estado_cita
                      WHERE e.bloquea_agenda=1
                        AND EXISTS (SELECT 1 FROM usuario_turno ut JOIN turno_laboral t ON t.id_turno=ut.id_turno AND t.activo=1
                                     WHERE ut.id_usuario=c.id_usuario)
                        AND NOT EXISTS (SELECT 1 FROM usuario_turno ut
                                          JOIN turno_laboral t ON t.id_turno=ut.id_turno AND t.activo=1
                                          JOIN turno_dia td ON td.id_turno=t.id_turno
                                         WHERE ut.id_usuario=c.id_usuario
                                           AND td.dia_semana = WEEKDAY(c.fecha_hora)+1
                                           AND TIME(c.fecha_hora) >= t.hora_inicio
                                           AND TIME(c.fecha_hora) < t.hora_fin)");
$put('citas_fuera_de_turno', count($fuera));
if ($fuera) {
    $mal('AUD_CITA_FUERA_DE_TURNO', count($fuera) . ' cita(s) quedaron fuera del turno de su profesional', 'ALTO');
}

// Citas asignadas a personal SIN turno (Administrador, recepción): el salón
// cerrado igual vende horario
$sinTurno = DB::select("SELECT c.id_cita, c.fecha_hora, u.username, r.nombre rol,
                               DAYOFWEEK(c.fecha_hora) dw, c.id_estado_cita
                          FROM cita c JOIN usuario u ON u.id_usuario=c.id_usuario
                          JOIN rol r ON r.id_rol=u.id_rol
                         WHERE NOT EXISTS (SELECT 1 FROM usuario_turno ut WHERE ut.id_usuario=c.id_usuario)");
$domingos = 0; $noProf = 0;
foreach ($sinTurno as $s) {
    if ((int) $s->dw === 1) $domingos++;
    if ($s->rol !== 'Profesional') $noProf++;
}
$put('citas_a_personal_sin_turno', count($sinTurno));
$put('citas_en_domingo', $domingos);
$put('citas_a_no_profesionales', $noProf);
if ($sinTurno) {
    $mal('AUD_AGENDA_SIN_TURNO',
        count($sinTurno) . ' cita(s) se vendieron a personal SIN turno cargado (Administrador / Asistente administrativo). '
        . "De ellas $domingos cayeron en domingo, con el salón cerrado. Ninguna de esas personas atiende clientes", 'ALTO');
}

// Citas Atendidas sin ningún servicio realizado
$sinSR = (int) DB::scalar('SELECT COUNT(*) FROM cita c WHERE c.id_estado_cita=4
                            AND NOT EXISTS (SELECT 1 FROM servicio_realizado sr WHERE sr.id_cita=c.id_cita)');
$put('atendidas_sin_servicio', $sinSR);
if ($sinSR) {
    $mal('AUD_ATENDIDA_SIN_SERVICIO', "$sinSR cita(s) Atendidas sin ningún servicio realizado", 'ALTO');
}

// El profesional que figura en servicio_realizado, ¿es el del reparto?
$repartoMal = (int) DB::scalar('SELECT COUNT(*) FROM servicio_realizado sr
                                 JOIN cita_servicio cs ON cs.id_cita=sr.id_cita AND cs.id_servicio=sr.id_servicio
                                WHERE cs.id_usuario IS NOT NULL AND cs.id_usuario <> sr.id_usuario');
$put('servicios_con_profesional_equivocado', $repartoMal);
if ($repartoMal) {
    $mal('AUD_REPARTO_IGNORADO',
        "$repartoMal servicio(s) realizados quedaron a nombre del profesional principal de la cita y no de quien "
        . 'figura en el reparto (cita_servicio.id_usuario). La comisión y el informe del equipo se le cargan a la persona equivocada', 'ALTO');
}

// =========================================================================
// 5. HUÉRFANOS Y RELACIONES
// =========================================================================
$huerfanos = [
    'cita sin cliente' => 'SELECT COUNT(*) FROM cita c LEFT JOIN cliente x ON x.id_cliente=c.id_cliente WHERE x.id_cliente IS NULL',
    'cita sin usuario' => 'SELECT COUNT(*) FROM cita c LEFT JOIN usuario x ON x.id_usuario=c.id_usuario WHERE x.id_usuario IS NULL',
    'cita_servicio sin cita' => 'SELECT COUNT(*) FROM cita_servicio cs LEFT JOIN cita x ON x.id_cita=cs.id_cita WHERE x.id_cita IS NULL',
    'servicio_realizado sin cita' => 'SELECT COUNT(*) FROM servicio_realizado s LEFT JOIN cita x ON x.id_cita=s.id_cita WHERE x.id_cita IS NULL',
    'producto_utilizado sin sr' => 'SELECT COUNT(*) FROM producto_utilizado p LEFT JOIN servicio_realizado x ON x.id_servicio_realizado=p.id_servicio_realizado WHERE x.id_servicio_realizado IS NULL',
    'detalle_factura sin factura' => 'SELECT COUNT(*) FROM detalle_factura d LEFT JOIN factura x ON x.id_factura=d.id_factura WHERE x.id_factura IS NULL',
    'cobro sin destino' => 'SELECT COUNT(*) FROM cobro WHERE id_factura IS NULL AND id_cita IS NULL',
    'cobro con dos destinos' => 'SELECT COUNT(*) FROM cobro WHERE id_factura IS NOT NULL AND id_cita IS NOT NULL',
    'factura sin timbrado' => 'SELECT COUNT(*) FROM factura f LEFT JOIN timbrado t ON t.id_timbrado=f.id_timbrado WHERE t.id_timbrado IS NULL',
    'nota de credito sin origen' => 'SELECT COUNT(*) FROM factura WHERE id_tipo_comprobante=5 AND id_factura_origen IS NULL',
    'movimiento sin producto' => 'SELECT COUNT(*) FROM movimiento_inventario m LEFT JOIN producto p ON p.id_producto=m.id_producto WHERE p.id_producto IS NULL',
    'auditoria sin usuario' => 'SELECT COUNT(*) FROM auditoria a LEFT JOIN usuario u ON u.id_usuario=a.id_usuario WHERE u.id_usuario IS NULL',
    'cliente sin persona' => 'SELECT COUNT(*) FROM cliente c LEFT JOIN persona p ON p.id_persona=c.id_persona WHERE p.id_persona IS NULL',
    'persona duplicada por cedula' => 'SELECT COALESCE(SUM(n-1),0) FROM (SELECT COUNT(*) n FROM persona WHERE cedula IS NOT NULL AND cedula<>"" GROUP BY cedula HAVING COUNT(*)>1) x',
    'clientes duplicados por email' => 'SELECT COALESCE(SUM(n-1),0) FROM (SELECT COUNT(*) n FROM cliente c JOIN persona p ON p.id_persona=c.id_persona WHERE p.email IS NOT NULL AND p.email<>"" GROUP BY p.email HAVING COUNT(*)>1) x',
];
foreach ($huerfanos as $q => $sql) {
    $n = (int) DB::scalar($sql);
    $put('huerfano_' . str_replace(' ', '_', $q), $n);
    if ($n > 0) {
        $mal('AUD_HUERFANO', "$q: $n fila(s)", 'ALTO');
    }
}

// =========================================================================
// 6. COHERENCIA TEMPORAL
// =========================================================================
$temporales = [
    'factura anterior a su cita' => 'SELECT COUNT(*) FROM factura f JOIN cita c ON c.id_cita=f.id_cita WHERE f.fecha_emision < c.fecha_hora',
    'cobro anterior a su factura' => 'SELECT COUNT(*) FROM cobro co JOIN factura f ON f.id_factura=co.id_factura WHERE co.fecha < f.fecha_emision',
    'cobro fuera de la caja' => 'SELECT COUNT(*) FROM cobro co JOIN caja c ON c.id_caja=co.id_caja WHERE co.fecha < c.fecha_apertura OR (c.fecha_cierre IS NOT NULL AND co.fecha > c.fecha_cierre)',
    'caja cerrada antes de abrir' => 'SELECT COUNT(*) FROM caja WHERE fecha_cierre IS NOT NULL AND fecha_cierre < fecha_apertura',
    'asistencia con salida antes de la entrada' => 'SELECT COUNT(*) FROM asistencia WHERE hora_salida IS NOT NULL AND hora_entrada IS NOT NULL AND hora_salida < hora_entrada',
    'asistencia de un dia futuro' => 'SELECT COUNT(*) FROM asistencia WHERE fecha > CURDATE()',
    'servicio realizado antes de la cita' => 'SELECT COUNT(*) FROM servicio_realizado sr JOIN cita c ON c.id_cita=sr.id_cita WHERE sr.fecha_hora < c.fecha_hora',
    'auditoria en el futuro' => 'SELECT COUNT(*) FROM auditoria WHERE fecha_hora > NOW()',
    'movimiento de inventario en el futuro' => 'SELECT COUNT(*) FROM movimiento_inventario WHERE fecha > NOW()',
];
foreach ($temporales as $q => $sql) {
    $n = (int) DB::scalar($sql);
    $put('tiempo_' . str_replace(' ', '_', $q), $n);
    if ($n > 0) {
        $mal('AUD_TEMPORAL', "$q: $n fila(s)", $n > 5 ? 'ALTO' : 'MEDIO');
    }
}

// =========================================================================
// 7. AUDITORÍA — ¿queda registro de lo que importa?
// =========================================================================
$emitidas = (int) DB::scalar('SELECT COUNT(*) FROM factura');
$audEmision = (int) DB::scalar("SELECT COUNT(DISTINCT id_registro) FROM auditoria WHERE accion IN ('EMISION','NOTA_CREDITO') AND tabla_afectada='factura'");
$put('facturas_emitidas', $emitidas);
$put('auditoria_emision', $audEmision);
if ($audEmision < $emitidas) {
    $mal('AUD_SIN_RASTRO_EMISION', ($emitidas - $audEmision) . ' comprobante(s) sin fila de auditoría de emisión', 'MEDIO');
}
$anuladas = (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_estado_factura=2');
$audAnul = (int) DB::scalar("SELECT COUNT(DISTINCT id_registro) FROM auditoria WHERE tabla_afectada='factura' AND accion='ANULACION'");
$put('facturas_anuladas', $anuladas);
$put('auditoria_anulacion_factura', $audAnul);

$cobrosAnul = (int) DB::scalar('SELECT COUNT(*) FROM cobro WHERE id_estado_cobro=3');
$audCob = (int) DB::scalar("SELECT COUNT(*) FROM auditoria WHERE tabla_afectada='cobro' AND accion='ANULACION'");
$put('cobros_anulados', $cobrosAnul);
$put('auditoria_anulacion_cobro', $audCob);
if ($audCob > $cobrosAnul) {
    $mal('AUD_AUDITORIA_DUPLICADA', "Hay $audCob filas de auditoría de anulación de cobro para $cobrosAnul cobros anulados", 'BAJO');
}
$put('auditoria_total', (int) DB::scalar('SELECT COUNT(*) FROM auditoria'));
$put('auditoria_acciones', implode(', ', array_map(fn ($r) => $r->accion . ':' . $r->n,
    DB::select('SELECT accion, COUNT(*) n FROM auditoria GROUP BY accion ORDER BY n DESC'))));

// Cambios de estado de cita sin rastro
$audCitas = (int) DB::scalar("SELECT COUNT(*) FROM auditoria WHERE tabla_afectada='cita'");
$put('auditoria_citas', $audCitas);

// =========================================================================
// 8. NOTIFICACIONES
// =========================================================================
$put('notificaciones', (int) DB::scalar('SELECT COUNT(*) FROM notificacion'));
$put('notif_por_estado', implode(', ', array_map(fn ($r) => $r->estado . ':' . $r->n,
    DB::select('SELECT estado, COUNT(*) n FROM notificacion GROUP BY estado'))));
$pend = (int) DB::scalar("SELECT COUNT(*) FROM notificacion WHERE estado='PENDIENTE' AND fecha_generacion < DATE_SUB(NOW(), INTERVAL 2 DAY)");
$put('notif_pendientes_viejas', $pend);
if ($pend > 0) {
    $mal('AUD_NOTIF_ESTANCADAS', "$pend notificación(es) llevan más de dos días en PENDIENTE", 'MEDIO');
}

// Recordatorio enviado DESPUÉS de que la cita se cancelara
$recTardio = (int) DB::scalar(
    "SELECT COUNT(*) FROM notificacion n
       JOIN cita c ON c.id_cita = n.id_cita
       JOIN auditoria a ON a.tabla_afectada='cita' AND a.id_registro=n.id_cita AND a.accion='CANCELACION'
      WHERE n.id_tipo_notificacion = 1 AND n.estado='ENVIADA'
        AND c.id_estado_cita = 3 AND n.fecha_envio > a.fecha_hora"
);
$put('recordatorios_enviados_tras_cancelar', $recTardio);
if ($recTardio > 0) {
    $mal('AUD_RECORDATORIO_DE_CITA_CANCELADA',
        "$recTardio recordatorio(s) se enviaron DESPUÉS de que la cita se cancelara: Notificaciones::despachar() "
        . 'no mira el estado de la cita, así que la clienta recibe «te recordamos tu cita» de una cita que ya no existe', 'ALTO');
}

// Cita reprogramada cuyo único recordatorio quedó con la fecha vieja
$recViejo = (int) DB::scalar(
    "SELECT COUNT(*) FROM cita c
      WHERE c.id_estado_cita = 2
        AND EXISTS (SELECT 1 FROM notificacion n WHERE n.id_cita=c.id_cita AND n.id_tipo_notificacion=1
                     AND n.mensaje NOT LIKE CONCAT('%', DATE_FORMAT(c.fecha_hora,'%d/%m/%Y a las %H:%i'), '%'))"
);
$put('recordatorios_con_fecha_vieja', $recViejo);
if ($recViejo > 0) {
    $mal('AUD_RECORDATORIO_FECHA_VIEJA',
        "$recViejo cita(s) reprogramada(s) conservan un recordatorio con la fecha ANTERIOR. "
        . 'generarRecordatorios() saltea la cita si ya tiene un aviso tipo 1, así que al reprogramar '
        . 'no se genera uno nuevo ni se corrige el viejo', 'ALTO');
}

// Citas con recordatorio pendiente cuya hora ya pasó
$recTarde = (int) DB::scalar("SELECT COUNT(*) FROM notificacion n JOIN cita c ON c.id_cita=n.id_cita
                               WHERE n.id_tipo_notificacion=1 AND n.estado='PENDIENTE' AND c.fecha_hora < NOW()");
$put('recordatorios_pendientes_de_citas_pasadas', $recTarde);

// =========================================================================
// 9. FIDELIZACIÓN
// =========================================================================
$put('puntos_total', (int) DB::scalar('SELECT COALESCE(SUM(puntos),0) FROM movimiento_punto'));
$negPuntos = (int) DB::scalar('SELECT COUNT(*) FROM (SELECT id_cliente, SUM(puntos) s FROM movimiento_punto GROUP BY id_cliente HAVING s < 0) x');
$put('clientes_con_puntos_negativos', $negPuntos);
if ($negPuntos) {
    $mal('AUD_PUNTOS_NEGATIVOS', "$negPuntos cliente(s) con saldo de puntos negativo", 'MEDIO');
}
// Puntos de comprobantes anulados que no se revirtieron
$puntosAnul = (int) DB::scalar('SELECT COALESCE(SUM(mp.puntos),0) FROM movimiento_punto mp
                                 JOIN factura f ON f.id_factura=mp.id_factura WHERE f.id_estado_factura=2');
$put('puntos_de_facturas_anuladas', $puntosAnul);
if ($puntosAnul > 0) {
    $mal('AUD_PUNTOS_NO_REVERTIDOS', "Quedaron $puntosAnul punto(s) sin revertir de comprobantes anulados", 'MEDIO');
}

// =========================================================================
// 10. REPORTES contra los datos crudos
// =========================================================================
$d0 = (string) DB::scalar('SELECT DATE(MIN(fecha_hora)) FROM cita');
$d1 = (string) DB::scalar('SELECT DATE(MAX(fecha_hora)) FROM cita');

$n = new Nav();
if ($n->entrar('admin', 'admin123')) {
    $n->get('/reportes', ['desde' => $d0, 'hasta' => $d1]);
    $html = $n->body;

    $leer = function (string $etiqueta) use ($html): ?string {
        if (preg_match('#<div class="lbl">' . preg_quote($etiqueta, '#') . '</div><div class="val[^"]*">(.*?)</div>#s', $html, $m)) {
            return trim(strip_tags($m[1]));
        }
        return null;
    };

    $rCitas = $leer('Citas del período');
    $rAtend = $leer('Atendidas');
    $rIngr = $leer('Ingresos cobrados');

    $mCitas = (int) DB::scalar('SELECT COUNT(*) FROM cita WHERE DATE(fecha_hora) BETWEEN ? AND ?', [$d0, $d1]);
    $mAtend = (int) DB::scalar('SELECT COUNT(*) FROM cita WHERE id_estado_cita=4 AND DATE(fecha_hora) BETWEEN ? AND ?', [$d0, $d1]);
    $mIngr = (float) DB::scalar('SELECT COALESCE(SUM(monto),0) FROM cobro WHERE id_estado_cobro=1 AND DATE(fecha) BETWEEN ? AND ?', [$d0, $d1]);

    $put('reporte_citas', $rCitas . ' vs ' . $mCitas);
    $put('reporte_atendidas', $rAtend . ' vs ' . $mAtend);
    $put('reporte_ingresos', $rIngr . ' vs Gs. ' . number_format($mIngr, 0, ',', '.'));

    if ($rCitas !== null && (int) $rCitas !== $mCitas) {
        $mal('AUD_REPORTE_CITAS', "El informe dice $rCitas citas y en la base hay $mCitas", 'ALTO');
    }
    if ($rAtend !== null && (int) $rAtend !== $mAtend) {
        $mal('AUD_REPORTE_ATENDIDAS', "El informe dice $rAtend atendidas y en la base hay $mAtend", 'ALTO');
    }
    if ($rIngr !== null && $rIngr !== 'Gs. ' . number_format($mIngr, 0, ',', '.')) {
        $mal('AUD_REPORTE_INGRESOS', "El informe dice $rIngr y los cobros del período suman Gs. " . number_format($mIngr, 0, ',', '.'), 'ALTO');
    }

    // Las pantallas de lista, todas: una columna mal escrita revienta al dibujar
    foreach (['/panel', '/citas', '/citas/agenda', '/citas/nueva', '/citas/excepciones',
              '/citas/reasignar',
              '/clientes', '/clientes/lista', '/clientes/fidelizacion', '/clientes/valoraciones',
              '/clientes/canjes',
              '/servicios', '/servicios/lista', '/servicios/categorias', '/servicios/descuentos',
              '/inventario', '/inventario/productos', '/inventario/stock', '/inventario/movimientos',
              '/inventario/compras', '/inventario/proveedores', '/inventario/cargar-stock', '/inventario/compras/nueva',
              '/facturacion', '/facturacion/facturas', '/facturacion/cobros', '/facturacion/caja',
              '/facturacion/pagos', '/facturacion/proveedores', '/facturacion/timbrados', '/facturacion/emitir',
              '/seguridad', '/seguridad/usuarios', '/seguridad/turnos', '/seguridad/comisiones',
              '/seguridad/asistencia', '/seguridad/sucursales', '/seguridad/contacto', '/seguridad/roles',
              '/seguridad/auditoria', '/reportes', '/reportes/imprimir', '/cuenta'] as $uri) {
        $n->get($uri);
        if ($n->status !== 200) {
            $mal('AUD_PANTALLA_ROTA', "La pantalla $uri devolvió HTTP {$n->status}", $n->status >= 500 ? 'CRITICO' : 'ALTO');
        }
    }
    // Exportaciones
    foreach (['/clientes/lista?export=csv', '/clientes/lista?export=pdf', '/facturacion/facturas?export=csv',
              '/inventario/movimientos?export=csv', '/seguridad/auditoria?export=csv',
              '/facturacion/cobros?export=pdf'] as $uri) {
        $n->get($uri);
        if ($n->status !== 200) {
            $mal('AUD_EXPORT_ROTO', "La exportación $uri devolvió HTTP {$n->status}", 'ALTO');
        }
    }
    $n->salir();
}

// =========================================================================
// 11 bis. ¿Qué ve una profesional en el panel?
// =========================================================================
// **Los ingresos del panel son los de SU local, no los del salón** (7.36.4).
// La comprobación leía bien la pantalla pero comparaba contra el total del
// negocio, así que con casi toda la plata en una sola sede coincidían por
// casualidad y gritaba una fuga que no existía. La referencia correcta es la
// recaudación de la sucursal en la que esa persona está trabajando.
$sucProf = (int) (DB::scalar("SELECT COALESCE((SELECT us.id_sucursal FROM usuario_sucursal us JOIN usuario u USING(id_usuario) WHERE u.username='marta' ORDER BY us.id_sucursal LIMIT 1), (SELECT MIN(id_sucursal) FROM sucursal WHERE activo=1))") ?: 1);
$ingHoy = (float) DB::scalar('SELECT COALESCE(SUM(co.monto),0) FROM cobro co
                               LEFT JOIN caja k ON k.id_caja = co.id_caja
                               LEFT JOIN cita ci ON ci.id_cita = co.id_cita
                              WHERE DATE(co.fecha)=CURDATE() AND co.id_estado_cobro=1
                                AND COALESCE(k.id_sucursal, ci.id_sucursal) = ?', [$sucProf]);
$ingSalon = (float) DB::scalar('SELECT COALESCE(SUM(monto),0) FROM cobro WHERE DATE(fecha)=CURDATE() AND id_estado_cobro=1');
$citasHoy = (int) DB::scalar('SELECT COUNT(*) FROM cita WHERE DATE(fecha_hora)=CURDATE() AND id_estado_cita NOT IN (3,6)');
$pp = new Nav();
if ($pp->entrar('marta', 'profesional123')) {
    $pp->get('/panel');
    $html = $pp->body;
    $leer = function (string $et) use ($html): ?string {
        if (preg_match('#<div class="lbl">' . preg_quote($et, '#') . '</div>\s*<div class="val[^"]*">(.*?)</div>#s', $html, $m)) {
            return trim(strip_tags($m[1]));
        }
        return null;
    };
    // Los rótulos exactos salen de la vista; se busca el importe en oro
    $veIngresos = preg_match('#<div class="val oro">(Gs\.[^<]*)</div>#', $html, $m) ? trim($m[1]) : null;
    $put('panel_profesional_ingresos', $veIngresos);
    $put('panel_ingresos_reales', 'Gs. ' . number_format($ingHoy, 0, ',', '.'));
    if ($veIngresos !== null && $ingSalon > $ingHoy + 0.5
        && $veIngresos === 'Gs. ' . number_format($ingSalon, 0, ',', '.')) {
        $mal('AUD_PANEL_FUGA_INGRESOS',
            'Una Profesional (sin permiso de caja) ve en el panel los ingresos de TODO el salón: ' . $veIngresos
            . '. La corrección de la 7.13.1 tapó la barra de caja pero no las cuatro métricas de arriba, '
            . 'que se calculan siempre sin filtrar por rol', 'MEDIO');
    }
    // Y las otras tres métricas
    if (str_contains($html, '>' . $citasHoy . '<')) {
        $put('panel_profesional_citas_hoy', 'muestra el total del salón (' . $citasHoy . ')');
    }
    $pp->salir();
}

// =========================================================================
// 11 ter. Venta de productos: ¿existe el camino?
// =========================================================================
$ventas = (int) DB::scalar('SELECT COUNT(*) FROM detalle_factura WHERE id_producto IS NOT NULL');
$movVenta = (int) DB::scalar('SELECT COUNT(*) FROM movimiento_inventario WHERE id_tipo_movimiento = 7');
$put('renglones_de_producto_facturados', $ventas);
$put('movimientos_venta_de_producto', $movVenta);
if ($ventas === 0) {
    // Confirmación, no hallazgo nuevo: la venta de productos quedó FUERA DE
    // ALCANCE por decisión del usuario (7.23.1) y las cuatro piezas se dejaron
    // en el modelo a propósito. Se registra como BAJO para dejar constancia de
    // que el camino sigue sin pantalla, que es lo que el TCC documenta.
    $mal('AUD_SIN_VENTA_DE_PRODUCTOS',
        'En 60 días no se facturó ni un producto. Es lo esperado: la venta quedó fuera de alcance por decisión '
        . 'del usuario (7.23.1) y `producto.precio_venta`, el tipo de movimiento 7 y trg_detfactura_ai se dejaron '
        . 'en el modelo a propósito. Queda anotado para que el modelo no prometa lo que la pantalla no da', 'BAJO');
}

// =========================================================================
// 12. LIQUIDACIONES AL PERSONAL
// =========================================================================
$liq = DB::select('SELECT p.id_pago_personal, p.periodo, p.fecha, p.id_usuario,
                          COUNT(d.id_detalle_pago) n,
                          MIN(DATE(sr.fecha_hora)) desde, MAX(DATE(sr.fecha_hora)) hasta,
                          COALESCE(SUM(d.monto),0) monto
                     FROM pago_personal p
                     LEFT JOIN detalle_pago_personal d ON d.id_pago_personal=p.id_pago_personal
                     LEFT JOIN servicio_realizado sr ON sr.id_servicio_realizado=d.id_servicio_realizado
                    WHERE p.id_estado_pago <> 4
                    GROUP BY p.id_pago_personal, p.periodo, p.fecha, p.id_usuario');
$fueraDePeriodo = 0;
foreach ($liq as $l) {
    if ($l->periodo && $l->desde) {
        $mesPeriodo = substr((string) $l->periodo, 3, 4) . '-' . substr((string) $l->periodo, 0, 2);
        if (substr((string) $l->desde, 0, 7) !== $mesPeriodo) {
            $fueraDePeriodo++;
        }
    }
}
$put('liquidaciones_activas', count($liq));
$put('liquidaciones_con_servicios_de_otro_mes', $fueraDePeriodo);
if ($fueraDePeriodo > 0) {
    $mal('AUD_LIQUIDACION_SIN_PERIODO',
        "$fueraDePeriodo liquidación(es) incluyen servicios de un mes distinto al de su período. "
        . '`sp_registrar_pago_personal` NO filtra por fecha: el período es sólo una etiqueta y '
        . 'liquida todo lo que esté pendiente desde siempre', 'ALTO');
}
$liqCero = (int) DB::scalar('SELECT COUNT(*) FROM (SELECT p.id_pago_personal FROM pago_personal p
                              JOIN detalle_pago_personal d ON d.id_pago_personal=p.id_pago_personal
                              GROUP BY p.id_pago_personal HAVING SUM(d.monto)=0) x');
$put('liquidaciones_por_cero', $liqCero);

// =========================================================================
// 11. VOLUMEN FINAL
// =========================================================================
foreach ([
    'clientes' => 'SELECT COUNT(*) FROM cliente',
    'clientes_activos' => 'SELECT COUNT(*) FROM cliente WHERE activo=1',
    'clientes_con_cuenta' => 'SELECT COUNT(*) FROM cliente WHERE id_usuario IS NOT NULL',
    'usuarios' => 'SELECT COUNT(*) FROM usuario',
    'citas' => 'SELECT COUNT(*) FROM cita',
    'citas_atendidas' => 'SELECT COUNT(*) FROM cita WHERE id_estado_cita=4',
    'citas_canceladas' => 'SELECT COUNT(*) FROM cita WHERE id_estado_cita=3',
    'citas_ausentes' => 'SELECT COUNT(*) FROM cita WHERE id_estado_cita=6',
    'citas_atrasadas' => 'SELECT COUNT(*) FROM cita WHERE id_estado_cita=7',
    'citas_programadas' => 'SELECT COUNT(*) FROM cita WHERE id_estado_cita IN (1,2)',
    'servicios_realizados' => 'SELECT COUNT(*) FROM servicio_realizado',
    'productos_usados' => 'SELECT COUNT(*) FROM producto_utilizado',
    'comprobantes' => 'SELECT COUNT(*) FROM factura',
    'comprobantes_factura' => 'SELECT COUNT(*) FROM factura WHERE id_tipo_comprobante=1',
    'comprobantes_pago' => 'SELECT COUNT(*) FROM factura WHERE id_tipo_comprobante=8',
    'notas_credito' => 'SELECT COUNT(*) FROM factura WHERE id_tipo_comprobante=5',
    'facturado' => 'SELECT COALESCE(SUM(fn_factura_total(id_factura)),0) FROM factura WHERE id_estado_factura=1 AND id_tipo_comprobante<>5',
    'cobrado' => 'SELECT COALESCE(SUM(monto),0) FROM cobro WHERE id_estado_cobro=1',
    'cobrado_efectivo' => "SELECT COALESCE(SUM(co.monto),0) FROM cobro co JOIN metodo_pago mp ON mp.id_metodo_pago=co.id_metodo_pago WHERE co.id_estado_cobro=1 AND mp.tipo='EFECTIVO'",
    'senas' => 'SELECT COUNT(*) FROM cobro WHERE id_cita IS NOT NULL AND id_estado_cobro=1',
    'compras' => 'SELECT COUNT(*) FROM compra',
    'comprado' => 'SELECT COALESCE(SUM(fn_compra_total(id_compra)),0) FROM compra WHERE id_estado_compra=2',
    'pagado_a_proveedores' => 'SELECT COALESCE(SUM(d.monto_aplicado),0) FROM detalle_pago_proveedor d JOIN pago_proveedor p ON p.id_pago_proveedor=d.id_pago_proveedor WHERE p.id_estado_pago_proveedor=1',
    'deuda_proveedores' => 'SELECT COALESCE(SUM(fn_proveedor_saldo(id_proveedor)),0) FROM proveedor',
    'movimientos_inventario' => 'SELECT COUNT(*) FROM movimiento_inventario',
    'liquidaciones' => 'SELECT COUNT(*) FROM pago_personal',
    'calificaciones' => 'SELECT COUNT(*) FROM calificacion',
    'solicitudes_sena' => 'SELECT COUNT(*) FROM sena_solicitud',
    'pedidos_portal' => 'SELECT COUNT(*) FROM cita_pedido',
    'asistencias' => 'SELECT COUNT(*) FROM asistencia',
] as $k => $sql) {
    $put($k, DB::scalar($sql));
}

// =========================================================================
// 13. FIDELIZACIÓN Y CANJES — lo que entró entre la 7.25.0 y la 7.27.0
// =========================================================================
$put('canjes_catalogo', (int) DB::scalar('SELECT COUNT(*) FROM servicio_canjeable WHERE activo=1'));
$put('canjes_hechos', (int) DB::scalar('SELECT COUNT(*) FROM canje'));
$put('canjes_usados', (int) DB::scalar('SELECT COUNT(*) FROM canje WHERE id_cita IS NOT NULL'));
$put('canjes_vencidos', (int) DB::scalar('SELECT COUNT(*) FROM canje WHERE id_cita IS NULL AND vence_en < CURDATE()'));
$put('relacion_puntos_final', (int) DB::scalar('SELECT puntos_cada_gs FROM configuracion WHERE id_configuracion=1'));
$put('puntos_max_cliente', (int) DB::scalar('SELECT COALESCE(MAX(fn_cliente_puntos(id_cliente)),0) FROM cliente'));
$canjeBarato = (int) DB::scalar('SELECT COALESCE(MIN(puntos),0) FROM servicio_canjeable WHERE activo=1');
$put('canje_mas_barato', $canjeBarato);

// ¿El programa es alcanzable? Si en 60 días nadie llegó ni al canje más barato,
// el catálogo está publicado y es decorativo.
$maxPts = (int) DB::scalar('SELECT COALESCE(MAX(fn_cliente_puntos(id_cliente)),0) FROM cliente');
$conCanje = (int) DB::scalar('SELECT COUNT(DISTINCT id_cliente) FROM canje');
if ($canjeBarato > 0 && $maxPts < $canjeBarato && $conCanje === 0) {
    $mal('AUD_FIDELIZACION_INALCANZABLE',
        "En 60 días la clienta que más puntos juntó llegó a $maxPts y el canje más barato cuesta $canjeBarato. "
        . 'El catálogo de canjes está publicado y ninguna clienta puede usarlo: el portal le muestra premios '
        . 'que no puede alcanzar', 'MEDIO');
}

// Un canje no puede estar atado a una cita cancelada
$canjePegado = (int) DB::scalar('SELECT COUNT(*) FROM canje cj JOIN cita c ON c.id_cita = cj.id_cita
                                  WHERE c.id_estado_cita IN (3,6)');
$put('canjes_atados_a_cita_muerta', $canjePegado);
if ($canjePegado > 0) {
    $mal('AUD_CANJE_EN_CITA_MUERTA',
        "$canjePegado canje(s) siguen atados a una cita cancelada o ausente: la clienta pagó puntos y perdió el servicio", 'ALTO');
}

// Un canje usado tiene que aparecer a CERO en el comprobante de esa cita
$canjeCobrado = (int) DB::scalar(
    'SELECT COUNT(*) FROM canje cj
       JOIN factura f ON f.id_cita = cj.id_cita AND f.id_estado_factura = 1
       JOIN detalle_factura df ON df.id_factura = f.id_factura AND df.id_servicio = cj.id_servicio
      WHERE df.precio_unitario > 0.01'
);
$put('canjes_cobrados_igual', $canjeCobrado);
if ($canjeCobrado > 0) {
    $mal('AUD_CANJE_COBRADO',
        "$canjeCobrado servicio(s) canjeado(s) salieron cobrados en el comprobante: se le descontaron los puntos "
        . 'a la clienta Y se le cobró el servicio', 'CRITICO');
}

// Los puntos gastados en canjes tienen que estar como movimiento negativo
$puntosCanje = (int) DB::scalar('SELECT COALESCE(SUM(puntos),0) FROM canje');
$puntosGastados = (int) DB::scalar("SELECT COALESCE(-SUM(puntos),0) FROM movimiento_punto WHERE tipo='CANJE'");
$put('puntos_en_canjes', $puntosCanje);
$put('puntos_movimiento_canje', $puntosGastados);
if ($puntosCanje !== $puntosGastados) {
    $mal('AUD_CANJE_PUNTOS_DESCUADRE',
        "Los canjes suman $puntosCanje puntos y los movimientos de tipo CANJE suman $puntosGastados", 'ALTO');
}

// =========================================================================
// 14. REGRESIÓN — los 18 hallazgos del informe de 90 días siguen cerrados
// =========================================================================
$regresion = [
    'AG-01 agenda vende a quien no atiende' => (int) DB::scalar(
        'SELECT COUNT(*) FROM cita c WHERE NOT EXISTS (SELECT 1 FROM usuario_turno ut WHERE ut.id_usuario=c.id_usuario)'),
    'FA-01 factura con saldo negativo' => (int) DB::scalar(
        'SELECT COUNT(*) FROM factura f JOIN tipo_comprobante tc USING(id_tipo_comprobante)
          WHERE f.id_estado_factura=1 AND tc.signo=1 AND fn_factura_saldo(f.id_factura) < -0.01'),
    'IN-01 stock negativo' => (int) DB::scalar('SELECT COUNT(*) FROM producto WHERE fn_producto_stock(id_producto, 1) < -0.0001'),
    // Por SUCURSAL: N locales con N cajas abiertas es lo correcto.
    'CJ-01 dos cajas abiertas' => (int) DB::scalar(
        'SELECT COALESCE(SUM(n - 1), 0) FROM (SELECT id_sucursal, COUNT(*) n FROM caja
           WHERE id_estado_caja = 1 GROUP BY id_sucursal HAVING COUNT(*) > 1) x'),
    'AG-02 comision a quien no trabajo' => (int) DB::scalar(
        'SELECT COUNT(*) FROM servicio_realizado sr JOIN cita_servicio cs ON cs.id_cita=sr.id_cita AND cs.id_servicio=sr.id_servicio
          WHERE cs.id_usuario IS NOT NULL AND cs.id_usuario <> sr.id_usuario'),
    'FA-03 sena mayor a la cita' => (int) DB::scalar(
        'SELECT COUNT(*) FROM (SELECT c.id_cita, SUM(co.monto) s,
                (SELECT COALESCE(SUM(s2.precio),0) FROM cita_servicio cs JOIN servicio s2 ON s2.id_servicio=cs.id_servicio WHERE cs.id_cita=c.id_cita) v
           FROM cita c JOIN cobro co ON co.id_cita=c.id_cita AND co.id_estado_cobro=1
          GROUP BY c.id_cita HAVING s > v + 0.01) x'),
    'CJ-02 liquidacion fuera del arqueo' => (int) DB::scalar(
        'SELECT COUNT(*) FROM pago_personal WHERE id_estado_pago=1 AND (id_caja IS NULL OR id_metodo_pago IS NULL)'),
    'FA-02 nota de credito sin egreso' => (int) DB::scalar(
        "SELECT COUNT(*) FROM factura n
          WHERE n.id_tipo_comprobante=5 AND n.id_estado_factura=1
            AND (SELECT COALESCE(SUM(co.monto),0) FROM cobro co JOIN metodo_pago mp USING(id_metodo_pago)
                  WHERE co.id_estado_cobro=1 AND mp.tipo='EFECTIVO'
                    AND (co.id_factura = n.id_factura_origen
                         OR co.id_cita = (SELECT id_cita FROM factura WHERE id_factura = n.id_factura_origen))) > 0
            AND NOT EXISTS (SELECT 1 FROM movimiento_caja mc WHERE mc.tipo='EGRESO' AND mc.concepto LIKE CONCAT('%', fn_factura_nro(n.id_factura), '%'))"),
    'SE-01 panel filtra por rol' => 0,   // se mide arriba, en 11 bis
];
foreach ($regresion as $k => $v) {
    $put('regresion_' . str_replace([' ', '-'], ['_', ''], $k), $v);
    if ($v > 0) {
        $mal('AUD_REGRESION', "Volvió a aparecer: $k → $v caso(s)", 'CRITICO');
    }
}



// =========================================================================
// 15. LO QUE TRAE ESTA VERSIÓN (7.30.0 → 7.36.1)
//
//     La simulación de 60 días corrió contra la 7.27.1 y no podía comprobar
//     nada de esto, así que son invariantes nuevos y sin cobertura previa.
// =========================================================================

// --- Aislamiento por sucursal (7.30.0–7.31.0) ---------------------------
$put('suc_citas_sin_sucursal', (int) DB::scalar('SELECT COUNT(*) FROM cita WHERE id_sucursal IS NULL OR id_sucursal = 0'));
$put('suc_cajas_sin_sucursal', (int) DB::scalar('SELECT COUNT(*) FROM caja WHERE id_sucursal IS NULL OR id_sucursal = 0'));
$put('suc_compras_sin_sucursal', (int) DB::scalar('SELECT COUNT(*) FROM compra WHERE id_sucursal IS NULL OR id_sucursal = 0'));
$put('suc_movinv_sin_sucursal', (int) DB::scalar('SELECT COUNT(*) FROM movimiento_inventario WHERE id_sucursal IS NULL OR id_sucursal = 0'));

foreach (['cita', 'caja', 'compra', 'movimiento_inventario'] as $t) {
    $n = (int) DB::scalar("SELECT COUNT(*) FROM $t x LEFT JOIN sucursal s ON s.id_sucursal = x.id_sucursal WHERE s.id_sucursal IS NULL");
    if ($n > 0) {
        $mal('AUD_SUCURSAL_HUERFANA', "$t tiene $n fila(s) apuntando a una sucursal que no existe", 'CRITICO');
    }
    $put('suc_huerfanas_' . $t, $n);
}

// La caja es del local: no puede haber dos abiertas en la MISMA sucursal.
$cajasDobles = (int) DB::scalar(
    'SELECT COALESCE(SUM(n - 1), 0) FROM (SELECT id_sucursal, COUNT(*) n FROM caja
       WHERE id_estado_caja = 1 GROUP BY id_sucursal HAVING COUNT(*) > 1) x');
$put('suc_cajas_abiertas_duplicadas', $cajasDobles);
if ($cajasDobles > 0) {
    $mal('AUD_CAJA_POR_SUCURSAL', "Hay $cajasDobles caja(s) abiertas de más en una misma sucursal", 'CRITICO');
}

// --- Catálogo de productos compartido (7.33.0) --------------------------
// El catálogo es único: el mismo producto no puede estar dos veces.
$dupProd = (int) DB::scalar(
    'SELECT COALESCE(SUM(n - 1), 0) FROM (SELECT nombre, COUNT(*) n FROM producto GROUP BY nombre HAVING COUNT(*) > 1) x');
$put('prod_nombres_duplicados', $dupProd);
if ($dupProd > 0) {
    $mal('AUD_PRODUCTO_DUPLICADO', "El catálogo tiene $dupProd producto(s) repetidos por nombre: la 7.33.0 lo prohíbe", 'ALTO');
}

// Un movimiento sólo puede existir donde el producto está habilitado.
$movSinHabilitar = (int) DB::scalar(
    'SELECT COUNT(*) FROM movimiento_inventario m
      WHERE NOT EXISTS (SELECT 1 FROM producto_sucursal ps
                         WHERE ps.id_producto = m.id_producto AND ps.id_sucursal = m.id_sucursal)');
$put('prod_mov_sin_habilitacion', $movSinHabilitar);
if ($movSinHabilitar > 0) {
    $mal('AUD_MOV_SIN_HABILITAR', "$movSinHabilitar movimiento(s) de un producto no habilitado en esa sucursal", 'ALTO');
}

// Stock por (producto, sucursal): recalculado a mano contra la función.
$difStock = 0;
foreach (DB::select('SELECT ps.id_producto, ps.id_sucursal, p.nombre FROM producto_sucursal ps
                       JOIN producto p ON p.id_producto = ps.id_producto') as $ps) {
    $teo = (float) DB::scalar(
        "SELECT COALESCE(SUM(CASE WHEN t.signo='E' THEN m.cantidad ELSE -m.cantidad END),0)
           FROM movimiento_inventario m JOIN tipo_movimiento_inventario t USING(id_tipo_movimiento)
          WHERE m.id_producto=? AND m.id_sucursal=?", [$ps->id_producto, $ps->id_sucursal]);
    $sis = (float) DB::scalar('SELECT fn_producto_stock(?,?)', [$ps->id_producto, $ps->id_sucursal]);
    if (abs($teo - $sis) > 0.0001) {
        $difStock++;
        $mal('AUD_STOCK_POR_SUCURSAL',
            "«{$ps->nombre}» en sucursal {$ps->id_sucursal}: recalculado $teo, el sistema dice $sis", 'CRITICO');
    }
    if ($sis < -0.0001) {
        $mal('AUD_STOCK_NEGATIVO_SUC', "«{$ps->nombre}» quedó en $sis en la sucursal {$ps->id_sucursal}", 'CRITICO');
    }
}
$put('prod_stock_discrepancias', $difStock);

// --- Servicios exclusivos en secuencia (7.36.0) -------------------------
// Dos profesionales con servicios exclusivos en la misma cita tienen que
// estar en turnos distintos; si comparten turno, se pisan sobre la clienta.
$exclParalelo = (int) DB::scalar(
    'SELECT COUNT(*) FROM (
       SELECT cs.id_cita, cs.orden
         FROM cita_servicio cs
         JOIN cita c ON c.id_cita = cs.id_cita
         JOIN servicio s ON s.id_servicio = cs.id_servicio AND s.requiere_exclusividad = 1
        GROUP BY cs.id_cita, cs.orden
       HAVING COUNT(DISTINCT COALESCE(cs.id_usuario, c.id_usuario)) > 1) x');
$put('excl_exclusivos_en_paralelo', $exclParalelo);
if ($exclParalelo > 0) {
    $mal('AUD_EXCLUSIVOS_PARALELO',
        "$exclParalelo cita(s) con dos profesionales haciendo servicios exclusivos a la vez: la clienta no puede estar en dos sillones", 'ALTO');
}

// Y el solape real: nadie puede estar en dos citas a la vez, contando el turno.
$solapes = (int) DB::scalar(
    "SELECT COUNT(*) FROM cita a JOIN cita b ON b.id_cita > a.id_cita
       JOIN estado_cita ea ON ea.id_estado_cita = a.id_estado_cita AND ea.bloquea_agenda = 1
       JOIN estado_cita eb ON eb.id_estado_cita = b.id_estado_cita AND eb.bloquea_agenda = 1
      WHERE a.id_usuario = b.id_usuario
        AND fn_cita_duracion_de(a.id_cita, a.id_usuario) > 0
        AND fn_cita_duracion_de(b.id_cita, b.id_usuario) > 0
        AND (a.fecha_hora + INTERVAL fn_cita_inicio_de(a.id_cita, a.id_usuario) MINUTE)
            < (b.fecha_hora + INTERVAL fn_cita_inicio_de(b.id_cita, b.id_usuario) MINUTE
                            + INTERVAL fn_cita_duracion_de(b.id_cita, b.id_usuario) MINUTE)
        AND (b.fecha_hora + INTERVAL fn_cita_inicio_de(b.id_cita, b.id_usuario) MINUTE)
            < (a.fecha_hora + INTERVAL fn_cita_inicio_de(a.id_cita, a.id_usuario) MINUTE
                            + INTERVAL fn_cita_duracion_de(a.id_cita, a.id_usuario) MINUTE)");
$put('excl_solapes_con_turno', $solapes);
if ($solapes > 0) {
    $mal('AUD_SOLAPE_AGENDA', "$solapes par(es) de citas se pisan en la agenda del mismo profesional", 'CRITICO');
}

// --- Identidad del sistema (7.35.0) -------------------------------------
$cfg = DB::selectOne('SELECT nombre_salon, logo FROM configuracion WHERE id_configuracion = 1');
$put('cfg_nombre_salon', (string) ($cfg->nombre_salon ?? ''));
$filasCfg = (int) DB::scalar('SELECT COUNT(*) FROM configuracion');
$put('cfg_filas_configuracion', $filasCfg);
if ($filasCfg !== 1) {
    $mal('AUD_CONFIG_DOBLE', 'La tabla `configuracion` tiene que tener exactamente una fila', 'ALTO');
}

sim_log(['tipo' => 'AUDITORIA_FINAL', 'r' => $R]);
echo json_encode($R, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
