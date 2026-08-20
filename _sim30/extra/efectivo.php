<?php
/**
 * Movimiento de efectivo y devolución por nota de crédito.
 *
 * **Estas dos pantallas no tenían cobertura y no se notaba.** La simulación de
 * 30 días registró CERO movimientos de caja y CERO notas de crédito: el gasto y
 * el retiro exigen adjuntar el comprobante desde la 7.47.0 y el banco no sabía
 * subir archivos, así que las dos piezas más nuevas del módulo de dinero se
 * ejercitaron exactamente nunca. Un hueco de cobertura esconde defectos, no
 * ausencia de defectos.
 */

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

$n = new Nav();
if (! $n->entrar('recepcion', 'recepcion123', true)) {
    return;
}

$suc = (int) (DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1') ?: 1);
$caja = DB::selectOne(
    'SELECT id_caja FROM caja WHERE id_estado_caja = 1 AND id_sucursal = ? LIMIT 1', [$suc]
);
if (! $caja) {
    return;   // sin caja abierta no hay nada que mover; el cierre ya lo mide
}

$saldoAntes = (float) DB::scalar('SELECT fn_caja_saldo(?)', [(int) $caja->id_caja]);

// ---------------------------------------------------------------------------
//  1. El gasto con comprobante — el camino completo
// ---------------------------------------------------------------------------
$gasto = DB::selectOne(
    'SELECT id_tipo_mov_caja FROM tipo_movimiento_caja WHERE exige_documento = 1 AND activo = 1 LIMIT 1'
);
if ($gasto) {
    $monto = 12000 + random_int(0, 8) * 1000;

    // 1a. Sin comprobante: tiene que rebotar. Es la regla que impide que la
    //     plata salga de la nada.
    $antes = (int) DB::scalar('SELECT COUNT(*) FROM movimiento_caja');
    $n->post('/facturacion/caja/movimiento', [
        'id_tipo_mov_caja' => (int) $gasto->id_tipo_mov_caja,
        'monto' => (string) $monto, 'concepto' => 'delivery del almuerzo',
    ])->seguir();
    if ((int) DB::scalar('SELECT COUNT(*) FROM movimiento_caja') > $antes) {
        sim_incidente('MOV_GASTO_SIN_RESPALDO',
            'Un gasto entró al cajón sin número de comprobante ni RUC ni foto: la plata sale de la nada',
            'CRITICO');
    }

    // 1b. Con un RUC de dígito verificador inválido: tampoco.
    $n->post('/facturacion/caja/movimiento', [
        'id_tipo_mov_caja' => (int) $gasto->id_tipo_mov_caja,
        'monto' => (string) $monto, 'concepto' => 'delivery del almuerzo',
        'nro_comprobante' => '001-001-0004521', 'ruc_emisor' => '80012345-6',
    ])->seguir();
    if ((int) DB::scalar('SELECT COUNT(*) FROM movimiento_caja') > $antes) {
        sim_incidente('MOV_RUC_INVALIDO',
            'Se aceptó un comprobante con el dígito verificador mal: es el rechazo 1309 de la DNIT',
            'ALTO');
    }

    // 1c. Completo y bien: entra, y el cajón baja exactamente ese monto.
    $n->postConArchivo('/facturacion/caja/movimiento', [
        'id_tipo_mov_caja' => (int) $gasto->id_tipo_mov_caja,
        'monto' => (string) $monto, 'concepto' => 'delivery del almuerzo',
        'nro_comprobante' => '001-001-000' . random_int(1000, 9999), 'ruc_emisor' => '80012345-0',
    ], 'archivo')->seguir();

    $ahora = (int) DB::scalar('SELECT COUNT(*) FROM movimiento_caja');
    if ($ahora === $antes) {
        sim_incidente('MOV_GASTO_NO_ENTRA',
            'Un gasto con comprobante, RUC válido y foto no se pudo registrar: ' . $n->flashTxt(), 'ALTO');
    } else {
        $saldo = (float) DB::scalar('SELECT fn_caja_saldo(?)', [(int) $caja->id_caja]);
        if (abs(($saldoAntes - $monto) - $saldo) > 0.01) {
            sim_incidente('MOV_ARQUEO_NO_BAJA',
                'El cajón tenía ' . $saldoAntes . ', salió ' . $monto . ' y quedó ' . $saldo, 'CRITICO');
        }

        // El archivo tiene que haber quedado guardado, fuera de public/.
        $arch = (string) DB::scalar(
            'SELECT archivo FROM movimiento_caja ORDER BY id_movimiento_caja DESC LIMIT 1'
        );
        if ($arch === '' || ! is_file(storage_path('app/respaldos/' . $arch))) {
            sim_incidente('MOV_RESPALDO_PERDIDO',
                'El movimiento quedó sin el archivo del comprobante: el respaldo no se guardó', 'ALTO');
        }
        if ($arch !== '' && is_file(public_path('assets/respaldos/' . $arch))) {
            sim_incidente('MOV_RESPALDO_PUBLICO',
                'El comprobante quedó bajo public/: cualquiera con la URL lo baja', 'CRITICO');
        }
    }
}

// ---------------------------------------------------------------------------
//  2. La devolución por nota de crédito — los dos actos
// ---------------------------------------------------------------------------
$f = DB::selectOne(
    "SELECT f.id_factura, fn_factura_nro(f.id_factura) AS nro
       FROM factura f
       JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = f.id_tipo_comprobante
      WHERE f.id_estado_factura = 1 AND tc.signo = 1
        AND NOT EXISTS (SELECT 1 FROM factura nc WHERE nc.id_factura_origen = f.id_factura)
        AND EXISTS (SELECT 1 FROM cobro co
                     JOIN metodo_pago mp ON mp.id_metodo_pago = co.id_metodo_pago
                    WHERE co.id_factura = f.id_factura AND co.id_estado_cobro = 1 AND mp.tipo = 'EFECTIVO')
      ORDER BY f.id_factura DESC LIMIT 1"
);

if ($f) {
    $notasAntes = (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_tipo_comprobante = 5');

    // 2a. Se emite desde Facturas. Sin motivo tiene que rebotar.
    $n->post('/facturacion/nota-credito', ['id_factura' => (int) $f->id_factura, 'motivo' => ''])->seguir();
    if ((int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_tipo_comprobante = 5') > $notasAntes) {
        sim_incidente('NC_SIN_MOTIVO_ENTRA', 'Se emitió una nota de crédito sin motivo', 'ALTO');
    }

    // 2b. Con motivo: se emite, y NO tiene que tocar el cajón.
    $saldoPrevio = (float) DB::scalar('SELECT fn_caja_saldo(?)', [(int) $caja->id_caja]);
    $n->post('/facturacion/nota-credito', [
        'id_factura' => (int) $f->id_factura, 'motivo' => 'la clienta no quedó conforme',
    ])->seguir();

    $notas = (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_tipo_comprobante = 5');
    if ($notas === $notasAntes) {
        sim_incidente('NC_NO_SE_EMITE',
            'No se pudo emitir la nota de crédito sobre ' . $f->nro . ': ' . $n->flashTxt(), 'ALTO');
    } else {
        if (abs((float) DB::scalar('SELECT fn_caja_saldo(?)', [(int) $caja->id_caja]) - $saldoPrevio) > 0.01) {
            sim_incidente('NC_MUEVE_EL_CAJON',
                'Emitir la nota movió el cajón sola: la devolución se confirma aparte, si no queda cargada dos veces',
                'ALTO');
        }

        // 2c. Se confirma desde Movimiento de efectivo, eligiendo la nota.
        $nc = (int) DB::scalar(
            'SELECT id_factura FROM factura WHERE id_factura_origen = ? ORDER BY id_factura DESC LIMIT 1',
            [(int) $f->id_factura]
        );
        $dev = (int) DB::scalar(
            "SELECT id_tipo_mov_caja FROM tipo_movimiento_caja WHERE nombre LIKE 'Devoluci%' AND activo = 1 LIMIT 1"
        );
        if ($nc && $dev) {
            $n->post('/facturacion/caja/movimiento', [
                'id_tipo_mov_caja' => $dev, 'id_factura' => $nc,
                'monto' => '1', 'concepto' => 'devolución',
            ])->seguir();

            $mov = DB::selectOne(
                'SELECT monto FROM movimiento_caja WHERE id_factura = ? AND activo = 1', [$nc]
            );
            if (! $mov) {
                sim_incidente('NC_DEVOLUCION_NO_ENTRA',
                    'No se pudo confirmar la devolución de la nota ' . $nc . ': ' . $n->flashTxt(), 'ALTO');
            } else {
                // **El monto sale del documento, no del formulario**: se mandó 1
                // a propósito. Si quedó 1, el formulario le gana al documento y
                // vuelven a poder existir dos números para la misma devolución.
                if ((float) $mov->monto <= 1.0) {
                    sim_incidente('NC_MONTO_DEL_FORMULARIO',
                        'La devolución tomó el monto que se tipeó (1) en vez del de la nota', 'CRITICO');
                }

                // La segunda no puede entrar.
                $n->post('/facturacion/caja/movimiento', [
                    'id_tipo_mov_caja' => $dev, 'id_factura' => $nc,
                    'monto' => '99999', 'concepto' => 'segunda devolución',
                ])->seguir();
                if ((int) DB::scalar(
                    'SELECT COUNT(*) FROM movimiento_caja WHERE id_factura = ? AND activo = 1', [$nc]
                ) > 1) {
                    sim_incidente('NC_DOBLE_DEVOLUCION',
                        'La misma nota se devolvió dos veces: el cajón queda faltando plata que nunca salió',
                        'CRITICO');
                }
            }
        }
    }
}
