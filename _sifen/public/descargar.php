<?php
declare(strict_types=1);

/*
 * Descarga segura de archivos generados (XML / PDF).
 * Solo sirve archivos que estén dentro de salida/.
 * Uso: /descargar.php?f=<CDC>.pdf
 */

require __DIR__ . '/../src/bootstrap.php';

$nombre = basename((string)($_GET['f'] ?? ''));
if ($nombre === '') {
    http_response_code(400); echo 'Archivo no especificado.'; exit;
}

// Solo extensiones permitidas
if (!preg_match('/\.(xml|pdf)$/i', $nombre)) {
    http_response_code(403); echo 'Tipo de archivo no permitido.'; exit;
}

$path = AUTO_ROOT . '/salida/' . $nombre;
if (!is_file($path)) {
    http_response_code(404); echo 'Archivo no encontrado.'; exit;
}

// Token opcional para proteger las descargas
$tokenEsperado = env('SIFEN_API_TOKEN');
if ($tokenEsperado !== '') {
    $tokenRecibido = $_GET['token'] ?? $_SERVER['HTTP_X_API_TOKEN'] ?? '';
    if ($tokenRecibido !== $tokenEsperado) {
        http_response_code(401); echo 'No autorizado.'; exit;
    }
}

$mime = str_ends_with(strtolower($nombre), '.pdf') ? 'application/pdf' : 'application/xml';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
