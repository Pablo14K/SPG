<?php
declare(strict_types=1);

/*
 * vigilar.php — WATCHER DESATENDIDO
 *
 * Vigila la carpeta de entrada y dispara el procesamiento en cuanto
 * aparece un .txt, sin esperar el siguiente ciclo.
 *
 * Uso:
 *   php bin/vigilar.php          # revisa cada 1 s (por defecto)
 *   php bin/vigilar.php 3        # revisa cada 3 s
 *
 * Para dejarlo corriendo solo en segundo plano (servidor Linux):
 *   nohup php bin/vigilar.php > /dev/null 2>&1 &
 *   echo $! > vigilar.pid        # guarda el PID para poder detenerlo
 *
 * Para detenerlo:
 *   kill $(cat vigilar.pid)
 */

$ctx   = require __DIR__ . '/../src/bootstrap.php';
$auto  = $ctx['auto'];
$sifen = $ctx['sifen'];

$intervaloSeg = max(1, (int)($argv[1] ?? 1));

$log = new \Automatizador\Logger($auto['rutas']['logs']);
$log->info('=== Automatizador SIFEN · watcher · modo ' . ($sifen['mode'] ?? 'mock') . ' ===');
$log->info('Vigilando: ' . $auto['rutas']['entrada']);

$proc = new \Automatizador\Procesador($auto, $sifen, $log);

// Cierre ordenado ante Ctrl+C / kill.
$correr = true;
if (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT,  function () use (&$correr, $log) { $correr = false; $log->info('Watcher detenido.'); });
    pcntl_signal(SIGTERM, function () use (&$correr, $log) { $correr = false; $log->info('Watcher detenido.'); });
}

$vistos = [];  // archivos ya tomados en este ciclo (evita procesarlos dos veces)

while ($correr) {
    $entrada = rtrim($auto['rutas']['entrada'], '/');
    $archivos = glob($entrada . '/*.txt') ?: [];

    foreach ($archivos as $archivo) {
        // Omitir si ya lo intentamos en este ciclo o está siendo escrito (.procesando)
        if (isset($vistos[$archivo])) continue;
        $vistos[$archivo] = true;

        // Esperar a que el archivo termine de escribirse (tamaño estable)
        $tam1 = filesize($archivo);
        usleep(150_000); // 0.15 s
        clearstatcache(true, $archivo);
        $tam2 = filesize($archivo);
        if ($tam1 !== $tam2) continue; // todavía se está escribiendo

        $proc->procesarArchivo($archivo);
    }

    // Limpiar vistos de archivos que ya no existen (fueron movidos a procesados/errores)
    foreach (array_keys($vistos) as $f) {
        if (!file_exists($f)) unset($vistos[$f]);
    }

    for ($i = 0; $i < $intervaloSeg && $correr; $i++) {
        sleep(1);
    }
}

$log->info('Vigilancia finalizada.');
