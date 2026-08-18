<?php
require __DIR__ . '/lib.php';
use Illuminate\Support\Facades\DB;
$u = DB::selectOne("SELECT u.username FROM usuario u JOIN cliente c ON c.id_persona=u.id_persona WHERE u.activo=1 AND u.id_rol=4 LIMIT 1");
$suc2 = (int) DB::scalar("SELECT id_sucursal FROM sucursal WHERE nombre='Peluqueria San Lorenzo' AND activo=1");
$sv = (int) DB::scalar("SELECT id_servicio FROM servicio WHERE activo=1 LIMIT 1");
$propio = (int) DB::scalar("SELECT u.id_usuario FROM usuario u JOIN usuario_sucursal us ON us.id_usuario=u.id_usuario AND us.id_sucursal=? WHERE u.activo=1 AND u.id_rol=2 LIMIT 1", [$suc2]);
$n = new Nav(); $n->cookies=[]; $n->quien=$u->username;
$n->get('/entrar'); $n->post('/entrar', ['usuario'=>$u->username,'password'=>'cliente123','forzar'=>'1']);
$dia = date('Y-m-d', strtotime('+55 days'));
$n->get('/portal/disponibilidad', ['servicios'=>[$sv], 'id_usuario'=>$propio, 'fecha'=>$dia]);
$j = json_decode($n->body, true);
echo "dia $dia (", date('l', strtotime($dia)), ") profesional $propio\n";
echo "respuesta ok=", var_export($j['ok'] ?? null, true), " horas=", count($j['horas'] ?? []), "\n";
if (! empty($j['horas'])) { echo "primeras: ", implode(', ', array_map(fn($h)=>$h['hora'], array_slice($j['horas'],0,5))), "\n"; }
else { echo "NINGUNA: ese dia no atiende. Mi guion caia a 10:00 por defecto — el fallo era mio.\n"; }
