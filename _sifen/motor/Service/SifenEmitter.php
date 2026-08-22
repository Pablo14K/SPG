<?php

declare(strict_types=1);
/*
 * DOCUMENTACION DEL ARCHIVO
 * Que hace: Motor de emisión SIFEN 100% PHP (reemplaza al microservicio Node). Orquesta CDC → XML → firma → QR →
 *           envío al WS (o mock) → guardado del XML firmado. Es el corazón del flujo "sin Node" para cPanel.
 * Donde se usa: lo invoca SifenBridge::emit(); el resultado lo consume PipelineService (que genera el KuDE y encola el correo).
 * Nota: réplica del pipeline.js del Node. Ver docs/DOCUMENTACION_TECNICA_COMPLETA.md y GUIA_COMENTARIOS_CODIGO.md.
 */

namespace App\Service;

use App\Support\FileStore;
use DateTimeImmutable;

/**
 * Pipeline de emisión SIFEN v150 en PHP puro.
 *
 * Pasos (equivalentes al pipeline.js del microservicio Node retirado):
 *   1. CdcGenerator      → código de seguridad (dCodSeg) + CDC de 44 dígitos
 *   2. SifenXmlBuilder   → XML rDE/DE SIN firma y SIN QR
 *   3. Certificado       → demo autogenerado (mock) o real .p12/.pem (test/prod)
 *   4. XmlSigner         → firma XMLDSig (RSA-SHA256) y devuelve el DigestValue
 *   5. QrGenerator       → arma el dCarQR (depende del DigestValue)
 *   6. insertarQr        → inserta gCamFuFD/dCarQR DESPUÉS de </Signature> (orden del schema)
 *   7. SoapSifenClient   → envía al WS oficial (o respuesta simulada en modo mock)
 *   8. Guarda el XML firmado en storage/xml/<CDC>.xml
 *
 * El KuDE PDF NO se genera aquí: lo arma PipelineService con el qr_text que devolvemos
 * (igual que antes hacía con el fallback PHP cuando el Node no traía KuDE).
 */
final class SifenEmitter
{
    private string $mode;
    private string $namespace;

    /**
     * @param array $config El array 'sifen' de config.php (mode, namespace, xml_dir, cert_dir,
     *                      url_recep_de, cert_*, qr_*_base, id_csc, csc, …).
     */
    public function __construct(private array $config)
    {
        $this->mode = (string)($config['mode'] ?? 'mock');
        $this->namespace = (string)($config['namespace'] ?? 'http://ekuatia.set.gov.py/sifen/xsd');
    }

    /**
     * Emite un documento electrónico a partir del payload del InvoiceMapper.
     *
     * @param array $payload ['emisor'=>…, 'documento'=>…, 'cliente'=>…, 'items'=>…, 'pagos'=>…, 'totales'=>…]
     * @return array Mismo contrato que devolvía SifenBridge::emit() vía Node:
     *               ['ok','cdc','xml','xml_path','qr_text','kude_path','estado','track_id','respuesta'].
     */
    public function emit(array $payload): array
    {
        // ── 1. Código de seguridad + CDC ──────────────────────────────────
        $cdcGen  = new CdcGenerator();
        $dCodSeg = $cdcGen->generateSecurityCode();
        $cdc     = $cdcGen->generate($payload['emisor'], $payload['documento'], $dCodSeg);

        // ── 2. XML sin firma y sin QR ─────────────────────────────────────
        $xmlSinFirmar = (new SifenXmlBuilder())->build(
            ['namespace' => $this->namespace],
            $payload,
            $cdc,
            $dCodSeg,
            null
        );

        // ── 3. Certificado (demo en mock; real si está configurado) ───────
        $certData = $this->loadCertificate();

        // ── 4. Firmar (el Id del DE referenciado por la firma es el CDC) ──
        $firma       = (new XmlSigner())->sign($xmlSinFirmar, $cdc, $certData);
        $xmlFirmado  = (string)$firma['xml'];
        $digestValue = (string)$firma['digest_value'];

        // ── 5. QR (depende del DigestValue) ───────────────────────────────
        $payload = $this->ensureCsc($payload);
        $qrText  = (new QrGenerator())->generate($this->config, $payload, $cdc, $digestValue);

        // ── 6. Insertar el QR después de la firma ─────────────────────────
        $xmlFinal = $this->insertarQr($xmlFirmado, $qrText);

        // ── 7. Guardar el XML firmado ─────────────────────────────────────
        $xmlPath = rtrim((string)($this->config['xml_dir'] ?? 'storage/xml'), '/\\') . '/' . $cdc . '.xml';
        FileStore::put($xmlPath, $xmlFinal);

        // ── 8. Enviar a SIFEN (WS real) o simular respuesta (mock) ────────
        $respuesta = $this->enviarASifen($xmlFinal, $cdc);

        return [
            'ok'        => (bool)($respuesta['ok'] ?? false),
            'cdc'       => $cdc,
            'xml'       => $xmlFinal,
            'xml_path'  => $xmlPath,
            'qr_text'   => $qrText,
            'kude_path' => '', // lo genera PipelineService con el qr_text
            'estado'    => (string)($respuesta['estado'] ?? 'APROBADO'),
            'track_id'  => (string)($respuesta['track_id'] ?? ''),
            'respuesta' => $respuesta,
        ];
    }

