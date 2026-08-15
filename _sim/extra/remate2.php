<?php
/** Segunda pasada de cierre: lo que quedó bloqueado por el propio banco de pruebas. */

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

$hoy = date('Y-m-d');

// La recepción ficha la entrada de la mañana, si no «Registrar atención» no deja
$rec = new Nav();
if ($rec->entrar('recepcion', 'recepcion123')) {
    foreach ([10, 12] as $idp) {
        $rec->post('/seguridad/asistencia', ['accion' => 'entrada', 'id_turno' => 3,
            'id_usuario' => $idp, 'fecha' => $hoy])->seguir();
        sim_log(['tipo' => 'R2', 'caso' => 'FICHAJE', 'prof' => $idp, 'msg' => $rec->flashTxt()]);
    }
    $rec->salir();
}

$adm = new Nav();
if (! $adm->entrar('admin', 'admin123')) return;
if (! DB::selectOne('SELECT id_caja FROM caja WHERE id_estado_caja=1 LIMIT 1')) {
    $adm->post('/facturacion/caja/abrir', ['monto_inicial' => '500000'])->seguir();
}

// =========================================================================
// C. Cita repartida entre dos profesionales (día hábil)
// =========================================================================
$cli = (int) DB::scalar('SELECT id_cliente FROM cliente WHERE activo=1 ORDER BY RAND() LIMIT 1');
$dia = date('Y-m-d', strtotime('next monday'));
$adm->get('/citas/disponibilidad', ['servicios' => [9, 12], 'id_usuario' => 10, 'fecha' => $dia]);
$j = json_decode($adm->body, true);
sim_log(['tipo' => 'R2', 'caso' => 'C_SLOTS', 'dia' => $dia, 'n' => count($j['horas'] ?? [])]);

