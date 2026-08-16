<?php
/**
 * Fidelización: puntos, catálogo de canjes y canje de verdad.
 *
 * Cubre lo que entró en 7.25.0, 7.26.0, 7.26.1 y 7.27.0, que en la simulación
 * de 90 días no existía todavía (era el hallazgo IN-03: los puntos se
 * acumulaban y no había con qué gastarlos).
 */

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/** @var int $DIA */

$hoy = date('Y-m-d');

/** Puntos de la clienta que más tiene, y a cuánto está del canje más barato. */
function topPuntos(): array
{
    $r = DB::selectOne(
        'SELECT c.id_cliente, fn_cliente_puntos(c.id_cliente) AS p
           FROM cliente c WHERE c.activo = 1
          ORDER BY p DESC LIMIT 1'
    );
    $barato = (int) (DB::scalar('SELECT MIN(puntos) FROM servicio_canjeable WHERE activo = 1') ?: 0);

    return [$r ? (int) $r->id_cliente : 0, $r ? (int) $r->p : 0, $barato];
}

// =========================================================================
// 1. ¿El programa de fidelización es alcanzable con la relación que se entrega?
// =========================================================================
[$idTop, $ptsTop, $barato] = topPuntos();
$relacion = (int) (DB::scalar('SELECT puntos_cada_gs FROM configuracion WHERE id_configuracion = 1') ?: 0);
$facturado = (float) DB::scalar('SELECT COALESCE(SUM(fn_factura_total(id_factura)),0) FROM factura WHERE id_estado_factura = 1');

sim_log(['tipo' => 'FIDELIZACION', 'dia' => $DIA, 'relacion' => $relacion,
         'top_cliente' => $idTop, 'top_puntos' => $ptsTop, 'canje_mas_barato' => $barato,
         'facturado_total' => $facturado]);

// =========================================================================
// 2. El salón agrega un canje al catálogo, y se prueban los rechazos
// =========================================================================
$a = new Nav();
if ($a->entrar('admin', 'admin123')) {
    $a->get('/clientes/canjes');

    if ($DIA === 9) {
        // Uno nuevo, barato, para que el programa se pueda usar de verdad
        $a->post('/clientes/canjes/guardar', ['id_servicio' => 15, 'puntos' => '400', 'dias_vigencia' => '60'])->seguir();
        sim_esperado($a, 'se puede canjear', 'CANJE_ALTA', 'Alta de un servicio canjeable', 'MEDIO');

        // Repetido: tiene que rechazarlo
        $antes = (int) DB::scalar('SELECT COUNT(*) FROM servicio_canjeable WHERE id_servicio = 15');
        $a->post('/clientes/canjes/guardar', ['id_servicio' => 15, 'puntos' => '500', 'dias_vigencia' => '30'])->seguir();
        $desp = (int) DB::scalar('SELECT COUNT(*) FROM servicio_canjeable WHERE id_servicio = 15');
        sim_check($desp === $antes, 'CANJE_DUPLICADO',
            "El mismo servicio entró $desp veces en el catálogo de canjes", 'MEDIO');

        // Absurdos
        foreach ([['0', '30'], ['-100', '30'], ['500', '0'], ['500', '900']] as [$p, $d]) {
            $a->post('/clientes/canjes/guardar', ['id_servicio' => 5, 'puntos' => $p, 'dias_vigencia' => $d])->seguir();
        }
        $malos = (int) DB::scalar('SELECT COUNT(*) FROM servicio_canjeable WHERE puntos <= 0 OR dias_vigencia < 1 OR dias_vigencia > 365');
        sim_check($malos === 0, 'CANJE_VALORES_ABSURDOS',
            "Quedaron $malos canje(s) con puntos <= 0 o vigencia fuera de 1..365", 'ALTO');
    }
    $a->salir();
}