    /**
     * Devuelve el certificado a usar para firmar: real si está configurado en .env
     * (p12 o pem), o uno DEMO autogenerado para modo mock (sin valor fiscal).
     */
    private function loadCertificate(): array
    {
        $tieneReal = ((string)($this->config['cert_p12_path'] ?? '')) !== ''
            || (((string)($this->config['cert_pem_path'] ?? '')) !== ''
                && ((string)($this->config['private_key_pem_path'] ?? '')) !== '');

        if ($tieneReal) {
            return (new CertificateLoader())->load($this->config);
        }
        $certDir = (string)($this->config['cert_dir'] ?? 'storage/certs');
        return (new DemoCertificateService($certDir))->getOrCreate();
    }

    /**
     * Si el emisor no trae CSC (emitter_settings vacío), usa el de config/.env como
     * respaldo. Sin CSC el QrGenerator no puede generar el dCarQR.
     */
    private function ensureCsc(array $payload): array
    {
        if ((string)($payload['emisor']['csc'] ?? '') === '') {
            $payload['emisor']['csc'] = (string)($this->config['csc'] ?? '');
        }
        if ((string)($payload['emisor']['id_csc'] ?? '') === '') {
            $payload['emisor']['id_csc'] = (string)($this->config['id_csc'] ?? '0001');
        }
        return $payload;
    }

    /**
     * Inserta el bloque gCamFuFD/dCarQR (QR) inmediatamente después de </Signature>,
     * que es donde lo exige el esquema SIFEN (DE → Signature → gCamFuFD). Réplica del
     * _insertarQREnXML del Node.
     */
    private function insertarQr(string $xml, string $qrText): string
    {
        $qrEsc = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $qrText);
        $bloque = '<gCamFuFD xmlns="' . $this->namespace . '"><dCarQR>' . $qrEsc . '</dCarQR></gCamFuFD>';

        if (str_contains($xml, '</Signature>')) {
            return str_replace('</Signature>', '</Signature>' . $bloque, $xml);
        }
        return str_replace('</rDE>', $bloque . '</rDE>', $xml);
    }

    /**
     * Envía el XML firmado al WS de recepción de DE. En modo mock devuelve una
     * aprobación simulada (cód. 0260, sin valor fiscal); en test/prod usa el
     * SoapSifenClient con mTLS (certificado del contribuyente).
     */
    private function enviarASifen(string $xmlFirmado, string $cdc): array
    {
        if ($this->mode === 'mock') {
            return [
                'ok'       => true,
                'estado'   => 'APROBADO',
                'code'     => '0260',
                'message'  => 'Documento generado, firmado y aprobado localmente en modo mock. Sin valor fiscal.',
                'track_id' => 'MOCK-' . (new DateTimeImmutable())->format('YmdHis'),
                'modo'     => 'mock',
            ];
        }

        $resp   = (new SoapSifenClient($this->config))->recepDe($xmlFirmado, $cdc);
        $code   = (string)($resp['code'] ?? '');
        $estado = strtoupper((string)($resp['status'] ?? ''));
        $ok     = in_array($code, ['0260', '0300', '0600'], true)
            || str_contains($estado, 'APROBAD') || str_contains($estado, 'ACEPTAD');

        return [
            'ok'       => $ok,
            'estado'   => (string)($resp['status'] ?? 'DESCONOCIDO'),
            'code'     => $code,
            'message'  => (string)($resp['message'] ?? ''),
            'track_id' => (string)($resp['track_id'] ?? ''),
            'raw'      => (string)($resp['raw_response'] ?? ''),
        ];
    }
}
