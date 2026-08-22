<?php
/**
 * Barrido de TODAS las pantallas, con todos los roles.
 *
 * Las pruebas abren una lista curada; acá se abre lo que exista. Una pantalla
 * que revienta al dibujar no falla al arrancar y no aparece en ningún log
 * hasta que alguien entra — que es como se descubrieron Auditoría (500 desde
 * la 6.1.1), Nueva compra y la agenda.
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

echo "\n=== 7. Barrido de pantallas ===\n";

// Un id de verdad para cada parámetro que las rutas piden.
$ids = [
    'id' => (int) DB::scalar('SELECT MAX(id_factura) FROM factura'),
];

$reemplazos = [
    'seguridad/usuarios/form/{id?}'   => (int) DB::scalar('SELECT MAX(id_usuario) FROM usuario'),
    'seguridad/comisiones/form/{id?}' => (int) DB::scalar('SELECT MAX(id_comision) FROM comision'),
    'seguridad/sucursales/form/{id?}' => (int) DB::scalar('SELECT MAX(id_sucursal) FROM sucursal'),
    'servicios/form/{id?}'            => (int) DB::scalar('SELECT MAX(id_servicio) FROM servicio'),
    'servicios/descuentos/form/{id?}' => (int) DB::scalar('SELECT MAX(id_descuento) FROM descuento'),
    'inventario/productos/form/{id?}' => (int) DB::scalar('SELECT MAX(id_producto) FROM producto'),
    'inventario/proveedores/form/{id?}' => (int) DB::scalar('SELECT MAX(id_proveedor) FROM proveedor'),
    'clientes/form/{id?}'             => (int) DB::scalar('SELECT MAX(id_cliente) FROM cliente'),
    'clientes/{id}/historial'         => (int) DB::scalar('SELECT MAX(id_cliente) FROM cliente'),
];

$queryPorRuta = [
    'facturacion.factura_ver'   => ['id' => (int) DB::scalar('SELECT MAX(id_factura) FROM factura')],
    'facturacion.receptor'      => [
        'cita' => (int) DB::scalar('SELECT MAX(id_cita) FROM cita'), 'tipo' => 1, 'condicion' => 1],
    'citas.atender'             => ['id' => (int) DB::scalar(
        'SELECT MAX(c.id_cita) FROM cita c WHERE EXISTS (SELECT 1 FROM cita_servicio x WHERE x.id_cita = c.id_cita)')],
    'inventario.compra_ver'     => ['id' => (int) DB::scalar('SELECT MAX(id_compra) FROM compra')],
    'portal.atencion'           => ['id' => (int) DB::scalar('SELECT MAX(id_cita) FROM cita')],
    'facturacion.sena.comprobante' => ['id' => (int) DB::scalar('SELECT MAX(id_solicitud) FROM sena_solicitud')],
    'facturacion.sifen.archivo' => ['id' => (int) DB::scalar('SELECT MAX(id_factura) FROM factura'), 'tipo' => 'txt'],
];

/** Las rutas GET que se pueden abrir sin inventar nada. */
function rutasGet(array $reemplazos): array
{
    $out = [];
    foreach (Route::getRoutes() as $r) {
        if (! in_array('GET', $r->methods(), true)) {
            continue;
        }
        $uri = $r->uri();
        $nombre = (string) $r->getName();

        // Las del framework y las que descargan archivos quedan afuera.
        if ($nombre === '' || str_starts_with($uri, '_') || str_starts_with($uri, 'storage/')
            || $uri === 'up' || str_contains($uri, '{path}')) {
            continue;
        }

        if (isset($reemplazos[$uri])) {
            $uri = preg_replace('/\{[^}]+\}/', (string) $reemplazos[$uri], $uri);
        } elseif (str_contains($uri, '{')) {
            // Parámetro obligatorio sin un id conocido: se saltea antes que
            // inventar uno y medir un 404 que no dice nada.
            if (! str_contains($uri, '?}')) {
                continue;
            }
            $uri = preg_replace('/\/\{[^}]+\?\}/', '', $uri);
        }

        $out[$nombre] = '/' . ltrim((string) $uri, '/');
    }

    return $out;
}

$rutas = rutasGet($reemplazos);
echo '  ' . count($rutas) . " pantallas GET a abrir\n\n";

// Una cuenta por rol. `ONLY_FULL_GROUP_BY` está activo, así que se agrupa
// por la clave y se toma el mínimo, no una columna suelta.
$cuentas = DB::select(
    "SELECT MIN(u.username) AS username, r.nombre AS rol, r.id_rol FROM usuario u
       JOIN rol r ON r.id_rol = u.id_rol
      WHERE u.activo = 1 GROUP BY r.id_rol, r.nombre ORDER BY r.id_rol");

$suc = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');
$total = ['200' => 0, '302' => 0, '403' => 0, '404' => 0, 'otros' => 0];

foreach ($cuentas as $c) {
    $n = new Nav();
    if (! $n->entrar($c->username, 'qa123456', $suc)) {
        echo '  ' . $c->rol . ": no pudo entrar\n";
        continue;
    }

    $r = ['200' => 0, '302' => 0, '403' => 0, '404' => 0, 'otros' => 0];
    foreach ($rutas as $nombre => $uri) {
        $q = $queryPorRuta[$nombre] ?? [];
        $n->get($uri, $q);
        $cod = $n->codigo();

        if ($cod >= 500) {
            hallazgo('CRITICO', $c->rol . ' · ' . $nombre, 'HTTP ' . $cod . '  (' . $uri . ')');
            $r['otros']++;
        } elseif (in_array($cod, [200, 302, 403, 404], true)) {
            $r[(string) $cod]++;
        } else {
            $r['otros']++;
            hallazgo('MEDIO', $c->rol . ' · ' . $nombre, 'HTTP ' . $cod);
        }
    }

    printf("  %-24s 200:%-4d 302:%-4d 403:%-4d 404:%-3d otros:%d\n",
        $c->rol, $r['200'], $r['302'], $r['403'], $r['404'], $r['otros']);
    foreach ($r as $k => $v) {
        $total[$k] += $v;
    }
}

echo "\n  TOTAL  200:" . $total['200'] . '  302:' . $total['302']
    . '  403:' . $total['403'] . '  404:' . $total['404'] . '  otros:' . $total['otros'] . "\n";

if ($total['otros'] === 0) {
    ok('barrido', 'ninguna pantalla reventó');
}