// =========================================================================
// 3. La relación de puntos la decide el salón (7.27.0)
// =========================================================================
if ($DIA === 23) {
    $b = new Nav();
    if ($b->entrar('admin', 'admin123')) {
        // Los puntos ya acumulados NO se pueden recalcular al cambiar la relación
        $antesPuntos = (int) DB::scalar('SELECT COALESCE(SUM(puntos),0) FROM movimiento_punto');
        $antesRel = (int) DB::scalar('SELECT puntos_cada_gs FROM configuracion WHERE id_configuracion = 1');

        // Absurdos primero
        foreach (['0', '1', '-5000', '99999999'] as $v) {
            $b->post('/servicios/puntos', ['puntos_cada_gs' => $v])->seguir();
        }
        $rel = (int) DB::scalar('SELECT puntos_cada_gs FROM configuracion WHERE id_configuracion = 1');
        sim_check($rel === $antesRel, 'PUNTOS_VALOR_ABSURDO',
            "La relación de puntos aceptó un valor inválido: quedó en $rel", 'ALTO');

        // El salón decide que el programa no arranca y baja la relación
        $b->post('/servicios/puntos', ['puntos_cada_gs' => '1.000'])->seguir();
        $rel2 = (int) DB::scalar('SELECT puntos_cada_gs FROM configuracion WHERE id_configuracion = 1');
        sim_check($rel2 === 1000, 'PUNTOS_NO_GUARDA',
            "Se pidió 1 punto cada Gs. 1.000 y quedó en $rel2", 'ALTO');

        $despPuntos = (int) DB::scalar('SELECT COALESCE(SUM(puntos),0) FROM movimiento_punto');
        sim_check($antesPuntos === $despPuntos, 'PUNTOS_RECALCULADOS',
            "Cambiar la relación movió los puntos ya acumulados: $antesPuntos → $despPuntos", 'ALTO');

        sim_log(['tipo' => 'PUNTOS_CAMBIO', 'de' => $antesRel, 'a' => $rel2, 'acumulado' => $despPuntos]);
        $b->salir();
    }
}

// =========================================================================
// 4. La clienta canjea desde el portal, y el salón canjea desde el mostrador
// =========================================================================
[$idTop, $ptsTop, $barato] = topPuntos();

if ($idTop && $barato && $ptsTop >= $barato) {
    // --- 4a. Desde el mostrador (7.26.0) --------------------------------
    $srv = (int) DB::scalar('SELECT id_servicio FROM servicio_canjeable WHERE activo=1 ORDER BY puntos LIMIT 1');
    $r = new Nav();
    if ($r->entrar('recepcion', 'recepcion123')) {
        $antes = (int) DB::scalar('SELECT COUNT(*) FROM canje WHERE id_cliente = ?', [$idTop]);
        $ptsAntes = (int) DB::scalar('SELECT fn_cliente_puntos(?)', [$idTop]);
        $r->post('/clientes/canjear', ['id_cliente' => $idTop, 'id_servicio' => $srv])->seguir();
        $desp = (int) DB::scalar('SELECT COUNT(*) FROM canje WHERE id_cliente = ?', [$idTop]);
        $ptsDesp = (int) DB::scalar('SELECT fn_cliente_puntos(?)', [$idTop]);

        if ($desp > $antes) {
            $costo = (int) DB::scalar('SELECT puntos FROM servicio_canjeable WHERE id_servicio = ?', [$srv]);
            sim_check($ptsAntes - $ptsDesp === $costo, 'CANJE_PUNTOS_MAL_DESCONTADOS',
                "El canje costaba $costo puntos y descontó " . ($ptsAntes - $ptsDesp), 'ALTO');
            sim_log(['tipo' => 'CANJE_MOSTRADOR', 'cliente' => $idTop, 'servicio' => $srv,
                     'puntos' => $ptsAntes . '→' . $ptsDesp]);
        } else {
            sim_log(['tipo' => 'CANJE_MOSTRADOR_NO', 'msg' => $r->flashTxt()]);
        }
        $r->salir();
    }

    // --- 4b. Sin puntos suficientes: tiene que rechazarlo ----------------
    $pobre = DB::selectOne('SELECT id_cliente FROM cliente WHERE activo=1 AND fn_cliente_puntos(id_cliente) = 0 LIMIT 1');
    if ($pobre) {
        $r2 = new Nav();
        if ($r2->entrar('recepcion', 'recepcion123')) {
            $r2->post('/clientes/canjear', ['id_cliente' => (int) $pobre->id_cliente, 'id_servicio' => $srv])->seguir();
            $hay = (int) DB::scalar('SELECT COUNT(*) FROM canje WHERE id_cliente = ?', [(int) $pobre->id_cliente]);
            sim_check($hay === 0, 'CANJE_SIN_PUNTOS',
                'Una clienta con 0 puntos consiguió un canje', 'CRITICO');
            $r2->salir();
        }
    }
}

