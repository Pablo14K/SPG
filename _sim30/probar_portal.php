<?php
require __DIR__ . '/lib.php';
use Illuminate\Support\Facades\DB;

$u = DB::selectOne("SELECT u.username, c.id_cliente FROM usuario u
                    JOIN cliente c ON c.id_persona = u.id_persona
                    WHERE u.activo = 1 AND u.id_rol = 4 LIMIT 1");
$suc2  = (int) DB::scalar("SELECT id_sucursal FROM sucursal WHERE nombre='Peluqueria San Lorenzo' AND activo=1");
$sv    = (int) DB::scalar("SELECT id_servicio FROM servicio WHERE activo=1 LIMIT 1");

// Un profesional que SOLO trabaja en la sucursal 1
$ajeno = (int) DB::scalar("SELECT u.id_usuario FROM usuario u
                            JOIN usuario_sucursal us ON us.id_usuario=u.id_usuario
                           WHERE u.activo=1 AND u.id_rol=2
                           GROUP BY u.id_usuario
                          HAVING SUM(us.id_sucursal = ?) = 0 LIMIT 1", [$suc2]);
// Y uno que SÍ trabaja en la 2
$propio = (int) DB::scalar("SELECT u.id_usuario FROM usuario u
                             JOIN usuario_sucursal us ON us.id_usuario=u.id_usuario AND us.id_sucursal=?
                            WHERE u.activo=1 AND u.id_rol=2 LIMIT 1", [$suc2]);

$n = new Nav();
$n->cookies = []; $n->quien = $u->username;
$n->get('/entrar');
$n->post('/entrar', ['usuario' => $u->username, 'password' => 'cliente123', 'forzar' => '1']);

function reservar(Nav $n, int $suc, int $prof, int $sv, string $hora): array {
    $antes = (int) DB::scalar('SELECT COALESCE(MAX(id_cita),0) FROM cita');
    $n->post('/portal/reservar', [
        'id_sucursal' => $suc, 'id_usuario' => $prof, 'servicios' => [$sv],
        'fecha_hora' => ($GLOBALS['dia'] ?? date('Y-m-d', strtotime('+55 days'))) . ' ' . $hora,
    ])->seguir();
    $c = DB::selectOne('SELECT id_cita, id_usuario, id_sucursal FROM cita WHERE id_cita > ? ORDER BY id_cita DESC LIMIT 1', [$antes]);
    return [$c, $n->flashTxt()];
}

echo "sucursal elegida: $suc2 | profesional AJENO (no atiende ahí): $ajeno | profesional PROPIO: $propio\n\n";

echo "1) Con un profesional que NO atiende en esa sucursal:\n";
[$c, $f] = reservar($n, $suc2, $ajeno, $sv, '09:00');
echo $c ? "   se creó igual la cita #{$c->id_cita} en la sucursal {$c->id_sucursal}  → MAL\n"
        : "   rechazada → $f\n";

echo "\n2) Con un profesional de esa sucursal:\n";
// **Se busca un día en que ESE profesional atienda**, no uno cualquiera:
// +55 días caía domingo y el salón cierra, así que el guion tomaba el valor
// por defecto y culpaba al sistema de un rechazo correcto.
$dia = null;
for ($d = 50; $d <= 70 && $dia === null; $d++) {
    $cand = date('Y-m-d', strtotime("+$d days"));
    $n->get('/portal/disponibilidad', ['servicios' => [$sv], 'id_usuario' => $propio, 'fecha' => $cand]);
    $j = json_decode($n->body, true);
    if (! empty($j['horas'])) { $dia = $cand; }
}
$dia ??= date('Y-m-d', strtotime('+55 days'));
$GLOBALS['dia'] = $dia;
$hora = $j['horas'][0]['hora'] ?? '10:00';
echo "   el sistema ofrece: $hora
";
[$c2, $f2] = reservar($n, $suc2, $propio, $sv, $hora);
if ($c2) {
    echo "   cita #{$c2->id_cita} | sucursal pedida $suc2 | guardada {$c2->id_sucursal}";
    echo ((int) $c2->id_sucursal === $suc2 ? "  → CORRECTO\n" : "  → NO COINCIDE\n");
} else {
    echo "   no se creó: $f2\n";
}
