<?php
/**
 * Comprueba los dos arreglos de la 7.28.0, en las dos direcciones: que lo que
 * tiene que pasar pase, y que lo que no tiene que pasar siga sin pasar.
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

use Illuminate\Support\Facades\DB;

$V = [];
$ok = function (string $cod, bool $cond, string $det) use (&$V) {
    $V[$cod] = ($cond ? 'PASS' : 'FAIL') . ' — ' . $det;
    echo ($cond ? '  OK   ' : '  FALLA ') . $cod . ' :: ' . $det . PHP_EOL;

    return $cond;
};

// =========================================================================
// 1. La nota de crédito se declara sola
// =========================================================================
echo "== F-01: nota de crédito → DNIT ==" . PHP_EOL;

$fac = DB::selectOne(
    "SELECT f.id_factura, fn_factura_nro(f.id_factura) nro
       FROM factura f JOIN tipo_comprobante tc ON tc.id_tipo_comprobante=f.id_tipo_comprobante
      WHERE f.id_estado_factura = 1 AND tc.signo = 1
        AND NOT EXISTS (SELECT 1 FROM factura n WHERE n.id_factura_origen = f.id_factura AND n.id_estado_factura = 1)
        AND fn_factura_saldo(f.id_factura) <= 0.01
      ORDER BY RAND() LIMIT 1"
);

if (! $fac) {
    $V['NC_SIN_CANDIDATA'] = 'NO VERIFICADO — no hay comprobante saldado sin nota';
} else {
    $a = new Nav();
    if ($a->entrar('admin', 'admin123')) {
        if (! DB::selectOne('SELECT id_caja FROM caja WHERE id_estado_caja = 1 LIMIT 1')) {
            $a->post('/facturacion/caja/abrir', ['monto_inicial' => '500.000'])->seguir();
        }

        $antes = (int) DB::scalar('SELECT COUNT(*) FROM factura_electronica');
        $a->post('/facturacion/nota-credito', [
            'id_factura' => (int) $fac->id_factura,
            'motivo' => 'Prueba del arreglo: la nota tiene que declararse sola',
        ])->seguir();

        $nota = DB::selectOne('SELECT id_factura FROM factura WHERE id_factura_origen = ? AND id_estado_factura = 1',
            [(int) $fac->id_factura]);

        if ($nota) {
            $idNota = (int) $nota->id_factura;
            $fe = DB::selectOne('SELECT estado, cdc FROM factura_electronica WHERE id_factura = ?', [$idNota]);
            $ok('NC_SE_DECLARA', $fe !== null,
                'La nota #' . $idNota . ' ' . ($fe ? 'quedó ' . $fe->estado . ' con CDC ' . substr((string) $fe->cdc, 0, 20) . '…' : 'NO generó fila en factura_electronica'));
            $ok('NC_ESTADO_ENVIADO', $fe && $fe->estado === 'ENVIADO',
                'Estado del envío: ' . ($fe->estado ?? '—'));
            $ok('NC_AVISA_EN_PANTALLA', str_contains($a->flashTxt(), 'CDC') || str_contains($a->flashTxt(), 'Declarado'),
                'El aviso dice qué pasó con la DNIT: ' . substr($a->flashTxt(), 0, 160));
            $V['nc_id'] = $idNota;
        } else {
            $V['NC_NO_SE_EMITIO'] = 'NO VERIFICADO — ' . $a->flashTxt();
        }
        $a->salir();
    }
}

// El comprobante interno NO se declara: el arreglo no puede haber roto eso
$pago = DB::selectOne('SELECT id_factura FROM factura WHERE id_tipo_comprobante = 8 AND id_estado_factura = 1 LIMIT 1');
if ($pago) {
    $hay = (int) DB::scalar('SELECT COUNT(*) FROM factura_electronica WHERE id_factura = ?', [(int) $pago->id_factura]);
    $ok('PAGO_SIGUE_SIN_DECLARARSE', $hay === 0,
        'El Comprobante de pago #' . $pago->id_factura . ' sigue sin declararse (filas: ' . $hay . ')');
}

// =========================================================================
// 2. El canje se usa desde el mostrador
// =========================================================================
echo PHP_EOL . "== F-02: canje desde el mostrador ==" . PHP_EOL;

$r = new Nav();
if (! $r->entrar('recepcion', 'recepcion123')) {
    file_put_contents(SIM_LOG . '/verificacion3.json', json_encode($V, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    exit(1);
}

// 2a. La pantalla ofrece el campo
$r->get('/citas/nueva');
$ok('FORM_TIENE_CANJES', str_contains($r->body, 'name="canjes[]"'),
    'El alta de cita del mostrador dibuja el campo canjes[]');

/** Agenda una cita desde el mostrador. Devuelve [idCita|null, flash]. */
function agendar(Nav $r, int $idCliente, array $servicios, array $canjes): array
{
    $r->get('/citas/disponibilidad', ['servicios' => $servicios]);
    $j = json_decode($r->body, true);
    $dias = array_values(array_filter($j['dias'] ?? [], fn ($d) => $d > date('Y-m-d')));
    if (! $dias) {
        return [null, 'sin días'];
    }
    foreach (array_slice($dias, 0, 6) as $f) {
        $r->get('/citas/disponibilidad', ['servicios' => $servicios, 'fecha' => $f]);
        $j2 = json_decode($r->body, true);
        if (empty($j2['horas'])) {
            continue;
        }
        $antes = (int) DB::scalar('SELECT COALESCE(MAX(id_cita),0) FROM cita');
        $r->post('/citas/guardar', [
            'id_cliente' => $idCliente, 'servicios' => $servicios,
            'fecha_hora' => $f . ' ' . $j2['horas'][0]['hora'], 'canjes' => $canjes,
        ])->seguir();
        $desp = (int) DB::scalar('SELECT COALESCE(MAX(id_cita),0) FROM cita');

        return [$desp > $antes ? $desp : null, $r->flashTxt()];
    }

    return [null, 'sin horas'];
}

