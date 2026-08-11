<?php

declare(strict_types=1);

/*
 * Un proceso que intenta agendar UN horario. Se lanzan varios a la vez desde
 * ConcurrenciaAgendaTest para reproducir lo que pasa de verdad: tres clientas
 * mirando la misma pantalla, con el mismo hueco libre, apretando "Reservar"
 * en el mismo segundo.
 *
 * No es parte de la aplicación: vive acá porque solo lo usa esa prueba.
 * Imprime OK <id> o NO <motivo>, que es lo que lee el test.
 *
 *   php tests/reservar_en_paralelo.php <idCliente> <idProfesional> "<fecha hora>" <idServicio> <largada>
 *
 * El servicio no es un detalle: `fn_cita_duracion` lo lee de `cita_servicio`,
 * así que una cita sin servicios ocupa cero minutos y no se pisa con nada.
 */

require __DIR__ . '/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[$cliente, $profesional, $fechaHora, $servicio] = [
    (int) ($argv[1] ?? 0), (int) ($argv[2] ?? 0), (string) ($argv[3] ?? ''), (int) ($argv[4] ?? 0),
];
$duracion = App\Servicios\Agenda::duracion([$servicio]);

// Todos arrancan sobre el mismo segundo del reloj, si no el primero termina
// antes de que el último empiece y no habría concurrencia que probar.
$arranque = (float) ($argv[5] ?? 0);
if ($arranque > 0) {
    $espera = $arranque - microtime(true);
    if ($espera > 0) {
        usleep((int) ($espera * 1_000_000));
    }
}

try {
    $id = App\Servicios\Agenda::agendar($cliente, $profesional, $fechaHora, $duracion,
        'prueba de concurrencia', [$servicio => $profesional]);
    echo 'OK ' . $id . PHP_EOL;
} catch (Throwable $e) {
    echo 'NO ' . str_replace(["\n", "\r"], ' ', $e->getMessage()) . PHP_EOL;
}
