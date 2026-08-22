<?php
declare(strict_types=1);

/*
 * BOOTSTRAP DEL AUTOMATIZADOR SIFEN
 * Sistema completamente independiente del sifen_final.
 * Lee su propio .env ubicado en la raíz de sifen_automatizador/.
 */

define('AUTO_ROOT', dirname(__DIR__));

// ── 1) Leer el .env propio ───────────────────────────────────────────────────
(function (): void {
    $candidates = [AUTO_ROOT . '/.env', AUTO_ROOT . '/.env.example'];
    foreach ($candidates as $path) {
        if (!is_file($path)) continue;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k); $v = trim($v);
            if ($k !== '' && getenv($k) === false) {
                putenv("$k=$v"); $_ENV[$k] = $v;
            }
        }
        break;
    }
})();

function env(string $key, string $default = ''): string {
    $v = getenv($key);
    return $v !== false && $v !== '' ? $v : $default;
}

// ── 2) Timezone ──────────────────────────────────────────────────────────────
date_default_timezone_set('America/Asuncion');

// ── 3) APP_ROOT → raíz del automatizador (FileStore lo usa para storage/) ───
if (!defined('APP_ROOT')) define('APP_ROOT', AUTO_ROOT);

// ── 4) Autoloader del MOTOR (App\*) incluido en motor/ ───────────────────────
$motorDir = AUTO_ROOT . '/motor';
spl_autoload_register(function (string $class) use ($motorDir): void {
    if (!str_starts_with($class, 'App\\')) return;
    $file = $motorDir . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require $file;
});

// ── 5) Autoloader propio (Automatizador\*) ───────────────────────────────────
spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'Automatizador\\')) return;
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 14)) . '.php';
    if (is_file($file)) require $file;
});

// ── 6) Construir configuración completa desde el .env ────────────────────────

$AUTO_CONFIG = [
    'modo' => env('SIFEN_MODE', 'mock'),

    'rutas' => [
        'entrada'    => AUTO_ROOT . '/entrada',
        'procesados' => AUTO_ROOT . '/procesados',
        'errores'    => AUTO_ROOT . '/errores',
        'salida'     => AUTO_ROOT . '/salida',
        'logs'       => AUTO_ROOT . '/logs',
        'certs'      => AUTO_ROOT . '/certs',
    ],

    'emisor' => [
        'ambiente'                        => env('EMISOR_AMBIENTE', 'test'),
        'ruc'                             => env('EMISOR_RUC'),
        'dv'                              => env('EMISOR_DV'),
        'tipo_contribuyente'              => (int) env('EMISOR_TIPO_CONTRIBUYENTE', '2'),
        'tipo_regimen'                    => '',
        'razon_social'                    => env('EMISOR_RAZON_SOCIAL'),
        'nombre_fantasia'                 => env('EMISOR_NOMBRE_FANTASIA'),
        'direccion'                       => env('EMISOR_DIRECCION'),
        'numero_casa'                     => env('EMISOR_NUMERO_CASA', '0'),
        'codigo_departamento'             => env('EMISOR_COD_DEPARTAMENTO', '11'),
        'descripcion_departamento'        => env('EMISOR_DESC_DEPARTAMENTO', 'CAPITAL'),
        'codigo_distrito'                 => env('EMISOR_COD_DISTRITO'),
        'descripcion_distrito'            => env('EMISOR_DESC_DISTRITO'),
        'codigo_ciudad'                   => env('EMISOR_COD_CIUDAD', '1'),
        'descripcion_ciudad'              => env('EMISOR_DESC_CIUDAD', 'ASUNCION'),
        'telefono'                        => env('EMISOR_TELEFONO'),
        'email'                           => env('EMISOR_EMAIL'),
        'codigo_actividad_economica'      => env('EMISOR_COD_ACTIVIDAD'),
        'descripcion_actividad_economica' => env('EMISOR_DESC_ACTIVIDAD'),
        'timbrado_numero'                 => env('EMISOR_TIMBRADO'),
        'timbrado_inicio'                 => env('EMISOR_TIMBRADO_INICIO'),
        'timbrado_fin'                    => env('EMISOR_TIMBRADO_FIN'),
        'id_csc'                          => env('SIFEN_ID_CSC', '0001'),
        'csc'                             => env('SIFEN_CSC'),
        'info_adicional_kude'             => '',
    ],

    'mail' => [
        'transport'  => env('MAIL_TRANSPORT', 'smtp'),
        'host'       => env('MAIL_HOST', 'smtp.gmail.com'),
        'port'       => (int) env('MAIL_PORT', '587'),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'username'   => env('MAIL_USERNAME'),
        'password'   => env('MAIL_PASSWORD'),
        'from_email' => env('MAIL_FROM_EMAIL'),
        'from_name'  => env('MAIL_FROM_NAME', 'Facturación Electrónica'),
    ],

    'certificado' => [
        'p12_path'             => env('SIFEN_CERT_P12_PATH'),
        'p12_password'         => env('SIFEN_CERT_P12_PASSWORD'),
        'pem_path'             => env('SIFEN_CERT_PEM_PATH'),
        'private_key_path'     => env('SIFEN_PRIVATE_KEY_PEM_PATH'),
        'private_key_password' => env('SIFEN_PRIVATE_KEY_PASSWORD'),
    ],

    'ws' => [
        'url_recep_de' => env('SIFEN_URL_RECEP_DE'),
        'url_cons_de'  => env('SIFEN_URL_CONS_DE'),
        'url_cons_ruc' => env('SIFEN_URL_CONS_RUC'),
    ],
];

$cert  = $AUTO_CONFIG['certificado'];
$ws    = $AUTO_CONFIG['ws'];
$rutas = $AUTO_CONFIG['rutas'];

$SIFEN_CONFIG = [
    'mode'                 => $AUTO_CONFIG['modo'],
    'test_label'           => '',
    'namespace'            => 'http://ekuatia.set.gov.py/sifen/xsd',
    'xml_dir'              => $rutas['salida'],
    'cert_dir'             => $rutas['certs'],
    'url_recep_de'         => $ws['url_recep_de'],
    'url_cons_de'          => $ws['url_cons_de'],
    'url_cons_ruc'         => $ws['url_cons_ruc'],
    'cert_p12_path'        => $cert['p12_path'],
    'cert_p12_password'    => $cert['p12_password'],
    'cert_pem_path'        => $cert['pem_path'],
    'private_key_pem_path' => $cert['private_key_path'],
    'private_key_password' => $cert['private_key_password'],
    'ca_bundle_path'       => '',
    'qr_test_base'         => 'https://ekuatia.set.gov.py/consultas-test/qr?',
    'qr_prod_base'         => 'https://ekuatia.set.gov.py/consultas/qr?',
    'id_csc'               => $AUTO_CONFIG['emisor']['id_csc'],
    'csc'                  => $AUTO_CONFIG['emisor']['csc'],
];

return ['auto' => $AUTO_CONFIG, 'sifen' => $SIFEN_CONFIG];
