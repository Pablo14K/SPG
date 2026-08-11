<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Facturación electrónica: el puente con el Automatizador SIFEN.
 *
 * El SPG **no habla con la DNIT ni firma nada**. Toma un comprobante que ya
 * emitió y numeró —con su timbrado y su correlativo, como siempre—, lo escribe
 * en el formato de texto que el Automatizador entiende y se lo manda. Lo que
 * vuelve es el CDC, que es el número con el que la DNIT reconoce el documento,
 * y se guarda en `factura_electronica`.
 *
 * TRES REGLAS QUE NO HAY QUE PERDER:
 *
 *  1. **Emitir y enviar son dos cosas separadas.** La factura se emite en el
 *     SPG y queda válida aunque el Automatizador esté caído; el envío es un
 *     paso posterior que se puede repetir. Si emitir dependiera de que un
 *     servicio externo conteste, un corte de internet dejaría al salón sin
 *     poder cobrar.
 *
 *  2. **Un rechazo por datos NO se reintenta.** Si la DNIT dice que el RUC
 *     está mal, mandarlo de nuevo da el mismo error. Por eso hay dos estados
 *     distintos: PENDIENTE (se puede reintentar) y RECHAZADO (hay que
 *     corregir el dato y emitir de nuevo).
 *
 *  3. **Un fallo de red deja el envío en PENDIENTE, nunca en RECHAZADO.** El
 *     comprobante puede haberse emitido igual del otro lado: se reintenta a
 *     mano, mirando antes si ya tiene CDC.
 *
 * El Ticket no pasa por acá: es el comprobante interno del salón, el que se
 * usa cuando la clienta no pide factura.
 */
class Sifen
{
    /** ¿El salón usa facturación electrónica? Con esto en false, el módulo no existe. */
    public static function activo(): bool
    {
        return (bool) config('sifen.activo', false);
    }

    /** ¿Este tipo de comprobante va a la DNIT, o es interno del salón? */
    public static function esElectronico(int $idTipoComprobante): bool
    {
        return in_array($idTipoComprobante, (array) config('sifen.tipos_electronicos', [1, 5]), true);
    }

    /** El estado del envío de una factura, o null si nunca se intentó. */
    public static function estado(int $idFactura): ?object
    {
        return DB::selectOne('SELECT * FROM factura_electronica WHERE id_factura = ?', [$idFactura]);
    }

    /**
     * Arma el comprobante en el formato del Automatizador.
     *
     * Son líneas separadas por `|`: una FAC con la cabecera, una CLI con el
     * cliente y una ITM por renglón. El total NO se escribe — lo calcula el
     * Automatizador desde los ítems, y el precio va con el IVA INCLUIDO, que
     * es como lo guarda el SPG.
     *
     * Se devuelve el texto para poder mirarlo antes de mandarlo: es la forma
     * de saber qué se envió cuando la DNIT rechaza algo.
     */
    public static function armarTxt(int $idFactura): string
    {
        $f = DB::selectOne(
            "SELECT f.id_factura, f.fecha_emision, f.nro_correlativo, f.id_condicion_venta,
                    t.establecimiento, t.punto_expedicion,
                    pe.nombre, pe.apellido, pe.cedula, pe.ruc, pe.email, pe.telefono, pe.direccion
               FROM factura f
               JOIN timbrado t ON t.id_timbrado = f.id_timbrado
               JOIN cliente c  ON c.id_cliente = f.id_cliente
               JOIN persona pe ON pe.id_persona = c.id_persona
              WHERE f.id_factura = ?", [$idFactura]
        );
        if (! $f) {
            throw new \RuntimeException('Esa factura no existe.');
        }

        $items = DB::select(
            'SELECT item, cantidad, precio_unitario, tasa_iva
               FROM vw_detalle_factura WHERE id_factura = ? ORDER BY id_detalle_factura', [$idFactura]
        );
        if (! $items) {
            throw new \RuntimeException('La factura no tiene renglones que declarar.');
        }

        // Nada puede llevar el separador adentro, o la línea se parte de más.
        $limpiar = fn ($v) => trim(str_replace(['|', "\r", "\n"], ['/', ' ', ' '], (string) $v));

        // Con RUC va el RUC; si no, la cédula. Sin ninguno de los dos es
        // consumidor final, que es lo normal en una peluquería.
        [$tipoDoc, $doc] = match (true) {
            trim((string) $f->ruc) !== '' => ['RUC', trim((string) $f->ruc)],
            trim((string) $f->cedula) !== '' => ['CI', trim((string) $f->cedula)],
            default => ['CI', 'CF'],
        };
        $nombre = trim($f->nombre . ' ' . $f->apellido) ?: 'Consumidor Final';

        $lineas = [];
        $lineas[] = implode('|', [
            'FAC',
            str_pad((string) $f->establecimiento, 3, '0', STR_PAD_LEFT),
            str_pad((string) $f->punto_expedicion, 3, '0', STR_PAD_LEFT),
            str_pad((string) $f->nro_correlativo, 7, '0', STR_PAD_LEFT),
            date('Y-m-d', strtotime((string) $f->fecha_emision)),
            (int) $f->id_condicion_venta === 2 ? '2' : '1',   // 1 contado · 2 crédito
            'PYG',
        ]);
        $lineas[] = implode('|', [
            'CLI', $tipoDoc, $limpiar($doc), $limpiar($nombre),
            $limpiar($f->email), $limpiar($f->direccion), $limpiar($f->telefono),
        ]);

        foreach ($items as $i => $it) {
            $lineas[] = implode('|', [
                'ITM',
                'S' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                $limpiar($it->item),
                // Cantidad sin decimales sobrantes: «1» y no «1.00».
                rtrim(rtrim(number_format((float) $it->cantidad, 2, '.', ''), '0'), '.'),
                (string) (int) round((float) $it->precio_unitario),
                (string) (int) $it->tasa_iva,
            ]);
        }

        return implode("\n", $lineas) . "\n";
    }

