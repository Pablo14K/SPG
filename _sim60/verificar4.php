<?php
/**
 * Comprueba los tres cambios de la 7.29.0 sobre la base de la simulación:
 * el permiso de caja del Profesional, el movimiento manual y el aviso interno.
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

use App\Servicios\Notificaciones;
use Illuminate\Support\Facades\DB;

$V = [];
$ok = function (string $cod, bool $cond, string $det) use (&$V) {
    $V[$cod] = ($cond ? 'PASS' : 'FAIL') . ' — ' . $det;
    echo ($cond ? '  OK    ' : '  FALLA ') . $cod . ' :: ' . $det . PHP_EOL;
};

// =========================================================================
// 1. El Profesional ya no administra la caja, pero sigue cobrando
// =========================================================================
echo '== El Profesional y la caja ==' . PHP_EOL;

$p = new Nav();
if ($p->entrar('marta', 'profesional123')) {
    $p->get('/facturacion/caja');
    $ok('PROF_SIN_CAJA', $p->status === 403, 'Tesorería → Caja contesta HTTP ' . $p->status);

    $p->get('/facturacion/cobros');
    $ok('PROF_COBRA', $p->status === 200, 'Tesorería → Cobros contesta HTTP ' . $p->status);

    $p->get('/facturacion/facturas');
    $ok('PROF_FACTURA', $p->status === 200, 'Tesorería → Facturas contesta HTTP ' . $p->status);

    // Y el POST directo tampoco: esconder el botón no es el control
    $p->post('/facturacion/caja/movimiento', ['tipo' => 'EGRESO', 'monto' => '10000', 'concepto' => 'colado']);
    $ok('PROF_SIN_MOVIMIENTO', $p->status === 403, 'El POST del movimiento contesta HTTP ' . $p->status);
    $p->salir();
}

// =========================================================================
// 2. El movimiento manual mueve el arqueo, y no deja el cajón en negativo
// =========================================================================
echo PHP_EOL . '== Movimiento manual de caja ==' . PHP_EOL;

$a = new Nav();
if ($a->entrar('admin', 'admin123')) {
    if (! DB::selectOne('SELECT id_caja FROM caja WHERE id_estado_caja = 1 LIMIT 1')) {
        $a->post('/facturacion/caja/abrir', ['monto_inicial' => '300.000'])->seguir();
    }
    $caja = DB::selectOne('SELECT id_caja FROM caja WHERE id_estado_caja = 1 LIMIT 1');
    $id = (int) $caja->id_caja;
    $antes = (float) DB::scalar('SELECT fn_caja_saldo(?)', [$id]);

    $a->post('/facturacion/caja/movimiento',
        ['tipo' => 'EGRESO', 'monto' => '45.000', 'concepto' => 'Delivery del almuerzo'])->seguir();
    $tras = (float) DB::scalar('SELECT fn_caja_saldo(?)', [$id]);
    $ok('MOV_EGRESO', abs(($antes - 45000) - $tras) < 0.01,
        'El cajón pasó de ' . $antes . ' a ' . $tras);

    $a->post('/facturacion/caja/movimiento',
        ['tipo' => 'INGRESO', 'monto' => '20.000', 'concepto' => 'Plata para el cambio'])->seguir();
    $tras2 = (float) DB::scalar('SELECT fn_caja_saldo(?)', [$id]);
    $ok('MOV_INGRESO', abs(($tras + 20000) - $tras2) < 0.01, 'Y subió a ' . $tras2);

    // Los absurdos
    foreach ([
        ['EGRESO', '0', 'monto cero', 'MOV_CERO'],
        ['EGRESO', '-5000', 'monto negativo', 'MOV_NEGATIVO'],
        ['REGALO', '1000', 'tipo inventado', 'MOV_TIPO_RARO'],
        ['EGRESO', '1000', '', 'MOV_SIN_CONCEPTO'],
    ] as [$tipo, $monto, $desc, $cod]) {
        $n0 = (int) DB::scalar('SELECT COUNT(*) FROM movimiento_caja WHERE id_caja = ?', [$id]);
        $a->post('/facturacion/caja/movimiento', ['tipo' => $tipo, 'monto' => $monto, 'concepto' => $desc])->seguir();
        $n1 = (int) DB::scalar('SELECT COUNT(*) FROM movimiento_caja WHERE id_caja = ?', [$id]);
        $ok($cod, $n1 === $n0, 'Rechazado (' . $desc . '): ' . substr($a->flashTxt(), 0, 90));
    }

    // Y el cajón no puede quedar en negativo
    $hay = (float) DB::scalar('SELECT fn_caja_saldo(?)', [$id]);
    $a->post('/facturacion/caja/movimiento',
        ['tipo' => 'EGRESO', 'monto' => (string) (int) ($hay + 500000), 'concepto' => 'Retiro imposible'])->seguir();
    $fin = (float) DB::scalar('SELECT fn_caja_saldo(?)', [$id]);
    $ok('MOV_NO_DEJA_NEGATIVO', $fin >= -0.01 && abs($fin - $hay) < 0.01,
        'Se intentó sacar más de lo que hay y el cajón quedó en ' . $fin);

    // Queda en la auditoría
    $aud = (int) DB::scalar("SELECT COUNT(*) FROM auditoria WHERE accion='MOVIMIENTO_CAJA'");
    $ok('MOV_AUDITADO', $aud >= 2, $aud . ' fila(s) de auditoría por los movimientos');
    $a->salir();
}

// =========================================================================
// 3. El aviso interno le llega al equipo que puede resolverlo
// =========================================================================
echo PHP_EOL . '== Aviso interno de stock ==' . PHP_EOL;

$prod = DB::selectOne('SELECT id_producto, nombre FROM producto WHERE activo = 1 LIMIT 1');
DB::insert(
    "INSERT INTO notificacion (id_tipo_notificacion, id_producto, canal, mensaje, estado, fecha_generacion)
     VALUES (5, ?, 'SISTEMA', ?, 'PENDIENTE', NOW())",
    [(int) $prod->id_producto, 'Comprobación: ' . $prod->nombre . ' llegó al mínimo.']
);
$idNotif = (int) DB::getPdo()->lastInsertId();

$r = Notificaciones::despachar();
$estado = (string) DB::scalar('SELECT estado FROM notificacion WHERE id_notificacion = ?', [$idNotif]);

$ok('AVISO_INTERNO_ENVIADO', $estado === 'ENVIADA',
    'El aviso interno quedó ' . $estado . ' (antes se cerraba como FALLIDA)');
$V['despacho'] = $r;

$quienes = DB::select(
    "SELECT DISTINCT r.nombre AS rol, pe.email FROM usuario u
       JOIN persona pe ON pe.id_persona = u.id_persona
       JOIN rol r ON r.id_rol = u.id_rol
       LEFT JOIN rol_modulo rm ON rm.id_rol = u.id_rol AND rm.modulo IN ('inventario.stock','inventario')
      WHERE u.activo = 1 AND r.es_personal = 1
        AND (rm.modulo IS NOT NULL OR u.id_rol = ?)
        AND pe.email IS NOT NULL AND pe.email <> ''",
    [(int) config('permisos.rol_admin', 1)]
);
$V['destinatarios'] = array_map(fn ($x) => $x->rol . ' <' . $x->email . '>', $quienes);
echo '  destinatarios: ' . implode(' | ', $V['destinatarios']) . PHP_EOL;

$roles = array_unique(array_map(fn ($x) => $x->rol, $quienes));
$ok('AVISO_A_LOS_QUE_CORRESPONDE',
    in_array('Administrador', $roles, true) && ! in_array('Profesional', $roles, true),
    'Roles que lo reciben: ' . implode(', ', $roles));

// Y en el registro de correo tiene que estar el envío
$log = @file_get_contents(SIM_ROOT . '/storage/logs/laravel.log') ?: '';
$ok('AVISO_SALIO_POR_CORREO',
    str_contains($log, 'Hay productos por reponer') || $r['internos_enviados'] > 0,
    'internos_enviados = ' . ($r['internos_enviados'] ?? 0));

file_put_contents(SIM_LOG . '/verificacion4.json', json_encode($V, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo PHP_EOL . 'guardado' . PHP_EOL;
