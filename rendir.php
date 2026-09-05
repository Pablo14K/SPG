<?php
use Illuminate\Support\Facades\DB;

$id = (int) DB::scalar("SELECT MAX(f.id_factura) FROM factura f
                         JOIN factura_electronica fe ON fe.id_factura = f.id_factura");
if (! $id) { $id = (int) DB::scalar('SELECT MAX(id_factura) FROM factura'); }

$f = DB::selectOne('SELECT v.*, fa.id_tipo_comprobante FROM vw_factura_resumen v
                      JOIN factura fa ON fa.id_factura = v.id_factura WHERE v.id_factura = ?', [$id]);

$m = new App\Mail\ComprobanteCliente(
    $f,
    DB::select('SELECT * FROM vw_detalle_factura WHERE id_factura = ? ORDER BY clase, item', [$id]),
    DB::select('SELECT co.fecha, co.monto, mp.nombre AS metodo FROM cobro co
                  JOIN metodo_pago mp ON mp.id_metodo_pago = co.id_metodo_pago
                 WHERE co.id_factura = ? AND co.id_estado_cobro = 1', [$id]),
    'Peluqueria Luque',
    ''
);

$html = $m->render();
echo 'factura        : ' . $id . ' · ' . $f->nro_comprobante . ' (tipo ' . $f->id_tipo_comprobante . ')' . PHP_EOL;
echo 'asunto         : ' . $m->envelope()->subject . PHP_EOL;
echo 'cuerpo         : ' . strlen($html) . ' caracteres' . PHP_EOL;
echo 'tiene el total : ' . (str_contains($html, 'Total') ? 'si' : 'NO') . PHP_EOL;
echo 'renglones      : ' . substr_count($html, 'border-bottom:1px solid #F2F0EC') / 3 . PHP_EOL;
echo 'adjuntos       : ' . count($m->attachments()) . PHP_EOL;
foreach ($m->attachments() as $a) { echo '   · ' . $a->as . PHP_EOL; }
