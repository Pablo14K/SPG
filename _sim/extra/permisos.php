<?php
/**
 * Sondeo de permisos: cada rol contra las pantallas que NO le corresponden.
 * Se ejecuta dentro de momento.php ($DIA, $HOY disponibles).
 */

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/** @var int $DIA */

/**
 * Bloqueo esperado. Los guardias del sistema contestan 403 (ExigeModulo /
 * ExigeAdmin) o redirigen al portal o al ingreso (ExigePersonal / ExigeSesion).
 * Cualquier otra cosa —sobre todo un 200— es un acceso indebido.
 */
$prohibido = function (Nav $n, string $m, string $uri, array $d, string $cod) {
    $n->req($m, $uri, $d);
    $st = $n->status;
    $loc = (string) ($n->location ?? '');
    $bloqueado = in_array($st, [401, 403], true)
        || ($st === 302 && (str_contains($loc, '/entrar') || str_contains($loc, '/portal')));

    if (! $bloqueado) {
        sim_incidente($cod, 'ACCESO INDEBIDO: ' . $n->quien . ' pudo ' . $m . ' ' . $uri
            . ' (HTTP ' . $st . ($loc ? ' → ' . $loc : '') . ')', $st === 200 ? 'CRITICO' : 'ALTO');
    } else {
        sim_log(['tipo' => 'PERM_OK', 'cod' => $cod, 'quien' => $n->quien, 'uri' => $uri, 'st' => $st]);
    }
};

// ---- 1. Sin sesión --------------------------------------------------------
$n = new Nav();
$n->quien = 'anonimo';
foreach ([['GET', '/panel'], ['GET', '/clientes/lista'], ['GET', '/facturacion/facturas'],
          ['GET', '/seguridad/usuarios'], ['GET', '/reportes'], ['POST', '/citas/guardar'],
          ['POST', '/facturacion/cobrar'], ['GET', '/portal'], ['GET', '/citas/disponibilidad?servicios[]=1']] as [$m, $u]) {
    $n->req($m, $u, []);
    if ($n->status === 200) {
        sim_incidente('ANON_ACCESO', 'Sin sesión se abrió ' . $m . ' ' . $u, 'CRITICO');
    }
}

// ---- 2. Profesional -------------------------------------------------------
$p = new Nav();
if ($p->entrar('marta', 'profesional123')) {
    foreach ([
        ['GET', '/facturacion/timbrados', 'PERM_PROF_TIMBRADOS'],
        ['GET', '/seguridad/roles', 'PERM_PROF_ROLES'],
        ['GET', '/seguridad/usuarios', 'PERM_PROF_USUARIOS'],
        ['GET', '/seguridad/auditoria', 'PERM_PROF_AUDITORIA'],
        ['GET', '/seguridad/sucursales', 'PERM_PROF_SUCURSALES'],
        ['GET', '/seguridad/turnos', 'PERM_PROF_TURNOS'],
        ['GET', '/seguridad/comisiones', 'PERM_PROF_COMISIONES'],
        ['GET', '/servicios/lista', 'PERM_PROF_SERVICIOS'],
        ['GET', '/servicios/descuentos', 'PERM_PROF_DESCUENTOS'],
        ['GET', '/inventario/productos', 'PERM_PROF_INVENTARIO'],
        ['GET', '/inventario/compras', 'PERM_PROF_COMPRAS'],
        ['GET', '/reportes', 'PERM_PROF_REPORTES'],
        ['GET', '/facturacion/pagos', 'PERM_PROF_PAGOS'],
        ['GET', '/facturacion/proveedores', 'PERM_PROF_PROVEEDORES'],
        ['GET', '/citas/excepciones', 'PERM_PROF_AUSENCIAS'],
        ['GET', '/seguridad/usuarios/form/0', 'PERM_PROF_USUARIO_FORM'],
    ] as [$m, $u, $c]) {
        $prohibido($p, $m, $u, [], $c);
    }

    // POST directos a acciones que no le tocan
    $prohibido($p, 'POST', '/servicios/guardar', ['nombre' => 'Hackeo', 'precio' => '1', 'duracion_min' => '10'], 'PERM_PROF_POST_SERVICIO');
    $prohibido($p, 'POST', '/seguridad/roles/permisos', ['id_rol' => 2, 'permisos' => ['seguridad.roles']], 'PERM_PROF_POST_PERMISOS');
    $prohibido($p, 'POST', '/facturacion/timbrados/guardar', ['nro_timbrado' => '99999999'], 'PERM_PROF_POST_TIMBRADO');

    // Agenda ajena: una cita de otra profesional
    $ajena = DB::selectOne('SELECT id_cita FROM cita WHERE id_usuario <> ? AND id_estado_cita IN (1,2) LIMIT 1', [$p->uid]);
    if ($ajena) {
        $p->post('/citas/estado', ['id_cita' => (int) $ajena->id_cita, 'id_estado_cita' => 6, 'dia' => date('Y-m-d')]);
        if ($p->status !== 403) {
            sim_incidente('PERM_CITA_AJENA', 'La profesional cambió el estado de la cita #' . $ajena->id_cita
                . ' de otra persona (HTTP ' . $p->status . ')', 'ALTO');
        }
        $p->get('/citas/atender', ['id' => (int) $ajena->id_cita]);
        if ($p->status === 200 && ! $p->dice('otro profesional')) {
            sim_log(['tipo' => 'PERM_NOTA', 'det' => 'atender cita ajena devolvió 200']);
        }
    }
    $p->salir();
}

