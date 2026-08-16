<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Servicios\Agenda;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * FA-01 y CJ-01: los dos candados que faltaban, probados como pasa de verdad.
 *
 * Como `ConcurrenciaAgendaTest`, esta prueba **no puede correr dentro de una
 * transacción**: lo que mide es justamente qué ven entre sí varias conexiones
 * distintas. Por eso limpia a mano lo que crea, en `tearDown`.
 *
 * Los dos hallazgos son el mismo error visto en dos lugares —leer un estado,
 * decidir y después escribir, sin candado— y los encontró la simulación de 90
 * días repitiendo cada escenario cuatro veces:
 *
 *  · **FA-01**: tres cobros simultáneos de la misma factura pasaban los tres,
 *    porque los tres leían el mismo saldo antes de que ninguno confirmara. La
 *    factura #1 —total Gs. 25.000, con seña de Gs. 12.500— terminó con
 *    Gs. 25.000 cobrados y saldo Gs. −12.500: plata de más en la caja, y
 *    ninguna pantalla que muestre un saldo negativo.
 *
 *  · **CJ-01**: tres aperturas de caja a la vez dejaban dos, y hasta tres,
 *    cajas abiertas. Con dos abiertas cada cobro cae en una u otra según quién
 *    lo registre, y ningún cierre cuadra.
 */
class ConcurrenciaCobroTest extends TestCase
{
    private const INTENTOS = 3;

    /** Cuántas veces se repite la ráfaga: la carrera no se gana siempre. */
    private const RONDAS = 4;

    /** @var list<int> */
    private array $cobrosCreados = [];

    /** @var list<int> */
    private array $cajasCreadas = [];

    /** @var list<int> */
    private array $movimientosCreados = [];

    /** @var list<int> */
    private array $citasCreadas = [];

    /**
     * Cajas que estaban abiertas antes de la prueba y hay que devolver así.
     *
     * Va acá y no al final del método a propósito: **si una aserción falla, lo
     * que viene después del assert no se ejecuta**, así que una restauración
     * escrita en línea deja la base torcida para las pruebas que siguen. Pasó:
     * una corrida fallida a propósito dejó el salón sin ninguna caja abierta y
     * la prueba de la seña, que necesita una, se saltó sin decir por qué.
     *
     * @var list<int>
     */
    private array $cajasQueEstabanAbiertas = [];

    protected function tearDown(): void
    {
        foreach ($this->cobrosCreados as $id) {
            DB::delete('DELETE FROM cobro_tarjeta WHERE id_cobro = ?', [$id]);
            DB::delete('DELETE FROM cobro_banco WHERE id_cobro = ?', [$id]);
            DB::delete('DELETE FROM cobro WHERE id_cobro = ?', [$id]);
        }
        foreach ($this->cajasCreadas as $id) {
            DB::delete('DELETE FROM caja WHERE id_caja = ?', [$id]);
        }
        foreach ($this->movimientosCreados as $id) {
            DB::delete('DELETE FROM movimiento_inventario WHERE id_movimiento = ?', [$id]);
        }
        foreach ($this->citasCreadas as $id) {
            DB::delete('DELETE FROM cita_servicio WHERE id_cita = ?', [$id]);
            DB::delete('DELETE FROM notificacion WHERE id_cita = ?', [$id]);
            DB::delete('DELETE FROM cita WHERE id_cita = ?', [$id]);
        }

        // El salón vuelve a quedar con la caja que tenía abierta.
        if ($this->cajasQueEstabanAbiertas) {
            DB::update('UPDATE caja SET id_estado_caja = 2 WHERE id_estado_caja = 1');
            foreach ($this->cajasQueEstabanAbiertas as $id) {
                DB::update('UPDATE caja SET id_estado_caja = 1 WHERE id_caja = ?', [$id]);
            }
        }

        $this->cobrosCreados = [];
        $this->cajasCreadas = [];
        $this->movimientosCreados = [];
        $this->citasCreadas = [];
        $this->cajasQueEstabanAbiertas = [];

        parent::tearDown();
    }

