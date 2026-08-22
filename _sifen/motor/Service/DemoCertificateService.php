<?php

declare(strict_types=1);
/*
 * DOCUMENTACION DEL ARCHIVO
 * Que hace: Genera certificado de demostracion. Solo sirve para pruebas locales sin valor fiscal.
 * Donde se usa: forma parte del flujo de facturacion electronica SIFEN documentado en docs/DOCUMENTACION_TECNICA_COMPLETA.md.
 * Nota: los detalles de variables, palabras reservadas y cambios posibles estan centralizados en docs/GUIA_COMENTARIOS_CODIGO.md para no duplicar ruido en cada linea.
 */


namespace App\Service;

use App\Support\FileStore;
use RuntimeException;

/**
 * Comentario de codigo: clase DemoCertificateService. Agrupa la responsabilidad principal indicada en la cabecera del archivo.
 */
final class DemoCertificateService
{
    public function __construct(private string $certDir)
    {
    }

    /**
     * Comentario de codigo: Metodo del flujo de negocio; leer parametros y retornos para ver que datos transforma.
     */
    public function getOrCreate(): array
    {
        FileStore::ensureDir($this->certDir);
        $certPath = rtrim($this->certDir, '/') . '/demo_cert.pem';
        $keyPath = rtrim($this->certDir, '/') . '/demo_key.pem';

        if (is_file($certPath) && is_file($keyPath)) {
            return [
                'certificate_pem' => (string) file_get_contents($certPath),
                'private_key_pem' => (string) file_get_contents($keyPath),
                'private_key_password' => '',
                'source' => 'demo',
            ];
        }

        $dn = [
            'countryName' => 'PY',
            'stateOrProvinceName' => 'CAPITAL',
            'localityName' => 'ASUNCION',
            'organizationName' => 'DEMO FACTURACION S.A.',
            'organizationalUnitName' => 'SIFEN DEMO',
            'commonName' => 'RUC 80012345-6',
            'emailAddress' => 'facturas@example.test',
        ];

        // En Windows, OpenSSL no encuentra solo su openssl.cnf y las funciones de
        // generación fallan ("configuration file routines: no such file"). Si lo
        // localizamos, lo pasamos explícitamente. En Linux/cPanel queda en null y
        // OpenSSL usa /etc/ssl/openssl.cnf automáticamente.
        $opensslConf = $this->resolveOpensslConfig();

        $keyArgs = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $signArgs = ['digest_alg' => 'sha256'];
        if ($opensslConf !== null) {
            $keyArgs['config'] = $opensslConf;
            $signArgs['config'] = $opensslConf;
        }

        $priv = openssl_pkey_new($keyArgs);
        if ($priv === false) {
            throw new RuntimeException('No se pudo generar la clave privada demo. ' . $this->opensslErrors());
        }

        $csr = openssl_csr_new($dn, $priv, $signArgs);
        if ($csr === false) {
            throw new RuntimeException('No se pudo generar el CSR demo. ' . $this->opensslErrors());
        }
        $x509 = openssl_csr_sign($csr, null, $priv, 3650, $signArgs);
        if ($x509 === false) {
            throw new RuntimeException('No se pudo generar el certificado demo. ' . $this->opensslErrors());
        }

        openssl_x509_export($x509, $certOut);
        openssl_pkey_export($priv, $keyOut, null, $opensslConf !== null ? ['config' => $opensslConf] : null);

        file_put_contents($certPath, $certOut);
        file_put_contents($keyPath, $keyOut);

        return [
            'certificate_pem' => $certOut,
            'private_key_pem' => $keyOut,
            'private_key_password' => '',
            'source' => 'demo',
        ];
    }

    /**
     * Localiza un openssl.cnf usable. Devuelve null si conviene dejar que OpenSSL
     * use su ubicación por defecto (caso Linux/cPanel).
     */
    private function resolveOpensslConfig(): ?string
    {
        $candidates = array_filter([
            getenv('OPENSSL_CONF') ?: null,
            dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf',
            dirname(PHP_BINARY) . '/extras/openssl/openssl.cnf',
            'C:/php/extras/ssl/openssl.cnf',
        ]);
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                // Algunas builds de OpenSSL en Windows ignoran el argumento 'config' y
                // solo respetan la variable de entorno; la fijamos por las dudas.
                if (getenv('OPENSSL_CONF') === false) {
                    putenv('OPENSSL_CONF=' . $candidate);
                }
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Junta los mensajes de error de la pila de OpenSSL para diagnóstico.
     */
    private function opensslErrors(): string
    {
        $errs = [];
        while (($e = openssl_error_string()) !== false) {
            $errs[] = $e;
        }
        return $errs === [] ? '' : '(' . implode('; ', $errs) . ')';
    }
}
