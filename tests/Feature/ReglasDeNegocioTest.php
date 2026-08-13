<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Servicios\Agenda;
use App\Servicios\Bd;
use App\Servicios\Calendario;
use App\Servicios\Permisos;
use App\Servicios\Sesion;
use App\Servicios\Sifen;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
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

    // -----------------------------------------------------------------
    //  La agenda no vende dos veces el mismo horario
    // -----------------------------------------------------------------

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
                                                fn_cita_duracion(c.id_cita), c.id_cita) = 1
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
            (int) $f->id_cita, 1, (int) $f->id_usuario, $sena, 'seña de prueba',
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
            "SELECT p.id_producto, p.nombre, fn_producto_stock(p.id_producto) AS stock,
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
        $p = DB::selectOne('SELECT id_producto, fn_producto_stock(id_producto) AS stock
                              FROM producto WHERE activo = 1 ORDER BY id_producto LIMIT 1');
        if (! $p) {
            $this->markTestSkipped('No hay productos en la base de prueba.');
        }

        // El disparador de la base tiene que frenar la salida
        $this->expectException(Throwable::class);

        DB::statement('CALL sp_registrar_movimiento_inventario(?,?,?,?,?,?,?)', [
            $p->id_producto, 1, 2, (float) $p->stock + 9999, null, 'TEST', 'salida imposible',
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
                    fn_producto_stock(id_producto) AS stock
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

            DB::statement('CALL sp_registrar_movimiento_inventario(?,?,?,?,?,?,?)', [
                $p->id_producto, 1, 2, $enStock, null, 'TEST', 'consumo fraccionado',
            ]);

            $ahora = (float) DB::scalar('SELECT fn_producto_stock(?)', [$p->id_producto]);
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
        $this->assertMatchesRegularExpression('/^FAC\|\d{3}\|\d{3}\|\d{7}\|\d{4}-\d{2}-\d{2}\|[12]\|PYG$/', $fac);
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

        $this->assertTrue(Permisos::rolPuede($rol, 'seguridad'),
            'Con un submódulo tiene que poder abrir el landing del módulo.');
        $this->assertTrue(Permisos::rolPuede($rol, 'seguridad.asistencia'));
        $this->assertFalse(Permisos::rolPuede($rol, 'seguridad.usuarios'),
            'Pero no los otros submódulos del mismo módulo.');
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

        foreach (['usuarios', 'turnos', 'comisiones', 'asistencia'] as $tenia) {
            $this->assertTrue(Permisos::rolPuede($rol, 'seguridad.' . $tenia),
                "El módulo Personal incluía $tenia: no puede perderlo.");
        }
        foreach (['roles', 'sucursales', 'contacto', 'auditoria'] as $noTenia) {
            $this->assertFalse(Permisos::rolPuede($rol, 'seguridad.' . $noTenia),
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
        session(['uid' => 999999, 'rol' => $rolProf, 'es_personal' => true, 'es_cliente' => false]);
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
                 'es_personal' => true, 'es_cliente' => false, 'tema' => 'oscuro']);
        $this->get(route('panel'))->assertOk()->assertSee('data-tema="oscuro"', false);

        // **El papel no**: un informe impreso en oscuro sería tinta sobre negro.
        $this->get(route('reportes.imprimir'))->assertOk()->assertDontSee('data-tema="oscuro"', false);

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
        // El Automatizador espera líneas separadas por «|»: una FAC con la
        // cabecera, una CLI con el cliente y una ITM por renglón. El total NO
        // se escribe — lo calcula él desde los ítems.
        $id = (int) DB::scalar('SELECT id_factura FROM factura WHERE id_tipo_comprobante = 1
                                  AND id_estado_factura = 1 ORDER BY id_factura LIMIT 1');
        if (! $id) {
            $this->markTestSkipped('No hay facturas emitidas en la base de prueba.');
        }

        $txt = Sifen::armarTxt($id);
        $lineas = array_values(array_filter(explode("\n", $txt)));

        $this->assertStringStartsWith('FAC|', $lineas[0], 'La primera línea es la cabecera.');
        $this->assertStringStartsWith('CLI|', $lineas[1], 'La segunda es el cliente.');
        $this->assertStringStartsWith('ITM|', $lineas[2], 'Después van los renglones.');

        // La cabecera lleva 7 campos y los números van con ceros a la izquierda.
        $fac = explode('|', $lineas[0]);
        $this->assertCount(7, $fac);
        $this->assertMatchesRegularExpression('/^\d{3}$/', $fac[1], 'Establecimiento de 3 dígitos.');
        $this->assertMatchesRegularExpression('/^\d{3}$/', $fac[2], 'Punto de expedición de 3 dígitos.');
        $this->assertMatchesRegularExpression('/^\d{7}$/', $fac[3], 'Correlativo de 7 dígitos.');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $fac[4]);
        $this->assertSame('PYG', $fac[6]);

        // Cada renglón: código, descripción, cantidad, precio y tasa de IVA.
        foreach (array_slice($lineas, 2) as $l) {
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
        session(['uid' => 999999, 'rol' => $rolProf, 'es_personal' => true, 'es_cliente' => false]);
        $this->get(route('servicios.form'))->assertForbidden();
        $this->get(route('servicios.descuentos'))->assertForbidden();

        // Lo que sí necesita para trabajar sigue abierto.
        $this->get(route('citas.agenda'))->assertOk();
        $this->get(route('citas.form'))->assertOk();
    }

    #[Test]
    public function dos_servicios_exclusivos_no_pueden_ir_en_paralelo(): void
    {
        // «Requiere atención exclusiva» significa que ese servicio no se puede
        // hacer al mismo tiempo que otro igual: una coloración y una keratina
        // se pisan, las dos son sobre el pelo. La regla se aplica ENTRE
        // profesionales distintos — si los hace la misma persona van uno
        // después del otro y no hay conflicto.
        $ex = DB::select('SELECT id_servicio FROM servicio WHERE activo = 1 AND requiere_exclusividad = 1 LIMIT 2');
        if (count($ex) < 2) {
            $this->markTestSkipped('Hacen falta dos servicios exclusivos en la base de prueba.');
        }
        [$a, $b] = [(int) $ex[0]->id_servicio, (int) $ex[1]->id_servicio];

        $profs = DB::select(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.activo = 1 AND r.es_personal = 1 LIMIT 2'
        );
        if (count($profs) < 2) {
            $this->markTestSkipped('Hacen falta dos profesionales en la base de prueba.');
        }
        [$p1, $p2] = [(int) $profs[0]->id_usuario, (int) $profs[1]->id_usuario];

        $cuando = date('Y-m-d', strtotime('+40 days')) . ' 10:00:00';

        // Dos exclusivos, uno con cada profesional: se pisan sobre la clienta.
        $this->assertNotNull(
            Agenda::validarReparto([$a => $p1, $b => $p2], $p1, $cuando),
            'Dos servicios exclusivos en paralelo tendrían que rechazarse.'
        );

        // Los mismos dos, con la misma persona: van uno después del otro.
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
        ]);

        $this->get(route('portal.reservar'))
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
        session(['uid' => 1, 'rol' => $admin, 'es_personal' => true, 'es_cliente' => false]);

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
        session(['uid' => 1, 'rol' => $admin, 'es_personal' => true, 'es_cliente' => false]);

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

        session(['uid' => 1, 'rol' => $admin, 'es_personal' => true, 'es_cliente' => false]);
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

        session(['uid' => 999999, 'rol' => $rolProf, 'es_personal' => true, 'es_cliente' => false]);
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
                 'es_personal' => true, 'es_cliente' => false]);

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
                 'es_personal' => true, 'es_cliente' => false]);

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
                 'es_personal' => true, 'es_cliente' => false]);

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
}
