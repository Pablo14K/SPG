<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Comprobantes, cobros y puntos.
 *
 * Todo lo que numera, calcula o anula lo hace la base; acá se arma la llamada
 * y se traduce el error. Dos cosas del modelo que conviene tener presentes:
 *
 *  · **`cobro` es CADA pago, no el pago de la factura.** Una factura puede
 *    tener todos los cobros que haga falta, cada uno con su medio y su monto,
 *    y `fn_factura_saldo` los resta a todos. Por eso el pago mixto no es una
 *    función aparte: es el modelo.
 *
 *  · **Anular no es borrar.** La numeración de la DNIT no puede tener huecos,
 *    así que los procedimientos solo cambian el estado y el comprobante sigue
 *    apareciendo en el listado, con su sello de anulado.
 */
class Facturacion
{
    /** Emite el comprobante de una cita ya atendida. Devuelve su id. */
    public static function emitir(int $idCliente, int $idCita, int $idUsuario, int $idTipo, int $idCondicion): int
    {
        // **La sucursal va al procedimiento**, que la necesita para elegir el
        // timbrado: el número impreso lleva el establecimiento del local, y con
        // el timbrado de otra sede el comprobante diría que salió de ahí. Si la
        // factura cuelga de una cita, la del procedimiento manda — el hecho
        // ocurrió donde ocurrió.
        return Bd::idDe('sp_emitir_factura',
            [$idCliente, $idCita, $idUsuario, $idTipo, $idCondicion, Sucursales::activa()]);
    }

    public static function numero(int $idFactura): string
    {
        return (string) Bd::funcion('fn_factura_nro(?)', [$idFactura]);
    }

    public static function saldo(int $idFactura): float
    {
        return (float) Bd::funcion('fn_factura_saldo(?)', [$idFactura]);
    }

    public static function total(int $idFactura): float
    {
        return (float) Bd::funcion('fn_factura_total(?)', [$idFactura]);
    }

    public static function hayTimbrado(int $idTipoComprobante): bool
    {
        return (bool) Bd::funcion('fn_timbrado_vigente(?, CURDATE(), ?)',
            [$idTipoComprobante, Sucursales::activa()]);
    }

    /**
     * Registra un cobro de varios medios en una sola transacción.
     *
     * Todo o nada: si una línea falla, no queda media factura cobrada. Cada
     * línea es una llamada a `sp_registrar_cobro`, y el detalle del medio va a
     * su tabla 1 a 1 según el tipo (`cobro_tarjeta` / `cobro_banco`). Los
     * disparadores de la base verifican que el detalle corresponda al tipo de
     * medio, así que acá no hace falta repetir esa validación.
     *
     * @param  array  $lineas  cada una: ['metodo','monto','tipo','referencia','detalle']
     * @return array{total: float, detalle: array<string>}
     */
    public static function cobrar(int $idFactura, int $idUsuario, array $lineas, int $idCaja): array
    {
        return Bd::enTransaccion(function () use ($idFactura, $idUsuario, $lineas, $idCaja) {
            $total = 0.0;
            $detalle = [];

            foreach ($lineas as $l) {
                // **El cajón se manda, no se deduce.** Con varios abiertos,
                // dejar que el procedimiento adivine mandaba la plata al arqueo
                // de otra persona — y el `UPDATE` que había acá sólo corregía
                // los cobros que quedaban SIN caja, o sea nunca los mal
                // asignados. Ahora es un parámetro y la base lo valida.
                $idCobro = Bd::idDe('sp_registrar_cobro',
                    [$idFactura, $l['metodo'], $idUsuario, $l['monto'],
                     $l['referencia'] ?? null, $idCaja ?: null]);
                if (! $idCobro) {
                    throw new RuntimeException('sin_cobro');
                }

                self::guardarDetalle($idCobro, (string) $l['tipo'], (array) ($l['detalle'] ?? []));

                $total += (float) $l['monto'];
                $detalle[] = $l['nombre'] . ' ' . money($l['monto']);
            }

            return ['total' => $total, 'detalle' => $detalle];
        });
    }

