<?php
/**
 * Verificación dirigida de los puntos dudosos que dejó la auditoría final.
 * No inventa nada: cada afirmación del informe tiene que salir de acá.
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

use Illuminate\Support\Facades\DB;

$V = [];

// =========================================================================
// 1. ¿El panel le filtra los números al Profesional, o es que TIENE el permiso?
// =========================================================================
$V['perm_profesional'] = array_map(fn ($r) => $r->modulo,
    DB::select('SELECT modulo FROM rol_modulo WHERE id_rol = 2 ORDER BY modulo'));
$V['profesional_tiene_cobros'] = in_array('facturacion.cobros', $V['perm_profesional'], true);
$V['profesional_tiene_caja'] = in_array('facturacion.caja', $V['perm_profesional'], true);

// ¿Puede de verdad operar la caja del salón?
$m = new Nav();
if ($m->entrar('marta', 'profesional123')) {
    $m->get('/facturacion/caja');
    $V['profesional_abre_pantalla_caja'] = $m->status;

    $abierta = DB::selectOne('SELECT id_caja FROM caja WHERE id_estado_caja = 1 LIMIT 1');
    $V['habia_caja_abierta'] = $abierta ? (int) $abierta->id_caja : null;
    if ($abierta) {
        $V['profesional_ve_saldo'] = str_contains($m->body, 'Saldo') || str_contains($m->body, 'saldo');
    } else {
        $antes = (int) DB::scalar('SELECT COUNT(*) FROM caja');
        $m->post('/facturacion/caja/abrir', ['monto_inicial' => '100.000'])->seguir();
        $desp = (int) DB::scalar('SELECT COUNT(*) FROM caja');
        $V['profesional_abrio_caja'] = $desp > $antes;
        $V['profesional_abrir_caja_msg'] = $m->flashTxt();
        if ($desp > $antes) {
            $nueva = (int) DB::scalar('SELECT MAX(id_caja) FROM caja');
            $m->post('/facturacion/caja/cerrar', ['id_caja' => $nueva])->seguir();
            $V['profesional_cerro_caja'] = (int) DB::scalar('SELECT id_estado_caja FROM caja WHERE id_caja = ?', [$nueva]) === 2;
        }
    }

    // Lo que el panel le dibuja de verdad
    $m->get('/panel');
    preg_match_all('#<div class="lbl">(.*?)</div>\s*<div class="val[^"]*">(.*?)</div>#s', $m->body, $mm, PREG_SET_ORDER);
    $V['panel_profesional'] = array_map(fn ($x) => trim(strip_tags($x[1])) . ' = ' . trim(strip_tags($x[2])), $mm);
    $m->salir();
}

// Y lo que ve el Administrador, para comparar
$a = new Nav();
if ($a->entrar('admin', 'admin123')) {
    $a->get('/panel');
    preg_match_all('#<div class="lbl">(.*?)</div>\s*<div class="val[^"]*">(.*?)</div>#s', $a->body, $mm, PREG_SET_ORDER);
    $V['panel_admin'] = array_map(fn ($x) => trim(strip_tags($x[1])) . ' = ' . trim(strip_tags($x[2])), $mm);
    $a->salir();
}

// =========================================================================
// 2. Auditoría de anulaciones: los DOS vocabularios
// =========================================================================
$V['cobros_anulados'] = (int) DB::scalar('SELECT COUNT(*) FROM cobro WHERE id_estado_cobro = 3');
$V['auditoria_cobro_ANULACION'] = (int) DB::scalar("SELECT COUNT(*) FROM auditoria WHERE tabla_afectada='cobro' AND accion='ANULACION'");
$V['auditoria_cobro_ANULAR'] = (int) DB::scalar("SELECT COUNT(*) FROM auditoria WHERE tabla_afectada='cobro' AND accion='ANULAR'");
$V['auditoria_cobro_cualquiera'] = (int) DB::scalar("SELECT COUNT(*) FROM auditoria WHERE tabla_afectada='cobro' AND accion IN ('ANULACION','ANULAR')");

// =========================================================================
// 3. El canje: se entrega y no se puede usar si la clienta no tiene portal
// =========================================================================
$V['canjes'] = DB::select(
    "SELECT cj.id_canje, cj.id_cliente, cj.id_cita, cj.vence_en,
            (cl.id_usuario IS NOT NULL) AS tiene_cuenta,
            fn_canje_estado(cj.id_canje) AS estado
       FROM canje cj JOIN cliente cl ON cl.id_cliente = cj.id_cliente"
);
$V['canjes_de_clienta_sin_cuenta'] = (int) DB::scalar(
    'SELECT COUNT(*) FROM canje cj JOIN cliente cl ON cl.id_cliente = cj.id_cliente WHERE cl.id_usuario IS NULL');
$V['canjes_usados'] = (int) DB::scalar('SELECT COUNT(*) FROM canje WHERE id_cita IS NOT NULL');

// ¿El alta de cita del MOSTRADOR acepta canjes? Se mira el formulario.
$r = new Nav();
if ($r->entrar('recepcion', 'recepcion123')) {
    $conCanje = DB::selectOne(
        'SELECT cj.id_cliente FROM canje cj WHERE cj.id_cita IS NULL AND cj.vence_en >= CURDATE() LIMIT 1');
    $r->get('/citas/nueva', $conCanje ? ['cliente' => (int) $conCanje->id_cliente] : []);
    $V['form_mostrador_nombra_canje'] = (bool) preg_match('/canje/i', $r->body);
    $V['form_mostrador_campo_canjes'] = (bool) preg_match('/name="canjes\[\]"/', $r->body);
    $V['form_mostrador_status'] = $r->status;

    // Y el portal, para comparar
    $r->salir();
}
$V['portal_campo_canjes'] = (bool) preg_match('/name="canjes\[\]"/',
    file_get_contents(SIM_ROOT . '/resources/views/portal/reservar.blade.php'));
$V['mostrador_campo_canjes_en_vista'] = (bool) preg_match('/name="canjes\[\]"/',
    file_get_contents(SIM_ROOT . '/resources/views/citas/form.blade.php'));

// =========================================================================
// 4. Calificaciones y pedidos: cero filas, ¿por qué?
// =========================================================================
$V['calificaciones'] = (int) DB::scalar('SELECT COUNT(*) FROM calificacion');
$V['pedidos'] = (int) DB::scalar('SELECT COUNT(*) FROM cita_pedido');
$V['citas_atendidas_de_clientes_con_cuenta'] = (int) DB::scalar(
    'SELECT COUNT(*) FROM cita c JOIN cliente cl ON cl.id_cliente = c.id_cliente
      WHERE c.id_estado_cita = 4 AND cl.id_usuario IS NOT NULL');
$V['clientes_con_cuenta'] = (int) DB::scalar('SELECT COUNT(*) FROM cliente WHERE id_usuario IS NOT NULL');
$V['citas_en_proceso_alguna_vez'] = (int) DB::scalar(
    "SELECT COUNT(DISTINCT id_registro) FROM auditoria WHERE tabla_afectada='cita' AND detalle LIKE '%proceso%'");

// =========================================================================
// 5. Notificaciones FALLIDAS: ¿son las que no tienen a quién mandarse? (NO-02)
// =========================================================================
$V['notif_fallidas'] = (int) DB::scalar("SELECT COUNT(*) FROM notificacion WHERE estado='FALLIDA'");
$V['notif_fallidas_sin_cliente'] = (int) DB::scalar(
    "SELECT COUNT(*) FROM notificacion WHERE estado='FALLIDA' AND id_cliente IS NULL");
$V['notif_fallidas_con_cliente'] = (int) DB::scalar(
    "SELECT COUNT(*) FROM notificacion WHERE estado='FALLIDA' AND id_cliente IS NOT NULL");
$V['notif_fallidas_detalle'] = DB::select(
    "SELECT id_tipo_notificacion, canal, COUNT(*) n FROM notificacion WHERE estado='FALLIDA'
      GROUP BY id_tipo_notificacion, canal");
$V['notif_pendientes'] = (int) DB::scalar("SELECT COUNT(*) FROM notificacion WHERE estado='PENDIENTE'");

// =========================================================================
// 6. Señas: ¿alguna superó el valor de su cita? (FA-03)
// =========================================================================
$V['senas_sobre_el_valor'] = DB::select(
    'SELECT c.id_cita, SUM(co.monto) cobrado,
            (SELECT COALESCE(SUM(s.precio),0) FROM cita_servicio cs JOIN servicio s ON s.id_servicio=cs.id_servicio
              WHERE cs.id_cita=c.id_cita) valor
       FROM cita c JOIN cobro co ON co.id_cita=c.id_cita AND co.id_estado_cobro=1
      GROUP BY c.id_cita HAVING cobrado > valor + 0.01'
);

// =========================================================================
// 7. SIFEN: qué pasó con los comprobantes declarables
// =========================================================================
$V['sifen'] = DB::select("SELECT estado, COUNT(*) n FROM factura_electronica GROUP BY estado");
$V['facturas_declarables'] = (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_tipo_comprobante IN (1,5) AND id_estado_factura=1');

file_put_contents(SIM_LOG . '/verificacion.json', json_encode($V, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($V, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
