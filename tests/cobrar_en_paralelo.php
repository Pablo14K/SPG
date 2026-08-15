<?php

declare(strict_types=1);

/*
 * Un proceso que intenta cobrar UNA factura por el saldo entero. Se lanzan
 * varios a la vez desde ConcurrenciaCobroTest para reproducir el hallazgo
 * FA-01 de la simulación de 90 días: dos personas cobrando el mismo
 * comprobante en el mismo segundo —el mostrador y el salón— lo dejaban
 * sobrecobrado, con saldo negativo y sin ninguna pantalla que lo mostrara.
 *
 * No es parte de la aplicación: vive acá porque solo lo usa esa prueba.
 * Imprime OK <monto> o NO <motivo>, que es lo que lee el test.
 *
 *   php tests/cobrar_en_paralelo.php <idFactura> <idUsuario> <monto> <idCaja> <largada>
 */

require __DIR__ . '/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[$factura, $usuario, $monto, $caja] = [
    (int) ($argv[1] ?? 0), (int) ($argv[2] ?? 0), (float) ($argv[3] ?? 0), (int) ($argv[4] ?? 0),
];

// Todos arrancan sobre el mismo instante del reloj, si no el primero termina
// antes de que el último empiece y no habría concurrencia que probar.
$arranque = (float) ($argv[5] ?? 0);
if ($arranque > 0) {
    $espera = $arranque - microtime(true);
    if ($espera > 0) {
        usleep((int) ($espera * 1_000_000));
    }
}

try {
    // El método de pago 1 es efectivo. Se cobra por el camino real, el mismo
    // que usa la pantalla, para que el candado del procedimiento cuente.
    App\Servicios\Facturacion::cobrar($factura, $usuario, [[
        'metodo' => 1,
        'tipo' => 'EFECTIVO',
        'monto' => $monto,
        'nombre' => 'Efectivo',
        'referencia' => 'prueba de concurrencia',
    ]], $caja);
    echo 'OK ' . $monto . PHP_EOL;
} catch (Throwable $e) {
    echo 'NO ' . str_replace(["\n", "\r"], ' ', $e->getMessage()) . PHP_EOL;
}