// 2b. Con el servicio marcado: el canje se ata
$c1 = DB::selectOne('SELECT id_canje, id_cliente, id_servicio FROM canje
                      WHERE id_cita IS NULL AND vence_en >= CURDATE() LIMIT 1');
if ($c1) {
    [$idCita, $msg] = agendar($r, (int) $c1->id_cliente, [(int) $c1->id_servicio], [(int) $c1->id_canje]);
    $atada = (int) (DB::scalar('SELECT COALESCE(id_cita,0) FROM canje WHERE id_canje = ?', [(int) $c1->id_canje]) ?: 0);
    $ok('CANJE_SE_ATA_DESDE_MOSTRADOR', $idCita !== null && $atada === $idCita,
        'Canje #' . $c1->id_canje . ' → cita #' . ($atada ?: 'ninguna') . ' | ' . substr($msg, 0, 140));
    $V['cita_con_canje'] = $idCita;
}

// 2c. Sin marcar el servicio: NO se gasta, y lo dice
$c2 = DB::selectOne('SELECT id_canje, id_cliente, id_servicio FROM canje
                      WHERE id_cita IS NULL AND vence_en >= CURDATE() LIMIT 1');
if ($c2) {
    $otro = (int) DB::scalar('SELECT id_servicio FROM servicio WHERE activo=1 AND id_servicio <> ? ORDER BY duracion_min LIMIT 1',
        [(int) $c2->id_servicio]);
    [$idCita2, $msg2] = agendar($r, (int) $c2->id_cliente, [$otro], [(int) $c2->id_canje]);
    $sigueLibre = DB::scalar('SELECT id_cita FROM canje WHERE id_canje = ?', [(int) $c2->id_canje]) === null;
    $ok('CANJE_SIN_SERVICIO_NO_SE_GASTA', $sigueLibre,
        'Se marcó el canje #' . $c2->id_canje . ' sin su servicio y el canje ' . ($sigueLibre ? 'quedó libre' : 'SE GASTÓ'));
    $ok('CANJE_SIN_SERVICIO_AVISA', str_contains($msg2, 'NO se aplicaron'),
        'El aviso lo dice: ' . substr($msg2, 0, 160));
}

// 2d. El canje de OTRA clienta no se puede gastar
$c3 = DB::selectOne('SELECT id_canje, id_cliente, id_servicio FROM canje
                      WHERE id_cita IS NULL AND vence_en >= CURDATE() LIMIT 1');
if ($c3) {
    $ajena = (int) DB::scalar('SELECT id_cliente FROM cliente WHERE activo=1 AND id_cliente <> ? ORDER BY RAND() LIMIT 1',
        [(int) $c3->id_cliente]);
    [$idCita3, $msg3] = agendar($r, $ajena, [(int) $c3->id_servicio], [(int) $c3->id_canje]);
    $sigueLibre = DB::scalar('SELECT id_cita FROM canje WHERE id_canje = ?', [(int) $c3->id_canje]) === null;
    $ok('CANJE_AJENO_NO_SE_GASTA', $sigueLibre,
        'Se le mandó el canje #' . $c3->id_canje . ' (de la clienta ' . $c3->id_cliente . ') a la cita de la ' . $ajena
        . ' y el canje ' . ($sigueLibre ? 'quedó libre' : 'SE GASTÓ'));
}
$r->salir();

// =========================================================================
// 3. El servicio canjeado sale a CERO en el comprobante
// =========================================================================
echo PHP_EOL . "== El comprobante de la cita con canje ==" . PHP_EOL;

if (! empty($V['cita_con_canje'])) {
    $idCita = (int) $V['cita_con_canje'];
    $cj = DB::selectOne('SELECT id_servicio FROM canje WHERE id_cita = ? LIMIT 1', [$idCita]);

    $p = new Nav();
    $prof = (int) DB::scalar('SELECT id_usuario FROM cita WHERE id_cita = ?', [$idCita]);
    $usr = (string) DB::scalar('SELECT username FROM usuario WHERE id_usuario = ?', [$prof]);

    // La cita es futura: se la atiende igual, adelantándola a hoy para poder
    // facturarla. Es una manipulación del banco de pruebas, no del sistema.
    DB::update('UPDATE cita SET fecha_hora = NOW() WHERE id_cita = ?', [$idCita]);

    if ($usr && $p->entrar($usr, 'profesional123')) {
        // **Sin fichaje no se registra la atención**, y es correcto que sea
        // así (regla desde la 5.2.0). Como esta comprobación corre fuera de
        // los 60 días, ese día no hubo apertura: se ficha acá.
        $turno = (int) DB::scalar('SELECT id_turno FROM usuario_turno WHERE id_usuario = ? LIMIT 1', [$prof]);
        $p->post('/seguridad/asistencia', ['accion' => 'entrada', 'id_turno' => $turno,
            'id_usuario' => $prof, 'fecha' => date('Y-m-d')])->seguir();
        $V['fichaje'] = $p->flashTxt();

        $p->post('/citas/estado', ['id_cita' => $idCita, 'id_estado_cita' => 5, 'dia' => date('Y-m-d')])->seguir();
        $servs = array_map(fn ($x) => (int) $x->id_servicio,
            DB::select('SELECT id_servicio FROM cita_servicio WHERE id_cita = ?', [$idCita]));
        $p->post('/citas/atender', ['id_cita' => $idCita, 'servicios' => $servs, 'dia' => date('Y-m-d')])->seguir();
        $V['atencion'] = $p->flashTxt();
        $p->salir();
    }
    $ok('ATENCION_REGISTRADA',
        (int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita = ?', [$idCita]) === 4,
        'La cita quedó Atendida | ' . substr((string) ($V['atencion'] ?? ''), 0, 140));

    $a = new Nav();
    if ($a->entrar('admin', 'admin123')) {
        if (! DB::selectOne('SELECT id_caja FROM caja WHERE id_estado_caja = 1 LIMIT 1')) {
            $a->post('/facturacion/caja/abrir', ['monto_inicial' => '500.000'])->seguir();
        }
        $a->post('/facturacion/emitir', ['id_cita' => $idCita, 'id_tipo_comprobante' => 8, 'id_condicion_venta' => 1])->seguir();
        $a->salir();
    }

    $linea = DB::selectOne(
        'SELECT df.precio_unitario, df.cantidad FROM detalle_factura df
           JOIN factura f ON f.id_factura = df.id_factura
          WHERE f.id_cita = ? AND f.id_estado_factura = 1 AND df.id_servicio = ? LIMIT 1',
        [$idCita, (int) ($cj->id_servicio ?? 0)]
    );
    $ok('CANJE_VA_A_CERO', $linea !== null && (float) $linea->precio_unitario <= 0.01,
        $linea === null
            ? 'El servicio canjeado NO figura en el comprobante'
            : 'El servicio canjeado figura a ' . $linea->precio_unitario);
}

file_put_contents(SIM_LOG . '/verificacion3.json', json_encode($V, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo PHP_EOL . 'guardado' . PHP_EOL;