    #[Test]
    public function tres_cobros_simultaneos_no_dejan_la_factura_sobrecobrada(): void
    {
        // Una factura vigente con saldo, y la caja donde va a caer el cobro.
        $factura = DB::selectOne(
            'SELECT f.id_factura, fn_factura_saldo(f.id_factura) AS saldo
               FROM factura f
               JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = f.id_tipo_comprobante
              WHERE f.id_estado_factura = 1 AND tc.signo = 1
                AND fn_factura_saldo(f.id_factura) > 0
              ORDER BY f.id_factura DESC LIMIT 1'
        );
        if (! $factura) {
            $this->markTestSkipped('No hay ninguna factura con saldo en la base de prueba.');
        }
        $saldo = (float) $factura->saldo;
        $idFactura = (int) $factura->id_factura;

        $caja = (int) DB::scalar('SELECT id_caja FROM caja WHERE id_estado_caja = 1 ORDER BY id_caja DESC LIMIT 1');
        if (! $caja) {
            $caja = (int) DB::scalar('SELECT id_caja FROM caja ORDER BY id_caja DESC LIMIT 1');
        }
        $usuario = (int) DB::scalar('SELECT id_usuario FROM usuario WHERE activo = 1 ORDER BY id_usuario LIMIT 1');

        $cobrosAntes = $this->cobrosDe($idFactura);

        // Los tres piden el saldo ENTERO: si el candado no está, entran los
        // tres y la factura queda con saldo negativo.
        $largada = microtime(true) + 2.5;
        $salidas = $this->enParalelo(base_path('tests/cobrar_en_paralelo.php'), [
            $idFactura, $usuario, $saldo, $caja,
        ], $largada);

        foreach ($this->cobrosDe($idFactura) as $id) {
            if (! in_array($id, $cobrosAntes, true)) {
                $this->cobrosCreados[] = $id;
            }
        }

        $aceptados = count($this->cobrosCreados);
        $this->assertSame(1, $aceptados,
            "Se aceptaron $aceptados cobros por el saldo entero de la misma factura. El candado "
            . 'de sp_registrar_cobro no está protegiendo, o Facturacion::cobrar() perdió su '
            . 'transacción. Salidas: ' . implode(' | ', $salidas));

        $saldoFinal = (float) DB::scalar('SELECT fn_factura_saldo(?)', [$idFactura]);
        $this->assertGreaterThanOrEqual(0, $saldoFinal,
            "La factura quedó con saldo $saldoFinal: se le cobró de más a la clienta.");
    }

