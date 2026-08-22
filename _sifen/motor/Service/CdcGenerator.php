<?php

declare(strict_types=1);
/*
 * DOCUMENTACION DEL ARCHIVO
 * Que hace: Generador CDC PHP. Arma el identificador de control de 44 digitos cuando se usa el flujo PHP.
 * Donde se usa: forma parte del flujo de facturacion electronica SIFEN documentado en docs/DOCUMENTACION_TECNICA_COMPLETA.md.
 * Nota: los detalles de variables, palabras reservadas y cambios posibles estan centralizados en docs/GUIA_COMENTARIOS_CODIGO.md para no duplicar ruido en cada linea.
 */


namespace App\Service;

use DateTimeImmutable;
use RuntimeException;

/**
 * Comentario de codigo: clase CdcGenerator. Agrupa la responsabilidad principal indicada en la cabecera del archivo.
 */
final class CdcGenerator
{
    /**
     * Genera el CDC para Factura Electrónica según MT v150.
     *
     * Conformación:
     * Tipo de Documento (2)
     * + RUC Emisor (8)
     * + DV Emisor (1)
     * + Establecimiento (3)
     * + Punto de Expedición (3)
     * + Número de Documento (7)
     * + Tipo de Contribuyente (1)
     * + Fecha de Emisión AAAAMMDD (8)
     * + Tipo de Emisión (1)
     * + Código de Seguridad (9)
     * + DV final módulo 11 (1)
     *
     * Base sin DV final = 43 posiciones
     * CDC final = 44 posiciones
     */
    /**
     * Comentario de codigo: Genera el artefacto o valor final requerido por el flujo.
     */
    public function generate(array $emitter, array $invoice, string $dCodSeg): string
    {
        $tipoDoc = str_pad((string) ($invoice['tipo_documento'] ?? 1), 2, '0', STR_PAD_LEFT);

        $ruc = str_pad(
            preg_replace('/\D+/', '', (string) ($emitter['ruc'] ?? '')),
            8,
            '0',
            STR_PAD_LEFT
        );

        $dvRuc = preg_replace('/\D+/', '', (string) ($emitter['dv'] ?? ''));
        $dvRuc = str_pad($dvRuc, 1, '0', STR_PAD_LEFT);

        $est = str_pad((string) ($invoice['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT);
        $pto = str_pad((string) ($invoice['punto'] ?? '001'), 3, '0', STR_PAD_LEFT);

        $num = str_pad(
            preg_replace('/\D+/', '', (string) ($invoice['numero'] ?? '0')),
            7,
            '0',
            STR_PAD_LEFT
        );

        // ESTE ERA EL CAMPO FALTANTE
        $tipoContribuyente = str_pad((string) ($emitter['tipo_contribuyente'] ?? 2), 1, '0', STR_PAD_LEFT);

        $fecha = new DateTimeImmutable((string) ($invoice['fecha_emision'] ?? 'now'));
        $fechaPart = $fecha->format('Ymd');

        $tipoEmision = str_pad((string) ($invoice['tipo_emision'] ?? 1), 1, '0', STR_PAD_LEFT);

        if (!preg_match('/^\d{9}$/', $dCodSeg)) {
            throw new RuntimeException('dCodSeg debe ser numérico de 9 dígitos.');
        }

        $base = $tipoDoc
            . $ruc
            . $dvRuc
            . $est
            . $pto
            . $num
            . $tipoContribuyente
            . $fechaPart
            . $tipoEmision
            . $dCodSeg;

        if (strlen($base) !== 43) {
            throw new RuntimeException('La base del CDC no tiene 43 posiciones. Obtenido: ' . strlen($base));
        }

        $dvFinal = Mod11::calculate($base);

        return $base . (string) $dvFinal;
    }

    /**
     * Comentario de codigo: Metodo del flujo de negocio; leer parametros y retornos para ver que datos transforma.
     */
    public function generateSecurityCode(): string
    {
        return str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT);
    }
}
