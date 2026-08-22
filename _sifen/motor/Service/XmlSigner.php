<?php

declare(strict_types=1);
/*
 * DOCUMENTACION DEL ARCHIVO
 * Que hace: Firma XML PHP con XMLDSig. Aplica digest SHA-256, RSA-SHA256 y certificado X509.
 * Donde se usa: forma parte del flujo de facturacion electronica SIFEN documentado en docs/DOCUMENTACION_TECNICA_COMPLETA.md.
 * Nota: los detalles de variables, palabras reservadas y cambios posibles estan centralizados en docs/GUIA_COMENTARIOS_CODIGO.md para no duplicar ruido en cada linea.
 */


namespace App\Service;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/**
 * Comentario de codigo: clase XmlSigner. Agrupa la responsabilidad principal indicada en la cabecera del archivo.
 */
final class XmlSigner
{
    private const DSIG = 'http://www.w3.org/2000/09/xmldsig#';

    /**
     * Comentario de codigo: Metodo del flujo de negocio; leer parametros y retornos para ver que datos transforma.
     */
    public function sign(string $xml, string $documentId, array $certData): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        if (!$dom->loadXML($xml, LIBXML_NOBLANKS)) {
            throw new RuntimeException('No se pudo cargar el XML para firmar.');
        }

        $referenceTarget = $this->extractReferenceXml($dom, $documentId);
        $canonicalized = $this->exclusiveCanonicalizeXmlString($referenceTarget);
        $digestRaw = hash('sha256', $canonicalized, true);
        $digestValue = base64_encode($digestRaw);

        $signature = $dom->createElementNS(self::DSIG, 'Signature');
        $signedInfo = $dom->createElementNS(self::DSIG, 'SignedInfo');
        $signature->appendChild($signedInfo);

        // Manual SIFEN v150 cap. 7.6 (Schema XML 1, XS04) — el SignedInfo se canoniza con
        // C14N INCLUSIVO. Las Transforms del Reference sí usan exc-c14n# (ver más abajo).
        $canonMethod = $dom->createElementNS(self::DSIG, 'CanonicalizationMethod');
        $canonMethod->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfo->appendChild($canonMethod);

        $sigMethod = $dom->createElementNS(self::DSIG, 'SignatureMethod');
        $sigMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256');
        $signedInfo->appendChild($sigMethod);

        $reference = $dom->createElementNS(self::DSIG, 'Reference');
        $reference->setAttribute('URI', '#' . $documentId);
        $signedInfo->appendChild($reference);

        $transforms = $dom->createElementNS(self::DSIG, 'Transforms');
        $reference->appendChild($transforms);

        $transform1 = $dom->createElementNS(self::DSIG, 'Transform');
        $transform1->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transforms->appendChild($transform1);

        $transform2 = $dom->createElementNS(self::DSIG, 'Transform');
        $transform2->setAttribute('Algorithm', 'http://www.w3.org/2001/10/xml-exc-c14n#');
        $transforms->appendChild($transform2);

        $digestMethod = $dom->createElementNS(self::DSIG, 'DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $reference->appendChild($digestMethod);

        $digestValueNode = $dom->createElementNS(self::DSIG, 'DigestValue', $digestValue);
        $reference->appendChild($digestValueNode);

        // IMPORTANTE: colgar la Signature en su posición FINAL (dentro de rDE) ANTES de
        // canonicalizar SignedInfo. Si se canoniza con la Signature "suelta" (fuera del
        // árbol), el contexto de namespaces difiere del documento final y la firma queda
        // INVÁLIDA al verificarse (SIFEN la rechaza). SignatureValue y KeyInfo se agregan
        // después: no forman parte de SignedInfo, así que no alteran su forma canónica.
        $root = $dom->documentElement;
        if (!$root instanceof DOMElement) {
            throw new RuntimeException('XML sin elemento raíz.');
        }
        $root->appendChild($signature);

        // C14N inclusivo (XS04). El primer argumento "exclusive" debe ser false.
        $signedInfoCanon = $signedInfo->C14N(false, false);
        if ($signedInfoCanon === false) {
            throw new RuntimeException('No se pudo canonicalizar SignedInfo.');
        }

        $privateKey = openssl_pkey_get_private($certData['private_key_pem'], (string) ($certData['private_key_password'] ?? ''));
        if ($privateKey === false) {
            throw new RuntimeException('No se pudo abrir la clave privada para firmar.');
        }

        if (!openssl_sign($signedInfoCanon, $signatureRaw, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No se pudo firmar el XML.');
        }

        $signatureValue = $dom->createElementNS(self::DSIG, 'SignatureValue', base64_encode($signatureRaw));
        $signature->appendChild($signatureValue);

        $keyInfo = $dom->createElementNS(self::DSIG, 'KeyInfo');
        $x509Data = $dom->createElementNS(self::DSIG, 'X509Data');
        $x509Certificate = $dom->createElementNS(self::DSIG, 'X509Certificate', $this->normalizeCertificate($certData['certificate_pem']));
        $x509Data->appendChild($x509Certificate);
        $keyInfo->appendChild($x509Data);
        $signature->appendChild($keyInfo);

        // Manual SIFEN v150 cap. 7.2.4 — Compactar XML para evitar rechazo 0105 (AB06).
        $xmlFirmado = (string) $dom->saveXML();
        $xmlFirmado = (string) preg_replace('/>\s+</', '><', $xmlFirmado);

        return [
            'xml' => trim($xmlFirmado),
            'digest_value' => $digestValue,
            'signature_value' => base64_encode($signatureRaw),
        ];
    }

    /**
     * Comentario de codigo: Metodo del flujo de negocio; leer parametros y retornos para ver que datos transforma.
     */
    private function extractReferenceXml(DOMDocument $dom, string $documentId): string
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ns', $dom->documentElement?->namespaceURI ?: '');
        $nodes = $xpath->query("//*[@Id='{$documentId}']");
        if ($nodes === false || $nodes->length === 0) {
            throw new RuntimeException('No se encontró el nodo referenciado por el Id del documento.');
        }
        $node = $nodes->item(0);
        $tmp = new DOMDocument('1.0', 'UTF-8');
        $tmp->appendChild($tmp->importNode($node, true));
        $xml = $tmp->saveXML($tmp->documentElement);
        if ($xml === false) {
            throw new RuntimeException('No se pudo serializar el nodo a firmar.');
        }
        return $xml;
    }

    /**
     * Comentario de codigo: Metodo del flujo de negocio; leer parametros y retornos para ver que datos transforma.
     */
    private function exclusiveCanonicalizeXmlString(string $xml): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        if (!$dom->loadXML($xml, LIBXML_NOBLANKS)) {
            throw new RuntimeException('No se pudo cargar XML para canonicalización.');
        }
        $canon = $dom->documentElement?->C14N(true, false);
        if ($canon === false) {
            throw new RuntimeException('No se pudo canonicalizar el XML.');
        }
        return $canon;
    }

    /**
     * Comentario de codigo: Metodo del flujo de negocio; leer parametros y retornos para ver que datos transforma.
     */
    private function normalizeCertificate(string $certificatePem): string
    {
        $body = preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $certificatePem);
        return (string) $body;
    }
}
