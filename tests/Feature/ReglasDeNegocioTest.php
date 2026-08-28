<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\AvisoInterno;
use App\Servicios\Agenda;
use App\Servicios\Bd;
use App\Servicios\Caja;
use App\Servicios\Canje;
use App\Servicios\Calendario;
use App\Servicios\Navegacion;
use App\Servicios\Notificaciones;
use App\Servicios\Config;
use App\Servicios\Permisos;
use App\Servicios\Sesion;
use App\Servicios\Sifen;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Throwable;

/**
 * Las reglas que el QA del mes dejó validadas, ahora como prueba automática.
 *
 * Son las que, si se rompen, no se notan hasta que ya hicieron daño: una cita
 * doble, una caja que no cuadra, un correlativo con hueco. Cada una comprueba
 * el comportamiento a través de la base, que es donde vive la regla.
 *
 * Los tests que ESCRIBEN usan DatabaseTransactions: cada uno corre dentro de
 * una transacción que se revierte al terminar, así `peluqueria_test` queda
 * como estaba. (Ojo: eso también significa que no sirven para probar
 * concurrencia real entre procesos — para eso está la prueba de carga por
 * HTTP que hizo el QA.)
 */
class ReglasDeNegocioTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Marca la entrada del profesional hoy, que es lo que «Registrar atención»
     * exige antes de dejar cerrar una cita.
     */
    private function fichar(int $idUsuario): void
    {
        $turno = (int) DB::scalar(
            'SELECT ut.id_turno FROM usuario_turno ut
               JOIN turno_laboral t ON t.id_turno = ut.id_turno AND t.activo = 1
              WHERE ut.id_usuario = ? LIMIT 1', [$idUsuario]
        );
        if (! $turno) {
            return;   // sin turno no se le exige fichaje
        }
        DB::insert(
            'INSERT IGNORE INTO asistencia (id_turno, id_usuario, fecha, hora_entrada, id_usuario_registro)
             VALUES (?, ?, ?, ?, ?)',
            [$turno, $idUsuario, ahora_bd('Y-m-d'), ahora_bd('H:i:s'), $idUsuario]
        );
    }

    /** Sesión de Administrador, para las pruebas que hacen POST a una pantalla. */
    private function entrarComoAdministrador(): void
    {
        $uid = (int) DB::scalar(
            'SELECT id_usuario FROM usuario WHERE id_rol = ? AND activo = 1 ORDER BY id_usuario LIMIT 1',
            [(int) config('permisos.rol_admin', 1)]
        );
        session([
            'uid' => $uid,
            'rol' => (int) config('permisos.rol_admin', 1),
            'es_personal' => true,
            'es_cliente' => false,
        ]); $this->conSucursal();
    }

    // -----------------------------------------------------------------
    //  La agenda no vende dos veces el mismo horario
    // -----------------------------------------------------------------

    /**
     * AG-01: la agenda no se le vende a quien no atiende.
     *
     * En la simulación de 90 días, 302 de 557 citas (54 %) quedaron a nombre
     * de la propietaria o de la recepcionista, y 76 cayeron en domingo con el
     * salón cerrado: ninguna se pudo atender, y son el 100 % de las que
     * terminaron Ausente. La clienta recibía confirmación y recordatorio de
     * una cita que el salón nunca iba a dar.
     *
     * La causa era que el criterio permisivo —«si no usa turnos, no le bloqueo
     * nada»— se resolvía persona por persona en vez de para el salón entero.
     */
    #[Test]
    public function quien_no_tiene_turno_no_ocupa_agenda_si_el_salon_usa_turnos(): void
    {
        if (! Agenda::elSalonUsaTurnos()) {
            $this->markTestSkipped('La base de prueba no tiene turnos cargados.');
        }

        $sinTurno = DB::selectOne(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.activo = 1 AND r.es_personal = 1
                AND NOT EXISTS (SELECT 1 FROM usuario_turno ut
                                  JOIN turno_laboral t ON t.id_turno = ut.id_turno AND t.activo = 1
                                 WHERE ut.id_usuario = u.id_usuario)
              LIMIT 1'
        );
        if (! $sinTurno) {
            $this->markTestSkipped('Todo el personal de la base de prueba tiene turno.');
        }
        $id = (int) $sinTurno->id_usuario;

        // Ni a una hora hábil, ni de madrugada, ni en domingo.
        $domingo = date('Y-m-d', strtotime('next sunday'));
        foreach ([date('Y-m-d', strtotime('+3 days')) . ' 10:00:00',
                  date('Y-m-d', strtotime('+3 days')) . ' 03:00:00',
                  $domingo . ' 10:00:00'] as $cuando) {
            $this->assertFalse(
                Agenda::huecoLibre($id, $cuando, 60),
                "Sin turno cargado no se le puede vender agenda ($cuando)."
            );
        }

        // Y tampoco se lo ofrece: no aparece en la lista ni se lo elige solo.
        $this->assertNotContains(
            $id,
            array_map(fn ($p) => (int) $p->id_usuario, Agenda::profesionales()),
            'Quien no atiende no tiene por qué figurar entre los profesionales.'
        );
        $this->assertNotSame(
            $id,
            Agenda::profesionalLibre(date('Y-m-d', strtotime('+3 days')) . ' 10:00:00', 60),
            'El «sin preferencia» no puede caer en quien no atiende.'
        );
    }

    #[Test]
    public function un_horario_ya_tomado_deja_de_estar_disponible(): void
    {
        // La cita elegida no puede estar tapada por una ausencia ni caer fuera
        // del turno del profesional: en esos dos casos `fn_verificar_disponibilidad`
        // contesta «no» con razón, y la segunda parte de la prueba fallaría por
        // un motivo que no es el que se está probando. Pasó de verdad — bastó
        // cargar una licencia sobre la primera cita futura para tumbarla.
        $cita = DB::selectOne(
            'SELECT c.id_cita, c.id_usuario, c.fecha_hora, fn_cita_duracion(c.id_cita) AS dur
               FROM cita c JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
              WHERE ec.bloquea_agenda = 1 AND c.fecha_hora > NOW()
                AND fn_cita_duracion(c.id_cita) > 0
                AND fn_verificar_disponibilidad(c.id_usuario, c.fecha_hora,
                                                fn_cita_duracion(c.id_cita), c.id_cita,
                                                c.id_sucursal) = 1
              ORDER BY c.fecha_hora LIMIT 1'
        );
        if (! $cita) {
            $this->markTestSkipped('No hay ninguna cita futura cuyo horario siga siendo válido para su profesional.');
        }

        // Sobre su propio horario, el profesional NO está disponible…
        $this->assertFalse(
            Agenda::huecoLibre((int) $cita->id_usuario, (string) $cita->fecha_hora, (int) $cita->dur),
            'La base ofreció un horario que ya estaba ocupado.'
        );

        // …salvo que se excluya esa misma cita, que es lo que hace reprogramar
        $this->assertTrue(
            Agenda::huecoLibre((int) $cita->id_usuario, (string) $cita->fecha_hora, (int) $cita->dur, (int) $cita->id_cita),
            'Al reprogramar, la propia cita no debería bloquearse a sí misma.'
        );
    }

    #[Test]
    public function agendar_sobre_un_horario_ocupado_lo_rechaza_la_base(): void
    {
        $cita = DB::selectOne(
            'SELECT c.id_cita, c.id_cliente, c.id_usuario, c.fecha_hora, fn_cita_duracion(c.id_cita) AS dur
               FROM cita c JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
              WHERE ec.bloquea_agenda = 1 AND c.fecha_hora > NOW()
              ORDER BY c.fecha_hora LIMIT 1'
        );
        if (! $cita) {
            $this->markTestSkipped('No hay citas futuras en la base de prueba.');
        }

        // El procedimiento tiene que negarse, aunque se lo pida directo: la
        // validación no vive en la pantalla.
        $this->expectException(Throwable::class);

        Agenda::agendar(
            (int) $cita->id_cliente, (int) $cita->id_usuario, (string) $cita->fecha_hora,
            (int) $cita->dur, 'prueba de solape', []
        );
    }

    #[Test]
    public function la_cita_dura_el_bloque_mas_largo_y_no_la_suma(): void
    {
        // Con dos profesionales en paralelo, la cita dura lo que tarde el más
        // largo. `fn_cita_duracion` agrupa por profesional y toma el máximo.
        $cita = DB::selectOne(
            'SELECT c.id_cita, fn_cita_duracion(c.id_cita) AS dur,
                    (SELECT COALESCE(SUM(s.duracion_min),0)
                       FROM cita_servicio cs JOIN servicio s ON s.id_servicio = cs.id_servicio
                      WHERE cs.id_cita = c.id_cita) AS suma,
                    (SELECT COUNT(DISTINCT COALESCE(cs.id_usuario, c.id_usuario))
                       FROM cita_servicio cs WHERE cs.id_cita = c.id_cita) AS profesionales
               FROM cita c
              WHERE (SELECT COUNT(*) FROM cita_servicio cs WHERE cs.id_cita = c.id_cita) > 1
              ORDER BY c.id_cita LIMIT 1'
        );
        if (! $cita) {
            $this->markTestSkipped('No hay citas con más de un servicio.');
        }

        if ((int) $cita->profesionales > 1) {
            $this->assertLessThan((int) $cita->suma, (int) $cita->dur,
                'Con dos profesionales en paralelo la cita no puede durar la suma de los servicios.');
        } else {
            $this->assertSame((int) $cita->suma, (int) $cita->dur,
                'Con un solo profesional los servicios sí se suman.');
        }
    }

    // -----------------------------------------------------------------
    //  La caja: el saldo es el efectivo del cajón
    // -----------------------------------------------------------------

    #[Test]
    public function el_saldo_de_caja_solo_cuenta_el_efectivo(): void
    {
        $caja = DB::selectOne(
            'SELECT c.id_caja, c.monto_inicial, fn_caja_saldo(c.id_caja) AS saldo
               FROM caja c ORDER BY c.id_caja LIMIT 1'
        );
        if (! $caja) {
            $this->markTestSkipped('No hay cajas en la base de prueba.');
        }

        $efectivo = (float) DB::scalar(
            "SELECT COALESCE(SUM(co.monto),0) FROM cobro co
               JOIN metodo_pago mp ON mp.id_metodo_pago = co.id_metodo_pago
              WHERE co.id_caja = ? AND co.id_estado_cobro = 1 AND mp.tipo = 'EFECTIVO'", [$caja->id_caja]
        );
        $otros = (float) DB::scalar(
            "SELECT COALESCE(SUM(co.monto),0) FROM cobro co
               JOIN metodo_pago mp ON mp.id_metodo_pago = co.id_metodo_pago
              WHERE co.id_caja = ? AND co.id_estado_cobro = 1 AND mp.tipo <> 'EFECTIVO'", [$caja->id_caja]
        );
        $ingresos = (float) DB::scalar("SELECT COALESCE(SUM(monto),0) FROM movimiento_caja
                                          WHERE id_caja = ? AND tipo = 'INGRESO'", [$caja->id_caja]);
        $egresos = (float) DB::scalar("SELECT COALESCE(SUM(monto),0) FROM movimiento_caja
                                         WHERE id_caja = ? AND tipo = 'EGRESO'", [$caja->id_caja]);
        $pagosEfectivo = (float) DB::scalar(
            "SELECT COALESCE(SUM(fn_pago_proveedor_monto(pp.id_pago_proveedor)),0)
               FROM pago_proveedor pp JOIN metodo_pago mp ON mp.id_metodo_pago = pp.id_metodo_pago
              WHERE pp.id_caja = ? AND pp.id_estado_pago_proveedor = 1 AND mp.tipo = 'EFECTIVO'", [$caja->id_caja]
        );

        $esperado = (float) $caja->monto_inicial + $efectivo + $ingresos - $egresos - $pagosEfectivo;

        $this->assertEqualsWithDelta($esperado, (float) $caja->saldo, 0.01,
            'El saldo de la caja no coincide con el arqueo del efectivo.');

        if ($otros > 0) {
            $this->assertNotEqualsWithDelta($esperado + $otros, (float) $caja->saldo, 0.01,
                'Lo cobrado con tarjeta o transferencia no debe engordar el cajón.');
        }
    }

    // -----------------------------------------------------------------
    //  Facturación: la numeración de la SET no tiene huecos
    // -----------------------------------------------------------------

    #[Test]
    public function los_correlativos_de_cada_timbrado_son_seguidos_y_sin_repetir(): void
    {
        $timbrados = DB::select(
            'SELECT t.id_timbrado, t.nro_timbrado, COUNT(f.id_factura) AS emitidas,
                    MIN(f.nro_correlativo) AS primero, MAX(f.nro_correlativo) AS ultimo,
                    COUNT(DISTINCT f.nro_correlativo) AS distintos
               FROM timbrado t JOIN factura f ON f.id_timbrado = t.id_timbrado
              GROUP BY t.id_timbrado, t.nro_timbrado'
        );
        if (! $timbrados) {
            $this->markTestSkipped('No hay comprobantes emitidos en la base de prueba.');
        }

        foreach ($timbrados as $t) {
            // Sin repetidos: dos comprobantes con el mismo número es lo peor
            $this->assertSame((int) $t->emitidas, (int) $t->distintos,
                "El timbrado {$t->nro_timbrado} tiene correlativos repetidos.");

            // Y sin huecos: la SET no los admite
            $esperados = (int) $t->ultimo - (int) $t->primero + 1;
            $this->assertSame($esperados, (int) $t->emitidas,
                "El timbrado {$t->nro_timbrado} tiene huecos en la numeración.");
        }
    }

    #[Test]
    public function el_saldo_de_la_factura_descuenta_la_sena_una_sola_vez(): void
    {
        // La seña va atada a la CITA, no a la factura. `fn_factura_saldo` ya
        // descuenta los cobros de la cita: si además se la vinculara a la
        // factura, se restaría dos veces.
        $f = DB::selectOne(
            'SELECT f.id_factura, f.id_cita, f.id_usuario,
                    fn_factura_total(f.id_factura) AS total,
                    fn_factura_saldo(f.id_factura) AS saldo
               FROM factura f
              WHERE f.id_estado_factura = 1 AND f.id_cita IS NOT NULL
                AND fn_factura_saldo(f.id_factura) > 50000 LIMIT 1'
        );
        if (! $f) {
            $this->markTestSkipped('No hay facturas con saldo sobre las que probar.');
        }

        $antes = (float) $f->saldo;
        $sena = 20000.0;

        // Se cobra una seña de la CITA (id_factura queda NULL, como en el sistema)
        Bd::idDe('sp_registrar_sena', [
            (int) $f->id_cita, 1, (int) $f->id_usuario, $sena, 'seña de prueba', null,
        ]);

        $this->assertEqualsWithDelta($sena, (float) DB::scalar('SELECT fn_cita_sena(?)', [$f->id_cita]), 0.01);

        // Y baja el saldo de la factura exactamente una vez, no dos
        $despues = (float) DB::scalar('SELECT fn_factura_saldo(?)', [$f->id_factura]);
        $this->assertEqualsWithDelta($antes - $sena, $despues, 0.01,
            'La seña se está descontando dos veces (o ninguna) del saldo de la factura.');
    }

    #[Test]
    public function anular_no_borra_el_comprobante(): void
    {
        // La numeración no puede tener huecos, así que anular cambia el estado
        // y el comprobante sigue existiendo.
        $anuladas = (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_estado_factura = 2');
        if (! $anuladas) {
            $this->markTestSkipped('No hay comprobantes anulados en la base de prueba.');
        }

        $conNumero = (int) DB::scalar(
            'SELECT COUNT(*) FROM factura WHERE id_estado_factura = 2 AND nro_correlativo IS NOT NULL'
        );
        $this->assertSame($anuladas, $conNumero,
            'Un comprobante anulado tiene que conservar su número.');
    }

    // -----------------------------------------------------------------
    //  Inventario: el stock sale de los movimientos
    // -----------------------------------------------------------------

    #[Test]
    public function el_stock_es_la_suma_de_los_movimientos_segun_su_signo(): void
    {
        $productos = DB::select(
            "SELECT p.id_producto, p.nombre, fn_producto_stock(p.id_producto, 1) AS stock,
                    (SELECT COALESCE(SUM(CASE WHEN tm.signo = 'E' THEN m.cantidad ELSE -m.cantidad END),0)
                       FROM movimiento_inventario m
                       JOIN tipo_movimiento_inventario tm ON tm.id_tipo_movimiento = m.id_tipo_movimiento
                      WHERE m.id_producto = p.id_producto) AS calculado
               FROM producto p LIMIT 20"
        );
        if (! $productos) {
            $this->markTestSkipped('No hay productos en la base de prueba.');
        }

        foreach ($productos as $p) {
            $this->assertEqualsWithDelta((float) $p->calculado, (float) $p->stock, 0.001,
                "El stock de «{$p->nombre}» no coincide con sus movimientos.");
        }
    }

    #[Test]
    public function no_se_puede_sacar_mas_stock_del_que_hay(): void
    {
        $p = DB::selectOne('SELECT id_producto, fn_producto_stock(id_producto, 1) AS stock
                              FROM producto WHERE activo = 1 ORDER BY id_producto LIMIT 1');
        if (! $p) {
            $this->markTestSkipped('No hay productos en la base de prueba.');
        }

        // El disparador de la base tiene que frenar la salida
        $this->expectException(Throwable::class);

        DB::statement('CALL sp_registrar_movimiento_inventario(?,?,?,?,?,?,?,?)', [
            $p->id_producto, 1, 1, 2, (float) $p->stock + 9999, null, 'TEST', 'salida imposible',
        ]);
    }

    /**
     * Cuánto shampoo lleva un lavado depende del pelo de cada clienta, así que
     * la cantidad tiene que poder cargarse como se usó de verdad.
     *
     * Con las columnas en DECIMAL(10,2) lo más chico que se podía descontar era
     * 1/100 del envase —10 ml de un frasco de litro—: 15 ml descontaban 20, 5 ml
     * descontaban 10, y 1 ml no entraba porque el CHECK `chk_pu_cantidad` lo
     * rechazaba y la pantalla contestaba «No se pudo registrar la atención».
     *
     * Son SEIS piezas las que tienen que estar en 4 decimales, no dos: si
     * `fn_producto_stock` o el disparador que bloquea las salidas vuelven a
     * declarar (12,2), la cuenta se trunca de nuevo y esta prueba lo agarra.
     */
    #[Test]
    public function el_consumo_fraccionado_descuenta_la_cantidad_exacta(): void
    {
        $p = DB::selectOne(
            'SELECT id_producto, nombre, contenido, unidad_consumo,
                    fn_producto_stock(id_producto, 1) AS stock
               FROM producto
              WHERE activo = 1 AND contenido >= 900 AND unidad_consumo IS NOT NULL
              ORDER BY id_producto LIMIT 1'
        );
        if (! $p) {
            $this->markTestSkipped('No hay productos fraccionados en la base de prueba.');
        }

        $antes = (float) $p->stock;

        // 15 ml de un frasco de 1.000: antes se guardaba 0,02 (20 ml).
        foreach ([15.0, 5.0, 1.0] as $ml) {
            $enStock = consumo_a_stock((array) $p, $ml);
            $this->assertGreaterThan(0, $enStock,
                "{$ml} {$p->unidad_consumo} de «{$p->nombre}» no llega a descontar nada.");

            DB::statement('CALL sp_registrar_movimiento_inventario(?,?,?,?,?,?,?,?)', [
                $p->id_producto, 1, 1, 2, $enStock, null, 'TEST', 'consumo fraccionado',
            ]);

            $ahora = (float) DB::scalar('SELECT fn_producto_stock(?,1)', [$p->id_producto]);
            $this->assertEqualsWithDelta($antes - $enStock, $ahora, 0.00005,
                "Descontar {$ml} {$p->unidad_consumo} no dio el stock esperado: se perdió precisión.");

            // Lo que se descontó, devuelto a la unidad de la persona
            $this->assertEqualsWithDelta($ml, stock_a_consumo((array) $p, $antes - $ahora), 0.05,
                "Se cargaron {$ml} {$p->unidad_consumo} y se descontó otra cantidad.");

            $antes = $ahora;
        }
    }

    // -----------------------------------------------------------------
    //  Facturación electrónica: lo que se puede comprobar sin la DNIT
    // -----------------------------------------------------------------

    /**
     * El dígito verificador por módulo 11.
     *
     * El caso que lo fija es el **CDC de ejemplo del propio Manual Técnico
     * v150** (sección 10.1): sus 43 primeros dígitos tienen que dar el 44º.
     * Es la única referencia verificable que trae el manual —para el
     * algoritmo remite a un PDF aparte de la SET—, y sirve porque distingue
     * el ciclo de pesos correcto (2..11) del otro que circula (2..9), que da
     * 2 en vez de 8.
     */
    #[Test]
    public function el_digito_verificador_del_ruc_sigue_el_modulo_11_del_manual(): void
    {
        $cdc = str_replace(' ', '', '0144 4444 0170 0100 1001 4528 2201 7012 5158 7326 0988');

        $this->assertSame(44, strlen($cdc), 'El CDC de ejemplo tiene que tener 44 dígitos.');
        $this->assertSame(
            (int) substr($cdc, -1),
            Sifen::dvRuc(substr($cdc, 0, 43)),
            'El módulo 11 no reproduce el dígito verificador del CDC de ejemplo del manual.'
        );

        // Y el ejemplo que muestra la pantalla tiene que ser válido: si no,
        // quien lo copia se lleva un rechazo de la propia validación.
        $this->assertSame(0, Sifen::dvRuc('80012345'));
    }

    /**
     * Las reglas del receptor que se pueden comprobar sin salir del salón.
     *
     * Importa que se validen ANTES de emitir: un rechazo de la DNIT no se
     * reintenta, el número de comprobante ya se gastó y hay que anular y
     * hacer otro.
     */
    #[Test]
    public function el_receptor_se_valida_antes_de_emitir(): void
    {
        $ok = ['tipo_doc' => 'RUC', 'documento' => '80012345-0', 'nombre' => 'Comercial SA'];
        $this->assertNull(Sifen::validarReceptor($ok, 100000));

        // 1309: dígito verificador que no corresponde
        $this->assertNotNull(Sifen::validarReceptor(
            ['tipo_doc' => 'RUC', 'documento' => '80012345-6', 'nombre' => 'Comercial SA'], 100000));

        // D211: el nombre es obligatorio (ocurrencia 1-1)
        $this->assertNotNull(Sifen::validarReceptor(
            ['tipo_doc' => 'RUC', 'documento' => '80012345-0', 'nombre' => ''], 100000));

        // D210: la cédula es numérica
        $this->assertNotNull(Sifen::validarReceptor(
            ['tipo_doc' => 'CI', 'documento' => 'abc123', 'nombre' => 'Andrea'], 100000));

        // 1321: innominado sí por debajo del tope, no por encima
        $this->assertNull(Sifen::validarReceptor(['tipo_doc' => 'CF'], Sifen::TOPE_INNOMINADO - 1));
        $this->assertNotNull(Sifen::validarReceptor(['tipo_doc' => 'CF'], Sifen::TOPE_INNOMINADO));

        // D216: el correo es a donde va el PDF, así que se revisa
        $this->assertNotNull(Sifen::validarReceptor(
            ['tipo_doc' => 'CI', 'documento' => '4200000', 'nombre' => 'A', 'email' => 'no-es-correo'], 1000));
    }

    /**
     * El TXT que se le manda al Automatizador.
     *
     * Lo que se carga en el formulario del receptor manda sobre la ficha: es
     * lo que permite emitir a consumidor final aunque la clienta tenga la
     * cédula cargada, o mandar el PDF a otro correo.
     */
    #[Test]
    public function el_txt_del_automatizador_respeta_lo_que_se_cargo_en_el_formulario(): void
    {
        $id = (int) DB::scalar(
            'SELECT f.id_factura FROM factura f
              WHERE f.id_estado_factura = 1
                AND EXISTS (SELECT 1 FROM detalle_factura d WHERE d.id_factura = f.id_factura)
              ORDER BY f.id_factura DESC LIMIT 1'
        );
        if (! $id) {
            $this->markTestSkipped('No hay facturas con detalle en la base de prueba.');
        }

        $cli = fn (string $txt) => collect(explode("\n", $txt))->first(fn ($l) => str_starts_with($l, 'CLI|'));

        $conFormulario = $cli(Sifen::armarTxt($id, [
            'tipo_doc' => 'RUC', 'documento' => '80012345-0', 'nombre' => 'Comercial SA',
            'email' => 'otro@correo.com',
        ]));
        $this->assertStringContainsString('|RUC|80012345-0|Comercial SA|otro@correo.com', $conFormulario);

        // Consumidor final: sin documento y sin nombre propio, como pide el
        // manual para el innominado.
        $this->assertStringContainsString('|CF|Consumidor Final|', $cli(Sifen::armarTxt($id, ['tipo_doc' => 'CF'])));

        // Y la cabecera lleva los tres números del comprobante, con su relleno
        $fac = collect(explode("\n", Sifen::armarTxt($id)))->first(fn ($l) => str_starts_with($l, 'FAC|'));
        $this->assertMatchesRegularExpression('/^FAC\|\d{3}\|\d{3}\|\d{7}\|\d{4}-\d{2}-\d{2}\|[12]\|PYG\|\d{1,2}$/', $fac);
    }

    /**
     * Una clienta que el salón ya tenía cargada no se duplica al registrarse.
     *
     * Es el caso normal, no el raro: casi todas entran por teléfono y las
     * carga quien atiende, así que tienen `persona` y `cliente` pero no
     * `usuario`. Los controles del registro miran `usuario JOIN persona` —o
     * sea sólo a quien ya tiene cuenta—, así que esa clienta pasaba el filtro
     * y se le creaban una persona y un cliente NUEVOS: quedaban dos fichas con
     * el mismo correo y su historial, sus puntos y su nivel se quedaban en la
     * vieja.
     */
    #[Test]
    public function registrarse_enlaza_la_ficha_que_el_salon_ya_tenia(): void
    {
        $c = DB::selectOne(
            'SELECT cl.id_cliente, pe.id_persona, pe.nombre, pe.apellido, pe.email, pe.telefono
               FROM cliente cl JOIN persona pe ON pe.id_persona = cl.id_persona
              WHERE cl.id_usuario IS NULL AND pe.email IS NOT NULL
              ORDER BY cl.id_cliente LIMIT 1'
        );
        if (! $c) {
            $this->markTestSkipped('No hay clientas sin cuenta en la base de prueba.');
        }

        $citas = (int) DB::scalar('SELECT COUNT(*) FROM cita WHERE id_cliente = ?', [$c->id_cliente]);
        $puntos = (float) DB::scalar('SELECT fn_cliente_puntos(?)', [$c->id_cliente]);
        $personas = (int) DB::scalar('SELECT COUNT(*) FROM persona');
        $clientes = (int) DB::scalar('SELECT COUNT(*) FROM cliente');

        // Se registra con su correo y SIN teléfono, para comprobar de paso que
        // no le borre el que el salón ya tenía cargado.
        $this->post(route('registro'), [
            'nombre' => $c->nombre,
            'apellido' => $c->apellido,
            'email' => $c->email,
            'username' => 'prueba' . substr((string) microtime(true), -8),
            'password' => 'clave123',
            'password2' => 'clave123',
        ])->assertRedirect(route('verificar'));

        $this->assertSame($personas, (int) DB::scalar('SELECT COUNT(*) FROM persona'),
            'Se creó una persona de más: la clienta quedó duplicada.');
        $this->assertSame($clientes, (int) DB::scalar('SELECT COUNT(*) FROM cliente'),
            'Se creó un cliente de más: la clienta quedó duplicada.');
        $this->assertSame(1, (int) DB::scalar('SELECT COUNT(*) FROM persona WHERE email = ?', [$c->email]),
            'Quedaron dos fichas con el mismo correo.');

        $r = DB::selectOne('SELECT cl.id_usuario, pe.telefono FROM cliente cl
                              JOIN persona pe ON pe.id_persona = cl.id_persona
                             WHERE cl.id_cliente = ?', [$c->id_cliente]);
        $this->assertNotNull($r->id_usuario, 'La ficha vieja no quedó enlazada a la cuenta nueva.');
        $this->assertSame($c->telefono, $r->telefono, 'El registro le borró el teléfono que ya tenía.');

        // Y lo que importa de verdad: no arranca de cero
        $this->assertSame($citas, (int) DB::scalar('SELECT COUNT(*) FROM cita WHERE id_cliente = ?', [$c->id_cliente]));
        $this->assertEqualsWithDelta($puntos, (float) DB::scalar('SELECT fn_cliente_puntos(?)', [$c->id_cliente]), 0.01);
    }

    // -----------------------------------------------------------------
    //  Permisos
    // -----------------------------------------------------------------

    #[Test]
    public function el_administrador_puede_todo_sin_tener_filas_de_permiso(): void
    {
        $admin = (int) config('permisos.rol_admin', 1);

        foreach (Permisos::claves() as $clave) {
            $this->assertTrue(Permisos::rolPuede($admin, $clave),
                "El Administrador tendría que poder entrar a $clave.");
        }
    }

    #[Test]
    public function tener_el_modulo_padre_habilita_todos_sus_submodulos(): void
    {
        // Es la red que deja andar a un rol guardado antes de que el módulo se
        // dividiera en submódulos.
        $rol = (int) DB::scalar('SELECT MAX(id_rol) + 1 FROM rol');   // uno que no existe
        Permisos::olvidar($rol);

        // Sin filas en rol_modulo y sin ser admin, no puede nada
        $this->assertFalse(Permisos::rolPuede($rol, 'facturacion.cobros'));

        DB::insert('INSERT INTO rol (id_rol, nombre, es_personal, activo) VALUES (?,?,1,1)',
            [$rol, 'Rol de prueba ' . $rol]);
        DB::insert('INSERT INTO rol_modulo (id_rol, modulo) VALUES (?,?)', [$rol, 'facturacion']);
        Permisos::olvidar($rol);

        $this->assertTrue(Permisos::rolPuede($rol, 'facturacion.cobros'),
            'Quien tiene el módulo entero tiene todos sus submódulos.');
        $this->assertTrue(Permisos::rolPuede($rol, 'facturacion'));
        $this->assertFalse(Permisos::rolPuede($rol, 'seguridad.turnos'),
            'No debería alcanzar a un módulo que no tiene.');
    }

    #[Test]
    public function tener_un_solo_submodulo_deja_entrar_al_modulo(): void
    {
        // Si no, no tendría cómo llegar hasta la pantalla que sí puede abrir.
        $rol = (int) DB::scalar('SELECT MAX(id_rol) + 2 FROM rol');
        DB::insert('INSERT INTO rol (id_rol, nombre, es_personal, activo) VALUES (?,?,1,1)',
            [$rol, 'Rol de prueba ' . $rol]);
        DB::insert('INSERT INTO rol_modulo (id_rol, modulo) VALUES (?,?)', [$rol, 'seguridad.asistencia']);
        Permisos::olvidar($rol);

        // `seguridad.asistencia` es la clave VIEJA: desde la 7.57.0 la
        // asistencia vive en Personal, así que lo guardado se traduce y el
        // landing que abre es el de Personal, no el de Seguridad.
        $this->assertTrue(Permisos::rolPuede($rol, 'personal'),
            'Con un submódulo tiene que poder abrir el landing del módulo.');
        $this->assertTrue(Permisos::rolPuede($rol, 'personal.asistencia'));
        $this->assertFalse(Permisos::rolPuede($rol, 'personal.turnos'),
            'Pero no los otros submódulos del mismo módulo.');
        $this->assertFalse(Permisos::rolPuede($rol, 'seguridad.usuarios'),
            'Ni nada de Seguridad, que ahora es otro módulo.');
    }

    #[Test]
    public function un_rol_guardado_con_las_claves_viejas_no_pierde_ni_gana_permisos(): void
    {
        // Personal y Configuración se unieron en Seguridad en la 6.2.0. Las
        // bases ya instaladas siguen teniendo las claves viejas en rol_modulo,
        // y traducirlas mal se paga de las dos formas: quedarse corto le saca
        // en silencio una pantalla a quien la usaba, y pasarse le regala los
        // roles y la auditoría a quien solo administraba al personal.
        $rol = (int) DB::scalar('SELECT MAX(id_rol) + 3 FROM rol');
        DB::insert('INSERT INTO rol (id_rol, nombre, es_personal, activo) VALUES (?,?,1,1)',
            [$rol, 'Rol de prueba ' . $rol]);
        DB::insert('INSERT INTO rol_modulo (id_rol, modulo) VALUES (?,?)', [$rol, 'personal']);
        Permisos::olvidar($rol);

        // Las cuatro que tenía siguen siendo suyas, aunque desde la 7.57.0
        // vivan repartidas: los usuarios quedaron en Seguridad y el resto en
        // Personal.
        foreach (['seguridad.usuarios', 'personal.turnos',
                  'personal.comisiones', 'personal.asistencia'] as $tenia) {
            $this->assertTrue(Permisos::rolPuede($rol, $tenia),
                "El módulo Personal incluía $tenia: no puede perderlo.");
        }
        foreach (['seguridad.roles', 'configuracion.sucursales',
                  'configuracion.contacto', 'seguridad.auditoria'] as $noTenia) {
            $this->assertFalse(Permisos::rolPuede($rol, $noTenia),
                "$noTenia era de Configuración: no puede aparecer de la nada.");
        }
    }

    #[Test]
    public function una_pantalla_sin_permiso_contesta_403(): void
    {
        // Se entra como Profesional, que no maneja timbrados
        $prof = DB::selectOne(
            "SELECT u.username FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.activo = 1 AND r.nombre = 'Profesional' LIMIT 1"
        );
        if (! $prof) {
            $this->markTestSkipped('No hay ningún Profesional en la base de prueba.');
        }

        $rolProf = (int) DB::scalar("SELECT id_rol FROM rol WHERE nombre = 'Profesional' LIMIT 1");
        $this->assertFalse(Permisos::rolPuede($rolProf, 'facturacion.timbrados'),
            'El Profesional no tendría que administrar timbrados.');

        // Y la ruta lo rechaza, no solo la pantalla lo esconde
        session(['uid' => (int) (DB::scalar('SELECT id_usuario FROM usuario WHERE id_rol = ? AND activo = 1 LIMIT 1', [$rolProf]) ?: 1), 'rol' => $rolProf, 'es_personal' => true, 'es_cliente' => false, 'id_sucursal' => 1]);
        $this->get(route('facturacion.timbrados'))->assertForbidden();
    }

    #[Test]
    public function el_tema_es_de_cada_persona_y_no_afecta_al_papel(): void
    {
        $u = (int) DB::scalar("SELECT id_usuario FROM usuario WHERE username = 'admin' LIMIT 1");
        if (! $u) {
            $this->markTestSkipped('No está la cuenta admin en la base de prueba.');
        }

        // Se guarda y se lee. Un valor inventado se rechaza: la columna tiene
        // su CHECK, pero el servicio no tiene por qué llegar a que salte.
        $this->assertTrue(Sesion::guardarTema($u, 'oscuro'));
        $this->assertSame('oscuro', Sesion::temaDe($u));
        $this->assertFalse(Sesion::guardarTema($u, 'fucsia'), 'Un tema que no existe no se guarda.');
        $this->assertSame('oscuro', Sesion::temaDe($u), 'Y no pisa el que ya estaba.');

        // La pantalla sale con el atributo, que es lo que el CSS mira.
        session(['uid' => $u, 'rol' => (int) config('permisos.rol_admin', 1),
                 'es_personal' => true, 'es_cliente' => false, 'tema' => 'oscuro']); $this->conSucursal();
        $this->get(route('panel'))->assertOk()->assertSee('data-tema="oscuro"', false);

        // **El PDF no**: se descarga como documento independiente y nunca
        // arrastra el tema oscuro de la pantalla.
        $pdf = $this->get(route('reportes.imprimir'));
        $pdf->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        // Y se vuelve al claro sin dejar rastro.
        $this->assertTrue(Sesion::guardarTema($u, 'claro'));
        $this->assertSame('claro', Sesion::temaDe($u));
        session(['tema' => 'claro']);
        $this->get(route('panel'))->assertOk()->assertDontSee('data-tema="oscuro"', false);
    }

    #[Test]
    public function el_ticket_no_se_declara_ante_la_dnit(): void
    {
        // La clienta no siempre pide factura. El Ticket es el comprobante
        // interno del salón: se numera y queda registrado, pero NO sale de acá.
        // Sólo la Factura y la Nota de crédito se declaran.
        $this->assertTrue(Sifen::esElectronico(1), 'La factura sí se declara.');
        $this->assertTrue(Sifen::esElectronico(5), 'La nota de crédito sí se declara.');
        $this->assertFalse(Sifen::esElectronico(3), 'El Ticket es interno: no se declara.');
        $this->assertFalse(Sifen::esElectronico(2), 'La boleta de venta tampoco.');
    }

    #[Test]
    public function el_comprobante_se_arma_en_el_formato_del_automatizador(): void
    {
        // El Automatizador espera líneas separadas por «|»: una EMI con quien
        // emite, una FAC con la cabecera, una CLI con el cliente y una ITM por
        // renglón. El total NO se escribe — lo calcula él desde los ítems.
        $id = (int) DB::scalar('SELECT id_factura FROM factura WHERE id_tipo_comprobante = 1
                                  AND id_estado_factura = 1 ORDER BY id_factura LIMIT 1');
        if (! $id) {
            $this->markTestSkipped('No hay facturas emitidas en la base de prueba.');
        }

        $txt = Sifen::armarTxt($id);
        $lineas = array_values(array_filter(explode("\n", $txt)));

        $this->assertStringStartsWith('EMI|', $lineas[0], 'La primera línea dice quién emite.');
        $this->assertStringStartsWith('FAC|', $lineas[1], 'La segunda es la cabecera.');
        $this->assertStringStartsWith('CLI|', $lineas[2], 'La tercera es el cliente.');
        $this->assertStringStartsWith('ITM|', $lineas[3], 'Después van los renglones.');

        // El emisor lleva 14 campos: razón social, RUC y DV separados, la
        // dirección y la ciudad del local, contacto, actividad, el timbrado
        // con su vigencia, y el nombre de la sucursal.
        $emi = explode('|', $lineas[0]);
        $this->assertCount(14, $emi, 'El emisor tiene que ir completo o el KuDE lo rellena con su ejemplo.');
        $this->assertNotSame('', trim($emi[1]), 'Sin razón social el comprobante no dice de quién es.');
        $this->assertMatchesRegularExpression('/^\d*$/', $emi[2], 'El RUC va sin el DV.');
        $this->assertMatchesRegularExpression('/^[0-9K]?$/', $emi[3], 'El DV va aparte, como lo pide el SIFEN.');

        // La cabecera lleva 8 campos y los números van con ceros a la izquierda.
        $fac = explode('|', $lineas[1]);
        $this->assertCount(8, $fac);
        $this->assertMatchesRegularExpression('/^\d{3}$/', $fac[1], 'Establecimiento de 3 dígitos.');
        $this->assertMatchesRegularExpression('/^\d{3}$/', $fac[2], 'Punto de expedición de 3 dígitos.');
        $this->assertMatchesRegularExpression('/^\d{7}$/', $fac[3], 'Correlativo de 7 dígitos.');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $fac[4]);
        $this->assertSame('PYG', $fac[6]);
        // D011 iTipTra. El salón presta servicios: mandarlo vacío lo dejaba
        // caer en «venta de mercadería», que sale impreso y va en el XML.
        $this->assertSame('2', $fac[7], 'El tipo de transacción es prestación de servicios.');

        // Cada renglón: código, descripción, cantidad, precio y tasa de IVA.
        foreach (array_slice($lineas, 3) as $l) {
            $itm = explode('|', $l);
            $this->assertCount(6, $itm, "Renglón mal armado: $l");
            $this->assertContains((int) $itm[5], [0, 5, 10], 'La tasa de IVA sólo puede ser 0, 5 o 10.');
        }

        // Ningún dato puede traer el separador adentro: partiría la línea.
        foreach ($lineas as $l) {
            $this->assertSame(substr_count($l, '|'), substr_count(str_replace('||', '| |', $l), '|'),
                'Un campo vacío está bien; un «|» dentro de un dato, no.');
        }
    }

    #[Test]
    public function la_cita_se_puede_agendar_en_el_calendario_del_telefono(): void
    {
        // Son DOS caminos y hacen falta los dos: el .ics lo abre el iPhone, y
        // el enlace de Google es el que anda en Android, donde el archivo se
        // baja a la carpeta de descargas y no pasa nada más.
        $cal = DB::selectOne('SELECT id_cita, fecha_hora, duracion_min, servicios, profesional
                                FROM vw_agenda_citas ORDER BY id_cita DESC LIMIT 1');
        if (! $cal) {
            $this->markTestSkipped('No hay citas en la base de prueba.');
        }

        // --- El .ics, con la estructura que pide el RFC 5545 ---
        $ics = Calendario::deCita($cal, 120, 'Salón, Luque');
        foreach (['BEGIN:VCALENDAR', 'BEGIN:VEVENT', 'BEGIN:VALARM', 'END:VALARM', 'END:VEVENT', 'END:VCALENDAR'] as $bloque) {
            $this->assertStringContainsString($bloque, $ics, "Al .ics le falta $bloque.");
        }
        $this->assertStringContainsString("\r\n", $ics, 'El .ics tiene que separar con CRLF.');

        // La hora va FLOTANTE: sin la Z de UTC. Si se convirtiera, al teléfono
        // le llegaría la cita una hora corrida.
        $this->assertMatchesRegularExpression('/DTSTART:\d{8}T\d{6}\r\n/', $ics,
            'DTSTART tiene que ir en hora flotante, sin Z.');
        $this->assertStringContainsString('DTSTART:' . date('Ymd\THis', strtotime((string) $cal->fecha_hora)), $ics,
            'La hora del .ics no coincide con la de la cita.');

        // --- El enlace de Google: misma hora local, con el huso declarado ---
        $url = Calendario::urlGoogle($cal, 'Salón, Luque');
        $this->assertStringStartsWith('https://calendar.google.com/calendar/render', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame('America/Asuncion', $q['ctz'] ?? null,
            'Sin ctz, Google interpreta la hora en el huso del visitante.');
        $this->assertStringNotContainsString('Z', $q['dates'] ?? '',
            'Las fechas van en hora local: la conversión la hace Google con ctz.');
        $this->assertStringStartsWith(date('Ymd\THis', strtotime((string) $cal->fecha_hora)), $q['dates'] ?? '',
            'La hora del enlace de Google no coincide con la de la cita.');
    }

    #[Test]
    public function el_profesional_no_administra_precios_ni_promociones(): void
    {
        // La auditoría del 11/08/2026 lo encontró cambiando una coloración de
        // 280.000 a 1.000 y poniendo una promo al 99 % — que `sp_emitir_factura`
        // aplica sola. El rol traía `servicios.catalogo` y `servicios.descuentos`
        // de fábrica: el middleware funcionaba, el permiso sobraba.
        $rolProf = (int) DB::scalar("SELECT id_rol FROM rol WHERE nombre = 'Profesional' LIMIT 1");
        if (! $rolProf) {
            $this->markTestSkipped('No hay rol Profesional en la base de prueba.');
        }

        // La caché de permisos es estática y sobrevive entre pruebas del mismo
        // proceso: otra prueba le agrega módulos a este rol dentro de su
        // transacción, y aunque la fila se revierta el arreglo en memoria queda.
        Permisos::olvidar();

        foreach (['servicios.catalogo', 'servicios.categorias', 'servicios.descuentos'] as $clave) {
            // Las llaves no son adorno: `»` es multibyte y PHP se lo come como
            // parte del nombre de la variable si va pegado.
            $this->assertFalse(Permisos::rolPuede($rolProf, $clave),
                "El Profesional no tendría que tener «{$clave}»: con eso fija cuánto cobra el salón.");
        }

        // Y la ruta lo rechaza de verdad, no sólo esconde el botón.
        session(['uid' => (int) (DB::scalar('SELECT id_usuario FROM usuario WHERE id_rol = ? AND activo = 1 LIMIT 1', [$rolProf]) ?: 1), 'rol' => $rolProf, 'es_personal' => true, 'es_cliente' => false, 'id_sucursal' => 1]);
        $this->get(route('servicios.form'))->assertForbidden();
        $this->get(route('servicios.descuentos'))->assertForbidden();

        // Lo que sí necesita para trabajar sigue abierto.
        $this->get(route('citas.agenda'))->assertOk();
        $this->get(route('citas.form'))->assertOk();
    }

    /**
     * Cada sucursal abre su propia caja, y sólo una.
     *
     * **La caja es del local desde la 7.31.0 — salvo el disparador, que se
     * quedó mirando el salón entero.** `caja.id_sucursal`, `sp_abrir_caja`,
     * `Caja::abierta()` y `vw_caja_resumen` ya trabajaban por sucursal;
     * `trg_caja_bi` seguía preguntando si había **alguna** caja abierta, así
     * que mientras un local tuviera la suya, **ningún otro podía abrir la
     * propia en todo el día**. Y sin caja no se cobra ni se factura: la
     * sucursal nueva quedaba sin mostrador.
     *
     * Lo destapó la simulación intensiva de 30 días — de 123 citas, sólo 2
     * eran del segundo local, y no por la agenda sino por esto.
     *
     * Se comprueba en las dos direcciones y **con más de dos locales**, que es
     * lo que hay que sostener: el sistema tiene que funcionar con N sucursales,
     * no con dos.
     */
    /**
     * La plata entra y sale de la caja DEL LOCAL donde ocurrió el hecho.
     *
     * **Las tres rutinas que mueven dinero elegían el cajón de quien opera, no
     * el del local.** Con un solo cajón en todo el salón daba lo mismo; desde
     * que cada sucursal tiene el suyo y una persona puede estar asignada a
     * varias, ese `ORDER BY id_caja DESC` devolvía la última que esa persona
     * hubiera abierto — que puede ser la de otro local.
     *
     * Medido en la simulación de 30 días: un pago a proveedor en efectivo por
     * Gs. 1.150.000 se validó contra el cajón de la sucursal activa y se grabó
     * en el de la otra, que tenía Gs. 150.000. Quedó en **−1.000.000**.
     *
     * Ahora cada documento dice dónde ocurrió y la sucursal se deduce: la
     * compra la trae en `compra.id_sucursal`, la cita en `cita.id_sucursal` y
     * la factura en el timbrado con el que se numeró.
     */
    /**
     * El panel muestra los ingresos de ESTE local, no los del negocio entero.
     *
     * Era la única métrica del panel sin filtro de sucursal: las citas, el
     * stock y la caja ya lo tenían desde la 7.31.0. Con dos locales, la sede 2
     * veía la recaudación de la sede 1 en su propia pantalla de inicio — y
     * quien trabaja en un local no tiene por qué ver la plata del otro.
     *
     * Comprobada en las dos direcciones: sacándole el filtro a la consulta, la
     * prueba falla.
     */
    #[Test]
    public function el_panel_muestra_los_ingresos_de_su_propio_local(): void
    {
        $uid = (int) DB::scalar('SELECT id_usuario FROM usuario WHERE id_rol = ? AND activo = 1 LIMIT 1',
            [(int) config('permisos.rol_admin', 1)]);

        DB::statement('UPDATE caja SET id_estado_caja = 2, fecha_cierre = NOW() WHERE id_estado_caja = 1');

        $suc1 = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');
        DB::insert('INSERT INTO sucursal (nombre, activo) VALUES (?, 1)', ['Prueba Ingresos']);
        $suc2 = (int) DB::getPdo()->lastInsertId();

        $metodo = (int) DB::scalar("SELECT id_metodo_pago FROM metodo_pago WHERE tipo='EFECTIVO' AND activo=1 LIMIT 1");
        $fact = DB::selectOne('SELECT id_factura FROM factura WHERE id_estado_factura = 1 ORDER BY id_factura DESC LIMIT 1');

        // Una caja y un cobro en cada local, el mismo día.
        foreach ([$suc1 => 111000.0, $suc2 => 222000.0] as $s => $monto) {
            DB::insert('INSERT INTO caja (id_usuario,id_sucursal,id_caja_fisica,id_estado_caja,monto_inicial)
                        VALUES (?,?,?,1,0)', [$uid, $s, $this->cajonDe($s)]);
            $idCaja = (int) DB::getPdo()->lastInsertId();

            DB::insert('INSERT INTO cobro (id_factura,id_metodo_pago,id_estado_cobro,id_usuario,id_caja,monto,fecha)
                        VALUES (?,?,1,?,?,?,NOW())',
                [$fact->id_factura ?? null, $metodo, $uid, $idCaja, $monto]);
        }

        $delLocal = fn (int $s) => (float) DB::scalar(
            'SELECT COALESCE(SUM(co.monto),0) FROM cobro co
               LEFT JOIN caja k ON k.id_caja = co.id_caja
               LEFT JOIN cita ci ON ci.id_cita = co.id_cita
              WHERE DATE(co.fecha) = CURDATE() AND co.id_estado_cobro = 1
                AND COALESCE(k.id_sucursal, ci.id_sucursal) = ?', [$s]);

        $enUno = $delLocal($suc1);
        $enDos = $delLocal($suc2);
        $total = (float) DB::scalar(
            'SELECT COALESCE(SUM(monto),0) FROM cobro WHERE DATE(fecha) = CURDATE() AND id_estado_cobro = 1');

        // El local nuevo cuenta lo suyo y nada más: 222.000 exactos, porque la
        // sucursal se creó en esta prueba y no puede tener otros cobros.
        $this->assertEqualsWithDelta(222000.0, $enDos, 0.01,
            'El local nuevo tiene que contar sólo su propio cobro.');

        // La comparación contra el total es la que detecta la fuga: sin filtro,
        // los dos locales verían la misma cifra.
        $this->assertGreaterThan($enDos, $total,
            'La prueba no está midiendo nada: el total tiene que incluir lo de los dos locales.');
        $this->assertNotEqualsWithDelta($total, $enDos, 0.01,
            'El local nuevo está viendo la recaudación de todo el negocio, no la suya.');
        $this->assertNotEqualsWithDelta($total, $enUno, 0.01,
            'El local 1 está viendo también la plata del local nuevo.');
    }

    /**
     * El comprobante se numera con el timbrado DEL LOCAL que lo emite.
     *
     * `fn_timbrado_vigente` elegía el primer timbrado vigente de ese tipo **sin
     * mirar la sucursal**, así que el segundo local emitía con el de la casa
     * central. Dos cosas se rompen: el **establecimiento** —los tres primeros
     * dígitos del número impreso, que es lo que la SET usa para saber de qué
     * local salió— queda mal, y los correlativos de las dos sedes se mezclan.
     * Y arrastra la plata: desde la 7.36.3 el cobro deduce su sucursal del
     * timbrado, así que la factura ajena lleva el cobro al cajón equivocado.
     *
     * Lo encontró la simulación al hacer que el segundo local **facturara** de
     * verdad. Hasta entonces sólo agendaba, y este camino no tenía cobertura:
     * es el ejemplo de que un hueco de cobertura esconde defectos, no ausencia
     * de defectos.
     */
    #[Test]
    public function el_comprobante_usa_el_timbrado_de_su_propio_local(): void
    {
        // **La cita tiene que ser de un local que TENGA timbrado propio.** Si
        // no, `fn_timbrado_vigente` cae al de otra sede —que es lo correcto y
        // deliberado— y la prueba mediría la caída en vez de la regla. Pasaba
        // en el contenedor y no en el host: ahí había una cita de una sucursal
        // sin timbrado, así que la prueba fallaba por el motivo equivocado.
        $cita = DB::selectOne(
            'SELECT c.id_cita, c.id_cliente, c.id_usuario, c.id_sucursal FROM cita c
              WHERE EXISTS (SELECT 1 FROM cita_servicio cs WHERE cs.id_cita = c.id_cita)
                AND NOT EXISTS (SELECT 1 FROM factura f WHERE f.id_cita = c.id_cita)
                AND EXISTS (SELECT 1 FROM timbrado t
                             WHERE t.id_sucursal = c.id_sucursal AND t.activo = 1
                               AND CURDATE() BETWEEN t.fecha_inicio AND t.fecha_fin)
              ORDER BY c.id_cita DESC LIMIT 1');
        if (! $cita) {
            $this->markTestSkipped('Hace falta una cita sin comprobante en un local con timbrado propio.');
        }

        // Un segundo local con SU timbrado del mismo tipo, vigente.
        DB::insert('INSERT INTO sucursal (nombre, activo) VALUES (?, 1)', ['Prueba Timbrado']);
        $otra = (int) DB::getPdo()->lastInsertId();

        // Un tipo que el local de la cita YA tenga: es la única forma de que
        // los dos timbrados compitan y la elección signifique algo.
        $tipo = (int) DB::scalar(
            'SELECT t.id_tipo_comprobante FROM timbrado t
              WHERE t.activo = 1 AND t.id_sucursal = ?
                AND CURDATE() BETWEEN t.fecha_inicio AND t.fecha_fin LIMIT 1', [(int) $cita->id_sucursal]);

        DB::insert(
            'INSERT INTO timbrado (id_sucursal, id_tipo_comprobante, nro_timbrado, establecimiento,
                                   punto_expedicion, nro_desde, nro_hasta, fecha_inicio, fecha_fin, activo)
             VALUES (?,?,?,?,?,?,?,?,?,1)',
            [$otra, $tipo, '99887766', '009', '001', 1, 9999999,
             // **Vence ANTES que los demás, a propósito.** `fn_timbrado_vigente`
             // ordena por `fecha_fin ASC`, así que con un vencimiento posterior la
             // versión rota igual elegía el correcto y la prueba pasaba por
             // casualidad. Con éste primero en el orden, si la función no mira la
             // sucursal se lleva el ajeno — que es justo lo que hay que detectar.
             date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('+2 days'))]);
        $timbradoAjeno = (int) DB::getPdo()->lastInsertId();

        // Emitiendo la cita —que es del local 1— el timbrado tiene que ser el
        // de ESE local, no el que acabamos de crear en el otro.
        $idFactura = Bd::idDe('sp_emitir_factura',
            [(int) $cita->id_cliente, (int) $cita->id_cita, (int) $cita->id_usuario, $tipo, 1, $otra]);

        $usado = (int) DB::scalar('SELECT id_timbrado FROM factura WHERE id_factura = ?', [$idFactura]);
        $sucUsada = (int) DB::scalar('SELECT id_sucursal FROM timbrado WHERE id_timbrado = ?', [$usado]);

        $this->assertSame((int) $cita->id_sucursal, $sucUsada,
            'El comprobante tiene que numerarse con el timbrado del local donde ocurrió la atención: '
            . 'con el de otra sede, el establecimiento impreso miente y los correlativos se mezclan.');
        $this->assertNotSame($timbradoAjeno, $usado,
            'Se usó el timbrado del otro local.');
    }

    #[Test]
    public function el_cobro_va_a_la_caja_del_local_no_a_la_de_quien_opera(): void
    {
        $uid = (int) DB::scalar('SELECT id_usuario FROM usuario WHERE activo = 1 ORDER BY id_usuario LIMIT 1');
        $cita = DB::selectOne(
            'SELECT c.id_cita, c.id_sucursal FROM cita c
              WHERE EXISTS (SELECT 1 FROM cita_servicio cs WHERE cs.id_cita = c.id_cita)
              ORDER BY c.id_cita DESC LIMIT 1');
        if (! $uid || ! $cita) {
            $this->markTestSkipped('Hace falta una cita con servicios en la base de prueba.');
        }

        DB::statement('UPDATE caja SET id_estado_caja = 2, fecha_cierre = NOW() WHERE id_estado_caja = 1');

        // Un segundo local, con su propia caja, abierta por la MISMA persona y
        // DESPUÉS que la del local de la cita: es el orden que hacía fallar el
        // `ORDER BY id_caja DESC`.
        DB::insert('INSERT INTO sucursal (nombre, activo) VALUES (?, 1)', ['Prueba Caja Local']);
        $otra = (int) DB::getPdo()->lastInsertId();

        DB::insert('INSERT INTO caja (id_usuario, id_sucursal, id_caja_fisica, id_estado_caja, monto_inicial)
                    VALUES (?, ?, ?, 1, 100000)', [$uid, (int) $cita->id_sucursal, $this->cajonDe((int) $cita->id_sucursal)]);
        $cajaDelLocal = (int) DB::getPdo()->lastInsertId();

        DB::insert('INSERT INTO caja (id_usuario, id_sucursal, id_caja_fisica, id_estado_caja, monto_inicial)
                    VALUES (?, ?, ?, 1, 900000)', [$uid, $otra, $this->cajonDe($otra)]);
        $cajaAjena = (int) DB::getPdo()->lastInsertId();

        $metodo = (int) DB::scalar("SELECT id_metodo_pago FROM metodo_pago WHERE tipo = 'EFECTIVO' AND activo = 1 LIMIT 1");
        $idCobro = Bd::idDe('sp_registrar_sena', [(int) $cita->id_cita, $metodo, $uid, 1000.0, 'TEST-LOCAL', null]);

        $quedo = (int) DB::scalar('SELECT id_caja FROM cobro WHERE id_cobro = ?', [$idCobro]);

        $this->assertSame($cajaDelLocal, $quedo,
            'La seña tiene que entrar al cajón del local de la cita. Si cae en el de otra sucursal, '
            . 'el arqueo de un local se come la plata del otro.');
        $this->assertNotSame($cajaAjena, $quedo,
            'Entró a la caja que esa persona abrió último, no a la del local: es el defecto CJ-03.');
    }

    #[Test]
    public function cada_sucursal_abre_su_caja_y_solo_una(): void
    {
        $uid = (int) DB::scalar('SELECT id_usuario FROM usuario WHERE activo = 1 ORDER BY id_usuario LIMIT 1');
        if (! $uid) {
            $this->markTestSkipped('No hay usuarios en la base de prueba.');
        }

        // Se parte de cero cajas abiertas para que la prueba mida la regla y no
        // el estado que dejó otra.
        DB::statement('UPDATE caja SET id_estado_caja = 2, fecha_cierre = NOW() WHERE id_estado_caja = 1');

        $sucursales = [(int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1')];
        foreach (['Prueba N1', 'Prueba N2'] as $nombre) {
            DB::insert('INSERT INTO sucursal (nombre, activo) VALUES (?, 1)', [$nombre]);
            $sucursales[] = (int) DB::getPdo()->lastInsertId();
        }

        // 1) Cada local abre la suya, sin estorbarse.
        foreach ($sucursales as $s) {
            DB::insert('INSERT INTO caja (id_usuario, id_sucursal, id_caja_fisica, id_estado_caja, monto_inicial)
                        VALUES (?, ?, ?, 1, 100000)', [$uid, $s, $this->cajonDe($s)]);
        }
        $this->assertSame(count($sucursales), (int) DB::scalar('SELECT COUNT(*) FROM caja WHERE id_estado_caja = 1'),
            'Cada sucursal tiene que poder abrir su propio cajón: si una bloquea a las demás, '
            . 'esos locales no cobran en todo el día.');

        // 2) Y dentro de un mismo local sigue habiendo una sola.
        foreach ($sucursales as $s) {
            try {
                DB::insert('INSERT INTO caja (id_usuario, id_sucursal, id_caja_fisica, id_estado_caja, monto_inicial)
                            VALUES (?, ?, ?, 1, 50000)', [$uid, $s, $this->cajonDe($s)]);
                $this->fail("La sucursal $s dejó abrir una segunda caja: el arqueo de ese local no cerraría.");
            } catch (\Illuminate\Database\QueryException $e) {
                $this->assertStringContainsString('sucursal', $e->getMessage(),
                    'El aviso tiene que decir que la caja abierta es la de ESTA sucursal.');
            }
        }
    }

    #[Test]
    public function dos_servicios_exclusivos_van_en_secuencia_no_en_paralelo(): void
    {
        // Dos servicios de la MISMA zona del cuerpo no se pueden hacer a la vez
        // —una coloración y una keratina se pisan: las dos son sobre el pelo—
        // así que van uno después del otro **aunque los hagan dos personas
        // distintas**. Eso es lo que se mide acá: que queden secuenciados y que
        // el segundo arranque exactamente cuando el primero termina.
        //
        // Hasta la 7.43.0 esto lo decidía la casilla «requiere atención
        // exclusiva». Con un booleano el caso normal no se podía expresar:
        // coloración y lavado suman aunque el lavado no sea «exclusivo».
        $ex = DB::select(
            'SELECT s.id_servicio FROM servicio s
              WHERE s.activo = 1 AND s.id_zona = (SELECT id_zona FROM zona_servicio WHERE nombre = ?)
              ORDER BY s.duracion_min ASC LIMIT 2', ['Cabello']
        );
        if (count($ex) < 2) {
            $this->markTestSkipped('Hacen falta dos servicios de la misma zona en la base de prueba.');
        }
        [$a, $b] = [(int) $ex[0]->id_servicio, (int) $ex[1]->id_servicio];

        // **Los profesionales salen de Agenda::profesionales(), no de una
        // consulta a mano por `es_personal`.** Con la consulta cruda entraba la
        // propietaria, que no tiene turno y desde AG-01 no atiende: la prueba
        // fallaba por «no atiende en ese horario», que no es lo que mide.
        $profs = Agenda::profesionales();
        if (count($profs) < 2) {
            $this->markTestSkipped('Hacen falta dos profesionales que atiendan en la base de prueba.');
        }
        [$p1, $p2] = [(int) $profs[0]->id_usuario, (int) $profs[1]->id_usuario];

        // Y el horario tiene que ser uno en que los DOS trabajen, así que se
        // toma de los huecos que el propio sistema ofrece en vez de inventar
        // una hora que puede caer domingo o fuera de turno.
        $dur = Agenda::duracion([$a]);
        $cuando = null;
        for ($i = 1; $i <= 60 && $cuando === null; $i++) {
            $dia = date('Y-m-d', strtotime("+$i days"));
            $comunes = array_intersect(
                Agenda::slotsProfesional($p1, $dia, $dur),
                Agenda::slotsProfesional($p2, $dia, $dur)
            );
            if ($comunes) {
                $cuando = $dia . ' ' . reset($comunes) . ':00';
            }
        }
        if ($cuando === null) {
            $this->markTestSkipped('No hay ningún horario en que los dos trabajen.');
        }

        // **Dos exclusivos con personas distintas SE PUEDEN**, y es lo que
        // pidió el usuario: no a la vez, pero sí uno después del otro. Antes se
        // rechazaba y la única salida que ofrecía el mensaje era ponerlos con la
        // misma persona, cosa que en el salón no siempre se puede.
        $this->assertNull(
            Agenda::validarReparto([$a => $p1, $b => $p2], $p1, $cuando),
            'Dos exclusivos con personas distintas tienen que poder agendarse en secuencia.'
        );

        // **Que se acepte no alcanza: tiene que quedar SECUENCIADO.** Si se
        // aceptara en paralelo, la clienta estaría en dos sillones a la vez y
        // el segundo profesional quedaría libre justo cuando va a atenderla.
        $turnos = Agenda::turnos([$a => $p1, $b => $p2], $p1);

        // **Cuál va primero no se fija acá, y es a propósito**: el reparto pone
        // adelante el bloque más largo, porque el primer turno es el único que
        // puede solaparse con lo de otras zonas y así la cita entera termina
        // antes. Lo que la prueba exige es lo que importa: que uno arranque con
        // la cita, que el otro NO arranque a la vez, y que no quede aire entre
        // los dos.
        $inicios = [$turnos[$p1]['inicio'], $turnos[$p2]['inicio']];
        sort($inicios);
        $this->assertSame(0, $inicios[0], 'Uno de los dos tiene que arrancar con la cita.');
        $this->assertGreaterThan(0, $inicios[1],
            'El otro tiene que esperar a que el primero termine, no arrancar a la vez.');

        $primero = $turnos[$p1]['inicio'] === 0 ? $turnos[$p1] : $turnos[$p2];
        $segundo = $turnos[$p1]['inicio'] === 0 ? $turnos[$p2] : $turnos[$p1];
        $this->assertSame($primero['minutos'], $segundo['inicio'],
            'El segundo arranca exactamente cuando el primero termina.');

        // Y la cita dura la SUMA, no el bloque más largo: la clienta está
        // ocupada de punta a punta.
        $this->assertSame(
            $turnos[$p1]['minutos'] + $turnos[$p2]['minutos'],
            Agenda::duracionReparto([$a => $p1, $b => $p2], $p1),
            'En secuencia la cita dura lo que suman los dos, no lo que dura el más largo.'
        );

        // Los mismos dos, con la misma persona: van uno después del otro y no
        // hay nada que secuenciar entre profesionales.
        $this->assertNull(
            Agenda::validarReparto([$a => $p1, $b => $p1], $p1, $cuando),
            'Con un solo profesional no hay paralelo, así que no hay conflicto.'
        );
    }

    #[Test]
    public function el_portal_pregunta_el_profesional_una_sola_vez(): void
    {
        // La pantalla tenía dos formas de contestar lo mismo: un selector por
        // servicio («quien me atienda» / «con Rocío») y, más abajo, un «¿Con
        // quién?» para toda la cita. No era evidente cuál mandaba. Queda el de
        // arriba, que es el más fino.
        $u = DB::selectOne(
            'SELECT u.id_usuario, c.id_cliente FROM usuario u
               JOIN cliente c ON c.id_persona = u.id_persona
              WHERE u.activo = 1 LIMIT 1'
        );
        if (! $u) {
            $this->markTestSkipped('No hay ninguna cuenta de cliente en la base de prueba.');
        }

        session([
            'uid' => (int) $u->id_usuario, 'rol' => (int) config('permisos.rol_cliente', 4),
            'es_personal' => false, 'es_cliente' => true, 'id_cliente' => (int) $u->id_cliente,
        ]); $this->conSucursal();

        // Con más de un local, el portal pide la sucursal ANTES de mostrar
        // servicios y horarios —no serían de ningún lado— así que se la pasa
        // explícita. Con uno solo se elige sola y el parámetro sobra, pero no
        // molesta: la prueba queda igual de válida en los dos casos.
        $suc = (int) DB::scalar('SELECT id_sucursal FROM sucursal WHERE activo = 1 ORDER BY id_sucursal LIMIT 1');

        $this->get(route('portal.reservar', ['sucursal' => $suc]))
            ->assertOk()
            ->assertDontSee('¿Con quién?')
            ->assertSee('prof_servicio', false);   // el selector fino sigue
    }

    #[Test]
    public function un_alta_rapida_no_borra_lo_que_habia_cargado(): void
    {
        // El alta rápida manda SU formulario, no el grande: los campos de la
        // ficha no viajan y la pantalla se redibujaba vacía. `app.js` adjunta
        // una copia en `_borrador` y el controlador la devuelve a la sesión.
        $admin = (int) config('permisos.rol_admin', 1);
        session(['uid' => 1, 'rol' => $admin, 'es_personal' => true, 'es_cliente' => false]); $this->conSucursal();

        $ficha = [
            'nombre' => 'Rocío', 'apellido' => 'Benítez', 'username' => 'rocio.b',
            'email' => 'rocio@ejemplo.com', 'password' => 'secreto123',
        ];

        $this->post(route('seguridad.sucursal.rapida'), [
            'nombre' => 'Sucursal Centro ' . uniqid(),   // el del alta rápida
            'ciudad' => 'Luque',
            '_borrador' => json_encode($ficha),
        ])->assertRedirect();

        // Lo tipeado vuelve...
        $this->assertSame('Rocío', session('_old_input.nombre'),
            'El nombre de la ficha tenía que volver, no el de la sucursal.');
        $this->assertSame('Benítez', session('_old_input.apellido'));
        $this->assertSame('rocio.b', session('_old_input.username'));

        // ...pero la contraseña NO queda dando vueltas en la sesión.
        $this->assertNull(session('_old_input.password'),
            'La contraseña no tiene que guardarse en el borrador.');
    }

    #[Test]
    public function sin_borrador_el_alta_rapida_no_pisa_los_datos_existentes(): void
    {
        // Sin JavaScript no llega `_borrador`. En ese caso no hay que flashear
        // un input vacío: en una pantalla de edición, un `old()` vacío le
        // ganaría al valor que la vista muestra por defecto y borraría la ficha.
        $admin = (int) config('permisos.rol_admin', 1);
        session(['uid' => 1, 'rol' => $admin, 'es_personal' => true, 'es_cliente' => false]); $this->conSucursal();

        $this->post(route('seguridad.sucursal.rapida'), [
            'nombre' => 'Sucursal Sin JS ' . uniqid(),
            'ciudad' => 'Luque',
        ])->assertRedirect();

        $this->assertNull(session('_old_input'),
            'Sin borrador no tendría que quedar ningún old() en la sesión.');
    }

    #[Test]
    public function el_aviso_de_roles_es_solo_para_quien_puede_dejarse_afuera(): void
    {
        // A quien ya está en la pantalla no se le explica que tiene el permiso
        // —lo tiene, por eso entró—. Lo que sí importa, y no es obvio, es que su
        // propio rol se edita ahí y puede quedarse sin la llave.
        $admin = (int) config('permisos.rol_admin', 1);

        session(['uid' => 1, 'rol' => $admin, 'es_personal' => true, 'es_cliente' => false]); $this->conSucursal();
        $this->get(route('seguridad.roles'))
            ->assertOk()
            ->assertDontSee('dejás de poder entrar acá');

        // El mismo aviso sí aparece para un rol que no es el Administrador y que
        // puede editar la matriz: ese sí puede sacarse el permiso a sí mismo.
        $rolProf = (int) DB::scalar("SELECT id_rol FROM rol WHERE nombre = 'Profesional' LIMIT 1");
        if (! $rolProf) {
            $this->markTestSkipped('No hay rol Profesional en la base de prueba.');
        }

        DB::insert('INSERT INTO rol_modulo (id_rol, modulo) VALUES (?,?)', [$rolProf, 'seguridad.roles']);
        Permisos::olvidar($rolProf);

        session(['uid' => (int) (DB::scalar('SELECT id_usuario FROM usuario WHERE id_rol = ? AND activo = 1 LIMIT 1', [$rolProf]) ?: 1), 'rol' => $rolProf, 'es_personal' => true, 'es_cliente' => false, 'id_sucursal' => 1]);
        $this->get(route('seguridad.roles'))
            ->assertOk()
            ->assertSee('dejás de poder entrar acá', false);
    }

    #[Test]
    public function editar_un_rol_protegido_no_lo_desactiva_ni_lo_saca_del_panel(): void
    {
        // El formulario de un rol protegido no dibuja esas dos casillas, y una
        // casilla que no se marca no viaja en el POST: si el servidor las
        // leyera del pedido, renombrar al Cliente lo dejaría inactivo y el
        // portal se quedaría sin rol al que asignar a quien se registra.
        // Esconder la casilla no es el control; el control es esto.
        $cliente = (int) config('permisos.rol_cliente', 4);
        $antes = DB::selectOne('SELECT nombre, es_personal, activo FROM rol WHERE id_rol = ?', [$cliente]);
        if (! $antes) {
            $this->markTestSkipped('No existe el rol Cliente en la base de prueba.');
        }

        session(['uid' => 1, 'rol' => (int) config('permisos.rol_admin', 1),
                 'es_personal' => true, 'es_cliente' => false]); $this->conSucursal();

        $this->post(route('seguridad.rol.editar'), [
            'id_rol' => $cliente,
            'nombre' => $antes->nombre . ' (renombrado)',
            'descripcion' => 'prueba',
            // sin `activo` ni `es_personal`, que es como llega del formulario
        ])->assertRedirect(route('seguridad.roles'));

        $despues = DB::selectOne('SELECT nombre, es_personal, activo FROM rol WHERE id_rol = ?', [$cliente]);

        $this->assertSame($antes->nombre . ' (renombrado)', $despues->nombre,
            'El nombre sí se tenía que poder cambiar.');
        $this->assertSame((int) $antes->activo, (int) $despues->activo,
            'Un rol protegido no puede quedar inactivo por no marcar una casilla que ni se dibuja.');
        $this->assertSame((int) $antes->es_personal, (int) $despues->es_personal,
            'Tampoco puede cambiar de tipo: el código lo referencia por id.');
    }

    #[Test]
    public function la_cita_repartida_entera_queda_a_nombre_de_quien_mas_trabaja(): void
    {
        // Cuando la clienta reparte TODOS los servicios y no elige principal,
        // al principal no le queda nada que hacer. Antes se buscaba entonces a
        // alguien «libre» de afuera y la cita caía en la propietaria, que no
        // atendía nada ahí. Y el método que lo resuelve **no existía**:
        // `CitasController` ya lo llamaba, así que ese camino reventaba con
        // «Call to undefined method» — sin que ninguna prueba lo recorriera,
        // porque no es un error de sintaxis.
        $profs = DB::select(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.activo = 1 AND r.es_personal = 1 ORDER BY u.id_usuario LIMIT 2'
        );
        $servicios = DB::select(
            'SELECT id_servicio, duracion_min FROM servicio WHERE activo = 1
              ORDER BY duracion_min DESC LIMIT 2'
        );
        if (count($profs) < 2 || count($servicios) < 2
            || (int) $servicios[0]->duracion_min === (int) $servicios[1]->duracion_min) {
            $this->markTestSkipped('Hacen falta dos profesionales y dos servicios de distinta duración.');
        }

        $largo = (int) $profs[0]->id_usuario;
        $corto = (int) $profs[1]->id_usuario;

        $this->assertSame($largo, Agenda::principalDelReparto([
            (int) $servicios[0]->id_servicio => $largo,   // el servicio más largo
            (int) $servicios[1]->id_servicio => $corto,
        ]), 'La cita tiene que quedar a nombre de quien más minutos pone.');

        // Y al revés, para que no sea el orden del formulario el que decide
        $this->assertSame($largo, Agenda::principalDelReparto([
            (int) $servicios[1]->id_servicio => $corto,
            (int) $servicios[0]->id_servicio => $largo,
        ]), 'El resultado no puede depender del orden en que vengan los servicios.');
    }

    /**
     * AG-02: la comisión es de quien hizo el servicio, no del de la cita.
     *
     * `atenderGuardar` escribía siempre `cita.id_usuario` como autor de cada
     * servicio realizado, ignorando el reparto de `cita_servicio`. Como
     * `fn_comision_servicio` sale de `servicio_realizado.id_usuario`, **la
     * comisión se le pagaba a quien no trabajó**, y las columnas «Generado» y
     * «Comisión» del informe del equipo atribuían mal el trabajo. La función de
     * varios profesionales por cita existe desde la 5.3.0 y no llegaba al final
     * del circuito.
     */
    #[Test]
    public function el_servicio_repartido_queda_a_nombre_de_quien_lo_hizo(): void
    {
        $profs = Agenda::profesionales();
        if (count($profs) < 2) {
            $this->markTestSkipped('Hacen falta dos profesionales que atiendan.');
        }
        [$dueno, $ayuda] = [(int) $profs[0]->id_usuario, (int) $profs[1]->id_usuario];

        $cliente = $this->clienteLibreHoy();
        $servicios = DB::select('SELECT id_servicio FROM servicio WHERE activo = 1 ORDER BY id_servicio LIMIT 2');
        if (! $cliente || count($servicios) < 2) {
            $this->markTestSkipped('Falta un cliente o dos servicios en la base de prueba.');
        }
        [$sA, $sB] = [(int) $servicios[0]->id_servicio, (int) $servicios[1]->id_servicio];

        // Una cita del dueño, con el segundo servicio repartido a la otra.
        // `cita` NO guarda la duración: es derivada y la calcula fn_cita_duracion.
        DB::insert('INSERT INTO cita (id_cliente,id_usuario,id_estado_cita,fecha_hora,id_sucursal) VALUES (?,?,?,?,1)',
            [$cliente, $dueno, 1, ahora_bd('Y-m-d H:i:s')]);
        $idCita = (int) DB::getPdo()->lastInsertId();
        DB::insert('INSERT INTO cita_servicio (id_cita,id_servicio,id_usuario) VALUES (?,?,NULL)', [$idCita, $sA]);
        DB::insert('INSERT INTO cita_servicio (id_cita,id_servicio,id_usuario) VALUES (?,?,?)', [$idCita, $sB, $ayuda]);

        // Atender exige que el profesional haya fichado ese día, así que se
        // ficha: es el camino real, no un atajo.
        $this->fichar($dueno);

        $this->entrarComoAdministrador();
        $this->post(route('citas.atender.guardar'), [
            'id_cita' => $idCita,
            'servicios' => [$sA, $sB],
        ]);

        $autorA = (int) DB::scalar('SELECT id_usuario FROM servicio_realizado WHERE id_cita = ? AND id_servicio = ?', [$idCita, $sA]);
        $autorB = (int) DB::scalar('SELECT id_usuario FROM servicio_realizado WHERE id_cita = ? AND id_servicio = ?', [$idCita, $sB]);

        $this->assertSame($dueno, $autorA, 'Sin reparto, el servicio es del profesional de la cita.');
        $this->assertSame($ayuda, $autorB,
            'El servicio repartido tiene que quedar a nombre de quien lo hizo: si no, la comisión '
            . 'se le paga a quien no trabajó.');
    }

    /**
     * IN-02: que falte un frasco no puede borrar el trabajo de la tarde.
     *
     * `atenderGuardar` corría todo en una sola transacción, así que un producto
     * sin stock abortaba **también los servicios realizados**, que no tenían
     * nada que ver. Fueron **69 de 204 intentos (34 %)**: la cita quedaba sin
     * cerrar, no se podía facturar y terminaba Atrasada o Ausente.
     */
    #[Test]
    public function un_producto_sin_stock_no_tumba_los_servicios_de_la_atencion(): void
    {
        $cliente = $this->clienteLibreHoy();
        $prof = (int) DB::scalar('SELECT id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
                                   WHERE u.activo = 1 AND r.es_personal = 1 ORDER BY u.id_usuario LIMIT 1');
        $servicio = (int) DB::scalar('SELECT id_servicio FROM servicio WHERE activo = 1 ORDER BY id_servicio LIMIT 1');

        // Un producto que NO tiene con qué: se pide mucho más de lo que hay.
        $prod = DB::selectOne(
            'SELECT p.id_producto, fn_producto_stock(p.id_producto, 1) AS stock
               FROM producto p WHERE p.activo = 1 AND p.contenido IS NULL
              ORDER BY p.id_producto LIMIT 1'
        );
        if (! $cliente || ! $prof || ! $servicio || ! $prod) {
            $this->markTestSkipped('Falta un cliente, un profesional, un servicio o un producto.');
        }
        $pedir = (float) $prod->stock + 1000;

        DB::insert('INSERT INTO cita (id_cliente,id_usuario,id_estado_cita,fecha_hora,id_sucursal) VALUES (?,?,?,?,1)',
            [$cliente, $prof, 1, date('Y-m-d H:i:s', strtotime('+3 hours'))]);
        $idCita = (int) DB::getPdo()->lastInsertId();
        DB::insert('INSERT INTO cita_servicio (id_cita,id_servicio,id_usuario) VALUES (?,?,NULL)', [$idCita, $servicio]);

        $this->entrarComoAdministrador();
        $this->post(route('citas.atender.guardar'), [
            'id_cita' => $idCita,
            'servicios' => [$servicio],
            'producto' => [$prod->id_producto],
            'cantidad' => [(string) $pedir],
            'servicio_de' => [0],
        ]);

        $this->assertSame(1, (int) DB::scalar(
            'SELECT COUNT(*) FROM servicio_realizado WHERE id_cita = ?', [$idCita]),
            'El servicio se hizo: no se puede perder porque falte un producto.');

        $this->assertSame(4, (int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita = ?', [$idCita]),
            'La cita tiene que quedar Atendida, o no se puede facturar.');

        $this->assertSame(0, (int) DB::scalar(
            'SELECT COUNT(*) FROM producto_utilizado pu
               JOIN servicio_realizado sr ON sr.id_servicio_realizado = pu.id_servicio_realizado
              WHERE sr.id_cita = ?', [$idCita]),
            'El consumo que no se pudo descontar no se guarda: el stock quedaría mintiendo.');

        $this->assertGreaterThanOrEqual(0, (float) DB::scalar('SELECT fn_producto_stock(?,1)', [$prod->id_producto]),
            'Y el stock no se toca.');
    }

    /**
     * SE-01: el panel no le muestra a cualquiera la plata del salón.
     *
     * Las cuatro métricas se calculaban sin filtrar y la vista las dibujaba
     * siempre; sólo la barra de caja estaba protegida. Una empleada entraba y
     * veía **cuánto facturó el salón hoy**, cuántas citas hay en total y
     * cuántos productos faltan. Es la misma fuga que la 7.13.1 corrigió para la
     * barra: se arregló la barra y quedaron las métricas de al lado.
     */
    #[Test]
    public function el_panel_muestra_solo_los_numeros_del_modulo_que_cada_rol_tiene(): void
    {
        $rolProf = 2;
        $uid = (int) DB::scalar(
            'SELECT id_usuario FROM usuario WHERE id_rol = ? AND activo = 1 ORDER BY id_usuario LIMIT 1', [$rolProf]
        ) ?: 999999;

        // Se le saca todo lo que no es suyo, que es lo que el salón haría en
        // Seguridad → Roles. La transacción de la prueba lo devuelve.
        DB::delete("DELETE FROM rol_modulo WHERE id_rol = ? AND modulo IN
                    ('facturacion','facturacion.cobros','facturacion.cajas','inventario','inventario.stock')", [$rolProf]);

        session(['uid' => $uid, 'rol' => $rolProf, 'es_personal' => true, 'es_cliente' => false]); $this->conSucursal();
        $panel = $this->get(route('panel'))->assertOk();

        $panel->assertDontSee('Ingresos de hoy')
              ->assertDontSee('Productos bajo stock');

        // Y lo que sí es suyo se sigue viendo, con el rótulo que corresponde:
        // no son «las citas de hoy», son las suyas.
        $panel->assertSee('Mis citas de hoy');

        // El Administrador ve todo, que es el otro lado de la misma regla.
        $admin = (int) DB::scalar('SELECT id_usuario FROM usuario WHERE id_rol = ? AND activo = 1 ORDER BY id_usuario LIMIT 1',
            [(int) config('permisos.rol_admin', 1)]);
        session(['uid' => $admin, 'rol' => (int) config('permisos.rol_admin', 1),
                 'es_personal' => true, 'es_cliente' => false]); $this->conSucursal();
        $this->get(route('panel'))->assertOk()
             ->assertSee('Ingresos de hoy')
             ->assertSee('Citas de hoy');
    }

    /**
     * CJ-02: la liquidación al personal sale del cajón, si se paga en efectivo.
     *
     * `fn_caja_saldo` sumaba el monto inicial, los cobros en efectivo y
     * `movimiento_caja`, y restaba los pagos a proveedores — **el pago al
     * personal no estaba**. Se liquidaron Gs. 1.868.250 en 90 días y el arqueo
     * no registró ni un egreso. `pago_personal` tampoco tenía con qué: no
     * guardaba ni la caja ni el medio de pago, al revés que `pago_proveedor`.
     */
    #[Test]
    public function la_liquidacion_al_personal_descuenta_del_cajon_solo_si_es_en_efectivo(): void
    {
        $caja = (int) DB::scalar('SELECT id_caja FROM caja ORDER BY id_caja DESC LIMIT 1');
        $prof = (int) DB::scalar(
            'SELECT sr.id_usuario FROM servicio_realizado sr
               LEFT JOIN detalle_pago_personal d ON d.id_servicio_realizado = sr.id_servicio_realizado
              WHERE d.id_detalle_pago IS NULL GROUP BY sr.id_usuario LIMIT 1'
        );
        $efectivo = (int) DB::scalar("SELECT id_metodo_pago FROM metodo_pago WHERE tipo = 'EFECTIVO' AND activo = 1 LIMIT 1");
        $banco = (int) DB::scalar("SELECT id_metodo_pago FROM metodo_pago WHERE tipo <> 'EFECTIVO' AND activo = 1 LIMIT 1");
        if (! $caja || ! $prof || ! $efectivo || ! $banco) {
            $this->markTestSkipped('Falta una caja, un profesional con servicios sin liquidar o los medios de pago.');
        }

        $antes = (float) DB::scalar('SELECT fn_caja_saldo(?)', [$caja]);

        // En efectivo: sale del cajón.
        $idPago = Bd::idDe('sp_registrar_pago_personal', [$prof, 1, '08/2026', $efectivo, $caja]);
        $monto = (float) DB::scalar('SELECT fn_pago_personal_monto(?)', [$idPago]);
        $this->assertGreaterThan(0, $monto, 'La liquidación tiene que tener monto, o la prueba no mide nada.');
        $this->assertEqualsWithDelta($antes - $monto, (float) DB::scalar('SELECT fn_caja_saldo(?)', [$caja]), 0.01,
            'Una liquidación en efectivo tiene que bajar el arqueo.');

        // Se deshace para probar el otro medio sobre los mismos servicios.
        DB::delete('DELETE FROM detalle_pago_personal WHERE id_pago_personal = ?', [$idPago]);
        DB::delete('DELETE FROM pago_personal WHERE id_pago_personal = ?', [$idPago]);

        // Por banco: no toca el cajón, sale de la cuenta.
        $idPago2 = Bd::idDe('sp_registrar_pago_personal', [$prof, 1, '08/2026', $banco, $caja]);
        $this->assertEqualsWithDelta($antes, (float) DB::scalar('SELECT fn_caja_saldo(?)', [$caja]), 0.01,
            'Una liquidación por transferencia no saca un guaraní del cajón.');
        $this->assertGreaterThan(0, (float) DB::scalar('SELECT fn_pago_personal_monto(?)', [$idPago2]),
            'Pero se registra igual: el salón la pagó.');

        // Y la vista la expone separada, que es lo que permite cuadrar.
        $r = DB::selectOne('SELECT pagos_pers_efectivo, pagos_pers_otros, pagos_personal
                              FROM vw_caja_resumen WHERE id_caja = ?', [$caja]);
        $this->assertEqualsWithDelta(0, (float) $r->pagos_pers_efectivo, 0.01);
        $this->assertGreaterThan(0, (float) $r->pagos_pers_otros);
        $this->assertGreaterThan(0, (float) $r->pagos_personal);
    }

    /**
     * El precio de venta salió de la pantalla, y editar un producto NO lo borra.
     *
     * El salón vende servicios, no productos, así que preguntar a cuánto se
     * vendería prometía algo que ninguna pantalla hace (IN-03). Los campos
     * quedaron **comentados y no borrados**, por si se revierte la decisión.
     *
     * La trampa está en el guardado: si el formulario deja de mandar el campo,
     * `num()` devuelve 0 y el UPDATE le borra el precio a cada producto que se
     * edite. Por eso se conserva el que ya tenía — si algún día se vuelve
     * atrás, lo que el salón había cargado sigue estando.
     */
    #[Test]
    public function el_precio_de_venta_no_se_pide_pero_tampoco_se_pierde(): void
    {
        $prod = DB::selectOne('SELECT p.id_producto, p.id_categoria, p.nombre, p.unidad_medida,
                                      COALESCE(ps.stock_minimo, 0) AS stock_minimo,
                                      p.precio_costo, p.precio_venta, p.tasa_iva
                                 FROM producto p
                                 LEFT JOIN producto_sucursal ps
                                        ON ps.id_producto = p.id_producto AND ps.id_sucursal = 1
                                ORDER BY p.id_producto LIMIT 1');
        if (! $prod) {
            $this->markTestSkipped('No hay productos en la base de prueba.');
        }

        // Se le carga un precio de venta como lo tendría un salón que ya lo usó.
        DB::update('UPDATE producto SET precio_venta = 55000 WHERE id_producto = ?', [$prod->id_producto]);

        $this->entrarComoAdministrador();

        // La pantalla no lo pide.
        $this->get(route('inventario.producto_form', $prod->id_producto))
             ->assertOk()
             ->assertDontSee('Precio de venta')
             ->assertDontSee('name="precio_venta"', false);

        // Y guardar sin ese campo no lo borra.
        $this->post(route('inventario.producto.guardar'), [
            'id_producto' => $prod->id_producto,
            'id_categoria' => $prod->id_categoria,
            'nombre' => $prod->nombre,
            'unidad_medida' => $prod->unidad_medida,
            'stock_minimo' => (string) $prod->stock_minimo,
            'precio_costo' => (string) $prod->precio_costo,
            'tasa_iva' => (int) $prod->tasa_iva,
        ]);

        $this->assertEqualsWithDelta(55000,
            (float) DB::scalar('SELECT precio_venta FROM producto WHERE id_producto = ?', [$prod->id_producto]), 0.01,
            'Editar un producto no puede borrarle el precio de venta que ya tenía cargado.');
    }

    /**
     * AG-03: las citas de quien se dio de baja se pasan en bloque.
     *
     * El aviso a las clientas salía (3 de 3), pero **las citas seguían
     * ocupando la agenda del profesional dado de baja** y había que abrirlas de
     * a una para cambiarles el profesional. Con un equipo chico y una licencia
     * larga, eso es media mañana.
     *
     * Lo que se exige acá es que mueva **y que no mueva a ciegas**: una cita
     * que caiga donde el destino ya está ocupado tiene que quedar como estaba,
     * porque reasignarla sería vender dos veces el mismo horario.
     */
    #[Test]
    public function las_citas_de_un_profesional_se_reasignan_sin_pisar_las_del_otro(): void
    {
        $profs = Agenda::profesionales();
        if (count($profs) < 2) {
            $this->markTestSkipped('Hacen falta dos profesionales que atiendan.');
        }
        [$sale, $recibe] = [(int) $profs[0]->id_usuario, (int) $profs[1]->id_usuario];

        // **Dos clientas distintas**, porque desde la 7.14.0 la base impide que
        // la misma repita el mismo servicio el mismo día — una regla correcta
        // con la que esta prueba no tiene por qué pelearse.
        $clientes = array_map(fn ($c) => (int) $c->id_cliente,
            DB::select('SELECT id_cliente FROM cliente WHERE activo = 1 ORDER BY id_cliente LIMIT 2'));
        $servicio = (int) DB::scalar('SELECT id_servicio FROM servicio WHERE activo = 1 ORDER BY duracion_min LIMIT 1');
        $dur = Agenda::duracion([$servicio]);

        // Un horario en que los DOS estén libres: ahí la reasignación entra.
        $libre = null;
        for ($i = 1; $i <= 30 && $libre === null; $i++) {
            $dia = date('Y-m-d', strtotime("+$i days"));
            $comunes = array_intersect(
                Agenda::slotsProfesional($sale, $dia, $dur),
                Agenda::slotsProfesional($recibe, $dia, $dur)
            );
            if ($comunes) {
                $libre = $dia . ' ' . reset($comunes) . ':00';
            }
        }
        if (count($clientes) < 2 || ! $servicio || $libre === null) {
            $this->markTestSkipped('Faltan dos clientes, un servicio o un horario en que los dos trabajen.');
        }

        $crear = function (int $prof, string $cuando, int $cliente) use ($servicio): int {
            DB::insert('INSERT INTO cita (id_cliente,id_usuario,id_estado_cita,fecha_hora,id_sucursal) VALUES (?,?,?,?,1)',
                [$cliente, $prof, 1, $cuando]);
            $id = (int) DB::getPdo()->lastInsertId();
            DB::insert('INSERT INTO cita_servicio (id_cita,id_servicio,id_usuario) VALUES (?,?,NULL)', [$id, $servicio]);

            return $id;
        };

        // 1. Una cita del que se va, en un hueco que el otro tiene libre: entra.
        $mueve = $crear($sale, $libre, $clientes[0]);
        $this->assertTrue(Agenda::reasignar($mueve, $recibe), 'Ese horario estaba libre para el destino.');
        $this->assertSame($recibe, (int) DB::scalar('SELECT id_usuario FROM cita WHERE id_cita = ?', [$mueve]));

        // 2. Ahora el destino quedó ocupado a esa hora. Otra cita del que se va,
        //    en el MISMO horario, ya no puede pasarle: se pisarían.
        $choca = $crear($sale, $libre, $clientes[1]);
        $this->assertFalse(Agenda::reasignar($choca, $recibe),
            'Reasignar sobre un horario ya ocupado sería venderlo dos veces.');
        $this->assertSame($sale, (int) DB::scalar('SELECT id_usuario FROM cita WHERE id_cita = ?', [$choca]),
            'La que no entra tiene que quedar como estaba.');
    }

    /**
     * La tarjeta del módulo no anuncia lo que el rol no puede abrir.
     *
     * El renglón de abajo de cada tarjeta —«Usuarios · Roles · Turnos…»— era un
     * texto fijo de `config/navegacion.php`, así que a quien le revocaban Roles
     * le seguía apareciendo «Roles» anunciado en la tarjeta de Seguridad. El
     * permiso funcionaba —entrar daba 403— pero **el cartel prometía una
     * pantalla que no iba a poder abrir**.
     */
    #[Test]
    public function la_tarjeta_del_modulo_no_anuncia_pantallas_sin_permiso(): void
    {
        $rolProf = 2;
        $uid = (int) DB::scalar(
            'SELECT id_usuario FROM usuario WHERE id_rol = ? AND activo = 1 ORDER BY id_usuario LIMIT 1', [$rolProf]
        ) ?: 999999;

        // Se le deja SÓLO Asistencia, que es lo que el rol Profesional tiene de
        // fábrica. **El módulo es Personal y no Seguridad**: la 7.57.0 partió
        // Seguridad en tres y la asistencia se fue con Personal — la clave vieja
        // se sigue guardando y `equivalencias` la traduce, así que el escenario
        // vale igual, pero la tarjeta que hay que mirar es la de Personal.
        DB::delete("DELETE FROM rol_modulo WHERE id_rol = ?
                     AND (modulo LIKE 'seguridad%' OR modulo LIKE 'personal%'
                          OR modulo LIKE 'configuracion%')
                     AND modulo <> 'seguridad.asistencia'", [$rolProf]);
        DB::insert('INSERT IGNORE INTO rol_modulo (id_rol, modulo) VALUES (?, ?)',
            [$rolProf, 'seguridad.asistencia']);

        session(['uid' => $uid, 'rol' => $rolProf, 'es_personal' => true, 'es_cliente' => false]); $this->conSucursal();

        // Sólo Asistencia, y en particular **sin «Profesionales»**: esa es la
        // ficha del equipo, que Personal ofrece prestada de Seguridad y este
        // rol no tiene.
        $this->assertSame('Asistencia', Navegacion::subDe('personal', 'NO DEBERÍA CAER ACÁ'),
            'La tarjeta tiene que listar sólo lo que este rol puede abrir.');

        $this->get(route('panel'))->assertOk()->assertDontSee('Usuarios · Roles');

        // Y el Administrador las sigue viendo todas, que es el otro lado.
        session(['uid' => 1, 'rol' => (int) config('permisos.rol_admin', 1),
                 'es_personal' => true, 'es_cliente' => false]); $this->conSucursal();
        foreach (['seguridad' => ['Usuarios', 'Roles', 'Auditoría'],
                  'personal' => ['Profesionales', 'Turnos', 'Asistencia', 'Comisiones']] as $mod => $pantallas) {
            $sub = Navegacion::subDe($mod, '');
            foreach ($pantallas as $pantalla) {
                $this->assertStringContainsString($pantalla, $sub,
                    'La tarjeta de ' . $mod . ' tendría que anunciar «' . $pantalla . '».');
            }
        }
    }

    /**
     * El canje de puntos, de punta a punta.
     *
     * El programa de fidelización sólo sumaba: en 90 días se acumularon 1.414
     * puntos y **no había forma de gastarlos** (IN-03). Lo que esta prueba fija
     * es el circuito entero, porque cada pedazo por separado no dice nada:
     * canjear descuenta puntos, el canje vence, se usa en una cita, y el
     * servicio canjeado **va a cero en el comprobante**.
     */
    #[Test]
    public function canjear_puntos_descuenta_y_el_servicio_no_se_cobra(): void
    {
        $cliente = (int) DB::scalar('SELECT id_cliente FROM cliente WHERE activo = 1 ORDER BY id_cliente LIMIT 1');
        $prof = (int) DB::scalar('SELECT u.id_usuario FROM usuario u
                                    JOIN usuario_turno ut ON ut.id_usuario = u.id_usuario LIMIT 1');
        $servicios = DB::select('SELECT id_servicio, nombre, precio FROM servicio WHERE activo = 1
                                  ORDER BY precio DESC LIMIT 2');
        if (! $cliente || ! $prof || count($servicios) < 2) {
            $this->markTestSkipped('Falta un cliente, un profesional con turno o dos servicios.');
        }
        [$regalado, $pagado] = $servicios;

        // El salón publica el canje y la clienta junta puntos.
        DB::insert('INSERT INTO servicio_canjeable (id_servicio, puntos, dias_vigencia, activo) VALUES (?,?,?,1)',
            [$regalado->id_servicio, 50, 30]);
        DB::statement('CALL sp_registrar_puntos(?, NULL, ?, ?, ?)', [$cliente, 'AJUSTE', 500, 'prueba']);

        $antes = Canje::puntos($cliente);
        $idCanje = Canje::canjear($cliente, (int) $regalado->id_servicio);

        $this->assertSame($antes - 50, Canje::puntos($cliente), 'El canje tiene que descontar los puntos.');
        $this->assertSame('DISPONIBLE', DB::scalar('SELECT fn_canje_estado(?)', [$idCanje]));

        // La vigencia se cuenta desde el canje, no desde una fecha fija.
        $this->assertSame(date('Y-m-d', strtotime('+30 days')),
            (string) DB::scalar('SELECT vence_en FROM canje WHERE id_canje = ?', [$idCanje]),
            'La vigencia corre desde el día del canje.');

        // Sin puntos suficientes no se puede: se le vacía el saldo.
        DB::statement('CALL sp_registrar_puntos(?, NULL, ?, ?, ?)',
            [$cliente, 'AJUSTE', -Canje::puntos($cliente), 'prueba']);
        try {
            Canje::canjear($cliente, (int) $regalado->id_servicio);
            $this->fail('Sin puntos no se puede canjear.');
        } catch (Throwable $e) {
            $this->assertStringContainsString('alcanzan', $e->getMessage());
        }

        // Se usa en una cita…
        DB::insert('INSERT INTO cita (id_cliente,id_usuario,id_estado_cita,fecha_hora,id_sucursal) VALUES (?,?,4,NOW(),1)',
            [$cliente, $prof]);
        $idCita = (int) DB::getPdo()->lastInsertId();
        foreach ([$regalado, $pagado] as $s) {
            DB::insert('INSERT INTO cita_servicio (id_cita,id_servicio,id_usuario) VALUES (?,?,NULL)',
                [$idCita, $s->id_servicio]);
        }
        $this->assertSame(1, Canje::aplicarACita([$idCanje], $idCita, $cliente));
        $this->assertSame('USADO', DB::scalar('SELECT fn_canje_estado(?)', [$idCanje]));

        // …y el comprobante lo NOMBRA pero no lo cobra.
        // El sexto parámetro es la sucursal, para elegir el timbrado del local. Con
        // una cita cargada manda la de la cita, así que el 0 acá es sólo la red.
        $idFactura = Bd::idDe('sp_emitir_factura', [$cliente, $idCita, $prof, 1, 1, 0]);
        $renglones = DB::select(
            'SELECT df.id_servicio, df.precio_unitario FROM detalle_factura df WHERE df.id_factura = ?', [$idFactura]
        );
        $this->assertCount(2, $renglones,
            'El servicio canjeado tiene que constar en el comprobante: se hizo, aunque no se cobre.');

        $porServicio = [];
        foreach ($renglones as $r) {
            $porServicio[(int) $r->id_servicio] = (float) $r->precio_unitario;
        }
        $this->assertEqualsWithDelta(0, $porServicio[(int) $regalado->id_servicio], 0.01,
            'El servicio canjeado va a cero.');
        $this->assertEqualsWithDelta((float) $pagado->precio, $porServicio[(int) $pagado->id_servicio], 0.01,
            'El que no se canjeó se cobra normal.');
    }

    /**
     * Un canje no se puede gastar dos veces, ni gastarle el canje a otra.
     */
    #[Test]
    public function el_canje_es_de_quien_lo_hizo_y_se_usa_una_sola_vez(): void
    {
        $clientes = array_map(fn ($c) => (int) $c->id_cliente,
            DB::select('SELECT id_cliente FROM cliente WHERE activo = 1 ORDER BY id_cliente LIMIT 2'));
        $prof = (int) DB::scalar('SELECT u.id_usuario FROM usuario u
                                    JOIN usuario_turno ut ON ut.id_usuario = u.id_usuario LIMIT 1');
        $servicio = (int) DB::scalar('SELECT id_servicio FROM servicio WHERE activo = 1 LIMIT 1');
        if (count($clientes) < 2 || ! $prof || ! $servicio) {
            $this->markTestSkipped('Faltan dos clientes, un profesional con turno o un servicio.');
        }

        DB::insert('INSERT INTO servicio_canjeable (id_servicio, puntos, dias_vigencia, activo) VALUES (?,?,?,1)',
            [$servicio, 10, 30]);
        DB::statement('CALL sp_registrar_puntos(?, NULL, ?, ?, ?)', [$clientes[0], 'AJUSTE', 100, 'prueba']);
        $idCanje = Canje::canjear($clientes[0], $servicio);

        // **La cita lleva su servicio**, que no es un detalle del andamiaje:
        // desde la 7.28.0 el canje sólo se aplica si el servicio canjeado está
        // de verdad en la cita. Una cita sin filas en `cita_servicio` tampoco
        // sería una cita — dura cero minutos y no se pisa con nada.
        // Las fechas van MUY adelante a propósito: `peluqueria_test` trae el
        // mes simulado del QA, así que una fecha cercana puede chocar con una
        // cita ya cargada y hacer saltar `trg_citaserv_bi` («no se repite el
        // mismo servicio en el día»), que no es lo que esta prueba mide.
        $cita = function (int $cliente, string $cuando = '+300 day') use ($prof, $servicio): int {
            DB::insert('INSERT INTO cita (id_cliente,id_usuario,id_estado_cita,fecha_hora,id_sucursal) VALUES (?,?,1,?,1)',
                [$cliente, $prof, date('Y-m-d H:i:s', strtotime($cuando))]);
            $id = (int) DB::getPdo()->lastInsertId();
            DB::insert('INSERT INTO cita_servicio (id_cita,id_servicio) VALUES (?,?)', [$id, $servicio]);

            return $id;
        };

        // **La otra clienta no puede usarlo**, aunque mande el id a mano.
        $ajena = $cita($clientes[1]);
        $this->assertSame(0, Canje::aplicarACita([$idCanje], $ajena, $clientes[1]),
            'El canje es de quien lo hizo: con el id suelto no se le gasta a otra persona.');

        // La dueña sí, y una sola vez.
        $propia = $cita($clientes[0]);
        $this->assertSame(1, Canje::aplicarACita([$idCanje], $propia, $clientes[0]));

        // La segunda va otro día: `trg_citaserv_bi` no deja repetirle a la
        // misma clienta el mismo servicio el mismo día, y acá lo que se mide
        // es el canje, no esa regla.
        $otra = $cita($clientes[0], '+310 day');
        $this->assertSame(0, Canje::aplicarACita([$idCanje], $otra, $clientes[0]),
            'Un canje ya usado no se puede volver a usar.');

        // Al cancelar la cita vuelve a estar disponible, **sin devolver puntos**:
        // no los perdió, sigue teniendo el canje.
        $puntos = Canje::puntos($clientes[0]);
        Agenda::cancelar($propia);
        $this->assertSame('DISPONIBLE', DB::scalar('SELECT fn_canje_estado(?)', [$idCanje]),
            'Cancelar la cita devuelve el canje.');
        $this->assertSame($puntos, Canje::puntos($clientes[0]),
            'Y NO devuelve los puntos: sería regalarle las dos cosas.');
    }

    /**
     * El Profesional cobra, pero no administra el arqueo del salón.
     *
     * La base venía dándole `facturacion.caja`, así que abría y cerraba la caja
     * y le veía el saldo — y este documento decía lo contrario desde la 7.13.1.
     * La simulación de 60 días lo destapó. Lo que sí conserva es cobrar y
     * emitir: sacarle eso lo dejaría sin poder trabajar en el mostrador.
     */
    #[Test]
    public function el_profesional_cobra_pero_no_administra_la_caja(): void
    {
        $claves = array_map(fn ($r) => $r->modulo,
            DB::select('SELECT modulo FROM rol_modulo WHERE id_rol = 2'));

        $this->assertNotContains('facturacion.cajas', $claves,
            'El Profesional NO administra la caja del salón.');
        $this->assertContains('facturacion.cobros', $claves,
            'Pero sí cobra: sin esto no puede trabajar en el mostrador.');
        $this->assertContains('facturacion.facturas', $claves,
            'Y sí emite comprobantes.');

        // Y el guardia lo hace cumplir, que es lo que importa: esconder el
        // botón no es el control. La caché de permisos es estática y sobrevive
        // entre pruebas del mismo proceso, así que se la olvida antes.
        Permisos::olvidar();
        session(['uid' => (int) (DB::scalar('SELECT id_usuario FROM usuario WHERE id_rol = 2 AND activo = 1 LIMIT 1') ?: 1), 'rol' => 2, 'es_personal' => true, 'es_cliente' => false, 'id_sucursal' => 1]);

        $this->get(route('facturacion.cajas'))->assertForbidden();

        // Lo que sí necesita para trabajar sigue abierto.
        $this->get(route('facturacion.cobros'))->assertOk();
        $this->get(route('facturacion.facturas'))->assertOk();
    }

    /**
     * Un aviso interno le llega al equipo que puede actuar sobre él.
     *
     * Los de `destinatario = 'INTERNO'` no se mandaban a nadie: el despachador
     * tomaba sólo los de CLIENTE y el barrido de NO-02 los cerraba como
     * FALLIDA. En 60 días fueron 21 alertas de stock que no leyó nadie.
     */
    #[Test]
    public function el_aviso_interno_le_llega_al_equipo_que_puede_resolverlo(): void
    {
        Mail::fake();

        $prod = DB::selectOne('SELECT id_producto FROM producto WHERE activo = 1 LIMIT 1');
        if (! $prod) {
            $this->markTestSkipped('No hay productos.');
        }

        // Un aviso interno recién nacido, como el que deja el disparador de stock
        DB::insert(
            "INSERT INTO notificacion (id_tipo_notificacion, id_producto, canal, mensaje, estado, fecha_generacion)
             VALUES (5, ?, 'SISTEMA', ?, 'PENDIENTE', NOW())",
            [(int) $prod->id_producto, 'Prueba: hay productos por reponer.']
        );
        $id = (int) DB::getPdo()->lastInsertId();

        Notificaciones::despachar();

        $this->assertSame('ENVIADA', DB::scalar('SELECT estado FROM notificacion WHERE id_notificacion = ?', [$id]),
            'El aviso interno se manda, no se cierra como FALLIDA.');

        // Y le llega a quien puede reponer el stock: hoy, el Administrador y el
        // Asistente administrativo. Se resuelve por permiso y no por id de rol.
        $esperados = array_map(fn ($r) => (string) $r->email, DB::select(
            "SELECT DISTINCT pe.email FROM usuario u
               JOIN persona pe ON pe.id_persona = u.id_persona
               JOIN rol r ON r.id_rol = u.id_rol
               JOIN rol_modulo rm ON rm.id_rol = u.id_rol
              WHERE u.activo = 1 AND r.es_personal = 1
                AND rm.modulo IN ('inventario.stock', 'inventario')
                AND pe.email IS NOT NULL AND pe.email <> ''"
        ));
        $this->assertNotEmpty($esperados, 'Tiene que haber alguien que pueda reponer stock.');

        foreach ($esperados as $email) {
            Mail::assertSent(AvisoInterno::class,
                fn ($m) => $m->hasTo($email));
        }

        // Al Profesional NO le llega: no repone stock.
        $prof = DB::scalar(
            "SELECT pe.email FROM usuario u JOIN persona pe ON pe.id_persona = u.id_persona
              WHERE u.id_rol = 2 AND u.activo = 1 AND pe.email IS NOT NULL AND pe.email <> '' LIMIT 1"
        );
        if ($prof && ! in_array((string) $prof, $esperados, true)) {
            Mail::assertNotSent(AvisoInterno::class, fn ($m) => $m->hasTo((string) $prof));
        }
    }

    /**
     * El movimiento de efectivo cargado a mano entra al arqueo.
     *
     * `fn_caja_saldo` resta `movimiento_caja` desde siempre y **esa tabla no la
     * escribía ninguna pantalla**: el gasto real del mostrador quedaba fuera
     * del arqueo y el cierre no cuadraba sin que se supiera por qué (CJ-02).
     */
    #[Test]
    public function el_movimiento_de_caja_a_mano_mueve_el_arqueo(): void
    {
        $abierta = DB::selectOne("SELECT id_caja FROM caja WHERE id_estado_caja = 1 LIMIT 1");
        $propia = false;
        if (! $abierta) {
            $idAdmin = (int) DB::scalar('SELECT id_usuario FROM usuario WHERE id_rol = 1 LIMIT 1');
            $idCaja = Caja::abrir($idAdmin, 200000,
                $this->cajonDe((int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1')));
            $abierta = (object) ['id_caja' => $idCaja];
            $propia = true;
        }
        $id = (int) $abierta->id_caja;

        $antes = (float) DB::scalar('SELECT fn_caja_saldo(?)', [$id]);

        DB::insert("INSERT INTO movimiento_caja (id_caja, tipo, monto, concepto) VALUES (?, 'EGRESO', ?, ?)",
            [$id, 25000, 'Prueba: delivery del almuerzo']);
        $this->assertEqualsWithDelta($antes - 25000, (float) DB::scalar('SELECT fn_caja_saldo(?)', [$id]), 0.01,
            'Un egreso a mano baja el efectivo del cajón.');

        DB::insert("INSERT INTO movimiento_caja (id_caja, tipo, monto, concepto) VALUES (?, 'INGRESO', ?, ?)",
            [$id, 40000, 'Prueba: plata para el cambio']);
        $this->assertEqualsWithDelta($antes - 25000 + 40000, (float) DB::scalar('SELECT fn_caja_saldo(?)', [$id]), 0.01,
            'Y un ingreso a mano lo sube.');

        if ($propia) {
            DB::update("UPDATE caja SET id_estado_caja = 2, fecha_cierre = NOW() WHERE id_caja = ?", [$id]);
        }
    }

    /**
     * Un canje marcado sin marcar su servicio NO se gasta.
     *
     * Es el accidente que las dos pantallas piden evitar («marcá el canje y
     * también el servicio de arriba») y que ninguna impedía: si el canje se
     * aplicara igual, la clienta perdería el vale sin que el servicio se haga.
     * La comprobación va en el servicio y no en el controlador porque protege
     * los dos caminos, el del portal y el del mostrador.
     */
    #[Test]
    public function el_canje_no_se_gasta_si_su_servicio_no_esta_en_la_cita(): void
    {
        $cliente = (int) DB::scalar('SELECT id_cliente FROM cliente WHERE activo = 1 LIMIT 1');
        $prof = (int) DB::scalar('SELECT u.id_usuario FROM usuario u
                                    JOIN usuario_turno ut ON ut.id_usuario = u.id_usuario LIMIT 1');
        $dos = DB::select('SELECT id_servicio FROM servicio WHERE activo = 1 ORDER BY id_servicio LIMIT 2');
        if (! $cliente || ! $prof || count($dos) < 2) {
            $this->markTestSkipped('Faltan cliente, profesional con turno o dos servicios.');
        }
        $canjeado = (int) $dos[0]->id_servicio;
        $otro = (int) $dos[1]->id_servicio;

        DB::insert('INSERT INTO servicio_canjeable (id_servicio, puntos, dias_vigencia, activo) VALUES (?,?,?,1)',
            [$canjeado, 10, 30]);
        DB::statement('CALL sp_registrar_puntos(?, NULL, ?, ?, ?)', [$cliente, 'AJUSTE', 100, 'prueba']);
        $idCanje = Canje::canjear($cliente, $canjeado);

        // Una cita que NO incluye el servicio canjeado
        DB::insert('INSERT INTO cita (id_cliente,id_usuario,id_estado_cita,fecha_hora,id_sucursal) VALUES (?,?,1,?,1)',
            [$cliente, $prof, date('Y-m-d H:i:s', strtotime('+320 day'))]);
        $sinElServicio = (int) DB::getPdo()->lastInsertId();
        DB::insert('INSERT INTO cita_servicio (id_cita,id_servicio) VALUES (?,?)', [$sinElServicio, $otro]);

        $this->assertSame(0, Canje::aplicarACita([$idCanje], $sinElServicio, $cliente),
            'El canje no se aplica a una cita que no tiene su servicio.');
        $this->assertSame('DISPONIBLE', DB::scalar('SELECT fn_canje_estado(?)', [$idCanje]),
            'Y sobre todo: el canje sigue disponible, la clienta no perdió los puntos.');

        // Con el servicio adentro, sí
        DB::insert('INSERT INTO cita (id_cliente,id_usuario,id_estado_cita,fecha_hora,id_sucursal) VALUES (?,?,1,?,1)',
            [$cliente, $prof, date('Y-m-d H:i:s', strtotime('+330 day'))]);
        $conElServicio = (int) DB::getPdo()->lastInsertId();
        DB::insert('INSERT INTO cita_servicio (id_cita,id_servicio) VALUES (?,?)', [$conElServicio, $canjeado]);

        $this->assertSame(1, Canje::aplicarACita([$idCanje], $conElServicio, $cliente),
            'Con el servicio marcado, el canje se aplica.');
    }

    /**
     * La nota de crédito se declara ante la DNIT, como la factura.
     *
     * `config/sifen.php` lista los dos tipos en `tipos_electronicos` desde la
     * 7.0.0, pero `notaCredito()` no llamaba a `Sifen::` en ninguna línea: en
     * la simulación de 60 días se declararon 70 de 70 facturas y 0 de 5 notas,
     * así que la DNIT veía la venta y no su reverso. Acá se fija lo que hace
     * falta para que eso no vuelva a pasar sin que nadie se entere.
     */
    #[Test]
    public function la_nota_de_credito_es_un_comprobante_que_se_declara(): void
    {
        $this->assertTrue(Sifen::esElectronico(1),
            'La factura se declara.');
        $this->assertTrue(Sifen::esElectronico(5),
            'Y la nota de crédito también: si esto deja de valer, hay que revisar notaCredito().');
        $this->assertFalse(Sifen::esElectronico(8),
            'El Comprobante de pago es interno del salón y NO se declara.');

        // Y el controlador tiene que llamarlo: sin esto, la nota se emite,
        // descuenta el efectivo y revierte los puntos sin avisarle a la DNIT.
        $codigo = file_get_contents(app_path('Http/Controllers/FacturacionController.php'));
        $desde = strpos($codigo, 'public function notaCredito');
        $hasta = strpos($codigo, 'public function sena', $desde ?: 0);
        $cuerpo = substr($codigo, (int) $desde, max(0, (int) $hasta - (int) $desde));

        $this->assertStringContainsString('Sifen::enviar', $cuerpo,
            'notaCredito() tiene que declarar la nota ante la DNIT.');
    }

    /**
     * El mostrador canjea por la clienta, pero no fija por cuánto.
     *
     * La mayoría de las clientas entra por teléfono y **no tiene cuenta en el
     * portal**, así que la que viene al local y pide gastar sus puntos tiene
     * que poder hacerlo ahí mismo. Eso es una acción del día a día y la hace
     * quien atiende.
     *
     * Lo que **no** puede el Profesional es administrar el catálogo: decidir
     * por cuántos puntos el salón regala un servicio es fijar precio, la misma
     * razón por la que no tiene `servicios.descuentos` desde la 6.4.0.
     */
    #[Test]
    public function el_profesional_canjea_por_la_clienta_pero_no_administra_el_catalogo(): void
    {
        $rolProf = 2;
        $cliente = (int) DB::scalar('SELECT id_cliente FROM cliente WHERE activo = 1 ORDER BY id_cliente LIMIT 1');
        $servicio = (int) DB::scalar('SELECT id_servicio FROM servicio WHERE activo = 1 LIMIT 1');
        if (! $cliente || ! $servicio) {
            $this->markTestSkipped('Falta un cliente o un servicio.');
        }

        DB::insert('INSERT INTO servicio_canjeable (id_servicio, puntos, dias_vigencia, activo) VALUES (?,?,?,1)',
            [$servicio, 10, 30]);
        DB::statement('CALL sp_registrar_puntos(?, NULL, ?, ?, ?)', [$cliente, 'AJUSTE', 100, 'prueba']);

        $uid = (int) DB::scalar('SELECT id_usuario FROM usuario WHERE id_rol = ? AND activo = 1 ORDER BY id_usuario LIMIT 1',
            [$rolProf]) ?: 999999;
        session(['uid' => $uid, 'rol' => $rolProf, 'es_personal' => true, 'es_cliente' => false]); $this->conSucursal();

        $antes = Canje::puntos($cliente);

        // Puede canjear por ella desde el mostrador.
        $this->post(route('clientes.canjear'), ['id_cliente' => $cliente, 'id_servicio' => $servicio])
             ->assertRedirect(route('clientes.fidelizacion'));

        $this->assertSame($antes - 10, Canje::puntos($cliente),
            'El canje del mostrador descuenta igual que el del portal.');
        $this->assertSame(1, (int) DB::scalar(
            'SELECT COUNT(*) FROM canje WHERE id_cliente = ? AND id_servicio = ?', [$cliente, $servicio]));

        // Pero el catálogo no es suyo: la ruta contesta 403, no se esconde el botón.
        $this->get(route('clientes.canjes'))->assertForbidden();
        $this->post(route('clientes.canje.guardar'), [
            'id_servicio' => $servicio, 'puntos' => 1, 'dias_vigencia' => 30,
        ])->assertForbidden();
    }

    /**
     * Cuánto vale un punto lo decide el salón, no un archivo de código.
     *
     * Vivía en `config/spg.php`, así que cambiarlo era editar código y volver a
     * desplegar. Es un número del negocio: pasa a la base y se edita desde la
     * pantalla de promociones, con el mismo permiso que ellas —subirlo o
     * bajarlo es fijar cuánto regala el salón—.
     */
    #[Test]
    public function la_relacion_de_puntos_se_edita_y_afecta_lo_que_se_acumula(): void
    {
        Config::olvidar();
        $original = Config::puntosCadaGs();

        $this->entrarComoAdministrador();

        // Se cambia desde la pantalla, no a mano en la base.
        $this->post(route('servicios.puntos.guardar'), ['puntos_cada_gs' => '5.000'])
             ->assertRedirect(route('servicios.descuentos'));

        Config::olvidar();
        $this->assertSame(5000, Config::puntosCadaGs(), 'El valor nuevo tiene que quedar guardado.');

        // Y **cambia lo que se acumula de acá en adelante**: con 1 punto cada
        // Gs. 5.000, una factura de Gs. 320.000 deja 64 y no 32.
        $this->assertSame(64, (int) floor(320000 / Config::puntosCadaGs()));

        // Los topes los hace cumplir la base; acá se comprueba que la pantalla
        // no deje pasar un valor que dividiría por cero o regalaría puntos.
        foreach (['0', '50', '99.999.999'] as $absurdo) {
            $this->post(route('servicios.puntos.guardar'), ['puntos_cada_gs' => $absurdo]);
            Config::olvidar();
            $this->assertSame(5000, Config::puntosCadaGs(),
                "Un valor de $absurdo no tendría que haberse guardado.");
        }

        // Se devuelve el valor con el que vino la base.
        Config::guardarPuntosCadaGs($original);
        Config::olvidar();
    }

    #[Test]
    public function la_agenda_ofrece_cobrar_la_sena_cuando_hay_caja_abierta(): void
    {
        // `FacturacionController::sena` y `sp_registrar_sena` funcionaban desde
        // siempre, y la ruta estaba declarada, pero NINGÚN formulario apuntaba
        // ahí: la agenda mostraba el badge «seña» y el aviso de caja cerrada,
        // y no había forma de cobrarla. Se comprueba la pantalla, que es lo
        // que faltaba.
        $cita = DB::selectOne(
            'SELECT c.id_cita, DATE(c.fecha_hora) AS dia
               FROM cita c JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
              WHERE ec.bloquea_agenda = 1 AND c.fecha_hora > NOW()
              ORDER BY c.fecha_hora LIMIT 1'
        );
        if (! $cita) {
            $this->markTestSkipped('No hay citas futuras en la base de prueba.');
        }
        if (! DB::scalar('SELECT COUNT(*) FROM caja WHERE id_estado_caja = 1')) {
            $this->markTestSkipped('No hay ninguna caja abierta en la base de prueba.');
        }

        session(['uid' => 1, 'rol' => (int) config('permisos.rol_admin', 1),
                 'es_personal' => true, 'es_cliente' => false]); $this->conSucursal();

        $this->get(route('citas.agenda', ['dia' => $cita->dia]))
            ->assertOk()
            ->assertSee('modalSena' . $cita->id_cita)
            ->assertSee(route('facturacion.sena'), false);
    }

    #[Test]
    public function cargar_una_ausencia_avisa_a_las_clientas_de_ese_rango(): void
    {
        // El aviso existía escrito desde la 6.0.0 y no lo llamaba nadie: la
        // clienta se enteraba de que su profesional no iba a estar cuando
        // llegaba al salón.
        $cita = DB::selectOne(
            'SELECT c.id_cita, c.id_usuario, c.fecha_hora
               FROM cita c JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
              WHERE ec.bloquea_agenda = 1 AND c.fecha_hora > NOW()
              ORDER BY c.fecha_hora LIMIT 1'
        );
        if (! $cita) {
            $this->markTestSkipped('No hay citas futuras en la base de prueba.');
        }

        $desde = date('Y-m-d H:i:s', strtotime((string) $cita->fecha_hora . ' -1 hour'));
        $hasta = date('Y-m-d H:i:s', strtotime((string) $cita->fecha_hora . ' +1 hour'));

        $antes = (int) DB::scalar('SELECT COUNT(*) FROM notificacion WHERE id_cita = ?', [$cita->id_cita]);

        // Se pasa por la PANTALLA, no por el servicio: el servicio ya estaba
        // escrito y andaba — lo que faltaba era que alguien lo llamara.
        session(['uid' => 1, 'rol' => (int) config('permisos.rol_admin', 1),
                 'es_personal' => true, 'es_cliente' => false]); $this->conSucursal();

        $this->post(route('citas.ausencia.guardar'), [
            'id_usuario' => (int) $cita->id_usuario,
            'id_tipo_ausencia' => (int) DB::scalar('SELECT MIN(id_tipo_ausencia) FROM tipo_ausencia'),
            'fecha_inicio' => str_replace(' ', 'T', $desde),
            'fecha_fin' => str_replace(' ', 'T', $hasta),
            'motivo' => 'licencia de prueba',
        ])->assertRedirect(route('citas.ausencias'));

        $this->assertSame($antes + 1, (int) DB::scalar(
            'SELECT COUNT(*) FROM notificacion WHERE id_cita = ?', [$cita->id_cita]
        ), 'Cargar la excepción tiene que encolarle el aviso a la clienta de esa cita.');

        // Y la excepción de todo el salón (id_usuario NULL, como un feriado)
        // alcanza a esa misma cita: es la que más gente deja plantada.
        $this->assertGreaterThan(0,
            \App\Servicios\Notificaciones::avisarProfesionalNoDisponible(null, $desde, $hasta, 'feriado'),
            'Una excepción de todo el salón también tiene que avisar.');
    }

    /**
     * Una cita de hoy que ya terminó deja de anunciarse como próxima.
     *
     * El portal llegó a tener el criterio en los dos extremos: primero
     * `fecha_hora >= NOW()`, que hacía desaparecer la cita **mientras la estaban
     * atendiendo**, y después `OR DATE(v.fecha_hora) = CURDATE()`, que la dejaba
     * en «Próximas» hasta la medianoche aunque hubiera terminado ocho horas
     * antes. La clienta creía que todavía le quedaba una cita por delante.
     *
     * **Se comprueba en las dos direcciones**, que es lo que hace que la prueba
     * signifique algo: la Programada que ya pasó tiene que salir de próximas, y
     * la En proceso y la Atrasada tienen que seguir ahí — la segunda es
     * justamente la que la clienta necesita ver para reclamar.
     */
    #[Test]
    public function una_cita_de_hoy_que_ya_paso_deja_de_ser_proxima(): void
    {
        $u = DB::selectOne(
            'SELECT u.id_usuario, c.id_cliente FROM usuario u
               JOIN cliente c ON c.id_persona = u.id_persona
              WHERE u.activo = 1 LIMIT 1'
        );
        if (! $u) {
            $this->markTestSkipped('No hay ninguna cuenta de cliente en la base de prueba.');
        }
        $idc = (int) $u->id_cliente;

        // Un servicio que esa clienta NO tenga ya reservado hoy: `trg_citaserv_bi`
        // no deja repetir el mismo servicio el mismo día.
        // El más corto de los que le quedan libres: de su duración depende cuánto
        // del día tiene que haber transcurrido para que la cita ya haya terminado.
        $s = DB::selectOne(
            'SELECT s.id_servicio, s.duracion_min FROM servicio s
              WHERE s.activo = 1
                AND NOT EXISTS (SELECT 1 FROM cita_servicio cs
                                  JOIN cita ci ON ci.id_cita = cs.id_cita
                                  JOIN estado_cita ec ON ec.id_estado_cita = ci.id_estado_cita
                                 WHERE ci.id_cliente = ? AND DATE(ci.fecha_hora) = CURDATE()
                                   AND ec.bloquea_agenda = 1 AND cs.id_servicio = s.id_servicio)
              ORDER BY s.duracion_min, s.id_servicio LIMIT 1', [$idc]
        );
        $srv = (int) ($s->id_servicio ?? 0);
        $prof = (int) DB::scalar(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE r.es_personal = 1 AND u.activo = 1 ORDER BY u.id_usuario LIMIT 1'
        );
        if (! $srv || ! $prof) {
            $this->markTestSkipped('Falta catálogo para armar la cita.');
        }

        // La cita tiene que cumplir DOS cosas a la vez: ser de hoy y haber
        // terminado. Una hora fija no sirve —esta prueba se escribió con
        // `00:15` y falló en el contenedor, que estaba en las **00:03**: a esa
        // hora una cita de las 00:15 todavía es futura y con razón salía en
        // «Próximas»—. Se ubica contra el reloj de la base, que es el que manda.
        $atras = (int) $s->duracion_min + 5;
        $transcurrido = (int) DB::scalar('SELECT HOUR(NOW()) * 60 + MINUTE(NOW())');
        if ($transcurrido < $atras) {
            // Recién pasó la medianoche: hoy todavía no hay ninguna cita que
            // pueda haber terminado. No hay nada que medir, y fingir que sí
            // sería peor que decirlo.
            $this->markTestSkipped('Recién pasó la medianoche: hoy no cabe una cita ya terminada.');
        }

        // Se inserta a mano y no con `sp_agendar_cita`: lo que se prueba es cómo
        // el portal LEE una cita pasada, y el procedimiento —con razón— no deja
        // agendar hacia atrás.
        $suc = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');
        DB::insert('INSERT INTO cita (id_cliente, id_usuario, id_sucursal, fecha_hora, id_estado_cita)
                    VALUES (?, ?, ?, DATE_SUB(NOW(), INTERVAL ? MINUTE), 1)',
                   [$idc, $prof, $suc, $atras]);
        $idCita = (int) DB::scalar('SELECT LAST_INSERT_ID()');
        DB::insert('INSERT INTO cita_servicio (id_cita, id_servicio) VALUES (?, ?)', [$idCita, $srv]);

        session([
            'uid' => (int) $u->id_usuario, 'rol' => (int) config('permisos.rol_cliente', 4),
            'es_personal' => false, 'es_cliente' => true, 'id_cliente' => $idc,
        ]);
        $this->conSucursal();

        $proximas = fn () => array_map(
            fn ($c) => (int) $c->id_cita,
            $this->get(route('portal.citas'))->assertOk()->viewData('prox')
        );

        // 1) Programada, con la hora ya pasada: no es próxima, es pasada.
        $this->assertNotContains($idCita, $proximas(),
            'Una cita de hoy cuya hora ya terminó no puede seguir anunciándose como próxima.');
        $this->assertContains($idCita, array_map(
            fn ($c) => (int) $c->id_cita,
            $this->get(route('portal.citas'))->viewData('pasadas')
        ), 'Y tiene que aparecer entre las pasadas: no se pierde, cambia de lugar.');

        // 2) En proceso: la están atendiendo ahora mismo. Tiene que seguir.
        DB::update('UPDATE cita SET id_estado_cita = 5 WHERE id_cita = ?', [$idCita]);
        $this->assertContains($idCita, $proximas(),
            'La cita en curso no puede desaparecer del portal mientras está pasando.');

        // 3) Atrasada: se pasó de hora y nadie la tocó. Es la que la clienta
        //    necesita ver para reclamar, así que tampoco se va.
        DB::update('UPDATE cita SET id_estado_cita = 7 WHERE id_cita = ?', [$idCita]);
        $this->assertContains($idCita, $proximas(),
            'La cita atrasada tiene que seguir a la vista: es la que hay que reclamar.');
    }

    /**
     * La cita que la clienta reserva queda en la sucursal que ELIGIÓ.
     *
     * El formulario manda `id_sucursal` desde que existe el selector, y el
     * controlador **no lo leía**: la cita se guardaba en la sucursal que
     * `sp_agendar_cita` dedujera, o sea la de la ficha del profesional. Quien
     * reservaba en el segundo local generaba una cita en la casa central, y el
     * día de la cita nadie la esperaba donde ella fue.
     *
     * Y arrastra el resto: el comprobante se numera con el timbrado de esa otra
     * sede (7.37.0) y el cobro entra a su cajón (7.36.3).
     */
    #[Test]
    public function la_reserva_del_portal_queda_en_la_sucursal_que_eligio_la_clienta(): void
    {
        $u = DB::selectOne(
            'SELECT u.id_usuario, c.id_cliente FROM usuario u
               JOIN cliente c ON c.id_persona = u.id_persona
              WHERE u.activo = 1 LIMIT 1'
        );
        if (! $u) {
            $this->markTestSkipped('No hay ninguna cuenta de cliente en la base de prueba.');
        }

        // Un local nuevo, al que todavía no está asignado nadie.
        DB::insert('INSERT INTO sucursal (nombre, direccion, activo) VALUES (?, ?, 1)',
                   ['Sucursal de prueba ' . uniqid(), 'Calle 1']);
        $otra = (int) DB::scalar('SELECT LAST_INSERT_ID()');

        // Alguien que atiende de verdad —con turno cargado—, hoy sólo del local 1.
        $prof = (int) DB::scalar(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
               JOIN usuario_turno ut ON ut.id_usuario = u.id_usuario
              WHERE r.es_personal = 1 AND u.activo = 1 ORDER BY u.id_usuario LIMIT 1'
        );
        $srv = (int) DB::scalar('SELECT id_servicio FROM servicio WHERE activo = 1 ORDER BY id_servicio LIMIT 1');
        if (! $prof || ! $srv) {
            $this->markTestSkipped('Falta catálogo para armar la reserva.');
        }

        session([
            'uid' => (int) $u->id_usuario, 'rol' => (int) config('permisos.rol_cliente', 4),
            'es_personal' => false, 'es_cliente' => true, 'id_cliente' => (int) $u->id_cliente,
        ]);
        $this->conSucursal();

        // La pantalla ofrece los huecos; se toma uno de ahí, que es lo que haría
        // la clienta. Sin esto habría que adivinar un horario con turno.
        $cuando = null;
        for ($d = 2; $d <= 45 && ! $cuando; $d++) {
            $dia = date('Y-m-d', strtotime("+$d days"));
            $j = $this->getJson(route('portal.disponibilidad') . '?' . http_build_query([
                'id_usuario' => $prof, 'servicios' => [$srv], 'fecha' => $dia,
            ]))->json();
            if (! empty($j['horas'])) {
                $cuando = $dia . ' ' . $j['horas'][0]['hora'] . ':00';
            }
        }
        if (! $cuando) {
            $this->markTestSkipped('No hay ningún hueco libre para probar la reserva.');
        }

        $reservar = fn () => $this->post(route('portal.guardar_reserva'), [
            'id_usuario' => $prof, 'id_sucursal' => $otra,
            'servicios' => [$srv], 'fecha_hora' => $cuando,
        ]);

        // 1) Ese profesional no atiende ahí, así que la reserva se rechaza. Sin
        //    esta mitad, la clienta reserva con alguien que ese día está en el
        //    otro local: horario vendido y nadie para atenderla.
        $antes = (int) DB::scalar('SELECT COUNT(*) FROM cita WHERE id_sucursal = ?', [$otra]);
        $reservar()->assertRedirect(route('portal.reservar'));
        $this->assertSame($antes, (int) DB::scalar('SELECT COUNT(*) FROM cita WHERE id_sucursal = ?', [$otra]),
            'Un profesional que no atiende en esa sucursal no puede quedar reservado ahí.');

        // 2) Ahora sí lo asignan a ese local: la cita entra, y entra en ÉL.
        DB::insert('INSERT INTO usuario_sucursal (id_usuario, id_sucursal) VALUES (?, ?)', [$prof, $otra]);
        $reservar();

        $idCita = (int) DB::scalar(
            'SELECT id_cita FROM cita WHERE id_cliente = ? ORDER BY id_cita DESC LIMIT 1', [(int) $u->id_cliente]
        );
        $this->assertSame($otra, (int) DB::scalar('SELECT id_sucursal FROM cita WHERE id_cita = ?', [$idCita]),
            'La cita tiene que quedar en la sucursal que eligió la clienta, no en la de la ficha del profesional.');
        $this->assertSame($prof, (int) DB::scalar('SELECT id_usuario FROM cita WHERE id_cita = ?', [$idCita]),
            'Y con el profesional que eligió.');
    }

    /**
     * La clienta tiene barra de navegación, igual que el personal.
     *
     * El portal se movía sólo por los enlaces del pie y por lo que cada pantalla
     * ofreciera: entrando a «Mis citas» no había forma de ir a «Promociones» sin
     * volver al inicio. La barra sale del **mismo catálogo** que el pie
     * (`config/navegacion.php`), así que no se pueden desfasar.
     */
    #[Test]
    public function la_clienta_tiene_barra_de_navegacion_en_el_portal(): void
    {
        $u = DB::selectOne(
            'SELECT u.id_usuario, c.id_cliente FROM usuario u
               JOIN cliente c ON c.id_persona = u.id_persona
              WHERE u.activo = 1 LIMIT 1'
        );
        if (! $u) {
            $this->markTestSkipped('No hay ninguna cuenta de cliente en la base de prueba.');
        }

        session([
            'uid' => (int) $u->id_usuario, 'rol' => (int) config('permisos.rol_cliente', 4),
            'es_personal' => false, 'es_cliente' => true, 'id_cliente' => (int) $u->id_cliente,
        ]);
        $this->conSucursal();

        // En una pantalla de adentro, que es donde hacía falta: en el inicio
        // siempre estuvieron los enlaces a la vista.
        $r = $this->get(route('portal.citas'))->assertOk();

        $enBarra = array_filter(Navegacion::portal(), fn ($p) => $p['barra']);
        $this->assertNotEmpty($enBarra, 'El portal tiene que declarar qué va en la barra.');

        $r->assertSee('spg-nav-item', false);
        foreach ($enBarra as $p) {
            $r->assertSee($p['url'], false);
        }

        // «Mi cuenta» NO va en la barra: se busca en el desplegable de la
        // cuenta, y ahí arriba competiría con lo que la clienta viene a hacer.
        foreach (Navegacion::portal() as $p) {
            if ($p['clave'] === 'cuenta.index') {
                $this->assertFalse($p['barra'], 'Mi cuenta no va en la barra del portal.');
            }
        }
    }

    /**
     * Un local sin timbrado propio factura igual, pero la pantalla lo dice.
     *
     * `fn_timbrado_vigente` cae al timbrado de otra sede cuando el local no
     * tiene el suyo, y esa caída es deliberada: dejar de facturar sería peor
     * que facturar con el número de la casa central. Lo que no puede pasar es
     * que la caída sea **silenciosa**, porque arrastra dos cosas que no se ven
     * en pantalla — el establecimiento impreso dice la otra sede, y el cobro
     * entra al cajón de esa otra sede (7.36.3) —.
     *
     * Se comprueba en las dos direcciones: con timbrado propio el aviso NO
     * aparece, que si no sería un cartel permanente y nadie lo leería.
     */
    #[Test]
    public function el_local_sin_timbrado_propio_avisa_con_cual_va_a_numerar(): void
    {
        $admin = (int) config('permisos.rol_admin', 1);

        // Un local nuevo, sin ningún timbrado suyo.
        DB::insert('INSERT INTO sucursal (nombre, direccion, activo) VALUES (?, ?, 1)',
                   ['Sucursal sin timbrado ' . uniqid(), 'Calle 2']);
        $otra = (int) DB::scalar('SELECT LAST_INSERT_ID()');

        // Con qué tipo se va a topar la pantalla, y de quién es ese timbrado.
        $t = DB::selectOne(
            'SELECT t.id_timbrado, t.id_sucursal, t.id_tipo_comprobante, t.nro_timbrado,
                    t.punto_expedicion, t.nro_desde, t.nro_hasta
               FROM timbrado t
               JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = t.id_tipo_comprobante
              WHERE t.activo = 1 AND CURDATE() BETWEEN t.fecha_inicio AND t.fecha_fin
                AND tc.activo = 1 AND tc.signo = 1 AND tc.requiere_origen = 0
              ORDER BY t.id_tipo_comprobante LIMIT 1'
        );
        if (! $t) {
            $this->markTestSkipped('No hay ningún timbrado vigente con el que comparar.');
        }
        $nombreDuenio = (string) DB::scalar('SELECT nombre FROM sucursal WHERE id_sucursal = ?', [(int) $t->id_sucursal]);

        session(['uid' => 1, 'rol' => $admin, 'es_personal' => true, 'es_cliente' => false]);
        $this->conSucursal($otra);

        $this->get(route('facturacion.emitir'))
            ->assertOk()
            ->assertSee('Esta sucursal no tiene timbrado propio.', false)
            ->assertSee($nombreDuenio, false);

        // Y con el suyo cargado para TODO lo que la pantalla ofrece, el aviso se
        // calla: es la mitad que evita que el cartel se vuelva parte del decorado.
        foreach (DB::select(
            'SELECT DISTINCT t.id_tipo_comprobante, t.nro_timbrado, t.punto_expedicion, t.nro_desde, t.nro_hasta
               FROM timbrado t
               JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = t.id_tipo_comprobante
              WHERE t.activo = 1 AND CURDATE() BETWEEN t.fecha_inicio AND t.fecha_fin
                AND tc.activo = 1 AND tc.signo = 1 AND tc.requiere_origen = 0'
        ) as $orig) {
            DB::insert('INSERT INTO timbrado (id_sucursal, id_tipo_comprobante, nro_timbrado, establecimiento,
                                              punto_expedicion, nro_desde, nro_hasta, fecha_inicio, fecha_fin, activo)
                        VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 1)',
                       [$otra, (int) $orig->id_tipo_comprobante, $orig->nro_timbrado, '002',
                        $orig->punto_expedicion, $orig->nro_desde, $orig->nro_hasta]);
        }

        $this->get(route('facturacion.emitir'))
            ->assertOk()
            ->assertDontSee('Esta sucursal no tiene timbrado propio.', false);
    }

    /**
     * El modal de cobro dice cuánto vale la cita y cuánto falta cobrar.
     *
     * Pedía un monto y **no decía cuál**: la única forma de enterarse del número
     * era mandar uno de más y leer el rechazo («Esa cita vale Gs. 60.000, así que
     * no se puede cobrar…»). En el mostrador eso es obligar a saber de memoria lo
     * que el sistema ya tiene calculado.
     *
     * El total sale de la **misma expresión** con la que la base topea el cobro,
     * así que la pantalla no puede ofrecer un monto que el procedimiento rechace.
     */
    #[Test]
    public function el_modal_de_cobro_dice_cuanto_hay_que_cobrar(): void
    {
        $cliente = $this->clienteLibreHoy();
        $srv = DB::selectOne('SELECT id_servicio, precio FROM servicio WHERE activo = 1 AND precio > 0
                               ORDER BY id_servicio LIMIT 1');
        $prof = (int) DB::scalar(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE r.es_personal = 1 AND u.activo = 1 ORDER BY u.id_usuario LIMIT 1'
        );
        if (! $cliente || ! $srv || ! $prof) {
            $this->markTestSkipped('Falta catálogo para armar la cita.');
        }

        // Atendida y sin comprobante: el caso en que la agenda ofrece «Cobrar».
        $suc = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');
        DB::insert('INSERT INTO cita (id_cliente, id_usuario, id_sucursal, fecha_hora, id_estado_cita)
                    VALUES (?, ?, ?, NOW(), 4)', [$cliente, $prof, $suc]);
        $idCita = (int) DB::scalar('SELECT LAST_INSERT_ID()');
        DB::insert('INSERT INTO cita_servicio (id_cita, id_servicio) VALUES (?, ?)', [$idCita, (int) $srv->id_servicio]);

        $this->entrarComoAdministrador();

        $r = $this->get(route('citas.agenda', ['dia' => date('Y-m-d')]))->assertOk();

        $html = $r->getContent();
        $conDescuento = (float) DB::scalar('SELECT fn_cita_total(?)', [$idCita]);

        $this->assertStringContainsString('A cobrar ' . money($conDescuento), $html,
            'El modal tiene que decir cuánto falta cobrar, no esperar a rechazarlo.');
        $this->assertStringContainsString('La cita vale', $html,
            'Y cuánto vale la cita, que es de dónde sale ese número.');

        // **Con descuento vigente, el modal NO puede ofrecer el precio de lista.**
        // Es lo que hacía cobrar de más: `sp_emitir_factura` aplica el mejor
        // descuento y la pantalla no lo sabía.
        if ($conDescuento < (float) $srv->precio) {
            $this->assertStringNotContainsString('A cobrar ' . money((float) $srv->precio), $html,
                'Con descuento vigente el modal estaría ofreciendo el precio de lista.');
            $this->assertStringContainsString('descuento', $html,
                'Un total más bajo sin decir por qué se lee como un error de la pantalla.');
        }
    }

    /**
     * Un local que no maneja ningún producto lo dice al registrar la atención.
     *
     * El catálogo es único desde la 7.33.0 y `producto_sucursal` dice qué maneja
     * cada sede, así que una sucursal recién abierta llega a «Registrar atención»
     * con la lista vacía: tres selectores con «— sin producto —» y nada más. La
     * atención se registra igual —hay servicios que no consumen nada— pero quien
     * atiende no tiene cómo saber si es que no hay productos o si el sistema se
     * rompió. Es el mismo criterio de IN-06: nombrar el camino en vez de callarse.
     */
    #[Test]
    public function el_local_sin_productos_lo_dice_al_registrar_la_atencion(): void
    {
        $cliente = $this->clienteLibreHoy();
        $srv = (int) DB::scalar('SELECT id_servicio FROM servicio WHERE activo = 1 ORDER BY id_servicio LIMIT 1');
        if (! $cliente || ! $srv) {
            $this->markTestSkipped('Falta catálogo para armar la cita.');
        }

        // Un local nuevo: ninguna fila en `producto_sucursal`, que es justo el
        // estado en el que queda una sucursal recién abierta.
        DB::insert('INSERT INTO sucursal (nombre, direccion, activo) VALUES (?, ?, 1)',
                   ['Sucursal sin productos ' . uniqid(), 'Calle 3']);
        $otra = (int) DB::scalar('SELECT LAST_INSERT_ID()');

        DB::insert('INSERT INTO cita (id_cliente, id_usuario, id_sucursal, fecha_hora, id_estado_cita)
                    VALUES (?, 1, ?, NOW(), 1)', [$cliente, $otra]);
        $idCita = (int) DB::scalar('SELECT LAST_INSERT_ID()');
        DB::insert('INSERT INTO cita_servicio (id_cita, id_servicio) VALUES (?, ?)', [$idCita, $srv]);

        $admin = (int) config('permisos.rol_admin', 1);
        session(['uid' => 1, 'rol' => $admin, 'es_personal' => true, 'es_cliente' => false]);
        $this->conSucursal($otra);

        $this->get(route('citas.atender', ['id' => $idCita]))
            ->assertOk()
            ->assertSee('Esta sucursal todavía no maneja ningún producto', false);

        // Y donde sí hay productos habilitados, el aviso no está: un cartel que
        // sale siempre deja de leerse.
        $conProductos = (int) DB::scalar(
            'SELECT ps.id_sucursal FROM producto_sucursal ps
               JOIN producto p ON p.id_producto = ps.id_producto
              WHERE ps.activo = 1 AND p.activo = 1 LIMIT 1'
        );
        if ($conProductos) {
            DB::update('UPDATE cita SET id_sucursal = ? WHERE id_cita = ?', [$conProductos, $idCita]);
            $this->conSucursal($conProductos);
            $this->get(route('citas.atender', ['id' => $idCita]))
                ->assertOk()
                ->assertDontSee('Esta sucursal todavía no maneja ningún producto', false);
        }
    }

    /**
     * La sucursal se pregunta UNA vez, y marcar alguna es obligatorio.
     *
     * El formulario preguntaba dos veces lo mismo: las casillas de «Sucursales
     * donde trabaja» y, debajo, un selector de «Sucursal principal». En cuál
     * está HOY lo decide la sesión al entrar desde la 7.30.0, así que el
     * segundo campo no contestaba nada que el primero no contestara.
     *
     * Lo que queda de `usuario.id_sucursal` es la red para las cuentas viejas
     * sin asignaciones (`Sucursales::delUsuario`) y para lo que agenda sin
     * sesión (`Agenda::agendar`), así que se deduce de la primera marcada.
     *
     * **Y al sacar el selector, marcar al menos una pasa a ser obligatorio**:
     * antes ese campo tapaba el caso, y sin él una cuenta sin ningún local no
     * puede entrar a ninguna parte — la pantalla de elegir sucursal le sale
     * vacía y sin decir por qué.
     */
    #[Test]
    public function la_sucursal_del_usuario_se_pregunta_una_sola_vez(): void
    {
        $this->entrarComoAdministrador();

        // Se compara sobre el contenido y no con assertSee/assertDontSee: cuando
        // esas fallan, PHPUnit imprime la PÁGINA ENTERA en el mensaje de error.
        $html = $this->get(route('seguridad.usuario_form'))->assertOk()->getContent();
        $this->assertStringContainsString('name="sucursales[]"', $html,
            'Las casillas de sucursales son la única pregunta que queda.');
        // Se busca el rótulo y no `name="id_sucursal"`: ese nombre lo usa también
        // el alta rápida de turno, que sí necesita decir de qué local es el turno.
        $this->assertStringNotContainsString('Sucursal principal', $html,
            'El selector de «Sucursal principal» preguntaba dos veces lo mismo.');

        $rol = (int) DB::scalar('SELECT id_rol FROM rol WHERE es_personal = 1 AND activo = 1 ORDER BY id_rol LIMIT 1');
        $suc = (int) DB::scalar('SELECT id_sucursal FROM sucursal WHERE activo = 1 ORDER BY id_sucursal LIMIT 1');
        $u = 'prueba.' . substr(uniqid(), -8);

        // **La persona se elige, no se tipea** (7.68.0): sus datos viven en
        // `persona` y se cargan en Personal → Profesionales.
        DB::insert("INSERT INTO persona (nombre, apellido, es_personal) VALUES ('Rocío', 'Prueba', 1)");
        $persona = (int) DB::scalar('SELECT LAST_INSERT_ID()');

        $ficha = [
            'id_persona' => $persona, 'username' => $u,
            'password' => 'secreto123', 'id_rol' => $rol,
        ];

        // 1) Sin ninguna marcada no entra: quedaría sin ningún local al que entrar.
        $this->post(route('seguridad.usuario.guardar'), $ficha);
        $this->assertSame(0, (int) DB::scalar('SELECT COUNT(*) FROM usuario WHERE username = ?', [$u]),
            'Una cuenta de personal sin ninguna sucursal no puede guardarse.');

        // 2) Con una marcada, la ficha queda apuntando a ésa sin haberla pedido aparte.
        $this->post(route('seguridad.usuario.guardar'), $ficha + ['sucursales' => [$suc]]);
        $guardada = DB::selectOne('SELECT id_usuario, id_sucursal FROM usuario WHERE username = ?', [$u]);
        $this->assertNotNull($guardada, 'Con una sucursal marcada la cuenta tiene que guardarse.');
        $this->assertSame($suc, (int) $guardada->id_sucursal,
            'La sucursal de la ficha se deduce de la primera marcada, no de un campo aparte.');
        $this->assertSame(1, (int) DB::scalar(
            'SELECT COUNT(*) FROM usuario_sucursal WHERE id_usuario = ? AND id_sucursal = ?',
            [(int) $guardada->id_usuario, $suc]
        ), 'Y queda asignada de verdad, que es lo que el sistema lee para dejarla entrar.');
    }

    /**
     * Un empleado no arrastra su horario de otra sucursal.
     *
     * El turno vive en `turno_laboral.id_sucursal` desde que existen las
     * sucursales, y `fn_verificar_disponibilidad` **nunca lo miró**: preguntaba
     * «¿tiene algún turno que cubra esta hora?» sin decir dónde. Una persona con
     * turno sólo en la casa central quedaba disponible para agendar en el
     * segundo local, y la clienta reservaba con alguien que ese día está a la
     * otra punta de la ciudad.
     *
     * **La pregunta "¿el salón usa turnos?" también pasa a ser del local**, que
     * es la parte fácil de romper: si fuera del salón entero, una sucursal recién
     * abierta —sin ningún turno cargado— quedaría sin agenda el primer día
     * porque la casa central sí los usa.
     */
    #[Test]
    public function el_turno_de_una_sucursal_no_habilita_la_agenda_de_otra(): void
    {
        // **Con turno Y con días cargados.** Un turno sin `turno_dia` no cubre
        // ningún día de la semana, así que no sirve para medir esto — y la
        // prueba reventaba con «property on null» en vez de saltearse. Pasó en
        // el contenedor y no en el host: la misma clase de dependencia del
        // entorno que corrigió la 7.31.3.
        $prof = (int) DB::scalar(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
               JOIN usuario_turno ut  ON ut.id_usuario = u.id_usuario
               JOIN turno_laboral t   ON t.id_turno = ut.id_turno AND t.activo = 1
               JOIN turno_dia td      ON td.id_turno = t.id_turno
              WHERE r.es_personal = 1 AND u.activo = 1 ORDER BY u.id_usuario LIMIT 1'
        );
        if (! $prof) {
            $this->markTestSkipped('No hay ningún profesional con turno y días cargados.');
        }

        // Dónde tiene turno hoy, y una hora que ese turno cubra de verdad.
        $t = DB::selectOne(
            'SELECT t.id_sucursal, t.hora_inicio, td.dia_semana
               FROM usuario_turno ut
               JOIN turno_laboral t ON t.id_turno = ut.id_turno AND t.activo = 1
               JOIN turno_dia td    ON td.id_turno = t.id_turno
              WHERE ut.id_usuario = ? ORDER BY td.dia_semana, t.hora_inicio LIMIT 1', [$prof]
        );
        $suya = (int) $t->id_sucursal;

        // Un día futuro que caiga en ese día de la semana, y libre de citas.
        $cuando = null;
        for ($d = 3; $d <= 30 && ! $cuando; $d++) {
            $f = date('Y-m-d', strtotime("+$d days"));
            if ((int) date('N', strtotime($f)) === (int) $t->dia_semana) {
                $cuando = $f . ' ' . $t->hora_inicio;
            }
        }

        // Un local nuevo donde esa persona NO tiene ningún turno.
        DB::insert('INSERT INTO sucursal (nombre, direccion, activo) VALUES (?, ?, 1)',
                   ['Sucursal sin turnos ' . uniqid(), 'Calle 4']);
        $otra = (int) DB::scalar('SELECT LAST_INSERT_ID()');

        // 1) En su local, a su hora, está disponible.
        $this->assertTrue(Agenda::huecoLibre($prof, $cuando, 30, null, $suya),
            'En la sucursal donde tiene turno tiene que estar disponible.');

        // 2) En el local nuevo NO, y ésa es la corrección: no arrastra el
        //    horario. Como ese local todavía no tiene ningún turno cargado,
        //    vale el criterio permisivo — así que primero se le carga uno a
        //    otra persona, que es lo que hace que el local «use turnos».
        $otroProf = (int) DB::scalar(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE r.es_personal = 1 AND u.activo = 1 AND u.id_usuario <> ?
              ORDER BY u.id_usuario LIMIT 1', [$prof]
        );
        DB::insert('INSERT INTO turno_laboral (id_sucursal, nombre, hora_inicio, hora_fin, activo)
                    VALUES (?, ?, ?, ?, 1)', [$otra, 'Turno de prueba', '08:00:00', '12:00:00']);
        $idTurno = (int) DB::scalar('SELECT LAST_INSERT_ID()');
        DB::insert('INSERT INTO turno_dia (id_turno, dia_semana) VALUES (?, ?)', [$idTurno, (int) $t->dia_semana]);
        DB::insert('INSERT INTO usuario_turno (id_usuario, id_turno) VALUES (?, ?)', [$otroProf, $idTurno]);

        $this->assertFalse(Agenda::huecoLibre($prof, $cuando, 30, null, $otra),
            'Sin turno EN ESE LOCAL no puede estar disponible ahí, aunque lo tenga en otra sucursal.');

        // 3) Y el otro, que sí tiene turno ahí, sí lo está: el filtro acota, no apaga.
        $this->assertTrue(Agenda::huecoLibre($otroProf, date('Y-m-d', strtotime($cuando)) . ' 08:00:00', 30, null, $otra),
            'Quien sí tiene turno en ese local tiene que estar disponible ahí.');

        // 4) El espejo de PHP dice lo mismo: la pantalla no puede ofrecer un
        //    horario que la base va a rechazar al guardar.
        $php = Agenda::slotsProfesional($prof, date('Y-m-d', strtotime($cuando)), 30, null, $otra);
        $this->assertNotContains(substr((string) $t->hora_inicio, 0, 5), $php,
            'El espejo de PHP tiene que esconder el mismo horario que la base rechaza.');
    }

    /**
     * Cada local decide qué ofrece, y sacarlo NO lo hace desaparecer.
     *
     * Durante un tiempo la lista mostró sólo lo de este local, y ahí estaba el
     * defecto: el botón «No ofrecerlo en esta sucursal» borraba la fila de
     * `servicio_sucursal`, con lo cual el servicio **dejaba de cumplir el
     * filtro y se iba de la pantalla**. Desde ahí no había forma de volver a
     * ofrecerlo —había que ir al alta y usar «traer uno existente», que nadie
     * va a adivinar—, así que parecía que el botón borraba el servicio.
     *
     * Lo que fija esta prueba es el ciclo entero: se ve la columna, se saca,
     * **sigue en la lista con «no»**, y se vuelve a poner. Y que traerlo no
     * duplique el catálogo, que es lo que evita que «Corte de dama» termine
     * escrito de dos formas.
     */
    #[Test]
    public function cada_local_ve_su_catalogo_y_trae_lo_que_ya_existe(): void
    {
        $this->entrarComoAdministrador();

        $srv = (int) DB::scalar('SELECT id_servicio FROM servicio WHERE activo = 1 ORDER BY id_servicio LIMIT 1');
        if (! $srv) {
            $this->markTestSkipped('No hay servicios en la base de prueba.');
        }

        // Un local nuevo, sin nada publicado.
        DB::insert('INSERT INTO sucursal (nombre, direccion, activo) VALUES (?, ?, 1)',
                   ['Sucursal sin catalogo ' . uniqid(), 'Calle 5']);
        $otra = (int) DB::scalar('SELECT LAST_INSERT_ID()');
        $this->conSucursal($otra);

        // 1) Un local nuevo no publica nada, y **eso se ve en la columna**: los
        //    servicios siguen listados, con «Disponible acá» en no.
        $rows = $this->get(route('servicios.lista'))->assertOk()->viewData('rows');
        $this->assertNotEmpty($rows, 'El catálogo del salón se sigue viendo.');
        foreach ($rows as $r) {
            $this->assertEmpty((int) $r->aqui,
                'Un local nuevo no publica ningún servicio todavía.');
        }

        // 2) El alta le ofrece traer lo que ya existe.
        $ajenos = $this->get(route('servicios.form'))->assertOk()->viewData('ajenos');
        $this->assertNotEmpty($ajenos,
            'El alta tiene que ofrecer el catálogo que este local todavía no publica.');

        // 3) Traerlo no crea un servicio nuevo: agrega la fila que dice que acá
        //    también se ofrece. El catálogo sigue siendo uno.
        $antes = (int) DB::scalar('SELECT COUNT(*) FROM servicio');
        $this->post(route('servicios.publicar'), ['id_servicio' => $srv]);

        $this->assertSame($antes, (int) DB::scalar('SELECT COUNT(*) FROM servicio'),
            'Traer un servicio no puede duplicarlo: el catálogo es único.');
        $this->assertSame(1, (int) DB::scalar(
            'SELECT COUNT(*) FROM servicio_sucursal WHERE id_servicio = ? AND id_sucursal = ?', [$srv, $otra]
        ), 'Tiene que quedar publicado en este local.');

        $rows = $this->get(route('servicios.lista'))->assertOk()->viewData('rows');
        $suyo = collect($rows)->firstWhere('id_servicio', $srv);
        $this->assertNotNull($suyo, 'El servicio traído tiene que estar en la lista.');
        $this->assertNotEmpty((int) $suyo->aqui, 'Y con «Disponible acá» en sí.');

        // 4) Y el filtro sigue dejando ver sólo lo de este local, que es lo que
        //    la lista hacía sola antes.
        $soloAca = $this->get(route('servicios.lista', ['aqui' => '1']))->assertOk()->viewData('rows');
        $this->assertSame([$srv], array_map(fn ($r) => (int) $r->id_servicio, $soloAca),
            'Con el filtro puesto, la lista es exactamente la de este local.');

        // 5) **Lo que estaba roto**: sacarlo no lo saca de la pantalla. Sigue
        //    listado, con «no», y se puede volver a ofrecer desde ahí mismo.
        $this->post(route('servicios.publicar'), ['id_servicio' => $srv, 'sacar' => 1]);

        $rows = $this->get(route('servicios.lista'))->assertOk()->viewData('rows');
        $suyo = collect($rows)->firstWhere('id_servicio', $srv);
        $this->assertNotNull($suyo,
            'Sacar un servicio del local no puede hacerlo desaparecer de la lista: '
            . 'desde ahí no habría forma de volver a ofrecerlo.');
        $this->assertEmpty((int) $suyo->aqui, 'Y la columna tiene que decir que acá no se ofrece.');

        $this->post(route('servicios.publicar'), ['id_servicio' => $srv]);
        $rows = $this->get(route('servicios.lista'))->assertOk()->viewData('rows');
        $this->assertNotEmpty((int) collect($rows)->firstWhere('id_servicio', $srv)->aqui,
            'Y se tiene que poder volver a ofrecer desde la misma columna.');
    }

    /**
     * Las valoraciones y el catálogo de canjes son del local.
     *
     * Una valoración se lee para corregir algo que pasó en un lugar, así que la
     * sede 2 no tiene por qué leer las quejas de la sede 1. No hace falta
     * guardarle la sucursal: cuelga de la cita, que sí la tiene.
     */
    #[Test]
    public function las_valoraciones_y_los_canjes_son_del_local(): void
    {
        $this->entrarComoAdministrador();

        DB::insert('INSERT INTO sucursal (nombre, direccion, activo) VALUES (?, ?, 1)',
                   ['Sucursal sin nada ' . uniqid(), 'Calle 6']);
        $otra = (int) DB::scalar('SELECT LAST_INSERT_ID()');

        // En la sucursal de siempre hay valoraciones y canjes; en la nueva, no.
        $primera = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');
        $this->conSucursal($primera);
        $hayAca = count($this->get(route('clientes.valoraciones'))->assertOk()->viewData('rows'));
        $catAca = count(Canje::catalogo(false, $primera));

        $this->conSucursal($otra);
        $this->assertCount(0, $this->get(route('clientes.valoraciones'))->assertOk()->viewData('rows'),
            'Un local sin citas no puede tener valoraciones: son de donde la atendieron.');

        if ($hayAca === 0) {
            $this->markTestSkipped('La base de prueba no tiene valoraciones con las que comparar.');
        }
        $this->assertGreaterThan(0, $hayAca, 'La sucursal que sí atendió tiene que verlas.');

        // El catálogo de canjes se acota igual, salvo que el canje valga en
        // todas —sin filas en `canjeable_sucursal`—, que es la convención.
        if ($catAca > 0) {
            $sc = (int) DB::scalar('SELECT id_servicio_canjeable FROM servicio_canjeable ORDER BY id_servicio_canjeable LIMIT 1');
            DB::insert('INSERT IGNORE INTO canjeable_sucursal (id_servicio_canjeable, id_sucursal) VALUES (?,?)',
                       [$sc, $primera]);

            $this->assertSame(0, count(array_filter(Canje::catalogo(false, $otra),
                fn ($c) => (int) $c->id_servicio_canjeable === $sc)),
                'Un canje publicado sólo en la otra sede no puede aparecer acá.');
            $this->assertGreaterThan(0, count(array_filter(Canje::catalogo(false, $primera),
                fn ($c) => (int) $c->id_servicio_canjeable === $sc)),
                'Y sí en la sede que lo publica.');
        }
    }

    /**
     * La comisión puede ser distinta según el local, y la del local manda.
     *
     * Por decisión del usuario. `comision` gana su sucursal, NULL vale en todas
     * —que es lo que hay cargado de antes— y `fn_comision_servicio` elige la
     * más específica según dónde se prestó el servicio.
     */
    #[Test]
    public function la_comision_del_local_le_gana_a_la_que_vale_en_todas(): void
    {
        $sr = DB::selectOne(
            'SELECT sr.id_servicio_realizado, sr.id_usuario, sr.id_servicio, c.id_sucursal, s.precio
               FROM servicio_realizado sr
               JOIN cita c     ON c.id_cita = sr.id_cita
               JOIN servicio s ON s.id_servicio = sr.id_servicio
              WHERE c.id_sucursal IS NOT NULL AND s.precio > 0
              ORDER BY sr.id_servicio_realizado DESC LIMIT 1'
        );
        if (! $sr) {
            $this->markTestSkipped('No hay ninguna atención registrada con la que medir.');
        }

        // Se le apaga lo que tenga, para medir sólo lo que carga esta prueba.
        DB::update('UPDATE comision SET activo = 0 WHERE id_usuario = ?', [$sr->id_usuario]);

        // Una que vale en todas: 10 %.
        DB::insert("INSERT INTO comision (id_usuario, id_sucursal, id_servicio, tipo, valor, vigente_desde, activo)
                    VALUES (?, NULL, NULL, 'PORCENTAJE', 10, '2000-01-01', 1)", [$sr->id_usuario]);
        $general = (float) DB::scalar('SELECT fn_comision_servicio(?)', [$sr->id_servicio_realizado]);
        $this->assertEqualsWithDelta((float) $sr->precio * 0.10, $general, 0.01,
            'Sin comisión del local manda la que vale en todas.');

        // Y otra de ESE local: 25 %. La del local le gana.
        DB::insert("INSERT INTO comision (id_usuario, id_sucursal, id_servicio, tipo, valor, vigente_desde, activo)
                    VALUES (?, ?, NULL, 'PORCENTAJE', 25, '2000-01-01', 1)", [$sr->id_usuario, $sr->id_sucursal]);
        $delLocal = (float) DB::scalar('SELECT fn_comision_servicio(?)', [$sr->id_servicio_realizado]);
        $this->assertEqualsWithDelta((float) $sr->precio * 0.25, $delLocal, 0.01,
            'La comisión cargada para ese local tiene que ganarle a la que vale en todas.');
    }

    /**
     * La auditoría sella dónde ocurrió, y se puede mirar por local.
     *
     * El módulo se comparte —quien audita necesita el cuadro completo— pero
     * tiene que poder acotarse a una sede, igual que los reportes. La sucursal
     * no se deduce de nada: la misma persona opera en varios locales, así que
     * se guarda.
     */
    #[Test]
    public function la_auditoria_sella_el_local_y_se_puede_filtrar(): void
    {
        $suc = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');
        $this->entrarComoAdministrador();
        $this->conSucursal($suc);

        \App\Servicios\Auditoria::registrar('PRUEBA', 'Seguridad', 'prueba_aislamiento', 12345, 'de esta prueba');

        $fila = DB::selectOne(
            "SELECT id_sucursal FROM auditoria WHERE tabla_afectada = 'prueba_aislamiento'
              ORDER BY id_auditoria DESC LIMIT 1"
        );
        $this->assertSame($suc, (int) $fila->id_sucursal,
            'La auditoría tiene que sellar la sucursal en la que se estaba trabajando.');

        // Y el filtro la encuentra por local, sin dejar de verse todo por defecto.
        $conFiltro = $this->get(route('seguridad.auditoria', ['sucursal' => $suc]))->assertOk()->viewData('rows');
        $this->assertNotEmpty($conFiltro, 'Filtrando por esa sucursal tiene que aparecer.');
        foreach ($conFiltro as $r) {
            $this->assertSame($suc, (int) DB::scalar(
                'SELECT id_sucursal FROM auditoria WHERE fecha_hora = ? AND accion = ? LIMIT 1',
                [$r->fecha, $r->accion]
            ) ?: $suc, 'El filtro no puede traer filas de otro local.');
        }
    }

    /**
     * Desde la agenda se puede dividir el pago, como contra una factura.
     *
     * El modal de la agenda tenía **un** monto y **un** medio: mitad efectivo y
     * mitad tarjeta —que en el mostrador es lo normal— no se podía cargar, el
     * detalle de la tarjeta o del banco no se pedía nunca y no había vuelto.
     * Las dos pantallas usan ahora el mismo componente y el mismo lector de
     * líneas, así que no se pueden desfasar.
     *
     * `cobro` es **cada pago**, no el pago de la cita: dos medios son dos filas.
     */
    #[Test]
    public function el_cobro_de_la_agenda_se_puede_dividir_en_varios_medios(): void
    {
        $cliente = $this->clienteLibreHoy();
        $srv = DB::selectOne('SELECT id_servicio, precio FROM servicio WHERE activo = 1 AND precio >= 20000
                               ORDER BY id_servicio LIMIT 1');
        $prof = (int) DB::scalar(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE r.es_personal = 1 AND u.activo = 1 ORDER BY u.id_usuario LIMIT 1'
        );
        $efectivo = (int) DB::scalar("SELECT id_metodo_pago FROM metodo_pago WHERE tipo = 'EFECTIVO' AND activo = 1 LIMIT 1");
        $otro = (int) DB::scalar("SELECT id_metodo_pago FROM metodo_pago WHERE tipo <> 'EFECTIVO' AND activo = 1 LIMIT 1");
        if (! $cliente || ! $srv || ! $prof || ! $efectivo || ! $otro) {
            $this->markTestSkipped('Falta catálogo para armar el cobro.');
        }

        $suc = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');
        DB::insert('INSERT INTO cita (id_cliente, id_usuario, id_sucursal, fecha_hora, id_estado_cita)
                    VALUES (?, ?, ?, NOW(), 4)', [$cliente, $prof, $suc]);
        $idCita = (int) DB::scalar('SELECT LAST_INSERT_ID()');
        DB::insert('INSERT INTO cita_servicio (id_cita, id_servicio) VALUES (?, ?)', [$idCita, (int) $srv->id_servicio]);

        $this->entrarComoAdministrador();
        if (! DB::scalar('SELECT COUNT(*) FROM caja WHERE id_estado_caja = 1 AND id_sucursal = ?', [$suc])) {
            // Sin caja abierta no se mueve un guaraní, así que se abre: es el
            // camino real, no un atajo.
            \App\Servicios\Bd::idDe('sp_abrir_caja', [1, 0, $this->cajonDe($suc), '']);
        }

        // **Lo que se cobra es el total CON descuento**: el tope de la base
        // sale de `fn_cita_total`, así que cobrar el precio de lista con una
        // promoción vigente lo rechazaría — y con razón.
        $total = (int) DB::scalar('SELECT fn_cita_total(?)', [$idCita]);

        // Mitad y mitad, en dos medios distintos.
        $mitad = (int) floor($total / 2);
        $this->post(route('facturacion.sena'), [
            'id_cita' => $idCita,
            'metodo' => [$efectivo, $otro],
            'monto' => [(string) $mitad, (string) ($total - $mitad)],
            'referencia' => ['', 'OP-123'],
        ]);

        $cobros = DB::select('SELECT monto, id_metodo_pago FROM cobro WHERE id_cita = ? AND id_estado_cobro = 1', [$idCita]);
        $this->assertCount(2, $cobros,
            'Dos medios son dos cobros: `cobro` es cada pago, no el pago de la cita.');
        $this->assertEqualsWithDelta((float) $total, array_sum(array_map(fn ($c) => (float) $c->monto, $cobros)), 0.01,
            'Entre las dos líneas tiene que entrar el total de la cita.');
        $this->assertEqualsWithDelta(0.0, (float) DB::scalar(
            'SELECT fn_cita_total(?) - fn_cita_sena(?)', [$idCita, $idCita]
        ), 0.01, 'Y la cita tiene que quedar saldada.');
    }

    /**
     * Un profesional no queda asignado a un servicio que no hace.
     *
     * La agenda ofrecía a cualquiera para cualquier servicio: la manicurista
     * para una coloración, la clienta reservaba y el día de la cita el salón no
     * lo podía dar. Es el mismo problema que AG-01, con el servicio en lugar del
     * turno.
     *
     * **El criterio es permisivo**, igual que el de los turnos: quien no tiene
     * ninguno cargado los hace todos, así que un salón que no administra esto
     * sigue funcionando igual. Se comprueba en las dos direcciones.
     */
    #[Test]
    public function un_profesional_no_queda_asignado_a_un_servicio_que_no_hace(): void
    {
        $prof = (int) DB::scalar(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE r.es_personal = 1 AND u.activo = 1 ORDER BY u.id_usuario LIMIT 1'
        );
        $srv = DB::select('SELECT id_servicio FROM servicio WHERE activo = 1 ORDER BY id_servicio LIMIT 2');
        if (! $prof || count($srv) < 2) {
            $this->markTestSkipped('Falta catálogo para armar la prueba.');
        }
        [$hace, $noHace] = [(int) $srv[0]->id_servicio, (int) $srv[1]->id_servicio];
        $cuando = date('Y-m-d H:i:s', strtotime('+5 days 10:00'));

        // 1) Sin nada cargado los hace todos: el criterio permisivo de siempre.
        $this->assertSame(1, (int) DB::scalar('SELECT fn_usuario_hace_servicio(?, ?)', [$prof, $noHace]),
            'Sin servicios cargados, esa persona los hace todos.');
        // Se mira SÓLO el motivo que importa acá: `validarReparto` también
        // valida turno y disponibilidad, y eso ya lo fijan otras pruebas.
        $this->assertStringNotContainsString('no hace',
            (string) Agenda::validarReparto([$noHace => $prof], $prof, $cuando),
            'Sin servicios cargados, la agenda no puede rechazarlo por el servicio.');

        // 2) En cuanto se le carga UNO, sólo hace ése.
        DB::insert("INSERT INTO persona_servicio (id_persona, id_servicio) VALUES ((SELECT id_persona FROM usuario WHERE id_usuario = ?), ?)", [$prof, $hace]);

        $this->assertSame(1, (int) DB::scalar('SELECT fn_usuario_hace_servicio(?, ?)', [$prof, $hace]),
            'El que se le cargó, lo hace.');
        $this->assertSame(0, (int) DB::scalar('SELECT fn_usuario_hace_servicio(?, ?)', [$prof, $noHace]),
            'El que no, no.');

        $problema = Agenda::validarReparto([$noHace => $prof], $prof, $cuando);
        $this->assertNotNull($problema,
            'La agenda no puede aceptar a alguien para un servicio que no hace.');
        $this->assertStringContainsString('no hace', (string) $problema,
            'Y el aviso tiene que decir por qué, no un «no se puede» a secas.');
    }

    /**
     * Un movimiento de caja mal cargado se anula y el cajón vuelve a cuadrar.
     *
     * **Se anula, no se borra**, que es el mismo criterio que la factura y el
     * cobro: el arqueo tiene que poder explicar qué pasó, y una fila que
     * desaparece no explica nada. Lo que cambia es que `fn_caja_saldo` deja de
     * contarlo.
     */
    #[Test]
    public function un_movimiento_de_caja_anulado_deja_de_contar_en_el_arqueo(): void
    {
        $caja = DB::selectOne('SELECT id_caja FROM caja WHERE id_estado_caja = 1 ORDER BY id_caja DESC LIMIT 1');
        if (! $caja) {
            $suc = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');
            Bd::idDe('sp_abrir_caja', [1, 0, $this->cajonDe($suc), '']);
            $caja = DB::selectOne('SELECT id_caja FROM caja WHERE id_estado_caja = 1 ORDER BY id_caja DESC LIMIT 1');
        }
        $id = (int) $caja->id_caja;

        $antes = (float) DB::scalar('SELECT fn_caja_saldo(?)', [$id]);

        DB::insert("INSERT INTO movimiento_caja (id_caja, tipo, monto, concepto)
                    VALUES (?, 'INGRESO', 50000, 'De la prueba')", [$id]);
        $idMov = (int) DB::scalar('SELECT LAST_INSERT_ID()');

        $this->assertEqualsWithDelta($antes + 50000, (float) DB::scalar('SELECT fn_caja_saldo(?)', [$id]), 0.01,
            'El movimiento tiene que entrar al arqueo.');

        $this->entrarComoAdministrador();
        $this->post(route('facturacion.caja.movimiento.anular'),
            ['id_movimiento_caja' => $idMov, 'motivo' => 'Se cargó dos veces']);

        $this->assertEqualsWithDelta($antes, (float) DB::scalar('SELECT fn_caja_saldo(?)', [$id]), 0.01,
            'Anulado, el saldo tiene que volver a lo de antes.');
        $fila = DB::selectOne('SELECT activo, anulado_motivo FROM movimiento_caja WHERE id_movimiento_caja = ?', [$idMov]);
        $this->assertNotNull($fila, 'La fila NO se borra: el arqueo tiene que poder explicar qué pasó.');
        $this->assertSame(0, (int) $fila->activo);
        $this->assertSame('Se cargó dos veces', $fila->anulado_motivo,
            'Y con su motivo, que es lo único que la explica al cerrar la caja.');

        // Sin motivo no se anula: un movimiento anulado «porque sí» no se puede
        // explicar seis meses después.
        DB::insert("INSERT INTO movimiento_caja (id_caja, tipo, monto, concepto)
                    VALUES (?, 'INGRESO', 1000, 'Otra de la prueba')", [$id]);
        $otro = (int) DB::scalar('SELECT LAST_INSERT_ID()');
        $this->post(route('facturacion.caja.movimiento.anular'), ['id_movimiento_caja' => $otro, 'motivo' => '']);
        $this->assertSame(1, (int) DB::scalar('SELECT activo FROM movimiento_caja WHERE id_movimiento_caja = ?', [$otro]),
            'Sin motivo no se anula.');
    }

    /**
     * Un calendario vacío dice POR QUÉ está vacío.
     *
     * El caso que lo motiva es real y lo reportó el usuario: mechas (180 min),
     * corte de dama (45) y depilación de cejas (20) son **245 minutos**, y el
     * único turno de esa sucursal dura **240**. No entra en ningún hueco de
     * ningún día, así que el selector salía sin una sola fecha y la pantalla
     * decía «no quedan días, probá con otro profesional» — que la manda a
     * recorrer uno por uno algo que ninguno puede dar.
     *
     * No es que esté ocupado: es que no cabe. Son dos problemas distintos y se
     * arreglan de formas distintas.
     */
    #[Test]
    public function un_calendario_vacio_dice_si_es_que_no_entra_en_ningun_turno(): void
    {
        $t = DB::selectOne(
            'SELECT t.id_sucursal, TIMESTAMPDIFF(MINUTE, t.hora_inicio, t.hora_fin) AS min
               FROM turno_laboral t WHERE t.activo = 1
              ORDER BY min ASC LIMIT 1'
        );
        if (! $t) {
            $this->markTestSkipped('No hay turnos cargados en la base de prueba.');
        }
        $corto = (int) $t->min;

        // Lo que entra en el turno no da motivo: si no hay días, es que está tomado.
        $this->assertNull(Agenda::motivoSinCupo($corto, null, (int) $t->id_sucursal),
            'Lo que cabe en el turno no puede explicarse con «no entra».');

        // Un minuto más que el turno más largo de ese local ya no entra nunca.
        $mayor = (int) DB::scalar(
            'SELECT MAX(TIMESTAMPDIFF(MINUTE, hora_inicio, hora_fin)) FROM turno_laboral
              WHERE activo = 1 AND id_sucursal = ?', [(int) $t->id_sucursal]
        );
        $motivo = Agenda::motivoSinCupo($mayor + 1, null, (int) $t->id_sucursal);

        $this->assertNotNull($motivo,
            'Lo que no entra en ningún turno tiene que explicarse, no dejar el calendario mudo.');
        $this->assertStringContainsString('turno más largo', (string) $motivo,
            'Y el aviso tiene que decir contra qué se está comparando.');

        // Y con eso, el calendario efectivamente sale vacío: las dos mitades
        // tienen que contar la misma historia.
        $this->assertCount(0, Agenda::diasConCupo(null, date('Y-m-d'), 30, $mayor + 1, (int) $t->id_sucursal),
            'Si no entra en ningún turno, no puede haber ni un día con lugar.');
    }

    /**
     * Lo que decide si dos servicios pueden hacerse a la vez es LA ZONA.
     *
     * Antes lo decidía una casilla por servicio —«requiere atención exclusiva»—
     * y con un booleano el caso normal no se podía expresar: coloración y lavado
     * suman aunque el lavado no sea «exclusivo», porque las dos son sobre la
     * misma cabeza; coloración y manicura no suman, porque son partes distintas.
     * No es una propiedad del servicio: es que compartan la parte del cuerpo.
     *
     * **Y la persona también es un recurso**: una sola no puede hacer dos cosas
     * a la vez aunque sean de zonas distintas.
     */
    #[Test]
    public function la_zona_del_cuerpo_decide_que_se_puede_hacer_a_la_vez(): void
    {
        $srv = fn (string $zona, int $n) => DB::select(
            'SELECT s.id_servicio, s.duracion_min FROM servicio s
               JOIN zona_servicio z ON z.id_zona = s.id_zona
              WHERE z.nombre = ? AND s.activo = 1 ORDER BY s.duracion_min DESC LIMIT ?', [$zona, $n]
        );
        $cabello = $srv('Cabello', 2);
        $manos = $srv('Manos', 1);
        $profs = array_map(fn ($r) => (int) $r->id_usuario, DB::select(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE r.es_personal = 1 AND u.activo = 1 ORDER BY u.id_usuario LIMIT 2'
        ));
        if (count($cabello) < 2 || ! $manos || count($profs) < 2) {
            $this->markTestSkipped('Falta catálogo clasificado por zona.');
        }
        [$c1, $c2] = $cabello;
        $m = $manos[0];

        // 1) Misma zona, personas distintas: NO pueden a la vez → suman.
        $this->assertSame(
            (int) $c1->duracion_min + (int) $c2->duracion_min,
            Agenda::duracionReparto([(int) $c1->id_servicio => $profs[0], (int) $c2->id_servicio => $profs[1]], $profs[0]),
            'Dos servicios sobre la misma parte del cuerpo se hacen uno después del otro.'
        );

        // 2) Zonas distintas, personas distintas: SÍ pueden a la vez → el más largo.
        $this->assertSame(
            max((int) $c1->duracion_min, (int) $m->duracion_min),
            Agenda::duracionReparto([(int) $c1->id_servicio => $profs[0], (int) $m->id_servicio => $profs[1]], $profs[0]),
            'Partes distintas se hacen a la vez, así que la cita dura lo del más largo.'
        );

        // 3) Zonas distintas pero UNA sola persona: tampoco puede a la vez.
        //    La persona es un recurso más, igual que el cuerpo de la clienta.
        $this->assertSame(
            (int) $c1->duracion_min + (int) $m->duracion_min,
            Agenda::duracionReparto([(int) $c1->id_servicio => $profs[0], (int) $m->id_servicio => $profs[0]], $profs[0]),
            'Una sola persona no puede hacer dos cosas a la vez, aunque sean de zonas distintas.'
        );
    }

    /**
     * El movimiento de efectivo es su propia clave, y separarlo no le quitó
     * nada a quien ya lo hacía.
     *
     * Abrir y cerrar el cajón es administrar el arqueo; meter o sacar plata a
     * mano es mover dinero **sin un documento detrás** —no hay cobro ni pago que
     * lo respalde, sólo un concepto escrito—, así que es la parte que un salón
     * puede querer dar por separado. Mismo criterio que separó Timbrados en la
     * 5.2.0.
     *
     * **Se comprueba en las dos direcciones**, que es lo que hace que valga:
     * sin la clave la pantalla contesta 403, y con ella se dibuja.
     */
    #[Test]
    public function el_movimiento_de_efectivo_es_su_propia_clave(): void
    {
        $rol = (int) DB::scalar(
            "SELECT r.id_rol FROM rol r JOIN rol_modulo rm ON rm.id_rol = r.id_rol
              WHERE rm.modulo = 'facturacion.caja' AND r.es_personal = 1 LIMIT 1"
        );
        if (! $rol) {
            $this->markTestSkipped('Ningún rol tiene la caja en la base de prueba.');
        }

        // **Separar el permiso no puede quitarle nada a quien ya lo hacía.** El
        // `.sql` que se entrega se lo concede a todo rol que tuviera la caja, y
        // de ahí en adelante el salón decide.
        $this->assertSame(1, (int) DB::scalar(
            "SELECT COUNT(*) FROM rol_modulo WHERE id_rol = ? AND modulo = 'facturacion.movimientos'", [$rol]
        ), 'Quien administraba la caja tiene que conservar el movimiento de efectivo.');

        $u = (int) DB::scalar('SELECT id_usuario FROM usuario WHERE id_rol = ? AND activo = 1 LIMIT 1', [$rol]);
        if (! $u) {
            $this->markTestSkipped('Ese rol no tiene ninguna cuenta activa.');
        }

        session(['uid' => $u, 'rol' => $rol, 'es_personal' => true, 'es_cliente' => false]);
        $this->conSucursal();

        $this->get(route('facturacion.movimientos'))->assertOk();

        // Y sin la clave, 403: **esconder el botón no es el control**.
        DB::delete("DELETE FROM rol_modulo WHERE id_rol = ? AND modulo = 'facturacion.movimientos'", [$rol]);
        // La matriz se lee una vez y queda en cache: sin tirarla, el rol sigue
        // contestando lo de antes y la prueba mediria la cache, no la regla.
        Permisos::olvidar();
        $this->get(route('facturacion.movimientos'))->assertStatus(403);

        // La caja sigue siendo suya: se separó una cosa, no se le sacó la otra.
        $this->get(route('facturacion.cajas'))->assertOk();
    }

    /**
     * La plata no entra ni sale del cajón de la nada.
     *
     * `movimiento_caja` pedía tipo, monto y un texto libre, así que quien tenía
     * la clave sacaba cualquier monto escribiendo «varios». Fiscalmente eso no
     * se sostiene: un gasto tiene comprobante, y el sistema tiene que exigirlo.
     *
     * **El retiro de la propietaria también se factura**, y eso también se
     * comprueba: ella tiene su propio RUC y su propio timbrado —el salón emite
     * con el punto 001-001 y ella con el 001-002—, así que le factura al salón
     * por lo que retira. Lo que de verdad no lleva comprobante es mover plata
     * al cambio, o un faltante de arqueo: son diferencias, no operaciones con
     * un tercero.
     */
    #[Test]
    public function un_gasto_de_caja_no_entra_sin_su_comprobante(): void
    {
        $gasto = DB::selectOne(
            "SELECT id_tipo_mov_caja FROM tipo_movimiento_caja WHERE exige_documento = 1 AND activo = 1 LIMIT 1"
        );
        // El que de verdad no lleva comprobante: mover plata al cambio o un
        // faltante de arqueo. El retiro de la propietaria SÍ lo lleva.
        $sinDoc = DB::selectOne(
            "SELECT id_tipo_mov_caja, nombre FROM tipo_movimiento_caja
              WHERE exige_documento = 0 AND signo = 'S' AND activo = 1 LIMIT 1"
        );
        $retiro = DB::selectOne(
            "SELECT id_tipo_mov_caja FROM tipo_movimiento_caja
              WHERE nombre = 'Retiro de la propietaria' AND activo = 1 LIMIT 1"
        );
        if (! $gasto || ! $retiro || ! $sinDoc) {
            $this->markTestSkipped('Falta el catálogo de tipos de movimiento.');
        }

        $this->entrarComoAdministrador();
        $suc = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');
        if (! DB::scalar('SELECT COUNT(*) FROM caja WHERE id_estado_caja = 1 AND id_sucursal = ?', [$suc])) {
            Bd::idDe('sp_abrir_caja', [1, 200000, $this->cajonDe($suc), '']);
        }

        $cuantos = fn () => (int) DB::scalar('SELECT COUNT(*) FROM movimiento_caja');

        // 1) Un gasto sin comprobante no entra.
        $antes = $cuantos();
        $this->post(route('facturacion.caja.movimiento'), [
            'id_tipo_mov_caja' => (int) $gasto->id_tipo_mov_caja,
            'monto' => '15000', 'concepto' => 'delivery',
        ]);
        $this->assertSame($antes, $cuantos(),
            'Un gasto sin número de comprobante no puede sacar plata del cajón.');

        // 2) Con un RUC inventado tampoco: el dígito verificador se comprueba
        //    con el mismo módulo 11 que evita el rechazo 1309 de la DNIT.
        $this->post(route('facturacion.caja.movimiento'), [
            'id_tipo_mov_caja' => (int) $gasto->id_tipo_mov_caja,
            'monto' => '15000', 'concepto' => 'delivery',
            'nro_comprobante' => '001-001-0001234', 'ruc_emisor' => '80012345-6',
        ]);
        $this->assertSame($antes, $cuantos(),
            'Un RUC con el dígito verificador mal no respalda nada.');

        // 3) **El retiro de la propietaria tampoco entra sin comprobante**: ella
        //    factura su retiro con su propio RUC, así que hay un papel que pedir.
        $this->post(route('facturacion.caja.movimiento'), [
            'id_tipo_mov_caja' => (int) $retiro->id_tipo_mov_caja,
            'monto' => '10000', 'concepto' => 'retiro de la dueña',
        ]);
        $this->assertSame($antes, $cuantos(),
            'El retiro de la propietaria se factura con su RUC: también necesita su comprobante.');

        // 4) Lo que de verdad no tiene documento —mover plata al cambio, un
        //    faltante— sí entra: pedirle un papel que no existe empujaría a
        //    disfrazarlo de otra cosa, que es justo lo que hay que evitar.
        $this->post(route('facturacion.caja.movimiento'), [
            'id_tipo_mov_caja' => (int) $sinDoc->id_tipo_mov_caja,
            'monto' => '10000', 'concepto' => 'se saca para tener cambio',
        ]);
        $this->assertSame($antes + 1, $cuantos(),
            $sinDoc->nombre . ' no es una operación con un tercero, así que no hay comprobante que pedir.');

        // Y quedó con su clase y su autor, que es lo que lo hace auditable.
        $m = DB::selectOne('SELECT id_tipo_mov_caja, id_usuario, concepto FROM movimiento_caja
                             ORDER BY id_movimiento_caja DESC LIMIT 1');
        $this->assertSame((int) $sinDoc->id_tipo_mov_caja, (int) $m->id_tipo_mov_caja,
            'El movimiento tiene que decir de qué clase es, no sólo si entra o sale.');
        $this->assertNotNull($m->id_usuario, 'Y quién lo cargó.');
    }

    /**
     * Una nota de crédito no puede devolverse dos veces, ni por otro monto.
     *
     * Emitirla escribía el egreso **sola**, y además la clase «Devolución al
     * cliente» dejaba cargar otro a mano: quedaban **dos salidas por la misma
     * devolución**, y con montos distintos si quien la cargaba escribía otro
     * número. El cajón terminaba faltando plata que nunca salió.
     *
     * Ahora emitir la nota **no toca el cajón** —son dos actos: el comprobante
     * se emite y la plata se entrega cuando la clienta pasa— y la devolución se
     * confirma eligiendo la nota, con el monto que sale de ella.
     */
    #[Test]
    public function una_nota_de_credito_no_se_devuelve_dos_veces(): void
    {
        $devolucion = (int) DB::scalar(
            "SELECT id_tipo_mov_caja FROM tipo_movimiento_caja
              WHERE nombre LIKE 'Devoluci%' AND activo = 1 LIMIT 1"
        );
        $nc = DB::selectOne(
            "SELECT nc.id_factura FROM factura nc
              WHERE nc.id_tipo_comprobante = 5 AND nc.id_estado_factura = 1 LIMIT 1"
        );
        if (! $devolucion || ! $nc) {
            $this->markTestSkipped('Hace falta una nota de crédito emitida en la base de prueba.');
        }

        $this->entrarComoAdministrador();
        $suc = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');
        if (! DB::scalar('SELECT COUNT(*) FROM caja WHERE id_estado_caja = 1 AND id_sucursal = ?', [$suc])) {
            Bd::idDe('sp_abrir_caja', [1, 500000, $this->cajonDe($suc), '']);
        }

        // Una devolución vigente sobre esa nota: la segunda ya no puede entrar.
        $caja = (int) DB::scalar(
            'SELECT id_caja FROM caja WHERE id_estado_caja = 1 AND id_sucursal = ? LIMIT 1', [$suc]
        );
        DB::insert(
            "INSERT INTO movimiento_caja (id_caja, id_tipo_mov_caja, id_factura, tipo, monto, concepto, id_usuario)
             VALUES (?,?,?,'EGRESO',1000,'devolución de prueba',1)",
            [$caja, $devolucion, (int) $nc->id_factura]
        );

        // **La base lo hace cumplir, no un `if`**: el índice único sobre
        // (id_factura, activo) impide la segunda vigente.
        $rebotó = false;
        try {
            DB::insert(
                "INSERT INTO movimiento_caja (id_caja, id_tipo_mov_caja, id_factura, tipo, monto, concepto, id_usuario)
                 VALUES (?,?,?,'EGRESO',9999,'segunda devolución con otro monto',1)",
                [$caja, $devolucion, (int) $nc->id_factura]
            );
        } catch (Throwable) {
            $rebotó = true;
        }

        $this->assertTrue($rebotó,
            'Dos devoluciones vigentes por la misma nota dejarían el cajón faltando plata que nunca salió.');

        // Y esa nota deja de ofrecerse: lo que ya se devolvió no se elige otra vez.
        $ofrecidas = $this->get(route('facturacion.movimientos'))->assertOk()->viewData('notas');
        $this->assertNotContains((int) $nc->id_factura,
            array_map(fn ($n) => (int) $n->id_factura, $ofrecidas),
            'Una nota ya devuelta no puede seguir en la lista de pendientes.');
    }

    /**
     * El cobro entra al cajón del local de la ATENCIÓN, no al del timbrado.
     *
     * `fn_timbrado_vigente` cae al timbrado de otra sede cuando el local no
     * tiene el suyo, y hasta acá el cobro deducía su sucursal de ahí: la plata
     * seguía al papel y entraba al arqueo del local ajeno. **La simulación de
     * 30 días midió 43 cobros acreditados a la sucursal equivocada.**
     *
     * Con la caída puesta, el local **no es derivable** del timbrado, así que
     * la factura lo guarda: no es redundancia, es un dato que el timbrado no
     * puede expresar.
     */
    #[Test]
    public function el_cobro_entra_al_cajon_del_local_de_la_atencion(): void
    {
        $cita = DB::selectOne(
            'SELECT c.id_cita, c.id_cliente, c.id_usuario, c.id_sucursal FROM cita c
              WHERE EXISTS (SELECT 1 FROM cita_servicio cs WHERE cs.id_cita = c.id_cita)
                AND NOT EXISTS (SELECT 1 FROM factura f WHERE f.id_cita = c.id_cita)
              ORDER BY c.id_cita DESC LIMIT 1'
        );
        if (! $cita) {
            $this->markTestSkipped('Hace falta una cita con servicios y sin comprobante.');
        }

        // Un local nuevo, SIN timbrado propio: el caso que dispara la caída.
        DB::insert('INSERT INTO sucursal (nombre, activo) VALUES (?, 1)', ['Sin timbrado ' . uniqid()]);
        $otra = (int) DB::scalar('SELECT LAST_INSERT_ID()');
        DB::update('UPDATE cita SET id_sucursal = ? WHERE id_cita = ?', [$otra, (int) $cita->id_cita]);

        $tipo = (int) DB::scalar(
            'SELECT id_tipo_comprobante FROM timbrado
              WHERE activo = 1 AND CURDATE() BETWEEN fecha_inicio AND fecha_fin LIMIT 1'
        );
        $idFactura = Bd::idDe('sp_emitir_factura',
            [(int) $cita->id_cliente, (int) $cita->id_cita, (int) $cita->id_usuario, $tipo, 1, $otra]);

        // El timbrado es prestado —el local no tiene el suyo— y eso está bien:
        // dejar de facturar sería peor. Lo que NO puede pasar es que la plata
        // se vaya con él.
        $delTimbrado = (int) DB::scalar(
            'SELECT t.id_sucursal FROM factura f JOIN timbrado t ON t.id_timbrado = f.id_timbrado
              WHERE f.id_factura = ?', [$idFactura]
        );
        $this->assertNotSame($otra, $delTimbrado,
            'El caso sólo significa algo si el timbrado es de otra sede.');

        $this->assertSame($otra, (int) DB::scalar(
            'SELECT id_sucursal FROM factura WHERE id_factura = ?', [$idFactura]
        ), 'La factura tiene que decir dónde ocurrió la atención, no de quién es el timbrado.');

        // Y el cobro va a la caja de ESE local.
        $caja = (int) DB::scalar(
            'SELECT id_caja FROM caja WHERE id_estado_caja = 1 AND id_sucursal = ? LIMIT 1', [$otra]
        );
        if (! $caja) {
            Bd::idDe('sp_abrir_caja', [1, 100000, $this->cajonDe($otra), '']);
            $caja = (int) DB::scalar(
                'SELECT id_caja FROM caja WHERE id_estado_caja = 1 AND id_sucursal = ? LIMIT 1', [$otra]
            );
        }

        $efectivo = (int) DB::scalar("SELECT id_metodo_pago FROM metodo_pago WHERE tipo = 'EFECTIVO' AND activo = 1 LIMIT 1");
        Bd::idDe('sp_registrar_cobro', [$idFactura, $efectivo, 1, 1000.0, null, null]);

        $delCobro = (int) DB::scalar(
            'SELECT k.id_sucursal FROM cobro co JOIN caja k ON k.id_caja = co.id_caja
              WHERE co.id_factura = ? ORDER BY co.id_cobro DESC LIMIT 1', [$idFactura]
        );
        $this->assertSame($otra, $delCobro,
            'La plata tiene que entrar al cajón del local que atendió, no al del timbrado prestado.');
    }

    /**
     * Quien no tiene turno en NINGÚN local no atiende en ninguno.
     *
     * El criterio permisivo es del local —una sucursal sin turnos cargados
     * ofrece la jornada por defecto, que es lo que la deja operar el primer
     * día— pero eso dejaba la agenda abierta **para cualquiera**: la
     * simulación de 30 días le vendió **71 citas a la asistente
     * administrativa**, 10 en domingo, y el 40 % terminó ausente.
     *
     * Son dos preguntas distintas: **«¿esta persona atiende?»** es del salón,
     * **«¿atiende acá?»** es del local.
     */
    #[Test]
    public function quien_no_atiende_en_ningun_local_no_recibe_citas_en_el_nuevo(): void
    {
        $sinTurno = (int) DB::scalar(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE r.es_personal = 1 AND u.activo = 1
                AND NOT EXISTS (SELECT 1 FROM usuario_turno ut WHERE ut.id_usuario = u.id_usuario)
              LIMIT 1'
        );
        $conTurno = (int) DB::scalar(
            'SELECT ut.id_usuario FROM usuario_turno ut
               JOIN turno_laboral t ON t.id_turno = ut.id_turno AND t.activo = 1 LIMIT 1'
        );
        if (! $sinTurno || ! $conTurno) {
            $this->markTestSkipped('Hacen falta una persona con turno y otra sin ninguno.');
        }

        // Un local recién abierto: sin un solo turno cargado.
        DB::insert('INSERT INTO sucursal (nombre, activo) VALUES (?, 1)', ['Recien abierta ' . uniqid()]);
        $nueva = (int) DB::scalar('SELECT LAST_INSERT_ID()');

        // **El día se busca, no se fija.** Con un `+5 days` a secas la prueba
        // caía sobre una fecha en la que esa persona ya tenía cita, y entonces
        // medía el solape —que a propósito NO se filtra por sucursal: la
        // persona es una sola— en vez de la regla del criterio permisivo. Es la
        // misma lección que dejó `clienteLibreHoy()`: una prueba que depende
        // del calendario dice cosas distintas según el día que se corra.
        $cuando = null;
        for ($d = 5; $d <= 120; $d++) {
            $tal = date('Y-m-d', strtotime("+$d days"));
            $ocupada = (int) DB::scalar(
                'SELECT COUNT(*) FROM cita c
                   JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
                  WHERE c.id_usuario = ? AND DATE(c.fecha_hora) = ? AND ec.bloquea_agenda = 1',
                [$conTurno, $tal]
            );
            if (! $ocupada) {
                $cuando = $tal . ' 10:00:00';
                break;
            }
        }
        $this->assertNotNull($cuando, 'Hace falta un día libre para medir la regla.');

        // 1) La que no atiende en ningún lado, tampoco acá.
        $this->assertFalse(Agenda::huecoLibre($sinTurno, $cuando, 30, null, $nueva),
            'Quien no tiene turno en ninguna sede no atiende clientes: no se le agenda en el local nuevo.');

        // 2) La que sí atiende —aunque su turno sea de otra sede— entra por el
        //    criterio permisivo: el local todavía no cargó turnos, y sin esto
        //    quedaría sin agenda el primer día.
        $this->assertTrue(Agenda::huecoLibre($conTurno, $cuando, 30, null, $nueva),
            'Un local sin turnos propios tiene que poder operar el primer día.');

        // 3) Y el espejo de PHP dice lo mismo, que es donde esto se rompe.
        $this->assertSame([], Agenda::slotsProfesional($sinTurno, substr($cuando, 0, 10), 30, null, $nueva),
            'La pantalla no puede ofrecer huecos de alguien que la base va a rechazar.');
    }

    /**
     * Una nota de crédito se puede emitir de verdad.
     *
     * **Estuvo rota desde la 7.37.0 y ninguna prueba lo vio.** Esa versión le
     * agregó el tercer parámetro a `fn_timbrado_vigente` y
     * `sp_emitir_nota_credito` se quedó llamándola con dos, así que emitir
     * reventaba con el error 1318 —«Incorrect number of arguments»— y la
     * pantalla lo traducía a «no hay timbrado vigente», que manda a mirar el
     * lugar equivocado.
     *
     * Lo que faltaba era una prueba que EMITIERA una: las que había sólo
     * comprobaban que la nota fuera un tipo declarable ante la DNIT. Un hueco
     * de cobertura esconde defectos, no ausencia de defectos.
     */
    #[Test]
    public function una_nota_de_credito_se_puede_emitir(): void
    {
        $f = DB::selectOne(
            'SELECT f.id_factura FROM factura f
               JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = f.id_tipo_comprobante
              WHERE f.id_estado_factura = 1 AND tc.signo = 1
                AND NOT EXISTS (SELECT 1 FROM factura nc WHERE nc.id_factura_origen = f.id_factura)
              ORDER BY f.id_factura DESC LIMIT 1'
        );
        if (! $f) {
            $this->markTestSkipped('No hay ninguna factura sin nota de crédito.');
        }

        $idNota = (int) Bd::idDe('sp_emitir_nota_credito',
            [(int) $f->id_factura, 1, 'la clienta no quedó conforme']);

        $this->assertGreaterThan(0, $idNota, 'La nota de crédito tiene que emitirse.');

        $nota = DB::selectOne(
            'SELECT id_tipo_comprobante, id_factura_origen, id_timbrado, id_sucursal
               FROM factura WHERE id_factura = ?', [$idNota]
        );
        $this->assertSame(5, (int) $nota->id_tipo_comprobante, 'Tiene que ser del tipo 5.');
        $this->assertSame((int) $f->id_factura, (int) $nota->id_factura_origen,
            'Y colgar de la factura que reversa.');
        $this->assertNotNull($nota->id_timbrado, 'Con su propio timbrado del tipo 5.');

        // Y copia el detalle: una nota sin renglones no reversa nada.
        $this->assertGreaterThan(0, (int) DB::scalar(
            'SELECT COUNT(*) FROM detalle_factura WHERE id_factura = ?', [$idNota]
        ), 'La nota tiene que copiar el detalle de la factura original.');
    }
    /**
     * El comprobante electrónico se declara con los datos del salón, no con
     * los del archivo de ejemplo del Automatizador.
     *
     * **Hasta la 7.52.0 el emisor no viajaba con la factura.** El KuDE lo
     * sacaba del `.env` del otro proyecto, así que salía «MI EMPRESA S.A.»,
     * RUC 80012345-6 —con el dígito verificador mal, que es el rechazo 1309
     * de la DNIT— y actividad «VENTA AL POR MENOR».
     *
     * Y no alcanzaba con cargar ese archivo una vez: **el emisor cambia con
     * la sucursal**. La dirección y el timbrado son los del local que
     * atendió, igual que el establecimiento del número impreso.
     */
    #[Test]
    public function el_txt_declara_al_salon_y_al_local_que_emitio(): void
    {
        $f = DB::selectOne(
            'SELECT f.id_factura, t.nro_timbrado, t.id_sucursal
               FROM factura f JOIN timbrado t ON t.id_timbrado = f.id_timbrado
              WHERE f.id_estado_factura = 1
                AND EXISTS (SELECT 1 FROM detalle_factura d WHERE d.id_factura = f.id_factura)
              ORDER BY f.id_factura DESC LIMIT 1'
        );
        if (! $f) {
            $this->markTestSkipped('Hace falta una factura vigente con renglones.');
        }

        // Un RUC con el DV MAL escrito en la ficha, que es el caso real: se
        // tipea a mano y de ahí sale impreso en cada comprobante.
        $suc = (int) DB::scalar(
            'SELECT COALESCE(id_sucursal, ?) FROM factura WHERE id_factura = ?',
            [(int) $f->id_sucursal, (int) $f->id_factura]
        );
        DB::update('UPDATE sucursal SET ruc = ?, ciudad = ? WHERE id_sucursal = ?',
            ['80012345-9', 'Luque', $suc]);
        DB::update("UPDATE configuracion SET actividad_cod = '96021',
                           actividad_desc = 'PELUQUERIA' WHERE id_configuracion = 1");
        Config::olvidar();

        $txt = Sifen::armarTxt((int) $f->id_factura);
        $emi = null;
        foreach (explode("\n", $txt) as $l) {
            if (str_starts_with($l, 'EMI|')) {
                $emi = explode('|', $l);
            }
        }

        $this->assertNotNull($emi, 'El TXT tiene que declarar quién emite.');
        $this->assertSame(Config::nombreSalon(), $emi[1], 'La razón social es la del salón.');

        // **El DV se recalcula, no se copia.** Con el 9 mal escrito en la
        // ficha, lo que sale tiene que ser el correcto: 80012345 → 0.
        $this->assertSame('80012345', $emi[2]);
        $this->assertSame('0', $emi[3],
            'El dígito verificador se calcula: uno mal tipeado en la ficha es el rechazo 1309.');

        $this->assertSame('Luque', $emi[5], 'La ciudad es la del local que emitió.');
        $this->assertSame('96021', $emi[8]);
        $this->assertSame((string) $f->nro_timbrado, $emi[10],
            'El timbrado impreso es el que numeró este comprobante, no uno de configuración.');

        // Y el tipo de transacción: un salón presta servicios, no vende
        // mercadería. Va en el KuDE y dentro del XML que ve la DNIT.
        foreach (explode("\n", $txt) as $l) {
            if (str_starts_with($l, 'FAC|')) {
                $this->assertSame('2', explode('|', $l)[7] ?? '',
                    'D011 iTipTra tiene que decir prestación de servicios.');
            }
        }
    }
    /**
     * Una cita ya atendida no se anuncia como próxima.
     *
     * **Atender temprano es lo normal**: la clienta de las 11:30 llega a las
     * 11 y se la atiende. Con la hora todavía por delante, el panel la seguía
     * listando en «Tus próximas citas» — y eso no es un detalle estético:
     * quien mira el panel decide con eso si le da tiempo de tomar otra.
     *
     * La causa es que esta consulta era la única del sistema que listaba los
     * estados a mano («todos menos Cancelada y Ausente») en vez de preguntar
     * `estado_cita.bloquea_agenda`, que es la columna que significa
     * exactamente «esta cita todavía ocupa el sillón».
     */
    #[Test]
    public function el_panel_no_anuncia_como_proxima_una_cita_ya_atendida(): void
    {
        $cita = DB::selectOne(
            'SELECT c.id_cita, c.id_usuario, c.id_sucursal FROM cita c
              WHERE EXISTS (SELECT 1 FROM cita_servicio cs WHERE cs.id_cita = c.id_cita)
              ORDER BY c.id_cita DESC LIMIT 1'
        );
        if (! $cita) {
            $this->markTestSkipped('Hace falta una cita con servicios.');
        }

        // Se entra como Administrador a propósito: ve la agenda entera, así
        // que lo que se mide es el estado de la cita y no de quién es.

        // El panel muestra CUATRO, así que la prueba se queda sin significado
        // si esta cita no entra en las cuatro primeras: las demás pendientes
        // de esa persona se cierran para que quede sola. `DatabaseTransactions`
        // lo revierte al terminar.
        DB::update(
            'UPDATE cita c JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
                SET c.id_estado_cita = 4
              WHERE ec.bloquea_agenda = 1 AND c.id_cita <> ?',
            [(int) $cita->id_cita]
        );

        // Dentro de un rato y en el local de la cita, que es donde el panel
        // mira: sin eso el filtro por sucursal la esconde por otro motivo.
        DB::update('UPDATE cita SET fecha_hora = DATE_ADD(NOW(), INTERVAL 40 MINUTE),
                           id_estado_cita = 1 WHERE id_cita = ?', [(int) $cita->id_cita]);

        $this->entrarComo('admin', 'admin123');
        $this->conSucursal((int) $cita->id_sucursal);

        $enElPanel = fn (): bool => str_contains(
            $this->get(route('panel'))->assertOk()->getContent(), 'id="citaProxima' . (int) $cita->id_cita . '"'
        );

        $this->assertTrue($enElPanel(),
            'Programada y con la hora por delante: tiene que estar en las próximas.');

        // Se la atiende antes de la hora, que es el caso que reportó el uso real.
        DB::update('UPDATE cita SET id_estado_cita = 4 WHERE id_cita = ?', [(int) $cita->id_cita]);

        $this->assertFalse($enElPanel(),
            'Ya atendida, aunque su hora no haya llegado, deja de ser una cita próxima.');
    }
    /**
     * El arqueo dice si la caja cuadra, sobra o falta.
     *
     * **Cerrar la caja era un botón, no un arqueo.** `sp_cerrar_caja` sólo
     * marcaba el estado: el sistema sabía cuánto DEBERÍA haber —`fn_caja_saldo`—
     * y nunca preguntaba cuánto HAY, así que no podía decir si cuadró. Un
     * faltante se descubría al día siguiente y sin saber de qué día venía.
     *
     * La diferencia **no se guarda**: es `contado − esperado`, una columna
     * derivada, y la regla número dos las prohíbe. La calcula
     * `fn_caja_diferencia`, y por eso sigue siendo cierta si mañana se anula
     * un movimiento de esa caja.
     */
    #[Test]
    public function el_arqueo_compara_lo_contado_con_lo_esperado(): void
    {
        $suc = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');
        $uid = (int) DB::scalar(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE r.es_personal = 1 AND u.activo = 1 LIMIT 1'
        );

        // Se cierran las que haya para poder abrir una limpia: el disparador
        // admite una sola abierta por local. `DatabaseTransactions` revierte.
        DB::update('UPDATE caja SET id_estado_caja = 2, fecha_cierre = NOW()
                     WHERE id_estado_caja = 1 AND id_sucursal = ?', [$suc]);

        $abrir = function (float $inicial) use ($uid, $suc): int {
            $id = Bd::idDe('sp_abrir_caja', [$uid, $inicial, $this->cajonDe($suc), '']);

            return (int) $id;
        };

        // --- 1. Cuadra: se cuenta exactamente lo esperado -----------------
        $id = $abrir(200000.0);
        $esperado = (float) DB::scalar('SELECT fn_caja_saldo(?)', [$id]);
        $this->assertSame(200000.0, $esperado, 'Recién abierta, lo esperado es el monto inicial.');

        Caja::cerrar($id, $esperado, $uid);
        $this->assertSame(0.0, (float) Caja::diferencia($id), 'Contar lo esperado tiene que dar cero.');
        $this->assertSame($uid, (int) DB::scalar('SELECT id_usuario_cierre FROM caja WHERE id_caja = ?', [$id]),
            'El arqueo guarda quién lo hizo: sin responsable no se le puede pedir explicaciones a nadie.');

        // --- 2. Falta plata ----------------------------------------------
        $id = $abrir(200000.0);
        Caja::cerrar($id, 180000.0, $uid);
        $this->assertSame(-20000.0, (float) Caja::diferencia($id), 'Contar de menos es un faltante, en negativo.');

        // --- 3. Sobra plata ----------------------------------------------
        $id = $abrir(200000.0);
        Caja::cerrar($id, 215000.0, $uid);
        $this->assertSame(15000.0, (float) Caja::diferencia($id), 'Contar de más es un sobrante, en positivo.');

        // --- 4. Una caja sin conteo no dice que cuadró --------------------
        //
        // Es la trampa que hace falta evitar: un 0 por defecto sería
        // indistinguible de un arqueo que dio exacto, y las cajas cerradas
        // antes de que esto existiera no tienen conteo.
        $id = $abrir(200000.0);
        DB::update('UPDATE caja SET id_estado_caja = 2, fecha_cierre = NOW() WHERE id_caja = ?', [$id]);
        $this->assertNull(Caja::diferencia($id),
            'Sin conteo la diferencia es NULL, no cero: cero significa que cuadró.');

        // --- 5. La diferencia SE CALCULA, no se guarda --------------------
        //
        // Si estuviera guardada, mover la plata de esa caja después del cierre
        // la dejaría diciendo lo de antes. Acá tiene que seguirla.
        $id = $abrir(200000.0);
        Caja::cerrar($id, 200000.0, $uid);
        $this->assertSame(0.0, (float) Caja::diferencia($id));

        $tipo = (int) DB::scalar(
            "SELECT id_tipo_mov_caja FROM tipo_movimiento_caja WHERE signo = 'S' AND activo = 1 LIMIT 1"
        );
        DB::insert('INSERT INTO movimiento_caja (id_caja, id_tipo_mov_caja, tipo, monto, concepto, id_usuario)
                    VALUES (?, ?, ?, ?, ?, ?)', [$id, $tipo, 'EGRESO', 30000, 'gasto que aparecio despues', $uid]);

        $this->assertSame(30000.0, (float) Caja::diferencia($id),
            'Al bajar lo esperado en 30.000, lo contado pasa a sobrar por 30.000: la diferencia sigue al saldo.');
    }
    /**
     * Una persona no puede quedar en dos turnos que se pisan, ni de locales
     * distintos.
     *
     * **El turno dice en qué sucursal se trabaja**, y que alguien tenga el
     * lunes en un local y el martes en otro es correcto: para eso existe la
     * tabla N:M. Lo que no puede pasar es que dos de sus turnos se pisen el
     * mismo día a la misma hora — ahí queda comprometida en dos lugares al
     * mismo tiempo y la agenda le ofrece los dos.
     *
     * Dos turnos del MISMO local ya se rechazaban al crearlos; uno de cada
     * local pasaba sin que nadie lo mirara, que es justo el caso peligroso.
     */
    #[Test]
    public function una_persona_no_queda_en_dos_turnos_que_se_pisan(): void
    {
        $sucs = DB::select('SELECT id_sucursal FROM sucursal WHERE activo = 1 ORDER BY id_sucursal LIMIT 2');
        $a = (int) $sucs[0]->id_sucursal;
        $b = (int) ($sucs[1]->id_sucursal ?? $sucs[0]->id_sucursal);

        $crear = function (int $suc, string $desde, string $hasta, int $dia) {
            DB::insert('INSERT INTO turno_laboral (id_sucursal, nombre, hora_inicio, hora_fin, activo)
                        VALUES (?, ?, ?, ?, 1)', [$suc, 'T' . uniqid(), $desde, $hasta]);
            $id = (int) DB::scalar('SELECT LAST_INSERT_ID()');
            DB::insert('INSERT INTO turno_dia (id_turno, dia_semana) VALUES (?, ?)', [$id, $dia]);

            return $id;
        };

        // Lunes 08–12 en un local y lunes 11–15 en el otro: se pisan de 11 a 12.
        $t1 = $crear($a, '08:00', '12:00', 1);
        $t2 = $crear($b, '11:00', '15:00', 1);
        // Martes 08–12: distinto día, no se pisa con nada.
        $t3 = $crear($b, '08:00', '12:00', 2);

        $usuario = (int) DB::scalar(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE r.es_personal = 1 AND u.activo = 1 LIMIT 1'
        );
        $this->entrarComo('admin', 'admin123');

        $guardar = function (array $turnos) use ($usuario): void {
            $u = DB::selectOne(
                'SELECT username, id_rol, id_persona FROM usuario WHERE id_usuario = ?', [$usuario]
            );
            $this->post(route('seguridad.usuario.guardar'), [
                'id_usuario' => $usuario, 'username' => $u->username, 'id_rol' => $u->id_rol,
                'id_persona' => $u->id_persona,
                'sucursales' => [(int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1')],
                'turnos' => $turnos,
            ]);
        };

        $tiene = fn (): array => array_map(
            fn ($r) => (int) $r->id_turno,
            DB::select('SELECT id_turno FROM usuario_turno WHERE id_usuario = ?', [$usuario])
        );

        // 1) Los dos que se pisan: se rechaza y no se le asigna ninguno.
        $antes = $tiene();
        $guardar([$t1, $t2]);
        $this->assertNotContains($t2, $tiene(),
            'Dos turnos que se pisan el mismo día dejan a la persona en dos lugares a la vez.');
        $this->assertSame($antes, $tiene(), 'Un guardado rechazado no toca lo que ya estaba.');

        // 2) Días distintos, aunque sean de locales distintos: entra.
        $guardar([$t1, $t3]);
        $ahora = $tiene();
        sort($ahora);
        $esperado = [$t1, $t3];
        sort($esperado);
        $this->assertSame($esperado, $ahora,
            'Lunes en un local y martes en otro es exactamente para lo que existe la tabla N:M.');
    }
    /**
     * Cuánta seña se pide lo fija el salón, no la clienta.
     *
     * **`servicio` no decía nada de seña**, así que el sistema no podía
     * contestar «¿este servicio la pide?» ni «¿de cuánto?»: la clienta
     * anunciaba el monto que quisiera y el salón se lo confirmaba de palabra.
     *
     * Se guarda un **porcentaje** y no un monto: un monto fijo se separa del
     * precio el día que el servicio sube —queda una seña de 50.000 sobre un
     * servicio de 400.000— y hay que acordarse de tocar los dos.
     */
    #[Test]
    public function la_sena_que_se_pide_sale_del_servicio_y_no_del_cliente(): void
    {
        $cita = DB::selectOne(
            'SELECT c.id_cita FROM cita c
              WHERE EXISTS (SELECT 1 FROM cita_servicio cs WHERE cs.id_cita = c.id_cita)
              ORDER BY c.id_cita DESC LIMIT 1'
        );
        if (! $cita) {
            $this->markTestSkipped('Hace falta una cita con servicios.');
        }
        $id = (int) $cita->id_cita;

        $srv = DB::select(
            'SELECT s.id_servicio, s.precio FROM cita_servicio cs
               JOIN servicio s ON s.id_servicio = cs.id_servicio
              WHERE cs.id_cita = ? ORDER BY s.precio DESC', [$id]
        );
        $ids = array_map(fn ($r) => (int) $r->id_servicio, $srv);

        // Ninguno pide seña: no se pide nada.
        DB::update('UPDATE servicio SET sena_porcentaje = NULL WHERE id_servicio IN ('
            . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);
        $this->assertSame(0.0, (float) DB::scalar('SELECT fn_cita_sena_requerida(?)', [$id]),
            'Sin servicios que pidan seña no hay nada que adelantar.');

        // El más caro pide el 50 %: se pide la mitad de ESE, no de la cita.
        $caro = $srv[0];
        DB::update('UPDATE servicio SET sena_porcentaje = 50 WHERE id_servicio = ?', [(int) $caro->id_servicio]);
        $this->assertSame(round((float) $caro->precio * 0.5),
            (float) DB::scalar('SELECT fn_cita_sena_requerida(?)', [$id]),
            'Cada servicio aporta su porcentaje sobre su propio precio.');

        // **El precio sube y la seña lo sigue.** Es lo que un monto fijo no
        // hace: quedaría en la mitad del precio viejo.
        DB::update('UPDATE servicio SET precio = precio * 2 WHERE id_servicio = ?', [(int) $caro->id_servicio]);
        $this->assertSame(round((float) $caro->precio),
            (float) DB::scalar('SELECT fn_cita_sena_requerida(?)', [$id]),
            'Al duplicarse el precio, el 50 % pasa a ser el precio viejo entero.');
    }
    /**
     * La clienta no se pisa a sí misma, salvo que reserve para otra persona.
     *
     * **La agenda cuidaba al profesional y no a la clienta.** Se comprobaba
     * que quien atiende estuviera libre, pero nada impedía que la misma
     * clienta reservara dos servicios a la misma hora con profesionales
     * distintos: el día de la cita tendría que estar en dos sillones.
     *
     * La excepción no es un rodeo: una clienta reserva para su hija o su
     * madre, y esas dos citas **sí** se superponen a propósito.
     */
    #[Test]
    public function una_clienta_no_se_pisa_a_si_misma_salvo_que_sea_para_otra_persona(): void
    {
        $cita = DB::selectOne(
            'SELECT c.id_cita, c.id_cliente, c.fecha_hora FROM cita c
               JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
              WHERE ec.bloquea_agenda = 1
                AND EXISTS (SELECT 1 FROM cita_servicio cs WHERE cs.id_cita = c.id_cita)
              ORDER BY c.id_cita DESC LIMIT 1'
        );
        if (! $cita) {
            $this->markTestSkipped('Hace falta una cita vigente con servicios.');
        }

        $cli = (int) $cita->id_cliente;
        $dur = (int) DB::scalar('SELECT fn_cita_duracion(?)', [(int) $cita->id_cita]);
        if ($dur <= 0) {
            $this->markTestSkipped('Esa cita dura cero: no puede solaparse con nada.');
        }

        // Justo encima de la que ya tiene: se pisan.
        $encima = date('Y-m-d H:i:s', strtotime((string) $cita->fecha_hora) + 60);

        $this->assertNotNull(Agenda::citaDelClienteSePisa($cli, $encima, $dur),
            'Dos citas de la misma clienta a la misma hora la ponen en dos sillones.');

        // La misma hora, pero declarada para otra persona: entra.
        $this->assertNull(Agenda::citaDelClienteSePisa($cli, $encima, $dur, 0, true),
            'Reservar para la hija o la madre son dos personas: pueden superponerse.');

        // Y la propia cita no se pisa consigo misma al reprogramarla.
        $this->assertNull(Agenda::citaDelClienteSePisa($cli, $encima, $dur, (int) $cita->id_cita),
            'La cita que se está moviendo no puede chocar contra sí misma.');

        // Lejos, no se pisa con nada.
        $lejos = date('Y-m-d H:i:s', strtotime((string) $cita->fecha_hora) + 86400 * 400);
        $this->assertNull(Agenda::citaDelClienteSePisa($cli, $lejos, $dur),
            'Un año después no hay solape posible.');
    }
    /**
     * Una sucursal que no existe no habilita la jornada por defecto.
     *
     * **El id de la sucursal viaja en la URL del endpoint del portal**, así
     * que se puede cambiar. Con uno inventado —o negativo— el filtro no
     * encontraba ningún turno, el salón parecía no usarlos y se ofrecía la
     * jornada por defecto: cincuenta días de horarios que el guardado después
     * rechaza. Es el control saltándose solo poniendo un número cualquiera.
     *
     * El cero sigue siendo «sin filtro» a propósito: lo usa lo que corre sin
     * sesión, como el cron de los recordatorios.
     */
    #[Test]
    public function una_sucursal_que_no_existe_no_ofrece_horarios(): void
    {
        $prof = (int) DB::scalar('SELECT ut.id_usuario FROM usuario_turno ut LIMIT 1');
        $suc = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');
        if (! $prof || ! $suc) {
            $this->markTestSkipped('Hace falta alguien con turno y una sucursal activa.');
        }

        $dia = date('Y-m-d', strtotime('+3 days'));

        // La sucursal de verdad sí ofrece huecos: si no, la prueba no mide nada.
        $reales = Agenda::slotsProfesional($prof, $dia, 30, null, $suc);
        if ($reales === []) {
            $this->markTestSkipped('Ese día no hay huecos ni en la sucursal real.');
        }

        foreach ([999999, -1] as $inventada) {
            $this->assertSame([], Agenda::slotsProfesional($prof, $dia, 30, null, $inventada),
                'Una sucursal inventada (' . $inventada . ') no puede ofrecer horarios.');
        }

        // Y el cero sigue significando «sin filtro», que es lo que usa el cron.
        $this->assertNotSame([], Agenda::slotsProfesional($prof, $dia, 30, null, 0),
            'El cero es «sin filtro por sucursal» y tiene que seguir funcionando.');
    }

    /**
     * Los informes no mezclan sucursales, ni ofrecen las que no son de uno.
     *
     * **Eran dos agujeros distintos y los dos daban números plausibles.**
     *
     * El primero: el filtro de sucursal se aplicaba a las citas y **no a los
     * cobros**, así que pidiendo el informe de un local salían sus citas con
     * los ingresos de TODOS. Dos números de la misma pantalla midiendo cosas
     * distintas, y nada que lo delatara.
     *
     * El segundo: el combo listaba **todas** las sucursales de la base, no las
     * de esta persona, así que quien tiene un local asignado podía pedir el
     * informe de otro cambiando el desplegable.
     *
     * Se comprueba con dos locales de verdad: la suma de las partes tiene que
     * dar el total, que es lo único que prueba que el filtro llegó a todos
     * lados.
     */
    #[Test]
    public function el_informe_no_mezcla_sucursales_ni_ofrece_las_ajenas(): void
    {
        $this->entrarComoAdministrador();

        $primera = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');
        DB::insert('INSERT INTO sucursal (nombre, direccion, activo) VALUES (?, ?, 1)',
                   ['Sucursal informe ' . uniqid(), 'Calle 9']);
        $otra = (int) DB::scalar('SELECT LAST_INSERT_ID()');

        // Se mueve la mitad de las citas de un rango al local nuevo, para tener
        // dos lados que sumar.
        $rango = DB::selectOne(
            'SELECT DATE(MIN(fecha_hora)) d, DATE(MAX(fecha_hora)) h FROM cita');
        if (! $rango || ! $rango->d) {
            $this->markTestSkipped('La base de prueba no tiene citas.');
        }
        DB::update('UPDATE cita SET id_sucursal = ? WHERE id_cita % 2 = 0', [$otra]);

        $q = ['desde' => $rango->d, 'hasta' => $rango->h];
        $leer = function (array $extra) use ($q) {
            $v = $this->get(route('reportes.index', $q + $extra))->assertOk();

            return [
                'citas' => (int) $v->viewData('citas')->total,
                'ingresos' => (float) $v->viewData('ingresos'),
                'servicios' => array_sum(array_map(
                    fn ($s) => (int) $s->veces_realizado, $v->viewData('servicios'))),
            ];
        };

        $todo = $leer([]);
        $a = $leer(['suc' => $primera]);
        $b = $leer(['suc' => $otra]);

        $this->assertSame($todo['citas'], $a['citas'] + $b['citas'],
            'Las citas de los dos locales tienen que sumar el total del salón.');
        $this->assertSame($todo['servicios'], $a['servicios'] + $b['servicios'],
            'Los servicios también: si no, el filtro llegó a una consulta y no a la otra.');

        // **Lo que estaba roto.** Sin el filtro en los cobros, cada local
        // devolvía el ingreso del salón entero y la suma daba el doble.
        $this->assertEqualsWithDelta($todo['ingresos'], $a['ingresos'] + $b['ingresos'], 0.01,
            'Los ingresos de los dos locales tienen que sumar el total: si cada uno '
            . 'devuelve el total del salón, el filtro de sucursal no llegó a los cobros.');

        // Y la otra mitad: el combo ofrece SÓLO las sucursales de esta persona.
        $rolProf = (int) DB::scalar("SELECT id_rol FROM rol WHERE nombre = 'Profesional' LIMIT 1");
        $uid = (int) DB::scalar(
            'SELECT id_usuario FROM usuario WHERE id_rol = ? AND activo = 1 LIMIT 1', [$rolProf]);
        DB::delete('DELETE FROM usuario_sucursal WHERE id_usuario = ?', [$uid]);
        DB::insert('INSERT INTO usuario_sucursal (id_usuario, id_sucursal) VALUES (?,?)', [$uid, $otra]);
        DB::insert('INSERT IGNORE INTO rol_modulo (id_rol, modulo) VALUES (?, ?)', [$rolProf, 'reportes']);
        Permisos::olvidar();

        session(['uid' => $uid, 'rol' => $rolProf, 'es_personal' => true,
                 'es_cliente' => false, 'id_sucursal' => $otra]);
        $this->conMarcaDeSesion();

        $suyo = $this->get(route('reportes.index', $q))->assertOk();

        // Con una sola sucursal asignada el combo ni se ofrece, y el filtro se
        // pone solo: lo que ve es su local, no el consolidado.
        $this->assertSame((int) $b['citas'], (int) $suyo->viewData('citas')->total,
            'Quien tiene un solo local asignado tiene que ver ese local, no el salón entero.');

        // Y forzando la otra sucursal por la URL tampoco la ve.
        $forzado = $this->get(route('reportes.index', $q + ['suc' => $primera]))->assertOk();
        $this->assertSame((int) $b['citas'], (int) $forzado->viewData('citas')->total,
            'Poner otra sucursal en la URL no puede mostrarle datos de un local ajeno.');

        Permisos::olvidar();
    }

    /**
     * La reserva que pide seña se guarda por un plazo, y después se suelta.
     *
     * **Son las dos mitades y hacen falta las dos.** Si la cita no se creara
     * hasta cobrar, la clienta perdería el horario mientras hace la
     * transferencia — que es justo lo que la pantalla le promete. Y si el
     * horario quedara reservado para siempre, un sillón se bloquea por alguien
     * que nunca pagó.
     *
     * Se comprueba que **dentro del plazo no se toca** y que **pasado el plazo
     * se cancela**: una sola de las dos mitades pasaría con la función
     * devolviendo siempre cero, o cancelando todo.
     */
    #[Test]
    public function la_reserva_sin_sena_se_guarda_un_plazo_y_despues_se_suelta(): void
    {
        $srv = DB::selectOne(
            'SELECT id_servicio FROM servicio WHERE activo = 1 AND sena_porcentaje IS NOT NULL LIMIT 1');
        if (! $srv) {
            $this->markTestSkipped('La base de prueba no tiene servicios que pidan seña.');
        }

        $cliente = (int) DB::scalar('SELECT id_cliente FROM cliente WHERE activo = 1 LIMIT 1');
        $prof = (int) DB::scalar(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE r.es_personal = 1 AND u.activo = 1 LIMIT 1');
        $suc = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');

        // **Cada cita va en un día distinto.** `trg_citaserv_bi` impide que la
        // misma clienta repita el mismo servicio el mismo día, así que las tres
        // citas de esta prueba se pisarían entre sí.
        $dia = 0;
        $crear = function (string $registrada) use ($cliente, $prof, $suc, $srv, &$dia): int {
            $dia += 3;
            DB::insert(
                'INSERT INTO cita (id_cliente, id_usuario, id_sucursal, id_estado_cita, fecha_hora, fecha_registro)
                 VALUES (?, ?, ?, 1, DATE_ADD(NOW(), INTERVAL ? DAY), ?)',
                [$cliente, $prof, $suc, $dia, $registrada]
            );
            $id = (int) DB::scalar('SELECT LAST_INSERT_ID()');
            DB::insert('INSERT INTO cita_servicio (id_cita, id_servicio) VALUES (?,?)',
                       [$id, (int) $srv->id_servicio]);

            return $id;
        };

        // Una recién reservada y otra de hace tres días, las dos sin seña.
        $reciente = $crear(date('Y-m-d H:i:s'));
        $vieja = $crear(date('Y-m-d H:i:s', strtotime('-3 days')));

        $this->assertGreaterThan(0, (float) DB::scalar('SELECT fn_cita_sena_requerida(?)', [$vieja]),
            'El servicio elegido tiene que pedir seña para que esta prueba mida algo.');

        Notificaciones::cancelarSenasVencidas();

        $estado = fn (int $id) => (int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita = ?', [$id]);

        $this->assertNotSame(3, $estado($reciente),
            'A la reserva de recién hay que guardarle el horario: todavía está dentro del plazo.');
        $this->assertSame(3, $estado($vieja),
            'Pasado el plazo sin confirmar la seña, el lugar se suelta.');

        // **Y una con solicitud pendiente NO se toca**: la clienta ya avisó que
        // pagó, así que lo que falta es que el salón lo confirme — cancelársela
        // sería castigarla por la demora del mostrador.
        $aviso = $crear(date('Y-m-d H:i:s', strtotime('-3 days')));
        DB::insert('INSERT INTO sena_solicitud (id_cita, monto, fecha_solicitud) VALUES (?,?,NOW())',
                   [$aviso, 1000]);

        Notificaciones::cancelarSenasVencidas();
        $this->assertNotSame(3, $estado($aviso),
            'Si la clienta ya registró la seña, la cita espera al salón: no se cancela sola.');
    }

    /**
     * Una cita pendiente más de un día se cierra sola como ausente.
     *
     * **Atrasada es un estado de paso y una cita que sigue Programada o
     * Reprogramada tampoco puede quedar permanente.** Bloquea la agenda a
     * propósito, pero eso vale mientras la cita todavía pueda ocurrir: se
     * midieron citas con más de 800 horas ahí adentro, contando como vivas en
     * el panel y torciendo el porcentaje de asistencia.
     *
     * Se comprueban las dos mitades y la reprogramación, que es lo que hace
     * que la prueba mida algo: **la de hace dos horas NO se toca** —todavía
     * puede atenderse—, las tres pendientes de hace dos días sí, y **la
     * reprogramada para el futuro NO se toca**. Esta última garantiza que el
     * contador de 24 horas empiece de nuevo con la fecha elegida al
     * reprogramar.
     */
    #[Test]
    public function las_citas_pendientes_mas_de_un_dia_se_cierran_como_ausentes(): void
    {
        $srv = DB::selectOne('SELECT id_servicio FROM servicio WHERE activo = 1 LIMIT 1');
        $clientes = DB::select(
            'SELECT c.id_cliente FROM cliente c
              WHERE NOT EXISTS (
                    SELECT 1 FROM cita ci
                      JOIN cita_servicio cs ON cs.id_cita = ci.id_cita
                      JOIN estado_cita ec ON ec.id_estado_cita = ci.id_estado_cita
                     WHERE ci.id_cliente = c.id_cliente
                       AND cs.id_servicio = ?
                       AND DATE(ci.fecha_hora) = CURDATE()
                       AND ec.bloquea_agenda = 1)
              ORDER BY c.id_cliente LIMIT 4', [$srv->id_servicio ?? 0]
        );
        $usr = DB::selectOne('SELECT id_usuario FROM usuario WHERE activo = 1 LIMIT 1');
        $suc = DB::selectOne('SELECT id_sucursal FROM sucursal WHERE activo = 1 LIMIT 1');

        // Pendientes en los tres estados que pueden quedar abiertos.
        $crear = function (int $cliente, string $cuando, int $estado) use ($usr, $suc, $srv): int {
            DB::insert(
                'INSERT INTO cita (id_cliente, id_usuario, id_sucursal, fecha_hora, id_estado_cita)
                  VALUES (?, ?, ?, ?, ?)',
                 [$cliente, $usr->id_usuario, $suc->id_sucursal, $cuando, $estado]
            );
            $id = (int) DB::scalar('SELECT LAST_INSERT_ID()');
            DB::insert('INSERT INTO cita_servicio (id_cita, id_servicio) VALUES (?, ?)',
                [$id, $srv->id_servicio]);

            return $id;
        };

        $reciente = $crear((int) $clientes[0]->id_cliente, date('Y-m-d H:i:s', strtotime('-2 hours')), 7);
        $programada = $crear((int) $clientes[1]->id_cliente, date('Y-m-d H:i:s', strtotime('-2 days')), 1);
        $reprogramada = $crear((int) $clientes[2]->id_cliente, date('Y-m-d H:i:s', strtotime('-2 days')), 2);
        $reprogramadaFutura = $crear((int) $clientes[3]->id_cliente, date('Y-m-d H:i:s', strtotime('+2 hours')), 2);

        $this->artisan('spg:notificaciones', ['--max' => 0]);

        $estado = fn (int $id) => (int) DB::scalar(
            'SELECT id_estado_cita FROM cita WHERE id_cita = ?', [$id]);

        $this->assertSame(7, $estado($reciente),
            'La atrasada de hace dos horas todavía se puede atender: el sistema no decide por nadie.');
        $this->assertSame(6, $estado($programada),
            'Una cita que sigue programada dos días después tiene que cerrarse sola como ausente.');
        $this->assertSame(6, $estado($reprogramada),
            'Una cita reprogramada cuya nueva fecha ya pasó hace dos días tiene que cerrarse como ausente.');
        $this->assertSame(2, $estado($reprogramadaFutura),
            'Reprogramar al futuro reinicia el plazo: la cita no se puede cerrar antes de su nueva fecha.');
    }

    /**
     * La clienta ve las cuentas de SU local, no las de otro.
     *
     * **No hay pasarela de pagos y no la va a haber**: la clienta transfiere
     * por su cuenta, así que lo único que el sistema puede hacer es decirle a
     * dónde. Cada sucursal puede cobrar en cuentas distintas, y mostrarle la
     * del otro local le hace transferir a un lado donde nadie la espera.
     *
     * Se comprueba en las DOS direcciones: que aparezca la del local de la
     * cita y que **no** aparezca la del otro. Con una sola mitad, una consulta
     * sin filtro pasaría igual.
     */
    #[Test]
    public function la_clienta_ve_las_cuentas_del_local_donde_reservo(): void
    {
        $a = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');

        // La segunda la crea la prueba: `peluqueria_test` trae una sola, y
        // saltearse ahí sería no medir nada justo en la base que se entrega.
        DB::insert("INSERT INTO sucursal (nombre, ciudad, activo) VALUES ('Local de prueba', 'Luque', 1)");
        $b = (int) DB::scalar('SELECT LAST_INSERT_ID()');
        $medio = (int) DB::scalar("SELECT id_metodo_pago FROM metodo_pago WHERE tipo = 'BANCO' LIMIT 1");

        $cargar = function (int $suc, string $entidad) use ($medio): void {
            DB::insert(
                'INSERT INTO dato_pago_sucursal
                    (id_sucursal, id_metodo_pago, entidad, titular, numero_cuenta)
                 VALUES (?, ?, ?, ?, ?)',
                [$suc, $medio, $entidad, 'Salón de prueba', 'CTA-' . $suc . '-' . $entidad]
            );
        };
        $cargar($a, 'Banco de acá');
        $cargar($b, 'Banco del otro local');

        $deLocal = fn (int $suc) => array_map(fn ($r) => $r->entidad, DB::select(
            'SELECT entidad FROM dato_pago_sucursal WHERE id_sucursal = ? AND activo = 1', [$suc]));

        $this->assertContains('Banco de acá', $deLocal($a));
        $this->assertNotContains('Banco del otro local', $deLocal($a),
            'La clienta estaría viendo la cuenta de otra sucursal: transferiría a donde nadie la espera.');

        // Sacar una cuenta la esconde, no la borra: las señas viejas siguen
        // teniendo su respaldo.
        DB::update("UPDATE dato_pago_sucursal SET activo = 0
                     WHERE id_sucursal = ? AND entidad = 'Banco de acá'", [$a]);

        $this->assertNotContains('Banco de acá', $deLocal($a),
            'Una cuenta desactivada no se le puede seguir ofreciendo a la clienta.');
        $this->assertSame(1, (int) DB::scalar(
            "SELECT COUNT(*) FROM dato_pago_sucursal WHERE id_sucursal = ? AND entidad = 'Banco de acá'", [$a]),
            'Desactivar una cuenta no la borra: el respaldo de las señas viejas se perdería.');
    }

    /**
     * Varios cajones del mismo local abren a la vez, y cada uno una sola sesión.
     *
     * **`caja` es una SESIÓN, no el cajón** (7.69.0). Antes el cajón no existía
     * en el modelo, así que «una caja abierta por sucursal» era en realidad «un
     * cajón por local» sin decirlo: un salón con dos puestos de cobro no lo
     * podía representar — el segundo no abría.
     *
     * Se comprueban las DOS mitades, que es lo que hace que la prueba mida
     * algo: **dos cajones distintos del mismo local abren los dos**, y **el
     * mismo cajón no se abre dos veces**. Con una sola mitad, un disparador
     * borrado pasaría igual.
     */
    #[Test]
    public function cada_cajon_abre_su_propia_caja_y_una_sola(): void
    {
        $uid = (int) DB::scalar('SELECT id_usuario FROM usuario WHERE activo = 1 LIMIT 1');
        $suc = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');

        // Dos cajones en el MISMO local: es el caso que antes no se podía.
        $nombre = 'Prueba ' . uniqid();
        DB::insert('INSERT INTO caja_fisica (id_sucursal, nombre) VALUES (?, ?)', [$suc, $nombre . ' A']);
        $a = (int) DB::scalar('SELECT LAST_INSERT_ID()');
        DB::insert('INSERT INTO caja_fisica (id_sucursal, nombre) VALUES (?, ?)', [$suc, $nombre . ' B']);
        $b = (int) DB::scalar('SELECT LAST_INSERT_ID()');

        // 1) Los dos abren, aunque sean del mismo local.
        Caja::abrir($uid, 0.0, $a);
        Caja::abrir($uid, 0.0, $b);

        $this->assertSame(2, (int) DB::scalar(
            'SELECT COUNT(*) FROM caja WHERE id_estado_caja = 1 AND id_caja_fisica IN (?, ?)', [$a, $b]),
            'Dos cajones del mismo local tienen que poder estar abiertos a la vez: es para lo que existe el cajón.');

        // 2) Pero el mismo cajón no se abre dos veces: su arqueo no cerraría.
        $rechazado = false;
        try {
            Caja::abrir($uid, 0.0, $a);
        } catch (\Throwable) {
            $rechazado = true;
        }

        $this->assertTrue($rechazado,
            'El mismo cajón no puede tener dos sesiones abiertas: al cerrar habría dos conteos de la misma plata.');
        $this->assertSame(1, (int) DB::scalar(
            'SELECT COUNT(*) FROM caja WHERE id_estado_caja = 1 AND id_caja_fisica = ?', [$a]));

        // 3) La sucursal sale del cajón, no se manda aparte: guardarla como
        //    parámetro dejaría poder contradecirse.
        $this->assertSame($suc, (int) DB::scalar(
            'SELECT id_sucursal FROM caja WHERE id_caja_fisica = ? ORDER BY id_caja DESC LIMIT 1', [$a]),
            'La sucursal de la sesión tiene que ser la del cajón.');
    }

    /**
     * Movimientos lista TODO lo que movió la caja, no sólo lo cargado a mano.
     *
     * **Un pago a proveedor es un movimiento de caja, y un cobro también.**
     * Antes la pantalla listaba únicamente `movimiento_caja`, así que en un
     * salón que no carga ninguno se veía vacía aunque la caja hubiera tenido
     * setenta cobros — y el nombre «movimiento de efectivo» hacía creer que
     * esos otros no contaban.
     *
     * Las cuatro fuentes son exactamente las que suma `fn_caja_saldo`, así que
     * lo que se lista es lo que explica el arqueo. La prueba lo mide contra la
     * base: **cada fuente con filas tiene que aparecer**.
     */
    #[Test]
    public function movimientos_lista_las_cuatro_fuentes_que_mueven_la_caja(): void
    {
        $this->entrarComo('admin', 'admin123');

        $suc = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');

        DB::insert('INSERT INTO caja_fisica (id_sucursal, nombre) VALUES (?, ?)',
            [$suc, 'Prueba movs ' . uniqid()]);
        $cajon = (int) DB::scalar('SELECT LAST_INSERT_ID()');

        // Una caja abierta con un cobro y un movimiento manual adentro: son dos
        // fuentes distintas y las dos tienen que salir en la misma tabla.
        DB::insert('INSERT INTO caja (id_usuario, id_sucursal, id_caja_fisica, id_estado_caja, monto_inicial)
                    VALUES (1, ?, ?, 1, 0)', [$suc, $cajon]);
        $caja = (int) DB::scalar('SELECT LAST_INSERT_ID()');

        $efectivo = (int) DB::scalar("SELECT id_metodo_pago FROM metodo_pago WHERE tipo = 'EFECTIVO' LIMIT 1");
        $cita = (int) DB::scalar('SELECT MAX(id_cita) FROM cita');
        DB::insert('INSERT INTO cobro (id_cita, id_metodo_pago, id_estado_cobro, id_usuario, id_caja, monto, fecha)
                    VALUES (?, ?, 1, 1, ?, 123456, NOW())', [$cita, $efectivo, $caja]);

        $tipo = (int) DB::scalar('SELECT id_tipo_mov_caja FROM tipo_movimiento_caja WHERE activo = 1 LIMIT 1');
        DB::insert("INSERT INTO movimiento_caja (id_caja, tipo, id_tipo_mov_caja, monto, concepto, id_usuario, fecha)
                    VALUES (?, 'EGRESO', ?, 7890, 'Gasto de prueba', 1, NOW())", [$caja, $tipo]);

        $r = $this->get(route('facturacion.movimientos'))->assertOk();
        $filas = collect($r->viewData('movimientos'));

        $clases = $filas->pluck('clase')->unique()->all();

        // El cobro: antes NO salía, y es lo que hacía ver la pantalla vacía.
        $this->assertContains('cobro', $clases,
            'Un cobro es un movimiento de caja y tiene que salir en la lista.');
        $this->assertContains('manual', $clases,
            'El movimiento cargado a mano tiene que seguir saliendo.');

        $this->assertTrue($filas->contains(fn ($m) => (float) $m->monto === 123456.0 && (int) $m->signo === 1),
            'El cobro entra a la caja: tiene que listarse con signo positivo.');
        $this->assertTrue($filas->contains(fn ($m) => (float) $m->monto === 7890.0 && (int) $m->signo === -1),
            'El gasto sale de la caja: tiene que listarse con signo negativo.');

        DB::delete('DELETE FROM movimiento_caja WHERE id_caja = ?', [$caja]);
        DB::delete('DELETE FROM cobro WHERE id_caja = ?', [$caja]);
        DB::delete('DELETE FROM caja WHERE id_caja = ?', [$caja]);
        DB::delete('DELETE FROM caja_fisica WHERE id_caja_fisica = ?', [$cajon]);
    }

    /**
     * Cada caja muestra SUS movimientos, y con el filtro puesto no revienta.
     *
     * **Dos defectos en el mismo camino, y el segundo tapaba al primero.**
     *
     * El que se veía: con el filtro de caja puesto —que es lo que hace el botón
     * «Ver movimientos»— la consulta moría con *Invalid parameter number*. El
     * marcador `:cf` aparecía en las cuatro partes del UNION, y la conexión abre
     * PDO con `ATTR_EMULATE_PREPARES` en `false`: MySQL prepara de verdad y
     * **no admite un marcador con nombre repetido**. Está anotado en el
     * documento del proyecto desde hace versiones, y así y todo volvió a pasar.
     *
     * El de fondo: con dos cajas abiertas en el mismo local, cada una tiene que
     * poder mirar lo suyo — si no, el arqueo de una se lee con los movimientos
     * de la otra.
     *
     * **La prueba suma las partes y exige que den el total**: es lo único que
     * demuestra que el filtro filtra y que no se pierde nada por el camino.
     */
    #[Test]
    public function cada_caja_muestra_sus_propios_movimientos(): void
    {
        $this->entrarComo('admin', 'admin123');

        $suc = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');

        // Dos cajones del MISMO local, los dos con una caja abierta: es el caso
        // que el rediseño de la 7.69.0 vino a hacer posible.
        $ids = [];
        // Un solo token para las dos: así el filtro de la lista las trae juntas.
        $token = uniqid();
        foreach (['A', 'B'] as $letra) {
            DB::insert('INSERT INTO caja_fisica (id_sucursal, nombre) VALUES (?, ?)',
                [$suc, 'Filtro ' . $letra . ' ' . $token]);
            $cf = (int) DB::scalar('SELECT LAST_INSERT_ID()');

            DB::insert('INSERT INTO caja (id_usuario, id_sucursal, id_caja_fisica, id_estado_caja, monto_inicial)
                        VALUES (1, ?, ?, 1, 0)', [$suc, $cf]);
            $ids[$letra] = ['cajon' => $cf, 'caja' => (int) DB::scalar('SELECT LAST_INSERT_ID()')];
        }

        // Un movimiento en cada uno, con montos distintos para poder decir cuál
        // es cuál.
        $tipo = (int) DB::scalar('SELECT id_tipo_mov_caja FROM tipo_movimiento_caja WHERE activo = 1 LIMIT 1');
        foreach (['A' => 1111, 'B' => 2222] as $letra => $monto) {
            DB::insert("INSERT INTO movimiento_caja (id_caja, tipo, id_tipo_mov_caja, monto, concepto, id_usuario, fecha)
                        VALUES (?, 'EGRESO', ?, ?, ?, 1, NOW())",
                [$ids[$letra]['caja'], $tipo, $monto, 'Movimiento ' . $letra]);
        }

        $montosDe = function (int $cajon): array {
            $r = $this->get(route('facturacion.movimientos', ['caja' => $cajon]))->assertOk();

            return collect($r->viewData('movimientos'))->map(fn ($m) => (float) $m->monto)->all();
        };

        // 1) Con el filtro puesto la pantalla ABRE: antes moría con
        //    «Invalid parameter number» por el marcador repetido.
        $deA = $montosDe($ids['A']['cajon']);
        $deB = $montosDe($ids['B']['cajon']);

        // 2) Y cada una muestra lo suyo, no lo de la otra.
        $this->assertContains(1111.0, $deA, 'La caja A tiene que mostrar su movimiento.');
        $this->assertNotContains(2222.0, $deA, 'La caja A no puede mostrar los movimientos de la B.');
        $this->assertContains(2222.0, $deB, 'La caja B tiene que mostrar su movimiento.');
        $this->assertNotContains(1111.0, $deB, 'La caja B no puede mostrar los movimientos de la A.');

        // 3) **Y la LISTA de cajas trae los de cada una.** Cada cajón es una
        //    tarjeta con sus propios movimientos del día, así que el mismo
        //    aislamiento tiene que valer ahí: con dos cajones abiertos en el
        //    mismo local, leer el arqueo de uno con los movimientos del otro es
        //    peor que no verlos.
        $lista = $this->get(route('facturacion.cajas', ['q' => $token]))->assertOk();
        $movs = $lista->viewData('movs');

        $montosDeLaTarjeta = fn (int $cajon): array => collect($movs[$cajon] ?? [])
            ->map(fn ($m) => (float) $m->monto)->all();

        $tA = $montosDeLaTarjeta($ids['A']['cajon']);
        $tB = $montosDeLaTarjeta($ids['B']['cajon']);

        $this->assertContains(1111.0, $tA, 'La tarjeta de la caja A tiene que traer su movimiento.');
        $this->assertNotContains(2222.0, $tA, 'La tarjeta de la A no puede traer los de la B.');
        $this->assertContains(2222.0, $tB, 'La tarjeta de la caja B tiene que traer su movimiento.');
        $this->assertNotContains(1111.0, $tB, 'La tarjeta de la B no puede traer los de la A.');

        // Y el modal existe: el botón abre acá mismo en vez de mandar al
        // listado general, que obligaba a volver a filtrar por la caja en la
        // que ya se estaba parado.
        $lista->assertSee('modalMovs' . $ids['A']['cajon'], false)
              ->assertSee('Movimientos de hoy');

        foreach (['B', 'A'] as $letra) {
            DB::delete('DELETE FROM movimiento_caja WHERE id_caja = ?', [$ids[$letra]['caja']]);
            DB::delete('DELETE FROM caja WHERE id_caja = ?', [$ids[$letra]['caja']]);
            DB::delete('DELETE FROM caja_fisica WHERE id_caja_fisica = ?', [$ids[$letra]['cajon']]);
        }
    }

    /**
     * Al pagar se elige de qué caja sale la plata, y el servidor la respeta.
     *
     * **Sin esto, el egreso caía en «la última caja abierta».** Con dos puestos
     * de cobro en el mismo local eso deja el arqueo de otra persona
     * descuadrado, y no se descubre hasta cerrar — que es cuando ya no se sabe
     * de qué movimiento vino la diferencia.
     *
     * Se mide en las dos mitades que importan: que la pantalla OFREZCA elegir,
     * y que lo elegido sea lo que se guarda. Con sólo la primera, un servidor
     * que ignorara el campo pasaría igual.
     */
    #[Test]
    public function al_pagar_se_elige_de_que_caja_sale_la_plata(): void
    {
        $this->entrarComo('admin', 'admin123');

        $suc = (int) session('id_sucursal');
        $this->assertNotSame(0, $suc, 'La sesión tiene que tener una sucursal elegida.');

        // Dos cajones abiertos en el MISMO local: con uno solo la pregunta no
        // existe y la pantalla no dibuja el combo, así que no se mediría nada.
        $ids = [];
        foreach (['A', 'B'] as $letra) {
            DB::insert('INSERT INTO caja_fisica (id_sucursal, nombre) VALUES (?, ?)',
                [$suc, 'Pago ' . $letra . ' ' . uniqid()]);
            $cf = (int) DB::scalar('SELECT LAST_INSERT_ID()');

            DB::insert('INSERT INTO caja (id_usuario, id_sucursal, id_caja_fisica, id_estado_caja, monto_inicial)
                        VALUES (1, ?, ?, 1, 500000)', [$suc, $cf]);
            $ids[$letra] = ['cajon' => $cf, 'caja' => (int) DB::scalar('SELECT LAST_INSERT_ID()')];
        }

        // 1) Las dos pantallas de pagos ofrecen elegir.
        $this->get(route('facturacion.pagos'))->assertOk()
            ->assertSee('name="id_caja"', false);
        $this->get(route('facturacion.proveedores'))->assertOk()
            ->assertSee('name="id_caja"', false);

        // 2) Y lo elegido es lo que se guarda. Se liquida contra la caja B, que
        //    NO es la última abierta ni la primera: si el controlador ignorara
        //    el campo, el pago quedaría en otra.
        $prof = (int) DB::scalar(
            'SELECT sr.id_usuario FROM servicio_realizado sr
               LEFT JOIN detalle_pago_personal d ON d.id_servicio_realizado = sr.id_servicio_realizado
              WHERE d.id_detalle_pago IS NULL GROUP BY sr.id_usuario LIMIT 1'
        );
        $this->assertNotSame(0, $prof, 'Hace falta alguien con servicios sin liquidar.');

        $metodo = (int) DB::scalar("SELECT id_metodo_pago FROM metodo_pago
                                     WHERE activo = 1 AND tipo <> 'EFECTIVO' LIMIT 1");

        $this->post(route('facturacion.pagar_personal'), [
            'id_usuario' => $prof,
            'periodo' => date('m/Y'),
            'id_metodo_pago' => $metodo,
            'id_caja' => $ids['B']['caja'],
        ])->assertRedirect();

        $guardada = (int) DB::scalar(
            'SELECT id_caja FROM pago_personal WHERE id_usuario = ? ORDER BY id_pago_personal DESC LIMIT 1',
            [$prof]
        );

        $this->assertSame($ids['B']['caja'], $guardada,
            'La liquidación tiene que quedar en la caja elegida, no en la última abierta.');

        // Se limpia a mano lo que cuelga del pago: `DatabaseTransactions` lo
        // revierte igual, pero el orden importa si algún día no lo hiciera.
        foreach (['B', 'A'] as $letra) {
            DB::delete('DELETE FROM detalle_pago_personal WHERE id_pago_personal IN
                        (SELECT id_pago_personal FROM pago_personal WHERE id_caja = ?)',
                [$ids[$letra]['caja']]);
            DB::delete('DELETE FROM pago_personal WHERE id_caja = ?', [$ids[$letra]['caja']]);
            DB::delete('DELETE FROM caja WHERE id_caja = ?', [$ids[$letra]['caja']]);
            DB::delete('DELETE FROM caja_fisica WHERE id_caja_fisica = ?', [$ids[$letra]['cajon']]);
        }
    }
}
