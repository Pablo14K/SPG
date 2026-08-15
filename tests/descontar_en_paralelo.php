<?php

declare(strict_types=1);

/*
 * Un proceso que intenta sacar una cantidad del stock de un producto. Se
 * lanzan varios a la vez desde ConcurrenciaStockTest para reproducir el
 * hallazgo IN-01 de la simulación de 90 días: tres salidas simultáneas del
 * mismo producto pasaban las tres, porque las tres sumaban el stock antes de
 * que ninguna hubiera insertado. «Agua oxigenada 900ml» quedó en −13,8311, y
 * nadie lo detecta: fn_producto_stock devuelve el negativo sin quejarse.
 *
 * No es parte de la aplicación: vive acá porque solo lo usa esa prueba.
 * Imprime OK <id> o NO <motivo>, que es lo que lee el test.
 *
 *   php tests/descontar_en_paralelo.php <idProducto> <idUsuario> <cantidad> <largada>
 */

require __DIR__ . '/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[$producto, $usuario, $cantidad] = [
    (int) ($argv[1] ?? 0), (int) ($argv[2] ?? 0), (float) ($argv[3] ?? 0),
];

$arranque = (float) ($argv[4] ?? 0);
if ($arranque > 0) {
    $espera = $arranque - microtime(true);
    if ($espera > 0) {
        usleep((int) ($espera * 1_000_000));
    }
}

try {
    // Tipo de movimiento 4 = ajuste negativo (signo S). Se llama al
    // procedimiento de verdad, que es por donde pasa la pantalla.
    App\Servicios\Bd::procedimiento('sp_registrar_movimiento_inventario',
        [$producto, $usuario, 4, $cantidad, null, 'CONC', 'prueba de concurrencia']);
    echo 'OK' . PHP_EOL;
} catch (Throwable $e) {
    echo 'NO ' . str_replace(["\n", "\r"], ' ', $e->getMessage()) . PHP_EOL;
}
