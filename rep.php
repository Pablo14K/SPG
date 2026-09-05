<?php
use App\Servicios\Agenda;
use Illuminate\Support\Facades\DB;
$prof = DB::selectOne("SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol=u.id_rol
  WHERE r.es_personal=1 AND u.activo=1
    AND EXISTS (SELECT 1 FROM persona_servicio ps WHERE ps.id_persona=u.id_persona)
    AND EXISTS (SELECT 1 FROM servicio s WHERE s.activo=1 AND fn_usuario_hace_servicio(u.id_usuario,s.id_servicio)=0) LIMIT 1");
$id=(int)$prof->id_usuario;
$ajeno = DB::selectOne("SELECT s.id_servicio,s.nombre FROM servicio s WHERE s.activo=1 AND fn_usuario_hace_servicio(?,s.id_servicio)=0 LIMIT 1",[$id]);
$sid=(int)$ajeno->id_servicio;
echo "principal=$id  ajeno={$ajeno->nombre} ($sid)".PHP_EOL;
$quien = Agenda::quienHace([$sid],1)[$sid];
echo 'lo hacen en la sucursal 1: '.implode(',',$quien).PHP_EOL;
$fecha = date('Y-m-d 10:00:00', strtotime('+8 days'));
echo "fecha=$fecha  dia=".date('N', strtotime($fecha)).PHP_EOL;
$dur = (int) DB::scalar('SELECT duracion_min FROM servicio WHERE id_servicio=?',[$sid]);
foreach ($quien as $q) {
  $libre = Agenda::huecoLibre($q,$fecha,$dur) ? 'libre' : 'ocupado';
  $trab  = Agenda::trabajaEseDia($q,substr($fecha,0,10),1) ? 'trabaja' : 'no trabaja';
  echo "   $q -> $libre / $trab".PHP_EOL;
}
echo 'profesionalLibre: '.var_export(Agenda::profesionalLibre($fecha,$dur,1,[$sid]),true).PHP_EOL;
