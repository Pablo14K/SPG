<?php
declare(strict_types=1);

namespace Automatizador;

use RuntimeException;

/**
 * Convierte una factura "cruda" del .txt + los datos del emisor (config) en los
 * arreglos $emitter / $invoice que espera App\Service\InvoiceMapper, rellenando
 * los catálogos obligatorios de SIFEN v150 con valores por defecto sensatos
 * (factura electrónica, emisión normal, IVA, operación presencial, etc.).
 */
final class InvoiceFactory
{
    /** Tipos de pago SIFEN (Manual v150, campo iTiPago). */
    private const PAGOS = [
        1 => 'Efectivo',
        2 => 'Cheque',
        3 => 'Tarjeta de crédito',
        4 => 'Tarjeta de débito',
        5 => 'Transferencia',
        6 => 'Giro',
        7 => 'Billetera electrónica',
        8 => 'Tarjeta empresarial',
        9 => 'Vale',
        10 => 'Retención',
    ];

    /** Tipos de transaccion SIFEN (Manual v150, campo D011 iTipTra). */
    private const TRANSACCIONES = [
        1  => 'Venta de mercaderia',
        2  => 'Prestacion de servicios',
        3  => 'Mixto (venta de mercaderia y servicios)',
        4  => 'Venta de activo fijo',
        5  => 'Venta de divisas',
        6  => 'Compra de divisas',
        7  => 'Promocion o entrega de muestras',
        8  => 'Donacion',
        9  => 'Anticipo',
        10 => 'Compra de productos',
        11 => 'Compra de servicios',
        12 => 'Venta de credito fiscal',
    ];

    private const MONEDAS = [
        'PYG' => 'Guarani',
        'USD' => 'Dolar Americano',
        'BRL' => 'Real',
        'ARS' => 'Peso Argentino',
        'EUR' => 'Euro',
    ];

    public function __construct(private array $emisorConfig)
    {
    }

