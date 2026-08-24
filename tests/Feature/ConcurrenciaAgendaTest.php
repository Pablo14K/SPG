<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Servicios\Agenda;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El candado anti-solapes, probado como pasa de verdad: procesos simultáneos.
 *
 * Esta es la única prueba del sistema que NO puede correr dentro de una
 * transacción, porque justamente lo que mide es qué ven entre sí varias
 * conexiones distintas. Por eso limpia lo que crea a mano, en tearDown.
 *
 * Antes del candado (`SELECT … FOR UPDATE` sobre la fila del profesional,
 * dentro de `sp_agendar_cita`) el QA midió **47 citas en 16 franjas, con 46
 * solapes**: las peticiones preguntaban "¿está libre?" y todas recibían que sí
 * antes de que ninguna hubiera insertado. Con el candado, la segunda espera y
 * cuando pregunta ya ve la cita de la primera.
 *
 * El candado solo vale si quien llama abrió una transacción, así que esta
 * prueba también cuida eso: si alguien saca el `Bd::enTransaccion()` de
 * `Agenda::agendar()`, acá aparecen dos citas y el test falla.
 */
class ConcurrenciaAgendaTest extends TestCase
{
    private const INTENTOS = 5;

    /** @var list<int> */
    private array $citasCreadas = [];

    protected function tearDown(): void
    {
        // Sin transacción que revierta: se borra lo que se creó, y en el orden
        // que las claves foráneas admiten.
        foreach ($this->citasCreadas as $id) {
            DB::delete('DELETE FROM cita_servicio WHERE id_cita = ?', [$id]);
            DB::delete('DELETE FROM notificacion WHERE id_cita = ?', [$id]);
            DB::delete('DELETE FROM cita WHERE id_cita = ?', [$id]);
        }
        $this->citasCreadas = [];

        parent::tearDown();
    }

    #[Test]
    public function cinco_reservas_simultaneas_al_mismo_hueco_dejan_una_sola_cita(): void
    {
        $cliente = (int) DB::scalar('SELECT id_cliente FROM cliente ORDER BY id_cliente LIMIT 1');
        $profesional = (int) DB::scalar(
            'SELECT u.id_usuario FROM usuario u
               JOIN usuario_turno ut ON ut.id_usuario = u.id_usuario
              WHERE u.activo = 1 ORDER BY u.id_usuario LIMIT 1'
        );
        if (! $cliente || ! $profesional) {
            $this->markTestSkipped('Falta un cliente o un profesional con turno en la base de prueba.');
        }

        // Con un servicio de verdad: `fn_cita_duracion` sale de `cita_servicio`,
        // así que una cita sin servicios ocuparía cero minutos y no se pisaría
        // con nada — la prueba pasaría siempre, midiendo nada.
        $servicio = (int) DB::scalar(
            'SELECT s.id_servicio FROM servicio s
               JOIN usuario u ON u.id_usuario = ?
               JOIN persona_servicio ps ON ps.id_servicio = s.id_servicio AND ps.id_persona = u.id_persona
              WHERE s.activo = 1 ORDER BY s.duracion_min LIMIT 1', [$profesional]
        ) ?: (int) DB::scalar('SELECT id_servicio FROM servicio WHERE activo = 1 ORDER BY duracion_min LIMIT 1');

        $duracion = Agenda::duracion([$servicio]);
        $hueco = $this->primerHuecoLibre($profesional, $duracion);
        if ($hueco === null) {
            $this->markTestSkipped('El profesional no tiene ningún hueco libre en los próximos días.');
        }

        // Todos los procesos apuntan al mismo instante para largar juntos
        $largada = microtime(true) + 2.5;
        $php = PHP_BINARY;
        $script = base_path('tests/reservar_en_paralelo.php');

        $procesos = [];
        for ($i = 0; $i < self::INTENTOS; $i++) {
            $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' '
                 . $cliente . ' ' . $profesional . ' ' . escapeshellarg($hueco) . ' ' . $servicio . ' ' . $largada;
            $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tuberias, base_path());
            if (is_resource($p)) {
                $procesos[] = [$p, $tuberias];
            }
        }
        $this->assertCount(self::INTENTOS, $procesos, 'No se pudieron lanzar los procesos en paralelo.');

        $salidas = [];
        foreach ($procesos as [$p, $tuberias]) {
            $salidas[] = trim((string) stream_get_contents($tuberias[1]));
            fclose($tuberias[1]);
            fclose($tuberias[2]);
            proc_close($p);
        }

        foreach ($salidas as $linea) {
            if (preg_match('/^OK (\d+)/', $linea, $m)) {
                $this->citasCreadas[] = (int) $m[1];
            }
        }

        $aceptadas = count($this->citasCreadas);
        $rechazadas = self::INTENTOS - $aceptadas;

        $this->assertSame(1, $aceptadas,
            "Se aceptaron $aceptadas reservas sobre el mismo horario. El candado de "
            . 'sp_agendar_cita no está protegiendo, o Agenda::agendar() perdió su transacción. '
            . 'Salidas: ' . implode(' | ', $salidas));

        $this->assertSame(self::INTENTOS - 1, $rechazadas);

        // Y en la base queda una sola cita ocupando esa franja
        $enLaFranja = (int) DB::scalar(
            'SELECT COUNT(*) FROM cita c JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
              WHERE c.id_usuario = ? AND c.fecha_hora = ? AND ec.bloquea_agenda = 1',
            [$profesional, $hueco]
        );
        $this->assertSame(1, $enLaFranja, 'Quedó más de una cita ocupando la misma franja.');
    }

    /** El primer horario que la agenda ofrece como libre para ese profesional. */
    private function primerHuecoLibre(int $profesional, int $duracion): ?string
    {
        $desde = date('Y-m-d', strtotime(ahora_bd('Y-m-d') . ' +1 day'));

        foreach (Agenda::diasConCupo($profesional, $desde, 21, $duracion) as $dia) {
            foreach (Agenda::slotsProfesional($profesional, $dia, $duracion) as $hora) {
                $fechaHora = $dia . ' ' . substr($hora, 0, 5) . ':00';
                // Se pregunta a la base, que es la autoridad: los slots de
                // arriba se arman en PHP solo para pintar la pantalla.
                if (Agenda::huecoLibre($profesional, $fechaHora, $duracion)) {
                    return $fechaHora;
                }
            }
        }

        return null;
    }
}
