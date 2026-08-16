<?php
/**
 * Cierre: los caminos que los 90 días no llegaron a recorrer, probados uno por
 * uno para no dejar nada como «no verificado» si se puede verificar.
 */

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

$hoy = date('Y-m-d');
$adm = new Nav();
if (! $adm->entrar('admin', 'admin123')) return;

// Caja abierta para todo lo que mueva plata
if (! DB::selectOne('SELECT id_caja FROM caja WHERE id_estado_caja=1 LIMIT 1')) {
    $adm->post('/facturacion/caja/abrir', ['monto_inicial' => '500000'])->seguir();
}

// =========================================================================
// A. Nota de crédito
// =========================================================================
$f = DB::selectOne('SELECT f.id_factura, f.id_cliente, fn_factura_total(f.id_factura) tot
                      FROM factura f WHERE f.id_estado_factura=1 AND f.id_tipo_comprobante IN (1,8)
                        AND NOT EXISTS (SELECT 1 FROM factura n WHERE n.id_factura_origen=f.id_factura)
                      ORDER BY f.id_factura DESC LIMIT 1');
if ($f) {
    $idf = (int) $f->id_factura;
    $ptsAntes = (int) DB::scalar('SELECT COALESCE(SUM(puntos),0) FROM movimiento_punto WHERE id_cliente=?', [(int) $f->id_cliente]);
    $adm->post('/facturacion/nota-credito', ['id_factura' => $idf, 'motivo' => 'La clienta devolvió el servicio'])->seguir();
    $nc = DB::selectOne('SELECT id_factura, nro_correlativo, fn_factura_total(id_factura) tot, id_timbrado
                           FROM factura WHERE id_factura_origen=? ORDER BY id_factura DESC LIMIT 1', [$idf]);
    $ptsDespues = (int) DB::scalar('SELECT COALESCE(SUM(puntos),0) FROM movimiento_punto WHERE id_cliente=?', [(int) $f->id_cliente]);
    sim_log(['tipo' => 'CIERRE', 'caso' => 'A_NOTA_CREDITO', 'origen' => $idf, 'tot_origen' => $f->tot,
             'nota' => $nc ? (int) $nc->id_factura : null, 'tot_nota' => $nc->tot ?? null,
             'puntos_antes' => $ptsAntes, 'puntos_despues' => $ptsDespues, 'msg' => $adm->flashTxt()]);
    if (! $nc) {
        sim_incidente('CIERRE_NC_NO_SALE', 'No se pudo emitir una nota de crédito: ' . $adm->flashTxt(), 'ALTO');
    } else {
        if (abs((float) $nc->tot - (float) $f->tot) > 0.01) {
            sim_incidente('CIERRE_NC_MONTO', "La nota de crédito acredita {$nc->tot} y el comprobante de origen es {$f->tot}", 'ALTO');
        }
        // El comprobante original queda EMITIDO y con saldo 0: ¿algo lo marca?
        $estado = (int) DB::scalar('SELECT id_estado_factura FROM factura WHERE id_factura=?', [$idf]);
        $saldo = (float) DB::scalar('SELECT fn_factura_saldo(?)', [$idf]);
        sim_log(['tipo' => 'CIERRE', 'caso' => 'A_ORIGEN_TRAS_NC', 'estado' => $estado, 'saldo' => $saldo]);
        if ($estado === 1) {
            sim_incidente('CIERRE_NC_NO_MARCA_ORIGEN',
                "Tras la nota de crédito el comprobante #$idf sigue en estado Emitida y con saldo $saldo: "
                . 'nada en la lista ni en los totales dice que ya fue acreditado, así que la venta se sigue contando entera', 'MEDIO');
        }
        // Segunda nota de crédito sobre el mismo comprobante
        $antes = (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_factura_origen=?', [$idf]);
        $adm->post('/facturacion/nota-credito', ['id_factura' => $idf, 'motivo' => 'Otra vez'])->seguir();
        if ((int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_factura_origen=?', [$idf]) > $antes) {
            sim_incidente('CIERRE_NC_DOBLE', "Se emitió una SEGUNDA nota de crédito sobre el comprobante #$idf", 'CRITICO');
        }
    }
}

// =========================================================================
// B. Anular un comprobante cobrado (primero los cobros)
// =========================================================================
$f2 = DB::selectOne('SELECT f.id_factura, f.id_cliente FROM factura f
                      WHERE f.id_estado_factura=1 AND f.id_tipo_comprobante=8
                        AND EXISTS (SELECT 1 FROM cobro c WHERE c.id_factura=f.id_factura AND c.id_estado_cobro=1)
                      ORDER BY f.id_factura DESC LIMIT 1');
if ($f2) {
    $idf = (int) $f2->id_factura;
    foreach (DB::select('SELECT id_cobro FROM cobro WHERE id_factura=? AND id_estado_cobro=1', [$idf]) as $c) {
        $adm->post('/facturacion/anular-cobro', ['id_cobro' => (int) $c->id_cobro, 'motivo' => 'Error de carga en el mostrador'])->seguir();
    }
    $ptsAntes = (int) DB::scalar('SELECT COALESCE(SUM(puntos),0) FROM movimiento_punto WHERE id_cliente=?', [(int) $f2->id_cliente]);
    $adm->post('/facturacion/anular-factura', ['id_factura' => $idf, 'motivo' => 'Se cargó el comprobante equivocado'])->seguir();
    $estado = (int) DB::scalar('SELECT id_estado_factura FROM factura WHERE id_factura=?', [$idf]);
    $ptsDespues = (int) DB::scalar('SELECT COALESCE(SUM(puntos),0) FROM movimiento_punto WHERE id_cliente=?', [(int) $f2->id_cliente]);
    $sigueNumero = (string) DB::scalar('SELECT fn_factura_nro(?)', [$idf]);
    sim_log(['tipo' => 'CIERRE', 'caso' => 'B_ANULA_FACTURA', 'factura' => $idf, 'estado' => $estado,
             'numero' => $sigueNumero, 'puntos_antes' => $ptsAntes, 'puntos_despues' => $ptsDespues, 'msg' => $adm->flashTxt()]);
    if ($estado !== 2) {
        sim_incidente('CIERRE_ANULA_NO_FUNCIONA', "No se pudo anular el comprobante #$idf: " . $adm->flashTxt(), 'ALTO');
    }
    // ¿La cita vuelve a poder facturarse? (el número anterior se pierde)
    $cita = (int) DB::scalar('SELECT COALESCE(id_cita,0) FROM factura WHERE id_factura=?', [$idf]);
    if ($cita) {
        $adm->post('/facturacion/emitir', ['id_cita' => $cita, 'id_tipo_comprobante' => 8, 'id_condicion_venta' => 1])->seguir();
        $nuevas = (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_cita=? AND id_estado_factura=1', [$cita]);
        sim_log(['tipo' => 'CIERRE', 'caso' => 'B_REEMITE', 'cita' => $cita, 'vigentes' => $nuevas, 'msg' => $adm->flashTxt()]);
    }
}

// =========================================================================
// C. Cita repartida entre dos profesionales
// =========================================================================
$cli = (int) DB::scalar('SELECT id_cliente FROM cliente WHERE activo=1 ORDER BY RAND() LIMIT 1');
$fecha = date('Y-m-d', strtotime('+2 day'));
$adm->get('/citas/disponibilidad', ['servicios' => [9, 12], 'id_usuario' => 10, 'fecha' => $fecha]);
$j = json_decode($adm->body, true);
if (! empty($j['horas'])) {
    $h = $j['horas'][0]['hora'];
    $adm->post('/citas/guardar', [
        'id_cliente' => $cli, 'servicios' => [9, 12], 'id_usuario' => 10,
        'fecha_hora' => $fecha . ' ' . $h,
        'prof_servicio' => [9 => 10, 12 => 12],     // lavado Marta, manicura Lucía
        'observaciones' => 'Reparto entre dos profesionales',
    ])->seguir();
    $idc = (int) DB::scalar('SELECT id_cita FROM cita WHERE id_cliente=? ORDER BY id_cita DESC LIMIT 1', [$cli]);
    $reparto = DB::select('SELECT id_servicio, id_usuario FROM cita_servicio WHERE id_cita=?', [$idc]);
    $dur = (int) DB::scalar('SELECT fn_cita_duracion(?)', [$idc]);
    sim_log(['tipo' => 'CIERRE', 'caso' => 'C_REPARTO', 'cita' => $idc, 'dur' => $dur,
             'reparto' => array_map(fn ($r) => $r->id_servicio . '=>' . ($r->id_usuario ?? 'principal'), $reparto),
             'msg' => $adm->flashTxt()]);
    // Se adelanta la cita a hoy para poder atenderla y ver a nombre de quién queda
    if ($idc) {
        DB::update('UPDATE cita SET fecha_hora = DATE_SUB(NOW(), INTERVAL 30 MINUTE) WHERE id_cita=?', [$idc]);
        $adm->post('/citas/atender', ['id_cita' => $idc, 'servicios' => [9, 12], 'dia' => $hoy])->seguir();
        $sr = DB::select('SELECT sr.id_servicio, sr.id_usuario, cs.id_usuario AS del_reparto
                            FROM servicio_realizado sr
                            LEFT JOIN cita_servicio cs ON cs.id_cita=sr.id_cita AND cs.id_servicio=sr.id_servicio
                           WHERE sr.id_cita=?', [$idc]);
        $malos = 0;
        foreach ($sr as $x) {
            if ($x->del_reparto !== null && (int) $x->del_reparto !== (int) $x->id_usuario) $malos++;
        }
        sim_log(['tipo' => 'CIERRE', 'caso' => 'C_ATENCION_REPARTO',
                 'sr' => array_map(fn ($x) => "srv{$x->id_servicio}: se registró a {$x->id_usuario}, el reparto decía "
                        . ($x->del_reparto ?? 'principal'), $sr), 'msg' => $adm->flashTxt()]);
        if ($malos > 0) {
            sim_incidente('CIERRE_REPARTO_IGNORADO',
                "En una cita repartida, $malos servicio(s) quedaron registrados a nombre del profesional PRINCIPAL "
                . 'y no de quien figura en `cita_servicio.id_usuario`. `CitasController::atenderGuardar` escribe '
                . 'siempre `$cita->id_usuario`, así que la comisión y la columna «Generado» del informe del equipo '
                . 'se le cargan a la persona equivocada', 'ALTO');
        }
    }
}

// =========================================================================
// D. Excepciones de agenda y el aviso al cliente
// =========================================================================
$prof = 11;
$desde = date('Y-m-d', strtotime('+3 day')) . ' 00:00';
$hasta = date('Y-m-d', strtotime('+5 day')) . ' 23:59';
$afectadas = (int) DB::scalar('SELECT COUNT(*) FROM cita c JOIN estado_cita e ON e.id_estado_cita=c.id_estado_cita
                                WHERE c.id_usuario=? AND e.bloquea_agenda=1 AND c.fecha_hora BETWEEN ? AND ?',
                               [$prof, $desde, $hasta]);
$notAntes = (int) DB::scalar('SELECT COUNT(*) FROM notificacion');
$adm->post('/citas/excepciones', ['id_usuario' => $prof, 'id_tipo_ausencia' => 2,
    'fecha_inicio' => $desde, 'fecha_fin' => $hasta, 'motivo' => 'Licencia médica'])->seguir();
$notDespues = (int) DB::scalar('SELECT COUNT(*) FROM notificacion');
$libre = (int) DB::scalar('SELECT fn_verificar_disponibilidad(?,?,60,NULL)', [$prof, date('Y-m-d', strtotime('+4 day')) . ' 14:00']);
sim_log(['tipo' => 'CIERRE', 'caso' => 'D_AUSENCIA', 'prof' => $prof, 'citas_afectadas' => $afectadas,
         'avisos_creados' => $notDespues - $notAntes, 'agenda_bloqueada' => $libre === 0, 'msg' => $adm->flashTxt()]);
if ($libre !== 0) {
    sim_incidente('CIERRE_AUSENCIA_NO_BLOQUEA', 'Con una ausencia cargada, la agenda sigue dando disponible al profesional', 'ALTO');
}
if ($afectadas > 0 && ($notDespues - $notAntes) < $afectadas) {
    sim_incidente('CIERRE_AUSENCIA_SIN_AVISO',
        "Se cargó una ausencia que pisa $afectadas cita(s) y sólo se crearon " . ($notDespues - $notAntes) . ' aviso(s)', 'ALTO');
}

// =========================================================================
// E. Recordatorio de una cita que después se cancela
// =========================================================================
$fut = DB::selectOne('SELECT c.id_cita, c.id_cliente, c.fecha_hora FROM cita c
                       WHERE c.id_estado_cita IN (1,2) AND c.fecha_hora BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 20 HOUR)
                       ORDER BY c.fecha_hora LIMIT 1');
if ($fut) {
    Illuminate\Support\Facades\Artisan::call('spg:notificaciones');
    $rec = DB::selectOne("SELECT id_notificacion, estado, mensaje FROM notificacion
                           WHERE id_cita=? AND id_tipo_notificacion=1 ORDER BY id_notificacion DESC LIMIT 1", [(int) $fut->id_cita]);
    if ($rec) {
        // Se cancela y se vuelve a correr el cron
        $adm->post('/citas/cancelar', ['id_cita' => (int) $fut->id_cita, 'dia' => $hoy])->seguir();
        Illuminate\Support\Facades\Artisan::call('spg:notificaciones');
        $rec2 = DB::selectOne('SELECT estado, fecha_envio FROM notificacion WHERE id_notificacion=?', [(int) $rec->id_notificacion]);
        sim_log(['tipo' => 'CIERRE', 'caso' => 'E_RECORDATORIO_CANCELADA', 'cita' => (int) $fut->id_cita,
                 'estado_antes' => $rec->estado, 'estado_despues' => $rec2->estado ?? null, 'mensaje' => $rec->mensaje]);
        if (($rec2->estado ?? '') === 'ENVIADA' && $rec->estado === 'PENDIENTE') {
            sim_incidente('CIERRE_RECORDATORIO_TRAS_CANCELAR',
                'El recordatorio de la cita #' . $fut->id_cita . ' se envió DESPUÉS de cancelarla: '
                . 'Notificaciones::despachar() no mira el estado de la cita', 'ALTO');
        }
    }
}

// Recordatorio con la fecha vieja tras reprogramar
$conRec = DB::selectOne("SELECT n.id_notificacion, n.mensaje, c.id_cita, c.id_usuario, c.fecha_hora
                           FROM notificacion n JOIN cita c ON c.id_cita=n.id_cita
                          WHERE n.id_tipo_notificacion=1 AND c.id_estado_cita IN (1,2) AND c.fecha_hora > NOW()
                          ORDER BY n.id_notificacion DESC LIMIT 1");
if ($conRec) {
    $nueva = date('Y-m-d', strtotime('+6 day')) . ' 10:00';
    $adm->get('/citas/disponibilidad', ['servicios' => array_map(fn ($r) => (int) $r->id_servicio,
        DB::select('SELECT id_servicio FROM cita_servicio WHERE id_cita=?', [(int) $conRec->id_cita])),
        'id_usuario' => (int) $conRec->id_usuario, 'fecha' => date('Y-m-d', strtotime('+6 day'))]);
    $jj = json_decode($adm->body, true);
    if (! empty($jj['horas'])) {
        $nueva = date('Y-m-d', strtotime('+6 day')) . ' ' . $jj['horas'][0]['hora'];
    }
    $adm->post('/citas/reprogramar', ['id_cita' => (int) $conRec->id_cita, 'nueva_fecha' => $nueva, 'dia' => $hoy])->seguir();
    Illuminate\Support\Facades\Artisan::call('spg:notificaciones');
    $cuantos = (int) DB::scalar('SELECT COUNT(*) FROM notificacion WHERE id_cita=? AND id_tipo_notificacion=1', [(int) $conRec->id_cita]);
    $fh = (string) DB::scalar('SELECT fecha_hora FROM cita WHERE id_cita=?', [(int) $conRec->id_cita]);
    $coincide = str_contains((string) $conRec->mensaje, date('d/m/Y \a \l\a\s H:i', strtotime($fh)));
    sim_log(['tipo' => 'CIERRE', 'caso' => 'E_RECORDATORIO_REPROGRAMADA', 'cita' => (int) $conRec->id_cita,
             'avisos_tipo1' => $cuantos, 'mensaje_viejo' => $conRec->mensaje, 'fecha_nueva' => $fh, 'coincide' => $coincide]);
    if (! $coincide && $cuantos === 1) {
        sim_incidente('CIERRE_RECORDATORIO_FECHA_VIEJA',
            'La cita #' . $conRec->id_cita . ' se reprogramó al ' . $fh . ' y su único recordatorio sigue diciendo «'
            . $conRec->mensaje . '». generarRecordatorios() saltea la cita porque ya tiene un aviso tipo 1, '
            . 'así que ni se corrige el viejo ni se genera uno nuevo', 'ALTO');
    }
}

// =========================================================================
// F. Timbrado agotado
// =========================================================================
$adm->post('/facturacion/timbrados/guardar', [
    'nro_timbrado' => '11223344', 'establecimiento' => '002', 'punto_expedicion' => '001',
    'nro_desde' => '1', 'nro_hasta' => '1', 'fecha_inicio' => $hoy,
    'fecha_fin' => date('Y-m-d', strtotime('+1 year')), 'id_tipo_comprobante' => 2,
])->seguir();
sim_log(['tipo' => 'CIERRE', 'caso' => 'F_TIMBRADO_CHICO', 'msg' => $adm->flashTxt()]);

// =========================================================================
// G. Cambio de contraseña con segundo factor, y recuperación
// =========================================================================
$u = new Nav();
if ($u->entrar('sofia', 'profesional123')) {
    $u->post('/cuenta/password', ['actual' => 'profesional123',
        'nueva' => 'nuevaclave1', 'nueva2' => 'nuevaclave1'])->seguir();
    $idu = (int) DB::scalar("SELECT id_usuario FROM usuario WHERE username='sofia'");
    $cod = (string) (DB::scalar("SELECT codigo FROM token_seguridad WHERE id_usuario=? AND tipo='CAMBIO_PASSWORD' AND usado=0 ORDER BY id_token DESC LIMIT 1", [$idu]) ?: '');
    $hashAntes = (string) DB::scalar('SELECT password_hash FROM usuario WHERE id_usuario=?', [$idu]);
    // Primero con un código inventado
    $u->post('/cuenta/password/confirmar', ['codigo' => '999999'])->seguir();
    $hashMedio = (string) DB::scalar('SELECT password_hash FROM usuario WHERE id_usuario=?', [$idu]);
    if ($hashMedio !== $hashAntes) {
        sim_incidente('CIERRE_2FA_BURLADO', 'La contraseña cambió con un código de verificación incorrecto', 'CRITICO');
    }
    if ($cod !== '') {
        $u->post('/cuenta/password/confirmar', ['codigo' => $cod])->seguir();
    }
    $hashDespues = (string) DB::scalar('SELECT password_hash FROM usuario WHERE id_usuario=?', [$idu]);
    sim_log(['tipo' => 'CIERRE', 'caso' => 'G_CAMBIO_PASSWORD', 'cambio' => $hashDespues !== $hashAntes,
             'codigo_hallado' => $cod !== '', 'msg' => $u->flashTxt()]);
    $u->salir();
    // Se vuelve a entrar con la clave nueva
    $v = new Nav();
    $ok = $v->entrar('sofia', 'nuevaclave1');
    sim_log(['tipo' => 'CIERRE', 'caso' => 'G_LOGIN_NUEVA_CLAVE', 'ok' => $ok]);
    if ($ok) { $v->salir(); }
}

// Recuperación de contraseña
$r = new Nav();
$r->quien = 'olvidadiza';
$r->get('/recuperar');
$r->post('/recuperar', ['email' => 'marta.caceres@peluqueria.local'])->seguir();
$idm = (int) DB::scalar("SELECT id_usuario FROM usuario WHERE username='marta'");
$codR = (string) (DB::scalar("SELECT codigo FROM token_seguridad WHERE id_usuario=? AND tipo='RECUPERACION' AND usado=0 ORDER BY id_token DESC LIMIT 1", [$idm]) ?: '');
sim_log(['tipo' => 'CIERRE', 'caso' => 'G_RECUPERACION', 'token_generado' => $codR !== '', 'msg' => $r->flashTxt()]);

// =========================================================================
// H. Bajas lógicas: nada se borra
// =========================================================================
$bajas = [];
$c1 = DB::selectOne('SELECT c.id_cliente FROM cliente c WHERE c.activo=1
                       AND EXISTS (SELECT 1 FROM cita x WHERE x.id_cliente=c.id_cliente) LIMIT 1');
if ($c1) {
    $citasAntes = (int) DB::scalar('SELECT COUNT(*) FROM cita WHERE id_cliente=?', [(int) $c1->id_cliente]);
    $adm->post('/clientes/baja', ['id_cliente' => (int) $c1->id_cliente])->seguir();
    $bajas['cliente'] = ['activo' => (int) DB::scalar('SELECT activo FROM cliente WHERE id_cliente=?', [(int) $c1->id_cliente]),
                         'citas_antes' => $citasAntes,
                         'citas_despues' => (int) DB::scalar('SELECT COUNT(*) FROM cita WHERE id_cliente=?', [(int) $c1->id_cliente])];
}
$s1 = DB::selectOne('SELECT id_servicio FROM servicio WHERE activo=1 ORDER BY id_servicio DESC LIMIT 1');
if ($s1) {
    $adm->post('/servicios/baja', ['id_servicio' => (int) $s1->id_servicio])->seguir();
    $bajas['servicio'] = (int) DB::scalar('SELECT activo FROM servicio WHERE id_servicio=?', [(int) $s1->id_servicio]);
    // ¿Se puede seguir agendando un servicio dado de baja?
    $adm->post('/citas/guardar', ['id_cliente' => $cli, 'servicios' => [(int) $s1->id_servicio],
        'fecha_hora' => date('Y-m-d', strtotime('+3 day')) . ' 09:00'])->seguir();
    $bajas['agenda_servicio_inactivo'] = $adm->dice('Cita agendada');
    $adm->post('/servicios/baja', ['id_servicio' => (int) $s1->id_servicio])->seguir();   // se reactiva
}
$p1 = DB::selectOne('SELECT id_producto FROM producto WHERE activo=1 ORDER BY id_producto DESC LIMIT 1');
if ($p1) {
    $adm->post('/inventario/productos/baja', ['id_producto' => (int) $p1->id_producto])->seguir();
    $bajas['producto'] = (int) DB::scalar('SELECT activo FROM producto WHERE id_producto=?', [(int) $p1->id_producto]);
    $adm->post('/inventario/productos/baja', ['id_producto' => (int) $p1->id_producto])->seguir();
}
$u1 = DB::selectOne("SELECT u.id_usuario FROM usuario u WHERE u.username='karen'");
if ($u1) {
    $futAntes = (int) DB::scalar('SELECT COUNT(*) FROM cita c JOIN estado_cita e ON e.id_estado_cita=c.id_estado_cita
                                   WHERE c.id_usuario=? AND e.bloquea_agenda=1 AND c.fecha_hora>NOW()', [(int) $u1->id_usuario]);
    $notA = (int) DB::scalar('SELECT COUNT(*) FROM notificacion');
    $adm->post('/seguridad/usuarios/baja', ['id_usuario' => (int) $u1->id_usuario])->seguir();
    $bajas['usuario'] = ['activo' => (int) DB::scalar('SELECT activo FROM usuario WHERE id_usuario=?', [(int) $u1->id_usuario]),
                         'citas_futuras' => $futAntes,
                         'avisos' => (int) DB::scalar('SELECT COUNT(*) FROM notificacion') - $notA,
                         'msg' => $adm->flashTxt()];
    $adm->post('/seguridad/usuarios/baja', ['id_usuario' => (int) $u1->id_usuario])->seguir();   // se reactiva
}
sim_log(['tipo' => 'CIERRE', 'caso' => 'H_BAJAS', 'r' => $bajas]);

// =========================================================================
// I. El profesional atrapado por el stock
// =========================================================================
$pro = new Nav();
if ($pro->entrar('marta', 'profesional123')) {
    $pro->get('/inventario/stock');
    $stStock = $pro->status;
    $pro->get('/inventario/cargar-stock');
    $stCargar = $pro->status;
    sim_log(['tipo' => 'CIERRE', 'caso' => 'I_PROFESIONAL_STOCK', 'inventario_stock' => $stStock, 'cargar_stock' => $stCargar]);
    if ($stStock === 403 && $stCargar === 403) {
        sim_incidente('CIERRE_STOCK_SIN_SALIDA',
            'Cuando falta stock, «Registrar atención» falla entera y el mensaje dice «cargá stock desde Inventario»; '
            . 'pero el rol Profesional recibe 403 tanto en Inventario → Stock como en Cargar stock. '
            . 'La persona que atiende no puede resolverlo ni registrar el trabajo que ya hizo '
            . '(69 atenciones de la simulación murieron así)', 'ALTO');
    }
    $pro->salir();
}

// =========================================================================
// J. Portal: pedido y valoración
// =========================================================================
$cuenta = DB::selectOne("SELECT u.username, c.id_cliente FROM usuario u
                           JOIN cliente c ON c.id_usuario=u.id_usuario
                          WHERE u.activo=1 AND u.id_rol=4 ORDER BY u.id_usuario DESC LIMIT 1");
if ($cuenta) {
    // Se le arma una cita En proceso
    $adm2 = new Nav();
    if ($adm2->entrar('admin', 'admin123')) {
        $adm2->get('/citas/disponibilidad', ['servicios' => [15], 'id_usuario' => 10, 'fecha' => date('Y-m-d', strtotime('+1 day'))]);
        $jj = json_decode($adm2->body, true);
        if (! empty($jj['horas'])) {
            $adm2->post('/citas/guardar', ['id_cliente' => (int) $cuenta->id_cliente, 'servicios' => [15],
                'id_usuario' => 10, 'fecha_hora' => date('Y-m-d', strtotime('+1 day')) . ' ' . $jj['horas'][0]['hora']])->seguir();
            $idc = (int) DB::scalar('SELECT id_cita FROM cita WHERE id_cliente=? ORDER BY id_cita DESC LIMIT 1', [(int) $cuenta->id_cliente]);
            DB::update('UPDATE cita SET fecha_hora = DATE_SUB(NOW(), INTERVAL 10 MINUTE), id_estado_cita = 5 WHERE id_cita=?', [$idc]);

            $cl = new Nav();
            $pw = $cuenta->username === 'cliente' ? 'cliente123' : 'clienta123';
            if ($cl->entrar((string) $cuenta->username, $pw)) {
                $cl->get('/portal/atencion', ['id' => $idc]);
                $stAt = $cl->status;
                $cl->get('/portal/atencion/json', ['id' => $idc]);
                $json = $cl->body;
                $cl->post('/portal/pedir', ['id_cita' => $idc, 'pedido' => 'Agregame las cejas'])->seguir();
                $ped = (int) DB::scalar('SELECT COUNT(*) FROM cita_pedido WHERE id_cita=?', [$idc]);
                $cl->salir();

                // El salón la atiende y la clienta la califica
                $adm2->post('/citas/atender', ['id_cita' => $idc, 'servicios' => [15], 'dia' => $hoy])->seguir();
                $cl2 = new Nav();
                if ($cl2->entrar((string) $cuenta->username, $pw)) {
                    $cl2->post('/portal/calificar', ['id_cita' => $idc, 'puntaje' => '5', 'comentario' => 'Excelente'])->seguir();
                    $cal = (int) DB::scalar('SELECT COUNT(*) FROM calificacion WHERE id_cita=?', [$idc]);
                    $cl2->post('/portal/calificar', ['id_cita' => $idc, 'puntaje' => '1', 'comentario' => 'Cambio de idea'])->seguir();
                    $cal2 = (int) DB::scalar('SELECT COUNT(*) FROM calificacion WHERE id_cita=?', [$idc]);
                    sim_log(['tipo' => 'CIERRE', 'caso' => 'J_PORTAL', 'cita' => $idc, 'atencion_http' => $stAt,
                             'pedidos' => $ped, 'calificaciones' => $cal, 'tras_segundo_intento' => $cal2,
                             'json_len' => strlen($json), 'msg' => $cl2->flashTxt()]);
                    if ($cal2 > 1) {
                        sim_incidente('CIERRE_DOBLE_CALIFICACION', 'La misma cita quedó calificada dos veces', 'MEDIO');
                    }
                    $cl2->salir();
                }
            }
        }
        $adm2->salir();
    }
}

// =========================================================================
// K. Rastro de lo que quedó mal
// =========================================================================
sim_log(['tipo' => 'CIERRE', 'caso' => 'K_STOCK_NEGATIVO',
         'detalle' => DB::select("SELECT p.nombre, fn_producto_stock(p.id_producto) stock,
                                        (SELECT GROUP_CONCAT(CONCAT(m.id_tipo_movimiento,':',m.cantidad,'@',m.referencia) ORDER BY m.id_movimiento DESC SEPARATOR ' | ')
                                           FROM movimiento_inventario m WHERE m.id_producto=p.id_producto
                                             AND m.referencia LIKE 'CONC%') conc
                                   FROM producto p WHERE fn_producto_stock(p.id_producto) < 0")]);
sim_log(['tipo' => 'CIERRE', 'caso' => 'K_AUDITORIA_FUTURA',
         'detalle' => DB::select('SELECT id_auditoria, id_usuario, accion, modulo, fecha_hora, LEFT(detalle,120) d
                                    FROM auditoria WHERE fecha_hora > NOW() ORDER BY fecha_hora DESC LIMIT 5')]);
sim_log(['tipo' => 'CIERRE', 'caso' => 'K_SALDO_NEGATIVO',
         'detalle' => DB::select('SELECT f.id_factura, fn_factura_nro(f.id_factura) nro, f.id_cita,
                                         fn_factura_total(f.id_factura) total, fn_factura_saldo(f.id_factura) saldo,
                                         (SELECT COALESCE(SUM(monto),0) FROM cobro WHERE id_factura=f.id_factura AND id_estado_cobro=1) cob_fac,
                                         (SELECT COALESCE(SUM(monto),0) FROM cobro WHERE id_cita=f.id_cita AND id_estado_cobro=1) cob_cita
                                    FROM factura f WHERE fn_factura_saldo(f.id_factura) < 0')]);

$adm->salir();
sim_log(['tipo' => 'CIERRE_FIN']);
echo "cierre ok\n";
