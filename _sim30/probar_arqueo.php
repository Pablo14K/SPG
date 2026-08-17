<?php
require __DIR__ . '/lib.php';
use Illuminate\Support\Facades\DB;
$k = DB::selectOne('SELECT id_caja FROM caja ORDER BY id_caja DESC LIMIT 1');
$t = DB::scalar("SELECT COALESCE((SELECT SUM(fn_pago_proveedor_monto(pp.id_pago_proveedor))
                   FROM pago_proveedor pp JOIN metodo_pago mp2 ON mp2.id_metodo_pago = pp.id_metodo_pago
                  WHERE pp.id_caja = ? AND pp.id_estado_pago_proveedor = 1 AND mp2.tipo = 'EFECTIVO'), 0)
                + COALESCE((SELECT SUM(fn_pago_personal_monto(ps.id_pago_personal))
                   FROM pago_personal ps JOIN metodo_pago mp3 ON mp3.id_metodo_pago = ps.id_metodo_pago
                  WHERE ps.id_caja = ? AND ps.id_estado_pago = 1 AND mp3.tipo = 'EFECTIVO'), 0)",
                [$k->id_caja, $k->id_caja]);
echo 'la consulta corrigida corre y devuelve: ', $t, PHP_EOL;