// =========================================================================
// 5. Un canje disponible se usa al reservar, y en el comprobante va a CERO
// =========================================================================
$disp = DB::selectOne(
    "SELECT cj.id_canje, cj.id_cliente, cj.id_servicio, cj.vence_en, cl.id_usuario
       FROM canje cj JOIN cliente cl ON cl.id_cliente = cj.id_cliente
      WHERE cj.id_cita IS NULL AND cj.vence_en >= CURDATE() LIMIT 1"
);

if ($disp) {
    // ¿Tiene cuenta en el portal? Es la única puerta que acepta `canjes[]`.
    if ($disp->id_usuario) {
        $usr = (string) DB::scalar('SELECT username FROM usuario WHERE id_usuario = ?', [(int) $disp->id_usuario]);
        $p = new Nav();
        // La contraseña de las cuentas del portal que crea la simulación
        foreach (['clienta123', 'cliente123'] as $pw) {
            if ($p->entrar($usr, $pw)) {
                break;
            }
        }
        if ($p->uid) {
            $p->get('/portal/disponibilidad', ['servicios' => [(int) $disp->id_servicio]]);
            $j = json_decode($p->body, true);
            $dias = array_values(array_filter($j['dias'] ?? [], fn ($d) => $d > $hoy));
            if ($dias) {
                $f = $dias[0];
                $p->get('/portal/disponibilidad', ['servicios' => [(int) $disp->id_servicio], 'fecha' => $f]);
                $j2 = json_decode($p->body, true);
                if (! empty($j2['horas'])) {
                    $h = $j2['horas'][0]['hora'];
                    $p->post('/portal/reservar', [
                        'servicios' => [(int) $disp->id_servicio],
                        'fecha_hora' => $f . ' ' . $h,
                        'canjes' => [(int) $disp->id_canje],
                    ])->seguir();

                    $atada = (int) DB::scalar('SELECT COALESCE(id_cita,0) FROM canje WHERE id_canje = ?', [(int) $disp->id_canje]);
                    sim_check($atada > 0, 'CANJE_NO_SE_ATA',
                        'Se reservó marcando el canje #' . $disp->id_canje . ' y quedó sin atarse a la cita', 'ALTO');
                    sim_log(['tipo' => 'CANJE_RESERVA', 'canje' => (int) $disp->id_canje, 'cita' => $atada]);
                }
            }
            $p->salir();
        }
    } else {
        // La clienta cargada en el mostrador NO tiene cuenta: se comprueba si
        // el sistema le da alguna forma de usar el canje que le acaban de dar.
        sim_log(['tipo' => 'CANJE_SIN_CUENTA', 'canje' => (int) $disp->id_canje, 'cliente' => (int) $disp->id_cliente]);
    }
}

