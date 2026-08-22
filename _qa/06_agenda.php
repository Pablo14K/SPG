<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
use Illuminate\Support\Facades\DB;

echo "\n=== 6. Disponibilidad del PORTAL (la que recibe la sucursal) ===\n";
$cl = DB::selectOne("SELECT u.username FROM usuario u JOIN rol r ON r.id_rol=u.id_rol WHERE r.id_rol=4 AND u.activo=1 LIMIT 1");
$n = new Nav();
if (! $n->entrar($cl->username, 'qa123456')) { echo "  (no se pudo entrar como clienta)\n"; return; }

$suc  = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');
$srv  = (int) DB::scalar('SELECT id_servicio FROM servicio WHERE activo = 1 LIMIT 1');
$prof = (int) DB::scalar('SELECT ut.id_usuario FROM usuario_turno ut LIMIT 1');
$sinT = (int) DB::scalar('SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol=u.id_rol
   WHERE r.es_personal=1 AND u.activo=1 AND NOT EXISTS(SELECT 1 FROM usuario_turno x WHERE x.id_usuario=u.id_usuario) LIMIT 1');

$casos = [
  'sucursal real'        => ['servicios'=>[$srv], 'id_usuario'=>$prof, 'sucursal'=>$suc],
  'sucursal INVENTADA'   => ['servicios'=>[$srv], 'id_usuario'=>$prof, 'sucursal'=>999999],
  'sucursal negativa'    => ['servicios'=>[$srv], 'id_usuario'=>$prof, 'sucursal'=>-1],
  'sucursal texto'       => ['servicios'=>[$srv], 'id_usuario'=>$prof, 'sucursal'=>'abc'],
  'profesional sin turno'=> ['servicios'=>[$srv], 'id_usuario'=>$sinT, 'sucursal'=>$suc],
  'sin sucursal'         => ['servicios'=>[$srv], 'id_usuario'=>$prof],
];
foreach ($casos as $que => $q) {
  $n->get('/portal/disponibilidad', $q);
  $j = json_decode($n->html(), true);
  $d = is_array($j['dias'] ?? null) ? count($j['dias']) : 0;
  printf("  %-24s HTTP %d · días=%d\n", $que, $n->codigo(), $d);
}
