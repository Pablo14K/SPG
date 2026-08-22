<?php
/**
 * Pertenencia: ver o tocar lo que es de otro.
 *
 * Los ids viajan en campos ocultos y en la URL, así que se pueden cambiar. Lo
 * que se busca acá es que el sistema decida por su cuenta —contra la sesión y
 * contra la sucursal activa— y no por lo que diga el formulario.
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

use Illuminate\Support\Facades\DB;

echo "\n=== 2. Pertenencia y aislamiento ===\n";

$sucs = DB::select('SELECT id_sucursal FROM sucursal WHERE activo = 1 ORDER BY id_sucursal LIMIT 3');
if (count($sucs) < 2) {
    echo "  (hace falta más de una sucursal)\n";

    return;
}
$sucA = (int) $sucs[0]->id_sucursal;
$sucB = (int) $sucs[1]->id_sucursal;

$n = new Nav();
$n->entrar('admin', 'admin123', $sucA);

// ---------------------------------------------------------------------
//  Una cita de OTRA sucursal
// ---------------------------------------------------------------------
$citaB = DB::selectOne(
    'SELECT id_cita, id_cliente, id_sucursal FROM cita WHERE id_sucursal <> ? ORDER BY id_cita DESC LIMIT 1',
    [$sucA]
);

if ($citaB) {
    // ¿La agenda de mi sucursal la muestra?
    $dia = (string) DB::scalar('SELECT DATE(fecha_hora) FROM cita WHERE id_cita = ?', [(int) $citaB->id_cita]);
    $n->get('/citas/agenda', ['dia' => $dia]);
    $html = $n->html();
    $cli = (string) DB::scalar(
        'SELECT CONCAT(pe.nombre, " ", pe.apellido) FROM cita c
           JOIN cliente cl ON cl.id_cliente = c.id_cliente
           JOIN persona pe ON pe.id_persona = cl.id_persona WHERE c.id_cita = ?', [(int) $citaB->id_cita]
    );
    if ($cli !== '' && str_contains($html, $cli) && str_contains($html, 'id_cita" value="' . $citaB->id_cita . '"')) {
        hallazgo('ALTO', 'agenda · cita de otra sucursal', 'aparece «' . $cli . '» estando en la sucursal ' . $sucA);
    } else {
        ok('agenda · cita de otra sucursal', 'no aparece');
    }

    // ¿Se puede cambiarle el estado desde acá?
    $antes = (int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita = ?', [(int) $citaB->id_cita]);
    $n->post('/citas/estado', ['id_cita' => $citaB->id_cita, 'id_estado_cita' => 6, 'dia' => $dia]);
    revisar($n, 'estado · cita de otra sucursal');
    $ahora = (int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita = ?', [(int) $citaB->id_cita]);
    if ($ahora !== $antes) {
        hallazgo('ALTO', 'estado · cita de otra sucursal',
            'se le cambió el estado de ' . $antes . ' a ' . $ahora . ' desde otro local');
    } else {
        ok('estado · cita de otra sucursal', 'no se pudo');
    }

    // ¿Se puede registrar la atención?
    $n->get('/citas/atender', ['id' => $citaB->id_cita]);
    revisar($n, 'atender · cita de otra sucursal');
    if ($n->codigo() === 200) {
        hallazgo('MEDIO', 'atender · cita de otra sucursal', 'la pantalla se abre desde otro local');
    } else {
        ok('atender · cita de otra sucursal', 'HTTP ' . $n->codigo());
    }
}

// ---------------------------------------------------------------------
//  La clienta y las citas de OTRA clienta, desde el portal
// ---------------------------------------------------------------------
$clientes = DB::select(
    'SELECT c.id_cliente, u.username FROM cliente c
       JOIN persona pe ON pe.id_persona = c.id_persona
       JOIN usuario u ON u.id_persona = pe.id_persona
      WHERE u.activo = 1 LIMIT 2'
);

if (count($clientes) >= 1) {
    $otraCita = DB::selectOne(
        'SELECT id_cita, id_cliente FROM cita WHERE id_cliente <> ? ORDER BY id_cita DESC LIMIT 1',
        [(int) $clientes[0]->id_cliente]
    );

    $p = new Nav();
    $entro = false;
    foreach ($clientes as $cl) {
        $p = new Nav();
        if ($p->entrar($cl->username, 'qa123456')) {
            $otraCita = DB::selectOne(
                'SELECT id_cita, id_cliente FROM cita WHERE id_cliente <> ? ORDER BY id_cita DESC LIMIT 1',
                [(int) $cl->id_cliente]
            );
            $entro = true;
            break;
        }
    }
    if (! $entro) { echo "  (ninguna clienta pudo entrar)
"; }

    if ($entro && $otraCita) {
        // Ver la atención de otra
        $p->get('/portal/atencion', ['id' => $otraCita->id_cita]);
        revisar($p, 'portal · atención de otra clienta');
        if ($p->codigo() === 200 && ! str_contains($p->html(), 'no es tuya')) {
            hallazgo('CRITICO', 'portal · atención de otra clienta', 'se ve el detalle de una cita ajena');
        } else {
            ok('portal · atención de otra clienta', 'rechazado');
        }

        // Cancelar la de otra
        $antes = (int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita = ?', [(int) $otraCita->id_cita]);
        $p->post('/portal/cancelar', ['id_cita' => $otraCita->id_cita]);
        revisar($p, 'portal · cancelar cita ajena');
        $ahora = (int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita = ?', [(int) $otraCita->id_cita]);
        if ($ahora !== $antes) {
            hallazgo('CRITICO', 'portal · cancelar cita ajena', 'se canceló una cita que no es suya');
        } else {
            ok('portal · cancelar cita ajena', 'rechazado');
        }

        // Registrar una seña sobre la cita de otra
        $c1 = (int) DB::scalar('SELECT COUNT(*) FROM sena_solicitud');
        $p->post('/portal/sena', ['id_cita' => $otraCita->id_cita, 'monto' => '50.000']);
        revisar($p, 'portal · seña sobre cita ajena');
        if ((int) DB::scalar('SELECT COUNT(*) FROM sena_solicitud') > $c1) {
            $de = (int) DB::scalar('SELECT id_cita FROM sena_solicitud ORDER BY id_solicitud DESC LIMIT 1');
            if ($de === (int) $otraCita->id_cita) {
                hallazgo('CRITICO', 'portal · seña sobre cita ajena', 'se registró contra la cita de otra persona');
            }
        } else {
            ok('portal · seña sobre cita ajena', 'rechazado');
        }

        // Pedir algo en la atención de otra
        $p->post('/portal/pedir', ['id_cita' => $otraCita->id_cita, 'pedido' => 'algo']);
        revisar($p, 'portal · pedido sobre cita ajena');
        $ped = (int) DB::scalar('SELECT COUNT(*) FROM cita_pedido WHERE id_cita = ?', [(int) $otraCita->id_cita]);
        if ($ped > 0) {
            hallazgo('ALTO', 'portal · pedido sobre cita ajena', 'quedó un pedido en la cita de otra');
        } else {
            ok('portal · pedido sobre cita ajena', 'rechazado');
        }
    }
}

// ---------------------------------------------------------------------
//  El Profesional y lo que no es suyo
// ---------------------------------------------------------------------
$prof = DB::selectOne(
    "SELECT u.username FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
      WHERE r.nombre = 'Profesional' AND u.activo = 1 LIMIT 1"
);
if ($prof) {
    foreach (['qa123456', 'profesional123'] as $clave) {
        $q = new Nav();
        if ($q->entrar($prof->username, $clave, $sucA)) {
            foreach ([
                '/facturacion/timbrados' => 'timbrados',
                '/seguridad/roles' => 'roles',
                '/seguridad/auditoria' => 'auditoría',
                '/servicios' => 'servicios',
            ] as $uri => $que) {
                $q->get($uri);
                if ($q->codigo() === 200) {
                    hallazgo('ALTO', 'profesional · ' . $que, 'entra a una pantalla que no es de su rol');
                } elseif ($q->codigo() >= 500) {
                    hallazgo('CRITICO', 'profesional · ' . $que, 'HTTP ' . $q->codigo());
                } else {
                    ok('profesional · ' . $que, 'HTTP ' . $q->codigo());
                }
            }
            break;
        }
    }
}
