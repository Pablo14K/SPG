<?php
/**
 * Lo que sale del salón: baja de una profesional con citas futuras
 * (AG-03, 7.24.0), devolución con nota de crédito (FA-02) y liquidación al
 * personal (CJ-02) — las tres cosas que en la simulación de 90 días no
 * tocaban el arqueo o dejaban citas huérfanas.
 */

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/** @var int $DIA */

$hoy = date('Y-m-d');

/** Saldo de la caja abierta, o null. */
function saldoCaja(): ?float
{
    $c = DB::selectOne('SELECT id_caja FROM caja WHERE id_estado_caja = 1 ORDER BY id_caja DESC LIMIT 1');

    return $c ? (float) DB::scalar('SELECT fn_caja_saldo(?)', [(int) $c->id_caja]) : null;
}

function hayCajaAbierta(): bool
{
    return (bool) DB::selectOne('SELECT id_caja FROM caja WHERE id_estado_caja = 1 LIMIT 1');
}

// =========================================================================
// 1. Devolución: la nota de crédito tiene que salir del cajón (FA-02)
// =========================================================================
$fac = DB::selectOne(
    "SELECT f.id_factura, fn_factura_total(f.id_factura) AS total,
            (SELECT COALESCE(SUM(co.monto),0) FROM cobro co
               JOIN metodo_pago mp ON mp.id_metodo_pago = co.id_metodo_pago
              WHERE co.id_estado_cobro = 1 AND mp.tipo = 'EFECTIVO'
                AND (co.id_factura = f.id_factura OR co.id_cita = f.id_cita)) AS efectivo
       FROM factura f
       JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = f.id_tipo_comprobante
      WHERE f.id_estado_factura = 1 AND tc.signo = 1
        AND NOT EXISTS (SELECT 1 FROM factura n WHERE n.id_factura_origen = f.id_factura AND n.id_estado_factura = 1)
        AND fn_factura_saldo(f.id_factura) <= 0.01
      ORDER BY RAND() LIMIT 1"
);

