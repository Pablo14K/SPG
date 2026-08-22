<?php

declare(strict_types=1);
/*
 * DOCUMENTACION DEL ARCHIVO
 * Que hace: Carga certificados reales o demo. Se usa antes de firmar XML en modo PHP.
 * Donde se usa: forma parte del flujo de facturacion electronica SIFEN documentado en docs/DOCUMENTACION_TECNICA_COMPLETA.md.
 * Nota: los detalles de variables, palabras reservadas y cambios posibles estan centralizados en docs/GUIA_COMENTARIOS_CODIGO.md para no duplicar ruido en cada linea.
 */


namespace App\Service;

use RuntimeException;

/**
 * Comentario de codigo: clase CertificateLoader. Agrupa la responsabilidad principal indicada en la cabecera del archivo.
 */
final class CertificateLoader
{
    public function load(array $config): array
    {
        $pemPath = (string) ($config['cert_pem_path'] ?? '');
        $keyPath = (string) ($config['private_key_pem_path'] ?? '');
        $keyPassword = (string) ($config['private_key_password'] ?? '');

        if ($pemPath !== '' && $keyPath !== '' && is_file($pemPath) && is_file($keyPath)) {
            $certificatePem = (string) file_get_contents($pemPath);
            $privateKeyPem = (string) file_get_contents($keyPath);
            if ($certificatePem === '' || $privateKeyPem === '') {
                throw new RuntimeException('No se pudo leer el certificado PEM o la clave privada.');
            }
            return [
                'certificate_pem' => $certificatePem,
                'private_key_pem' => $privateKeyPem,
                'private_key_password' => $keyPassword,
                'source' => 'pem',
            ];
        }

        $p12Path = (string) ($config['cert_p12_path'] ?? '');
        $p12Password = (string) ($config['cert_p12_password'] ?? '');
        if ($p12Path !== '' && is_file($p12Path)) {
            $content = (string) file_get_contents($p12Path);
            $certs = [];
            if (!openssl_pkcs12_read($content, $certs, $p12Password)) {
                throw new RuntimeException('No se pudo leer el certificado P12/PFX.');
            }
            return [
                'certificate_pem' => (string) ($certs['cert'] ?? ''),
                'private_key_pem' => (string) ($certs['pkey'] ?? ''),
                'private_key_password' => '',
                'source' => 'p12',
            ];
        }

        throw new RuntimeException('No se encontró certificado real configurado.');
    }
}
