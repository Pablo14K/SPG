<?php

declare(strict_types=1);
/*
 * DOCUMENTACION DEL ARCHIVO
 * Que hace: Mapea registros SQL a estructura SIFEN. Traduce invoice/items/customer a campos usados por XML, KuDE y Node.
 * Donde se usa: forma parte del flujo de facturacion electronica SIFEN documentado en docs/DOCUMENTACION_TECNICA_COMPLETA.md.
 * Nota: los detalles de variables, palabras reservadas y cambios posibles estan centralizados en docs/GUIA_COMENTARIOS_CODIGO.md para no duplicar ruido en cada linea.
 */


namespace App\Service;

/**
 * Toma datos crudos de MySQL y los convierte al modelo interno del emisor / cliente /
 * documento / ítems / pagos. Después ese modelo se usa para construir el XML v150.
 */
final class InvoiceMapper
{
    /**
     * Comentario de codigo: Inicializa dependencias y configuracion que usara esta clase.
     */
    public function __construct(private ?TotalsCalculator $totalsCalculator = null)
    {
        $this->totalsCalculator ??= new TotalsCalculator();
    }

    /**
     * Comentario de codigo: Metodo del flujo de negocio; leer parametros y retornos para ver que datos transforma.
     */
    public function buildPayload(array $emitter, array $invoice, array $sifenConfig = []): array
    {
        // Decimales según la moneda (Manual SIFEN: Guaraní = 0). Determina el redondeo de
        // TODOS los montos para no emitir fracciones en PYG (rechazo por regla de negocio).
        $decimals = strtoupper((string) ($invoice['moneda'] ?? 'PYG')) === 'PYG' ? 0 : 2;
        $items = $this->totalsCalculator->normalizeItems($invoice['items'], $decimals);
        $totals = $this->totalsCalculator->calculateInvoiceTotals($invoice, $items, $decimals);

        $emitterName = (string) $emitter['razon_social'];
        // Manual SIFEN v150 cap. 7.7 (campo D105) — En ambiente de pruebas el nombre
        // del emisor debe contener obligatoriamente el literal "DE generado en ambiente
        // de prueba - sin valor comercial ni fiscal". Si el usuario configuró un
        // test_label custom se respeta; si no, se aplica el literal oficial.
        $defaultTestLabel = 'DE generado en ambiente de prueba - sin valor comercial ni fiscal';
        $testLabel = trim((string) ($sifenConfig['test_label'] ?? $defaultTestLabel));
        if (($emitter['ambiente'] ?? 'test') === 'test' && $testLabel !== '' && !str_contains($emitterName, $testLabel)) {
            $emitterName .= ' - ' . $testLabel;
        }

        $payments = [];
        foreach ($invoice['payments'] as $payment) {
            $payments[] = [
                'tipo_pago' => (int) $payment['tipo_pago'],
                'descripcion_tipo_pago' => (string) $payment['descripcion_tipo_pago'],
                'monto' => (float) $payment['monto'],
                'moneda' => (string) $payment['moneda'],
                'descripcion_moneda' => (string) $payment['descripcion_moneda'],
                'tipo_cambio' => $payment['tipo_cambio'] !== null ? (float) $payment['tipo_cambio'] : null,
                'fecha_pago' => (string) $payment['fecha_pago'],
            ];
        }

        return [
            'emisor' => [
                'ambiente' => (string) $emitter['ambiente'],
                'ruc' => (string) $emitter['ruc'],
                'dv' => (string) $emitter['dv'],
                'tipo_contribuyente' => (int) $emitter['tipo_contribuyente'],
                'tipo_regimen' => (string) ($emitter['tipo_regimen'] ?? ''),
                'razon_social' => $emitterName,
                'razon_social_original' => (string) $emitter['razon_social'],
                'nombre_fantasia' => (string) ($emitter['nombre_fantasia'] ?? ''),
                'direccion' => (string) $emitter['direccion'],
                'numero_casa' => (string) $emitter['numero_casa'],
                'codigo_departamento' => (string) $emitter['codigo_departamento'],
                'descripcion_departamento' => (string) $emitter['descripcion_departamento'],
                'codigo_distrito' => (string) ($emitter['codigo_distrito'] ?? ''),
                'descripcion_distrito' => (string) ($emitter['descripcion_distrito'] ?? ''),
                'codigo_ciudad' => (string) $emitter['codigo_ciudad'],
                'descripcion_ciudad' => (string) $emitter['descripcion_ciudad'],
                'telefono' => (string) $emitter['telefono'],
                'email' => (string) $emitter['email'],
                'codigo_actividad_economica' => (string) $emitter['codigo_actividad_economica'],
                'descripcion_actividad_economica' => (string) $emitter['descripcion_actividad_economica'],
                // Del local que emitio. **Son para el KuDE, no para el XML**:
                // la DNIT valida la ciudad contra su tabla de codigos y el
                // sistema de origen manda texto, asi que el codigo sigue
                // siendo el del .env y estos dos solo se imprimen.
                'sucursal_nombre' => (string) ($emitter['sucursal_nombre'] ?? ''),
                'sucursal_ciudad' => (string) ($emitter['sucursal_ciudad'] ?? ''),
                'timbrado_numero' => (string) $emitter['timbrado_numero'],
                'timbrado_inicio' => (string) $emitter['timbrado_inicio'],
                'timbrado_fin' => (string) $emitter['timbrado_fin'],
                'establecimiento' => (string) $invoice['establecimiento'],
                'punto' => (string) $invoice['punto'],
                'id_csc' => (string) ($emitter['id_csc'] ?? ''),
                'csc' => (string) ($emitter['csc'] ?? ''),
                'info_adicional_kude' => (string) ($emitter['info_adicional_kude'] ?? ''),
            ],
            'documento' => [
                'tipo_documento' => (int) $invoice['tipo_documento'],
                'descripcion_tipo_documento' => (string) $invoice['descripcion_tipo_documento'],
                'numero' => (string) $invoice['numero'],
                'serie' => (string) ($invoice['serie'] ?? ''),
                'establecimiento' => (string) $invoice['establecimiento'],
                'punto' => (string) $invoice['punto'],
                'fecha_emision' => (string) $invoice['fecha_emision'],
                'fecha_firma' => (string) ($invoice['fecha_firma'] ?? $invoice['fecha_emision']),
                'tipo_emision' => (int) $invoice['tipo_emision'],
                'descripcion_tipo_emision' => (string) $invoice['descripcion_tipo_emision'],
                'tipo_transaccion' => (int) $invoice['tipo_transaccion'],
                'descripcion_tipo_transaccion' => (string) $invoice['descripcion_tipo_transaccion'],
                'tipo_impuesto' => (int) $invoice['tipo_impuesto'],
                'descripcion_tipo_impuesto' => (string) $invoice['descripcion_tipo_impuesto'],
                'moneda' => (string) $invoice['moneda'],
                'descripcion_moneda' => (string) $invoice['descripcion_moneda'],
                'condicion_operacion' => (int) $invoice['condicion_operacion'],
                'descripcion_condicion_operacion' => (string) $invoice['descripcion_condicion_operacion'],
                'indicador_presencia' => (int) $invoice['indicador_presencia'],
                'descripcion_indicador_presencia' => (string) $invoice['descripcion_indicador_presencia'],
                'descripcion' => (string) ($invoice['descripcion'] ?? ''),
                'info_emisor' => (string) ($invoice['info_emisor'] ?? ''),
                'info_fisco' => (string) ($invoice['info_fisco'] ?? ''),
                'servicio_ciclo' => (string) ($invoice['servicio_ciclo'] ?? ''),
                'servicio_vencimiento' => (string) ($invoice['servicio_vencimiento'] ?? ''),
                'servicio_periodo_desde' => (string) ($invoice['servicio_periodo_desde'] ?? ''),
                'servicio_periodo_hasta' => (string) ($invoice['servicio_periodo_hasta'] ?? ''),
                'servicio_ruta' => (string) ($invoice['servicio_ruta'] ?? ''),
                'servicio_categoria' => (string) ($invoice['servicio_categoria'] ?? ''),
                'servicio_cuenta' => (string) ($invoice['servicio_cuenta'] ?? ''),
                'servicio_medidor_anterior' => ($invoice['servicio_medidor_anterior'] ?? null) !== null ? (float) $invoice['servicio_medidor_anterior'] : null,
                'servicio_medidor_actual' => ($invoice['servicio_medidor_actual'] ?? null) !== null ? (float) $invoice['servicio_medidor_actual'] : null,
                'servicio_consumo_mes' => ($invoice['servicio_consumo_mes'] ?? null) !== null ? (float) $invoice['servicio_consumo_mes'] : null,
            ],
            'cliente' => [
                'naturaleza_receptor' => (int) $invoice['naturaleza_receptor'],
                'tipo_operacion' => (int) $invoice['tipo_operacion'],
                'codigo_pais' => (string) $invoice['codigo_pais'],
                'descripcion_pais' => (string) $invoice['descripcion_pais'],
                'tipo_contribuyente' => $invoice['tipo_contribuyente'] !== null ? (int) $invoice['tipo_contribuyente'] : null,
                'ruc' => (string) ($invoice['cliente_ruc'] ?? ''),
                'dv' => (string) ($invoice['cliente_dv'] ?? ''),
                'tipo_documento' => $invoice['cliente_tipo_documento'] !== null ? (int) $invoice['cliente_tipo_documento'] : null,
                'descripcion_tipo_documento' => (string) ($invoice['cliente_descripcion_tipo_documento'] ?? ''),
                'numero_documento' => (string) ($invoice['cliente_numero_documento'] ?? ''),
                'nombre' => (string) $invoice['cliente_nombre'],
                'direccion' => (string) ($invoice['cliente_direccion'] ?? ''),
                'numero_casa' => (string) ($invoice['cliente_numero_casa'] ?? ''),
                'codigo_departamento' => (string) ($invoice['cliente_codigo_departamento'] ?? ''),
                'descripcion_departamento' => (string) ($invoice['cliente_descripcion_departamento'] ?? ''),
                'codigo_ciudad' => (string) ($invoice['cliente_codigo_ciudad'] ?? ''),
                'descripcion_ciudad' => (string) ($invoice['cliente_descripcion_ciudad'] ?? ''),
                'telefono' => (string) ($invoice['cliente_telefono'] ?? ''),
                'email' => (string) ($invoice['cliente_email'] ?? ''),
                'codigo_cliente' => (string) ($invoice['cliente_codigo_cliente'] ?? ''),
            ],
            'items' => $items,
            'pagos' => $payments,
            'totales' => $totals,
        ];
    }
}