if (! empty($j['horas'])) {
    $h = $j['horas'][0]['hora'];
    $adm->post('/citas/guardar', [
        'id_cliente' => $cli, 'servicios' => [9, 12], 'id_usuario' => 10,
        'fecha_hora' => $dia . ' ' . $h,
        'prof_servicio' => [9 => 10, 12 => 12],
        'observaciones' => 'Reparto: lavado con Marta, manicura con Lucía',
    ])->seguir();
    $idc = (int) DB::scalar('SELECT id_cita FROM cita WHERE id_cliente=? ORDER BY id_cita DESC LIMIT 1', [$cli]);
    $reparto = DB::select('SELECT id_servicio, id_usuario FROM cita_servicio WHERE id_cita=?', [$idc]);
    sim_log(['tipo' => 'R2', 'caso' => 'C_REPARTO', 'cita' => $idc,
             'dur_cita' => (int) DB::scalar('SELECT fn_cita_duracion(?)', [$idc]),
             'dur_marta' => (int) DB::scalar('SELECT fn_cita_duracion_de(?,10)', [$idc]),
             'dur_lucia' => (int) DB::scalar('SELECT fn_cita_duracion_de(?,12)', [$idc]),
             'reparto' => array_map(fn ($r) => $r->id_servicio . '=>' . ($r->id_usuario ?? 'principal'), $reparto),
             'msg' => $adm->flashTxt()]);

    if ($idc && count($reparto) === 2) {
        DB::update('UPDATE cita SET fecha_hora = DATE_SUB(NOW(), INTERVAL 30 MINUTE) WHERE id_cita=?', [$idc]);
        $adm->post('/citas/atender', ['id_cita' => $idc, 'servicios' => [9, 12], 'dia' => $hoy])->seguir();
        $sr = DB::select('SELECT sr.id_servicio, sr.id_usuario, cs.id_usuario AS del_reparto
                            FROM servicio_realizado sr
                            LEFT JOIN cita_servicio cs ON cs.id_cita=sr.id_cita AND cs.id_servicio=sr.id_servicio
                           WHERE sr.id_cita=?', [$idc]);
        $malos = [];
        foreach ($sr as $x) {
            if ($x->del_reparto !== null && (int) $x->del_reparto !== (int) $x->id_usuario) {
                $malos[] = "servicio {$x->id_servicio}: quedó a nombre de {$x->id_usuario}, el reparto decía {$x->del_reparto}";
            }
        }
        sim_log(['tipo' => 'R2', 'caso' => 'C_ATENCION', 'cita' => $idc, 'msg' => $adm->flashTxt(),
                 'sr' => array_map(fn ($x) => [$x->id_servicio, $x->id_usuario, $x->del_reparto], $sr), 'malos' => $malos]);
        if ($malos) {
            sim_incidente('R2_REPARTO_IGNORADO',
                'Cita repartida #' . $idc . ': ' . implode(' | ', $malos)
                . '. `CitasController::atenderGuardar` escribe siempre `$cita->id_usuario` en `servicio_realizado`, '
                . 'así que la comisión (`fn_comision_servicio`) y las columnas «Generado» y «Comisión» del informe '
                . 'del equipo se le acreditan al profesional principal y no a quien hizo el trabajo', 'ALTO');
        }
    }
}

// =========================================================================
// J. Portal: valoración de una cita atendida
// =========================================================================
$cuenta = DB::selectOne("SELECT u.username, c.id_cliente FROM usuario u
                           JOIN cliente c ON c.id_usuario=u.id_usuario
                          WHERE u.activo=1 AND u.id_rol=4 ORDER BY u.id_usuario DESC LIMIT 1");
if ($cuenta) {
    $idc = (int) DB::scalar('SELECT id_cita FROM cita WHERE id_cliente=? AND id_estado_cita=5 ORDER BY id_cita DESC LIMIT 1',
        [(int) $cuenta->id_cliente]);
    if (! $idc) {
        $idc = (int) DB::scalar('SELECT id_cita FROM cita WHERE id_cliente=? AND id_estado_cita IN (1,2,5) ORDER BY id_cita DESC LIMIT 1',
            [(int) $cuenta->id_cliente]);
    }
    if ($idc) {
        // El profesional de esa cita tiene que tener fichaje: se lo pone la recepción
        $prof = (int) DB::scalar('SELECT id_usuario FROM cita WHERE id_cita=?', [$idc]);
        $tur = (int) (DB::scalar('SELECT ut.id_turno FROM usuario_turno ut
                                   JOIN turno_laboral t ON t.id_turno=ut.id_turno
                                   JOIN turno_dia td ON td.id_turno=t.id_turno AND td.dia_semana=?
                                  WHERE ut.id_usuario=? LIMIT 1', [(int) date('N'), $prof]) ?: 0);
        if ($tur) {
            $r2 = new Nav();
            if ($r2->entrar('recepcion', 'recepcion123')) {
                $r2->post('/seguridad/asistencia', ['accion' => 'entrada', 'id_turno' => $tur,
                    'id_usuario' => $prof, 'fecha' => $hoy])->seguir();
                $r2->salir();
            }
        }
        DB::update('UPDATE cita SET fecha_hora = DATE_SUB(NOW(), INTERVAL 20 MINUTE) WHERE id_cita=?', [$idc]);
        $srv = array_map(fn ($r) => (int) $r->id_servicio, DB::select('SELECT id_servicio FROM cita_servicio WHERE id_cita=?', [$idc]));
        $adm->post('/citas/atender', ['id_cita' => $idc, 'servicios' => $srv ?: [15], 'dia' => $hoy])->seguir();
        $estado = (int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita=?', [$idc]);
        sim_log(['tipo' => 'R2', 'caso' => 'J_ATENCION', 'cita' => $idc, 'estado' => $estado, 'msg' => $adm->flashTxt()]);

        if ($estado === 4) {
            $cl = new Nav();
            $pw = $cuenta->username === 'cliente' ? 'cliente123' : 'clienta123';
            if ($cl->entrar((string) $cuenta->username, $pw)) {
                $cl->get('/portal/valoraciones');
                $cl->post('/portal/calificar', ['id_cita' => $idc, 'puntaje' => '5', 'comentario' => 'Excelente atención'])->seguir();
                $n1 = (int) DB::scalar('SELECT COUNT(*) FROM calificacion WHERE id_cita=?', [$idc]);
                $m1 = $cl->flashTxt();
                $cl->post('/portal/calificar', ['id_cita' => $idc, 'puntaje' => '1', 'comentario' => 'Cambio de idea'])->seguir();
                $n2 = (int) DB::scalar('SELECT COUNT(*) FROM calificacion WHERE id_cita=?', [$idc]);
                $cl->post('/portal/calificar', ['id_cita' => $idc, 'puntaje' => '9'])->seguir();
                $max = (int) DB::scalar('SELECT COALESCE(MAX(puntaje),0) FROM calificacion');
                sim_log(['tipo' => 'R2', 'caso' => 'J_CALIFICA', 'cita' => $idc, 'primera' => $n1,
                         'tras_segunda' => $n2, 'max_puntaje' => $max, 'msg1' => $m1, 'msg2' => $cl->flashTxt()]);
                if ($n1 === 0) {
                    sim_incidente('R2_CALIFICACION_NO_SALE', 'La clienta no pudo calificar su cita atendida: ' . $m1, 'ALTO');
                }
                if ($n2 > 1) {
                    sim_incidente('R2_DOBLE_CALIFICACION', 'La misma cita quedó calificada dos veces', 'MEDIO');
                }
                $cl->salir();
            }
        }
    }
}

// =========================================================================
// H. Bajas lógicas, con el aviso a la vista
// =========================================================================
$c1 = DB::selectOne('SELECT c.id_cliente FROM cliente c WHERE c.activo=1
                       AND EXISTS (SELECT 1 FROM cita x WHERE x.id_cliente=c.id_cliente
                                    AND x.id_estado_cita IN (1,2) AND x.fecha_hora > NOW()) LIMIT 1');
if ($c1) {
    $id = (int) $c1->id_cliente;
    $citasAntes = (int) DB::scalar('SELECT COUNT(*) FROM cita WHERE id_cliente=?', [$id]);
    $facAntes = (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_cliente=?', [$id]);
    $adm->post('/clientes/baja', ['id_cliente' => $id])->seguir();
    $r = ['activo' => (int) DB::scalar('SELECT activo FROM cliente WHERE id_cliente=?', [$id]),
          'citas' => $citasAntes . '→' . (int) DB::scalar('SELECT COUNT(*) FROM cita WHERE id_cliente=?', [$id]),
          'facturas' => $facAntes . '→' . (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_cliente=?', [$id]),
          'msg' => $adm->flashTxt()];
    // ¿Se le puede seguir agendando estando inactivo?
    $adm->post('/citas/guardar', ['id_cliente' => $id, 'servicios' => [15],
        'fecha_hora' => date('Y-m-d', strtotime('next tuesday')) . ' 09:00'])->seguir();
    $r['agenda_inactivo'] = $adm->dice('Cita agendada');
    $adm->post('/clientes/baja', ['id_cliente' => $id])->seguir();
    sim_log(['tipo' => 'R2', 'caso' => 'H_CLIENTE', 'r' => $r]);
    if ($r['activo'] !== 0) {
        sim_incidente('R2_BAJA_CLIENTE_NO_APLICA', 'La baja del cliente #' . $id . ' no cambió su estado: ' . $r['msg'], 'MEDIO');
    }
    if ($r['agenda_inactivo']) {
        sim_incidente('R2_AGENDA_CLIENTE_INACTIVO', 'Se agendó una cita para un cliente dado de baja', 'ALTO');
    }
}

$k = DB::selectOne("SELECT id_usuario FROM usuario WHERE username='karen' AND activo=1");
if ($k) {
    $id = (int) $k->id_usuario;
    $fut = (int) DB::scalar('SELECT COUNT(*) FROM cita c JOIN estado_cita e ON e.id_estado_cita=c.id_estado_cita
                              WHERE c.id_usuario=? AND e.bloquea_agenda=1 AND c.fecha_hora>NOW()', [$id]);
    $notA = (int) DB::scalar('SELECT COUNT(*) FROM notificacion');
    $adm->post('/seguridad/usuarios/baja', ['id_usuario' => $id])->seguir();
    $r = ['activo' => (int) DB::scalar('SELECT activo FROM usuario WHERE id_usuario=?', [$id]),
          'citas_futuras' => $fut,
          'avisos' => (int) DB::scalar('SELECT COUNT(*) FROM notificacion') - $notA,
          'msg' => $adm->flashTxt()];
    // Sus citas futuras: ¿siguen ahí y siguen bloqueando la agenda?
    $r['citas_tras_baja'] = (int) DB::scalar('SELECT COUNT(*) FROM cita c JOIN estado_cita e ON e.id_estado_cita=c.id_estado_cita
                                               WHERE c.id_usuario=? AND e.bloquea_agenda=1 AND c.fecha_hora>NOW()', [$id]);
    sim_log(['tipo' => 'R2', 'caso' => 'H_USUARIO', 'r' => $r]);
    if ($fut > 0 && $r['avisos'] < $fut) {
        sim_incidente('R2_BAJA_SIN_AVISO', "Se dio de baja a un profesional con $fut cita(s) futura(s) y se crearon "
            . $r['avisos'] . ' aviso(s)', 'ALTO');
    }
    if ($r['citas_tras_baja'] > 0) {
        sim_log(['tipo' => 'R2', 'caso' => 'H_CITAS_HUERFANAS', 'n' => $r['citas_tras_baja'],
                 'det' => 'las citas del profesional dado de baja siguen en la agenda y hay que reasignarlas a mano']);
    }
    $adm->post('/seguridad/usuarios/baja', ['id_usuario' => $id])->seguir();
}

// =========================================================================
// F. Timbrado con rango de un solo número: agotarlo
// =========================================================================
$suc = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal');
$adm->post('/facturacion/timbrados/guardar', [
    'id_sucursal' => $suc, 'nro_timbrado' => '11223344', 'establecimiento' => '002', 'punto_expedicion' => '001',
    'nro_desde' => '1', 'nro_hasta' => '1', 'fecha_inicio' => $hoy,
    'fecha_fin' => date('Y-m-d', strtotime('+1 year')), 'id_tipo_comprobante' => 2, 'activo' => '1',
])->seguir();
$idt = (int) (DB::scalar("SELECT id_timbrado FROM timbrado WHERE nro_timbrado='11223344'") ?: 0);
sim_log(['tipo' => 'R2', 'caso' => 'F_TIMBRADO', 'creado' => $idt > 0, 'msg' => $adm->flashTxt()]);
if ($idt) {
    // Se activa el tipo 2 y se emiten dos comprobantes: el segundo tiene que fallar
    DB::update('UPDATE tipo_comprobante SET activo=1 WHERE id_tipo_comprobante=2');
    $citas = DB::select('SELECT c.id_cita FROM cita c WHERE c.id_estado_cita=4
                           AND NOT EXISTS (SELECT 1 FROM factura f WHERE f.id_cita=c.id_cita AND f.id_estado_factura=1)
                           AND EXISTS (SELECT 1 FROM cita_servicio cs WHERE cs.id_cita=c.id_cita) LIMIT 2');
    $res = [];
    foreach ($citas as $c) {
        $adm->post('/facturacion/emitir', ['id_cita' => (int) $c->id_cita, 'id_tipo_comprobante' => 2, 'id_condicion_venta' => 1])->seguir();
        $res[] = $adm->flashTxt();
    }
    sim_log(['tipo' => 'R2', 'caso' => 'F_AGOTADO', 'r' => $res,
             'emitidos' => (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_timbrado=?', [$idt])]);
    DB::update('UPDATE tipo_comprobante SET activo=0 WHERE id_tipo_comprobante=2');
    DB::update('UPDATE timbrado SET activo=0 WHERE id_timbrado=?', [$idt]);
}

// =========================================================================
// Nota de crédito: ¿mueve plata?
// =========================================================================
$nc = DB::selectOne('SELECT f.id_factura, f.id_factura_origen, fn_factura_total(f.id_factura) tot
                       FROM factura f WHERE f.id_tipo_comprobante=5 ORDER BY f.id_factura DESC LIMIT 1');
if ($nc) {
    $cajaId = (int) (DB::scalar('SELECT id_caja FROM caja WHERE id_estado_caja=1 ORDER BY id_caja DESC LIMIT 1') ?: 0);
    $cob = (int) DB::scalar('SELECT COUNT(*) FROM cobro WHERE id_factura=?', [(int) $nc->id_factura]);
    $mov = (int) DB::scalar('SELECT COUNT(*) FROM movimiento_caja');
    sim_log(['tipo' => 'R2', 'caso' => 'NC_MUEVE_PLATA', 'nota' => (int) $nc->id_factura, 'monto' => $nc->tot,
             'cobros_asociados' => $cob, 'movimientos_de_caja' => $mov]);
    if ($cob === 0 && $mov === 0) {
        sim_incidente('R2_NC_SIN_EGRESO',
            'La nota de crédito #' . $nc->id_factura . ' por ' . $nc->tot . ' Gs. no generó ningún movimiento de dinero: '
            . 'ni un cobro negativo, ni un egreso de caja, ni una anulación del cobro original. '
            . 'Al cliente se le devuelve la plata en el mostrador y el arqueo no se entera; '
            . 'los ingresos del informe tampoco bajan, porque salen de `cobro`', 'ALTO');
    }
}

$adm->salir();
sim_log(['tipo' => 'R2_FIN']);
echo "remate2 ok\n";
