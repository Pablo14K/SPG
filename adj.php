<?php
use App\Servicios\Sifen;
use Illuminate\Support\Facades\DB;
$id = (int) DB::scalar('SELECT MAX(id_factura) FROM factura');
$f = DB::selectOne('SELECT v.*, fa.id_tipo_comprobante FROM vw_factura_resumen v JOIN factura fa ON fa.id_factura=v.id_factura WHERE v.id_factura=?',[$id]);
$m = new App\Mail\ComprobanteCliente($f, DB::select('SELECT * FROM vw_detalle_factura WHERE id_factura=?',[$id]), [], 'Salon', '');
$a = $m->attachments();
echo "Factura $id ({$f->nro_comprobante}) -> adjuntos: ".count($a).PHP_EOL;
foreach ($a as $x) { echo '  - '.$x->as.PHP_EOL; }
echo 'pdf: '.(Sifen::copia($id,'pdf') ?: 'NO').PHP_EOL;
