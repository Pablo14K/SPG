<?php
/** Segunda tanda de verificaciones dirigidas. */

declare(strict_types=1);

require __DIR__ . '/lib.php';

use Illuminate\Support\Facades\DB;

$V = [];

// 1. ¿La nota de crédito se puede declarar a mano?
$nc = DB::selectOne('SELECT id_factura FROM factura WHERE id_tipo_comprobante = 5 AND id_estado_factura = 1 LIMIT 1');
$V['nota_credito_probada'] = $nc ? (int) $nc->id_factura : null;
if ($nc) {
    $id = (int) $nc->id_factura;
    $V['nc_tenia_electronica'] = (int) DB::scalar('SELECT COUNT(*) FROM factura_electronica WHERE id_factura = ?', [$id]);

    $a = new Nav();
    if ($a->entrar('admin', 'admin123')) {
        // ¿La pantalla del comprobante ofrece el botón de declarar?
        $a->get('/facturacion/factura/ver', ['id' => $id]);
        $V['nc_pantalla_status'] = $a->status;
        $V['nc_pantalla_ofrece_declarar'] = (bool) preg_match('#sifen/enviar#', $a->body);

        // Se intenta declararla a mano
        $a->post('/facturacion/sifen/enviar', ['id_factura' => $id])->seguir();
        $V['nc_envio_msg'] = $a->flashTxt();
        $V['nc_quedo_electronica'] = (int) DB::scalar('SELECT COUNT(*) FROM factura_electronica WHERE id_factura = ?', [$id]);
        $V['nc_estado'] = DB::scalar('SELECT estado FROM factura_electronica WHERE id_factura = ?', [$id]);
        $a->salir();
    }
}

// 2. ¿Y la factura común sí se declara sola?
$V['facturas_tipo1'] = (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_tipo_comprobante = 1 AND id_estado_factura = 1');
$V['facturas_tipo1_declaradas'] = (int) DB::scalar(
    'SELECT COUNT(*) FROM factura f JOIN factura_electronica fe ON fe.id_factura = f.id_factura
      WHERE f.id_tipo_comprobante = 1 AND f.id_estado_factura = 1');
$V['nc_total'] = (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_tipo_comprobante = 5 AND id_estado_factura = 1');
$V['nc_declaradas'] = (int) DB::scalar(
    'SELECT COUNT(*) FROM factura f JOIN factura_electronica fe ON fe.id_factura = f.id_factura
      WHERE f.id_tipo_comprobante = 5 AND f.id_estado_factura = 1');

// 3. Canje: ¿hay alguna forma de usarlo sin cuenta del portal?
$V['rutas_que_aceptan_canjes'] = [];
foreach (['resources/views/portal/reservar.blade.php', 'resources/views/citas/form.blade.php'] as $vista) {
    $ruta = SIM_ROOT . '/' . $vista;
    if (is_file($ruta)) {
        $V['rutas_que_aceptan_canjes'][$vista] = (bool) preg_match('/name="canjes\[\]"/', file_get_contents($ruta));
    }
}
$V['citas_guardar_lee_canjes'] = (bool) preg_match('/canjes/',
    file_get_contents(SIM_ROOT . '/app/Http/Controllers/CitasController.php'));
$V['portal_guardar_lee_canjes'] = (bool) preg_match('/canjes/',
    file_get_contents(SIM_ROOT . '/app/Http/Controllers/PortalController.php'));

// Prueba real: se le manda canjes[] al alta del mostrador y se mira si lo ata
$disp = DB::selectOne(
    "SELECT cj.id_canje, cj.id_cliente, cj.id_servicio FROM canje cj
       JOIN cliente cl ON cl.id_cliente = cj.id_cliente
      WHERE cj.id_cita IS NULL AND cj.vence_en >= CURDATE() AND cl.id_usuario IS NULL LIMIT 1");
$V['canje_probado'] = $disp ? (int) $disp->id_canje : null;
if ($disp) {
    $r = new Nav();
    if ($r->entrar('recepcion', 'recepcion123')) {
        $r->get('/citas/disponibilidad', ['servicios' => [(int) $disp->id_servicio]]);
        $j = json_decode($r->body, true);
        $dias = array_values(array_filter($j['dias'] ?? [], fn ($d) => $d > date('Y-m-d')));
        if ($dias) {
            $f = $dias[0];
            $r->get('/citas/disponibilidad', ['servicios' => [(int) $disp->id_servicio], 'fecha' => $f]);
            $j2 = json_decode($r->body, true);
            if (! empty($j2['horas'])) {
                $h = $j2['horas'][0]['hora'];
                $r->post('/citas/guardar', [
                    'id_cliente' => (int) $disp->id_cliente,
                    'servicios' => [(int) $disp->id_servicio],
                    'fecha_hora' => $f . ' ' . $h,
                    'canjes' => [(int) $disp->id_canje],
                ])->seguir();
                $V['mostrador_agendo'] = $r->dice('Cita agendada');
                $V['mostrador_ato_el_canje'] = DB::scalar('SELECT id_cita FROM canje WHERE id_canje = ?', [(int) $disp->id_canje]) !== null;
                $V['mostrador_msg'] = $r->flashTxt();
            }
        }
        $r->salir();
    }
}

// 4. El Profesional y la caja del salón: ¿qué puede hacer exactamente?
$V['profesional_caja'] = [];
$p = new Nav();
if ($p->entrar('marta', 'profesional123')) {
    foreach ([
        '/facturacion/caja' => 'ver la caja',
        '/facturacion/cobros' => 'ver los cobros',
        '/facturacion/facturas' => 'ver los comprobantes',
        '/facturacion/pagos' => 'liquidar al personal',
        '/facturacion/proveedores' => 'pagar proveedores',
        '/facturacion/timbrados' => 'administrar timbrados',
    ] as $uri => $que) {
        $p->get($uri);
        $V['profesional_caja'][$que] = $p->status;
    }
    $p->salir();
}

file_put_contents(SIM_LOG . '/verificacion2.json', json_encode($V, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($V, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