    /**
     * Manda el comprobante y guarda lo que conteste.
     *
     * Devuelve `['ok' => bool, 'mensaje' => string, 'cdc' => ?string]`. Nunca
     * lanza: el resultado se le muestra a quien apretó el botón, y el estado
     * queda escrito pase lo que pase.
     */
    public static function enviar(int $idFactura): array
    {
        $f = DB::selectOne(
            'SELECT f.id_factura, f.id_tipo_comprobante, f.id_estado_factura, fn_factura_nro(f.id_factura) AS nro
               FROM factura f WHERE f.id_factura = ?', [$idFactura]
        );
        if (! $f) {
            return ['ok' => false, 'mensaje' => 'Esa factura no existe.', 'cdc' => null];
        }
        if ((int) $f->id_estado_factura === 2) {
            return ['ok' => false, 'mensaje' => 'Ese comprobante está anulado: no se manda a la DNIT.', 'cdc' => null];
        }
        if (! self::esElectronico((int) $f->id_tipo_comprobante)) {
            return ['ok' => false, 'mensaje' => 'Ese comprobante es interno del salón y no se declara.', 'cdc' => null];
        }

        $ya = self::estado($idFactura);
        if ($ya && $ya->estado === 'ENVIADO') {
            return ['ok' => true, 'mensaje' => 'Ya estaba declarado. CDC ' . $ya->cdc . '.', 'cdc' => $ya->cdc];
        }

        try {
            $txt = self::armarTxt($idFactura);
        } catch (Throwable $e) {
            self::guardar($idFactura, 'RECHAZADO', null, null, [], $e->getMessage());

            return ['ok' => false, 'mensaje' => $e->getMessage(), 'cdc' => null];
        }

        $r = config('sifen.modo') === 'http' ? self::porHttp($txt) : self::simulado($idFactura);

        self::guardar($idFactura, $r['estado'], $r['cdc'], $r['track_id'] ?? null, $r, $r['mensaje']);

        if ($r['estado'] === 'ENVIADO') {
            Auditoria::registrar('SIFEN', 'Facturacion', 'factura', $idFactura,
                'Comprobante ' . $f->nro . ' declarado. CDC ' . $r['cdc']);
        }

        return ['ok' => $r['estado'] === 'ENVIADO', 'mensaje' => $r['mensaje'], 'cdc' => $r['cdc']];
    }

