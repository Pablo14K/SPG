<?php

declare(strict_types=1);

/*
 * Un proceso que intenta abrir la caja del salón. Se lanzan varios a la vez
 * desde ConcurrenciaCobroTest para reproducir el hallazgo CJ-01 de la
 * simulación de 90 días: tres personas llegando a las 07:40 y apretando
 * «Abrir caja» en el mismo segundo dejaban dos —y hasta tres— cajas abiertas.
 *
 * No es parte de la aplicación: vive acá porque solo lo usa esa prueba.
 * Imprime OK <idCaja> o NO <motivo>, que es lo que lee el test.
 *
 *   php tests/abrir_caja_en_paralelo.php <idUsuario> <largada>
 */

require __DIR__ . '/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$usuario = (int) ($argv[1] ?? 0);

$arranque = (float) ($argv[2] ?? 0);
if ($arranque > 0) {
    $espera = $arranque - microtime(true);
    if ($espera > 0) {
        usleep((int) ($espera * 1_000_000));
    }
}

try {
    echo 'OK ' . App\Servicios\Caja::abrir($usuario, 100000.0, 1) . PHP_EOL;
} catch (Throwable $e) {
    echo 'NO ' . str_replace(["\n", "\r"], ' ', $e->getMessage()) . PHP_EOL;
}