    /** @return array{emitter:array,invoice:array} */
    public function build(array $cruda): array
    {
        $fac = $cruda['fac'];
        $cli = $cruda['cli'];

        $moneda = strtoupper((string)($fac['moneda'] ?: 'PYG'));

        // Sin valor en el .txt vale 1, que es como se comportaba antes: un
        // archivo viejo no cambia de significado al actualizar.
        $tipoTra = (int)($fac['tipo_transaccion'] ?? 0);
        if (!isset(self::TRANSACCIONES[$tipoTra])) {
            $tipoTra = 1;
        }
        $descMoneda = self::MONEDAS[$moneda] ?? $moneda;
        $condicion = (int)($fac['condicion'] ?: 1); // 1=contado, 2=crédito

        // ── Receptor ────────────────────────────────────────────────────────
        $tipoCli = strtoupper((string)($cli['tipo'] ?: 'CI'));
        $tieneRuc = ($tipoCli === 'RUC');
        $clienteRuc = '';
        $clienteDv = '';
        $clienteNumDoc = '';
        if ($tieneRuc) {
            // documento viene como "80012345-6"
            $partes = explode('-', (string)$cli['documento']);
            $clienteRuc = trim($partes[0] ?? '');
            $clienteDv  = trim($partes[1] ?? '');
            if ($clienteRuc === '' || $clienteDv === '') {
                throw new RuntimeException("Cliente con RUC mal formado: '{$cli['documento']}' (esperado RUC-DV).");
            }
        } else {
            $doc = trim((string)$cli['documento']);
            $clienteNumDoc = ($doc === '' || strtoupper($doc) === 'CF') ? '0' : $doc;
        }

        // ── Ítems ───────────────────────────────────────────────────────────
        $items = [];
        foreach ($cruda['items'] as $it) {
            $iva = (int)($it['iva']);
            if (!in_array($iva, [0, 5, 10], true)) {
                throw new RuntimeException("IVA inválido '{$it['iva']}' en ítem '{$it['codigo']}' (use 10, 5 o 0).");
            }
            $afectacion = $iva === 0 ? 3 : 1; // 1=gravado, 3=exento
            $items[] = [
                'codigo'                    => (string)($it['codigo'] !== '' ? $it['codigo'] : '001'),
                'descripcion'               => (string)$it['descripcion'],
                'unidad_codigo'             => '77',   // 77 = UNI (unidad)
                'unidad_descripcion'        => 'UNI',
                'cantidad'                  => (float)$it['cantidad'],
                'precio_unitario'           => (float)$it['precio_unitario'],
                'afectacion_iva'            => $afectacion,
                'descripcion_afectacion_iva'=> $afectacion === 3 ? 'Exento' : 'Gravado IVA',
                'proporcion_iva'            => 100,
                'tasa_iva'                  => $iva,
            ];
        }

        // ── Total del documento (IVA incluido), CALCULADO desde los ítems ────
        // Mismo criterio que la calculadora fiscal: se redondea cada línea según
        // los decimales de la moneda (PYG = 0). Así los pagos cuadran exacto y se
        // evitan errores humanos (el monto ya no viene en el .txt).
        $decimales = ($moneda === 'PYG') ? 0 : 2;
        $totalDoc  = 0.0;
        foreach ($items as $it) {
            $totalDoc += round($it['cantidad'] * $it['precio_unitario'], $decimales);
        }
        $totalDoc = round($totalDoc, $decimales);

        // ── Pagos ────────────────────────────────────────────────────────────
        // El MONTO lo calcula el sistema (la línea PAG solo trae el medio de pago).
        // El total del documento se reparte entre los medios indicados, de modo que
        // la suma SIEMPRE coincida con el total. Si no se indica ningún medio y es
        // contado, se asume Efectivo por el total.
        $tiposPago = [];
        foreach ($cruda['pagos'] as $pg) {
            $tiposPago[] = (int)($pg['tipo'] ?: 1);
        }
        if ($tiposPago === [] && $condicion === 1) {
            $tiposPago = [1]; // Efectivo por defecto
        }
        $pagos = [];
        $n = count($tiposPago);
        if ($n > 0) {
            $cuota = round($totalDoc / $n, $decimales);
            foreach ($tiposPago as $idx => $tipoPago) {
                // El último pago absorbe el redondeo para que la suma dé exacto.
                $monto = ($idx === $n - 1) ? round($totalDoc - $cuota * ($n - 1), $decimales) : $cuota;
                $pagos[] = [
                    'tipo_pago'             => $tipoPago,
                    'descripcion_tipo_pago' => self::PAGOS[$tipoPago] ?? 'Efectivo',
                    'monto'                 => $monto,
                    'moneda'                => $moneda,
                    'descripcion_moneda'    => $descMoneda,
                    'tipo_cambio'           => null,
                    'fecha_pago'            => substr((string)$fac['fecha'], 0, 10),
                ];
            }
        }

        $emitter = $this->buildEmitter($cruda['emi'] ?? []);

        $invoice = [
            // documento
            'tipo_documento'                 => 1,
            'descripcion_tipo_documento'     => 'Factura electronica',
            'numero'                         => (string)$fac['numero'],
            'serie'                          => '',
            'establecimiento'                => (string)$fac['establecimiento'],
            'punto'                          => (string)$fac['punto'],
            'fecha_emision'                  => $this->fechaHora((string)$fac['fecha']),
            'fecha_firma'                    => $this->fechaHora((string)$fac['fecha']),
            'tipo_emision'                   => 1,
            'descripcion_tipo_emision'       => 'Normal',
            'tipo_transaccion'               => $tipoTra,
            'descripcion_tipo_transaccion'   => self::TRANSACCIONES[$tipoTra] ?? 'Venta de mercaderia',
            'tipo_impuesto'                  => 1,
            'descripcion_tipo_impuesto'      => 'IVA',
            'moneda'                         => $moneda,
            'descripcion_moneda'             => $descMoneda,
            'condicion_operacion'            => $condicion,
            'descripcion_condicion_operacion'=> $condicion === 2 ? 'Credito' : 'Contado',
            'indicador_presencia'            => 1,
            'descripcion_indicador_presencia'=> 'Operacion presencial',
            'descripcion'                    => '',
            // receptor
            'naturaleza_receptor'            => $tieneRuc ? 1 : 2,
            'tipo_operacion'                 => $tieneRuc ? 1 : 2, // 1=B2B, 2=B2C
            'codigo_pais'                    => 'PRY',
            'descripcion_pais'               => 'Paraguay',
            'tipo_contribuyente'             => $tieneRuc ? 1 : null,
            'cliente_ruc'                    => $clienteRuc,
            'cliente_dv'                     => $clienteDv,
            'cliente_tipo_documento'         => $tieneRuc ? null : 1,
            'cliente_descripcion_tipo_documento' => $tieneRuc ? '' : 'Cedula paraguaya',
            'cliente_numero_documento'       => $clienteNumDoc,
            'cliente_nombre'                 => (string)$cli['nombre'],
            'cliente_direccion'              => (string)$cli['direccion'],
            'cliente_telefono'               => (string)$cli['telefono'],
            'cliente_email'                  => (string)$cli['email'],
            // detalle
            'items'                          => $items,
            'payments'                       => $pagos,
        ];

        return ['emitter' => $emitter, 'invoice' => $invoice];
    }

