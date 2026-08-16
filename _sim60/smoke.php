<?php

declare(strict_types=1);

require __DIR__ . '/lib.php';

use Illuminate\Support\Facades\DB;

echo 'PHP  : ' . date('Y-m-d H:i:s') . PHP_EOL;
echo 'BD   : ' . DB::scalar('SELECT NOW()') . ' / ' . DB::scalar('SELECT DATABASE()') . PHP_EOL;
echo 'env  : ' . app()->environment() . PHP_EOL;

$n = new Nav();
var_dump($n->entrar('admin', 'admin123'));
echo 'loc  : ' . $n->location . PHP_EOL;
$n->get('/panel');
echo 'panel: ' . $n->status . ' len=' . strlen($n->body) . PHP_EOL;
$n->get('/citas/agenda');
echo 'agenda: ' . $n->status . PHP_EOL;
$n->get('/facturacion/caja');
echo 'caja: ' . $n->status . PHP_EOL;
$n->post('/facturacion/caja/abrir', ['monto_inicial' => '200.000']);
echo 'abrir: ' . $n->status . ' ' . $n->flashTxt() . PHP_EOL;
echo 'caja abierta: ' . json_encode(DB::selectOne('SELECT id_caja,monto_inicial,fecha_apertura FROM caja ORDER BY id_caja DESC LIMIT 1')) . PHP_EOL;
$n->salir();
echo 'FIN' . PHP_EOL;
