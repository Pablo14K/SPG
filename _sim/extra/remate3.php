<?php
/**
 * Tercera pasada: cada bloque abre y cierra su propia sesión, sin intercalar
 * dos usuarios a la vez (el banco de pruebas comparte el almacén de sesión
 * dentro de un mismo proceso PHP).
 */

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

$hoy = date('Y-m-d');

$como = function (string $u, string $p, callable $fn) {
    $n = new Nav();
    if (! $n->entrar($u, $p)) { return; }
    $fn($n);
    $n->salir();
};

// ---- Baja de un cliente ---------------------------------------------------
$como('admin', 'admin123', function (Nav $a) {
    $c1 = DB::selectOne('SELECT c.id_cliente FROM cliente c WHERE c.activo=1
                           AND EXISTS (SELECT 1 FROM cita x WHERE x.id_cliente=c.id_cliente
                                        AND x.id_estado_cita IN (1,2) AND x.fecha_hora > NOW()) LIMIT 1');
    if (! $c1) { return; }
    $id = (int) $c1->id_cliente;
    $citas = (int) DB::scalar('SELECT COUNT(*) FROM cita WHERE id_cliente=?', [$id]);
    $fac = (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_cliente=?', [$id]);
    $a->post('/clientes/baja', ['id_cliente' => $id])->seguir();
    $r = ['id' => $id, 'activo' => (int) DB::scalar('SELECT activo FROM cliente WHERE id_cliente=?', [$id]),
          'citas' => $citas . '→' . (int) DB::scalar('SELECT COUNT(*) FROM cita WHERE id_cliente=?', [$id]),
          'facturas' => $fac . '→' . (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_cliente=?', [$id]),
          'msg' => $a->flashTxt()];
    $a->post('/citas/guardar', ['id_cliente' => $id, 'servicios' => [15],
        'fecha_hora' => date('Y-m-d', strtotime('next tuesday')) . ' 09:00'])->seguir();
    $r['agenda_estando_inactivo'] = $a->dice('Cita agendada') ? 'SÍ' : 'no';
    $r['msg_agenda'] = $a->flashTxt();
    $a->post('/clientes/baja', ['id_cliente' => $id])->seguir();
    sim_log(['tipo' => 'R3', 'caso' => 'BAJA_CLIENTE', 'r' => $r]);
    if ($r['activo'] !== 0) {
        sim_incidente('R3_BAJA_CLIENTE', 'La baja del cliente no aplicó: ' . $r['msg'], 'MEDIO');
    }
});

// ---- Baja de un profesional con citas futuras -----------------------------
$como('admin', 'admin123', function (Nav $a) {
    $k = DB::selectOne("SELECT id_usuario FROM usuario WHERE username='karen' AND activo=1");
    if (! $k) { return; }
    $id = (int) $k->id_usuario;
    $fut = (int) DB::scalar('SELECT COUNT(*) FROM cita c JOIN estado_cita e ON e.id_estado_cita=c.id_estado_cita
                              WHERE c.id_usuario=? AND e.bloquea_agenda=1 AND c.fecha_hora>NOW()', [$id]);
    $notA = (int) DB::scalar('SELECT COUNT(*) FROM notificacion');
    $a->post('/seguridad/usuarios/baja', ['id_usuario' => $id])->seguir();
    $r = ['activo' => (int) DB::scalar('SELECT activo FROM usuario WHERE id_usuario=?', [$id]),
          'citas_futuras' => $fut,
          'avisos_creados' => (int) DB::scalar('SELECT COUNT(*) FROM notificacion') - $notA,
          'citas_que_quedan' => (int) DB::scalar('SELECT COUNT(*) FROM cita c JOIN estado_cita e ON e.id_estado_cita=c.id_estado_cita
                                                   WHERE c.id_usuario=? AND e.bloquea_agenda=1 AND c.fecha_hora>NOW()', [$id]),
          'msg' => $a->flashTxt()];
    sim_log(['tipo' => 'R3', 'caso' => 'BAJA_PROFESIONAL', 'r' => $r]);
    if ($fut > 0 && $r['avisos_creados'] < $fut) {
        sim_incidente('R3_BAJA_SIN_AVISO', "Baja de un profesional con $fut cita(s) futura(s): se crearon "
            . $r['avisos_creados'] . ' aviso(s)', 'ALTO');
    }
    $a->post('/seguridad/usuarios/baja', ['id_usuario' => $id])->seguir();
});

// ---- Timbrado de un solo número: agotarlo ---------------------------------
$como('admin', 'admin123', function (Nav $a) use ($hoy) {
    $suc = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal');
    $a->post('/facturacion/timbrados/guardar', [
        'id_sucursal' => $suc, 'nro_timbrado' => '11223344', 'establecimiento' => '002', 'punto_expedicion' => '001',
        'nro_desde' => '1', 'nro_hasta' => '1', 'fecha_inicio' => $hoy,
        'fecha_fin' => date('Y-m-d', strtotime('+1 year')), 'id_tipo_comprobante' => 2, 'activo' => '1',
    ])->seguir();
    $idt = (int) (DB::scalar("SELECT id_timbrado FROM timbrado WHERE nro_timbrado='11223344'") ?: 0);
    $r = ['creado' => $idt, 'msg' => $a->flashTxt()];
    if ($idt) {
        DB::update('UPDATE tipo_comprobante SET activo=1 WHERE id_tipo_comprobante=2');
        $citas = DB::select('SELECT c.id_cita FROM cita c WHERE c.id_estado_cita=4
                               AND NOT EXISTS (SELECT 1 FROM factura f WHERE f.id_cita=c.id_cita AND f.id_estado_factura=1)
                               AND EXISTS (SELECT 1 FROM cita_servicio cs WHERE cs.id_cita=c.id_cita) LIMIT 2');
        foreach ($citas as $i => $c) {
            $a->post('/facturacion/emitir', ['id_cita' => (int) $c->id_cita, 'id_tipo_comprobante' => 2, 'id_condicion_venta' => 1])->seguir();
            $r['emision_' . ($i + 1)] = $a->flashTxt();
        }
        $r['emitidos'] = (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_timbrado=?', [$idt]);
        DB::update('UPDATE tipo_comprobante SET activo=0 WHERE id_tipo_comprobante=2');
        DB::update('UPDATE timbrado SET activo=0 WHERE id_timbrado=?', [$idt]);
    }
    sim_log(['tipo' => 'R3', 'caso' => 'TIMBRADO_AGOTADO', 'r' => $r]);
});

// ---- Atender la cita del portal para poder calificarla ---------------------
$idCitaPortal = (int) (DB::scalar("SELECT c.id_cita FROM cita c
                                     JOIN cliente cl ON cl.id_cliente=c.id_cliente
                                    WHERE cl.id_usuario IS NOT NULL AND c.id_estado_cita IN (1,2,5)
                                    ORDER BY c.id_cita DESC LIMIT 1") ?: 0);
if ($idCitaPortal) {
    $prof = (int) DB::scalar('SELECT id_usuario FROM cita WHERE id_cita=?', [$idCitaPortal]);
    $tur = (int) (DB::scalar('SELECT ut.id_turno FROM usuario_turno ut
                               JOIN turno_laboral t ON t.id_turno=ut.id_turno
                               JOIN turno_dia td ON td.id_turno=t.id_turno AND td.dia_semana=?
                              WHERE ut.id_usuario=? LIMIT 1', [(int) date('N'), $prof]) ?: 0);
    if ($tur) {
        $como('recepcion', 'recepcion123', function (Nav $r) use ($tur, $prof, $hoy) {
            $r->post('/seguridad/asistencia', ['accion' => 'entrada', 'id_turno' => $tur,
                'id_usuario' => $prof, 'fecha' => $hoy])->seguir();
        });
    }
    DB::update('UPDATE cita SET fecha_hora = DATE_SUB(NOW(), INTERVAL 20 MINUTE) WHERE id_cita=?', [$idCitaPortal]);
    $como('admin', 'admin123', function (Nav $a) use ($idCitaPortal, $hoy) {
        $srv = array_map(fn ($r) => (int) $r->id_servicio,
            DB::select('SELECT id_servicio FROM cita_servicio WHERE id_cita=?', [$idCitaPortal]));
        $a->post('/citas/atender', ['id_cita' => $idCitaPortal, 'servicios' => $srv ?: [15], 'dia' => $hoy])->seguir();
        sim_log(['tipo' => 'R3', 'caso' => 'ATENDER_PORTAL', 'cita' => $idCitaPortal,
                 'estado' => (int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita=?', [$idCitaPortal]),
                 'msg' => $a->flashTxt()]);
    });

    $u = DB::selectOne('SELECT us.username FROM cita c JOIN cliente cl ON cl.id_cliente=c.id_cliente
                          JOIN usuario us ON us.id_usuario=cl.id_usuario WHERE c.id_cita=?', [$idCitaPortal]);
    if ($u && (int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita=?', [$idCitaPortal]) === 4) {
        $pw = $u->username === 'cliente' ? 'cliente123' : 'clienta123';
        $como((string) $u->username, $pw, function (Nav $c) use ($idCitaPortal) {
            $c->get('/portal/valoraciones');
            $c->post('/portal/calificar', ['id_cita' => $idCitaPortal, 'puntaje' => '5', 'comentario' => 'Excelente atención'])->seguir();
            $n1 = (int) DB::scalar('SELECT COUNT(*) FROM calificacion WHERE id_cita=?', [$idCitaPortal]);
            $m1 = $c->flashTxt();
            $c->post('/portal/calificar', ['id_cita' => $idCitaPortal, 'puntaje' => '1'])->seguir();
            $n2 = (int) DB::scalar('SELECT COUNT(*) FROM calificacion WHERE id_cita=?', [$idCitaPortal]);
            $c->post('/portal/calificar', ['id_cita' => $idCitaPortal, 'puntaje' => '9'])->seguir();
            sim_log(['tipo' => 'R3', 'caso' => 'CALIFICAR', 'cita' => $idCitaPortal, 'primera' => $n1,
                     'tras_segunda' => $n2, 'max_global' => (int) DB::scalar('SELECT COALESCE(MAX(puntaje),0) FROM calificacion'),
                     'msg1' => $m1, 'msg_final' => $c->flashTxt()]);
            if ($n1 === 0) {
                sim_incidente('R3_CALIFICACION_NO_SALE', 'No se pudo calificar una cita atendida propia: ' . $m1, 'ALTO');
            }
            if ($n2 > 1) {
                sim_incidente('R3_DOBLE_CALIFICACION', 'La misma cita quedó calificada dos veces', 'MEDIO');
            }
        });
    }
}

// ---- ¿La nota de crédito mueve plata? -------------------------------------
$nc = DB::selectOne('SELECT f.id_factura, fn_factura_total(f.id_factura) tot FROM factura f
                      WHERE f.id_tipo_comprobante=5 ORDER BY f.id_factura DESC LIMIT 1');
if ($nc) {
    $r = ['nota' => (int) $nc->id_factura, 'monto' => $nc->tot,
          'cobros_de_la_nota' => (int) DB::scalar('SELECT COUNT(*) FROM cobro WHERE id_factura=?', [(int) $nc->id_factura]),
          'movimientos_de_caja' => (int) DB::scalar('SELECT COUNT(*) FROM movimiento_caja'),
          'saldo_caja_hoy' => DB::scalar('SELECT COALESCE(fn_caja_saldo(MAX(id_caja)),0) FROM caja WHERE id_estado_caja=1')];
    sim_log(['tipo' => 'R3', 'caso' => 'NC_MUEVE_PLATA', 'r' => $r]);
    if ($r['cobros_de_la_nota'] === 0 && $r['movimientos_de_caja'] === 0) {
        sim_incidente('R3_NC_SIN_EGRESO',
            'La nota de crédito #' . $nc->id_factura . ' por Gs. ' . $nc->tot . ' no generó ningún movimiento de dinero: '
            . 'ni cobro negativo, ni egreso de caja, ni anulación del cobro original. La devolución se hace en el '
            . 'mostrador y el arqueo no se entera; los ingresos del informe tampoco bajan, porque salen de `cobro`', 'ALTO');
    }
}

sim_log(['tipo' => 'R3_FIN']);
echo "remate3 ok\n";
