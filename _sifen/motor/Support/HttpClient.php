<?php

declare(strict_types=1);
/*
 * DOCUMENTACION DEL ARCHIVO
 * Que hace: Cliente HTTP basico PHP. Se usa para llamadas POST/GET hacia servicios externos o Node.
 * Donde se usa: forma parte del flujo de facturacion electronica SIFEN documentado en docs/DOCUMENTACION_TECNICA_COMPLETA.md.
 * Nota: los detalles de variables, palabras reservadas y cambios posibles estan centralizados en docs/GUIA_COMENTARIOS_CODIGO.md para no duplicar ruido en cada linea.
 */


namespace App\Support;

use RuntimeException;

/**
 * Comentario de codigo: clase HttpClient. Agrupa la responsabilidad principal indicada en la cabecera del archivo.
 */
final class HttpClient
{
    /**
     * Cliente HTTP mínimo basado en streams.
     * Sirve para la demo sin depender de cURL ni librerías externas.
     */
    public function postJson(string $url, array $payload, int $timeoutSeconds = 20): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('No se pudo serializar el payload a JSON.');
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Content-Length: ' . strlen($body),
                ]),
                'content' => $body,
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];
        $statusLine = $headers[0] ?? 'HTTP/1.1 500 Internal Server Error';
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        $status = isset($matches[1]) ? (int) $matches[1] : 500;

        if ($result === false) {
            throw new RuntimeException('No se pudo conectar con el endpoint: ' . $url);
        }

        $decoded = json_decode($result, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('La respuesta del endpoint no es JSON válido: ' . $result);
        }

        return [
            'status' => $status,
            'body' => $decoded,
            'raw' => $result,
        ];
    }
}