    /** El envío de verdad, contra el Automatizador. */
    private static function porHttp(string $txt): array
    {
        $url = (string) config('sifen.url');
        if ($url === '') {
            return ['estado' => 'PENDIENTE', 'cdc' => null,
                    'mensaje' => 'Falta configurar SIFEN_URL: no hay a dónde mandarlo.'];
        }

        try {
            $resp = Http::withHeaders(['X-API-Token' => (string) config('sifen.token')])
                ->withBody($txt, 'text/plain; charset=utf-8')
                ->timeout((int) config('sifen.timeout', 60))
                ->post($url);
        } catch (Throwable $e) {
            // Puede haberse emitido igual del otro lado: queda PENDIENTE para
            // reintentar a mano, nunca RECHAZADO.
            return ['estado' => 'PENDIENTE', 'cdc' => null,
                    'mensaje' => 'No se pudo llegar al servicio (' . $e->getMessage()
                                 . '). Puede haberse emitido igual: fijate antes de reenviar.'];
        }

        $j = $resp->json();

        if ($resp->successful() && ! empty($j['ok']) && ! empty($j['facturas'][0]['cdc'])) {
            $d = $j['facturas'][0];

            return [
                'estado' => 'ENVIADO',
                'cdc' => (string) $d['cdc'],
                'track_id' => (string) ($d['track_id'] ?? ''),
                'kude_url' => (string) ($d['kude_url'] ?? ''),
                'xml_url' => (string) ($d['xml_url'] ?? ''),
                'mensaje' => 'Declarado. CDC ' . $d['cdc'] . '.',
            ];
        }

        $motivo = (string) ($j['error'] ?? ('El servicio contestó ' . $resp->status() . '.'));

        // 4xx es un problema de los datos y repetirlo da lo mismo; 5xx o un
        // corte es del otro lado y sí se puede reintentar.
        return [
            'estado' => $resp->clientError() ? 'RECHAZADO' : 'PENDIENTE',
            'cdc' => null,
            'mensaje' => $motivo,
        ];
    }

    /**
     * Modo simulado: arma el TXT de verdad pero no sale de acá.
     *
     * Sirve para ver el circuito completo —emitir, declarar, guardar el CDC,
     * mostrarlo en el comprobante— sin depender de que el Automatizador esté
     * publicado. El CDC arranca con `0` para que se note de una que no es real.
     */
    private static function simulado(int $idFactura): array
    {
        $cdc = '0' . str_pad((string) $idFactura, 8, '0', STR_PAD_LEFT)
             . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT)
             . str_pad((string) time(), 10, '0', STR_PAD_LEFT)
             . str_pad((string) random_int(0, 999999999), 20, '0', STR_PAD_LEFT);

        return [
            'estado' => 'ENVIADO',
            'cdc' => substr($cdc, 0, 44),
            'track_id' => 'SIM-' . $idFactura,
            'mensaje' => 'Declarado en modo simulado: el comprobante NO se mandó a la DNIT.',
        ];
    }

    /** Deja escrito el resultado del envío. Una fila por factura. */
    private static function guardar(int $idFactura, string $estado, ?string $cdc, ?string $trackId, array $r, string $mensaje): void
    {
        DB::statement(
            'INSERT INTO factura_electronica
                (id_factura, cdc, estado, track_id, kude_url, xml_url, mensaje, intentos, fecha_envio)
             VALUES (:id, :cdc, :estado, :track, :kude, :xml, :msg, 1, NOW())
             ON DUPLICATE KEY UPDATE
                cdc = COALESCE(VALUES(cdc), cdc),
                estado = VALUES(estado),
                track_id = COALESCE(VALUES(track_id), track_id),
                kude_url = COALESCE(VALUES(kude_url), kude_url),
                xml_url = COALESCE(VALUES(xml_url), xml_url),
                mensaje = VALUES(mensaje),
                intentos = intentos + 1,
                fecha_envio = NOW()',
            [
                'id' => $idFactura, 'cdc' => $cdc, 'estado' => $estado,
                'track' => $trackId ?: null,
                'kude' => ($r['kude_url'] ?? '') ?: null,
                'xml' => ($r['xml_url'] ?? '') ?: null,
                'msg' => mb_substr($mensaje, 0, 500),
            ]
        );
    }
}
