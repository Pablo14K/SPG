<?php

declare(strict_types=1);
/*
 * DOCUMENTACION DEL ARCHIVO
 * Que hace: Generador QR PHP de respaldo. Construye cadena dCarQR segun Manual Tecnico v150.
 * Donde se usa: forma parte del flujo de facturacion electronica SIFEN documentado en docs/DOCUMENTACION_TECNICA_COMPLETA.md.
 * Nota: los detalles de variables, palabras reservadas y cambios posibles estan centralizados en docs/GUIA_COMENTARIOS_CODIGO.md para no duplicar ruido en cada linea.
 */


namespace App\Service;

use DateTimeImmutable;
use RuntimeException;

/**
 * Comentario de codigo: clase QrGenerator. Agrupa la responsabilidad principal indicada en la cabecera del archivo.
 */
final class QrGenerator
{
    public function generate(array $config, array $payload, string $cdc, string $digestValueBase64): string
    {
        $base = (($config['mode'] ?? 'mock') === 'prod')
            ? (string) $config['qr_prod_base']
            : (string) $config['qr_test_base'];

        $receiver = $payload['cliente'];
        $document = $payload['documento'];
        $totals = $payload['totales'];
        $idCsc = (string) ($payload['emisor']['id_csc'] ?? '0001');
        $csc = (string) ($payload['emisor']['csc'] ?? '');
        if ($csc === '') {
            throw new RuntimeException('Falta el CSC del emisor para generar el QR.');
        }

        $fecha = new DateTimeImmutable((string) $document['fecha_emision']);
        $fechaHex = bin2hex($fecha->format('Y-m-d\TH:i:s'));
        $digestHex = bin2hex($digestValueBase64);

        $receiverId = '0';
        if (($receiver['ruc'] ?? '') !== '') {
            $receiverId = preg_replace('/\D+/', '', (string) $receiver['ruc']);
        } elseif (($receiver['numero_documento'] ?? '') !== '') {
            $receiverId = preg_replace('/\D+/', '', (string) $receiver['numero_documento']);
        }
        if ($receiverId === '') {
            $receiverId = '0';
        }

        $params = [
            'nVersion' => '150',
            'Id' => $cdc,
            'dFeEmiDE' => $fechaHex,
            'dRucRec' => $receiverId,
            'dTotGralOpe' => $this->normalizeNumberForUrl((float) ($totals['total_neto'] ?? 0)),
            'dTotIVA' => $this->normalizeNumberForUrl((float) ($totals['total_iva'] ?? 0)),
            'cItems' => (string) count($payload['items']),
            'DigestValue' => $digestHex,
            'IdCSC' => $idCsc,
        ];

        $step1 = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $step2 = $step1 . $csc;
        $hash = hash('sha256', $step2);

        return $base . $step1 . '&cHashQR=' . $hash;
    }

    /**
     * Comentario de codigo: Metodo del flujo de negocio; leer parametros y retornos para ver que datos transforma.
     */
    private function normalizeNumberForUrl(float $value): string
    {
        if (abs($value - round($value)) < 0.00000001) {
            return (string) (int) round($value);
        }
        $str = rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.');
        return $str === '' ? '0' : $str;
    }
}
