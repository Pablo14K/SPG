<?php

declare(strict_types=1);
/*
 * DOCUMENTACION DEL ARCHIVO
 * Que hace: Calculadora de totales fiscales. Calcula exentas, gravadas, IVA 5/10 y total general.
 * Donde se usa: forma parte del flujo de facturacion electronica SIFEN documentado en docs/DOCUMENTACION_TECNICA_COMPLETA.md.
 * Nota: los detalles de variables, palabras reservadas y cambios posibles estan centralizados en docs/GUIA_COMENTARIOS_CODIGO.md para no duplicar ruido en cada linea.
 */


namespace App\Service;

/**
 * Comentario de codigo: clase TotalsCalculator. Agrupa la responsabilidad principal indicada en la cabecera del archivo.
 */
final class TotalsCalculator
{
    public function normalizeItems(array $items, int $decimals = 0): array
    {
        // $decimals = cantidad de decimales de la moneda (Manual SIFEN cap. 7, Tabla de Monedas).
        // Guaraní (PYG) = 0 decimales → todos los montos deben ser ENTEROS o SIFEN rechaza.
        $out = [];
        foreach ($items as $item) {
            $qty = (float) $item['cantidad'];
            $price = (float) $item['precio_unitario'];
            $descItem = (float) ($item['descuento_item'] ?? 0);
            $descGlobal = (float) ($item['descuento_global_item'] ?? 0);
            $antItem = (float) ($item['anticipo_item'] ?? 0);
            $antGlobal = (float) ($item['anticipo_global_item'] ?? 0);
            $ea008 = round(($price - $descItem - $descGlobal - $antItem - $antGlobal) * $qty, $decimals);

            $afec = (int) ($item['afectacion_iva'] ?? 1);
            $prop = (float) ($item['proporcion_iva'] ?? 100);
            $tasa = (float) ($item['tasa_iva'] ?? 0);

            $base = 0.0;
            $iva = 0.0;
            if (in_array($afec, [1, 4], true) && $tasa > 0) {
                $base = round(($ea008 * ($prop / 100)) / (1 + ($tasa / 100)), $decimals);
                $iva = round($base * ($tasa / 100), $decimals);
            }

            $item['ea008'] = $ea008;
            $item['base_gravada_iva'] = $base;
            $item['liquidacion_iva'] = $iva;
            $item['total_linea'] = $ea008;
            $out[] = $item;
        }
        return $out;
    }

    /**
     * Comentario de codigo: Metodo del flujo de negocio; leer parametros y retornos para ver que datos transforma.
     */
    public function calculateInvoiceTotals(array $invoice, array $items, int $decimals = 0): array
    {
        $f = [
            'subtotal_exenta' => 0.0,
            'subtotal_exonerada' => 0.0,
            'subtotal_5' => 0.0,
            'subtotal_10' => 0.0,
            'total_descuento' => 0.0,
            'total_anticipo' => 0.0,
            'redondeo' => 0.0,
            'total_bruto' => 0.0,
            'total_neto' => 0.0,
            'iva_5' => 0.0,
            'iva_10' => 0.0,
            'total_iva' => 0.0,
            'base_gravada_5' => 0.0,
            'base_gravada_10' => 0.0,
            'total_base_gravada' => 0.0,
            'total_gs' => null,
        ];

        foreach ($items as $item) {
            $ea008 = (float) $item['ea008'];
            $tasa = (float) ($item['tasa_iva'] ?? 0);
            $afec = (int) ($item['afectacion_iva'] ?? 1);
            $f['total_descuento'] += (float) ($item['descuento_item'] ?? 0) + (float) ($item['descuento_global_item'] ?? 0);
            $f['total_anticipo'] += (float) ($item['anticipo_item'] ?? 0) + (float) ($item['anticipo_global_item'] ?? 0);
            $f['total_bruto'] += $ea008;

            if ($afec === 3) {
                $f['subtotal_exenta'] += $ea008;
            } elseif ($afec === 2) {
                $f['subtotal_exonerada'] += $ea008;
            } elseif (in_array($afec, [1,4], true) && (int) $tasa === 5) {
                $f['subtotal_5'] += $ea008;
                $f['iva_5'] += (float) $item['liquidacion_iva'];
                $f['base_gravada_5'] += (float) $item['base_gravada_iva'];
            } elseif (in_array($afec, [1,4], true) && (int) $tasa === 10) {
                $f['subtotal_10'] += $ea008;
                $f['iva_10'] += (float) $item['liquidacion_iva'];
                $f['base_gravada_10'] += (float) $item['base_gravada_iva'];
            }
        }

        // Redondeo a los decimales de la moneda (0 en Guaraníes): SIFEN exige montos sin
        // decimales para PYG; emitir fracciones provoca rechazo por regla de negocio.
        foreach ($f as $k => $v) {
            if (is_float($v)) {
                $f[$k] = round($v, $decimals);
            }
        }

        $f['total_neto'] = round($f['total_bruto'] - $f['redondeo'], $decimals);
        $f['total_iva'] = round($f['iva_5'] + $f['iva_10'], $decimals);
        $f['total_base_gravada'] = round($f['base_gravada_5'] + $f['base_gravada_10'], $decimals);
        if (($invoice['moneda'] ?? 'PYG') !== 'PYG') {
            // El total convertido a guaraníes (dTotalGs) siempre va sin decimales.
            $f['total_gs'] = round($f['total_neto'] * (float) ($invoice['tipo_cambio'] ?? 1), 0);
        }

        return $f;
    }
}
