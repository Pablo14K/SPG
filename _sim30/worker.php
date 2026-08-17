<?php
/**
 * Un solo pedido, en su propio proceso, para las pruebas de concurrencia.
 * argv: <etiqueta> <usuario> <pass> <metodo> <uri> <json-datos> <largada-float>
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

[$s, $etq, $usr, $pass, $met, $uri, $json, $largada] = array_pad($argv, 8, '');

$n = new Nav();
if ($usr !== '-') {
    $n->entrar($usr, $pass, true);
}

$datos = json_decode($json ?: '[]', true) ?: [];

// Todos largan juntos
$t = (float) $largada;
while (microtime(true) < $t) {
    usleep(500);
}

$n->req(strtoupper($met), $uri, $datos);
$n->seguir();

echo json_encode(['etq' => $etq, 'st' => $n->status, 'flash' => $n->flash], JSON_UNESCAPED_UNICODE), "\n";
