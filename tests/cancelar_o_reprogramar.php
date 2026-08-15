<?php

declare(strict_types=1);

/*
 * Un proceso que cancela O reprograma una cita. Se lanzan los dos a la vez
 * desde ConcurrenciaCobroTest para reproducir el hallazgo AG-04 de la
 * simulación de 90 días: las dos acciones leían el estado y escribían sin
 * candado sobre la cita, así que ganaba la última en confirmar y la
 * cancelación se perdía — la clienta cree que canceló, el horario sigue
 * ocupado y alguien la va a esperar.
 *
 * No es parte de la aplicación: vive acá porque solo lo usa esa prueba.
 * Imprime OK <accion> o NO <motivo>, que es lo que lee el test.
 *
 *   php tests/cancelar_o_reprogramar.php <idCita> <cancelar|reprogramar> "<fecha hora>" <largada>
 */

require __DIR__ . '/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[$idCita, $accion, $fecha] = [
    (int) ($argv[1] ?? 0), (string) ($argv[2] ?? ''), (string) ($argv[3] ?? ''),
];

$arranque = (float) ($argv[4] ?? 0);
if ($arranque > 0) {
    $espera = $arranque - microtime(true);
    if ($espera > 0) {
        usleep((int) ($espera * 1_000_000));
    }
}

try {
    if ($accion === 'cancelar') {
        App\Servicios\Agenda::cancelar($idCita);
    } else {
        App\Servicios\Agenda::reprogramar($idCita, $fecha);
    }
    echo 'OK ' . $accion . PHP_EOL;
} catch (Throwable $e) {
    echo 'NO ' . str_replace(["\n", "\r"], ' ', $e->getMessage()) . PHP_EOL;
}