// ---- 3. Asistente administrativo -----------------------------------------
$r = new Nav();
if ($r->entrar('recepcion', 'recepcion123')) {
    foreach ([
        ['GET', '/seguridad/usuarios/form/0', 'PERM_REC_USUARIO_FORM'],
        ['GET', '/seguridad/roles', 'PERM_REC_ROLES'],
        ['GET', '/seguridad/auditoria', 'PERM_REC_AUDITORIA'],
        ['GET', '/seguridad/sucursales', 'PERM_REC_SUCURSALES'],
        ['GET', '/seguridad/contacto', 'PERM_REC_CONTACTO'],
        ['GET', '/facturacion/timbrados', 'PERM_REC_TIMBRADOS'],
        ['GET', '/citas/excepciones', 'PERM_REC_AUSENCIAS'],
        ['GET', '/seguridad/usuarios', 'PERM_REC_USUARIOS'],
    ] as [$m, $u, $c]) {
        $prohibido($r, $m, $u, [], $c);
    }
    $prohibido($r, 'POST', '/seguridad/usuarios/guardar', ['id_rol' => 1, 'username' => 'colado',
        'nombre' => 'Colado', 'apellido' => 'Test', 'email' => 'colado@x.com', 'password' => 'colado123'], 'PERM_REC_CREA_ADMIN');
    $r->salir();
}

// ---- 4. Clienta -----------------------------------------------------------
$c = new Nav();
if ($c->entrar('cliente', 'cliente123')) {
    foreach ([
        ['GET', '/panel', 'PERM_CLI_PANEL'],
        ['GET', '/citas/agenda', 'PERM_CLI_AGENDA'],
        ['GET', '/clientes/lista', 'PERM_CLI_CLIENTES'],
        ['GET', '/facturacion/facturas', 'PERM_CLI_FACTURAS'],
        ['GET', '/seguridad/usuarios', 'PERM_CLI_USUARIOS'],
        ['GET', '/reportes', 'PERM_CLI_REPORTES'],
    ] as [$m, $u, $cod]) {
        $prohibido($c, $m, $u, [], $cod);
    }
    $prohibido($c, 'POST', '/citas/guardar', ['id_cliente' => 2, 'servicios' => [1],
        'fecha_hora' => date('Y-m-d', strtotime('+3 day')) . ' 10:00'], 'PERM_CLI_AGENDAR');
    $prohibido($c, 'POST', '/facturacion/caja/abrir', ['monto_inicial' => '1'], 'PERM_CLI_CAJA');

    // Cancelar la cita de OTRA clienta desde el portal (el id viene del formulario)
    $ajena = DB::selectOne('SELECT c.id_cita FROM cita c WHERE c.id_cliente <> 1 AND c.id_estado_cita IN (1,2) LIMIT 1');
    if ($ajena) {
        $c->post('/portal/cancelar', ['id_cita' => (int) $ajena->id_cita]);
        $c->seguir();
        $estado = (int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita = ?', [(int) $ajena->id_cita]);
        if ($estado === 3) {
            sim_incidente('IDOR_PORTAL_CANCELAR', 'Una clienta canceló desde el portal la cita #'
                . $ajena->id_cita . ', que es de otra persona', 'CRITICO');
        }
        // Y registrarle una seña
        $c->post('/portal/sena', ['id_cita' => (int) $ajena->id_cita, 'monto' => '50000']);
        $c->seguir();
        $hay = (int) DB::scalar('SELECT COUNT(*) FROM sena_solicitud WHERE id_cita = ?', [(int) $ajena->id_cita]);
        if ($hay) {
            sim_incidente('IDOR_PORTAL_SENA', 'Una clienta registró una seña sobre la cita #'
                . $ajena->id_cita . ', que es de otra persona', 'CRITICO');
        }
    }
    $c->salir();
}

// ---- 5. Credenciales incorrectas y cuenta inactiva -------------------------
$x = new Nav();
$x->quien = 'intruso';
$x->get('/entrar');
$x->post('/entrar', ['usuario' => 'admin', 'password' => 'admin124']);
$x->seguir();
if (! $x->dice('incorrectos')) {
    sim_incidente('LOGIN_MENSAJE', 'Contraseña incorrecta no devolvió el aviso esperado: ' . $x->flashTxt(), 'BAJO');
}
$x->post('/entrar', ['usuario' => 'noexiste_' . $DIA, 'password' => 'loquesea']);
if ($x->status >= 500) {
    sim_incidente('LOGIN_500', 'Usuario inexistente devolvió ' . $x->status, 'ALTO');
}
$x->post('/entrar', ['usuario' => '', 'password' => '']);

// ---- 6. Fuerza bruta: el limitador de peticiones ---------------------------
$b = new Nav();
$b->quien = 'fuerzabruta';
$b->get('/entrar');
$bloqueo = 0;
for ($i = 0; $i < 14; $i++) {
    $b->post('/entrar', ['usuario' => 'admin', 'password' => 'clave' . $i]);
    if ($b->status === 429) { $bloqueo = $i + 1; break; }
}
sim_log(['tipo' => 'THROTTLE', 'intentos_hasta_429' => $bloqueo]);
if ($bloqueo === 0) {
    sim_incidente('SIN_THROTTLE', '14 intentos seguidos de contraseña contra `admin` y ninguno fue bloqueado', 'ALTO');
}

sim_log(['tipo' => 'PERM_FIN', 'dia' => $DIA]);
