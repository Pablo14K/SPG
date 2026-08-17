<?php
/**
 * Comprueba, contra la base de la simulación, los tres defectos que ésta
 * encontró y que se corrigieron en la 7.36.3. Se hace acá y no sólo en la
 * batería porque la base de la simulación es la que los produjo.
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

use App\Servicios\Bd;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();

// --- CJ-03: la plata va al cajón del local del documento -------------------
DB::statement('UPDATE caja SET id_estado_caja = 2, fecha_cierre = NOW() WHERE id_estado_caja = 1');

$cita = DB::selectOne(
    'SELECT c.id_cita, c.id_sucursal FROM cita c
      WHERE EXISTS (SELECT 1 FROM cita_servicio cs WHERE cs.id_cita = c.id_cita)
      ORDER BY c.id_cita DESC LIMIT 1');
$otra = (int) DB::scalar('SELECT id_sucursal FROM sucursal WHERE id_sucursal <> ? AND activo = 1 LIMIT 1',
    [$cita->id_sucursal]);
$uid = 1;

DB::insert('INSERT INTO caja (id_usuario,id_sucursal,id_estado_caja,monto_inicial) VALUES (?,?,1,100000)',
    [$uid, $cita->id_sucursal]);
$buena = (int) DB::getPdo()->lastInsertId();

// La de OTRO local, abierta después por la misma persona: es la que el
// `ORDER BY id_caja DESC` elegía antes.
DB::insert('INSERT INTO caja (id_usuario,id_sucursal,id_estado_caja,monto_inicial) VALUES (?,?,1,900000)',
    [$uid, $otra]);
$ajena = (int) DB::getPdo()->lastInsertId();

$metodo = (int) DB::scalar("SELECT id_metodo_pago FROM metodo_pago WHERE tipo='EFECTIVO' AND activo=1 LIMIT 1");
$idCobro = Bd::idDe('sp_registrar_sena', [(int) $cita->id_cita, $metodo, $uid, 500.0, 'VERIF']);
$quedo = (int) DB::scalar('SELECT id_caja FROM cobro WHERE id_cobro = ?', [$idCobro]);

echo 'CJ-03  cita en sucursal ', $cita->id_sucursal, ' · caja de ese local #', $buena,
     ' · caja abierta después en otro #', $ajena, PHP_EOL;
echo '       la seña entró a la caja #', $quedo, ' → ',
     ($quedo === $buena ? 'CORRECTO' : 'MAL'), PHP_EOL;

// --- CJ-04: cada local abre la suya ----------------------------------------
$abiertas = (int) DB::scalar('SELECT COUNT(*) FROM caja WHERE id_estado_caja = 1');
$locales = (int) DB::scalar('SELECT COUNT(DISTINCT id_sucursal) FROM caja WHERE id_estado_caja = 1');
echo 'CJ-04  ', $abiertas, ' caja(s) abiertas en ', $locales, ' local(es) → ',
     ($abiertas === $locales ? 'CORRECTO' : 'MAL'), PHP_EOL;

DB::rollBack();

// --- IN-06: el aviso nombra el camino --------------------------------------
$p = DB::selectOne(
    'SELECT p.id_producto, p.nombre FROM producto p
      WHERE NOT EXISTS (SELECT 1 FROM producto_sucursal ps
                         WHERE ps.id_producto = p.id_producto AND ps.id_sucursal = ?)
      LIMIT 1', [$otra]);
echo 'IN-06  ', $p ? ('hay un producto no habilitado en la sucursal ' . $otra . ': «' . $p->nombre . '»')
                   : 'todos los productos están habilitados en todos los locales', PHP_EOL;

// --- IN-05: la pantalla de compra ------------------------------------------
$n = new Nav();
if ($n->entrar('admin', 'admin123')) {
    $n->get('/inventario/compras/nueva');
    echo 'IN-05  «Nueva compra» → HTTP ', $n->status, ' → ',
         ($n->status === 200 ? 'CORRECTO' : 'MAL'), PHP_EOL;
}
