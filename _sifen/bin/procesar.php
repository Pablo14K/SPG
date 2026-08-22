<?php
declare(strict_types=1);

/*
 * procesar.php — corrida ÚNICA (one-shot).
 * Procesa todos los .txt que haya en la carpeta de entrada y termina.
 * Pensado para ejecutarse desde un Cron Job:
 *     * * * * * /usr/bin/php /ruta/sifen_automatizador/bin/procesar.php >> /dev/null 2>&1
 */

$ctx   = require __DIR__ . '/../src/bootstrap.php';
$auto  = $ctx['auto'];
$sifen = $ctx['sifen'];

$log = new \Automatizador\Logger($auto['rutas']['logs']);
$log->info('=== Automatizador SIFEN (corrida única) · modo ' . ($sifen['mode'] ?? 'mock') . ' ===');

$proc = new \Automatizador\Procesador($auto, $sifen, $log);
[$ok, $err] = $proc->procesarEntrada();

exit($err > 0 ? 1 : 0);
