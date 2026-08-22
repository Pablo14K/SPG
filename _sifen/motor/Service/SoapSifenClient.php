<?php

declare(strict_types=1);
/*
 * DOCUMENTACION DEL ARCHIVO
 * Que hace: Cliente SOAP PHP para SIFEN. Envia XML firmado a web services oficiales cuando no se usa mock.
 * Donde se usa: forma parte del flujo de facturacion electronica SIFEN documentado en docs/DOCUMENTACION_TECNICA_COMPLETA.md.
 * Nota: los detalles de variables, palabras reservadas y cambios posibles estan centralizados en docs/GUIA_COMENTARIOS_CODIGO.md para no duplicar ruido en cada linea.
 */


namespace App\Service;

use RuntimeException;

/**
 * Comentario de codigo: clase SoapSifenClient. Agrupa la responsabilidad principal indicada en la cabecera del archivo.
 */
final class SoapSifenClient
{
    public function __construct(private array $config)
    {
    }

    /**
     * Comentario de codigo: Metodo del flujo de negocio; leer parametros y retornos para ver que datos transforma.
     */
    public function recepDe(string $xmlRde, string $cdc): array
    {
        $url = (string) ($this->config['url_recep_de'] ?? '');
        if ($url === '') {
            throw new RuntimeException('No está configurada la URL del WS siRecepDE.');
        }

        $soapBody = $this->buildRecepDeEnvelope($xmlRde, $cdc);
        $responseXml = $this->postSoap($url, $soapBody);

        return [
            'raw_response' => $responseXml,
            'status' => $this->extractTagValue($responseXml, 'dEstRes') ?? 'DESCONOCIDO',
            'track_id' => $this->extractTagValue($responseXml, 'dProtAut') ?? '',
            'code' => $this->extractTagValue($responseXml, 'dCodRes') ?? '',
            'message' => $this->extractTagValue($responseXml, 'dMsgRes') ?? '',
        ];
    }

    /**
     * Comentario de codigo: Metodo del flujo de negocio; leer parametros y retornos para ver que datos transforma.
     */
    private function buildRecepDeEnvelope(string $xmlRde, string $cdc): string
    {
        $xDe = htmlspecialchars($xmlRde, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope" xmlns:sif="http://ekuatia.set.gov.py/sifen/xsd">
  <soapenv:Body>
    <sif:rEnviDe>
      <sif:dId>{$cdc}</sif:dId>
      <sif:xDE>{$xDe}</sif:xDE>
    </sif:rEnviDe>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    /**
     * Comentario de codigo: Metodo del flujo de negocio; leer parametros y retornos para ver que datos transforma.
     */
    private function postSoap(string $url, string $body): string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('No se pudo inicializar cURL para SOAP.');
        }

        $headers = [
            'Content-Type: application/soap+xml; charset=UTF-8',
            'Content-Length: ' . strlen($body),
        ];

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
        ]);

        if (($this->config['cert_p12_path'] ?? '') !== '') {
            curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'P12');
            curl_setopt($ch, CURLOPT_SSLCERT, (string) $this->config['cert_p12_path']);
            curl_setopt($ch, CURLOPT_SSLCERTPASSWD, (string) ($this->config['cert_p12_password'] ?? ''));
        } elseif (($this->config['cert_pem_path'] ?? '') !== '' && ($this->config['private_key_pem_path'] ?? '') !== '') {
            curl_setopt($ch, CURLOPT_SSLCERT, (string) $this->config['cert_pem_path']);
            curl_setopt($ch, CURLOPT_SSLKEY, (string) $this->config['private_key_pem_path']);
            if (($this->config['private_key_password'] ?? '') !== '') {
                curl_setopt($ch, CURLOPT_KEYPASSWD, (string) $this->config['private_key_password']);
            }
        }

        if (($this->config['ca_bundle_path'] ?? '') !== '') {
            curl_setopt($ch, CURLOPT_CAINFO, (string) $this->config['ca_bundle_path']);
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Error SOAP/TLS al enviar a SIFEN: ' . $error);
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new RuntimeException('SIFEN devolvió HTTP ' . $httpCode . ': ' . $response);
        }

        return (string) $response;
    }

    /**
     * Comentario de codigo: Metodo del flujo de negocio; leer parametros y retornos para ver que datos transforma.
     */
    private function extractTagValue(string $xml, string $tag): ?string
    {
        if (preg_match('/<' . preg_quote($tag, '/') . '>(.*?)<\/' . preg_quote($tag, '/') . '>/s', $xml, $m)) {
            return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        return null;
    }
}