    #[Test]
    public function tres_aperturas_simultaneas_dejan_una_sola_caja_abierta(): void
    {
        // **Tres cuentas DISTINTAS, y eso es lo que mide la prueba.** Con una
        // sola cuenta pasa igual con el disparador viejo, porque ése ya impedía
        // la segunda caja del MISMO usuario: lo que no impedía —y es el
        // hallazgo— es que tres personas del salón abran una cada una.
        $cuentas = array_map(
            fn ($u) => (int) $u->id_usuario,
            DB::select('SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
                         WHERE u.activo = 1 AND r.es_personal = 1
                         ORDER BY u.id_usuario LIMIT ' . self::INTENTOS)
        );
        if (count($cuentas) < self::INTENTOS) {
            $this->markTestSkipped('Hacen falta ' . self::INTENTOS . ' cuentas de personal en la base de prueba.');
        }

        // Se parte de un salón sin caja abierta, que es la situación real de
        // las 07:40: tres personas llegan y las tres aprietan «Abrir caja».
        // Lo que había queda anotado para que `tearDown` lo devuelva pase lo
        // que pase con las aserciones.
        foreach (DB::select('SELECT id_caja FROM caja WHERE id_estado_caja = 1') as $c) {
            $this->cajasQueEstabanAbiertas[] = (int) $c->id_caja;
        }
        DB::update('UPDATE caja SET id_estado_caja = 2 WHERE id_estado_caja = 1');

        $antes = (int) DB::scalar('SELECT COALESCE(MAX(id_caja),0) FROM caja');

        $largada = microtime(true) + 2.5;
        $salidas = $this->enParalelo(
            base_path('tests/abrir_caja_en_paralelo.php'),
            array_map(fn ($u) => [$u], $cuentas),
            $largada
        );

        foreach (DB::select('SELECT id_caja FROM caja WHERE id_caja > ?', [$antes]) as $c) {
            $this->cajasCreadas[] = (int) $c->id_caja;
        }

        $simultaneas = (int) DB::scalar('SELECT COUNT(*) FROM caja WHERE id_estado_caja = 1');
        $this->assertSame(1, $simultaneas,
            "Quedaron $simultaneas cajas abiertas a la vez. trg_caja_bi tiene que impedir "
            . 'CUALQUIER segunda caja abierta, no sólo la del mismo usuario. '
            . 'Salidas: ' . implode(' | ', $salidas));
    }

    #[Test]
    public function tres_salidas_simultaneas_no_dejan_el_stock_en_negativo(): void
    {
        // Un producto con stock, del que los tres procesos van a sacar el
        // total: si el control no toma candado, los tres suman lo mismo, los
        // tres pasan y el stock queda en el doble negativo.
        $prod = DB::selectOne(
            'SELECT p.id_producto, fn_producto_stock(p.id_producto) AS stock
               FROM producto p
              WHERE p.activo = 1 AND fn_producto_stock(p.id_producto) > 0
              ORDER BY fn_producto_stock(p.id_producto) LIMIT 1'
        );
        if (! $prod) {
            $this->markTestSkipped('No hay ningún producto con stock en la base de prueba.');
        }
        $idProducto = (int) $prod->id_producto;
        $usuario = (int) DB::scalar('SELECT id_usuario FROM usuario WHERE activo = 1 ORDER BY id_usuario LIMIT 1');

        // **La ráfaga se repite, y no es exceso de celo: la carrera no se gana
        // siempre.** Con una sola pasada esta prueba llegó a quedar en verde
        // con el candado sacado a propósito, que es la peor forma de prueba —
        // la que dice que sí sin haber medido nada. El propio QA la reprodujo
        // 3 de 4 veces.
        for ($ronda = 1; $ronda <= self::RONDAS; $ronda++) {
            $stock = (float) DB::scalar('SELECT fn_producto_stock(?)', [$idProducto]);
            if ($stock <= 0) {
                break;   // ya no queda nada que sacar
            }

            $antes = (int) DB::scalar('SELECT COALESCE(MAX(id_movimiento),0) FROM movimiento_inventario');

            $largada = microtime(true) + 2.5;
            $salidas = $this->enParalelo(base_path('tests/descontar_en_paralelo.php'), [
                $idProducto, $usuario, $stock,
            ], $largada);

            $nuevos = [];
            foreach (DB::select('SELECT id_movimiento FROM movimiento_inventario WHERE id_movimiento > ?', [$antes]) as $m) {
                $nuevos[] = (int) $m->id_movimiento;
                $this->movimientosCreados[] = (int) $m->id_movimiento;
            }

            $stockFinal = (float) DB::scalar('SELECT fn_producto_stock(?)', [$idProducto]);

            $this->assertGreaterThanOrEqual(0, $stockFinal,
                "Ronda $ronda: el stock quedó en $stockFinal. Un stock negativo no lo detecta "
                . 'nadie —fn_producto_stock lo devuelve sin quejarse, la vista de bajo stock lo '
                . 'lista como uno más y el arqueo cierra igual—, así que queda corrompido para '
                . 'siempre. Salidas: ' . implode(' | ', $salidas));

            $this->assertCount(1, $nuevos,
                'Ronda ' . $ronda . ': se aceptaron ' . count($nuevos) . ' salidas por el stock '
                . 'entero del mismo producto. El candado de trg_movinv_bi no está protegiendo. '
                . 'Salidas: ' . implode(' | ', $salidas));

            // Se devuelve el stock para que la ronda siguiente mida lo mismo.
            foreach ($nuevos as $id) {
                DB::delete('DELETE FROM movimiento_inventario WHERE id_movimiento = ?', [$id]);
            }
            $this->movimientosCreados = array_values(array_diff($this->movimientosCreados, $nuevos));
        }
    }

    /**
     * AG-04: cancelar y reprogramar a la vez no puede perder la cancelación.
     *
     * Las dos acciones leían el estado y escribían sin candado sobre la cita,
     * así que ganaba la última en confirmar: la cita quedaba **Reprogramada
     * aunque la cancelación se hubiera registrado en la auditoría**. La clienta
     * cree que canceló, el horario sigue ocupado y alguien la va a esperar.
     *
     * Lo que se exige acá no es que gane una en particular —las dos llegaron a
     * la vez y cualquiera puede ganar—: es que **el resultado sea coherente con
     * lo que el sistema contestó**. Si la cancelación dijo que sí, la cita
     * quedó cancelada; si dijo que no, quedó reprogramada.
     */
    #[Test]
    public function cancelar_y_reprogramar_a_la_vez_no_pierde_la_cancelacion(): void
    {
        $cliente = (int) DB::scalar('SELECT id_cliente FROM cliente WHERE activo = 1 ORDER BY id_cliente LIMIT 1');
        $prof = (int) DB::scalar(
            'SELECT u.id_usuario FROM usuario u
               JOIN usuario_turno ut ON ut.id_usuario = u.id_usuario
              WHERE u.activo = 1 ORDER BY u.id_usuario LIMIT 1'
        );
        $servicio = (int) DB::scalar('SELECT id_servicio FROM servicio WHERE activo = 1 ORDER BY duracion_min LIMIT 1');
        if (! $cliente || ! $prof || ! $servicio) {
            $this->markTestSkipped('Falta un cliente, un profesional con turno o un servicio.');
        }

        $dur = Agenda::duracion([$servicio]);

        for ($ronda = 1; $ronda <= self::RONDAS; $ronda++) {
            // Una cita nueva por ronda, y un hueco al que mover la reprogramación.
            $huecos = $this->dosHuecos($prof, $dur);
            if ($huecos === null) {
                $this->markTestSkipped('El profesional no tiene dos huecos libres.');
            }
            [$donde, $adonde] = $huecos;

            DB::insert('INSERT INTO cita (id_cliente,id_usuario,id_estado_cita,fecha_hora,id_sucursal) VALUES (?,?,?,?,1)',
                [$cliente, $prof, 1, $donde]);
            $idCita = (int) DB::getPdo()->lastInsertId();
            DB::insert('INSERT INTO cita_servicio (id_cita,id_servicio,id_usuario) VALUES (?,?,NULL)', [$idCita, $servicio]);
            $this->citasCreadas[] = $idCita;

            $largada = microtime(true) + 2.5;
            $salidas = $this->enParalelo(base_path('tests/cancelar_o_reprogramar.php'), [
                [$idCita, 'cancelar', $donde],
                [$idCita, 'reprogramar', $adonde],
            ], $largada);

            $cancelo = (bool) array_filter($salidas, fn ($s) => str_starts_with($s, 'OK cancelar'));
            $estado = (int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita = ?', [$idCita]);

            if ($cancelo) {
                $this->assertSame(3, $estado,
                    "Ronda $ronda: la cancelación dijo que sí y la cita no quedó cancelada. "
                    . 'La clienta cree que canceló y el horario sigue ocupado. '
                    . 'Salidas: ' . implode(' | ', $salidas));
            } else {
                $this->assertNotSame(3, $estado,
                    "Ronda $ronda: la cancelación falló pero la cita quedó cancelada igual. "
                    . 'Salidas: ' . implode(' | ', $salidas));
            }
        }
    }

    /**
     * Dos horarios libres de ese profesional: uno donde nace la cita y otro
     * al que la reprogramación la quiere mover.
     *
     * @return array{0:string,1:string}|null
     */
    private function dosHuecos(int $profesional, int $duracion): ?array
    {
        $encontrados = [];
        $desde = date('Y-m-d', strtotime(ahora_bd('Y-m-d') . ' +1 day'));

        foreach (Agenda::diasConCupo($profesional, $desde, 21, $duracion) as $dia) {
            foreach (Agenda::slotsProfesional($profesional, $dia, $duracion) as $hora) {
                $fechaHora = $dia . ' ' . substr($hora, 0, 5) . ':00';
                if (Agenda::huecoLibre($profesional, $fechaHora, $duracion)) {
                    $encontrados[] = $fechaHora;
                    if (count($encontrados) === 2) {
                        return [$encontrados[0], $encontrados[1]];
                    }
                }
            }
        }

        return null;
    }

    /** @return list<int> */
    private function cobrosDe(int $idFactura): array
    {
        return array_map(
            fn ($r) => (int) $r->id_cobro,
            DB::select('SELECT id_cobro FROM cobro WHERE id_factura = ?', [$idFactura])
        );
    }

    /**
     * Lanza procesos de verdad, todos largando en el mismo instante.
     *
     * `$args` puede ser una lista de argumentos —la misma para todos— o una
     * lista de listas, un juego por proceso: eso último hace falta cuando lo
     * que se prueba es que sean **cuentas distintas**.
     *
     * @return list<string>
     */
    private function enParalelo(string $script, array $args, float $largada): array
    {
        $juegos = is_array($args[0] ?? null)
            ? $args
            : array_fill(0, self::INTENTOS, $args);

        $procesos = [];
        foreach ($juegos as $juego) {
            $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' '
                 . implode(' ', array_map(fn ($a) => escapeshellarg((string) $a), $juego))
                 . ' ' . $largada;

            $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tuberias, base_path());
            if (is_resource($p)) {
                $procesos[] = [$p, $tuberias];
            }
        }
        $this->assertCount(count($juegos), $procesos, 'No se pudieron lanzar los procesos en paralelo.');

        $salidas = [];
        foreach ($procesos as [$p, $tuberias]) {
            $salidas[] = trim((string) stream_get_contents($tuberias[1]));
            fclose($tuberias[1]);
            fclose($tuberias[2]);
            proc_close($p);
        }

        return $salidas;
    }
}