    /**
     * Los datos de quien emite.
     *
     * Salen de config/.env, y **el registro EMI del .txt les gana**. Sin esa
     * linea el KuDE imprimia lo que hubiera en el archivo de ejemplo —"MI
     * EMPRESA S.A.", RUC 80012345-6 con el digito verificador mal, actividad
     * "VENTA AL POR MENOR"— porque el emisor nunca viajaba con la factura.
     *
     * Y no alcanza con cargar el .env una vez: **el emisor cambia con la
     * sucursal**. La direccion y el timbrado son los del local que atendio,
     * igual que el establecimiento del numero impreso.
     *
     * Lo que NO se pisa son los codigos geograficos de SIFEN
     * (departamento/distrito/ciudad): son de una tabla oficial y el sistema
     * de origen manda la ciudad como texto, no su codigo. Pisar la
     * descripcion dejando el codigo viejo haria que el XML se contradiga
     * solo, asi que se cargan una vez en el .env y mandan los dos.
     */
    private function buildEmitter(array $emi = []): array
    {
        $c = $this->emisorConfig;

        $dato = static fn (string $k): string => trim((string)($emi[$k] ?? ''));
        foreach ([
            'razon_social'                    => 'razon_social',
            'ruc'                             => 'ruc',
            'dv'                              => 'dv',
            'direccion'                       => 'direccion',
            'telefono'                        => 'telefono',
            'email'                           => 'email',
            'codigo_actividad_economica'      => 'actividad_cod',
            'descripcion_actividad_economica' => 'actividad_desc',
            'timbrado_numero'                 => 'timbrado',
            'timbrado_inicio'                 => 'timbrado_ini',
            'timbrado_fin'                    => 'timbrado_fin',
        ] as $destino => $origen) {
            if ($dato($origen) !== '') {
                $c[$destino] = $dato($origen);
            }
        }

        // El nombre del local y su ciudad son para el KuDE, no para el XML:
        // con varias sedes, de cual salio el papel no se deduce del numero
        // para quien lo recibe.
        $c['sucursal_nombre'] = $dato('sucursal');
        $c['sucursal_ciudad'] = $dato('ciudad');
        $req = ['ruc', 'dv', 'razon_social', 'timbrado_numero'];
        foreach ($req as $k) {
            if (trim((string)($c[$k] ?? '')) === '') {
                throw new RuntimeException("Falta el dato del emisor '$k' en config/automatizador.php.");
            }
        }
        return [
            'ambiente'                        => (string)($c['ambiente'] ?? 'test'),
            'ruc'                             => (string)$c['ruc'],
            'dv'                              => (string)$c['dv'],
            'tipo_contribuyente'              => (int)($c['tipo_contribuyente'] ?? 2),
            'tipo_regimen'                    => (string)($c['tipo_regimen'] ?? ''),
            'razon_social'                    => (string)$c['razon_social'],
            'nombre_fantasia'                 => (string)($c['nombre_fantasia'] ?? ''),
            'direccion'                       => (string)($c['direccion'] ?? ''),
            'numero_casa'                     => (string)($c['numero_casa'] ?? '0'),
            'codigo_departamento'             => (string)($c['codigo_departamento'] ?? '11'),
            'descripcion_departamento'        => (string)($c['descripcion_departamento'] ?? 'CENTRAL'),
            'codigo_distrito'                 => (string)($c['codigo_distrito'] ?? ''),
            'descripcion_distrito'            => (string)($c['descripcion_distrito'] ?? ''),
            'codigo_ciudad'                   => (string)($c['codigo_ciudad'] ?? '1'),
            'descripcion_ciudad'              => (string)($c['descripcion_ciudad'] ?? 'ASUNCION'),
            'telefono'                        => (string)($c['telefono'] ?? ''),
            'email'                           => (string)($c['email'] ?? ''),
            'codigo_actividad_economica'      => (string)($c['codigo_actividad_economica'] ?? ''),
            'descripcion_actividad_economica' => (string)($c['descripcion_actividad_economica'] ?? ''),
            'timbrado_numero'                 => (string)$c['timbrado_numero'],
            'timbrado_inicio'                 => (string)($c['timbrado_inicio'] ?? ''),
            'timbrado_fin'                    => (string)($c['timbrado_fin'] ?? ''),
            'id_csc'                          => (string)($c['id_csc'] ?? '0001'),
            'csc'                             => (string)($c['csc'] ?? ''),
            'info_adicional_kude'             => (string)($c['info_adicional_kude'] ?? ''),
            'sucursal_nombre'                 => (string)($c['sucursal_nombre'] ?? ''),
            'sucursal_ciudad'                 => (string)($c['sucursal_ciudad'] ?? ''),
        ];
    }

    /** Acepta "2026-06-21" o "2026-06-21 14:30:00" y devuelve "Y-m-d H:i:s". */
    private function fechaHora(string $fecha): string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            return date('Y-m-d H:i:s');
        }
        $ts = strtotime($fecha);
        if ($ts === false) {
            throw new RuntimeException("Fecha inválida: '$fecha' (use AAAA-MM-DD).");
        }
        // si vino sólo la fecha, agregar la hora actual
        if (strlen($fecha) <= 10) {
            return date('Y-m-d', $ts) . ' ' . date('H:i:s');
        }
        return date('Y-m-d H:i:s', $ts);
    }
}
