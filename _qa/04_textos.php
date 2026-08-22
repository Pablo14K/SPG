<?php
/**
 * Textos hostiles, fechas imposibles y parámetros basura.
 *
 * Lo que se busca: que nada de lo que escribe una persona vuelva a la pantalla
 * como HTML, que un texto larguísimo no rompa la consulta, y que la agenda no
 * reviente con una fecha que no existe.
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

use Illuminate\Support\Facades\DB;

echo "\n=== 4. Textos, fechas y parámetros ===\n";

$suc = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');
$n = new Nav();
if (! $n->entrar('admin', 'qa123456', $suc)) {
    hallazgo('CRITICO', 'ingreso', 'no se pudo entrar');

    return;
}

// ---------------------------------------------------------------------
//  ¿Lo que se escribe vuelve como HTML?
// ---------------------------------------------------------------------
$payloads = [
    'script'   => '<script>window.__qa=1</script>',
    'img'      => '<img src=x onerror="window.__qa=1">',
    'comilla'  => 'Ana " onmouseover="window.__qa=1',
    'sql'      => "x'; DROP TABLE cita; --",
    'largo'    => str_repeat('A', 5000),
    'unicode'  => "Ñandú 😀 ünïcödé \u{202E}reversed",
];

foreach ($payloads as $que => $txt) {
    // Un cliente nuevo con el texto en el nombre
    $antes = (int) DB::scalar('SELECT COUNT(*) FROM cliente');
    $n->post('/clientes/guardar', [
        'id_cliente' => 0, 'nombre' => $txt, 'apellido' => 'QA', 'telefono' => '0981000000',
    ]);
    if (! revisar($n, 'cliente · ' . $que)) {
        continue;
    }

    $creado = (int) DB::scalar('SELECT COUNT(*) FROM cliente') > $antes;
    if (! $creado) {
        ok('cliente · ' . $que, 'rechazado');
        continue;
    }

    // ¿Aparece crudo en la lista?
    $html = $n->get('/clientes')->html();
    $crudo = str_contains($html, '<script>window.__qa') || str_contains($html, 'onerror="window.__qa')
        || str_contains($html, 'onmouseover="window.__qa');
    if ($crudo) {
        hallazgo('CRITICO', 'cliente · ' . $que, 'el texto vuelve a la pantalla SIN escapar');
    } else {
        $guardado = (string) DB::scalar('SELECT nombre FROM persona ORDER BY id_persona DESC LIMIT 1');
        ok('cliente · ' . $que, 'guardado escapado (' . mb_strlen($guardado) . ' car.)');
    }
}

// Las tablas siguen ahí (la inyección no pasó)
$tablas = (int) DB::scalar(
    "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'");
if ($tablas < 70) {
    hallazgo('CRITICO', 'inyección SQL', 'quedan ' . $tablas . ' tablas: algo se borró');
} else {
    ok('inyección SQL', $tablas . ' tablas intactas');
}

// ---------------------------------------------------------------------
//  Fechas imposibles en la agenda
// ---------------------------------------------------------------------
$fechas = [
    'inexistente' => '2026-02-30',
    'mes 13'      => '2026-13-01',
    'texto'       => 'ayer',
    'vacía'       => '',
    'sql'         => "2026-01-01' OR '1'='1",
    'año 0'       => '0000-00-00',
    'lejísimos'   => '9999-12-31',
    'negativa'    => '-0001-01-01',
];

foreach ($fechas as $que => $d) {
    $n->get('/citas/agenda', ['dia' => $d]);
    if ($n->codigo() >= 500) {
        hallazgo('CRITICO', 'agenda · fecha ' . $que, 'HTTP ' . $n->codigo());
    } else {
        ok('agenda · fecha ' . $que, 'HTTP ' . $n->codigo());
    }
}

// ---------------------------------------------------------------------
//  El endpoint de disponibilidad, que es el que más parámetros recibe
// ---------------------------------------------------------------------
$combos = [
    'sin nada'          => [],
    'servicio inventado' => ['servicios' => [999999], 'id_usuario' => 0],
    'servicio negativo'  => ['servicios' => [-1], 'id_usuario' => 0],
    'usuario inventado'  => ['servicios' => [1], 'id_usuario' => 999999],
    'texto por id'       => ['servicios' => ['abc'], 'id_usuario' => 'xyz'],
    'muchos servicios'   => ['servicios' => range(1, 200), 'id_usuario' => 0],
    'sucursal inventada' => ['servicios' => [1], 'id_usuario' => 0, 'id_sucursal' => 999999],
    'anidado'            => ['servicios' => [['a' => 1]], 'id_usuario' => 0],
];

foreach ($combos as $que => $q) {
    $n->get('/citas/disponibilidad', $q);
    if ($n->codigo() >= 500) {
        hallazgo('CRITICO', 'disponibilidad · ' . $que, 'HTTP ' . $n->codigo());
    } else {
        $j = json_decode($n->html(), true);
        ok('disponibilidad · ' . $que, 'HTTP ' . $n->codigo() . ($j === null ? ' (no es JSON)' : ' · JSON ok'));
    }
}

// ---------------------------------------------------------------------
//  Paginación y filtros con basura
// ---------------------------------------------------------------------
$listas = ['/clientes', '/servicios', '/inventario/productos', '/facturacion/facturas',
           '/facturacion/cobros', '/seguridad/auditoria'];
$basura = [
    ['p' => -1], ['p' => 999999], ['p' => 'abc'],
    ['q' => str_repeat('x', 3000)],
    ['q' => "' OR 1=1 --"],
    ['desde' => 'no-es-fecha', 'hasta' => '2026-99-99'],
    ['export' => 'csv', 'p' => 'abc'],
];

foreach ($listas as $uri) {
    foreach ($basura as $i => $q) {
        $n->get($uri, $q);
        if ($n->codigo() >= 500) {
            hallazgo('CRITICO', 'lista ' . $uri, 'revienta con ' . json_encode($q));
        }
    }
    ok('lista ' . $uri, 'aguanta los ' . count($basura) . ' filtros basura');
}
