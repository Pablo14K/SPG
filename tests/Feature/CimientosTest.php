<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Servicios\Agenda;
use App\Servicios\Bd;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Que los cimientos de la migración estén parados.
 *
 * No prueba pantallas: prueba que Laravel llegue a la lógica que vive en la
 * base y que los formatos con los que se muestra la plata y las cantidades
 * sigan dando lo mismo que en el sistema anterior.
 *
 * Corre contra `peluqueria_test` (ver phpunit.xml). Estas pruebas solo LEEN.
 */
class CimientosTest extends TestCase
{
    // -----------------------------------------------------------------
    //  Formato de números: lo que se muestra y lo que se escribe
    // -----------------------------------------------------------------

    #[Test]
    public function el_dinero_se_muestra_en_guaranies_sin_decimales(): void
    {
        $this->assertSame('Gs. 320.000', money(320000));
        $this->assertSame('Gs. 0', money(0));
        $this->assertSame('Gs. 1.284.000', money(1284000.4));
    }

    #[Test]
    public function los_montos_escritos_con_separador_de_miles_se_entienden(): void
    {
        // (float)"7.000" da 7: por eso existe num(). Si esto se rompe, el
        // salón cobra siete guaraníes en vez de siete mil.
        $this->assertSame(7000.0, num('7.000'));
        $this->assertSame(1250.0, num('1.250'));
        $this->assertSame(0.5, num('0,5'));
        $this->assertSame(7.5, num('7.5'));
        $this->assertSame(1234567.0, num('1,234,567'));
        $this->assertSame(0.0, num(''));
        $this->assertSame(60.0, num('abc', 60.0));
        $this->assertSame(1250, entero('1.250'));
    }

    #[Test]
    public function las_cantidades_enteras_no_muestran_decimales(): void
    {
        $this->assertSame('12', cant(12));
        $this->assertSame('12', cant(12.001));   // ruido de coma flotante
        $this->assertSame('0,5', cant(0.5));
        $this->assertSame('1,25', cant(1.25));
    }

    // -----------------------------------------------------------------
    //  El frasco y el mililitro
    // -----------------------------------------------------------------

    #[Test]
    public function treinta_mililitros_de_un_litro_descuentan_tres_centesimos(): void
    {
        $shampoo = ['contenido' => 1000, 'unidad_consumo' => 'ml', 'unidad_medida' => 'frasco'];

        $this->assertTrue(producto_fraccionado($shampoo));
        $this->assertSame(0.03, consumo_a_stock($shampoo, 30));
        $this->assertSame(500.0, stock_a_consumo($shampoo, 0.5));
        $this->assertSame('ml', unidad_consumo($shampoo));
    }

    #[Test]
    public function un_producto_sin_contenido_se_cuenta_por_unidad(): void
    {
        $sachet = ['contenido' => null, 'unidad_consumo' => null, 'unidad_medida' => 'unidad'];

        $this->assertFalse(producto_fraccionado($sachet));
        $this->assertSame(2.0, consumo_a_stock($sachet, 2));
        $this->assertSame('unidad', unidad_consumo($sachet));
    }

    // -----------------------------------------------------------------
    //  La hora: los dos relojes tienen que coincidir
    // -----------------------------------------------------------------

    #[Test]
    public function el_reloj_de_php_coincide_con_el_de_la_base(): void
    {
        // Si no coinciden, el fichaje de asistencia queda corrido y deja de
        // servir como prueba de quién estuvo en el salón.
        //
        // **Se le pregunta a la base DIRECTAMENTE, no por `ahora_bd()`.** Esa
        // función cachea el valor en un `static` —una vez por petición, que es
        // lo correcto en la web— pero una corrida de pruebas es UN solo
        // proceso: el `static` guarda la hora del primer llamado y sigue
        // devolviéndola cuatro minutos después.
        //
        // Con eso, lo que la prueba medía era **cuánto tarda la suite**, no la
        // zona horaria: en el host tardaba 108 s y daba 99 —pasaba raspando— y
        // en el contenedor tardaba 256 s y daba 92, o sea que fallaba con los
        // dos relojes perfectamente sincronizados. Es el defecto del entorno en
        // vez de la regla, otra vez.
        $desfase = abs(
            strtotime((string) DB::scalar('SELECT NOW()')) - strtotime(date('Y-m-d H:i:s'))
        );

        $this->assertLessThan(90, $desfase,
            'PHP y la base no están en la misma hora. Revisá la zona horaria del servidor.');
    }