    /**
     * El detalle del medio, en su tabla 1 a 1.
     *
     * Si llegan datos de tarjeta en una línea de efectivo —un POST forjado, la
     * pantalla los oculta—, se descartan: el cobro se registra igual.
     */
    /**
     * El detalle 1 a 1 del medio: tarjeta o banco. **Es publica** porque el
     * cobro desde la agenda tambien la necesita: ahi el cobro va contra la
     * cita y no contra una factura, asi que no pasa por `cobrar()`.
     */
    public static function guardarDetalle(int $idCobro, string $tipo, array $d): void
    {
        $v = fn (string $k) => trim((string) ($d[$k] ?? '')) !== '' ? trim((string) $d[$k]) : null;

        // `cobro_tarjeta.tipo_tarjeta` y `cobro_banco.banco` son NOT NULL, así
        // que las dos columnas se resuelven ANTES de insertar. Sin esto, una
        // línea de tarjeta con la marca cargada y el tipo vacío tiraba
        // «1048 Column 'tipo_tarjeta' cannot be null» y el cobro entero se caía
        // —las otras líneas incluidas, porque va todo en una transacción—.
        if ($tipo === 'TARJETA') {
            $hay = $v('marca') ?? $v('ultimos_4') ?? $v('nro_boleta') ?? $v('cod_autorizacion');
            if ($hay === null) {
                return;   // sin datos, no se fuerza
            }
            $tt = strtoupper((string) ($v('tipo_tarjeta') ?? ''));

            // **Los últimos cuatro son cuatro dígitos, no un texto.** La
            // pantalla ya no deja escribir otra cosa, pero eso es comodidad:
            // el POST puede llegar armado a mano, y un «ABCD» guardado ahí no
            // sirve para identificar la tarjeta el día que se reclama un
            // cobro. Lo que no cumple se descarta en vez de romper el cobro
            // entero: el resto del detalle sigue siendo válido.
            $u4 = preg_replace('/\D/', '', (string) ($v('ultimos_4') ?? ''));
            $u4 = ($u4 !== '' && strlen($u4) <= 4) ? str_pad($u4, 4, '0', STR_PAD_LEFT) : null;
            DB::insert(
                'INSERT INTO cobro_tarjeta (id_cobro,marca,tipo_tarjeta,cuotas,ultimos_4,nro_boleta,cod_autorizacion)
                 VALUES (?,?,?,?,?,?,?)',
                [$idCobro, $v('marca'), in_array($tt, ['DEBITO', 'CREDITO'], true) ? $tt : 'DEBITO',
                 max(1, entero($d['cuotas'] ?? 1, 1)),
                 $u4, $v('nro_boleta'), $v('cod_autorizacion')]
            );
        } elseif ($tipo === 'BANCO' || $tipo === 'CHEQUE') {
            if ($v('banco') === null && $v('nro_cheque') === null && $v('nro_operacion') === null) {
                return;
            }
            $fecha = $v('fecha_emision');
            DB::insert(
                'INSERT INTO cobro_banco (id_cobro,banco,nro_cheque,nro_operacion,fecha_emision) VALUES (?,?,?,?,?)',
                [$idCobro, $v('banco') ?? 'Sin especificar', $v('nro_cheque'), $v('nro_operacion'),
                 ($fecha && strtotime($fecha)) ? $fecha : null]
            );
        }
    }

    /**
     * La seña se cobra antes de atender, así que todavía no hay factura: queda
     * como un cobro atado a la cita.
     *
     * NO hay que vincularla después a la factura: `fn_factura_saldo` ya
     * descuenta los cobros de la cita además de los de la factura. Si se la
     * vinculara, se contaría dos veces y el saldo saldría de menos.
     */
    public static function sena(int $idCita, int $idMetodo, int $idUsuario, float $monto, ?string $ref, int $idCaja): int
    {
        return Bd::idDe('sp_registrar_sena',
            [$idCita, $idMetodo, $idUsuario, $monto, $ref, $idCaja ?: null]);
    }

    public static function anularFactura(int $idFactura, int $idUsuario): void
    {
        Bd::procedimiento('sp_anular_factura', [$idFactura, $idUsuario]);
    }

    public static function anularCobro(int $idCobro, int $idUsuario): void
    {
        Bd::procedimiento('sp_anular_cobro', [$idCobro, $idUsuario]);
    }

    /** Acredita el total de un comprobante de venta. Devuelve el id de la nota. */
    public static function notaCredito(int $idFacturaOrigen, int $idUsuario, string $motivo): int
    {
        return Bd::idDe('sp_emitir_nota_credito', [$idFacturaOrigen, $idUsuario, $motivo]);
    }

    // -----------------------------------------------------------------
    //  Puntos de fidelización
    //
    //  La base ya trae todo (sp_registrar_puntos y el CHECK que fija el
    //  vocabulario ACUMULA / CANJE / AJUSTE); lo único que faltaba era que
    //  alguien llamara al procedimiento. Los puntos nunca deben tumbar la
    //  operación principal: si algo falla acá, la factura ya está emitida y
    //  eso es lo que importa.
    // -----------------------------------------------------------------

    public static function acumularPuntos(int $idFactura, int $idCliente): int
    {
        $total = self::total($idFactura);
        // La relación la decide el salón desde Servicios → Descuentos, así que
        // sale de la base y no de `config/spg.php` — que queda de respaldo por
        // si la tabla todavía no está.
        $puntos = (int) floor($total / Config::puntosCadaGs());
        if ($puntos <= 0) {
            return 0;
        }

        try {
            Bd::procedimiento('sp_registrar_puntos', [
                $idCliente, $idFactura, 'ACUMULA', $puntos,
                'Emisión del comprobante ' . self::numero($idFactura),
            ]);

            return $puntos;
        } catch (Throwable $e) {
            report($e);

            return 0;
        }
    }

    /**
     * Al anular el comprobante hay que devolver los puntos que dio. Se registra
     * el movimiento contrario en vez de borrar el original, para que el
     * historial del cliente muestre lo que pasó. Va como AJUSTE porque el CHECK
     * de la base solo admite CANJE con puntos negativos cuando canjea el cliente.
     */
    public static function revertirPuntos(int $idFactura, int $idCliente, string $motivo = 'Anulación'): int
    {
        $saldo = (int) DB::scalar('SELECT COALESCE(SUM(puntos),0) FROM movimiento_punto WHERE id_factura = ?', [$idFactura]);
        if ($saldo <= 0) {
            return 0;
        }

        try {
            Bd::procedimiento('sp_registrar_puntos', [
                $idCliente, $idFactura, 'AJUSTE', -$saldo,
                $motivo . ' del comprobante ' . self::numero($idFactura),
            ]);

            return $saldo;
        } catch (Throwable $e) {
            // Puede fallar si el cliente ya canjeó esos puntos: queda en el log
            report($e);

            return 0;
        }
    }
}