// =========================================================================
// 6. El comprobante de una cita con canje: el servicio tiene que ir a CERO
// =========================================================================
$conCanje = DB::select(
    'SELECT DISTINCT c.id_cita, cj.id_servicio, cj.id_canje
       FROM canje cj JOIN cita c ON c.id_cita = cj.id_cita
       JOIN factura f ON f.id_cita = c.id_cita AND f.id_estado_factura = 1
      LIMIT 5'
);
foreach ($conCanje as $cc) {
    $linea = DB::selectOne(
        'SELECT df.precio_unitario, df.cantidad
           FROM detalle_factura df JOIN factura f ON f.id_factura = df.id_factura
          WHERE f.id_cita = ? AND df.id_servicio = ? LIMIT 1',
        [(int) $cc->id_cita, (int) $cc->id_servicio]
    );
    if (! $linea) {
        sim_incidente('CANJE_SERVICIO_OMITIDO',
            'La cita #' . $cc->id_cita . ' usó el canje #' . $cc->id_canje . ' y el servicio '
            . $cc->id_servicio . ' NO figura en el comprobante: la clienta se queda sin constancia', 'ALTO');
    } elseif ((float) $linea->precio_unitario > 0.01) {
        sim_incidente('CANJE_SERVICIO_COBRADO',
            'La cita #' . $cc->id_cita . ' usó un canje y el servicio ' . $cc->id_servicio
            . ' salió cobrado a ' . $linea->precio_unitario . ' en el comprobante', 'CRITICO');
    } else {
        sim_log(['tipo' => 'CANJE_OK_CERO', 'cita' => (int) $cc->id_cita]);
    }
}

// =========================================================================
// 7. Cancelar una cita con canje: vuelve el canje, NO los puntos
// =========================================================================
if ($DIA % 2 === 0) {
    $cn = DB::selectOne(
        'SELECT cj.id_canje, cj.id_cita, cj.id_cliente, cj.puntos
           FROM canje cj JOIN cita c ON c.id_cita = cj.id_cita
          WHERE c.id_estado_cita IN (1,2) AND c.fecha_hora > NOW() LIMIT 1'
    );
    if ($cn) {
        $ptsAntes = (int) DB::scalar('SELECT fn_cliente_puntos(?)', [(int) $cn->id_cliente]);
        $r = new Nav();
        if ($r->entrar('recepcion', 'recepcion123')) {
            $r->post('/citas/cancelar', ['id_cita' => (int) $cn->id_cita, 'dia' => $hoy])->seguir();
            $r->salir();
        }
        $suelto = DB::scalar('SELECT id_cita FROM canje WHERE id_canje = ?', [(int) $cn->id_canje]);
        $ptsDesp = (int) DB::scalar('SELECT fn_cliente_puntos(?)', [(int) $cn->id_cliente]);

        sim_check($suelto === null, 'CANJE_NO_VUELVE',
            'Se canceló la cita #' . $cn->id_cita . ' y el canje #' . $cn->id_canje . ' quedó atado a ella', 'ALTO');
        sim_check($ptsDesp === $ptsAntes, 'CANJE_DEVUELVE_PUNTOS',
            "Cancelar la cita devolvió también los puntos ($ptsAntes → $ptsDesp): la clienta se queda con el canje Y los puntos", 'ALTO');
        sim_log(['tipo' => 'CANJE_CANCELA', 'canje' => (int) $cn->id_canje, 'suelto' => $suelto === null]);
    }
}

// =========================================================================
// 8. Permisos: el Profesional canjea POR una clienta pero no arma el catálogo
// =========================================================================
if ($DIA === 9 || $DIA === 47) {
    $pr = new Nav();
    if ($pr->entrar('marta', PASS_PROF)) {
        $pr->get('/clientes/canjes');
        sim_check($pr->status === 403, 'PERM_CANJES_CATALOGO',
            'El Profesional entró al catálogo de canjes (clientes.canjes): HTTP ' . $pr->status, 'ALTO');

        $pr->get('/clientes/fidelizacion');
        sim_check($pr->status === 200, 'PERM_FIDELIZACION_NEGADA',
            'El Profesional NO pudo ver Fidelización, que sí le corresponde: HTTP ' . $pr->status, 'MEDIO');
        $pr->salir();
    }
}

sim_log(['tipo' => 'CANJES_FIN', 'dia' => $DIA]);