    #[Test]
    public function ahora_bd_saca_la_hora_de_la_base_y_no_de_php(): void
    {
        // La otra mitad, que la de arriba dejó de cubrir al preguntar directo:
        // que `ahora_bd()` de verdad venga de la base y no de `date()`.
        //
        // **El margen es de diez minutos y no de noventa segundos, a
        // propósito.** La caché sólo puede dejar a `ahora_bd()` ATRÁS, y como
        // mucho lo que lleve corriendo la suite; con un margen ajustado la
        // prueba volvería a medir la duración de la corrida en vez de la
        // regla. Y sigue detectando lo que importa: un error de zona horaria
        // son 3.600 segundos como mínimo — el que este proyecto ya tuvo era
        // de 10.800.
        $this->assertLessThan(
            600,
            abs(strtotime(ahora_bd()) - strtotime((string) DB::scalar('SELECT NOW()'))),
            'ahora_bd() se separó de la base: no está saliendo de la conexión.'
        );
    }

    // -----------------------------------------------------------------
    //  La lógica sigue viviendo en la base
    // -----------------------------------------------------------------

    #[Test]
    public function la_base_conserva_sus_procedimientos_funciones_y_disparadores(): void
    {
        $procedimientos = DB::scalar("SELECT COUNT(*) FROM information_schema.routines
                                       WHERE routine_schema = DATABASE() AND routine_type = 'PROCEDURE'");
        $funciones = DB::scalar("SELECT COUNT(*) FROM information_schema.routines
                                  WHERE routine_schema = DATABASE() AND routine_type = 'FUNCTION'");
        $triggers = DB::scalar('SELECT COUNT(*) FROM information_schema.triggers
                                 WHERE trigger_schema = DATABASE()');

        $this->assertGreaterThanOrEqual(20, $procedimientos, 'Faltan procedimientos en la base de prueba.');
        $this->assertGreaterThanOrEqual(30, $funciones, 'Faltan funciones en la base de prueba.');
        $this->assertGreaterThanOrEqual(17, $triggers, 'Faltan disparadores en la base de prueba.');
    }

    #[Test]
    public function laravel_puede_ejecutar_las_funciones_de_la_base(): void
    {
        $idProducto = DB::scalar('SELECT MIN(id_producto) FROM producto');
        $this->assertNotNull($idProducto, 'La base de prueba no tiene productos cargados.');

        // Si el DEFINER de la rutina no existe en este servidor, esto revienta
        // con el error 1449 y es justo lo que hay que detectar temprano.
        $stock = Bd::funcion('fn_producto_stock(?,1)', [$idProducto]);

        $this->assertIsNumeric($stock);
    }

    #[Test]
    public function la_disponibilidad_la_sigue_decidiendo_la_base(): void
    {
        $idProfesional = DB::scalar("SELECT MIN(u.id_usuario) FROM usuario u
                                      JOIN rol r ON r.id_rol = u.id_rol
                                     WHERE u.activo = 1 AND r.es_personal = 1");
        $this->assertNotNull($idProfesional, 'La base de prueba no tiene personal activo.');

        $libre = Agenda::huecoLibre((int) $idProfesional, date('Y-m-d H:i:s', strtotime('+3 days 10:00')), 30);

        $this->assertIsBool($libre);
    }

    // -----------------------------------------------------------------
    //  El motor de la agenda
    // -----------------------------------------------------------------

    #[Test]
    public function la_duracion_de_una_cita_es_la_suma_de_sus_servicios(): void
    {
        $servicios = DB::select('SELECT id_servicio, duracion_min FROM servicio WHERE activo=1 ORDER BY id_servicio LIMIT 2');
        $this->assertNotEmpty($servicios, 'La base de prueba no tiene servicios cargados.');

        $esperado = array_sum(array_map(fn ($s) => (int) $s->duracion_min, $servicios));
        $ids = array_map(fn ($s) => (int) $s->id_servicio, $servicios);

        $this->assertSame($esperado, Agenda::duracion($ids));
        $this->assertSame(0, Agenda::duracion([]), 'Sin servicios la duración tiene que ser cero.');
    }

    #[Test]
    public function la_cita_dura_el_bloque_mas_largo_cuando_atienden_dos_personas(): void
    {
        // Color de 45 min con una y uñas de 30 con otra, a la vez, son 45
        // minutos de cita — no 75. Con la misma persona, sí se suman.
        //
        // **Los dos servicios tienen que ser de ZONAS distintas**, que es lo que
        // desde la 7.43.0 decide si pueden pasar a la vez: dos cosas sobre el
        // mismo pelo se turnan aunque las hagan dos personas. Antes bastaba con
        // tomar los dos primeros del catálogo, y la prueba pasaba por casualidad
        // —los dos eran de cabello y el paralelo lo daba la casilla, no la zona—.
        $servicios = DB::select(
            'SELECT s.id_servicio, s.duracion_min FROM servicio s
              WHERE s.activo = 1 AND s.id_zona IS NOT NULL
                AND s.id_servicio = (SELECT MIN(x.id_servicio) FROM servicio x
                                      WHERE x.id_zona = s.id_zona AND x.activo = 1)
              ORDER BY s.id_zona LIMIT 2'
        );
        if (count($servicios) < 2) {
            $this->markTestSkipped('Hacen falta dos servicios en la base de prueba.');
        }
        [$a, $b] = $servicios;
        $principal = 1;
        $ayudante = 2;

        $enParalelo = Agenda::duracionReparto(
            [(int) $a->id_servicio => 0, (int) $b->id_servicio => $ayudante], $principal
        );
        $mismaPersona = Agenda::duracionReparto(
            [(int) $a->id_servicio => 0, (int) $b->id_servicio => 0], $principal
        );

        $this->assertSame(max((int) $a->duracion_min, (int) $b->duracion_min), $enParalelo);
        $this->assertSame((int) $a->duracion_min + (int) $b->duracion_min, $mismaPersona);
    }

    #[Test]
    public function los_huecos_que_se_ofrecen_entran_enteros_en_el_turno(): void
    {
        $idProfesional = (int) DB::scalar("SELECT MIN(ut.id_usuario) FROM usuario_turno ut
                                            JOIN turno_laboral t ON t.id_turno = ut.id_turno AND t.activo = 1");
        if (! $idProfesional) {
            $this->markTestSkipped('La base de prueba no tiene turnos asignados.');
        }

        $fecha = date('Y-m-d', strtotime('+7 days'));
        $huecos = Agenda::slotsProfesional($idProfesional, $fecha, 30);

        $this->assertIsArray($huecos);
        foreach ($huecos as $h) {
            $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $h);
        }
    }

    /**
     * El espejo de PHP y la base tienen que decir lo mismo, hueco por hueco.
     *
     * **Es la única parte del sistema donde una regla se replica a propósito**,
     * y se hace por costo: preguntarle a `fn_verificar_disponibilidad` hueco por
     * hueco daba ~12.000 consultas y 38 segundos para el calendario de 60 días.
     * `Agenda::slotsProfesional()` arma los mismos huecos en memoria con tres
     * consultas.
     *
     * El riesgo de replicar es que se desfasen, y el síntoma es feo: la pantalla
     * ofrece un horario que el servidor después rechaza, o esconde uno que
     * estaba libre. CLAUDE.md pide rehacer esta comparación cada vez que se
     * tocan las reglas de disponibilidad —la última fue al permitir que dos
     * servicios exclusivos vayan en secuencia—, así que en vez de correrla a
     * mano queda escrita: ahora se rehace sola.
     */
    #[Test]
    public function el_espejo_de_php_dice_lo_mismo_que_la_base(): void
    {
        $profs = Agenda::profesionales();
        if (! $profs) {
            $this->markTestSkipped('La base de prueba no tiene profesionales que atiendan.');
        }

        $duracion = 30;
        $candidatos = 0;
        $discrepancias = [];

        foreach (array_slice($profs, 0, 3) as $p) {
            $id = (int) $p->id_usuario;

            for ($d = 1; $d <= 10; $d++) {
                $dia = date('Y-m-d', strtotime("+$d days"));
                $ofrecidos = Agenda::slotsProfesional($id, $dia, $duracion);

                // No alcanza con recorrer lo que PHP ofrece: así sólo se
                // detectaría que ofrece de más. Se recorre **la grilla entera**
                // del día, así también se ve lo que esconde de más.
                for ($m = 6 * 60; $m <= 22 * 60; $m += config('spg.agenda.paso_min', 15)) {
                    $hora = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
                    $php = in_array($hora, $ofrecidos, true);
                    $bd = Agenda::huecoLibre($id, "$dia $hora:00", $duracion);
                    $candidatos++;

                    if ($php !== $bd) {
                        $discrepancias[] = "usuario $id, $dia $hora: PHP dice "
                            . ($php ? 'libre' : 'ocupado') . ' y la base dice '
                            . ($bd ? 'libre' : 'ocupado');
                    }
                }
            }
        }

        $this->assertGreaterThan(0, $candidatos, 'No se comparó ningún hueco: la prueba no midió nada.');
        $this->assertSame([], array_slice($discrepancias, 0, 5),
            'El espejo de PHP y `fn_verificar_disponibilidad` dicen cosas distintas. '
            . 'La pantalla va a ofrecer horarios que el servidor rechaza, o a esconder '
            . 'huecos libres. Se comparó sobre ' . $candidatos . ' candidatos.');
    }

    // -----------------------------------------------------------------
    //  Que Laravel no ensucie la base que se entrega
    // -----------------------------------------------------------------

    #[Test]
    public function laravel_no_crea_sus_tablas_dentro_de_la_base_del_sistema(): void
    {
        $intrusas = DB::select("SELECT table_name AS t FROM information_schema.tables
                                 WHERE table_schema = DATABASE()
                                   AND table_name IN ('users','sessions','cache','cache_locks','jobs',
                                                      'job_batches','failed_jobs','migrations')");

        $this->assertEmpty($intrusas,
            'Aparecieron tablas de Laravel en la base: ' . implode(', ', array_column($intrusas, 't')));
    }
}