if ($fac && hayCajaAbierta()) {
    $r = new Nav();
    if ($r->entrar('recepcion', 'recepcion123')) {
        $antesSaldo = saldoCaja();
        $antesMov = (int) DB::scalar('SELECT COUNT(*) FROM movimiento_caja');
        $efectivo = (float) $fac->efectivo;

        // Sin motivo: tiene que rechazarla
        $r->post('/facturacion/nota-credito', ['id_factura' => (int) $fac->id_factura, 'motivo' => ''])->seguir();
        $sinMotivo = (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_factura_origen = ?', [(int) $fac->id_factura]);
        sim_check($sinMotivo === 0, 'NC_SIN_MOTIVO',
            'Se emitió una nota de crédito sin motivo', 'MEDIO');

        $r->post('/facturacion/nota-credito', [
            'id_factura' => (int) $fac->id_factura,
            'motivo' => 'La clienta no quedó conforme con el color',
        ])->seguir();

        $nota = DB::selectOne('SELECT id_factura FROM factura WHERE id_factura_origen = ? AND id_estado_factura = 1',
            [(int) $fac->id_factura]);

        if ($nota) {
            $despSaldo = saldoCaja();
            $despMov = (int) DB::scalar('SELECT COUNT(*) FROM movimiento_caja');
            $bajo = ($antesSaldo ?? 0) - ($despSaldo ?? 0);

            sim_log(['tipo' => 'NOTA_CREDITO', 'factura' => (int) $fac->id_factura, 'nota' => (int) $nota->id_factura,
                     'total' => (float) $fac->total, 'pagado_efectivo' => $efectivo,
                     'caja' => ($antesSaldo ?? 0) . '→' . ($despSaldo ?? 0), 'movimientos' => $antesMov . '→' . $despMov]);

            if ($efectivo > 0.01) {
                sim_check(abs($bajo - $efectivo) < 1.0, 'NC_CAJA_NO_BAJA',
                    'Se acreditó una venta pagada con ' . $efectivo . ' en efectivo y la caja bajó ' . $bajo, 'ALTO');
                sim_check($despMov > $antesMov, 'NC_SIN_MOVIMIENTO',
                    'La nota de crédito no dejó egreso en movimiento_caja', 'ALTO');
            } else {
                sim_check(abs($bajo) < 1.0, 'NC_CAJA_BAJA_DE_MAS',
                    'La venta no se había cobrado en efectivo y aun así la caja bajó ' . $bajo, 'ALTO');
            }

            // Doble nota de crédito sobre el mismo comprobante
            $r->post('/facturacion/nota-credito', [
                'id_factura' => (int) $fac->id_factura, 'motivo' => 'Segunda vez',
            ])->seguir();
            $n2 = (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_factura_origen = ? AND id_estado_factura = 1',
                [(int) $fac->id_factura]);
            sim_check($n2 <= 1, 'NC_DUPLICADA',
                "El comprobante #{$fac->id_factura} quedó con $n2 notas de crédito vigentes", 'ALTO');

            // El comprobante acreditado tiene que verse como tal en la lista (FA-04)
            $r->get('/facturacion/facturas', ['q' => '']);
            sim_log(['tipo' => 'NC_LISTA', 'st' => $r->status]);
        } else {
            sim_log(['tipo' => 'NC_NO', 'msg' => $r->flashTxt()]);
        }
        $r->salir();
    }
}

// =========================================================================
// 2. Un gasto de caja chica: ¿se puede registrar? (lo que el informe de 90
//    días dejó pendiente de CJ-02)
// =========================================================================
if ($DIA === 13 && hayCajaAbierta()) {
    $r = new Nav();
    if ($r->entrar('admin', 'admin123')) {
        $r->get('/facturacion/caja');
        $tiene = $r->dice('movimiento') || $r->dice('Egreso') || $r->dice('caja chica');
        sim_log(['tipo' => 'CAJA_MOV_MANUAL', 'pantalla_ofrece' => $tiene]);
        if (! $tiene) {
            sim_incidente('CAJA_SIN_MOVIMIENTO_MANUAL',
                'No hay pantalla para cargar un gasto de caja chica ni un retiro: movimiento_caja sólo la escribe '
                . 'la nota de crédito, así que un egreso real del mostrador queda fuera del arqueo', 'MEDIO');
        }
        $r->salir();
    }
}

// =========================================================================
// 3. Baja de una profesional con citas futuras → reasignación (AG-03)
// =========================================================================
if ($DIA === 34) {
    $victima = DB::selectOne(
        "SELECT u.id_usuario, u.username, COUNT(*) AS futuras
           FROM cita c
           JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
           JOIN usuario u ON u.id_usuario = c.id_usuario
          WHERE ec.bloquea_agenda = 1 AND c.fecha_hora >= NOW() AND u.activo = 1 AND u.id_rol = 2
          GROUP BY u.id_usuario, u.username
          HAVING futuras >= 2
          ORDER BY futuras DESC LIMIT 1"
    );

    if ($victima) {
        $idV = (int) $victima->id_usuario;
        $a = new Nav();
        if ($a->entrar('admin', 'admin123')) {
            $antesAvisos = (int) DB::scalar('SELECT COUNT(*) FROM notificacion');

            // Se la da de baja
            $a->post('/seguridad/usuarios/baja', ['id_usuario' => $idV])->seguir();
            $activo = (int) DB::scalar('SELECT activo FROM usuario WHERE id_usuario = ?', [$idV]);
            sim_check($activo === 0, 'BAJA_NO_APLICA',
                "Se dio de baja a {$victima->username} y quedó activo = $activo", 'ALTO');

            $despAvisos = (int) DB::scalar('SELECT COUNT(*) FROM notificacion');
            sim_check($despAvisos > $antesAvisos, 'BAJA_SIN_AVISO',
                'La baja no generó ningún aviso para las clientas con cita', 'MEDIO');

            // Sus citas siguen ocupando agenda: se reasignan
            $suyas = DB::select(
                'SELECT c.id_cita FROM cita c JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
                  WHERE c.id_usuario = ? AND ec.bloquea_agenda = 1 AND c.fecha_hora >= NOW()', [$idV]
            );
            $ids = array_map(fn ($r) => (int) $r->id_cita, $suyas);

            $a->get('/citas/reasignar', ['de' => $idV]);
            sim_check($a->status === 200, 'REASIGNAR_PANTALLA',
                'La pantalla de reasignación contestó HTTP ' . $a->status, 'ALTO');

            $destino = (int) DB::scalar(
                'SELECT u.id_usuario FROM usuario u JOIN usuario_turno ut ON ut.id_usuario = u.id_usuario
                  WHERE u.activo = 1 AND u.id_rol = 2 AND u.id_usuario <> ? LIMIT 1', [$idV]
            );

            if ($destino && $ids) {
                $horasAntes = [];
                foreach ($ids as $i) {
                    $horasAntes[$i] = (string) DB::scalar('SELECT fecha_hora FROM cita WHERE id_cita = ?', [$i]);
                }

                $a->post('/citas/reasignar', ['de' => $idV, 'a' => $destino, 'citas' => $ids])->seguir();

                $movidas = 0;
                $movidasDeHora = 0;
                foreach ($ids as $i) {
                    $f = DB::selectOne('SELECT id_usuario, fecha_hora, id_estado_cita FROM cita WHERE id_cita = ?', [$i]);
                    if ($f && (int) $f->id_usuario === $destino) {
                        $movidas++;
                        if ((string) $f->fecha_hora !== $horasAntes[$i]) {
                            $movidasDeHora++;
                        }
                    }
                }

                sim_check($movidasDeHora === 0, 'REASIGNAR_CAMBIA_HORA',
                    "$movidasDeHora cita(s) reasignada(s) cambiaron de horario: la clienta ya tenía su hora", 'ALTO');

                // El reparto tiene que mudarse con la cita, o la comisión se le
                // sigue atribuyendo a quien se fue (AG-02)
                $repartoViejo = (int) DB::scalar(
                    'SELECT COUNT(*) FROM cita_servicio cs JOIN cita c ON c.id_cita = cs.id_cita
                      WHERE c.id_cita IN (' . implode(',', array_map('intval', $ids)) . ')
                        AND cs.id_usuario = ?', [$idV]
                );
                sim_check($repartoViejo === 0, 'REASIGNAR_REPARTO_VIEJO',
                    "$repartoViejo servicio(s) de las citas reasignadas siguen a nombre de quien se fue", 'ALTO');

                // Y no puede haber solapes en la agenda del que las recibe
                $solapes = (int) DB::scalar(
                    "SELECT COUNT(*) FROM cita a JOIN cita b ON a.id_usuario = b.id_usuario AND a.id_cita < b.id_cita
                       JOIN estado_cita ea ON ea.id_estado_cita = a.id_estado_cita AND ea.bloquea_agenda = 1
                       JOIN estado_cita eb ON eb.id_estado_cita = b.id_estado_cita AND eb.bloquea_agenda = 1
                      WHERE a.id_usuario = ?
                        AND b.fecha_hora < DATE_ADD(a.fecha_hora, INTERVAL fn_cita_duracion_de(a.id_cita, a.id_usuario) MINUTE)
                        AND a.fecha_hora < DATE_ADD(b.fecha_hora, INTERVAL fn_cita_duracion_de(b.id_cita, b.id_usuario) MINUTE)",
                    [$destino]
                );
                sim_check($solapes === 0, 'REASIGNAR_SOLAPE',
                    "La reasignación dejó $solapes solape(s) en la agenda de #$destino", 'CRITICO');

                sim_log(['tipo' => 'REASIGNACION', 'de' => $idV, 'a' => $destino,
                         'pedidas' => count($ids), 'movidas' => $movidas, 'solapes' => $solapes]);
            }

            // Se la reactiva para no dejar el salón corto el resto del mes
            $a->post('/seguridad/usuarios/baja', ['id_usuario' => $idV])->seguir();
            $a->salir();
        }
    }
}

// =========================================================================
// 4. Liquidación al personal: tiene que descontar del cajón sólo en efectivo
// =========================================================================
if ($DIA === 28 || $DIA === 56) {
    if (! hayCajaAbierta()) {
        $x = new Nav();
        if ($x->entrar('recepcion', 'recepcion123')) {
            $x->post('/facturacion/caja/abrir', ['monto_inicial' => '300.000'])->seguir();
            $x->salir();
        }
    }

    $prof = DB::selectOne(
        'SELECT sr.id_usuario, COUNT(*) AS n,
                COALESCE(SUM(fn_comision_servicio(sr.id_servicio_realizado)),0) AS monto
           FROM servicio_realizado sr
           LEFT JOIN detalle_pago_personal d ON d.id_servicio_realizado = sr.id_servicio_realizado
          WHERE d.id_detalle_pago IS NULL
          GROUP BY sr.id_usuario HAVING monto > 0 ORDER BY monto DESC LIMIT 1'
    );

    if ($prof) {
        $a = new Nav();
        if ($a->entrar('admin', 'admin123')) {
            $antes = saldoCaja();
            $monto = (float) $prof->monto;

            // Sin medio de pago: tiene que rechazarlo (7.22.0)
            $a->post('/facturacion/pagos/personal', ['id_usuario' => (int) $prof->id_usuario, 'periodo' => date('m/Y')])->seguir();
            $creado = (int) DB::scalar("SELECT COUNT(*) FROM pago_personal WHERE id_usuario = ? AND DATE(fecha_pago) = CURDATE()",
                [(int) $prof->id_usuario]);
            sim_check($creado === 0, 'LIQUIDACION_SIN_MEDIO_PASA',
                'Se registró una liquidación sin decir con qué se paga', 'ALTO');

            // Por transferencia: NO toca el cajón
            $a->post('/facturacion/pagos/personal', [
                'id_usuario' => (int) $prof->id_usuario, 'periodo' => date('m/Y'), 'id_metodo_pago' => 4,
            ])->seguir();
            $desp = saldoCaja();

            if ($a->dice('registrada')) {
                sim_check(abs(($antes ?? 0) - ($desp ?? 0)) < 1.0, 'LIQUIDACION_BANCO_TOCA_CAJA',
                    'Una liquidación por transferencia movió el cajón: ' . ($antes ?? 0) . ' → ' . ($desp ?? 0), 'ALTO');
                sim_log(['tipo' => 'LIQUIDACION', 'usuario' => (int) $prof->id_usuario, 'monto' => $monto,
                         'medio' => 'BANCO', 'caja' => ($antes ?? 0) . '→' . ($desp ?? 0)]);
            }
            $a->salir();
        }
    }
}

// =========================================================================
// 5. No se puede pagar en efectivo más de lo que hay en el cajón
// =========================================================================
if ($DIA === 41 && hayCajaAbierta()) {
    $saldo = saldoCaja() ?? 0;
    $cta = DB::selectOne('SELECT * FROM vw_cuenta_proveedor WHERE saldo > 0 ORDER BY saldo DESC LIMIT 1');
    if ($cta) {
        $a = new Nav();
        if ($a->entrar('admin', 'admin123')) {
            $exceso = (int) ($saldo + 500000);
            $a->post('/facturacion/proveedores/pagar', [
                'id_compra' => (int) $cta->id_compra, 'id_metodo_pago' => 1,
                'monto' => (string) $exceso, 'referencia' => 'EXCESO',
            ])->seguir();
            $ahora = saldoCaja() ?? 0;
            sim_check($ahora >= -0.01, 'CAJA_NEGATIVA_PROVEEDOR',
                "Un pago en efectivo de $exceso sobre un cajón de $saldo dejó la caja en $ahora", 'CRITICO');
            sim_log(['tipo' => 'PAGO_EXCESO', 'saldo' => $saldo, 'intento' => $exceso, 'resultado' => $ahora,
                     'msg' => $a->flashTxt()]);
            $a->salir();
        }
    }
}

sim_log(['tipo' => 'BAJAS_FIN', 'dia' => $DIA]);
