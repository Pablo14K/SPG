<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\AvisoInterno;
use App\Servicios\Acompanantes;
use App\Servicios\Agenda;
use App\Servicios\Alertas;
use App\Servicios\Alergias;
use App\Servicios\Bd;
use App\Servicios\Caja;
use App\Servicios\Canje;
use App\Servicios\Cuenta;
use App\Servicios\Facturacion;
use App\Servicios\Calendario;
use App\Servicios\Navegacion;
use App\Servicios\Notificaciones;
use App\Servicios\Config;
use App\Servicios\Pagos;
use App\Servicios\Perfil;
use App\Servicios\Permisos;
use App\Servicios\Notificaciones as NotificacionesSGP;
use App\Servicios\Sesion;
use App\Servicios\Sifen;
use App\Servicios\Sucursales;
use App\Servicios\WebAuthn;
use App\Servicios\CitasVencidas;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
    /**
     * Un PNG de 1x1 de verdad, para las pruebas que suben una imagen.
     *
     * **No se usa `UploadedFile::fake()->image()`**: eso necesita GD, y el
     * contenedor no la trae. Y tampoco serviría un archivo con la extensión
     * cambiada — `Imagen::guardar()` mira el CONTENIDO con `getimagesize`,
     * que es justamente su defensa principal, así que la prueba tiene que
     * darle algo que de verdad sea una imagen.
     */
    private const PNG_MINIMO = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJ'
        . 'AAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

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
        // **`INSERT IGNORE` no alcanzaba, y el nombre del método prometía otra
        // cosa.** Si esa persona ya tiene fila de hoy —porque alguien la marcó
        // ausente desde Asistencia, que es lo que pasó con la base cargada del
        // 28/08— el INSERT se ignoraba en silencio, `hora_entrada` quedaba en
        // NULL y la atención se rechazaba por falta de fichaje. La prueba medía
        // eso en vez de la regla.
        //
        // El postcondición es «esta persona tiene entrada marcada hoy», así que
        // se escribe igual: `justificada = NULL` es presente.
        DB::insert(
            'INSERT INTO asistencia (id_turno, id_usuario, fecha, hora_entrada, id_usuario_registro)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE hora_entrada = VALUES(hora_entrada),
                                     justificada = NULL',
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
        // **La prueba crea la cita que necesita.** Antes la buscaba en la base
        // y se salteaba si no la encontraba: el día que el mes simulado se
        // quedó sin citas futuras dejó de medir nada, en silencio. El horario
        // sale de `Agenda::slots()`, así que cae en un hueco de verdad libre
        // —ni tapado por una ausencia, ni fuera del turno—, que son los dos
        // casos en que `fn_verificar_disponibilidad` diría «no» con razón y la
        // segunda mitad fallaría por un motivo que no es el que se prueba.
        $cita = $this->citaFuturaAgendada();

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
        // La cita la crea la prueba: buscarla en la base la dejaba salteada el
        // día que el mes simulado se quedó sin citas futuras.
        $cita = $this->citaFuturaAgendada();

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
            [(int) $cita->id_cliente, (int) $cita->id_cita, (int) $cita->id_usuario, $tipo, 1, $otra, null]);

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
        // **La cita tiene que tener espacio para la seña.** «La última con
        // servicios» puede estar ya cobrada entera, y ahí `sp_registrar_sena`
        // rechaza con «el monto no puede superar lo que valen los servicios»:
        // la prueba medía el tope en vez de a qué cajón entra la plata.
        $cita = DB::selectOne(
            'SELECT c.id_cita, c.id_sucursal FROM cita c
              WHERE EXISTS (SELECT 1 FROM cita_servicio cs WHERE cs.id_cita = c.id_cita)
                AND fn_cita_total(c.id_cita) - fn_cita_sena(c.id_cita) >= 1000
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

        $html = $this->get(route('portal.reservar', ['sucursal' => $suc]))
            ->assertOk()
            ->assertSee('prof_servicio', false)    // el selector fino sigue
            ->getContent();

        // **La regla es que haya UN selector por servicio y ninguno para toda
        // la cita**, no que un texto esté o no esté. La primera versión de esta
        // prueba pedía que no apareciera «¿Con quién?», y eso dejó de medir la
        // regla en cuanto el asistente de reserva usó ese mismo título para el
        // paso que junta los combos: son los mismos nodos movidos por el JS, o
        // sea exactamente un selector por servicio.
        $this->assertDoesNotMatchRegularExpression(
            '/<select[^>]*name="id_usuario"/', $html,
            'El portal volvió a preguntar el profesional para toda la cita.'
        );

        // Uno por servicio y ni uno de más: un segundo combo con el mismo
        // `name` mandaría dos valores para el mismo servicio y ganaría el
        // último — que es lo que pasaría si el paso los COPIARA en vez de
        // moverlos.
        preg_match_all('/name="prof_servicio\[(\d+)\]"/', $html, $m);
        $this->assertSame(count($m[1]), count(array_unique($m[1])),
            'Hay más de un selector de profesional para el mismo servicio.');
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
        // **Alguien que trabaje HOY**, porque atender exige el fichaje de
        // entrada y sólo se ficha contra un turno de ese día. Tomando al primer
        // profesional a secas, la prueba pasaba o fallaba según el día de la
        // semana que tocara correrla.
        $hoy = (int) date('N');
        $turno = DB::selectOne(
            'SELECT ut.id_usuario, t.id_turno FROM usuario u
               JOIN rol r            ON r.id_rol = u.id_rol
               JOIN usuario_turno ut ON ut.id_usuario = u.id_usuario
               JOIN turno_laboral t  ON t.id_turno = ut.id_turno AND t.activo = 1
               JOIN turno_dia td     ON td.id_turno = t.id_turno AND td.dia_semana = ?
              WHERE u.activo = 1 AND r.es_personal = 1
              ORDER BY u.id_usuario LIMIT 1', [$hoy]
        );
        $prof = (int) ($turno->id_usuario ?? 0);
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

        // **La entrada marcada.** Sin fichaje, `atenderGuardar` rechaza antes
        // de llegar a los productos, así que la prueba no mediría nada de lo
        // que dice medir. Se pone acá, y no se discute la regla: es la misma
        // que impide que la comisión se le cargue a quien no estuvo.
        DB::insert('INSERT INTO asistencia (id_usuario, id_turno, fecha, hora_entrada) VALUES (?,?,?,?)
                    ON DUPLICATE KEY UPDATE hora_entrada = VALUES(hora_entrada)',
            [$prof, (int) $turno->id_turno, date('Y-m-d'), date('H:i:s')]);

        // **Dentro de la ventana de `MINUTOS_ANTES_DE_ATENDER`.** La cita se
        // creaba a +3 horas, y desde que existe esa regla el servidor rechaza
        // atender algo que todavía falta —con razón: sería anotar como hecho
        // algo que no pasó—. La prueba mide otra cosa (que un producto sin
        // stock no borre los servicios), así que lo que hace falta es que la
        // cita esté en hora, no discutir la regla.
        DB::insert('INSERT INTO cita (id_cliente,id_usuario,id_estado_cita,fecha_hora,id_sucursal) VALUES (?,?,?,?,1)',
            [$cliente, $prof, 1, date('Y-m-d H:i:s', strtotime('+10 minutes'))]);
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
        // no son «las próximas citas», son las suyas. El rediseño de la
        // 7.118.0 —la maqueta del usuario— dejó una sola lista de citas, así
        // que el posesivo va en su título.
        $panel->assertSee('Mis próximas citas');

        // El Administrador ve todo, que es el otro lado de la misma regla.
        $admin = (int) DB::scalar('SELECT id_usuario FROM usuario WHERE id_rol = ? AND activo = 1 ORDER BY id_usuario LIMIT 1',
            [(int) config('permisos.rol_admin', 1)]);
        session(['uid' => $admin, 'rol' => (int) config('permisos.rol_admin', 1),
                 'es_personal' => true, 'es_cliente' => false]); $this->conSucursal();
        // Lo que se mide sigue siendo lo mismo: el Administrador ve la plata
        // del día —y contra ayer, que es lo que la maqueta pide—, y sus citas
        // se nombran sin el posesivo porque son las del salón.
        //
        // **Y lo que salió del panel no vuelve** (pedido del usuario, 7.118.0):
        // «Citas hoy» lo dice la lista de al lado, y el faltante de stock pasa
        // a la campanita con los nombres y el enlace, que un número no daba.
        $this->get(route('panel'))->assertOk()
             ->assertSee('Ingresos de hoy')
             ->assertSee('ayer')
             ->assertSee('Próximas citas')
             ->assertDontSee('Citas hoy')
             ->assertDontSee('Falta stock');
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
        $idFactura = Bd::idDe('sp_emitir_factura', [$cliente, $idCita, $prof, 1, 1, 0, null]);
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
     * El Profesional atiende; cobrar y facturar son del Administrador y del
     * Asistente administrativo.
     *
     * **Decisión del usuario, y da vuelta lo que fijaba la 7.29.0.** Aquella
     * versión le sacó `facturacion.caja` —abría y cerraba el arqueo del
     * salón— y conservó cobrar y emitir a propósito, con el argumento de que
     * sacárselo lo dejaba sin poder trabajar en el mostrador. Hoy el salón
     * decide justamente eso: quien atiende, atiende.
     *
     * **La consecuencia queda medida acá y no sólo escrita**: con una
     * profesional sola en el salón, el dinero espera. Es lo mismo que ya pasa
     * con la caja desde la 7.29.0.
     *
     * Se mide **en las dos direcciones**: que al Profesional le contesten 403
     * y que al Asistente le contesten 200. Con sólo la primera mitad, una
     * matriz que se hubiera quedado sin esas claves para TODOS pasaría igual.
     */
    #[Test]
    public function cobrar_y_facturar_son_del_administrador_y_del_asistente(): void
    {
        $claves = array_map(fn ($r) => $r->modulo,
            DB::select('SELECT modulo FROM rol_modulo WHERE id_rol = 2'));

        $this->assertNotContains('facturacion.cajas', $claves,
            'El Profesional NO administra la caja del salón.');
        $this->assertNotContains('facturacion.cobros', $claves,
            'Cobrar dejó de ser del Profesional.');
        $this->assertNotContains('facturacion.facturas', $claves,
            'Emitir comprobantes dejó de ser del Profesional.');

        // Y el guardia lo hace cumplir, que es lo que importa: esconder el
        // botón no es el control. La caché de permisos es estática y sobrevive
        // entre pruebas del mismo proceso, así que se la olvida antes.
        Permisos::olvidar();
        session(['uid' => (int) (DB::scalar('SELECT id_usuario FROM usuario WHERE id_rol = 2 AND activo = 1 LIMIT 1') ?: 1), 'rol' => 2, 'es_personal' => true, 'es_cliente' => false, 'id_sucursal' => 1]);

        $this->get(route('facturacion.cajas'))->assertForbidden();
        $this->get(route('facturacion.cobros'))->assertForbidden();
        $this->get(route('facturacion.facturas'))->assertForbidden();

        // **La otra mitad: alguien tiene que poder cobrar.** Sin esto, borrar
        // esas claves de la matriz entera dejaría el salón sin cobrar y la
        // prueba seguiría en verde.
        Permisos::olvidar();
        $asistente = (int) (DB::scalar('SELECT id_usuario FROM usuario WHERE id_rol = 3 AND activo = 1 LIMIT 1') ?: 0);
        if ($asistente) {
            session(['uid' => $asistente, 'rol' => 3, 'es_personal' => true,
                     'es_cliente' => false, 'id_sucursal' => 1]);
            $this->get(route('facturacion.cobros'))->assertOk();
            $this->get(route('facturacion.facturas'))->assertOk();
        }
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
     * Vivía en `config/sgp.php`, así que cambiarlo era editar código y volver a
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
        // La cita la crea la prueba: buscarla la dejaba salteada el día que el
        // mes simulado se quedó sin citas futuras.
        $cita = $this->citaFuturaAgendada();
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
        // La cita la crea la prueba: buscarla la dejaba salteada el día que el
        // mes simulado se quedó sin citas futuras.
        $cita = $this->citaFuturaAgendada();

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
     * «Van 3» sin los nombres no reserva, y tampoco «van» en blanco.
     *
     * Es la mitad del servidor de la validación del asistente: el paso
     * «Detalles» no deja avanzar sin esos datos, pero esconder un paso no es
     * el control. Hasta acá `personas` vacío se acomodaba a 1 en silencio y
     * `Acompanantes::guardar()` descartaba al que no tenía nombre, así que
     * una reserva «para 3» entraba con una sola persona nombrada y la agenda
     * no decía a quién esperar.
     *
     * **Se rechaza ANTES de agendar**: si no, el horario queda tomado por una
     * reserva que después se va a corregir.
     */
    #[Test]
    public function la_reserva_del_portal_pide_los_nombres_de_quienes_vienen(): void
    {
        $u = DB::selectOne(
            'SELECT u.id_usuario, c.id_cliente FROM usuario u
               JOIN cliente c ON c.id_persona = u.id_persona
              WHERE u.activo = 1 LIMIT 1'
        );
        $suc = (int) DB::scalar('SELECT id_sucursal FROM sucursal WHERE activo = 1 ORDER BY id_sucursal LIMIT 1');
        $srv = (int) DB::scalar('SELECT id_servicio FROM servicio WHERE activo = 1 ORDER BY id_servicio LIMIT 1');
        if (! $u || ! $suc || ! $srv) {
            $this->markTestSkipped('Falta catálogo para armar la reserva.');
        }

        session([
            'uid' => (int) $u->id_usuario, 'rol' => (int) config('permisos.rol_cliente', 4),
            'es_personal' => false, 'es_cliente' => true, 'id_cliente' => (int) $u->id_cliente,
        ]);
        $this->conMarcaDeSesion();
        $this->conSucursal();

        // Un horario que la pantalla ofrece de verdad: lo que se mide es el
        // rechazo por los nombres, no por el horario.
        $cuando = null;
        for ($d = 2; $d <= 45 && ! $cuando; $d++) {
            $dia = date('Y-m-d', strtotime("+$d days"));
            $j = $this->getJson(route('portal.disponibilidad') . '?' . http_build_query([
                'id_usuario' => 0, 'servicios' => [$srv], 'fecha' => $dia, 'id_sucursal' => $suc, 'personas' => 3,
            ]))->json();
            if (! empty($j['horas'])) {
                $cuando = $dia . ' ' . $j['horas'][0]['hora'] . ':00';
            }
        }
        if (! $cuando) {
            $this->markTestSkipped('No hay ningún hueco libre para probar la reserva.');
        }

        $antes = (int) DB::scalar('SELECT COUNT(*) FROM cita WHERE id_cliente = ?', [(int) $u->id_cliente]);
        $base = ['id_usuario' => 0, 'id_sucursal' => $suc, 'servicios' => [$srv], 'fecha_hora' => $cuando];
        // El último: la cola de avisos se acumula de una petición a la otra.
        $ultimoAviso = function (): string {
            $msgs = array_column((array) session('sgp_flash', []), 'msg');

            return (string) ($msgs ? end($msgs) : '');
        };

        // 1) «Van 3» y ningún nombre: no entra, y dice qué falta.
        $this->post(route('portal.guardar_reserva'), $base + ['personas' => 3])
            ->assertRedirectContains(route('portal.reservar'));
        $this->assertSame($antes, (int) DB::scalar('SELECT COUNT(*) FROM cita WHERE id_cliente = ?', [(int) $u->id_cliente]),
            'Con los nombres sin cargar la reserva no puede entrar: el horario quedaría tomado por una cita a medias.');
        $this->assertStringContainsString('falta el nombre', $ultimoAviso(),
            'El rechazo tiene que decir qué falta, no un genérico.');

        // 2) Cuántas van en blanco: tampoco, y tampoco se acomoda a 1.
        $this->post(route('portal.guardar_reserva'), $base + ['personas' => ''])
            ->assertRedirectContains(route('portal.reservar'));
        $this->assertSame($antes, (int) DB::scalar('SELECT COUNT(*) FROM cita WHERE id_cliente = ?', [(int) $u->id_cliente]),
            '«Cuántas van» vacío se acomodaba a 1 en silencio: ahora se pregunta.');
        $this->assertStringContainsString('entre 1 y 20', $ultimoAviso());

        // 3) Con los nombres puestos, entra — y entran los dos acompañantes.
        //    Es la mitad que impide que un control demasiado estricto apague
        //    la reserva de varias personas, que hoy funciona.
        $this->post(route('portal.guardar_reserva'), $base + [
            'personas' => 3,
            'acomp_nombre' => [2 => 'Josefina', 3 => 'Marta'],
            'acomp_apellido' => [2 => 'Villalba', 3 => 'Duarte'],
        ]);
        $idCita = (int) DB::scalar(
            'SELECT id_cita FROM cita WHERE id_cliente = ? ORDER BY id_cita DESC LIMIT 1', [(int) $u->id_cliente]
        );
        $this->assertSame($antes + 1, (int) DB::scalar('SELECT COUNT(*) FROM cita WHERE id_cliente = ?', [(int) $u->id_cliente]),
            'Con todo cargado la reserva tiene que entrar: ' . $ultimoAviso());
        $this->assertSame(2, (int) DB::scalar('SELECT COUNT(*) FROM cita_acompanante WHERE id_cita = ?', [$idCita]),
            'Los dos acompañantes tienen que quedar anotados con la cita.');
    }

    /**
     * El mismo servicio puede ser para VARIAS personas de la misma cita.
     *
     * Reportado tal cual (7.119.0): *«el sistema solo permite elegir un
     * cliente por servicio, debe permitir que se pueda poner más de un
     * cliente al mismo servicio»*. Dos amigas que vienen a cortarse el pelo
     * marcan las dos en «Corte»: son **dos cortes** —dos filas de
     * `cita_servicio`, una por persona— que la misma profesional hace una
     * después de la otra, así que la cita dura el doble y el calendario ya lo
     * mide así. Hasta acá el único `(id_cita, id_servicio)` rechazaba la
     * segunda fila y había que reservar dos citas.
     *
     * Se comprueba en las dos direcciones: con el único viejo, el INSERT de
     * la segunda fila falla con 1062; con el combo de una sola persona, la
     * segunda casilla no llega.
     */
    #[Test]
    public function el_mismo_servicio_puede_ser_para_varias_personas_de_la_cita(): void
    {
        $u = DB::selectOne(
            'SELECT u.id_usuario, c.id_cliente FROM usuario u
               JOIN cliente c ON c.id_persona = u.id_persona
              WHERE u.activo = 1 LIMIT 1'
        );
        $suc = (int) DB::scalar('SELECT id_sucursal FROM sucursal WHERE activo = 1 ORDER BY id_sucursal LIMIT 1');
        $srvRow = DB::selectOne('SELECT id_servicio, duracion_min FROM servicio WHERE activo = 1 ORDER BY duracion_min, id_servicio LIMIT 1');
        if (! $u || ! $suc || ! $srvRow) {
            $this->markTestSkipped('Falta catálogo para armar la reserva.');
        }
        $srv = (int) $srvRow->id_servicio;
        $min = (int) $srvRow->duracion_min;

        session([
            'uid' => (int) $u->id_usuario, 'rol' => (int) config('permisos.rol_cliente', 4),
            'es_personal' => false, 'es_cliente' => true, 'id_cliente' => (int) $u->id_cliente,
        ]);
        $this->conMarcaDeSesion();
        $this->conSucursal();

        // Un horario que el calendario ofrece PARA DOS CORTES: la consulta
        // lleva `veces[srv]=2`, y la duración que contesta tiene que ser la de
        // los dos, no la de uno — es lo que hace que no se prometa un horario
        // que el guardado rechace.
        $cuando = null;
        $durOfrecida = 0;
        for ($d = 2; $d <= 45 && ! $cuando; $d++) {
            $dia = date('Y-m-d', strtotime("+$d days"));
            $j = $this->getJson(route('portal.disponibilidad') . '?' . http_build_query([
                'id_usuario' => 0, 'servicios' => [$srv], 'fecha' => $dia, 'sucursal' => $suc,
                'personas' => 2, 'veces' => [$srv => 2],
            ]))->json();
            if (! empty($j['horas'])) {
                $cuando = $dia . ' ' . $j['horas'][0]['hora'] . ':00';
                $durOfrecida = (int) ($j['horas'][0]['duracion'] ?? $j['duracion'] ?? 0);
            }
        }
        if (! $cuando) {
            $this->markTestSkipped('No hay ningún hueco libre para probar la reserva.');
        }
        $this->assertGreaterThanOrEqual($min * 2, $durOfrecida,
            'El calendario tiene que medir los DOS cortes: con uno solo ofrecería un hueco en el que el segundo no entra.');

        $this->post(route('portal.guardar_reserva'), [
            'id_usuario' => 0, 'id_sucursal' => $suc, 'servicios' => [$srv], 'fecha_hora' => $cuando,
            'personas' => 2,
            'acomp_nombre' => [2 => 'Josefina'], 'acomp_apellido' => [2 => 'Villalba'],
            'para' => [$srv => [1, 2]],
        ]);
        $msgs = array_column((array) session('sgp_flash', []), 'msg');
        $idCita = (int) DB::scalar(
            'SELECT id_cita FROM cita WHERE id_cliente = ? AND fecha_hora = ? ORDER BY id_cita DESC LIMIT 1',
            [(int) $u->id_cliente, $cuando]
        );
        $this->assertGreaterThan(0, $idCita, 'La reserva de dos amigas en «Corte» tiene que entrar: ' . (string) end($msgs));

        $filas = DB::select(
            'SELECT persona, id_usuario, orden FROM cita_servicio WHERE id_cita = ? AND id_servicio = ? ORDER BY persona',
            [$idCita, $srv]
        );
        $this->assertCount(2, $filas, 'Dos personas en el mismo servicio son DOS filas: una por persona.');
        $this->assertSame([1, 2], array_map(fn ($f) => (int) $f->persona, $filas));

        // La misma profesional los hace uno después del otro: la cita dura
        // los dos, en la base —que es la autoridad— y en `vw_agenda_citas`.
        $this->assertSame($min * 2, (int) DB::scalar('SELECT fn_cita_duracion(?)', [$idCita]),
            'Dos cortes con la misma persona son dos cortes de tiempo, no uno.');
        $this->assertStringContainsString('×2', (string) DB::scalar(
            'SELECT servicios FROM vw_agenda_citas WHERE id_cita = ?', [$idCita]),
            'La agenda dice «Corte ×2», no «Corte, Corte».');

        // Y la factura de UNA de ellas trae UN corte: la fila es de esa persona.
        $this->assertSame(1, (int) DB::scalar(
            'SELECT COUNT(*) FROM cita_servicio WHERE id_cita = ? AND persona = 2', [$idCita]));
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
        // El rechazo vuelve a Reservar **con la sucursal puesta**: desde la
        // 7.97.0 el formulario conserva lo que ya estaba cargado, y volver sin
        // el local haría empezar de cero justo lo que se quiso evitar. Se mide
        // la ruta, no la cadena exacta.
        $r = $reservar();
        $r->assertRedirectContains(route('portal.reservar'));
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

        $r->assertSee('sgp-nav-item', false);
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

        // El renglón de la cuenta pasó a ser una tabla: el monto y su rótulo
        // ya no van en la misma cadena, así que se miden por separado.
        $this->assertStringContainsString('A cobrar', $html,
            'El modal tiene que decir cuánto falta cobrar, no esperar a rechazarlo.');
        $this->assertStringContainsString(money($conDescuento), $html,
            'Y el número tiene que ser el que se va a cobrar de verdad.');
        $this->assertStringContainsString('Total de la cita', $html,
            'Y cuánto vale la cita, que es de dónde sale ese número.');
        $this->assertStringContainsString('Precio de lista', $html,
            'Con el desglose abierto: un total suelto no se puede comprobar.');

        // **Con descuento vigente, el modal NO puede ofrecer el precio de lista.**
        // Es lo que hacía cobrar de más: `sp_emitir_factura` aplica el mejor
        // descuento y la pantalla no lo sabía.
        if ($conDescuento < (float) $srv->precio) {
            $this->assertStringNotContainsString('A cobrar ' . money((float) $srv->precio), $html,
                'Con descuento vigente el modal estaría ofreciendo el precio de lista.');
            $this->assertStringContainsString('Descuento', $html,
                'Un total más bajo sin decir por qué se lee como un error de la pantalla.');
            // **Y de dónde sale ese descuento.** Se aplica uno solo —el mejor
            // entre el del nivel y la promoción vigente— así que decir cuál
            // ganó es lo que permite defender el número si la clienta pregunta.
            $this->assertTrue(
                str_contains($html, 'por su nivel') || str_contains($html, 'por la promoción'),
                'El descuento tiene que decir si vino del nivel de la clienta o de una promoción.'
            );
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

        // **La prueba tiene que garantizar su propia premisa.** El primer tramo
        // mide el criterio permisivo —«sin nada cargado los hace todos»— así que
        // esa persona no puede tener ni una fila; y el segundo inserta una, que
        // contra un `uq_persona_servicio` ya ocupado revienta con 1062 en vez de
        // medir nada. Con una fila suelta de una corrida anterior la prueba
        // decía cosas distintas según el día. Va adentro de la transacción de
        // `DatabaseTransactions`, así que se deshace al terminar.
        DB::delete('DELETE FROM persona_servicio WHERE id_persona =
                        (SELECT id_persona FROM usuario WHERE id_usuario = ?)', [$prof]);

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
     * **La zona la ocupa una PERSONA, no la cita.**
     *
     * Dos servicios sobre la misma cabeza no pueden pasar a la vez... sobre la
     * misma cabeza. Cuando la reserva es para dos —la clienta y su hija— son
     * dos cabezas, así que con dos peluqueras van en paralelo. El modelo lo
     * daba por imposible y sumaba los tiempos, con lo cual la cita «no entraba
     * en el turno» y el calendario salía vacío: no se podía agendar algo que el
     * salón hace todos los días.
     *
     * Se mide en las tres direcciones que importan, porque relajar de más sería
     * peor que el defecto: el candado del PROFESIONAL sigue siendo duro, vengan
     * las personas que vengan.
     */
    public function test_dos_servicios_de_la_misma_zona_van_a_la_vez_si_van_dos_personas(): void
    {
        $mismaZona = DB::select(
            'SELECT s.id_servicio, s.duracion_min
               FROM servicio s
              WHERE s.activo = 1 AND s.id_zona IS NOT NULL
                AND s.id_zona = (SELECT s2.id_zona FROM servicio s2
                                  WHERE s2.activo = 1 AND s2.id_zona IS NOT NULL
                                  GROUP BY s2.id_zona HAVING COUNT(*) >= 2 LIMIT 1)
              ORDER BY s.duracion_min DESC LIMIT 2'
        );
        $profs = array_map(fn ($r) => (int) $r->id_usuario, DB::select(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE r.es_personal = 1 AND u.activo = 1 ORDER BY u.id_usuario LIMIT 2'
        ));
        if (count($mismaZona) < 2 || count($profs) < 2) {
            $this->markTestSkipped('Falta catálogo clasificado por zona.');
        }
        [$a, $b] = $mismaZona;
        $suma = (int) $a->duracion_min + (int) $b->duracion_min;
        $mayor = max((int) $a->duracion_min, (int) $b->duracion_min);
        $reparto = [(int) $a->id_servicio => $profs[0], (int) $b->id_servicio => $profs[1]];

        // 1) UNA persona: una sola cabeza, así que van uno después del otro.
        $this->assertSame($suma, Agenda::duracionReparto($reparto, $profs[0], 1),
            'Con una clienta, dos servicios de la misma zona siguen sumando.');

        // 2) DOS personas y dos profesionales: dos cabezas, van a la vez.
        $this->assertSame($mayor, Agenda::duracionReparto($reparto, $profs[0], 2),
            'Con dos clientas y dos peluqueras, los dos servicios van en paralelo.');

        // 3) DOS personas pero UNA sola profesional: el candado del
        //    profesional no se relaja nunca — una no hace dos cosas a la vez.
        $this->assertSame($suma, Agenda::duracionReparto(
            [(int) $a->id_servicio => $profs[0], (int) $b->id_servicio => $profs[0]], $profs[0], 2),
            'Una sola profesional no atiende a dos clientas al mismo tiempo.');

        // 4) Y la duración prevista —la que abre el calendario— sigue a las
        //    personas: es lo que se compara contra el largo del turno.
        $servicios = [(int) $a->id_servicio, (int) $b->id_servicio];
        $this->assertGreaterThan(
            Agenda::duracionPrevista($servicios, 2),
            Agenda::duracionPrevista($servicios, 1),
            'Con más personas la cita tiene que durar menos, no lo mismo.'
        );
    }

    /**
     * **El comprobante electrónico declara el total CON descuento.**
     *
     * El Automatizador calcula el total sumando `cantidad × precio` de cada
     * renglón —no se le manda—, y el descuento del SGP vive por factura
     * (`factura_descuento`), no por renglón. Mandando el precio de lista, el
     * KuDE y el XML declaraban el subtotal sin descontar: la factura decía una
     * cosa y el comprobante interno, el cobro y la caja decían otra.
     *
     * Se comprueba sobre el TXT, que es exactamente lo que se manda.
     */
    public function test_el_comprobante_electronico_declara_el_total_con_descuento(): void
    {
        $f = DB::selectOne(
            'SELECT f.id_factura FROM factura f
               JOIN detalle_factura df ON df.id_factura = f.id_factura
              WHERE f.id_estado_factura = 1
              GROUP BY f.id_factura HAVING COUNT(*) >= 2
              ORDER BY f.id_factura DESC LIMIT 1'
        );
        $d = DB::selectOne('SELECT id_descuento FROM descuento LIMIT 1');
        if (! $f || ! $d) {
            $this->markTestSkipped('Hace falta una factura con dos renglones y un descuento cargado.');
        }
        $id = (int) $f->id_factura;

        // **La premisa se GARANTIZA, no se busca.** Sin descuento cargado esta
        // prueba pasaría siempre sin medir nada, que es el defecto que este
        // proyecto ya tiene anotado.
        DB::statement(
            'INSERT INTO factura_descuento (id_factura, id_descuento, monto_aplicado) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE monto_aplicado = VALUES(monto_aplicado)',
            [$id, (int) $d->id_descuento, 7500]
        );
        $this->assertGreaterThan(0, (float) DB::scalar('SELECT fn_factura_descuento(?)', [$id]),
            'La premisa: esta factura tiene descuento.');

        $sumaDeRenglones = 0.0;
        foreach (explode("\n", Sifen::armarTxt($id)) as $linea) {
            if (! str_starts_with($linea, 'ITM|')) {
                continue;
            }
            $c = explode('|', $linea);
            $sumaDeRenglones += (float) $c[3] * (float) $c[4];
        }

        $this->assertEqualsWithDelta(
            (float) DB::scalar('SELECT fn_factura_total(?)', [$id]),
            $sumaDeRenglones, 1.0,
            'Lo que suma el comprobante electrónico tiene que ser lo que la clienta paga.'
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
        // Sin cobros previos y con monto suficiente: si la cita ya está
        // saldada, el cobro de prueba se rechaza por el tope y no se llega a
        // medir a qué cajón entró.
        $cita = DB::selectOne(
            'SELECT c.id_cita, c.id_cliente, c.id_usuario, c.id_sucursal FROM cita c
              WHERE EXISTS (SELECT 1 FROM cita_servicio cs WHERE cs.id_cita = c.id_cita)
                AND NOT EXISTS (SELECT 1 FROM factura f WHERE f.id_cita = c.id_cita)
                AND NOT EXISTS (SELECT 1 FROM cobro co
                                 WHERE co.id_cita = c.id_cita AND co.id_estado_cobro = 1)
                AND fn_cita_total(c.id_cita) >= 1000
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
            [(int) $cita->id_cliente, (int) $cita->id_cita, (int) $cita->id_usuario, $tipo, 1, $otra, null]);

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
            [(int) $f->id_factura, 1, 'la clienta no quedó conforme', null]);

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

    #[Test]
    public function una_nota_de_credito_puede_revertir_un_monto_parcial(): void
    {
        $f = DB::selectOne(
            'SELECT f.id_factura, fn_factura_total(f.id_factura) AS total
               FROM factura f JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = f.id_tipo_comprobante
              WHERE f.id_estado_factura = 1 AND tc.signo = 1
                AND fn_factura_total(f.id_factura) > 2
                AND NOT EXISTS (SELECT 1 FROM factura nc WHERE nc.id_factura_origen = f.id_factura)
              ORDER BY f.id_factura DESC LIMIT 1'
        );
        if (! $f) {
            $this->markTestSkipped('No hay una factura apta para probar una reversa parcial.');
        }

        $monto = floor(((float) $f->total) / 2);
        $idNota = (int) Bd::idDe('sp_emitir_nota_credito',
            [(int) $f->id_factura, 1, 'reversa parcial', $monto]);

        $this->assertEqualsWithDelta($monto, (float) DB::scalar('SELECT fn_factura_total(?)', [$idNota]), 0.01,
            'El total de la nota tiene que coincidir con el monto pedido, incluido el redondeo.');
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
        // **La cita de partida se CREA, no se busca.** Tomaba «la más nueva
        // que bloquea agenda», y desde que el mes simulado se quedó sin citas
        // futuras (7.103.1) eso es lo que haya quedado de otra corrida: el
        // 11/09/2026 pasó en verde sólo porque una cita sembrada a mano para
        // probar el panel estaba en la base, y con la base limpia se salteaba
        // en silencio. `citaFuturaAgendada()` la arma en un hueco libre de
        // verdad, a nombre de una clienta que ese día no tiene ese servicio y
        // **sin `para_otra_persona`**, que es justo el caso que la regla
        // excluye a propósito y contra el que no habría solape que detectar.
        $cita = $this->citaFuturaAgendada();

        $cli = (int) $cita->id_cliente;
        $dur = (int) $cita->dur;
        $this->assertGreaterThan(0, $dur, 'Premisa: la cita tiene que durar algo para poder solaparse.');

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

        // **La premisa se garantiza, no se espera.** Con `+3 days` fijo, la
        // prueba se salteaba cada vez que ese día caía en domingo —o en un día
        // en que esa persona no trabaja— y una prueba salteada no se ve. Se
        // busca el primer día de la semana que viene con huecos de verdad.
        $dia = null;
        for ($d = 2; $d <= 9 && ! $dia; $d++) {
            $cand = date('Y-m-d', strtotime("+$d days"));
            if (Agenda::slotsProfesional($prof, $cand, 30, null, $suc) !== []) {
                $dia = $cand;
            }
        }
        if (! $dia) {
            $this->markTestSkipped('En toda la semana no hay huecos ni en la sucursal real.');
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
              ORDER BY c.id_cliente LIMIT 5', [$srv->id_servicio ?? 0]
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

        // **La tolerancia pasó de 24 horas a 15 minutos**, por pedido del
        // usuario, así que lo que se mide es ese borde: recién pasada la hora
        // la clienta todavía puede estar llegando, y pasados los 15 no.
        $reciente = $crear((int) $clientes[0]->id_cliente, date('Y-m-d H:i:s', strtotime('-5 minutes')), 7);
        $programada = $crear((int) $clientes[1]->id_cliente, date('Y-m-d H:i:s', strtotime('-2 days')), 1);
        $reprogramada = $crear((int) $clientes[2]->id_cliente, date('Y-m-d H:i:s', strtotime('-2 days')), 2);
        $reprogramadaFutura = $crear((int) $clientes[3]->id_cliente, date('Y-m-d H:i:s', strtotime('+2 hours')), 2);

        $this->artisan('sgp:notificaciones', ['--max' => 0]);

        $estado = fn (int $id) => (int) DB::scalar(
            'SELECT id_estado_cita FROM cita WHERE id_cita = ?', [$id]);

        $this->assertSame(7, $estado($reciente),
            'Dentro de la tolerancia la cita sigue abierta: a los cinco minutos la clienta '
            . 'está llegando, no faltando.');
        $this->assertSame(6, $estado($programada),
            'Una cita que sigue programada dos días después tiene que cerrarse sola como ausente.');
        $this->assertSame(6, $estado($reprogramada),
            'Una cita reprogramada cuya nueva fecha ya pasó hace dos días tiene que cerrarse como ausente.');
        $this->assertSame(2, $estado($reprogramadaFutura),
            'Reprogramar al futuro reinicia el plazo: la cita no se puede cerrar antes de su nueva fecha.');

        // Y la otra mitad del borde: pasada la tolerancia se cierra sola, sin
        // esperar el día entero. Con la regla vieja esta seguiría en 7.
        // **Con su propia clienta**: `trg_citaserv_bi` no deja repetir el
        // mismo servicio el mismo día, y la primera ya tiene la suya.
        $pasada = $crear((int) $clientes[4]->id_cliente,
            date('Y-m-d H:i:s', strtotime('-' . (CitasVencidas::MINUTOS_SIN_PRESENTARSE + 10) . ' minutes')), 1);
        $this->artisan('sgp:notificaciones', ['--max' => 0]);

        $this->assertSame(6, $estado($pasada),
            'Pasada la tolerancia, la clienta que no se presentó queda ausente: es lo que se pidió.');
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
                'INSERT INTO cuenta_bancaria
                    (id_sucursal, id_metodo_pago, entidad, titular, numero_cuenta)
                 VALUES (?, ?, ?, ?, ?)',
                [$suc, $medio, $entidad, 'Salón de prueba', 'CTA-' . $suc . '-' . $entidad]
            );
        };
        $cargar($a, 'Banco de acá');
        $cargar($b, 'Banco del otro local');

        $deLocal = fn (int $suc) => array_map(fn ($r) => $r->entidad, DB::select(
            'SELECT entidad FROM cuenta_bancaria WHERE id_sucursal = ? AND activo = 1', [$suc]));

        $this->assertContains('Banco de acá', $deLocal($a));
        $this->assertNotContains('Banco del otro local', $deLocal($a),
            'La clienta estaría viendo la cuenta de otra sucursal: transferiría a donde nadie la espera.');

        // Sacar una cuenta la esconde, no la borra: las señas viejas siguen
        // teniendo su respaldo.
        DB::update("UPDATE cuenta_bancaria SET activo = 0
                     WHERE id_sucursal = ? AND entidad = 'Banco de acá'", [$a]);

        $this->assertNotContains('Banco de acá', $deLocal($a),
            'Una cuenta desactivada no se le puede seguir ofreciendo a la clienta.');
        $this->assertSame(1, (int) DB::scalar(
            "SELECT COUNT(*) FROM cuenta_bancaria WHERE id_sucursal = ? AND entidad = 'Banco de acá'", [$a]),
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

            // Con plata de sobra: se liquida EN EFECTIVO y el cajón tiene que
            // alcanzar, o el control del saldo rechazaría antes de medir nada.
            DB::insert('INSERT INTO caja (id_usuario, id_sucursal, id_caja_fisica, id_estado_caja, monto_inicial)
                        VALUES (1, ?, ?, 1, 99000000)', [$suc, $cf]);
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

        // **En efectivo, que es lo que sale de un cajón.** Desde la 7.121.0 lo
        // que va por banco sale de la cuenta bancaria y no se anota en ninguna
        // caja: medir la elección del cajón con una transferencia mediría nada.
        $metodo = (int) DB::scalar("SELECT id_metodo_pago FROM metodo_pago
                                     WHERE activo = 1 AND tipo = 'EFECTIVO' LIMIT 1");

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

    /**
     * Reservar es un asistente, y es el MISMO en las dos pantallas.
     *
     * Lo pidió el usuario así: «utilizar este estilo para el agendamiento de
     * citas… realizar tanto para portal cliente como para los demás». Las dos
     * pantallas pedían cinco cosas en una sola página, y en el celular eso son
     * varias pantallas de scroll donde no se ve dónde se está ni cuánto falta
     * — con el botón de confirmar al final, deshabilitado y sin decir por qué.
     *
     * **Se mide el andamiaje, no el aspecto.** Que los pasos existan, que el
     * botón de confirmar viva en el último y que haya un lugar donde se dibuje
     * el repaso: si alguno se renombra, el asistente deja de armarse **y no da
     * ningún error** — la pantalla se dibuja entera, sin pasos. Es el patrón
     * que `AndamiajeTest` persigue, acá aplicado a las dos pantallas que más
     * se usan.
     */
    #[Test]
    public function las_dos_pantallas_de_reserva_usan_el_mismo_asistente(): void
    {
        $suc = (int) DB::scalar('SELECT id_sucursal FROM sucursal WHERE activo = 1 ORDER BY id_sucursal LIMIT 1');

        // La del mostrador y la de la clienta: son dos roles distintos, así que
        // se entra dos veces.
        $this->entrarComo('admin', 'admin123');
        $mostrador = $this->get(route('citas.form'))->assertOk()->getContent();

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
        $portal = $this->get(route('portal.reservar', ['sucursal' => $suc]))->assertOk()->getContent();

        foreach (['Nueva cita' => $mostrador, 'el portal' => $portal] as $donde => $html) {
            $this->assertStringContainsString('data-asistente', $html,
                "En $donde el contenedor del asistente dejó de estar: la pantalla se dibuja entera y sin pasos.");

            $this->assertGreaterThanOrEqual(5, substr_count($html, 'data-paso='),
                "En $donde quedaron menos de cinco pasos: alguno se perdió al mover el marcado.");

            $this->assertStringContainsString('data-wiz-confirmar', $html,
                "En $donde el botón de confirmar no está marcado, así que queda suelto en medio del último paso.");

            $this->assertStringContainsString('data-wiz-repaso', $html,
                "En $donde no hay dónde dibujar el repaso, que es lo que se mira antes de confirmar.");

            // **El paso «Detalles» no deja avanzar vacío.** El asistente valida
            // cada paso con `checkValidity()`, así que lo que no lleve
            // `required` en el marcado pasa aunque esté en blanco: se reportó
            // que dejaba seguir sin decir cuántas van, y con «3 personas» y
            // ningún nombre. Se mide el atributo, que es lo que el motor lee.
            $this->assertMatchesRegularExpression(
                '/<input[^>]*name="personas"[^>]*\brequired\b[^>]*pattern="\(\[1-9\]\|1\[0-9\]\|20\)"/s', $html,
                "En $donde «cuántas personas van» tiene que ser obligatorio y estar acotado a 1–20: sin eso el asistente avanza con el campo vacío o en 0."
            );
        }

        // Y los nombres de quienes vienen los dibuja `app.js`: el `required`
        // tiene que ir en el molde, o el paso pasa con los renglones en blanco.
        $js = (string) file_get_contents(public_path('assets/js/app.js'));
        $this->assertMatchesRegularExpression('/required minlength="2"[^\n]*\n[^\n]*name="acomp_nombre\[/', $js,
            'El nombre de cada acompañante tiene que dibujarse con `required`: sin eso «3 personas» pasa el paso sin ningún nombre.');

        // **El paso de profesionales no COPIA los combos, los mueve**, así que
        // el marcado sólo declara dónde van. Copiarlos mandaría dos valores
        // para el mismo servicio y ganaría el último.
        $this->assertStringContainsString('data-paso-profesionales', $portal,
            'Sin el destino, los combos de profesional se quedan dentro de sus tarjetas.');
    }

    /**
     * La cuenta del banco AVISA cuando no alcanza, y no frena el pago.
     *
     * **El efectivo tenía su control desde la 5.5.0 y el banco ninguno.** El
     * propio código lo decía al lado del `if` —«los pagos por banco no se
     * frenan: no salen del cajón, salen de la cuenta»— y de la cuenta no se
     * sabía nada: se podía liquidar el mes entero contra una cuenta vacía y
     * enterarse cuando el banco rechazara la transferencia.
     *
     * Se miden las TRES cosas que hacen que esto signifique algo, porque
     * cualquiera de ellas sola pasaría con la función rota:
     *
     * 1. **Sin declarar es NULL, no cero.** Un cero se leería como «la cuenta
     *    está vacía», que es afirmar algo que nadie comprobó — y con eso el
     *    sistema avisaría siempre, que es lo mismo que no avisar nunca.
     * 2. **Avisa** cuando el pago se lleva más de lo declarado.
     * 3. **Y no bloquea.** El saldo es un PISO —el sistema conoce lo que sale
     *    del banco, no lo que entra— así que rechazar con un número que
     *    sabemos incompleto frenaría un pago legítimo. Con el efectivo es al
     *    revés y por eso ahí sí se rechaza: ese saldo es exacto.
     */
    #[Test]
    public function la_cuenta_del_banco_avisa_cuando_no_alcanza_pero_no_frena_el_pago(): void
    {
        $this->entrarComo('admin', 'admin123');
        $suc = (int) session('id_sucursal');

        // Se abre una caja igual, para que esta prueba mida el AVISO y no la
        // regla de la caja: desde la 7.121.0 liquidar por banco no la exige,
        // y eso lo mide `la_liquidacion_por_banco_sale_de_la_cuenta…`.
        DB::insert('INSERT INTO caja_fisica (id_sucursal, nombre) VALUES (?, ?)',
            [$suc, 'Cta ' . uniqid()]);
        $cajon = (int) DB::scalar('SELECT LAST_INSERT_ID()');
        DB::insert('INSERT INTO caja (id_usuario, id_sucursal, id_caja_fisica, id_estado_caja, monto_inicial)
                    VALUES (1, ?, ?, 1, 0)', [$suc, $cajon]);
        $caja = (int) DB::scalar('SELECT LAST_INSERT_ID()');

        $metodo = (int) DB::scalar("SELECT id_metodo_pago FROM metodo_pago
                                     WHERE activo = 1 AND tipo = 'BANCO' LIMIT 1");
        $this->assertNotSame(0, $metodo, 'Hace falta un método de pago bancario.');

        // La cuenta se crea acá y no se toma una cargada: la base de prueba
        // puede no tener ninguna, y sobre todo el saldo declarado es lo que se
        // está midiendo — tomarlo de una existente mediría otra cosa.
        DB::insert('INSERT INTO cuenta_bancaria
                    (id_sucursal, id_metodo_pago, entidad, titular, numero_cuenta, orden, activo)
                    VALUES (?, ?, ?, ?, ?, 99, 1)',
            [$suc, $metodo, 'Banco de prueba', 'Peluquería', '000-' . random_int(1000, 9999)]);
        $cuenta = (int) DB::scalar('SELECT LAST_INSERT_ID()');

        // 1) Sin declarar: NULL, y por lo tanto nada que avisar.
        $this->assertNull(DB::scalar('SELECT fn_cuenta_saldo(?)', [$cuenta]),
            'Una cuenta que nadie declaró vale «no se sabe», no cero.');
        $this->assertSame('', Cuenta::aviso($cuenta, 999999999),
            'Sin saldo declarado no hay nada que avisar: avisar sería inventarlo.');

        // El arqueo de la cuenta, por el mismo camino que la pantalla.
        $this->post(route('facturacion.cuentas.saldo'),
            ['id_cuenta' => $cuenta, 'saldo' => '1.000'])->assertRedirect();

        $this->assertSame(1000.0, (float) DB::scalar('SELECT fn_cuenta_saldo(?)', [$cuenta]),
            'Recién declarado, el saldo es el declarado.');

        // 2) y 3): se liquida por transferencia contra esa cuenta, por mucho
        //    más de los Gs. 1.000 que dice tener.
        $prof = (int) DB::scalar(
            'SELECT sr.id_usuario FROM servicio_realizado sr
               LEFT JOIN detalle_pago_personal d ON d.id_servicio_realizado = sr.id_servicio_realizado
              WHERE d.id_detalle_pago IS NULL
              GROUP BY sr.id_usuario
             HAVING SUM(fn_comision_servicio(sr.id_servicio_realizado)) > 1000 LIMIT 1'
        );
        if (! $prof) {
            $this->markTestSkipped('Hace falta alguien con comisión sin liquidar mayor a Gs. 1.000.');
        }

        $monto = (float) DB::scalar(
            'SELECT COALESCE(SUM(fn_comision_servicio(sr.id_servicio_realizado)), 0)
               FROM servicio_realizado sr
               LEFT JOIN detalle_pago_personal d ON d.id_servicio_realizado = sr.id_servicio_realizado
              WHERE sr.id_usuario = ? AND d.id_detalle_pago IS NULL', [$prof]
        );

        $this->post(route('facturacion.pagar_personal'), [
            'id_usuario' => $prof,
            'periodo' => date('m/Y'),
            'id_metodo_pago' => $metodo,
            'id_caja' => $caja,
            'id_cuenta' => $cuenta,
        ])->assertRedirect();

        $pago = DB::selectOne(
            'SELECT id_pago_personal, id_cuenta FROM pago_personal
              WHERE id_usuario = ? ORDER BY id_pago_personal DESC LIMIT 1', [$prof]
        );

        // **NO se frenó**: es la mitad que más importa. Un control que
        // bloqueara acá apagaría algo que hoy funciona.
        $this->assertNotNull($pago, 'El pago tiene que registrarse igual: esto avisa, no impide.');
        $this->assertSame($cuenta, (int) $pago->id_cuenta,
            'Tiene que quedar anotado de qué cuenta salió, o no hay forma de saber cuál se vació.');

        // **Y avisó**, con el monto y el saldo nombrados: un «no alcanza» a
        // secas no dice qué comprobar.
        $avisos = array_column(session('sgp_flash', []), 'msg');
        $this->assertNotEmpty(preg_grep('/declar/', $avisos),
            'El pago tiene que avisar que se lleva más de lo que la cuenta declara.');

        // El saldo baja por lo que se pagó: es lo que hace que el aviso valga
        // para el pago siguiente.
        $this->assertSame(round(1000 - $monto, 2),
            round((float) DB::scalar('SELECT fn_cuenta_saldo(?)', [$cuenta]), 2),
            'La función tiene que descontar los pagos posteriores al arqueo.');

        DB::delete('DELETE FROM detalle_pago_personal WHERE id_pago_personal = ?', [$pago->id_pago_personal]);
        DB::delete('DELETE FROM pago_personal WHERE id_pago_personal = ?', [$pago->id_pago_personal]);
        DB::delete('DELETE FROM cuenta_bancaria WHERE id_cuenta = ?', [$cuenta]);
        DB::delete('DELETE FROM caja WHERE id_caja = ?', [$caja]);
        DB::delete('DELETE FROM caja_fisica WHERE id_caja_fisica = ?', [$cajon]);
    }

    /**
     * El mismo servicio se repite en el día si la cita es para OTRA persona.
     *
     * `trg_citaserv_bi` comparaba «el mismo cliente», y eso rechazaba un caso
     * legítimo y frecuente: la clienta reserva un corte para su hija a las 10 y
     * otro para ella a las 15. Son dos personas y las dos citas cuelgan de la
     * misma cuenta, así que el disparador las veía como una repetición.
     *
     * La regla pasa a comparar **destinatarios**. Se comprueba en las tres
     * direcciones, porque con sólo la del medio un disparador borrado pasaría
     * igual.
     */
    #[Test]
    public function el_mismo_servicio_se_repite_el_dia_si_la_cita_es_para_otra_persona(): void
    {
        $prof = (int) DB::scalar(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.activo = 1 AND r.es_personal = 1 ORDER BY u.id_usuario LIMIT 1');
        $servicio = (int) DB::scalar('SELECT id_servicio FROM servicio WHERE activo = 1 ORDER BY id_servicio LIMIT 1');
        $cliente = (int) DB::scalar('SELECT id_cliente FROM cliente ORDER BY id_cliente LIMIT 1');
        if (! $prof || ! $servicio || ! $cliente) {
            $this->markTestSkipped('Falta catálogo para armar la prueba.');
        }

        // **Un día que esa clienta tenga libre.** Con uno fijo, la prueba mide
        // lo que haya cargado ese día en vez de la regla.
        $dia = null;
        for ($i = 200; $i < 400; $i++) {
            $d = date('Y-m-d', strtotime("+$i days"));
            if (! DB::scalar('SELECT COUNT(*) FROM cita WHERE id_cliente = ? AND DATE(fecha_hora) = ?', [$cliente, $d])) {
                $dia = $d;
                break;
            }
        }
        $this->assertNotNull($dia, 'No encontré un día libre para armar la prueba.');

        $crear = function (string $hora, int $otra, ?string $para) use ($cliente, $prof, $dia): int {
            DB::insert('INSERT INTO cita (id_cliente,id_usuario,id_estado_cita,fecha_hora,id_sucursal,
                                          para_otra_persona,nombre_para)
                        VALUES (?,?,1,?,1,?,?)', [$cliente, $prof, "$dia $hora", $otra, $para]);

            return (int) DB::getPdo()->lastInsertId();
        };
        $poner = fn (int $idCita) => DB::insert(
            'INSERT INTO cita_servicio (id_cita,id_servicio) VALUES (?,?)', [$idCita, $servicio]);

        // 1) Las dos para ELLA: sigue rechazando, que es la regla original.
        $poner($a = $crear('10:00', 0, null));
        $b = $crear('15:00', 0, null);
        try {
            $poner($b);
            $this->fail('Dos veces el mismo servicio para la misma persona en el día tiene que rechazarse.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('mismo servicio', $e->getMessage());
        }

        // 2) Una para ella y otra para su hija: ENTRA. Es el caso reportado.
        $c = $crear('17:00', 1, 'Josefina');
        $poner($c);
        $this->assertSame(1, (int) DB::scalar(
            'SELECT COUNT(*) FROM cita_servicio WHERE id_cita = ?', [$c]),
            'Una cita para otra persona no repite nada: son dos personas distintas.');

        // 3) Dos para la MISMA hija: se rechaza igual, o la regla no serviría
        //    para nada — alcanzaría con marcar la casilla para saltearla.
        $d = $crear('19:00', 1, 'Josefina');
        try {
            $poner($d);
            $this->fail('Dos veces el mismo servicio para la misma tercera persona tiene que rechazarse.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Josefina', $e->getMessage());
        }
    }

    /**
     * Dos promociones sobre servicios distintos se SUMAN, no compiten.
     *
     * Antes se elegía una sola promoción para toda la cita —la que más
     * descontara— y se la comparaba contra el descuento del nivel: ganaba la
     * mayor y la otra se perdía. Con una promo del 5 % en el corte y otra del
     * 3 % en el lavado eso está mal, porque no son ofertas que compitan: cada
     * una es sobre otra cosa.
     *
     * Se comprueba en las dos direcciones: que dos promos sobre servicios
     * distintos sumen, y que dos sobre el MISMO servicio sigan compitiendo — sin
     * la segunda mitad, apilar tres promos daría el 100 %.
     */
    #[Test]
    public function los_descuentos_de_servicios_distintos_se_suman(): void
    {
        $srv = DB::select('SELECT id_servicio, precio FROM servicio WHERE activo = 1 AND precio > 0
                            ORDER BY id_servicio LIMIT 2');
        $cliente = (int) DB::scalar('SELECT id_cliente FROM cliente ORDER BY id_cliente LIMIT 1');
        $prof = (int) DB::scalar('SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
                                   WHERE u.activo = 1 AND r.es_personal = 1 ORDER BY u.id_usuario LIMIT 1');
        if (count($srv) < 2 || ! $cliente || ! $prof) {
            $this->markTestSkipped('Falta catálogo para armar la prueba.');
        }
        [$a, $b] = $srv;

        // Un día libre, para no chocar con lo que haya cargado.
        $dia = null;
        for ($i = 400; $i < 600; $i++) {
            $d = date('Y-m-d', strtotime("+$i days"));
            if (! DB::scalar('SELECT COUNT(*) FROM cita WHERE id_cliente = ? AND DATE(fecha_hora) = ?', [$cliente, $d])) {
                $dia = $d;
                break;
            }
        }
        $this->assertNotNull($dia);

        DB::insert('INSERT INTO cita (id_cliente,id_usuario,id_estado_cita,fecha_hora,id_sucursal)
                    VALUES (?,?,1,?,1)', [$cliente, $prof, "$dia 10:00:00"]);
        $idCita = (int) DB::getPdo()->lastInsertId();
        DB::insert('INSERT INTO cita_servicio (id_cita,id_servicio) VALUES (?,?),(?,?)',
            [$idCita, $a->id_servicio, $idCita, $b->id_servicio]);

        $promo = function (string $nombre, float $pct, int $servicio) {
            DB::insert("INSERT INTO descuento (nombre, tipo, valor, activo) VALUES (?, 'PORCENTAJE', ?, 1)",
                [$nombre, $pct]);
            $id = (int) DB::getPdo()->lastInsertId();
            DB::insert('INSERT INTO servicio_descuento (id_descuento, id_servicio) VALUES (?,?)', [$id, $servicio]);

            return $id;
        };

        $nivel = (int) DB::scalar('SELECT fn_cliente_descuento(?)', [$cliente]);
        $sinPromos = (float) DB::scalar('SELECT fn_cita_descuento_total(?, ?)', [$idCita, $nivel]);

        // 1) Una promo por servicio: el descuento es la SUMA de las dos.
        $promo('PRUEBA uno', 10, (int) $a->id_servicio);
        $promo('PRUEBA dos', 4, (int) $b->id_servicio);

        $esperado = round((float) $a->precio * 0.10, 2) + round((float) $b->precio * 0.04, 2);
        $total = (float) DB::scalar('SELECT fn_cita_descuento_total(?, ?)', [$idCita, $nivel]);

        $this->assertEqualsWithDelta(max($esperado, $sinPromos), $total, 0.01,
            'Dos promociones sobre servicios distintos tienen que sumarse: no compiten entre sí.');

        // 2) Una TERCERA sobre el primer servicio, peor que la que ya tenía: no
        //    cambia nada. Dos promos sobre lo mismo siguen compitiendo.
        $promo('PRUEBA tres peor', 2, (int) $a->id_servicio);
        $this->assertEqualsWithDelta($total,
            (float) DB::scalar('SELECT fn_cita_descuento_total(?, ?)', [$idCita, $nivel]), 0.01,
            'Dos promociones sobre el MISMO servicio compiten: gana la mejor, no se apilan.');

        // 3) Y una mejor sobre ese mismo servicio sí lo mejora, pero reemplaza
        //    a la anterior en vez de sumarse.
        $promo('PRUEBA cuatro mejor', 20, (int) $a->id_servicio);
        $conMejor = (float) DB::scalar('SELECT fn_cita_descuento_total(?, ?)', [$idCita, $nivel]);
        $this->assertEqualsWithDelta(
            round((float) $a->precio * 0.20, 2) + round((float) $b->precio * 0.04, 2), $conMejor, 0.01,
            'Sobre el mismo servicio gana la mejor y reemplaza, no se acumula.');
    }

    /**
     * **Cada cuenta tiene su propia huella, y registrar una no borra la de otro.**
     *
     * Antes el registro borraba las credenciales de TODAS las demas cuentas, asi
     * que una persona le revocaba el acceso a otra sin enterarse: se registraba
     * la huella y la de la clienta anterior dejaba de andar. Es el modelo
     * equivocado — una credencial WebAuthn apunta a una cuenta, y el navegador
     * sabe cual es cual: al entrar ofrece las guardadas y se entra a la que
     * registro la elegida.
     *
     * Lo que si se reemplaza es lo que ya tenia ESA misma cuenta, para que quede
     * una por cuenta: volver a registrarla es cambiar la suya, no acumular.
     */
    public function test_cada_cuenta_conserva_su_propia_huella(): void
    {
        $dos = DB::select(
            'SELECT u.id_usuario FROM usuario u
               JOIN rol r ON r.id_rol = u.id_rol AND r.es_personal = 1
              WHERE u.activo = 1 ORDER BY u.id_usuario LIMIT 2'
        );
        if (count($dos) < 2) {
            $this->markTestSkipped('Hacen falta dos cuentas de personal.');
        }
        [$a, $b] = [(int) $dos[0]->id_usuario, (int) $dos[1]->id_usuario];

        DB::delete('DELETE FROM credencial_webauthn WHERE id_usuario IN (?,?)', [$a, $b]);
        foreach ([$a => 'PRUEBA-A', $b => 'PRUEBA-B'] as $uid => $cid) {
            DB::insert('INSERT INTO credencial_webauthn (id_usuario, credential_id, public_key, etiqueta)
                        VALUES (?,?,?,?)', [$uid, $cid, '-----PEM-----', 'Prueba']);
        }
        $this->assertSame(2, (int) DB::scalar(
            'SELECT COUNT(*) FROM credencial_webauthn WHERE id_usuario IN (?,?)', [$a, $b]),
            'Premisa: las dos cuentas arrancan con su credencial.');

        // Lo que hace el registro hoy: reemplaza SOLO la de esa cuenta.
        WebAuthn::guardarCredencial($b, 'PRUEBA-B2', '-----PEM-----');

        $this->assertSame(1, (int) DB::scalar(
            'SELECT COUNT(*) FROM credencial_webauthn WHERE id_usuario = ?', [$a]),
            'La huella de la otra cuenta NO se toca: nadie le revoca el acceso a nadie.');
        $this->assertSame(1, (int) DB::scalar(
            'SELECT COUNT(*) FROM credencial_webauthn WHERE id_usuario = ?', [$b]),
            'Y la cuenta que la registro queda con UNA, no con dos.');

        // Y cada credencial sigue apuntando a su cuenta: es lo que hace que
        // entrar con la huella entre a la cuenta correcta y no a otra.
        $this->assertSame($a, (int) DB::scalar(
            'SELECT id_usuario FROM credencial_webauthn WHERE credential_id = ?', ['PRUEBA-A']));
        $this->assertSame($b, (int) DB::scalar(
            'SELECT id_usuario FROM credencial_webauthn WHERE credential_id = ?', ['PRUEBA-B2']));
    }

    /**
     * **Se puede entrar con la huella sin tipear el usuario.**
     *
     * Antes el botón sólo aparecía si ESTE navegador recordaba una cuenta en
     * `localStorage`: en otra computadora o con los datos del sitio borrados
     * había que tipear usuario y contraseña otra vez, o sea que la huella servía
     * justo cuando ya no hacía falta.
     *
     * Con la lista de credenciales vacía el navegador ofrece las que el
     * autenticador tenga guardadas. Lo que se mide acá es que el servidor
     * conteste esas opciones **sin** que se le diga de quién es.
     */
    public function test_la_huella_entra_sin_tipear_el_usuario(): void
    {
        $uid = (int) DB::scalar(
            'SELECT u.id_usuario FROM usuario u
               JOIN rol r ON r.id_rol = u.id_rol AND r.es_personal = 1
              WHERE u.activo = 1 ORDER BY u.id_usuario LIMIT 1'
        );
        DB::delete('DELETE FROM credencial_webauthn WHERE credential_id = ?', ['PRUEBA-SIN-USUARIO']);
        DB::insert('INSERT INTO credencial_webauthn (id_usuario, credential_id, public_key, etiqueta)
                    VALUES (?,?,?,?)', [$uid, 'PRUEBA-SIN-USUARIO', '-----PEM-----', 'Prueba']);

        $r = $this->post(route('webauthn.auth_options'), ['payload' => json_encode(['login' => ''])]);

        $r->assertOk();
        $j = $r->json();
        $this->assertTrue((bool) ($j['ok'] ?? false),
            'Sin usuario tiene que contestar las opciones igual: es lo que deja entrar sin tipear nada.');
        $this->assertSame([], $j['publicKey']['allowCredentials'] ?? null,
            'La lista va VACÍA: si el servidor la llena, el navegador sólo ofrece esas y '
            . 'volvemos a necesitar saber de quién es la huella antes de pedirla.');
        $this->assertSame('required', $j['publicKey']['userVerification'] ?? null,
            'Y con verificación obligatoria, que si no entraría cualquiera que tenga el equipo.');
    }

    /**
     * **La factura le cobra a la clienta lo mismo que la agenda le prometió.**
     *
     * Son dos caminos que calculan el descuento por separado —`fn_cita_total`
     * sobre la cita para el modal de cobro, y `sp_aplicar_descuentos` sobre el
     * detalle al emitir— y son la clase de par que se desincroniza sin avisar:
     * si uno cambia y el otro no, el mostrador propone un total y el comprobante
     * sale con otro. Fue justo lo que se reportó, «precio base 235.000 cuando
     * debía ser 245.000».
     *
     * Se comprueba re-aplicando el descuento con las reglas de HOY sobre cada
     * factura ya emitida y exigiendo que el total dé igual al de su cita. Las
     * facturas viejas guardan su descuento congelado —son documentos fiscales y
     * no se recalculan solos—, así que lo que se mide es la coherencia de la
     * lógica, no lo que quedó grabado: por eso el re-aplicar va dentro de la
     * transacción que `DatabaseTransactions` revierte.
     */
    public function test_la_factura_cobra_lo_que_la_agenda_promete(): void
    {
        $facturas = DB::select(
            'SELECT f.id_factura, f.id_cita, f.id_cliente
               FROM factura f
              WHERE f.id_estado_factura = 1 AND f.id_cita IS NOT NULL
              ORDER BY f.id_factura DESC LIMIT 30'
        );
        if (! $facturas) {
            $this->markTestSkipped('No hay facturas de cita para comprobar.');
        }

        foreach ($facturas as $f) {
            $nivel = (int) DB::scalar('SELECT fn_cliente_descuento(?)', [(int) $f->id_cliente]);
            // Con las reglas de hoy, sobre este mismo detalle.
            DB::statement('CALL sp_aplicar_descuentos(?, ?)', [(int) $f->id_factura, $nivel]);

            $factura = (float) DB::scalar('SELECT fn_factura_total(?)', [(int) $f->id_factura]);
            $cita = (float) DB::scalar('SELECT fn_cita_total(?)', [(int) $f->id_cita]);

            $this->assertEqualsWithDelta($cita, $factura, 0.01,
                "La factura #{$f->id_factura} cobraría {$factura} y la agenda promete {$cita} "
                . 'para la misma cita: el descuento de la emisión y el del cobro se separaron.');
        }
    }

    /**
     * **La cuenta de correo del sistema la cambia SÓLO el Administrador**, y la
     * pantalla se dibuja.
     *
     * Es la que envía el código de verificación, la recuperación y los
     * recordatorios: cambiarla toca cómo se comunica el salón entero. El
     * `admin` del middleware es el control —esconder el enlace no lo es—, así
     * que se comprueba en las dos direcciones: el Administrador entra, y un
     * rol de personal que no sea Administrador recibe 403.
     */
    public function test_el_correo_del_sistema_es_solo_del_admin(): void
    {
        $this->entrarComo('admin', 'admin123');
        $r = $this->get(route('seguridad.correo_sistema'))
            ->assertOk()
            ->assertSee('Correo del sistema');

        // **Sin cuenta cargada, la pantalla tiene que DECIRLO.** Desde la
        // 7.105.0 este formulario es la única fuente —las credenciales salieron
        // de los archivos de entorno— así que vacío significa que el sistema no
        // manda un solo correo. Callárselo sería la función apagada en silencio
        // de siempre: la pantalla del registro igual dice «te enviamos un
        // código».
        if (\App\Servicios\Config::correoSistema()['usuario'] === ''
            && (string) config('mail.mailers.smtp.username') === '') {
            $r->assertSee('no hay ninguna cuenta cargada', false);
        }

        $u = DB::selectOne(
            "SELECT u.id_usuario, u.id_rol FROM usuario u
               JOIN rol r ON r.id_rol = u.id_rol
              WHERE r.es_personal = 1 AND r.id_rol <> ? AND u.activo = 1 LIMIT 1",
            [(int) config('permisos.rol_admin', 1)]
        );
        if (! $u) {
            $this->markTestSkipped('No hay un rol de personal no administrador para comprobar el 403.');
        }

        session(['uid' => (int) $u->id_usuario, 'rol' => (int) $u->id_rol]);
        $this->conMarcaDeSesion();
        $this->conSucursal();

        $this->get(route('seguridad.correo_sistema'))->assertStatus(403);
    }

    /**
     * **El `clientDataJSON` de la huella llega en base64url y hay que
     * decodificarlo.**
     *
     * Es el defecto que tenía la huella rota desde que existe: el navegador
     * manda `bufToB64url(cred.response.clientDataJSON)` y el servidor le hacía
     * `json_decode` directamente, que sobre base64 nunca devuelve un arreglo.
     * El síntoma era «Datos del cliente inválidos» con la ceremonia del
     * navegador perfectamente completada.
     *
     * Se comprueba con el mismo dato en las dos formas: como lo manda el
     * navegador (base64url) tiene que pasar, y el base64 crudo —lo que se
     * miraba antes— tiene que ser rechazado.
     */
    public function test_la_huella_decodifica_el_client_data_del_navegador(): void
    {
        $desafio = WebAuthn::nuevoDesafio();

        $cd = json_encode([
            'type' => 'webauthn.create',
            'challenge' => $desafio,
            'origin' => WebAuthn::origin(),
        ], JSON_UNESCAPED_SLASHES);

        // Como lo manda el navegador.
        $comoLoManda = WebAuthn::b64urlEncode($cd);

        $this->assertNull(
            WebAuthn::motivoClientData(WebAuthn::clientData($comoLoManda), 'webauthn.create'),
            'El clientDataJSON que manda el navegador tiene que aceptarse una vez decodificado.'
        );

        // Y la mitad que faltaba: sin decodificar, no se entiende. Si esta
        // aserción dejara de fallar, es que alguien sacó el `clientData()` y la
        // prueba de arriba pasaría igual sin medir nada.
        $this->assertNotNull(
            WebAuthn::motivoClientData($comoLoManda, 'webauthn.create'),
            'Sin decodificar, el base64 no es JSON: tiene que dar un motivo.'
        );
    }

    /**
     * **A los 30 minutos sin actividad la sesión se cierra, y se dice por qué.**
     *
     * Laravel ya vence la sesión con `SESSION_LIFETIME`, pero cuando lo hace no
     * queda nada: la persona cae en el ingreso **sin ninguna explicación**, y
     * eso se lee como que el sistema la echó o como que se rompió algo. Por eso
     * el plazo se comprueba en `ExigeSesion`, con la sesión todavía viva, que es
     * lo único que permite contar el motivo.
     *
     * Se mide en las dos direcciones: recién usado sigue adentro, y pasado el
     * plazo sale **con el aviso**. Sin la segunda mitad, un middleware que no
     * cerrara nada pasaría igual.
     */
    public function test_la_sesion_se_cierra_por_inactividad_y_lo_dice(): void
    {
        $minutos = (int) config('sgp.sesion.inactividad_min', 30);
        $this->assertGreaterThan(0, $minutos, 'Tiene que haber un plazo configurado.');

        $this->entrarComo('admin', 'admin123');
        $this->get(route('panel'))->assertOk();

        // Recién usado: sigue adentro.
        session(['sgp_ultima_actividad' => time() - 60]);
        $this->get(route('panel'))->assertOk();

        // Pasado el plazo: afuera, y con el motivo.
        session(['sgp_ultima_actividad' => time() - ($minutos * 60 + 60)]);
        $this->get(route('panel'))->assertRedirect(route('login'));

        $avisos = array_column((array) session('sgp_flash', []), 'msg');
        $this->assertNotEmpty($avisos, 'El cierre por inactividad tiene que dejar un aviso.');
        $this->assertStringContainsString('sin que se usara el sistema', implode(' ', $avisos),
            'El aviso tiene que decir el motivo: sin eso, caer en el ingreso parece una falla.');
    }

    /**
     * **El enlace del correo NO deja reprogramar dos veces.**
     *
     * El portal lo topaba desde la 7.66.0 y este camino se habia quedado
     * afuera: el enlace sigue llegando en cada recordatorio, asi que la clienta
     * podia mover la misma cita todas las veces que quisiera -- y con un enlace
     * VIEJO tambien, porque el token no vence al reprogramar.
     *
     * La comprobacion es sobre el ESTADO de la cita y no sobre el token, que es
     * lo que hace que un enlace de hace un mes tampoco sirva: `Reprogramada`
     * (estado 2) es la marca de que el cambio ya se uso.
     */
    public function test_el_enlace_del_correo_no_deja_reprogramar_dos_veces(): void
    {
        // La cita la crea la prueba —programada, futura y con servicios—: antes
        // la buscaba y se salteaba, así que el día que el mes simulado se quedó
        // sin citas futuras esta regla dejó de comprobarse sin que nada lo
        // dijera.
        $cita = $this->citaFuturaAgendada();

        $token = NotificacionesSGP::tokenDeCita((int) $cita->id_cita);
        $this->assertNotSame('', (string) $token, 'Hace falta un token para entrar por el enlace.');

        // La cita ya uso su unico cambio.
        DB::update('UPDATE cita SET id_estado_cita = 2 WHERE id_cita = ?', [(int) $cita->id_cita]);
        $antes = DB::scalar('SELECT fecha_hora FROM cita WHERE id_cita = ?', [(int) $cita->id_cita]);

        $this->post(route('cita.token.guardar'), [
            't' => $token,
            'fecha_hora' => date('Y-m-d H:i:s', strtotime('+9 days 10:00')),
        ]);

        $despues = DB::scalar('SELECT fecha_hora FROM cita WHERE id_cita = ?', [(int) $cita->id_cita]);
        $this->assertSame((string) $antes, (string) $despues,
            'La cita se movio desde el enlace del correo pese a que ya habia usado su unico cambio.');

        // Y la pantalla deja de ofrecer el formulario.
        $this->get(route('cita.token', ['t' => $token]))
            ->assertOk()
            ->assertSee('Ya cambiaste el', false);
    }

    /**
     * **«Que me atienda cualquiera» no puede terminar en «fulana no hace eso».**
     *
     * Un 0 en el reparto no significa «nadie»: significa que lo hace el DUENIO
     * de la cita, porque `cita_servicio.id_usuario` en NULL se resuelve contra
     * `cita.id_usuario`. Asi que eligiendo a Lucia para un servicio y dejando
     * otro en «cualquiera», el segundo caia sobre Lucia — y si ella no lo hace,
     * la reserva se rechazaba nombrando a una persona que la clienta NO habia
     * elegido para eso. Desde afuera parecia que el sistema se contradecia: el
     * combo de ese servicio ni siquiera la ofrecia.
     *
     * Lo que corresponde no es rechazar sino **asignarlo a alguien que si lo
     * haga**, que es lo que haria el salon. Se mide con el caso exacto que se
     * reporto.
     */
    public function test_lo_que_queda_en_cualquiera_va_a_alguien_que_lo_haga(): void
    {
        // Alguien con servicios cargados, y un servicio que NO hace.
        // **La prueba garantiza su premisa.** Tomando «el primero con servicios
        // cargados» caía en alguien que los hace todos, y entonces no hay caso
        // que medir: hace falta uno que tenga al menos un servicio ajeno.
        $prof = DB::selectOne(
            "SELECT u.id_usuario FROM usuario u
               JOIN rol r ON r.id_rol = u.id_rol
              WHERE r.es_personal = 1 AND u.activo = 1
                AND EXISTS (SELECT 1 FROM persona_servicio ps WHERE ps.id_persona = u.id_persona)
                AND EXISTS (SELECT 1 FROM servicio s
                             WHERE s.activo = 1 AND fn_usuario_hace_servicio(u.id_usuario, s.id_servicio) = 0)
              LIMIT 1"
        );
        if (! $prof) {
            $this->markTestSkipped('Nadie tiene servicios cargados: el criterio permisivo hace todo.');
        }
        $id = (int) $prof->id_usuario;

        $suyo = DB::selectOne(
            "SELECT ps.id_servicio FROM persona_servicio ps
               JOIN usuario u ON u.id_persona = ps.id_persona
               JOIN servicio s ON s.id_servicio = ps.id_servicio AND s.activo = 1
              WHERE u.id_usuario = ? LIMIT 1", [$id]
        );
        $ajeno = DB::selectOne(
            "SELECT s.id_servicio FROM servicio s
              WHERE s.activo = 1 AND fn_usuario_hace_servicio(?, s.id_servicio) = 0 LIMIT 1", [$id]
        );
        if (! $suyo || ! $ajeno) {
            $this->markTestSkipped('Hace todos los servicios: no hay caso que medir.');
        }

        // **La prueba garantiza su premisa: un momento en que ALGUIEN que hace
        // ese servicio esté de verdad libre.** Con un `+8 days` fijo, el día
        // que eso caía en domingo —el salón cerrado— no había a quién
        // asignarle nada y la prueba se ponía roja sin que el sistema hubiera
        // cambiado. Es el mismo defecto que este proyecto ya tiene anotado
        // varias veces.
        $dur = (int) DB::scalar('SELECT duracion_min FROM servicio WHERE id_servicio = ?',
                                [(int) $ajeno->id_servicio]);
        $fecha = null;
        for ($i = 1; $i <= 30 && $fecha === null; $i++) {
            $dia = date('Y-m-d', strtotime("+$i day"));
            foreach (Agenda::slots(null, $dia, $dur, null, 1, [(int) $ajeno->id_servicio]) as $h) {
                $fecha = $dia . ' ' . $h['hora'] . ':00';
                break;
            }
        }
        $this->assertNotNull($fecha,
            'La premisa: hace falta un horario en que alguien que hace ese servicio esté libre.');

        $asignacion = [(int) $suyo->id_servicio => $id, (int) $ajeno->id_servicio => 0];

        // Sin completar, el reparto culpa al principal por un servicio que la
        // clienta dejo a criterio del salon.
        $this->assertStringContainsString('no hace',
            (string) Agenda::validarReparto($asignacion, $id, $fecha),
            'Esta es la mitad que falla: sin completar, el 0 cae sobre el principal.');

        // Completado, ese servicio queda en manos de alguien que si lo hace.
        $comp = Agenda::completarReparto($asignacion, $id, $fecha, 1);
        $asignado = (int) ($comp[(int) $ajeno->id_servicio] ?? 0);

        $this->assertNotSame(0, $asignado,
            'El servicio que el principal no hace tiene que quedar asignado a otra persona.');
        $this->assertSame(1, (int) DB::scalar('SELECT fn_usuario_hace_servicio(?, ?)',
            [$asignado, (int) $ajeno->id_servicio]),
            'A quien se le asigno tiene que hacer ese servicio.');
    }

    /**
     * **El día en que ya tiene ese servicio no se ofrece.**
     *
     * La regla es de la 7.14.0 —una clienta no repite el mismo servicio el
     * mismo día— y la hacía cumplir `trg_citaserv_bi` **al guardar**, o sea con
     * el formulario ya completo: la clienta elegía todo y recién ahí se enteraba.
     * Sacando ese día de la lista, el rechazo deja de poder ocurrir.
     *
     * **Sólo cuentan las citas que ocupan agenda**, así que cancelando la otra
     * el día vuelve a ofrecerse — que es exactamente lo que dice la regla.
     */
    public function test_no_se_ofrece_el_dia_en_que_ya_tiene_ese_servicio(): void
    {
        $cli = (int) DB::scalar('SELECT id_cliente FROM cliente WHERE activo = 1 LIMIT 1');
        $srv = (int) DB::scalar('SELECT id_servicio FROM servicio WHERE activo = 1 LIMIT 1');
        $prof = (int) DB::scalar(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE r.es_personal = 1 AND u.activo = 1 LIMIT 1'
        );
        if (! $cli || ! $srv || ! $prof) {
            $this->markTestSkipped('Falta una clienta, un servicio o un profesional.');
        }

        // Un día que HOY no esté tomado, para que la prueba mida la regla y no
        // el estado en que quedó la base.
        $dia = null;
        for ($i = 5; $i < 40 && $dia === null; $i++) {
            $cand = date('Y-m-d', strtotime("+$i days"));
            if (! in_array($cand, Agenda::diasYaTomados($cli, [$srv]), true)) {
                $dia = $cand;
            }
        }
        $this->assertNotNull($dia, 'No se encontró un día libre para medir.');

        $this->assertNotContains($dia, Agenda::diasYaTomados($cli, [$srv]),
            'La premisa: ese día tiene que estar libre antes de agendar.');

        DB::insert('INSERT INTO cita (id_cliente, id_usuario, id_sucursal, fecha_hora, id_estado_cita)
                    VALUES (?,?,1,?,1)', [$cli, $prof, $dia . ' 09:00:00']);
        $idCita = (int) DB::scalar('SELECT LAST_INSERT_ID()');
        DB::insert('INSERT INTO cita_servicio (id_cita, id_servicio) VALUES (?,?)', [$idCita, $srv]);

        $this->assertContains($dia, Agenda::diasYaTomados($cli, [$srv]),
            'Con la cita cargada, ese día tiene que quedar fuera de lo que se ofrece.');

        // Cancelada deja de ocupar la agenda, así que el día vuelve.
        DB::update('UPDATE cita SET id_estado_cita = 3 WHERE id_cita = ?', [$idCita]);
        $this->assertNotContains($dia, Agenda::diasYaTomados($cli, [$srv]),
            'Cancelando la otra cita, el día tiene que volver a ofrecerse.');
    }

    /**
     * El horario que ocupa el único que hace ese servicio deja de ofrecerse.
     *
     * **Es el defecto reportado, y el peor de los de agenda**: con «que me
     * atienda cualquiera», el selector juntaba los huecos del equipo ENTERO
     * sin mirar quién hace qué. Si la coloración la hace una sola persona y
     * esa persona está tomada de 10 a 12, esas horas seguían apareciendo
     * porque las demás estaban libres — y la clienta lo descubría al guardar,
     * con todo elegido.
     *
     * La prueba **garantiza su premisa**: deja el servicio en manos de una
     * sola persona y le carga a las demás uno distinto, porque el criterio
     * permisivo dice que quien no tiene ninguno cargado los hace todos.
     *
     * Se mide en las dos direcciones: sin pasar los servicios —que es como
     * estaba— la hora se sigue ofreciendo.
     */
    #[Test]
    public function test_no_se_ofrece_la_hora_del_unico_que_hace_ese_servicio(): void
    {
        $equipo = array_map(fn ($p) => (int) $p->id_usuario, Agenda::profesionales(1));
        if (count($equipo) < 2) {
            $this->markTestSkipped('Hace falta más de un profesional en el local.');
        }

        $srv = DB::selectOne('SELECT id_servicio, duracion_min FROM servicio WHERE activo = 1 ORDER BY duracion_min LIMIT 1');
        $otro = DB::selectOne('SELECT id_servicio FROM servicio WHERE activo = 1 AND id_servicio <> ? LIMIT 1',
                              [(int) $srv->id_servicio]);
        $this->assertNotNull($otro, 'Hacen falta dos servicios en el catálogo.');

        $duenio = $equipo[0];
        $dur = (int) $srv->duracion_min;

        // Sólo el primero hace ese servicio: a los demás se les carga el otro,
        // que es lo que los saca del criterio permisivo.
        foreach ($equipo as $id) {
            $per = (int) DB::scalar('SELECT id_persona FROM usuario WHERE id_usuario = ?', [$id]);
            DB::delete('DELETE FROM persona_servicio WHERE id_persona = ?', [$per]);
            DB::insert('INSERT INTO persona_servicio (id_persona, id_servicio) VALUES (?,?)',
                       [$per, $id === $duenio ? (int) $srv->id_servicio : (int) $otro->id_servicio]);
        }
        Agenda::olvidarQuienHace();

        $this->assertSame([$duenio], Agenda::quienHace([(int) $srv->id_servicio], 1)[(int) $srv->id_servicio],
            'La premisa: ese servicio lo tiene que hacer una sola persona.');

        // Un día y una hora en que ese profesional esté libre de verdad.
        $dia = null;
        $hora = null;
        for ($i = 1; $i <= 30 && ! $dia; $i++) {
            $f = date('Y-m-d', strtotime("+$i day"));
            $libres = Agenda::slots($duenio, $f, $dur, null, 1);
            if (count($libres) > 1) {
                $dia = $f;
                $hora = $libres[0]['hora'];
            }
        }
        $this->assertNotNull($dia, 'No se encontró un día con huecos para medir.');

        $hayHora = fn (array $slots) => in_array($hora, array_column($slots, 'hora'), true);

        $this->assertTrue($hayHora(Agenda::slots(null, $dia, $dur, null, 1, [(int) $srv->id_servicio])),
            'La premisa: antes de ocuparlo, esa hora se ofrece.');

        // Se le llena esa hora al único que lo hace.
        DB::insert('INSERT INTO cita (id_cliente, id_usuario, id_sucursal, fecha_hora, id_estado_cita)
                    VALUES ((SELECT MIN(id_cliente) FROM cliente), ?, 1, ?, 1)',
                   [$duenio, $dia . ' ' . $hora . ':00']);
        $idCita = (int) DB::scalar('SELECT LAST_INSERT_ID()');
        DB::insert('INSERT INTO cita_servicio (id_cita, id_servicio) VALUES (?,?)',
                   [$idCita, (int) $srv->id_servicio]);

        $this->assertFalse($hayHora(Agenda::slots(null, $dia, $dur, null, 1, [(int) $srv->id_servicio])),
            'Ocupado el único que hace ese servicio, esa hora NO se puede seguir ofreciendo.');

        // Y ésta es la mitad que falla sin el arreglo: sin los servicios, el
        // selector junta los huecos de todos y la sigue ofreciendo.
        $this->assertTrue($hayHora(Agenda::slots(null, $dia, $dur, null, 1)),
            'Sin filtrar por servicio, la hora se ofrece igual: eso es lo que estaba mal.');
    }

    /**
     * El portal nombra a TODAS las que atienden la cita, no sólo a la dueña.
     *
     * **El defecto reportado**: la clienta eligió varios servicios con varios
     * profesionales distintos y «Mis citas» le mostraba **uno solo**.
     * `vw_agenda_citas.profesional` sale de `cita.id_usuario` —la dueña de la
     * cita— y quién hace cada servicio vive en `cita_servicio.id_usuario`, que
     * esa vista no mira.
     *
     * **Un NULL ahí no es «nadie»: es la dueña**, que es como se representa «lo
     * hace quien la tiene» desde siempre; por eso la subconsulta usa
     * `COALESCE`. Es la misma corrección que el panel recibió en la 7.104.0 y
     * que el portal se había quedado sin aplicar — media corrección, el patrón
     * que este documento ya tiene anotado.
     *
     * Se mide en las dos direcciones dentro de la misma prueba: la columna
     * vieja **no** puede nombrar a la segunda profesional —eso es exactamente
     * lo que estaba mal— y la nueva tiene que nombrar a las dos.
     */
    #[Test]
    public function test_el_portal_nombra_a_todos_los_profesionales_de_la_cita(): void
    {
        $cita = $this->citaFuturaAgendada();

        $nombreDe = fn (int $idu) => (string) DB::scalar(
            'SELECT CONCAT(pe.nombre, \' \', pe.apellido) FROM usuario u
               JOIN persona pe ON pe.id_persona = u.id_persona WHERE u.id_usuario = ?', [$idu]);

        // **La premisa: una segunda profesional, distinta de la dueña.** Sin eso
        // las dos columnas dirían lo mismo y la prueba no mediría nada.
        $otro = 0;
        foreach (Agenda::profesionales($cita->id_sucursal) as $p) {
            if ((int) $p->id_usuario !== (int) $cita->id_usuario) {
                $otro = (int) $p->id_usuario;
                break;
            }
        }
        if (! $otro) {
            $this->markTestSkipped('Hace falta más de un profesional en el local.');
        }

        $srv2 = (int) DB::scalar(
            'SELECT s.id_servicio FROM servicio s
              WHERE s.activo = 1
                AND s.id_servicio NOT IN (SELECT cs.id_servicio FROM cita_servicio cs WHERE cs.id_cita = ?)
              LIMIT 1', [$cita->id_cita]);
        $this->assertNotSame(0, $srv2, 'La premisa: hace falta un segundo servicio en el catálogo.');

        DB::insert('INSERT INTO cita_servicio (id_cita, id_servicio, id_usuario) VALUES (?,?,?)',
                   [$cita->id_cita, $srv2, $otro]);

        // La cita pasa a una clienta con cuenta en el portal: es la pantalla que
        // se está midiendo, y sin cuenta no se puede abrir.
        $u = DB::selectOne(
            'SELECT u.id_usuario, cl.id_cliente FROM usuario u
               JOIN rol r ON r.id_rol = u.id_rol
               JOIN persona pe ON pe.id_persona = u.id_persona
               JOIN cliente cl ON cl.id_persona = pe.id_persona
              WHERE r.es_personal = 0 AND u.activo = 1 AND cl.activo = 1 LIMIT 1');
        $this->assertNotNull($u, 'La premisa: hace falta una clienta con cuenta en el portal.');
        DB::update('UPDATE cita SET id_cliente = ? WHERE id_cita = ?', [$u->id_cliente, $cita->id_cita]);

        session([
            'uid' => (int) $u->id_usuario, 'rol' => (int) config('permisos.rol_cliente', 4),
            'es_personal' => false, 'es_cliente' => true, 'id_cliente' => (int) $u->id_cliente,
        ]);
        $this->conMarcaDeSesion();
        $this->conSucursal();

        $fila = null;
        foreach ($this->get(route('portal.citas'))->assertOk()->viewData('prox') as $c) {
            if ((int) $c->id_cita === (int) $cita->id_cita) {
                $fila = $c;
                break;
            }
        }
        $this->assertNotNull($fila, 'La premisa: la cita tiene que salir entre las próximas.');

        $duenia = $nombreDe((int) $cita->id_usuario);
        $segunda = $nombreDe($otro);

        // **La mitad que falla sin el arreglo.** La columna de la vista nombra
        // sólo a la dueña, así que la segunda profesional no puede estar ahí.
        $this->assertStringNotContainsString($segunda, (string) $fila->profesional,
            'La premisa: `vw_agenda_citas.profesional` nombra sólo a la dueña de la cita.');

        // Y la que tiene que cumplirse: la columna nueva las nombra a las dos.
        $this->assertStringContainsString($duenia, (string) $fila->profesionales,
            'Falta la profesional dueña de la cita.');
        $this->assertStringContainsString($segunda, (string) $fila->profesionales,
            'Falta la profesional que hace el otro servicio: es el defecto reportado.');
    }

    /**
     * Los horarios que se ofrecen respetan el turno de CADA profesional pedido.
     *
     * **El defecto reportado**: «el horario no coincide con el turno de los
     * profesionales seleccionados». La consulta de disponibilidad llevaba un
     * solo `id_usuario`, así que el navegador sólo podía mandarlo cuando todos
     * los servicios iban a la misma persona: con dos servicios en dos manos
     * distintas mandaba **cero**, y cero significa «cualquiera». El servidor
     * contestaba entonces con los huecos del equipo entero y la pantalla
     * ofrecía horas en las que una de las dos personas elegidas ni trabaja —
     * el «no» llegaba al guardar, con el día y la hora ya elegidos.
     *
     * La prueba **garantiza su premisa**: elige a dos profesionales cuyos
     * turnos NO se superponen del todo y les carga un servicio a cada uno, que
     * es lo que los saca del criterio permisivo.
     *
     * Se mide en las dos direcciones, que es lo que la hace valer: **sin** los
     * pedidos se ofrece al menos una hora que uno de los dos no puede tomar
     * —eso es exactamente lo que estaba mal— y **con** los pedidos toda hora
     * ofrecida les sirve a los dos.
     */
    #[Test]
    public function test_los_horarios_respetan_el_turno_de_cada_profesional_pedido(): void
    {
        $equipo = array_map(fn ($p) => (int) $p->id_usuario, Agenda::profesionales(1));
        if (count($equipo) < 2) {
            $this->markTestSkipped('Hace falta más de un profesional en el local.');
        }

        $srv = DB::select('SELECT id_servicio, duracion_min FROM servicio WHERE activo = 1 ORDER BY duracion_min LIMIT 2');
        if (count($srv) < 2) {
            $this->markTestSkipped('Hacen falta dos servicios en el catálogo.');
        }
        $s1 = (int) $srv[0]->id_servicio;
        $s2 = (int) $srv[1]->id_servicio;
        $dur = (int) $srv[0]->duracion_min + (int) $srv[1]->duracion_min;

        // **La premisa: dos personas cuyos turnos no coinciden.** Se busca el
        // par y el día donde la diferencia se ve: si los turnos fueran los
        // mismos, la corrección no cambiaría nada y la prueba no mediría nada.
        $par = null;
        $dia = null;
        foreach ($equipo as $a) {
            foreach ($equipo as $b) {
                if ($a === $b || $par) {
                    continue;
                }
                for ($i = 1; $i <= 21; $i++) {
                    $f = date('Y-m-d', strtotime("+$i day"));
                    $ha = array_column(Agenda::slotsProfesional($a, $f, $dur, null, 1), 0) ?: Agenda::slotsProfesional($a, $f, $dur, null, 1);
                    $hb = Agenda::slotsProfesional($b, $f, $dur, null, 1);
                    // Hay algo que uno puede y el otro no: ahí se nota.
                    if ($ha && $hb && array_diff($hb, $ha)) {
                        $par = [$a, $b];
                        $dia = $f;
                        break 3;
                    }
                }
            }
        }
        if (! $par) {
            $this->markTestSkipped('Todos los profesionales tienen el mismo turno: no hay diferencia que medir.');
        }
        [$p1, $p2] = $par;

        // Cada uno hace un servicio, y sólo ése: es lo que los saca del
        // criterio permisivo —quien no tiene ninguno cargado los hace todos—.
        foreach ($equipo as $id) {
            $per = (int) DB::scalar('SELECT id_persona FROM usuario WHERE id_usuario = ?', [$id]);
            DB::delete('DELETE FROM persona_servicio WHERE id_persona = ?', [$per]);
            if ($id === $p1) {
                DB::insert('INSERT INTO persona_servicio (id_persona, id_servicio) VALUES (?,?)', [$per, $s1]);
            } elseif ($id === $p2) {
                DB::insert('INSERT INTO persona_servicio (id_persona, id_servicio) VALUES (?,?)', [$per, $s2]);
            }
        }
        Agenda::olvidarQuienHace();

        $suyas = Agenda::slotsProfesional($p1, $dia, $dur, null, 1);

        $sin = array_column(Agenda::slots(null, $dia, $dur, null, 1, [$s1, $s2], 1), 'hora');
        $con = array_column(Agenda::slots(null, $dia, $dur, null, 1, [$s1, $s2], 1, [$s1 => $p1, $s2 => $p2]), 'hora');

        // **La mitad que falla sin el arreglo.** Sin decir a quién se pidió, el
        // selector junta los huecos del equipo y ofrece horas que la persona
        // elegida no puede tomar.
        $this->assertNotEmpty(array_diff($sin, $suyas),
            'La premisa: sin los pedidos se ofrece alguna hora que ese profesional no puede tomar.');

        // Y la que tiene que cumplirse siempre: lo ofrecido le sirve a los dos.
        foreach ($con as $h) {
            $this->assertContains($h, $suyas,
                "Se ofreció $h, y el profesional pedido para ese servicio no trabaja a esa hora.");
        }
        $this->assertEmpty(array_diff($con, $sin),
            'Pedir a alguien sólo puede acotar lo que se ofrece, nunca agregar horas.');
    }

    /**
     * Tres profesionales que atienden el mismo turno, cada una con un servicio
     * de una zona distinta, y un día en que ninguna tiene nada: la premisa de
     * las pruebas de la intersección de agendas.
     *
     * Se GARANTIZA, no se espera: a cada una se le deja cargado sólo su
     * servicio —que es lo que la saca del criterio permisivo— y el día se
     * busca entre los que vienen hasta encontrar uno sin citas ni ausencias
     * para las tres.
     *
     * @return array{0: array<int>, 1: array<int>, 2: string, 3: int, 4: int}
     *         [usuarios, servicios, día, inicio del turno (ts), fin del turno (ts)]
     */
    private function tresConElMismoTurno(): array
    {
        $turno = DB::selectOne(
            'SELECT t.id_turno, t.hora_inicio, t.hora_fin, COUNT(*) AS n
               FROM turno_laboral t
               JOIN usuario_turno ut ON ut.id_turno = t.id_turno
               JOIN usuario u ON u.id_usuario = ut.id_usuario AND u.activo = 1
               JOIN rol r ON r.id_rol = u.id_rol AND r.es_personal = 1
              WHERE t.activo = 1 AND t.id_sucursal = 1
              GROUP BY t.id_turno, t.hora_inicio, t.hora_fin
             HAVING n >= 3
              ORDER BY n DESC LIMIT 1'
        );
        if (! $turno) {
            $this->markTestSkipped('Hacen falta tres profesionales con el mismo turno.');
        }
        $equipo = array_map(fn ($r) => (int) $r->id_usuario, DB::select(
            'SELECT ut.id_usuario FROM usuario_turno ut
               JOIN usuario u ON u.id_usuario = ut.id_usuario AND u.activo = 1
               JOIN rol r ON r.id_rol = u.id_rol AND r.es_personal = 1
              WHERE ut.id_turno = ? ORDER BY ut.id_usuario', [(int) $turno->id_turno]
        ));
        $dias = array_map(fn ($r) => (int) $r->dia_semana,
            DB::select('SELECT dia_semana FROM turno_dia WHERE id_turno = ?', [(int) $turno->id_turno]));

        // Tres servicios de tres zonas distintas: van a la vez, así que la
        // cita dura el más largo y la intersección se mide limpia.
        $porZona = [];
        foreach (DB::select('SELECT id_servicio, id_zona, duracion_min FROM servicio
                              WHERE activo = 1 AND id_zona IS NOT NULL ORDER BY duracion_min, id_servicio') as $x) {
            $porZona[(int) $x->id_zona] ??= (int) $x->id_servicio;
        }
        if (count($porZona) < 3) {
            $this->markTestSkipped('Hacen falta servicios de tres zonas distintas.');
        }
        $servicios = array_slice(array_values($porZona), 0, 3);

        // Un día del turno en que las tres estén libres del todo.
        $profs = null;
        $dia = null;
        for ($i = 2; $i <= 60 && ! $dia; $i++) {
            $f = date('Y-m-d', strtotime("+$i day"));
            if (! in_array((int) date('N', strtotime($f)), $dias, true)) {
                continue;
            }
            $libres = [];
            foreach ($equipo as $id) {
                if (Agenda::datosProfesional($id, $f, $f, 1)['ocupado'] === []) {
                    $libres[] = $id;
                }
                if (count($libres) === 3) {
                    break;
                }
            }
            if (count($libres) === 3) {
                $profs = $libres;
                $dia = $f;
            }
        }
        if (! $dia) {
            $this->markTestSkipped('No hay un día en que tres del mismo turno estén libres.');
        }

        $ini = strtotime($dia . ' ' . $turno->hora_inicio);
        $fin = strtotime($dia . ' ' . $turno->hora_fin);
        foreach ($profs as $i => $id) {
            $per = (int) DB::scalar('SELECT id_persona FROM usuario WHERE id_usuario = ?', [$id]);
            DB::delete('DELETE FROM persona_servicio WHERE id_persona = ?', [$per]);
            DB::insert('INSERT INTO persona_servicio (id_persona, id_servicio) VALUES (?,?)', [$per, $servicios[$i]]);
            // **Sólo cuenta ESE turno.** Quien además tiene el otro turno del
            // día seguiría con lugar a la mañana, y la prueba mediría la
            // agenda de otro momento en vez de la intersección de éste.
            $this->ocupar($id, strtotime($dia . ' 00:00:00'), $ini);
            $this->ocupar($id, $fin, strtotime($dia . ' 23:59:59'));
        }
        Agenda::olvidarQuienHace();

        return [$profs, $servicios, $dia, $ini, $fin];
    }

    /** Una ausencia puntual, para ocupar a alguien un rato. */
    private function ocupar(int $idUsuario, int $desde, int $hasta): void
    {
        DB::insert(
            'INSERT INTO ausencia_agenda (id_usuario, id_tipo_ausencia, fecha_inicio, fecha_fin, motivo, activo)
             VALUES (?, (SELECT MIN(id_tipo_ausencia) FROM tipo_ausencia), ?, ?, ?, 1)',
            [$idUsuario, date('Y-m-d H:i:s', $desde), date('Y-m-d H:i:s', $hasta), 'prueba intersección']
        );
    }

    /**
     * Con varias profesionales pedidas, el calendario ofrece la INTERSECCIÓN
     * de sus agendas — y cuando no hay, dice quién es la que no coincide.
     *
     * Es el ejemplo que dio el usuario: turno de 8 a 12, Marta ocupada de 8 a
     * 8:45, Josefina de 8:30 a 9, Fabio de 11 a 12; eligiendo a las tres, lo
     * que se tiene que ver es sólo lo que las tres pueden a la vez: de 9 hasta
     * donde la cita todavía termina antes de las 11.
     *
     * Y si una no coincide, en vez de «ese día ya no tiene horarios libres»
     * —que no dice cuál de las decisiones es la que no cierra— el sistema la
     * nombra, dice desde cuándo entra el resto sin ella, y qué hacer.
     */
    #[Test]
    public function el_calendario_ofrece_la_interseccion_de_las_agendas_pedidas_y_dice_quien_no_coincide(): void
    {
        [[$a, $b, $c], $srv, $dia, $ini, $fin] = $this->tresConElMismoTurno();
        [$sa, $sb, $sc] = $srv;

        $this->ocupar($a, $ini, $ini + 45 * 60);          // Marta: 8:00–8:45
        $this->ocupar($b, $ini + 30 * 60, $ini + 60 * 60); // Josefina: 8:30–9:00
        $this->ocupar($c, $fin - 60 * 60, $fin);           // Fabio: 11:00–12:00

        $pedidos = [$sa => $a, $sb => $b, $sc => $c];
        $dur = Agenda::duracionPrevista($srv, 1, 1, null, $pedidos);
        $horas = Agenda::slots(null, $dia, $dur, null, 1, $srv, 1, $pedidos);

        $paso = (int) config('sgp.agenda.paso_min', 15) * 60;
        $esperado = [];
        for ($m = $ini + 60 * 60; $m + $dur * 60 <= $fin - 60 * 60; $m += $paso) {
            $esperado[] = date('H:i', $m);
        }
        $this->assertNotEmpty($esperado, 'La premisa: el turno tiene que dejar lugar entre las 9 y las 11.');
        $this->assertSame($esperado, array_column($horas, 'hora'),
            'Se ofrecen exactamente las horas en que las TRES están libres a la vez, y ninguna más.');
        foreach ($horas as $h) {
            $this->assertSame($dur, $h['duracion']);
            $this->assertSame($pedidos, array_intersect_key($h['reparto'], $pedidos),
                'Cada hora lleva el reparto pedido tal cual.');
        }

        // Ahora la tercera queda ocupada toda la mañana: no hay intersección, y
        // el sistema tiene que decir que es ELLA, y desde cuándo entra el resto.
        $this->ocupar($c, $ini, $fin - 60 * 60);
        $this->assertSame([], Agenda::slots(null, $dia, $dur, null, 1, $srv, 1, $pedidos),
            'Con una de las pedidas ocupada todo el día no puede ofrecerse ninguna hora.');

        $motivo = (string) Agenda::porQueNoHayHora($dia, $srv, 1, $pedidos, 1);
        $this->assertStringContainsString('Quien no coincide es ' . Agenda::nombreDe($c), $motivo,
            'El aviso tiene que nombrar a la profesional que no coincide con las demás.');
        $this->assertStringContainsString('el resto entra de ' . date('H:i', $ini + 60 * 60), $motivo,
            'Y decir desde cuándo entra el resto sin ella: es la salida que orienta.');
        $this->assertStringContainsString('quien me atienda', $motivo,
            'Y qué hacer: dejar ese servicio en «quien me atienda» o elegir a otra persona.');
    }

    /**
     * En «quien me atienda» se reparte entre las que están libres buscando el
     * tiempo MENOR, y el guardado usa exactamente el mismo reparto.
     *
     * **El defecto que esto fija era de verdad, y tenía dos mitades.** La
     * pantalla ofrecía la hora sólo si el reparto entre las libres entraba en
     * la duración PREVISTA —el mejor caso con todo el equipo—, así que con dos
     * profesionales libres para tres servicios el día salía vacío aunque las
     * dos pudieran hacerlo en hora y media. Y el guardado buscaba a UNA persona
     * que hiciera TODO por la SUMA: si nadie lo hace todo, «no quedó nadie
     * libre que haga todo lo que elegiste», con la pantalla habiéndolo
     * ofrecido — el genérico que se pidió sacar.
     *
     * Y un tercer defecto, en la base: `trg_citaserv_bi` comprobaba la
     * habilitación del DUEÑO de la cita para cada servicio, así que una cita
     * repartida entre dos oficios distintos se rechazaba siempre. Se comprueba
     * agendando de verdad.
     */
    #[Test]
    public function sin_preferencia_se_reparte_entre_las_libres_y_el_guardado_dice_lo_mismo_que_la_pantalla(): void
    {
        [[$a, $b, $c], $srv, $dia, $ini, $fin] = $this->tresConElMismoTurno();
        [$sa, $sb, $sc] = $srv;

        // Sólo A y B trabajan ese día: el resto del local, ocupado el día entero.
        foreach (Agenda::profesionales(1) as $p) {
            $id = (int) $p->id_usuario;
            if ($id !== $a && $id !== $b) {
                $this->ocupar($id, strtotime($dia . ' 00:00:00'), strtotime($dia . ' 23:59:59'));
            }
        }
        // B hace lo de C además de lo suyo: dos zonas distintas, pero es UNA
        // persona, así que lo suyo va en serie.
        $perB = (int) DB::scalar('SELECT id_persona FROM usuario WHERE id_usuario = ?', [$b]);
        DB::insert('INSERT INTO persona_servicio (id_persona, id_servicio) VALUES (?,?)', [$perB, $sc]);
        Agenda::olvidarQuienHace();
        $info = Agenda::infoServicios($srv);
        $esperada = max($info[$sa]['min'], $info[$sb]['min'] + $info[$sc]['min']);

        $dur = Agenda::duracionPrevista($srv, 1, 1, null, []);
        $horas = Agenda::slots(null, $dia, $dur, null, 1, $srv, 1, []);
        $this->assertNotEmpty($horas,
            'Con dos profesionales libres que entre las dos hacen todo, el día tiene que ofrecer horas.');
        $primera = $horas[0];
        $this->assertSame($esperada, $primera['duracion'],
            'La hora dice cuánto dura con ESE reparto: A en paralelo con B, que hace dos cosas en serie.');
        $reparto = array_intersect_key($primera['reparto'], array_flip($srv));
        ksort($reparto);
        $esperado = [$sa => $a, $sb => $b, $sc => $b];
        ksort($esperado);
        $this->assertSame($esperado, $reparto,
            'El reparto le da a cada una lo suyo: A no puede hacer lo de B, y lo de C lo toma B.');

        // **El guardado dice lo mismo.** Antes buscaba a una persona que
        // hiciera todo, y acá no la hay.
        $cuando = $dia . ' ' . $primera['hora'] . ':00';
        $this->assertNull(Agenda::profesionalLibre($cuando, Agenda::duracion($srv), 1, $srv),
            'La premisa: nadie hace los tres servicios, así que el criterio viejo no encontraba a nadie.');
        $r = Agenda::repartoPara($srv, [$sa => 0, $sb => 0, $sc => 0], $cuando, 1, 1);
        $this->assertNotNull($r, 'El guardado tiene que encontrar el mismo reparto que ofreció la pantalla.');
        $this->assertSame($primera['reparto'], $r['reparto']);

        // **Y la base lo acepta**: el disparador mira a quien hace cada
        // servicio, no al dueño de la cita.
        $idCliente = $this->clienteLibreHoy();
        $duenio = Agenda::principalDelReparto($r['reparto']);
        $this->assertSame($b, $duenio, 'La cita queda a nombre de quien más minutos pone.');
        $idCita = Agenda::agendar($idCliente, $duenio, $cuando, $r['duracion'], null, $r['reparto'], 1);
        $this->assertGreaterThan(0, $idCita);
        $this->assertSame($a, (int) DB::scalar(
            'SELECT id_usuario FROM cita_servicio WHERE id_cita = ? AND id_servicio = ?', [$idCita, $sa]),
            'El servicio de A queda a nombre de A aunque la dueña de la cita sea B, que no lo hace.');
        $this->assertSame($esperada, (int) DB::scalar('SELECT fn_cita_duracion(?)', [$idCita]),
            'La base calcula la misma duración que anunció la pantalla.');
    }

    /**
     * El reparto de «quien me atienda» busca el tiempo MENOR, y a igual tiempo
     * ocupa a MENOS gente.
     *
     * Es la regla que pidió el usuario: si otra profesional libre puede tomar
     * un servicio de otra zona del cuerpo, se lo lleva y van a la vez; si dos
     * servicios son de la misma zona van en serie hagan lo que hagan, así que
     * los hace la misma persona en vez de ocupar a dos. Y con dos personas
     * atendidas, dos de la misma zona SÍ van a la vez, y ahí vuelven a ser dos.
     */
    #[Test]
    public function el_reparto_sin_preferencia_acorta_el_tiempo_y_no_ocupa_gente_de_mas(): void
    {
        $dos = DB::select('SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
                            WHERE u.activo = 1 AND r.es_personal = 1 ORDER BY u.id_usuario LIMIT 2');
        $zonas = DB::select('SELECT id_servicio, id_zona FROM servicio WHERE activo = 1 AND id_zona IS NOT NULL
                              ORDER BY id_zona, id_servicio');
        if (count($dos) < 2 || count($zonas) < 3) {
            $this->markTestSkipped('Hacen falta dos profesionales y servicios de dos zonas.');
        }
        [$a, $b] = array_map(fn ($u) => (int) $u->id_usuario, $dos);

        $porZona = [];
        foreach ($zonas as $z) {
            $porZona[(int) $z->id_zona][] = (int) $z->id_servicio;
        }
        $mismaZona = null;
        foreach ($porZona as $lista) {
            if (count($lista) >= 2) {
                $mismaZona = array_slice($lista, 0, 2);
                break;
            }
        }
        $distintas = array_map(fn ($l) => $l[0], array_slice(array_values($porZona), 0, 2));
        if (! $mismaZona || count($distintas) < 2) {
            $this->markTestSkipped('Hacen falta dos servicios de la misma zona y dos de zonas distintas.');
        }

        // Zonas distintas: las dos libres y las dos hacen todo → una cada una.
        $hace = [$distintas[0] => [$a, $b], $distintas[1] => [$a, $b]];
        $r = Agenda::mejorReparto($hace, [$a, $b], 1);
        $this->assertSame(2, count(array_unique($r)),
            'Dos servicios de zonas distintas se reparten entre dos personas: van a la vez y la cita termina antes.');

        // Misma zona: van en serie hagan lo que hagan → la misma persona.
        $hace = [$mismaZona[0] => [$a, $b], $mismaZona[1] => [$a, $b]];
        $r = Agenda::mejorReparto($hace, [$a, $b], 1);
        $this->assertSame(1, count(array_unique($r)),
            'Dos servicios de la misma zona los hace la misma persona: repartirlos no acorta nada y ocupa a una de más.');

        // …salvo que vengan dos personas: ahí sí van a la vez, y son dos.
        $r = Agenda::mejorReparto($hace, [$a, $b], 2);
        $this->assertSame(2, count(array_unique($r)),
            'Con dos personas atendidas, dos servicios de la misma zona van a la vez con dos profesionales.');
    }
    /**
     * Reprogramar desde el panel no deja escribir la fecha a mano.
     *
     * **Es el defecto reportado**: el modal tenía un `datetime-local` suelto,
     * así que ofrecía domingos, días en que esa persona no trabaja y horas
     * fuera de su turno — y el «no» llegaba recién al guardar. Es la regla del
     * proyecto —*las pantallas no dejan escribir una fecha a mano*— que el
     * portal cumple desde la 7.96.0 y el panel se había quedado sin aplicar.
     *
     * Se mide lo que se ve: que no haya ningún campo de fecha libre y que el
     * selector esté declarado con los servicios, el profesional y la sucursal
     * de esa cita — sin ellos consultaría la agenda equivocada.
     *
     * **Y que la columna nombre a TODOS los profesionales**, que es el otro
     * defecto de la misma pantalla: `cita_servicio.id_usuario` en NULL
     * significa «lo hace el dueño de la cita», y descartarlo dejaba una cita
     * repartida entre dos mostrando una sola persona.
     */
    #[Test]
    public function test_reprogramar_desde_el_panel_usa_el_selector_de_horarios(): void
    {
        $cita = $this->citaFuturaAgendada();

        // Un segundo servicio a nombre de OTRA persona: el primero queda en
        // NULL —o sea, del dueño— que es el caso que se perdía.
        $otro = DB::selectOne(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE r.es_personal = 1 AND u.activo = 1 AND u.id_usuario <> ? LIMIT 1',
            [$cita->id_usuario]
        );
        $srv2 = DB::selectOne(
            'SELECT id_servicio FROM servicio WHERE activo = 1
              AND id_servicio NOT IN (SELECT id_servicio FROM cita_servicio WHERE id_cita = ?) LIMIT 1',
            [$cita->id_cita]
        );
        $hayReparto = $otro && $srv2;
        if ($hayReparto) {
            DB::update('UPDATE cita_servicio SET id_usuario = NULL WHERE id_cita = ?', [$cita->id_cita]);
            DB::insert('INSERT INTO cita_servicio (id_cita, id_servicio, id_usuario) VALUES (?,?,?)',
                       [$cita->id_cita, (int) $srv2->id_servicio, (int) $otro->id_usuario]);
        }

        $this->entrarComoAdministrador();
        $html = $this->get(route('citas.agenda', ['dia' => $cita->dia]))->assertOk()->getContent();

        $this->assertStringNotContainsString('type="datetime-local"', $html,
            'El modal estaría dejando escribir la fecha a mano: ofrece días y horas que el salón no da.');
        $this->assertStringContainsString('data-agenda-servicios', $html,
            'El selector tiene que ir con los servicios de la cita: si no, consulta la agenda equivocada.');
        $this->assertStringContainsString('data-agenda-profesional', $html,
            'Y con su profesional: los horarios se calculan para quien la atiende.');

        if ($hayReparto) {
            $nombre = (string) DB::scalar(
                "SELECT TRIM(CONCAT(pe.nombre,' ',pe.apellido)) FROM usuario u
                   JOIN persona pe ON pe.id_persona = u.id_persona WHERE u.id_usuario = ?",
                [$cita->id_usuario]
            );
            $this->assertStringContainsString($nombre, $html,
                'La columna tiene que nombrar también a quien tiene el servicio en NULL: '
                . 'ese NULL es el dueño de la cita, no «nadie».');
        }
    }
    /**
     * **El comprobante sale con el correo del salón, nunca con uno ajeno.**
     *
     * `EMI|` lleva el correo que se imprime en el KuDE. Si va vacío, el
     * Automatizador cae en el `EMISOR_EMAIL` de su archivo de ejemplo
     * —`facturacion@miempresa.com`— y la clienta recibe un comprobante fiscal
     * con el correo de otra empresa: el mismo defecto que la 7.52.0 corrigió
     * con la razón social y el RUC, por otra puerta.
     *
     * El orden es fiscal primero —es el que el salón declara— y la cuenta que
     * envía como respaldo, porque es una dirección real y suya.
     */
    #[Test]
    public function el_comprobante_electronico_lleva_el_correo_del_salon(): void
    {
        $factura = DB::scalar('SELECT MAX(id_factura) FROM factura');
        if (! $factura) {
            $this->markTestSkipped('No hay ninguna factura emitida para armar el TXT.');
        }

        $linea = function (): string {
            Config::olvidar();
            foreach (explode("\n", Sifen::armarTxt((int) DB::scalar('SELECT MAX(id_factura) FROM factura'))) as $l) {
                if (str_starts_with($l, 'EMI|')) {
                    return $l;
                }
            }

            return '';
        };

        // 1. Sin correo fiscal, manda el de la cuenta que envía.
        DB::update("UPDATE configuracion SET email = NULL, mail_usuario = 'salon@ejemplo.com',
                    mail_desde = NULL WHERE id_configuracion = 1");
        $this->assertStringContainsString('|salon@ejemplo.com|', $linea(),
            'Sin correo fiscal cargado, el comprobante tiene que salir con la cuenta que envía: '
            . 'vacío, el Automatizador imprime el de su archivo de ejemplo.');

        // 2. Con correo fiscal, manda ése: es el que el salón declara.
        DB::update("UPDATE configuracion SET email = 'fiscal@ejemplo.com' WHERE id_configuracion = 1");
        $this->assertStringContainsString('|fiscal@ejemplo.com|', $linea(),
            'El correo fiscal cargado es el que tiene que ir impreso.');

        Config::olvidar();
    }

    // -----------------------------------------------------------------
    //  7.108.0
    // -----------------------------------------------------------------

    /**
     * La nota de crédito se puede emitir: el formulario no devuelve 500.
     *
     * **`$montoTexto` se leía sin existir.** La línea que lo define había
     * quedado en `anularFactura()` —donde además no se usa: anular no recibe
     * monto— así que `notaCredito()` la leía indefinida. En Laravel eso es un
     * `ErrorException`, y como el `try` empieza más abajo salía sin traducir:
     * la pantalla devolvía **500** y acreditar un comprobante era imposible.
     *
     * Se mide por el camino real —el POST del modal— y en los dos sentidos que
     * importan: **por el total** y **por una parte**, que es el que la 7.101.0
     * agregó y nunca llegó a funcionar.
     */
    #[Test]
    public function emitir_una_nota_de_credito_no_devuelve_500(): void
    {
        // Una factura de venta vigente, sin nota emitida todavía: si ya la
        // tuviera, el controlador rechaza antes de llegar al bloque del monto y
        // la prueba pasaría sin medir nada.
        $f = DB::selectOne(
            'SELECT f.id_factura, fn_factura_total(f.id_factura) AS total
               FROM factura f
               JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = f.id_tipo_comprobante
              WHERE f.id_estado_factura = 1 AND tc.signo = 1
                AND fn_factura_total(f.id_factura) > 1000
                AND NOT EXISTS (SELECT 1 FROM factura n
                                 WHERE n.id_factura_origen = f.id_factura AND n.id_estado_factura = 1)
              ORDER BY f.id_factura DESC LIMIT 1'
        );
        if (! $f) {
            $this->markTestSkipped('No hay una factura de venta sin nota de crédito para medir.');
        }
        if (! DB::scalar('SELECT fn_timbrado_vigente(5, CURDATE(), NULL)')) {
            $this->markTestSkipped('El salón no tiene timbrado de nota de crédito cargado.');
        }

        $this->entrarComoAdministrador();

        // **La devolución sale de una caja del local de la factura (7.109.0).**
        // Si esa venta se cobró en efectivo y no se dice de cuál, el controlador
        // rechaza antes de emitir: sin esto la prueba mediría el rechazo nuevo y
        // no el 500 que vino a cuidar.
        $caja = DB::scalar(
            'SELECT c.id_caja
               FROM caja c
               JOIN factura fa ON fa.id_factura = ?
               LEFT JOIN timbrado t ON t.id_timbrado = fa.id_timbrado
              WHERE c.id_estado_caja = 1
                AND c.id_sucursal = COALESCE(fa.id_sucursal, t.id_sucursal)
              ORDER BY fn_caja_saldo(c.id_caja) DESC LIMIT 1', [$f->id_factura]
        );

        // 1) Parcial: es el camino que leía la variable indefinida.
        $r = $this->post(route('facturacion.nota_credito'), [
            'id_factura' => $f->id_factura,
            'motivo' => 'Prueba automatica de acreditacion parcial',
            'monto' => (string) (int) max(1, (int) $f->total - 1),
            'id_caja' => (string) (int) $caja,
        ]);

        $this->assertNotSame(500, $r->getStatusCode(),
            'Emitir una nota de crédito devuelve 500: alguna variable se está leyendo sin definir.');
        $r->assertRedirect();

        // Y de verdad se emitió, que es la otra mitad: un redirect también lo
        // devuelve un rechazo con `flash()`, así que sin esto un controlador
        // que contestara «no se pudo» pasaría igual.
        $this->assertGreaterThan(0,
            (int) DB::scalar('SELECT COUNT(*) FROM factura WHERE id_factura_origen = ?', [$f->id_factura]),
            'La nota de crédito no llegó a emitirse.');
    }

    /**
     * Reprogramar mide la cita para la cantidad de personas que son.
     *
     * **El modal no ofrecía ni una fecha.** La cita sabe para cuántas es
     * (`cita.personas`) pero el modal no tiene la casilla —no se vuelve a
     * preguntar lo que ya está decidido— así que el selector consultaba sin
     * ella y el servidor medía el peor caso: todo en serie sobre una sola
     * clienta. Cuatro servicios de una reserva para dos daban 6 h 15 min
     * contra un turno de 6 h, y contestaba «no entra en el turno» a una cita
     * que el salón estaba por atender ese mismo día.
     *
     * Se mide en las dos direcciones: el atributo tiene que estar **y** tiene
     * que llevar el número de la cita, no un 1 fijo.
     */
    #[Test]
    public function el_modal_de_reprogramar_manda_para_cuantas_personas_es_la_cita(): void
    {
        $cita = $this->citaFuturaAgendada();
        DB::update('UPDATE cita SET personas = 3 WHERE id_cita = ?', [$cita->id_cita]);

        $this->entrarComoAdministrador();
        $html = $this->get(route('citas.agenda', ['dia' => $cita->dia]))->assertOk()->getContent();

        $this->assertStringContainsString('data-agenda-personas="3"', $html,
            'El modal de reprogramar no manda cuántas personas son: el selector va a medir el '
            . 'peor caso y no va a ofrecer ninguna fecha.');

        // Y el endpoint lo respeta: con más personas, la cita dura menos porque
        // varias cosas pasan a la vez. Si diera lo mismo, el atributo sería
        // decorativo y el defecto seguiría ahí.
        $servicios = array_map('intval', explode(',', (string) DB::scalar(
            'SELECT GROUP_CONCAT(id_servicio) FROM cita_servicio WHERE id_cita = ?', [$cita->id_cita]
        )));
        $una = Agenda::duracionPrevista($servicios, 1);
        $tres = Agenda::duracionPrevista($servicios, 3);
        $this->assertLessThanOrEqual($una, $tres,
            'Con más personas la cita no puede durar MÁS: varias cosas se hacen a la vez.');
    }

    /**
     * La agenda deja ver más de un día.
     *
     * Mostraba UN día y no había forma de ver el conjunto: para saber qué había
     * esta semana se iba día por día con la flecha, y una cita de hace tres
     * meses era inalcanzable. El día sigue siendo lo que se abre por defecto.
     */
    #[Test]
    public function la_agenda_se_puede_mirar_por_rango_y_no_solo_por_dia(): void
    {
        $this->entrarComoAdministrador();

        // Un día sin citas: si el rango no cambiara nada, las dos respuestas
        // traerían lo mismo y la prueba no mediría nada.
        $vacio = (string) DB::scalar(
            "SELECT d.f FROM (SELECT DATE_ADD(CURDATE(), INTERVAL n DAY) AS f
                                FROM (SELECT 40 n UNION SELECT 41 UNION SELECT 42
                                      UNION SELECT 43 UNION SELECT 44) x) d
              WHERE NOT EXISTS (SELECT 1 FROM cita c WHERE DATE(c.fecha_hora) = d.f)
              LIMIT 1"
        );
        if ($vacio === '') {
            $this->markTestSkipped('No hay un día vacío cercano para comparar.');
        }

        $soloDia = $this->get(route('citas.agenda', ['dia' => $vacio]))->assertOk()->getContent();
        $this->assertStringContainsString('No hay citas para el', $soloDia,
            'Ese día tendría que salir vacío: la premisa de la prueba no se cumple.');

        // Con «Todas», el historial entero: tiene que traer filas aunque el día
        // elegido no tenga ninguna.
        $todas = $this->get(route('citas.agenda', ['dia' => $vacio, 'rango' => 'todas']))
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('No hay citas para el', $todas,
            'Con el rango «Todas» la agenda sigue mirando un solo día.');
        $this->assertStringContainsString('todo el historial', $todas,
            'La agenda no dice qué tramo está mostrando.');
    }

    /**
     * Un rol puede declarar que no necesita turno.
     *
     * El aviso de «falta asignar turno» salía para todo el personal, y eso
     * incluye a quien no atiende —recepción, compras, caja—: les pedía todos
     * los días resolver algo que no era un problema. La 7.107.0 exceptuó al
     * Administrador **por id**, así que un rol nuevo volvía a tener el aviso
     * sin forma de callarlo.
     *
     * Se mide en las dos direcciones sobre la MISMA persona: con el rol
     * exigiendo turno tiene que aparecer, y sin exigirlo tiene que desaparecer.
     */
    #[Test]
    public function un_rol_puede_declarar_que_no_necesita_turno(): void
    {
        if (! (int) DB::scalar('SELECT COUNT(*) FROM usuario_turno')) {
            $this->markTestSkipped('El salón no usa turnos: el aviso no corre.');
        }

        // **La premisa se GARANTIZA, no se busca.** Una cuenta de personal sin
        // turno puede no existir hoy, y saltear la prueba la deja sin medir
        // nada justo cuando el salón está bien configurado — que es el defecto
        // que este proyecto ya se hizo cinco veces (7.103.1).
        //
        // Se toma alguien con un rol que exige turno y se le sacan los suyos:
        // corre dentro de `DatabaseTransactions`, así que se revierte solo.
        $u = DB::selectOne(
            "SELECT u.id_usuario, u.id_rol, CONCAT(pe.nombre,' ',pe.apellido) AS quien
               FROM usuario u
               JOIN rol r ON r.id_rol = u.id_rol
               JOIN persona pe ON pe.id_persona = u.id_persona
              WHERE u.activo = 1 AND r.es_personal = 1 AND r.exige_turno = 1
                AND NOT EXISTS (SELECT 1 FROM usuario_rol ur
                                 JOIN rol r2 ON r2.id_rol = ur.id_rol
                                WHERE ur.id_usuario = u.id_usuario
                                  AND r2.es_personal = 1 AND r2.exige_turno = 1
                                  AND r2.id_rol <> u.id_rol)
              ORDER BY u.id_usuario LIMIT 1"
        );
        if (! $u) {
            $this->markTestSkipped('No hay ninguna cuenta con un rol que exija turno.');
        }
        DB::delete('DELETE FROM usuario_turno WHERE id_usuario = ?', [$u->id_usuario]);

        // Y que el salón siga usando turnos después de sacárselos: si era el
        // único que los tenía, el criterio permisivo apaga el aviso entero y la
        // prueba mediría eso en vez de la regla.
        if (! (int) DB::scalar('SELECT COUNT(*) FROM usuario_turno')) {
            $this->markTestSkipped('Era la única cuenta con turno: el salón dejaría de usarlos.');
        }

        $nombres = fn (): string => implode(' ', array_map(
            fn (array $p) => (string) $p['que'], \App\Servicios\Pendientes::todo()
        ));

        $this->assertStringContainsString((string) $u->quien, $nombres(),
            'Con el rol exigiendo turno, esa persona tiene que figurar como pendiente.');

        DB::update('UPDATE rol SET exige_turno = 0 WHERE id_rol = ?', [$u->id_rol]);

        $this->assertStringNotContainsString((string) $u->quien, $nombres(),
            'Con el rol declarando que no atiende, el aviso de turno tiene que dejar de salir.');
    }

    /**
     * La clienta ve y baja el comprobante de sus citas.
     *
     * El endpoint de descarga existía desde la 7.42.0 y el único enlace hacia
     * él vivía en la pantalla de la atención en curso: el comprobante se podía
     * bajar durante las dos horas de la cita y nunca más. Quien lo necesitaba
     * para rendir un gasto lo pedía por WhatsApp.
     *
     * Y la pertenencia se comprueba de verdad: el id viaja en la URL, así que
     * la factura de otra clienta tiene que contestar 404.
     */
    #[Test]
    public function la_clienta_ve_y_baja_el_comprobante_de_su_cita(): void
    {
        $f = DB::selectOne(
            'SELECT f.id_factura, f.id_cliente, cl.id_usuario
               FROM factura f
               JOIN cliente cl ON cl.id_cliente = f.id_cliente
              WHERE f.id_estado_factura = 1 AND cl.id_usuario IS NOT NULL
              ORDER BY f.id_factura DESC LIMIT 1'
        );
        if (! $f) {
            $this->markTestSkipped('No hay una factura de una clienta con cuenta.');
        }

        session(['uid' => (int) $f->id_usuario, 'rol' => 4, 'es_personal' => false, 'es_cliente' => true]);
        $this->conMarcaDeSesion();

        $this->get(route('portal.factura_ver', ['id' => $f->id_factura]))
            ->assertOk()
            ->assertSee('Comprobante', false);

        $pdf = $this->get(route('portal.factura_descargar', ['id' => $f->id_factura]))->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent(),
            'La descarga no devolvió un PDF.');

        // La de otra clienta, no. El id se puede cambiar a mano en la URL.
        $ajena = (int) DB::scalar(
            'SELECT id_factura FROM factura WHERE id_estado_factura = 1 AND id_cliente <> ? LIMIT 1',
            [(int) $f->id_cliente]
        );
        if ($ajena) {
            $this->get(route('portal.factura_ver', ['id' => $ajena]))->assertNotFound();
        }
    }

    /**
     * El comprobante electrónico declara el descuento en vez de negarlo.
     *
     * El KuDE imprimía «DESCUENTO: 0 %» sobre una factura con descuento: el SGP
     * reparte el descuento entre los renglones antes de mandarlo —el total lo
     * calcula el Automatizador sumándolos— así que del otro lado no quedaba
     * rastro de que hubiera existido. La clienta veía un papel con los precios
     * corridos (75.000 impreso como 74.648) negando el descuento que sí se le
     * hizo.
     *
     * El precio de lista viaja como **séptimo campo opcional del ITM**, sólo
     * para mostrar: el que se declara sigue siendo el neto, así que el total no
     * cambia ni con un Automatizador viejo que ignore el campo.
     */
    #[Test]
    public function el_txt_del_comprobante_lleva_el_precio_de_lista_cuando_hay_descuento(): void
    {
        $f = DB::selectOne(
            'SELECT f.id_factura, fn_factura_descuento(f.id_factura) AS desc_total
               FROM factura f
              WHERE f.id_estado_factura = 1
                AND fn_factura_descuento(f.id_factura) > 0
              ORDER BY f.id_factura DESC LIMIT 1'
        );
        if (! $f) {
            $this->markTestSkipped('No hay una factura con descuento para medir.');
        }

        $txt = Sifen::armarTxt((int) $f->id_factura);
        $items = array_values(array_filter(explode("\n", $txt),
            fn ($l) => str_starts_with($l, 'ITM|')));
        $this->assertNotEmpty($items, 'El TXT salió sin renglones.');

        $conLista = 0;
        foreach ($items as $l) {
            $c = explode('|', $l);
            if (count($c) >= 7) {
                $conLista++;
                $this->assertGreaterThan((int) $c[4], (int) $c[6],
                    'El precio de lista tiene que ser mayor que el neto: si no, no hubo descuento '
                    . 'y el campo sobra.');
            }
        }
        $this->assertGreaterThan(0, $conLista,
            'Con descuento, al menos un renglón tiene que llevar su precio de lista: sin eso el '
            . 'KuDE vuelve a imprimir «DESCUENTO: 0 %».');

        // **Y el neto sigue siendo el que se declara.** Es la mitad que evita
        // el defecto peor: si el campo 5 pasara a ser el de lista, un
        // Automatizador viejo declararía de más ante la DNIT.
        $suma = 0;
        foreach ($items as $l) {
            $c = explode('|', $l);
            $suma += (int) round((float) $c[3] * (float) $c[4]);
        }
        $total = (int) round((float) DB::scalar('SELECT fn_factura_total(?)', [$f->id_factura]));
        $this->assertSame($total, $suma,
            'La suma de los renglones dejó de dar el total de la factura: el comprobante '
            . 'declararía un monto distinto del que la clienta pagó.');
    }

    /**
     * Los niveles de fidelización se pueden cambiar desde el sistema.
     *
     * Se podían mirar y no tocar: subir el corte de Oro de 10 a 15 visitas o
     * cambiarle el porcentaje era un `UPDATE` a mano, o sea imposible para el
     * salón. Es el caso del valor del punto (7.27.0) y del nombre del salón
     * (7.35.0) — un número comercial detrás de un despliegue.
     */
    #[Test]
    public function los_niveles_de_fidelizacion_se_cambian_desde_la_pantalla(): void
    {
        $n = DB::selectOne('SELECT * FROM nivel ORDER BY visitas_minimas DESC LIMIT 1');
        if (! $n) {
            $this->markTestSkipped('El salón no tiene niveles cargados.');
        }
        $antes = (int) $n->visitas_minimas;

        $this->entrarComoAdministrador();

        // La pantalla lo ofrece.
        $this->get(route('servicios.descuentos'))->assertOk()
            ->assertSee('Desde cuántas visitas', false);

        $this->post(route('servicios.nivel.guardar'), [
            'id_nivel' => $n->id_nivel,
            'visitas_minimas' => (string) ($antes + 7),
            'id_descuento' => (string) ($n->id_descuento ?: ''),
        ])->assertRedirect();

        $this->assertSame($antes + 7,
            (int) DB::scalar('SELECT visitas_minimas FROM nivel WHERE id_nivel = ?', [$n->id_nivel]),
            'El corte del nivel no se guardó.');

        // **Dos niveles con el mismo corte no entran.** `fn_cliente_nivel`
        // elige por `visitas_minimas`: con dos en el mismo número, el descuento
        // que le toca a la clienta pasa a depender del orden interno de la
        // tabla, o sea de algo que nadie eligió.
        $otro = DB::selectOne('SELECT * FROM nivel WHERE id_nivel <> ? LIMIT 1', [$n->id_nivel]);
        if ($otro) {
            $this->post(route('servicios.nivel.guardar'), [
                'id_nivel' => $n->id_nivel,
                'visitas_minimas' => (string) (int) $otro->visitas_minimas,
                'id_descuento' => '',
            ])->assertRedirect();

            $this->assertNotSame((int) $otro->visitas_minimas,
                (int) DB::scalar('SELECT visitas_minimas FROM nivel WHERE id_nivel = ?', [$n->id_nivel]),
                'Se aceptaron dos niveles arrancando en la misma cantidad de visitas.');
        }
    }

    // -----------------------------------------------------------------
    //  7.109.0
    // -----------------------------------------------------------------

    /**
     * La nota de crédito descuenta el efectivo de la caja QUE SE ELIGE.
     *
     * Pedido del usuario: *«Nota de crédito no disminuye caja (al disminuir
     * debe dar como opción a qué caja disminuir —perteneciente a esa
     * sucursal—)»*. Invierte a propósito lo que decidió la 7.48.0, que sacó el
     * egreso de la emisión para que no hubiera dos devoluciones por la misma
     * nota; hoy esa protección la sostiene la base —el índice único
     * `uq_movcaja_devolucion (id_factura, activo)`— así que el egreso puede
     * volver a escribirse acá sin reabrir aquel agujero.
     *
     * **Se mide con DOS cajas abiertas en el mismo local**, que es el único
     * escenario donde la pregunta significa algo: con una sola, un controlador
     * que tomara «la primera que encuentre» pasaría igual. Por eso se elige
     * deliberadamente la segunda, y se comprueba que la otra no se mueva.
     */
    #[Test]
    public function la_nota_de_credito_descuenta_el_efectivo_de_la_caja_que_se_elige(): void
    {
        if (! DB::scalar('SELECT fn_timbrado_vigente(5, CURDATE(), NULL)')) {
            $this->markTestSkipped('El salón no tiene timbrado de nota de crédito cargado.');
        }

        // Una factura de venta vigente, sin nota y **cobrada en efectivo**: sin
        // efectivo no hay nada que sacar del cajón y la prueba no mediría nada.
        // Facturas de venta vigentes, sin nota y **cobradas en efectivo**: sin
        // efectivo no hay nada que sacar del cajón y la prueba no mediría nada.
        // Se piden dos porque cada mitad gasta la suya —una factura admite una
        // sola nota vigente, así que no se la puede acreditar dos veces.
        $facturas = DB::select(
            "SELECT f.id_factura, COALESCE(f.id_sucursal, t.id_sucursal) AS id_sucursal,
                    (SELECT COALESCE(SUM(co.monto),0)
                       FROM cobro co
                       JOIN metodo_pago mp ON mp.id_metodo_pago = co.id_metodo_pago
                      WHERE co.id_estado_cobro = 1 AND mp.tipo = 'EFECTIVO'
                        AND (co.id_factura = f.id_factura OR co.id_cita = f.id_cita)) AS efectivo
               FROM factura f
               JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = f.id_tipo_comprobante
               LEFT JOIN timbrado t ON t.id_timbrado = f.id_timbrado
              WHERE f.id_estado_factura = 1 AND tc.signo = 1
                AND NOT EXISTS (SELECT 1 FROM factura n
                                 WHERE n.id_factura_origen = f.id_factura AND n.id_estado_factura = 1)
              HAVING efectivo > 0
              ORDER BY efectivo ASC LIMIT 2"
        );
        if (! $facturas) {
            $this->markTestSkipped('No hay una factura cobrada en efectivo y sin nota de crédito.');
        }
        $f = $facturas[0];

        $this->entrarComoAdministrador();
        $uid = (int) session('uid');
        $suc = (int) $f->id_sucursal;

        // **La premisa se GARANTIZA, no se espera a encontrarla.** Dos cajas
        // abiertas en ese local: las que falten se abren acá, con su cajón
        // creado al vuelo si hace falta. Todo dentro de la transacción de la
        // prueba, así que no queda nada.
        $abiertas = array_map('intval', array_column(DB::select(
            'SELECT id_caja FROM caja WHERE id_estado_caja = 1 AND id_sucursal = ? ORDER BY id_caja',
            [$suc]), 'id_caja'));

        while (count($abiertas) < 2) {
            $libre = DB::scalar(
                'SELECT cf.id_caja_fisica FROM caja_fisica cf
                  WHERE cf.id_sucursal = ? AND cf.activo = 1
                    AND NOT EXISTS (SELECT 1 FROM caja c
                                     WHERE c.id_caja_fisica = cf.id_caja_fisica AND c.id_estado_caja = 1)
                  ORDER BY cf.id_caja_fisica LIMIT 1', [$suc]);
            if (! $libre) {
                DB::insert("INSERT INTO caja_fisica (id_sucursal, nombre) VALUES (?, 'Caja de prueba')", [$suc]);
                $libre = (int) DB::scalar('SELECT LAST_INSERT_ID()');
            }
            $abiertas[] = (int) Bd::idDe('sp_abrir_caja', [$uid, 500000, (int) $libre, 'Prueba automatica'], 4);
        }

        // La SEGUNDA, a propósito: es la que un controlador que eligiera solo
        // no habría tomado.
        $elegida = $abiertas[1];
        $otra = $abiertas[0];
        $efectivo = (float) $f->efectivo;
        $antesElegida = (float) DB::scalar('SELECT fn_caja_saldo(?)', [$elegida]);
        $antesOtra = (float) DB::scalar('SELECT fn_caja_saldo(?)', [$otra]);

        // 1) **Sin decir de cuál sale, con dos abiertas, no se toca ninguna.**
        //    La nota se emite igual —es un comprobante fiscal, no se cancela
        //    por un problema de caja— pero el egreso no se escribe: adivinar
        //    dejaría el arqueo de otra persona descuadrado sin que nada lo diga,
        //    y la devolución vuelve a ser el segundo acto, desde Movimiento de
        //    efectivo. Va sobre OTRA factura porque una sólo admite una nota.
        if (count($facturas) > 1) {
            $g = $facturas[1];
            $this->post(route('facturacion.nota_credito'), [
                'id_factura' => $g->id_factura,
                'motivo' => 'Prueba automatica sin elegir caja',
            ])->assertRedirect();

            $idSinCaja = (int) DB::scalar(
                'SELECT id_factura FROM factura WHERE id_factura_origen = ? AND id_estado_factura = 1
                  ORDER BY id_factura DESC LIMIT 1', [$g->id_factura]);
            $this->assertGreaterThan(0, $idSinCaja,
                'La nota tiene que emitirse igual: el cajón no puede cancelar un comprobante fiscal.');
            $this->assertSame(0,
                (int) DB::scalar('SELECT COUNT(*) FROM movimiento_caja WHERE id_factura = ? AND activo = 1',
                    [$idSinCaja]),
                'Con dos cajas abiertas y ninguna elegida, el sistema le sacó plata a una igual.');
        }

        // 2) Eligiendo la caja, la nota sale y el cajón baja exactamente eso.
        $this->post(route('facturacion.nota_credito'), [
            'id_factura' => $f->id_factura,
            'motivo' => 'Prueba automatica de devolucion desde caja',
            'id_caja' => (string) $elegida,
        ])->assertRedirect();

        $idNota = (int) DB::scalar(
            'SELECT id_factura FROM factura WHERE id_factura_origen = ? AND id_estado_factura = 1
              ORDER BY id_factura DESC LIMIT 1', [$f->id_factura]);
        $this->assertGreaterThan(0, $idNota, 'La nota de crédito no llegó a emitirse.');

        $mov = DB::selectOne(
            'SELECT id_caja, tipo, monto FROM movimiento_caja WHERE id_factura = ? AND activo = 1',
            [$idNota]);
        $this->assertNotNull($mov, 'La nota de crédito no descontó nada de ninguna caja.');
        $this->assertSame($elegida, (int) $mov->id_caja,
            'El egreso cayó en un cajón que nadie eligió.');
        $this->assertSame('EGRESO', $mov->tipo);
        $this->assertEqualsWithDelta($efectivo, (float) $mov->monto, 0.01,
            'Se devolvió un monto distinto del que la clienta había pagado en efectivo.');

        $this->assertEqualsWithDelta($antesElegida - $efectivo,
            (float) DB::scalar('SELECT fn_caja_saldo(?)', [$elegida]), 0.01,
            'El saldo de la caja elegida no bajó lo que se devolvió.');
        $this->assertEqualsWithDelta($antesOtra,
            (float) DB::scalar('SELECT fn_caja_saldo(?)', [$otra]), 0.01,
            'Se tocó el arqueo de una caja que no era la elegida.');

        // 3) **Y no puede haber una segunda salida por la misma nota**, que es
        //    lo que la 7.48.0 vino a evitar y sigue valiendo. Lo sostiene la
        //    base con `uq_movcaja_devolucion (id_factura, activo)`; acá se mide
        //    el efecto: un solo egreso vigente, y la nota ya no aparece en la
        //    lista de devoluciones pendientes de «Movimiento de efectivo».
        $this->assertSame(1,
            (int) DB::scalar(
                'SELECT COUNT(*) FROM movimiento_caja WHERE id_factura = ? AND activo = 1', [$idNota]),
            'Quedó más de un egreso vigente por la misma nota de crédito.');

        $this->assertSame(0,
            (int) DB::scalar(
                'SELECT COUNT(*) FROM factura nc
                  WHERE nc.id_factura = ?
                    AND NOT EXISTS (SELECT 1 FROM movimiento_caja mc
                                     WHERE mc.id_factura = nc.id_factura AND mc.activo = 1)',
                [$idNota]),
            'La nota sigue figurando como devolución pendiente después de haberse devuelto.');
    }

    /**
     * «Mis citas» del portal pagina, y cada tabla conserva la página de la otra.
     *
     * Reportado por el usuario. Las dos tablas se dibujaban enteras: la de
     * anteriores cortaba con `LIMIT 50` **sin decirlo**, que es justo lo que el
     * prototipo de listado existe para evitar — a partir de la fila 51 esas
     * citas no existían para la clienta.
     *
     * **La premisa se garantiza bajando el tamaño de página**, no esperando a
     * que una clienta junte cincuenta citas: `Listado::paginacion()` lo lee de
     * la configuración, así que con 5 cualquier historial real da varias
     * páginas.
     */
    #[Test]
    public function el_portal_pagina_sus_citas_y_las_dos_tablas_no_se_pisan(): void
    {
        config(['sgp.lista.por_pagina' => 5]);

        // La clienta con más historial: con menos de una página la paginación
        // no se dibuja y la prueba pasaría sin medir nada.
        $u = DB::selectOne(
            'SELECT u.id_usuario, cl.id_cliente,
                    (SELECT COUNT(*) FROM cita c WHERE c.id_cliente = cl.id_cliente) AS citas
               FROM usuario u
               JOIN cliente cl ON cl.id_usuario = u.id_usuario
              WHERE u.activo = 1
              ORDER BY citas DESC LIMIT 1'
        );
        if (! $u || (int) $u->citas <= 5) {
            $this->markTestSkipped('Ninguna cuenta de clienta tiene más de una página de citas.');
        }

        session([
            'uid' => (int) $u->id_usuario, 'rol' => (int) config('permisos.rol_cliente', 4),
            'es_personal' => false, 'es_cliente' => true, 'id_cliente' => (int) $u->id_cliente,
        ]); $this->conSucursal();

        $r = $this->get(route('portal.citas'))->assertOk();
        $html = $r->getContent();

        $this->assertStringContainsString('Mostrando <strong>1–5</strong>', $html,
            'La lista de citas del portal no dice cuántas hay ni en qué página está.');

        // **`ph` y no `p`**: son dos tablas en la misma pantalla, así que cada
        // paginador necesita su propio parámetro o pasar de página en una las
        // mueve a las dos.
        $this->assertStringContainsString('ph=2', $html,
            'El historial no ofrece la página siguiente con su propio parámetro.');

        // Y la página 2 trae **otras** citas, que es lo único que prueba que el
        // OFFSET llegó a la consulta: un paginador dibujado sobre una lista que
        // no se recorta se ve exactamente igual. Se comparan los datos de la
        // vista y no el HTML: la pantalla dibuja además un modal por cita
        // próxima, así que buscar fechas en el marcado encuentra las de arriba.
        $primeras = array_column($r->viewData('pasadas'), 'id_cita');
        $segundas = array_column(
            $this->get(route('portal.citas', ['ph' => 2]))->assertOk()->viewData('pasadas'), 'id_cita');

        $this->assertCount(5, $primeras, 'El historial no se recortó a la página pedida.');
        $this->assertEmpty(array_intersect($primeras, $segundas),
            'La página 2 del historial muestra las mismas citas que la 1.');

        // **Y las próximas se excluyen del historial ENTERAS, no sólo las de
        // esta página.** Contando únicamente las de la página 1, la 2 mostraría
        // como pasadas las citas próximas que no entraron arriba.
        $vigentes = array_column($r->viewData('prox'), 'id_cita');
        $this->assertEmpty(array_intersect($vigentes, $segundas),
            'Una cita que todavía no ocurrió aparece en el historial.');

        // **Los dos paginadores se arrastran entre sí.** Sin esto, pasar de
        // página en «Próximas» devolvía «Anteriores» a la primera —y al revés—,
        // así que quien recorría su historial lo perdía al tocar el otro.
        $enPagina3 = $this->get(route('portal.citas', ['ph' => 3]))->assertOk()->getContent();
        preg_match_all('/\?([^"]*pp=\d+[^"]*)"/', $enPagina3, $m);
        if ($m[1]) {
            $this->assertNotEmpty(preg_grep('/ph=3/', $m[1]),
                'Pasar de página en «Próximas» pierde la página del historial.');
        }
    }

    // -----------------------------------------------------------------
    //  7.110.0
    // -----------------------------------------------------------------

    /**
     * Cada profesional cierra SU parte, y con eso su agenda queda libre.
     *
     * **Una cita de dos horas dejaba ocupadas dos horas a las dos.** La clienta
     * pide mechas con una y manicura con otra; la segunda termina en diez
     * minutos y seguía sin poder recibir a nadie, porque «atendida» era un
     * estado de la CITA y no había forma de decir que una parte ya terminó.
     *
     * Se miden las cuatro cosas que tienen que pasar a la vez, y **la tercera
     * es la que puede salir catastróficamente mal**: cerrar lo propio no puede
     * llevarse puestos los servicios que la otra todavía no hizo — antes se
     * borraba de la cita todo lo agendado sin atención, y la clienta se iría
     * sin la mitad de lo que pidió.
     */
    #[Test]
    public function cada_profesional_cierra_su_parte_y_deja_de_estar_ocupada(): void
    {
        // **La premisa se garantiza, y acá se garantiza de más.** La que cierra
        // tiene que ser una profesional **sin** acceso a la agenda entera: con
        // el Administrador —que `Agenda::profesionales()` devuelve como uno
        // más— el alcance es «todas» y la prueba mediría el otro camino. Ya
        // pasó al escribirla: `profs[1]` era el `admin`.
        $profs = array_values(array_filter(Agenda::profesionales(),
            fn ($p) => ! Permisos::rolPuede(
                (int) DB::scalar('SELECT id_rol FROM usuario WHERE id_usuario = ?', [$p->id_usuario]),
                'personal.turnos')
            && (int) DB::scalar('SELECT id_rol FROM usuario WHERE id_usuario = ?', [$p->id_usuario])
               !== (int) config('permisos.rol_admin', 1)));

        if (count($profs) < 2) {
            $this->markTestSkipped('Hacen falta dos profesionales que atiendan y no vean la agenda entera.');
        }
        [$dueno, $ayuda] = [(int) $profs[0]->id_usuario, (int) $profs[1]->id_usuario];

        // **Y cada una tiene que HACER el servicio que se le asigna.** Con dos
        // ids cualquiera, la base rechaza con «no está habilitado para alguno de
        // esos servicios» y la prueba mediría eso en vez del cierre por partes.
        $cliente = $this->clienteLibreHoy();
        $haceA = DB::select(
            'SELECT id_servicio FROM servicio WHERE activo = 1
              AND fn_usuario_hace_servicio(?, id_servicio) = 1 ORDER BY id_servicio', [$dueno]);
        $haceB = DB::select(
            'SELECT id_servicio FROM servicio WHERE activo = 1
              AND fn_usuario_hace_servicio(?, id_servicio) = 1 ORDER BY id_servicio DESC', [$ayuda]);

        $sA = (int) ($haceA[0]->id_servicio ?? 0);
        $sB = 0;
        foreach ($haceB as $x) {
            if ((int) $x->id_servicio !== $sA) {
                $sB = (int) $x->id_servicio;
                break;
            }
        }
        if (! $cliente || ! $sA || ! $sB) {
            $this->markTestSkipped('Hacen falta dos servicios distintos que esas dos personas hagan.');
        }

        DB::insert('INSERT INTO cita (id_cliente,id_usuario,id_estado_cita,fecha_hora,id_sucursal) VALUES (?,?,?,?,1)',
            [$cliente, $dueno, 1, ahora_bd('Y-m-d H:i:s')]);
        $idCita = (int) DB::getPdo()->lastInsertId();
        DB::insert('INSERT INTO cita_servicio (id_cita,id_servicio,id_usuario) VALUES (?,?,NULL)', [$idCita, $sA]);
        DB::insert('INSERT INTO cita_servicio (id_cita,id_servicio,id_usuario) VALUES (?,?,?)', [$idCita, $sB, $ayuda]);

        $this->fichar($dueno);
        $this->fichar($ayuda);

        // Antes de cerrar nada, las dos están ocupadas.
        $this->assertGreaterThan(0, (int) DB::scalar('SELECT fn_cita_duracion_de(?,?)', [$idCita, $dueno]));
        $this->assertGreaterThan(0, (int) DB::scalar('SELECT fn_cita_duracion_de(?,?)', [$idCita, $ayuda]));

        // --- La segunda cierra SÓLO lo suyo, entrando como ella ---
        Permisos::olvidar();
        session(['uid' => $ayuda, 'rol' => 2, 'es_personal' => true,
                 'es_cliente' => false, 'id_sucursal' => 1]);

        $this->post(route('citas.atender.guardar'), [
            'id_cita' => $idCita,
            'servicios' => [$sB],
        ])->assertRedirect();

        // 1) Su parte quedó marcada.
        $this->assertNotNull(
            DB::scalar('SELECT terminado_en FROM cita_servicio WHERE id_cita = ? AND id_servicio = ?', [$idCita, $sB]),
            'La parte que cerró tiene que quedar con su hora.');

        // 2) **Y deja de ocuparle la agenda**, que es para lo que existe todo
        //    esto: `fn_verificar_disponibilidad` descarta el solape con `> 0`.
        $this->assertSame(0, (int) DB::scalar('SELECT fn_cita_duracion_de(?,?)', [$idCita, $ayuda]),
            'Cerrada su parte, esa cita ya no le ocupa la agenda.');

        // 3) **La otra sigue ocupada Y su servicio sigue en la cita.** Esto es
        //    lo que no puede fallar: cerrar lo propio borraba de `cita_servicio`
        //    todo lo agendado sin atención, o sea el trabajo de la otra.
        $this->assertGreaterThan(0, (int) DB::scalar('SELECT fn_cita_duracion_de(?,?)', [$idCita, $dueno]),
            'La que todavía no cerró sigue ocupada.');
        $this->assertSame(1, (int) DB::scalar(
            'SELECT COUNT(*) FROM cita_servicio WHERE id_cita = ? AND id_servicio = ?', [$idCita, $sA]),
            'Cerrar lo propio no puede sacar de la cita el servicio que la otra todavía no hizo.');

        // 4) **Y la cita NO se puede facturar todavía**: se factura al terminar
        //    la cita entera (decisión del usuario), así que sigue En proceso.
        $this->assertSame(5, (int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita = ?', [$idCita]),
            'Con una parte abierta la cita no queda Atendida: si no, se podría facturar a medias.');

        // --- Cierra la que faltaba: recién ahí la cita queda cerrada ---
        Permisos::olvidar();
        $this->entrarComoAdministrador();
        $this->post(route('citas.atender.guardar'), [
            'id_cita' => $idCita,
            'servicios' => [$sA],
            'cerrar_de' => $dueno,
        ])->assertRedirect();

        $this->assertSame(4, (int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita = ?', [$idCita]),
            'Con todas las partes cerradas la cita queda Atendida y ya se puede facturar.');
        $this->assertSame(0, (int) DB::scalar('SELECT fn_cita_duracion_de(?,?)', [$idCita, $dueno]));
    }
    // -----------------------------------------------------------------
    //  La foto de perfil: es de la PERSONA, y se puede sacar
    // -----------------------------------------------------------------

    /**
     * Cargar la foto desde Mi cuenta, verla, y volver a las iniciales.
     *
     * **Se comprueba el ciclo entero y no sólo la subida**, porque el defecto
     * que importa está del otro lado: quitarla tiene que devolver la columna a
     * NULL *y* borrar el archivo — si no, el disco del servidor se llena de
     * caras de gente que pidió que se las sacaran.
     */
    public function test_la_foto_de_perfil_se_carga_y_se_saca(): void
    {
        $uid = (int) DB::scalar(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.activo = 1 AND r.es_personal = 0 ORDER BY u.id_usuario LIMIT 1');
        if (! $uid) {
            $this->markTestSkipped('No hay ninguna cuenta de clienta para probarlo.');
        }
        $idPersona = (int) DB::scalar('SELECT id_persona FROM usuario WHERE id_usuario = ?', [$uid]);
        $antes = DB::scalar('SELECT foto FROM persona WHERE id_persona = ?', [$idPersona]);

        session(['uid' => $uid, 'rol' => (int) DB::scalar('SELECT id_rol FROM usuario WHERE id_usuario = ?', [$uid]),
            'es_personal' => false, 'es_cliente' => true]);
        $this->conMarcaDeSesion();
        DB::update('UPDATE persona SET foto = NULL WHERE id_persona = ?', [$idPersona]);
        Perfil::olvidar();

        // --- Sin foto: van las iniciales, no un monigote genérico ---
        $sinFoto = (string) $this->get(route('cuenta.index'))->assertOk()->getContent();
        $this->assertStringContainsString('sgp-avatar', $sinFoto);
        // Y el oro de las iniciales se pinta: la clase que lo apaga no está.
        $this->assertStringNotContainsString('tiene-img', $sinFoto,
            'Sin foto el avatar lleva su fondo de oro: `tiene-img` es sólo para cuando hay imagen.');
        $this->assertStringNotContainsString('rel="preload" as="image"', $sinFoto,
            'Sin foto no hay nada que precargar.');

        // **Un PNG de verdad, no un `fake()`.** `Imagen::guardar()` mira el
        // CONTENIDO con `getimagesize` y no la extensión, que es su defensa
        // principal; y el contenedor no trae GD, así que la imagen de mentira
        // de Laravel no se puede generar acá.
        $tmp = tempnam(sys_get_temp_dir(), 'sgp') . '.png';
        file_put_contents($tmp, base64_decode(self::PNG_MINIMO));
        $nombre = '';

        // **La limpieza va en un `finally`.** `DatabaseTransactions` revierte
        // la base pero NO el disco, y si una aserción falla lo de abajo no se
        // ejecuta — quedaría la cara de alguien en `public/`, que es justo lo
        // que esta prueba dice que no puede pasar.
        try {
            $this->post(route('cuenta.foto'), [
                'foto' => new UploadedFile($tmp, 'cara.png', 'image/png', null, true),
            ])->assertRedirect(route('cuenta.index'));

            $nombre = (string) DB::scalar('SELECT foto FROM persona WHERE id_persona = ?', [$idPersona]);
            $this->assertNotSame('', $nombre, 'La foto tiene que quedar guardada en la PERSONA.');
            $this->assertFileExists(public_path('assets/personas/' . $nombre),
                'El archivo se escribe antes de tocar la base: si la fila lo nombra, tiene que estar.');

            Perfil::olvidar();
            $conFoto = (string) $this->get(route('cuenta.index'))->assertOk()->getContent();
            $this->assertStringContainsString($nombre, $conFoto);

            // **Con foto, ni el oro debajo ni la foto pedida tarde.** Se reportó
            // un parpadeo al entrar: un segundo con el disco dorado del avatar
            // por defecto y recién después la cara. Dos cosas lo evitan y las
            // dos se miden: la clase que apaga el fondo y el `preload` en el
            // `<head>`, que pide la imagen junto con el CSS y no después de
            // dibujar la barra.
            $this->assertStringContainsString('sgp-avatar tiene-img', $conFoto,
                'Con foto, el avatar de la barra apaga el fondo de oro: si no, se ve debajo mientras la foto carga.');
            $this->assertMatchesRegularExpression('/<link rel="preload" as="image" href="[^"]*' . preg_quote($nombre, '/') . '/', $conFoto,
                'La foto de perfil se precarga desde el <head>, para que esté antes de que haya que dibujarla.');

            // --- Y se saca: vuelve a NULL y el archivo se borra ---
            $this->post(route('cuenta.foto_quitar'))->assertRedirect(route('cuenta.index'));

            $this->assertNull(DB::scalar('SELECT foto FROM persona WHERE id_persona = ?', [$idPersona]),
                'Quitarla tiene que dejar la columna en NULL, que es «no cargó ninguna».');
            $this->assertFileDoesNotExist(public_path('assets/personas/' . $nombre),
                'Y el archivo se borra: es la cara de alguien que pidió que se la saquen.');
        } finally {
            if ($nombre !== '') {
                @unlink(public_path('assets/personas/' . $nombre));
            }
            DB::update('UPDATE persona SET foto = ? WHERE id_persona = ?', [$antes, $idPersona]);
            Perfil::olvidar();
            @unlink($tmp);
        }
    }

    /**
     * Las alergias se cargan también desde Mi cuenta, con el mismo formulario.
     *
     * Se reportó que «no aparece un campo de alergias en Mi cuenta»: existía, y
     * estaba sólo en «Mi ficha». Las dos son pantallas legítimas para buscarlo,
     * así que la prueba pide las dos cosas — que el campo esté, y que guardar
     * desde ahí **vuelva a ahí** y no a la otra pantalla.
     */
    public function test_las_alergias_se_cargan_tambien_desde_mi_cuenta(): void
    {
        $uid = (int) DB::scalar(
            'SELECT u.id_usuario FROM usuario u
               JOIN rol r ON r.id_rol = u.id_rol
               JOIN cliente c ON c.id_usuario = u.id_usuario
              WHERE u.activo = 1 AND r.es_personal = 0 ORDER BY u.id_usuario LIMIT 1');
        if (! $uid) {
            $this->markTestSkipped('No hay ninguna clienta con cuenta para probarlo.');
        }
        $idc = (int) DB::scalar('SELECT id_cliente FROM cliente WHERE id_usuario = ?', [$uid]);
        $antes = DB::scalar('SELECT alergias FROM cliente WHERE id_cliente = ?', [$idc]);

        session(['uid' => $uid, 'rol' => (int) DB::scalar('SELECT id_rol FROM usuario WHERE id_usuario = ?', [$uid]),
            'es_personal' => false, 'es_cliente' => true]);
        $this->conMarcaDeSesion();

        $this->get(route('cuenta.index'))
            ->assertOk()
            ->assertSee('¿Sos alérgica a algo?', false)
            ->assertSee('name="alergias"', false);

        // Guardar desde acá vuelve ACÁ: el mismo POST sirve a las dos pantallas.
        $this->post(route('portal.ficha'), ['alergias' => 'Látex', 'volver' => 'cuenta'])
            ->assertRedirect(route('cuenta.index'));
        $this->assertSame('Látex', (string) DB::scalar(
            'SELECT alergias FROM cliente WHERE id_cliente = ?', [$idc]));

        // Y desde «Mi ficha» sigue volviendo a «Mi ficha».
        $this->post(route('portal.ficha'), ['alergias' => 'Amoníaco'])
            ->assertRedirect(route('portal.ficha'));

        DB::update('UPDATE cliente SET alergias = ? WHERE id_cliente = ?', [$antes, $idc]);
    }

    /**
     * El resumen de la reserva dice DE DÓNDE sale la seña.
     *
     * Con un servicio el total se explica solo; con dos, «Gs. 315.000» es una
     * cifra que la clienta no puede comprobar — se reportó así. El desglose lo
     * arma el navegador con los `data-` de cada tarjeta, así que lo que esta
     * prueba fija es **que esos datos viajen**: si el atributo se renombra, el
     * resumen deja de sumar y no da ningún error.
     *
     * Comprobado en las dos direcciones: el que pide seña la declara con su
     * porcentaje, y el que no pide la declara en CERO — omitirla dejaría al JS
     * sumando NaN.
     */
    public function test_el_resumen_de_la_reserva_dice_de_donde_sale_la_sena(): void
    {
        $con = DB::selectOne('SELECT id_servicio, precio, sena_porcentaje FROM servicio
                               WHERE activo = 1 AND sena_porcentaje > 0 ORDER BY id_servicio LIMIT 1');
        $sin = DB::selectOne('SELECT id_servicio FROM servicio
                               WHERE activo = 1 AND sena_porcentaje IS NULL ORDER BY id_servicio LIMIT 1');
        if (! $con || ! $sin) {
            $this->markTestSkipped('Hace falta un servicio con seña y uno sin ella.');
        }

        $uid = (int) DB::scalar(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
               JOIN cliente c ON c.id_usuario = u.id_usuario
              WHERE u.activo = 1 AND r.es_personal = 0 ORDER BY u.id_usuario LIMIT 1');
        if (! $uid) {
            $this->markTestSkipped('No hay ninguna clienta con cuenta para probarlo.');
        }
        session(['uid' => $uid, 'rol' => (int) DB::scalar('SELECT id_rol FROM usuario WHERE id_usuario = ?', [$uid]),
            'es_personal' => false, 'es_cliente' => true]);
        $this->conMarcaDeSesion();

        $html = $this->get(route('portal.reservar', ['sucursal' => 1]))->assertOk()->getContent();

        $esperado = (int) round((float) $con->precio * (float) $con->sena_porcentaje / 100);
        $this->assertStringContainsString('data-sena="' . $esperado . '"', $html,
            'La tarjeta tiene que declarar cuánta seña pide ese servicio.');
        $this->assertMatchesRegularExpression(
            '/id="srv' . (int) $con->id_servicio . '"[^>]*data-sena-pct="[1-9]/s', $html,
            'Y el porcentaje, que es lo que hace comprobable el número.');

        $this->assertMatchesRegularExpression(
            '/id="srv' . (int) $sin->id_servicio . '"[^>]*data-sena="0"/s', $html,
            'El servicio sin seña tiene que declarar cero, no omitir el atributo.');

        $this->assertStringContainsString('data-resumen="sena-detalle"', $html,
            'Y el contenedor donde el desglose se dibuja.');
        $this->assertStringContainsString('Es el mínimo', $html,
            'Que el número es un mínimo se dice a la vista, no detrás del ícono de ayuda.');
    }

    /**
     * El alias dice de qué tipo es, en su propio rótulo.
     *
     * Iba abajo del número, como una instrucción suelta —«buscalo por cédula»—
     * y se reportó que confunde. Ahora es «Alias (Cédula)», con el nombre
     * saliendo del MISMO lugar que usa la pantalla donde el salón lo carga:
     * escritas aparte, las dos listas ya se habían desfasado una vez.
     */
    public function test_el_alias_dice_de_que_tipo_es_en_su_rotulo(): void
    {
        $this->assertSame('Alias (Cédula)', Pagos::rotuloAlias('CI'));
        $this->assertSame('Alias (Nº de celular)', Pagos::rotuloAlias('CELULAR'));
        // Sin tipo cargado queda «Alias» a secas, que es lo honesto: el salón
        // puede haberlo cargado sin decir de cuál se trata.
        $this->assertSame('Alias', Pagos::rotuloAlias(null));
        $this->assertSame('Alias', Pagos::rotuloAlias('LO_QUE_SEA'));

        $uid = (int) DB::scalar(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
               JOIN cliente c ON c.id_usuario = u.id_usuario
              WHERE u.activo = 1 AND r.es_personal = 0 ORDER BY u.id_usuario LIMIT 1');
        if (! $uid) {
            return;
        }
        session(['uid' => $uid, 'rol' => (int) DB::scalar('SELECT id_rol FROM usuario WHERE id_usuario = ?', [$uid]),
            'es_personal' => false, 'es_cliente' => true]);
        $this->conMarcaDeSesion();

        $html = $this->get(route('portal.citas'))->assertOk()->getContent();
        $this->assertStringNotContainsString('buscalo por', $html,
            'La instrucción suelta se fue: el tipo vive en el rótulo.');
    }

    /**
     * La huella se registra a nombre del SALÓN.
     *
     * `rp.name` es lo que el sistema operativo escribe en el diálogo de la
     * clave de acceso. Salía de `config('app.name')`, o sea del pisado que hace
     * `AppServiceProvider` al arrancar; ahora sale de la fuente.
     *
     * **El dominio que igual aparece no es esto**: el `rpId` es el dominio
     * efectivo por especificación —no admite texto libre ni una IP— y varios
     * navegadores lo muestran tal cual en su propia burbuja. Lo que esta prueba
     * fija es lo único que el sistema decide.
     */
    public function test_la_huella_se_registra_a_nombre_del_salon(): void
    {
        $this->entrarComoAdministrador();

        $pk = $this->post(route('webauthn.reg_options'))->assertOk()->json('publicKey');

        $salon = Config::nombreSalon();
        $this->assertSame($salon, $pk['rp']['name'],
            'El diálogo del sistema operativo tiene que decir el nombre del salón.');
        $this->assertStringContainsString($salon, $pk['user']['displayName'],
            'Y la credencial guardada tiene que decir de qué sistema es.');
        $this->assertSame(WebAuthn::rpId(), $pk['rp']['id'],
            'El rpId sigue siendo el dominio: lo exige la especificación.');
    }

    /**
     * Con logo cargado NO queda el ícono por defecto de fondo.
     *
     * La pastilla de oro es el fondo de la tijera, no un marco de la marca: al
     * cargar un logo la imagen se dibujaba encima con `object-fit:contain` y
     * quedaban dos bandas doradas a los costados — el ícono de antes asomando.
     * Se reportó así.
     *
     * Comprobado en las dos direcciones, que es lo que se pidió explícitamente:
     * quitando el logo tiene que volver el ícono.
     */
    public function test_el_logo_cargado_reemplaza_al_icono_por_defecto(): void
    {
        $antes = DB::scalar('SELECT logo FROM configuracion WHERE id_configuracion = 1');
        $archivo = 'logo-prueba-' . uniqid() . '.png';

        // **La limpieza va en un `finally`, no después del último assert.**
        // `DatabaseTransactions` revierte la base pero NO el disco, y si una
        // aserción falla lo de abajo no se ejecuta: el archivo queda ahí. Pasó
        // exactamente eso al comprobar esta prueba en reversa.
        try {
            // --- Sin logo: la tijera, con su fondo dorado ---
            DB::update('UPDATE configuracion SET logo = NULL WHERE id_configuracion = 1');
            Config::olvidar();
            $html = $this->get(route('login'))->assertOk()->getContent();
            $this->assertStringContainsString('bi-scissors', $html, 'Sin logo va la tijera de la identidad.');
            $this->assertStringNotContainsString('tiene-img', $html);

            // --- Con logo: la imagen, y el fondo del ícono se va ---
            file_put_contents(public_path('assets/logo/' . $archivo), base64_decode(self::PNG_MINIMO));
            DB::update('UPDATE configuracion SET logo = ? WHERE id_configuracion = 1', [$archivo]);
            Config::olvidar();

            $html = $this->get(route('login'))->assertOk()->getContent();
            $this->assertStringContainsString('tiene-img', $html,
                'Con logo cargado, el contenedor pierde el fondo dorado del ícono.');
            $this->assertStringNotContainsString('bi-scissors', $html,
                'Y la tijera no se dibuja: no puede quedar el ícono viejo detrás.');
        } finally {
            @unlink(public_path('assets/logo/' . $archivo));
            DB::update('UPDATE configuracion SET logo = ? WHERE id_configuracion = 1', [$antes]);
            Config::olvidar();
        }
    }

    // -----------------------------------------------------------------
    //  La cita de varias se cobra y se factura junta O por persona
    // -----------------------------------------------------------------

    /**
     * Cada persona de la cita se lleva SU comprobante, con lo suyo adentro.
     *
     * `cita.personas` dice cuántas vienen desde la 7.57.0 y `cita_acompanante`
     * quiénes desde la 7.97.0; lo que faltaba era **qué servicio es de cuál**.
     * Sin eso la cita era una bolsa —«tres personas: corte, mechas,
     * manicura»— y no había forma de cobrarle a una sólo lo suyo ni de hacerle
     * su factura: el mostrador terminaba dividiendo a mano.
     *
     * Se mide en las dos direcciones que importan: el comprobante de una
     * persona trae **sólo** sus servicios, y el de la otra los suyos. Con una
     * sola de las dos, un `p_persona` ignorado pasaría igual.
     */
    #[Test]
    public function la_cita_de_varias_se_factura_por_persona(): void
    {
        $cita = $this->citaFuturaAgendada();

        // La cita pasa a ser de dos, con un servicio de cada una. El segundo
        // se agrega a mano: `sp_agendar_cita` arma la cita de la titular.
        $otro = (int) DB::scalar(
            'SELECT s.id_servicio FROM servicio s
              WHERE s.activo = 1 AND s.precio > 0
                AND NOT EXISTS (SELECT 1 FROM cita_servicio cs
                                 WHERE cs.id_cita = ? AND cs.id_servicio = s.id_servicio)
              ORDER BY s.id_servicio LIMIT 1', [(int) $cita->id_cita]
        );
        $this->assertGreaterThan(0, $otro, 'Premisa: hace falta un segundo servicio con precio.');

        DB::update('UPDATE cita SET personas = 2, id_estado_cita = 4 WHERE id_cita = ?', [(int) $cita->id_cita]);
        DB::update('UPDATE cita_servicio SET persona = 1 WHERE id_cita = ?', [(int) $cita->id_cita]);
        DB::insert('INSERT INTO cita_servicio (id_cita, id_servicio, persona) VALUES (?, ?, 2)',
            [(int) $cita->id_cita, $otro]);

        $mio = (int) DB::scalar('SELECT id_servicio FROM cita_servicio WHERE id_cita = ? AND persona = 1',
            [(int) $cita->id_cita]);
        $tipo = (int) config('sifen.tipo_defecto', 1);

        // El comprobante de la persona 2: sólo su servicio.
        $f2 = Facturacion::emitir((int) $cita->id_cliente, (int) $cita->id_cita,
            (int) $cita->id_usuario, $tipo, 1, 2);
        $this->assertGreaterThan(0, $f2, 'No se pudo emitir el comprobante de la segunda persona.');

        $deF2 = array_map('intval', array_column(
            DB::select('SELECT id_servicio FROM detalle_factura WHERE id_factura = ?', [$f2]), 'id_servicio'));

        $this->assertSame([$otro], $deF2,
            'El comprobante de una persona tiene que traer SÓLO los servicios de esa persona.');
        $this->assertSame(2, (int) DB::scalar('SELECT persona FROM factura WHERE id_factura = ?', [$f2]),
            'El comprobante tiene que quedar marcado como de esa persona.');

        // Y el de la persona 1, el suyo: son dos comprobantes distintos.
        $f1 = Facturacion::emitir((int) $cita->id_cliente, (int) $cita->id_cita,
            (int) $cita->id_usuario, $tipo, 1, 1);
        $deF1 = array_map('intval', array_column(
            DB::select('SELECT id_servicio FROM detalle_factura WHERE id_factura = ?', [$f1]), 'id_servicio'));

        $this->assertSame([$mio], $deF1,
            'Cada persona se lleva su comprobante con lo suyo, no con lo de la otra.');

        // **Y el saldo de cada uno mira sólo los cobros de SU persona.** Si no,
        // pagando una se daría por saldada la otra — que es lo que hace
        // `fn_factura_saldo` con todo lo cobrado contra la cita.
        $totalF2 = (float) DB::scalar('SELECT fn_factura_total(?)', [$f2]);
        $this->assertGreaterThan(0, $totalF2, 'Premisa: el servicio de la segunda tiene que valer algo.');

        $metodo = (int) DB::scalar('SELECT MIN(id_metodo_pago) FROM metodo_pago WHERE activo = 1');
        Facturacion::sena((int) $cita->id_cita, $metodo, (int) $cita->id_usuario,
            $totalF2, 'TEST-PERSONA-2', 0, 2);

        $this->assertEqualsWithDelta(0, (float) DB::scalar('SELECT fn_factura_saldo(?)', [$f2]), 0.01,
            'El cobro de esa persona tiene que saldar SU comprobante.');
        $this->assertGreaterThan(0.01, (float) DB::scalar('SELECT fn_factura_saldo(?)', [$f1]),
            'Y no puede saldar el de la otra: cada una paga lo suyo.');
    }

    /**
     * Cobrada por persona, la factura sale por persona — y la de la otra
     * después, desde las mismas pantallas.
     *
     * Reportado tal cual (7.119.0): *«aparece la opción de pago individual
     * pero al final hace la factura por el pago grupal y deja una deuda de lo
     * que le corresponde a la otra persona, pero luego no se genera el
     * comprobante de ese pago al registrarlo»*. La base sabía emitir por
     * persona desde la 7.117.0; las PANTALLAS no: `emitir` no leía `persona`
     * y su lista excluía toda cita con alguna factura, así que la segunda
     * amiga se quedaba sin comprobante.
     *
     * Recorre el camino real: el cobro de una desde la agenda, la pantalla de
     * emitir con ELLA elegida, su factura con sólo lo suyo y saldada; la cita
     * sigue en la lista, «toda la cita» apagada; y el cobro y la factura de la
     * otra cierran la cita. Con el `persona` ignorado, la primera factura
     * saldría de toda la cita y el segundo `emitir` contestaría «ya tiene una
     * factura emitida».
     */
    #[Test]
    public function la_cita_cobrada_por_persona_se_factura_por_persona_y_la_otra_despues(): void
    {
        $this->entrarComo('admin', 'admin123');
        $suc = (int) session('id_sucursal');
        $cajon = $this->cajonDe($suc);
        if (! DB::scalar('SELECT COUNT(*) FROM caja WHERE id_caja_fisica = ? AND id_estado_caja = 1', [$cajon])) {
            DB::insert('INSERT INTO caja (id_usuario, id_sucursal, id_caja_fisica, id_estado_caja, monto_inicial)
                        VALUES (1, ?, ?, 1, 0)', [$suc, $cajon]);
        }
        Caja::olvidar();

        // Una cita atendida de dos, con un servicio de cada una.
        $cita = $this->citaFuturaAgendada();
        $idCita = (int) $cita->id_cita;
        $otro = (int) DB::scalar(
            'SELECT s.id_servicio FROM servicio s
              WHERE s.activo = 1 AND s.precio > 0
                AND NOT EXISTS (SELECT 1 FROM cita_servicio cs WHERE cs.id_cita = ? AND cs.id_servicio = s.id_servicio)
              ORDER BY s.id_servicio LIMIT 1', [$idCita]);
        DB::update('UPDATE cita SET personas = 2, id_estado_cita = 4, id_sucursal = ? WHERE id_cita = ?', [$suc, $idCita]);
        DB::update('UPDATE cita_servicio SET persona = 1 WHERE id_cita = ?', [$idCita]);
        DB::insert('INSERT INTO cita_servicio (id_cita, id_servicio, persona) VALUES (?, ?, 2)', [$idCita, $otro]);
        DB::insert('INSERT INTO cita_acompanante (id_cita, orden, nombre, apellido) VALUES (?, 2, ?, ?)',
            [$idCita, 'Josefina', 'Prueba']);
        $mio = (int) DB::scalar('SELECT id_servicio FROM cita_servicio WHERE id_cita = ? AND persona = 1', [$idCita]);

        $citaObj = DB::selectOne(
            "SELECT c.*, CONCAT(pe.nombre,' ',pe.apellido) AS cliente FROM cita c
               JOIN cliente cl ON cl.id_cliente = c.id_cliente JOIN persona pe ON pe.id_persona = cl.id_persona
              WHERE c.id_cita = ?", [$idCita]);
        $cuenta = Acompanantes::cuenta($citaObj, Acompanantes::deCitas([$idCita])[$idCita] ?? []);
        $efectivo = (int) DB::scalar("SELECT MIN(id_metodo_pago) FROM metodo_pago WHERE activo = 1 AND tipo = 'EFECTIVO'");

        // 1) Josefina paga lo suyo desde la agenda: el sistema manda a emitir SU comprobante.
        $r = $this->post(route('facturacion.sena'), [
            'id_cita' => $idCita, 'dia' => date('Y-m-d'), 'modo_pago' => 'persona', 'persona' => 2,
            'metodo' => [$efectivo], 'monto' => [(string) (int) $cuenta[2]['total']],
        ]);
        $r->assertRedirect(route('facturacion.emitir', ['cita' => $idCita, 'persona' => 2]));

        // 2) La pantalla de emitir la trae ELEGIDA, y ofrece «toda la cita» todavía.
        $html = $this->get(route('facturacion.emitir', ['cita' => $idCita, 'persona' => 2]))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="2"[^>]*selected[^>]*>\s*Sólo Josefina Prueba/', $html,
            'La pantalla de emitir tiene que venir con la persona cobrada ya elegida.');

        // Emitir por las pantallas: con SIFEN prendido, `emitir.guardar` pasa
        // por la del receptor —que tiene que arrastrar la persona— y es ésa
        // la que emite; apagado, emite directo. El camino real en los dos casos.
        $emitir = function (int $persona) use ($idCita): void {
            $r = $this->post(route('facturacion.emitir.guardar'), [
                'id_cita' => $idCita, 'persona' => $persona, 'id_tipo_comprobante' => '1', 'id_condicion_venta' => 1,
            ]);
            $destino = (string) $r->headers->get('Location');
            if (str_contains($destino, 'receptor')) {
                $this->assertStringContainsString('persona=' . $persona, $destino,
                    'La pantalla del receptor tiene que recibir de quién es el comprobante.');
                $this->post(route('facturacion.receptor.guardar'), [
                    'id_cita' => $idCita, 'persona' => $persona, 'id_tipo_comprobante' => 1, 'id_condicion_venta' => 1,
                    'tipo_doc' => 'CF', 'documento' => '', 'nombre' => '', 'email' => '', 'direccion' => '', 'telefono' => '',
                ]);
            }
        };

        // 3) Su factura: sólo lo suyo, marcada como suya, y saldada por SU cobro.
        $emitir(2);
        $f2 = DB::selectOne('SELECT id_factura, persona FROM factura WHERE id_cita = ? AND id_estado_factura = 1 ORDER BY id_factura DESC LIMIT 1', [$idCita]);
        $msgs = array_column((array) session('sgp_flash', []), 'msg');
        $this->assertNotNull($f2, 'La factura de Josefina tiene que emitirse: ' . (string) end($msgs));
        $this->assertSame(2, (int) $f2->persona, 'Y tiene que ser SUYA, no de toda la cita: ése era el defecto.');
        $this->assertSame([$otro], array_map('intval', array_column(
            DB::select('SELECT id_servicio FROM detalle_factura WHERE id_factura = ?', [(int) $f2->id_factura]), 'id_servicio')),
            'Con sólo sus servicios adentro.');
        $this->assertEqualsWithDelta(0, (float) DB::scalar('SELECT fn_factura_saldo(?)', [(int) $f2->id_factura]), 0.01,
            'Y saldada con lo que ella pagó: nada de dejarle una deuda a la otra.');

        // 4) La cita sigue en la lista de emitir —falta la de Andrea— y «toda
        //    la cita» ya no se ofrece: volvería a cobrar lo de Josefina.
        $html = $this->get(route('facturacion.emitir', ['cita' => $idCita]))->assertOk()->getContent();
        $this->assertStringContainsString('name="id_cita" value="' . $idCita . '"', $html,
            'A medio facturar, la cita tiene que seguir en la lista: la segunda todavía no tiene el suyo.');
        $this->assertMatchesRegularExpression('/<option value="0"[^>]*disabled[^>]*>\s*Toda la cita/', $html,
            'Con una ya facturada, «toda la cita» se apaga.');

        // 5) Cobrar «todo junto» ahora se rechaza: se cobra por persona.
        $this->post(route('facturacion.sena'), [
            'id_cita' => $idCita, 'dia' => date('Y-m-d'), 'modo_pago' => 'junto',
            'metodo' => [$efectivo], 'monto' => [(string) (int) $cuenta[1]['total']],
        ]);
        $this->assertSame(1, (int) DB::scalar('SELECT COUNT(*) FROM cobro WHERE id_cita = ? AND id_estado_cobro = 1', [$idCita]),
            'Con una ya facturada, el cobro «de todas» no entra.');

        // 6) Andrea paga lo suyo y se lleva el suyo: la cita queda facturada entera.
        $this->post(route('facturacion.sena'), [
            'id_cita' => $idCita, 'dia' => date('Y-m-d'), 'modo_pago' => 'persona', 'persona' => 1,
            'metodo' => [$efectivo], 'monto' => [(string) (int) $cuenta[1]['total']],
        ])->assertRedirect(route('facturacion.emitir', ['cita' => $idCita, 'persona' => 1]));
        $emitir(1);
        $f1 = DB::selectOne('SELECT id_factura, persona FROM factura WHERE id_cita = ? AND id_estado_factura = 1 AND persona = 1', [$idCita]);
        $msgs = array_column((array) session('sgp_flash', []), 'msg');
        $this->assertNotNull($f1, 'La factura de la titular también tiene que salir: ' . (string) end($msgs));
        $this->assertSame([$mio], array_map('intval', array_column(
            DB::select('SELECT id_servicio FROM detalle_factura WHERE id_factura = ?', [(int) $f1->id_factura]), 'id_servicio')));
        $this->assertEqualsWithDelta(0, (float) DB::scalar('SELECT fn_factura_saldo(?)', [(int) $f1->id_factura]), 0.01);

        $html = $this->get(route('facturacion.emitir'))->assertOk()->getContent();
        $this->assertStringNotContainsString('name="id_cita" value="' . $idCita . '"', $html,
            'Con las dos facturadas, la cita sale de la lista.');

        // 7) Y la lista de facturas dice de qué cita es cada una, y de quién.
        $html = $this->get(route('facturacion.facturas', ['q' => $citaObj->cliente]))->assertOk()->getContent();
        $this->assertStringContainsString('de Josefina Prueba', $html,
            'La lista de facturas tiene que decir de quién es el comprobante de UNA persona.');
        $this->assertStringContainsString(fecha($citaObj->fecha_hora), $html,
            'Y de qué cita: la fecha y hora, no sólo la clienta.');
    }

    // -----------------------------------------------------------------
    //  La atención ya cobrada no se vuelve a cobrar
    // -----------------------------------------------------------------

    /**
     * Cobrada, la fila deja de ofrecer «Cobrar»: lo que falta es emitir.
     *
     * **Dos administradores sobre la misma agenda.** Uno cobra la atención y
     * al otro le sigue apareciendo el botón —su pantalla es una foto de un
     * minuto antes—, así que puede volver a cobrarle a la misma clienta. La
     * base lo rechaza, pero después del clic y con un mensaje que no dice que
     * ya estaba cobrada. Se reportó así: *«esto abre a la posibilidad de que
     * se le cobre doble a un cliente, no debe pasar»*.
     *
     * Se mide en las dos direcciones: sin cobrar ofrece «Cobrar», y cobrada
     * ofrece «Emitir». Con una sola mitad, una fila que nunca ofreciera nada
     * pasaría igual.
     */
    #[Test]
    public function la_cita_ya_cobrada_no_vuelve_a_ofrecer_cobrar(): void
    {
        $this->entrarComo('admin', 'admin123');
        $suc = (int) session('id_sucursal');

        // Hace falta caja abierta: sin eso la agenda no dibuja ningún botón de
        // cobro y la prueba mediría el caso equivocado.
        $cajon = $this->cajonDe($suc);
        if (! DB::scalar('SELECT COUNT(*) FROM caja WHERE id_caja_fisica = ? AND id_estado_caja = 1', [$cajon])) {
            DB::insert('INSERT INTO caja (id_usuario, id_sucursal, id_caja_fisica, id_estado_caja, monto_inicial)
                        VALUES (1, ?, ?, 1, 0)', [$suc, $cajon]);
        }
        Caja::olvidar();

        $cita = $this->citaFuturaAgendada($suc);
        DB::update('UPDATE cita SET id_estado_cita = 4 WHERE id_cita = ?', [(int) $cita->id_cita]);

        // La celda de acciones de ESA fila: arranca en el botón «Detalle»
        // —que apunta a la ventana de la cita— y termina con la fila. Desde
        // la 7.118.0 el botón está siempre, así que no hace falta cargarle
        // una observación a la cita para que aparezca el ancla.
        $fila = function () use ($cita): string {
            $html = (string) $this->get(route('citas.agenda', ['dia' => $cita->dia]))->assertOk()->getContent();
            $ini = strpos($html, '#detCita' . (int) $cita->id_cita . '"');
            $this->assertNotFalse($ini, 'La cita tiene que aparecer en la agenda de su día.');
            $fin = strpos($html, '</tr>', (int) $ini);

            return substr($html, (int) $ini, ($fin ?: strlen($html)) - (int) $ini);
        };

        $this->assertStringContainsString('Cobrar', $fila(),
            'Atendida y sin cobrar: la fila tiene que ofrecer cobrar.');

        // Se cobra todo lo que vale la cita, que es lo que hace el otro admin.
        $total = (float) DB::scalar('SELECT fn_cita_total(?)', [(int) $cita->id_cita]);
        $this->assertGreaterThan(0, $total, 'Premisa: la cita tiene que valer algo.');
        Facturacion::sena((int) $cita->id_cita,
            (int) DB::scalar('SELECT MIN(id_metodo_pago) FROM metodo_pago WHERE activo = 1'),
            1, $total, 'TEST-DOBLE-COBRO', 0);

        $ahora = $fila();
        $this->assertStringNotContainsString('Cobrar', $ahora,
            'Ya cobrada, la fila NO puede volver a ofrecer cobrar: ahí se le cobra dos veces a la clienta.');
        $this->assertStringContainsString('Emitir', $ahora,
            'Lo que falta es el comprobante, y la fila tiene que decirlo.');
    }

    // -----------------------------------------------------------------
    //  Registrar atención: lo que se elige y lo que ya está decidido
    // -----------------------------------------------------------------

    /**
     * «Ver atención» muestra lo que pasó; no ofrece elegir nada.
     *
     * Se reportó así: *«Ver atención debe dejar de mostrar el buscador y la
     * selección»*. Con la cita ya cerrada la lista son los tres o cuatro
     * servicios que se hicieron, y ahí las dos piezas informan mal: un campo
     * para filtrar cuatro renglones no filtra nada —y se lee como que hay algo
     * más que buscar—, y una casilla es una invitación, se lee como que ahí se
     * decide algo cuando lo que hay es lo que ya ocurrió.
     *
     * **Los datos no se sacan, cambia cómo se dibujan**: por eso la prueba
     * exige que el servicio hecho siga apareciendo. Se mide en las dos
     * direcciones, que es lo que la vuelve una prueba: con la cita abierta las
     * dos piezas tienen que estar.
     */
    #[Test]
    public function ver_atencion_no_ofrece_el_buscador_ni_la_seleccion(): void
    {
        $this->entrarComo('admin', 'admin123');
        $cita = $this->citaFuturaAgendada((int) session('id_sucursal'));
        $srv = (int) DB::scalar('SELECT id_servicio FROM cita_servicio WHERE id_cita = ?', [(int) $cita->id_cita]);

        // Abierta: se elige, así que el buscador y las casillas van.
        $abierta = (string) $this->get(route('citas.atender', ['id' => (int) $cita->id_cita]))
            ->assertOk()->getContent();
        $this->assertStringContainsString('data-filtra="#listaServiciosAt"', $abierta,
            'Con la cita abierta el buscador sirve: la lista es el catálogo entero.');
        $this->assertStringContainsString('id="sa' . $srv . '"', $abierta,
            'Y el servicio se marca con una casilla, que es lo que lo agrega.');

        // Cerrada: se mira. La atencion queda REGISTRADA, que es lo que esa
        // pantalla lista —lo que se hizo, no lo que se habia reservado—.
        DB::insert('INSERT INTO servicio_realizado (id_cita, id_servicio, id_usuario) VALUES (?,?,?)',
            [(int) $cita->id_cita, $srv, (int) $cita->id_usuario]);
        DB::update('UPDATE cita SET id_estado_cita = 4 WHERE id_cita = ?', [(int) $cita->id_cita]);
        $cerrada = (string) $this->get(route('citas.atender', ['id' => (int) $cita->id_cita]))
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('data-filtra="#listaServiciosAt"', $cerrada,
            'Con la cita cerrada el buscador promete algo más para buscar, y no hay nada.');
        $this->assertStringNotContainsString('id="sa' . $srv . '"', $cerrada,
            'Y la casilla se lee como que ahí se decide algo: lo que hay es lo que ya pasó.');
        $this->assertStringContainsString('sgp-lista-hecho', $cerrada,
            'Lo hecho se sigue viendo: lo que cambia es que deja de parecer un formulario.');
    }

    /**
     * La lista de «se agrega en el sillón» sólo ofrece lo que esa persona hace.
     *
     * Se reportó junto con lo anterior: *«la lista de más servicios sólo puede
     * mostrar los servicios que el profesional hace»*. Ofrecía el catálogo
     * entero, así que una peluquera veía quince renglones para elegir entre
     * ellos los tres que sabe hacer — y marcando otro, el rechazo llegaba
     * después de guardar.
     *
     * **Y lo que no hace no desaparece: se guarda detrás de «con otra
     * profesional»**, que es el otro pedido de la misma tanda —*«antes de
     * finalizar debe haber una opción de poner un servicio adicional con otro
     * profesional»*—. La clienta está en el sillón, pide las uñas, y eso lo
     * hace otra persona: hasta acá la única salida era agendarle una cita
     * aparte.
     *
     * Se mide en las dos direcciones sobre la MISMA pantalla: lo suyo tiene
     * que estar en la lista de arriba y no en el bloque plegado, y lo ajeno al
     * revés. Con una sola mitad, una lista vacía pasaría igual.
     */
    #[Test]
    public function en_la_atencion_los_servicios_de_otra_profesional_van_aparte(): void
    {
        $this->entrarComo('admin', 'admin123');
        $cita = $this->citaFuturaAgendada((int) session('id_sucursal'));

        // Dos servicios que no están en la cita: uno lo hace quien atiende y el
        // otro no. **La premisa se GARANTIZA**: sin ninguna fila cargada,
        // `fn_usuario_hace_servicio` es permisiva y los hace todos, así que la
        // prueba mediría una pantalla sin nada aparte.
        $fuera = array_map('intval', array_column(DB::select(
            'SELECT s.id_servicio FROM servicio s
              WHERE s.activo = 1
                AND NOT EXISTS (SELECT 1 FROM cita_servicio cs
                                 WHERE cs.id_cita = ? AND cs.id_servicio = s.id_servicio)
              ORDER BY s.id_servicio LIMIT 2', [(int) $cita->id_cita]), 'id_servicio'));
        $this->assertCount(2, $fuera, 'Premisa: hacen falta dos servicios fuera de la cita.');
        [$mio, $ajeno] = $fuera;

        $persona = (int) DB::scalar('SELECT id_persona FROM usuario WHERE id_usuario = ?', [(int) session('uid')]);
        DB::delete('DELETE FROM persona_servicio WHERE id_persona = ?', [$persona]);
        DB::insert('INSERT INTO persona_servicio (id_persona, id_servicio) VALUES (?, ?)', [$persona, $mio]);

        $html = (string) $this->get(route('citas.atender', ['id' => (int) $cita->id_cita]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('id="otraProf"', $html,
            'Lo que esa persona no hace no se esconde: se ofrece con quién hacerlo.');

        // El bloque de arriba es lo que ella puede sumar sola; el de abajo, lo
        // que necesita a otra. Se leen por separado porque son dos decisiones.
        $ini = strpos($html, 'Se agrega durante la atención');
        $fin = strpos($html, 'Sumar un servicio con otra profesional');
        $this->assertNotFalse($ini, 'Tiene que haber lista de «se agrega en el sillón».');
        $this->assertNotFalse($fin, 'Y el bloque de la otra profesional.');
        $suyos = substr($html, (int) $ini, (int) $fin - (int) $ini);
        $deOtra = substr($html, (int) $fin);

        $this->assertStringContainsString('id="sa' . $mio . '"', $suyos,
            'Lo que ella hace se marca ahí mismo.');
        $this->assertStringNotContainsString('id="sa' . $ajeno . '"', $suyos,
            'Y lo que no hace no puede ofrecérsele como si pudiera: el «no» llegaría al guardar.');
        $this->assertStringContainsString('id="sa' . $ajeno . '"', $deOtra,
            'Lo ajeno va en el bloque de «con otra profesional», que es donde se elige con quién.');
    }

    // -----------------------------------------------------------------
    //  En qué local se trabaja: se dice y se cambia en el mismo lugar
    // -----------------------------------------------------------------

    /**
     * El local se cambia desde la barra, no desde Mi cuenta.
     *
     * Lo pidió el usuario: *«el botón que permite cambiar entre sucursales
     * que está ubicado en MI CUENTA se eliminará y se desplazará la lógica de
     * cambio de sucursal al panel principal e irá como combo»*. Eran dos
     * piezas para una sola cosa —un chip que decía en qué local se estaba y
     * unos botones, dos pantallas más allá, que lo cambiaban—, así que mover
     * el sistema entero de sucursal obligaba a salir de la pantalla en la que
     * se estaba trabajando.
     *
     * Se mide en las tres direcciones que importan: la barra lo **ofrece** con
     * los locales de esa persona, Mi cuenta ya **no** lo ofrece, y lo elegido
     * es lo que **queda** — con las dos primeras solas, un combo decorativo
     * pasaría igual, que es exactamente lo que le pasó a la campanita.
     */
    #[Test]
    public function el_local_se_cambia_desde_la_barra_y_no_desde_mi_cuenta(): void
    {
        $this->entrarComo('admin', 'admin123');
        $uid = (int) session('uid');
        $antes = (int) session('id_sucursal');

        // Hace falta un segundo local asignado: con uno solo el combo no tiene
        // nada que elegir y la prueba mediría media pantalla.
        DB::insert('INSERT INTO sucursal (nombre, activo) VALUES (?, 1)', ['Prueba Barra']);
        $otra = (int) DB::scalar('SELECT LAST_INSERT_ID()');
        DB::insert('INSERT IGNORE INTO usuario_sucursal (id_usuario, id_sucursal) VALUES (?,?)', [$uid, $antes]);
        DB::insert('INSERT IGNORE INTO usuario_sucursal (id_usuario, id_sucursal) VALUES (?,?)', [$uid, $otra]);

        $panel = (string) $this->get(route('panel'))->assertOk()->getContent();
        $this->assertStringContainsString('class="sgp-suc-chip sgp-suc-combo"', $panel,
            'La barra tiene que ofrecer el combo de local.');
        $this->assertStringContainsString('<option value="' . $otra . '"', $panel,
            'Y tiene que ofrecer los locales de esa persona, no uno solo.');
        $this->assertStringContainsString('id="sgpSucIr"', $panel,
            'Con el respaldo sin JavaScript: `app.js` lo esconde, pero tiene que estar dibujado.');

        // En Mi cuenta el unico que queda es el de la barra, que la envuelve:
        // por eso se CUENTA en vez de buscarlo: dos veces serian dos lugares
        // para lo mismo, que es lo que se vino a sacar.
        $cuenta = (string) $this->get(route('cuenta.index'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($cuenta, 'action="' . route('sucursal.entrar') . '"'),
            'Mi cuenta ya no cambia de local: el unico formulario es el de la barra.');

        // Y lo elegido es lo que queda.
        $this->post(route('sucursal.entrar'), ['id_sucursal' => $otra]);
        $this->assertSame($otra, (int) session('id_sucursal'),
            'El combo tiene que cambiar de verdad el local en el que se trabaja.');
    }

    // -----------------------------------------------------------------
    //  La campanita: lo que está pasando AHORA
    // -----------------------------------------------------------------

    /**
     * La caja que quedó abierta suena en la campanita, y sólo para quien la cierra.
     *
     * Lo pidió el usuario: *«se agregó una campanita de alertas, allí se dirá
     * si la caja está mucho tiempo abierta»*. Una caja que sigue abierta al
     * día siguiente es una que nadie contó: los cobros del día nuevo entran al
     * mismo arqueo que los de ayer, y cuando alguien la cierre la diferencia
     * ya no dice de qué día vino.
     *
     * Se mide en las dos direcciones, y la segunda es la que importa: **a
     * quien no maneja la caja no se le dice nada**, que es la misma regla que
     * `Pendientes` — un aviso que no aplica enseña a ignorar los que sí. Y se
     * comprueba que la barra lo DIBUJE: el servicio existía y la campanita era
     * un enlace a `#`, o sea la función apagada en silencio de siempre.
     */
    #[Test]
    public function la_campanita_avisa_la_caja_que_quedo_abierta_y_solo_a_quien_la_cierra(): void
    {
        $this->entrarComo('admin', 'admin123');
        $suc = (int) session('id_sucursal');
        $cajon = $this->cajonDe($suc);

        // Sin ninguna abierta de más, la campanita no dice nada de cajas.
        DB::update('UPDATE caja SET id_estado_caja = 2, fecha_cierre = NOW()
                     WHERE id_estado_caja = 1 AND id_sucursal = ?', [$suc]);
        Caja::olvidar();
        $this->assertSame([], array_values(array_filter(Alertas::todas(),
            fn ($a) => $a['nivel'] === 'CAJA')),
            'Sin ninguna caja vieja abierta, la campanita no tiene por qué sonar.');

        DB::insert('INSERT INTO caja (id_usuario, id_sucursal, id_caja_fisica, id_estado_caja,
                                      monto_inicial, fecha_apertura)
                    VALUES (1, ?, ?, 1, 0, DATE_SUB(NOW(), INTERVAL 2 DAY))', [$suc, $cajon]);
        Caja::olvidar();

        $mias = Alertas::mias();
        $this->assertNotEmpty($mias, 'La caja abierta desde anteayer tiene que aparecer en la campanita.');
        $this->assertSame('facturacion.caja', $mias[0]['permiso'],
            'El aviso tiene que decir con qué permiso se resuelve, como los pendientes.');
        $this->assertStringContainsString('días', $mias[0]['que'],
            'Y desde cuándo: «hace mucho» no dice si hay que correr o no.');

        // **Y la barra lo dibuja.** El servicio andaba y la campanita era un
        // enlace muerto: sin esto, el aviso existe y no lo lee nadie.
        $html = (string) $this->get(route('panel'))->assertOk()->getContent();
        $this->assertStringContainsString('sgp-campana-n', $html,
            'La campanita tiene que mostrar cuántas cosas hay para resolver.');
        $this->assertStringContainsString('sgp-alerta-que', $html,
            'Y el aviso entero, que es lo que dice qué hacer.');
        $this->assertStringContainsString('>Avisos<', $html,
            'La bandeja lleva un solo rótulo, «Avisos» (pedido del usuario, 7.118.1).');
        $this->assertStringNotContainsString('Ahora mismo', $html,
            'El grupo «Ahora mismo» se fue: la caja abierta va bajo «Avisos».');

        // A quien no maneja la caja, nada: no puede cerrarla.
        DB::update('UPDATE usuario SET id_rol = 2 WHERE id_usuario = 1');
        session(['rol' => 2]);
        Permisos::olvidar();

        $this->assertSame([], array_values(array_filter(Alertas::mias(),
            fn ($a) => $a['permiso'] === 'facturacion.caja')),
            'El Profesional no cierra cajas: avisarle es ruido que le tapa lo que sí es suyo.');
    }

    /**
     * Abrir la campanita baja el número; lo que falta cargar sigue contando.
     *
     * Lo pidió el usuario con esas dos mitades: *«el número que indica las
     * notificaciones se eliminará/bajará según se haya visto o abierto nada
     * más desde la campana (excepción de los contenidos del FALTA CARGAR, para
     * ellos el número seguirá presente hasta ser atendidos)»*.
     *
     * La distinción no es caprichosa y es la misma que separa a `Alertas` de
     * `Pendientes`: una caja abierta desde ayer **ya se la leyó** —queda en la
     * bandeja, sigue pasando, pero no hace falta que siga gritando—; un
     * timbrado sin cargar **no se resuelve mirándolo**, así que apagarle el
     * número sería apagarle el aviso al salón.
     *
     * Se mide en las dos direcciones sobre la misma cuenta: después de verlas,
     * la alerta deja de contar y el pendiente **no**.
     */
    #[Test]
    public function la_campanita_baja_el_numero_al_verla_y_lo_que_falta_cargar_no(): void
    {
        $this->entrarComo('admin', 'admin123');
        $suc = (int) session('id_sucursal');
        $cajon = $this->cajonDe($suc);

        // **La premisa se garantiza: esta persona no vio nada todavía.** Desde
        // que el stock faltante suena en la campanita (7.118.0) hay avisos
        // que viven en la base de prueba y sobreviven entre corridas —el
        // faltante no lo crea esta prueba—, así que con sólo abrir la
        // campanita en el navegador como `admin` uno de ellos quedaba visto y
        // la prueba se ponía roja sin que el sistema hubiera cambiado. Va
        // dentro de la transacción: no toca lo que la persona vio de verdad.
        DB::delete('DELETE FROM alerta_vista WHERE id_usuario = 1');

        // Una caja vieja abierta —la alerta— y algo sin cargar —el pendiente—.
        DB::update('UPDATE caja SET id_estado_caja = 2, fecha_cierre = NOW()
                     WHERE id_estado_caja = 1 AND id_sucursal = ?', [$suc]);
        DB::insert('INSERT INTO caja (id_usuario, id_sucursal, id_caja_fisica, id_estado_caja,
                                      monto_inicial, fecha_apertura)
                    VALUES (1, ?, ?, 1, 0, DATE_SUB(NOW(), INTERVAL 2 DAY))', [$suc, $cajon]);
        Caja::olvidar();

        $prof = (int) DB::scalar(
            'SELECT u.id_usuario FROM usuario u
               JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.activo = 1 AND r.es_personal = 1
                AND EXISTS (SELECT 1 FROM usuario_turno t WHERE t.id_usuario = u.id_usuario)
              LIMIT 1');
        $this->assertGreaterThan(0, $prof, 'Premisa: hace falta alguien con turno.');
        DB::update('UPDATE comision SET activo = 0 WHERE id_usuario = ?', [$prof]);

        $alertas = Alertas::mias();
        $pendientes = \App\Servicios\Pendientes::mios();
        $this->assertNotEmpty($alertas, 'Premisa: tiene que haber una alerta.');
        $this->assertNotEmpty($pendientes, 'Premisa: tiene que faltar algo por cargar.');
        $this->assertSame([], array_values(array_filter($alertas, fn ($a) => $a['visto'])),
            'Recién aparecida, la alerta todavía no se vio.');

        $sinVerAntes = count($pendientes) + count(array_filter($alertas, fn ($a) => ! $a['visto']));

        // Abrir la campanita es esto: marcar lo que se mostró.
        $this->post(route('alertas.vistas'), ['claves' => array_column($alertas, 'clave')])
            ->assertOk();

        $despues = Alertas::mias();
        $this->assertSame([], array_values(array_filter($despues, fn ($a) => ! $a['visto'])),
            'Vista una vez, la alerta deja de contar.');
        $this->assertCount(count($alertas), $despues,
            'Pero NO desaparece: la caja sigue abierta, así que el renglón se queda en la bandeja.');

        // **Y lo que falta cargar sigue contando**, que es la excepción.
        $pendDespues = \App\Servicios\Pendientes::mios();
        $this->assertSame([], array_values(array_filter($pendDespues, fn ($p) => $p['visto'])),
            'Un pendiente no se puede marcar como visto: mirarlo no lo resuelve.');

        $sinVerDespues = count($pendDespues)
            + count(array_filter($despues, fn ($a) => ! $a['visto']));
        $this->assertLessThan($sinVerAntes, $sinVerDespues, 'El número tiene que bajar.');
        $this->assertSame(count($pendDespues), $sinVerDespues,
            'Y lo que queda contando es exactamente lo que falta cargar.');

        // Un POST armado a mano no marca cualquier cosa.
        $this->assertSame(0, Alertas::marcarVistas(['caja:999999', 'pend:loquesea']),
            'Sólo se marca lo que hoy está en la campanita de quien llama.');
    }

    // -----------------------------------------------------------------
    //  Las alergias son de CADA persona de la cita, no de la cita
    // -----------------------------------------------------------------

    /**
     * Cada persona de la cita tiene las suyas, y cada una va a su lugar.
     *
     * `cliente.alergias` alcanzaba mientras la cita fuera de una sola persona
     * con ficha. No es el caso: la cita puede ser **para otra persona** —cuyo
     * nombre va como texto en `cita.nombre_para`, porque el salón no la
     * registró— y pueden venir varias, que viven en `cita_acompanante`. Así
     * que en una cita de tres el sistema anotaba **una sola** alergia, y en
     * una «para otra persona» la que anotaba era la de alguien que ese día ni
     * viene.
     *
     * Se mide en las dos direcciones que importan: que cada alergia quede
     * atribuida a SU persona, y que la de la clienta que reservó **no** se le
     * cuelgue a la que se atiende en su lugar.
     */
    #[Test]
    public function las_alergias_son_de_cada_persona_de_la_cita(): void
    {
        $cita = $this->citaFuturaAgendada();

        // La clienta que reservó tiene lo suyo en su ficha, como siempre.
        DB::update('UPDATE cliente SET alergias = ? WHERE id_cliente = ?',
                   ['Amoniaco de la titular', $cita->id_cliente]);

        // Y la cita es para su hija, que viene con una amiga.
        DB::update('UPDATE cita SET para_otra_persona = 1, nombre_para = ?, alergias_para = ?, personas = 2
                     WHERE id_cita = ?',
                   ['Josefina Villalba', 'Latex y PPD', $cita->id_cita]);

        Acompanantes::guardar($cita->id_cita,
            [2 => 'Marta'], [2 => 'Duarte'], 2, [2 => 'Niquel']);

        $fila = DB::selectOne(
            'SELECT c.para_otra_persona, c.nombre_para, c.alergias_para,
                    (SELECT cl.alergias FROM cliente cl WHERE cl.id_cliente = c.id_cliente) AS alergias,
                    (SELECT CONCAT(pe.nombre, \' \', pe.apellido)
                       FROM cliente cl JOIN persona pe ON pe.id_persona = cl.id_persona
                      WHERE cl.id_cliente = c.id_cliente) AS cliente
               FROM cita c WHERE c.id_cita = ?', [$cita->id_cita]
        );
        $acomp = Acompanantes::deCitas([$cita->id_cita])[$cita->id_cita] ?? [];

        $this->assertCount(1, $acomp, 'La premisa: la acompañante tiene que haberse guardado.');
        $this->assertSame('Niquel', $acomp[0]->alergias,
            'La alergia de quien acompaña va con su nombre, en la cita: no tiene ficha donde dejarla.');

        $gente = Alergias::deLaCita($fila, $acomp);

        $this->assertCount(2, $gente,
            'Se atienden dos: la hija —que ocupa el lugar de la titular— y la amiga.');
        $this->assertSame('Josefina Villalba', $gente[0]->quien);
        $this->assertSame('Latex y PPD', $gente[0]->alergias,
            'La que se atiende es ella, así que la alergia que vale es la suya.');
        $this->assertSame('Marta Duarte', $gente[1]->quien);
        $this->assertSame('Niquel', $gente[1]->alergias);

        // **La otra mitad, que es la que estaba mal**: la de la clienta que
        // reservó no puede aparecer, porque ese día no se atiende.
        foreach ($gente as $p) {
            $this->assertNotSame('Amoniaco de la titular', $p->alergias,
                'La alergia de quien reservó no dice nada de quien se sienta en el sillón.');
        }

        // Y sin «para otra persona», la que se atiende es ella y vale su ficha.
        DB::update('UPDATE cita SET para_otra_persona = 0, nombre_para = NULL, alergias_para = NULL
                     WHERE id_cita = ?', [$cita->id_cita]);
        $fila2 = DB::selectOne(
            'SELECT c.para_otra_persona, c.nombre_para, c.alergias_para,
                    (SELECT cl.alergias FROM cliente cl WHERE cl.id_cliente = c.id_cliente) AS alergias,
                    (SELECT CONCAT(pe.nombre, \' \', pe.apellido)
                       FROM cliente cl JOIN persona pe ON pe.id_persona = cl.id_persona
                      WHERE cl.id_cliente = c.id_cliente) AS cliente
               FROM cita c WHERE c.id_cita = ?', [$cita->id_cita]
        );
        $this->assertSame('Amoniaco de la titular', Alergias::deLaCita($fila2, $acomp)[0]->alergias);
    }

    /**
     * La agenda las muestra y dice de quién es cada una.
     *
     * «Maní» a secas en una cita de tres es media advertencia: no dice a quién
     * no se le puede dar. La fila mostraba una sola —la de la ficha de quien
     * reservó— así que las otras dos personas se sentaban sin que nadie
     * supiera con qué no se las puede tocar.
     */
    #[Test]
    public function la_agenda_discrimina_la_alergia_de_cada_persona(): void
    {
        $cita = $this->citaFuturaAgendada();

        DB::update('UPDATE cita SET para_otra_persona = 1, nombre_para = ?, alergias_para = ?, personas = 2
                     WHERE id_cita = ?',
                   ['Josefina Villalba', 'AlergiaDeJosefina', $cita->id_cita]);
        Acompanantes::guardar($cita->id_cita,
            [2 => 'Marta'], [2 => 'Duarte'], 2, [2 => 'AlergiaDeMarta']);

        $this->entrarComoAdministrador();
        $html = $this->get(route('citas.agenda', ['dia' => $cita->dia]))->assertOk()->getContent();

        $this->assertStringContainsString('AlergiaDeJosefina', $html,
            'La alergia de quien se atiende tiene que verse en la fila, no escondida.');
        $this->assertStringContainsString('AlergiaDeMarta', $html,
            'Y la de quien la acompaña también: se la atiende igual.');

        // Discriminadas: cada una con el nombre de su dueña al lado. Sin eso,
        // dos alergias sueltas en la misma fila no se pueden atribuir.
        $this->assertMatchesRegularExpression(
            '/Josefina Villalba.{0,120}AlergiaDeJosefina/su', $html,
            'Cada alergia va con el nombre de quien la tiene.');
        $this->assertMatchesRegularExpression(
            '/Marta Duarte.{0,120}AlergiaDeMarta/su', $html);
    }

    /**
     * Las dos pantallas que agendan piden las alergias de cada uno.
     *
     * Se pidió explícitamente que estén en las dos —portal y mostrador—, y es
     * el andamiaje lo que se mide: si un campo se renombra, el formulario no
     * da ningún error, simplemente deja de guardar la alergia. Es el patrón
     * que este proyecto ya tiene anotado.
     */
    #[Test]
    public function las_dos_pantallas_de_agendar_piden_las_alergias(): void
    {
        // --- El mostrador ---
        $this->entrarComoAdministrador();
        $html = $this->get(route('citas.form'))->assertOk()->getContent();

        // **Con el `name=` entero y no el nombre suelto.** Buscando el
        // nombre a secas, un campo renombrado a `alergias_paraX` seguiría
        // conteniéndolo: la prueba pasaría sin medir nada. Comprobado.
        foreach (['name="alergias_titular"', 'name="alergias_titular_base"',
                  'name="alergias_para"', 'data-alergias='] as $campo) {
            $this->assertStringContainsString($campo, $html,
                "Nueva cita tiene que ofrecer «{$campo}».");
        }

        // --- El portal ---
        $idc = (int) DB::scalar('SELECT id_cliente FROM cliente WHERE id_usuario IS NOT NULL
                                  AND activo = 1 ORDER BY id_cliente LIMIT 1');
        $this->assertNotSame(0, $idc, 'La premisa: hace falta una clienta con cuenta.');
        $uid = (int) DB::scalar('SELECT id_usuario FROM cliente WHERE id_cliente = ?', [$idc]);
        session([
            'uid' => $uid, 'rol' => (int) config('permisos.rol_cliente', 4),
            'es_personal' => false, 'es_cliente' => true, 'id_cliente' => $idc,
        ]);
        $this->conMarcaDeSesion();

        $suc = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');
        $html = $this->get(route('portal.reservar', ['sucursal' => $suc]))->assertOk()->getContent();

        foreach (['name="alergias_titular"', 'name="alergias_titular_base"',
                  'name="alergias_para"'] as $campo) {
            $this->assertStringContainsString($campo, $html,
                "Reservar tiene que ofrecer «{$campo}».");
        }

        // Y el JS que dibuja a los acompañantes tiene que saber de la alergia:
        // sin ese campo, en una cita de tres sólo se puede cargar una.
        $js = file_get_contents(public_path('assets/js/app.js'));
        $this->assertStringContainsString('acomp_alergias[', $js,
            'Cada acompañante lleva su propio campo de alergias.');
    }

    /**
     * Agendar sin tocar el campo NO le borra a la clienta lo que ya tenía.
     *
     * Es el defecto que este arreglo podía introducir, y del peor tipo: en
     * silencio, sobre el único dato de la ficha que puede lastimar a alguien.
     * En «Nueva cita» el campo arranca vacío —la clienta se elige en esa misma
     * pantalla— así que sin la comparación contra el valor con el que se
     * dibujó, agendarle una cita le vaciaba las alergias.
     */
    #[Test]
    public function agendar_no_borra_las_alergias_que_la_clienta_ya_tenia(): void
    {
        $idc = (int) DB::scalar('SELECT id_cliente FROM cliente WHERE activo = 1 ORDER BY id_cliente LIMIT 1');
        DB::update('UPDATE cliente SET alergias = ? WHERE id_cliente = ?', ['PPD y amoniaco', $idc]);

        $leer = fn () => DB::scalar('SELECT alergias FROM cliente WHERE id_cliente = ?', [$idc]);

        // 1. El formulario no mandó nada: no se toca. Es el caso de un POST
        //    viejo, o de la pantalla sin JavaScript.
        Alergias::guardarDelTitular(new Request(), $idc);
        $this->assertSame('PPD y amoniaco', $leer(),
            'Sin el campo en el POST, lo cargado se queda como está.');

        // 2. Se dibujó con lo que tenía y no se tocó: tampoco.
        Alergias::guardarDelTitular(
            new Request(['alergias_titular' => 'PPD y amoniaco',
                         'alergias_titular_base' => 'PPD y amoniaco']), $idc);
        $this->assertSame('PPD y amoniaco', $leer());

        // 3. Se corrigió: se guarda.
        Alergias::guardarDelTitular(
            new Request(['alergias_titular' => 'PPD, amoniaco y niquel',
                         'alergias_titular_base' => 'PPD y amoniaco']), $idc);
        $this->assertSame('PPD, amoniaco y niquel', $leer());

        // 4. Se vació a propósito: se borra, y queda NULL —«sin registrar»—
        //    y no una cadena vacía, que la pantalla no sabría distinguir.
        Alergias::guardarDelTitular(
            new Request(['alergias_titular' => '',
                         'alergias_titular_base' => 'PPD, amoniaco y niquel']), $idc);
        $this->assertNull($leer(), 'Borrarlas a propósito tiene que poder hacerse.');
    }


    /**
     * Los filtros «select» de las listas ofrecen SÓLO lo que hay.
     *
     * Se reportó sobre Facturas → Comprobante: el combo ofrecía los OCHO tipos
     * del catálogo —seis dados de baja en la 7.9.0 y la 7.85.0, sin un solo
     * comprobante emitido— y elegir cualquiera de ellos devolvía la lista
     * vacía. «Da opciones que no hay.» Ahora las opciones salen de las filas
     * que la pantalla lista (`Listado::opcionesUsadas()`), con el mismo
     * alcance que la lista, así que un tipo sin comprobantes no aparece y uno
     * que empiece a usarse aparece solo. Vale igual para Cobros → Medio de
     * pago e Inventario → Movimientos → Tipo, que ofrecían medios sin un solo
     * cobro y «Venta de producto», que está fuera de alcance.
     *
     * La premisa se garantiza y no se espera: se crea un tipo de comprobante
     * NUEVO, activo y sin comprobantes, y un medio de pago igual, y se exige
     * que ninguno de los dos se ofrezca. Y en la otra dirección, que lo
     * ofrecido sea EXACTAMENTE lo que hay emitido en el local — con el
     * catálogo entero de antes, esto falla.
     */
    #[Test]
    public function los_filtros_de_las_listas_ofrecen_solo_lo_que_hay(): void
    {
        DB::insert("INSERT INTO tipo_comprobante (codigo, nombre, signo, activo) VALUES ('ZZ', 'Tipo de prueba', 1, 1)");
        DB::insert("INSERT INTO metodo_pago (nombre, tipo, activo) VALUES ('Medio de prueba', 'OTRO', 1)");
        $idMedio = (int) DB::scalar("SELECT id_metodo_pago FROM metodo_pago WHERE nombre = 'Medio de prueba'");

        $this->entrarComo('admin', 'admin123');
        $suc = Sucursales::activa();

        $opciones = function (string $html, string $campo): array {
            preg_match('/<select[^>]*name="' . $campo . '"[^>]*>(.*?)<\/select>/s', $html, $m);
            $this->assertNotEmpty($m, "No se dibujó el filtro «{$campo}».");
            preg_match_all('/<option value="([^"]*)"/', $m[1], $ops);
            $out = array_map('html_entity_decode', array_filter($ops[1], fn ($v) => $v !== ''));
            sort($out);

            return array_values($out);
        };

        // ---- Facturas → Comprobante: lo que hay emitido en este local ----
        $html = (string) $this->get(route('facturacion.facturas'))->assertOk()->getContent();
        $ofrecidos = $opciones($html, 'tipo');
        $hay = array_map(fn ($r) => (string) $r->nombre, DB::select(
            'SELECT DISTINCT tc.nombre
               FROM factura fa
               JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = fa.id_tipo_comprobante
               JOIN timbrado t ON t.id_timbrado = fa.id_timbrado
              WHERE t.id_sucursal = ?', [$suc]));
        sort($hay);
        $this->assertNotEmpty($hay, 'Premisa: el local tiene comprobantes emitidos.');
        $this->assertSame($hay, $ofrecidos,
            'El filtro «Comprobante» tiene que ofrecer exactamente los tipos con comprobantes en el local.');
        $this->assertNotContains('Tipo de prueba', $ofrecidos,
            'Un tipo activo pero sin comprobantes no es una opción: filtrar por él devuelve vacío.');
        $this->assertGreaterThan(count($ofrecidos), (int) DB::scalar('SELECT COUNT(*) FROM tipo_comprobante'),
            'Premisa: el catálogo tiene más tipos que los que se usan, si no la prueba no mide nada.');

        // ---- Cobros → Medio de pago: lo que hay cobrado ----
        $html = (string) $this->get(route('facturacion.cobros'))->assertOk()->getContent();
        $ofrecidos = $opciones($html, 'metodo');
        $hay = array_map(fn ($r) => (string) $r->id_metodo_pago,
            DB::select('SELECT DISTINCT id_metodo_pago FROM cobro'));
        sort($hay);
        $this->assertNotEmpty($hay, 'Premisa: hay cobros.');
        $this->assertSame($hay, $ofrecidos,
            'El filtro «Medio de pago» tiene que ofrecer exactamente los medios con cobros.');
        $this->assertNotContains((string) $idMedio, $ofrecidos,
            'Un medio de pago activo pero sin cobros no es una opción.');

        // ---- Inventario → Movimientos → Tipo: lo que hay movido ----
        $html = (string) $this->get(route('inventario.movimientos'))->assertOk()->getContent();
        $ofrecidos = $opciones($html, 'tipo');
        $hay = array_map(fn ($r) => (string) $r->id_tipo_movimiento,
            DB::select('SELECT DISTINCT id_tipo_movimiento FROM movimiento_inventario'));
        sort($hay);
        $this->assertNotEmpty($hay, 'Premisa: hay movimientos de stock.');
        $this->assertSame($hay, $ofrecidos,
            'El filtro «Tipo» de Movimientos tiene que ofrecer exactamente las clases con movimientos.');
        $ventaDeProducto = (string) DB::scalar("SELECT id_tipo_movimiento FROM tipo_movimiento_inventario WHERE nombre = 'Venta de producto'");
        if ($ventaDeProducto !== '' && ! in_array($ventaDeProducto, $hay, true)) {
            $this->assertNotContains($ventaDeProducto, $ofrecidos,
                '«Venta de producto» está fuera de alcance: no puede ofrecerse como filtro.');
        }
    }

    /**
     * El panel lista TODAS las cajas abiertas del local, y las mismas para todos.
     *
     * La barra mostraba UNA —`Caja::abierta()`, que prefiere la que abrió
     * quien mira—, así que con dos cajones abiertos cada administrador veía
     * una caja distinta y un saldo distinto en el mismo panel, y ninguno
     * sabía que había otra. Se reportó así: «esa notificación muestra
     * diferente para cada admin dependiendo qué caja haya abierto».
     *
     * Premisa garantizada: dos cajones nuevos del local, abiertos por DOS
     * personas distintas y con montos distintos; el panel se mira como cada
     * una, y tiene que decir lo mismo — las dos cajas, los dos saldos, en el
     * mismo orden. Con la barra de antes, cada una veía sólo la suya.
     */
    #[Test]
    public function el_panel_lista_todas_las_cajas_abiertas_del_local_y_las_mismas_para_todos(): void
    {
        $suc = 1;
        $admin = (int) DB::scalar("SELECT id_usuario FROM usuario WHERE username = 'admin'");
        $otro = (int) (DB::scalar('SELECT id_usuario FROM usuario WHERE id_rol = 3 AND activo = 1 LIMIT 1') ?: 0);
        $this->assertGreaterThan(0, $otro, 'Premisa: hace falta una segunda persona con caja.');

        $nombre = 'Panel ' . uniqid();
        DB::insert('INSERT INTO caja_fisica (id_sucursal, nombre) VALUES (?, ?)', [$suc, $nombre . ' A']);
        $a = (int) DB::scalar('SELECT LAST_INSERT_ID()');
        DB::insert('INSERT INTO caja_fisica (id_sucursal, nombre) VALUES (?, ?)', [$suc, $nombre . ' B']);
        $b = (int) DB::scalar('SELECT LAST_INSERT_ID()');
        Caja::abrir($admin, 111000.0, $a);
        Caja::abrir($otro, 222000.0, $b);

        // **Y la cuenta bancaria, al lado de las cajas** (7.121.1): una con
        // saldo declarado, que tiene que salir con su número, y una sin
        // declarar, que tiene que decirlo con palabras — NULL no es cero.
        $conSaldo = $this->cuentaDePrueba($suc, 333000, 1, 'Banco del panel ' . $nombre);
        $sinSaldo = $this->cuentaDePrueba($suc, 0, 0, 'Billetera del panel ' . $nombre);
        DB::update('UPDATE cuenta_bancaria SET saldo_declarado = NULL, saldo_declarado_en = NULL WHERE id_cuenta = ?', [$sinSaldo]);

        $barra = function (): string {
            Caja::olvidar();
            $html = (string) $this->get(route('panel'))->assertOk()->getContent();
            $ini = strpos($html, 'sgp-caja-barra');
            $fin = strpos($html, 'sgp-metrics');
            $this->assertNotFalse($ini, 'El panel no dibujó la barra de caja.');

            return substr($html, $ini, $fin - $ini);
        };

        $comprobar = function (string $html, string $quien) use ($nombre): void {
            foreach ([$nombre . ' A', $nombre . ' B', money(111000), money(222000)] as $t) {
                $this->assertStringContainsString($t, $html,
                    "Mirando como $quien, la barra no muestra «{$t}»: tiene que listar TODAS las cajas abiertas.");
            }
            $this->assertMatchesRegularExpression('/\d+ cajas abiertas/', $html,
                "Mirando como $quien, la barra no dice cuántas cajas hay abiertas.");
            $this->assertLessThan(strpos($html, $nombre . ' B'), strpos($html, $nombre . ' A'),
                'Las cajas van por nombre, en el mismo orden para todos.');

            // El estado financiero son las dos cajas: el cajón Y el banco.
            $this->assertStringContainsString('Estado financiero', $html,
                'El bloque se llama «Estado financiero»: cuánta plata hay son el cajón y el banco.');
            $this->assertStringContainsString('Banco del panel ' . $nombre, $html,
                "Mirando como $quien, el panel no muestra la cuenta bancaria del local.");
            $this->assertStringContainsString(money(333000), $html,
                'La cuenta tiene que salir con su saldo: es lo que hay en el banco.');
            $this->assertStringContainsString('sin declarar', $html,
                'La cuenta sin saldo declarado lo dice con palabras: NULL no es cero.');
        };

        // Quien abrió la A…
        $this->entrarComo('admin', 'admin123');
        $this->conSucursal($suc);
        $comprobar($barra(), 'quien abrió la caja A');

        // …y quien abrió la B ven exactamente lo mismo.
        session(['uid' => $otro, 'rol' => 3, 'es_personal' => true, 'es_cliente' => false]);
        $this->conSucursal($suc);
        $comprobar($barra(), 'quien abrió la caja B');
    }

    /**
     * El XML y el KuDE del Automatizador dicen lo mismo: precio de lista,
     * descuento en monto, y neto bajo su tasa.
     *
     * Pedido del usuario sobre el KuDE: PRECIO UNITARIO, DESCUENTO en monto —un
     * servicio de 100.000 al 20 % muestra 20.000 y 80.000 bajo «10%»—, EXENTA,
     * 5 %, 10 %; y sin la fila «DESCUENTO» del pie, que iba en cero. Y como el
     * KuDE es la representación gráfica del XML, el XML declara lo mismo: E721
     * el precio de lista, EA002 el descuento particular por ítem con su EA003,
     * EA008 el neto; y en el pie F009 y F011. **El total no cambia**: EA008
     * sale del neto, que es el campo 5 del ITM, como siempre.
     *
     * Corre las clases del Automatizador en el mismo proceso —son PHP puro,
     * sin dependencias— sobre un TXT con un ítem descontado, otro sin campo 7
     * y uno exento con descuento. Con el `SifenXmlBuilder` de antes, E727
     * salía neto y EA008 descontado dos veces; con el KuDE de antes, sale
     * «%DESC» y «DESCUENTO: 0 %».
     */
    #[Test]
    public function el_xml_y_el_kude_declaran_el_precio_de_lista_y_el_descuento_por_item(): void
    {
        foreach (['src/TxtParser.php', 'src/InvoiceFactory.php', 'motor/Service/TotalsCalculator.php',
                  'motor/Service/InvoiceMapper.php', 'motor/Service/SifenXmlBuilder.php',
                  'motor/Support/FileStore.php', 'motor/Service/QrCode.php', 'motor/Service/KudeService.php'] as $archivo) {
            if (! is_file(base_path('_sifen/' . $archivo))) {
                $this->markTestSkipped('El Automatizador no está en esta copia.');
            }
            require_once base_path('_sifen/' . $archivo);
        }

        $txt = "EMI|Eternal Beauty|80000000|5|Av. Aquino 1234|Luque|021000000|f@salon.com|96021|PELUQUERIA|28283352|2026-08-22|2027-08-22|Sucursal Luque\n"
             . "FAC|001|001|0009901|2026-09-11|1|PYG|2\n"
             . "CLI|CI|5777742|Noelia Villalba|noelia@correo.com||0981000000\n"
             . "ITM|S001|Corte de dama|1|80000|10|100000\n"      // 20 % de descuento
             . "ITM|S002|Brushing|1|60000|10\n"                  // sin campo 7: sin descuento
             . "ITM|S003|Manicura exenta|1|45000|0|50000\n";     // exenta, 10 %

        $cruda = (new \Automatizador\TxtParser())->parse($txt)[0];
        $built = (new \Automatizador\InvoiceFactory([]))->build($cruda);
        $items = $built['invoice']['items'];

        $this->assertSame(100000.0, $items[0]['precio_unitario'], 'E721 es el precio de LISTA.');
        $this->assertSame(20000.0, $items[0]['descuento_item'], 'EA002 es lista − neto.');
        $this->assertSame(0.0, $items[1]['descuento_item'], 'Sin campo 7 no hay descuento.');
        $this->assertSame(185000.0, array_sum(array_column($built['invoice']['payments'], 'monto')),
            'El total del documento sale del NETO: 80.000 + 60.000 + 45.000.');

        $payload = (new \App\Service\InvoiceMapper())->buildPayload($built['emitter'], $built['invoice'], []);
        $tot = $payload['totales'];
        $this->assertSame(80000.0, (float) $payload['items'][0]['ea008'], 'EA008 = (E721 − EA002) × cantidad.');
        $this->assertSame(185000.0, (float) $tot['total_neto']);
        $this->assertSame(25000.0, (float) $tot['total_descuento'], 'F009: suma de los EA002.');
        $this->assertSame(25000.0, (float) $tot['descuento_total'], 'F011: particulares + globales.');
        $this->assertSame(140000.0, (float) $tot['subtotal_10']);
        $this->assertSame(45000.0, (float) $tot['subtotal_exenta']);

        // ---- El XML ----
        $xml = (new \App\Service\SifenXmlBuilder())->build(
            ['namespace' => 'http://ekuatia.set.gov.py/sifen/xsd'], $payload,
            str_repeat('0', 44), '123456789');
        $campo = function (string $tag) use ($xml): array {
            preg_match_all('/<' . $tag . '>([^<]*)<\/' . $tag . '>/', $xml, $m);

            return $m[1];
        };
        $this->assertSame(['100000', '60000', '50000'], $campo('dPUniProSer'));
        $this->assertSame(['100000', '60000', '50000'], $campo('dTotBruOpeItem'), 'E727 = E721 × cantidad, ANTES del descuento.');
        $this->assertSame(['20000', '5000'], $campo('dDescItem'), 'Sólo los ítems con descuento lo declaran.');
        $this->assertSame(['20', '10'], $campo('dPorcDesIt'), 'EA003 = EA002 × 100 / E721.');
        $this->assertSame(['80000', '60000', '45000'], $campo('dTotOpeItem'), 'EA008 es el neto, descontado UNA vez.');
        $this->assertSame(['25000'], $campo('dTotDesc'));
        $this->assertSame(['25000'], $campo('dDescTotal'));
        $this->assertSame(['185000'], $campo('dTotGralOpe'), 'El total declarado no cambia.');
        $this->assertSame(['185000'], $campo('dMonTiPag'));

        // ---- El KuDE: la tabla dice lo mismo ----
        $dir = sys_get_temp_dir() . '/kude_' . uniqid();
        $pdf = (string) file_get_contents((new \App\Service\KudeService($dir))->generate([
            'cdc' => str_repeat('0', 44), 'qr_text' => 'https://ekuatia.set.gov.py/consultas/qr?x=1',
            'emisor' => $payload['emisor'], 'cliente' => $payload['cliente'],
            'documento' => $payload['documento'], 'items' => $payload['items'], 'totales' => $tot,
        ]));
        array_map('unlink', glob($dir . '/*') ?: []);
        @rmdir($dir);

        // El KuDE se escribe sin comprimir, así que el texto se puede leer tal cual.
        $this->assertStringContainsString('(DESCUENTO)', $pdf, 'La columna se llama DESCUENTO.');
        $this->assertStringContainsString('(100.000)', $pdf, 'PRECIO UNITARIO es el de lista.');
        $this->assertStringContainsString('(20.000)', $pdf, 'El descuento va en MONTO, no en porcentaje.');
        $this->assertStringContainsString('(80.000)', $pdf, 'Bajo 10% va el neto.');
        $this->assertStringNotContainsString('%DESC', $pdf, 'Ya no hay columna de porcentaje.');
        $this->assertStringNotContainsString('DESCUENTO: 0', $pdf, 'La fila DESCUENTO del pie se fue.');
        $this->assertLessThan(strpos($pdf, '(DESCUENTO)'), strpos($pdf, '(UNITARIO)'),
            'El orden es PRECIO UNITARIO y después DESCUENTO.');
    }

    // -----------------------------------------------------------------
    //  7.118.0 — el enlace del correo, la campanita con el stock, la agenda
    // -----------------------------------------------------------------

    /**
     * El enlace del correo ofrece horarios SIN sesión.
     *
     * Reportado tal cual: *«los links de reagendar por los correos no
     * funcionan»*. La pantalla del enlace se abría —el token la deja pasar—,
     * pero el selector de horarios le pedía los días a `portal.disponibilidad`,
     * que vive detrás del middleware de sesión. La clienta que llega desde el
     * correo **no tiene sesión** —ése es el punto del token—, así que la
     * consulta volvía como una redirección al ingreso, el calendario quedaba
     * vacío y el botón «Reprogramar» nunca se habilitaba. Ni un error en
     * pantalla: la función apagada en silencio de siempre.
     *
     * Se mide como llega la clienta —sin sesión— y en las dos direcciones: el
     * endpoint del token contesta con días, y el del portal sigue exigiendo
     * sesión, que es lo correcto para quien entra por ahí.
     */
    #[Test]
    public function el_enlace_del_correo_ofrece_horarios_sin_sesion(): void
    {
        $cita = $this->citaFuturaAgendada();
        $token = Notificaciones::tokenDeCita((int) $cita->id_cita);

        // La pantalla del enlace apunta al endpoint del token, no al del portal.
        $html = (string) $this->get(route('cita.token', ['t' => $token]))->assertOk()->getContent();
        $this->assertStringContainsString('data-agenda=', $html,
            'La pantalla del enlace dibuja el selector de horarios.');
        $this->assertStringContainsString('mi-cita/disponibilidad', $html,
            'Y le pide los días al endpoint del token: el del portal exige sesión y ella no tiene.');
        $this->assertStringNotContainsString('portal/disponibilidad', $html,
            'Apuntando al del portal, la consulta vuelve como redirección y el calendario queda vacío.');

        // El endpoint contesta sin sesión, con días de verdad.
        $r = $this->getJson(route('cita.disponibilidad', ['t' => $token]))->assertOk()->json();
        $this->assertTrue((bool) ($r['ok'] ?? false), 'Con un token vigente tiene que contestar.');
        $this->assertNotEmpty($r['dias'] ?? [], 'Y ofrecer días: sin eso el botón nunca se habilita.');
        $this->assertGreaterThan(0, (int) ($r['duracion'] ?? 0), 'La duración sale de la cita.');

        // Y las horas de uno de esos días.
        $h = $this->getJson(route('cita.disponibilidad', ['t' => $token, 'fecha' => $r['dias'][0]]))->assertOk()->json();
        $this->assertTrue((bool) ($h['ok'] ?? false));
        $this->assertNotEmpty($h['horas'] ?? [], 'El día ofrecido tiene que tener horas.');

        // Un token inventado no contesta nada de la agenda de nadie.
        $malo = $this->getJson(route('cita.disponibilidad', ['t' => str_repeat('0', 48)]))->assertOk()->json();
        $this->assertFalse((bool) ($malo['ok'] ?? true), 'Sin token válido no hay horarios.');

        // La otra dirección: el del portal SIGUE pidiendo sesión.
        $this->get(route('portal.disponibilidad', ['servicios' => [1]]))->assertRedirect();
    }

    /**
     * El stock que llegó al mínimo suena en la campanita, con los nombres.
     *
     * «Falta stock» era un número en el panel —«3»— que no decía qué falta ni
     * a dónde ir, y sólo se veía desde el inicio; el usuario lo sacó del
     * panel y pidió que fuera a la campanita. Acá va con los nombres y el
     * enlace a la lista de compras, y sólo a quien tiene `inventario.stock`:
     * al Profesional un faltante que no puede reponer le tapa lo suyo.
     */
    #[Test]
    public function la_campanita_avisa_el_stock_que_llego_al_minimo_y_solo_a_quien_repone(): void
    {
        $this->entrarComo('admin', 'admin123');
        $suc = (int) session('id_sucursal');

        // Se garantiza la premisa: un producto de este local por debajo del
        // mínimo, con un nombre que no pueda estar en la pantalla por otro
        // motivo. Y el resto por encima, para que el aviso hable de ÉSTE.
        DB::update('UPDATE producto_sucursal SET stock_minimo = 0.01 WHERE id_sucursal = ?', [$suc]);
        $prod = DB::selectOne('SELECT ps.id_producto FROM producto_sucursal ps
                                 JOIN producto p ON p.id_producto = ps.id_producto
                                WHERE ps.id_sucursal = ? AND p.activo = 1 ORDER BY ps.id_producto LIMIT 1', [$suc]);
        $this->assertNotNull($prod, 'Premisa: este local maneja al menos un producto.');
        DB::update('UPDATE producto SET nombre = ? WHERE id_producto = ?',
            ['Tintura prueba campanita', (int) $prod->id_producto]);
        DB::update('UPDATE producto_sucursal SET stock_minimo = 999999 WHERE id_producto = ? AND id_sucursal = ?',
            [(int) $prod->id_producto, $suc]);

        $stock = array_values(array_filter(Alertas::mias(), fn ($a) => $a['nivel'] === 'STOCK'));
        $this->assertCount(1, $stock, 'El faltante de stock tiene que estar en la campanita, como un solo aviso.');
        $this->assertStringContainsString('Tintura prueba campanita', $stock[0]['que'],
            'Con el NOMBRE: «3 productos» no dice qué comprar.');
        $this->assertSame('inventario.stock', $stock[0]['permiso']);
        $this->assertSame('inventario.stock', $stock[0]['ruta'], 'Y con el enlace a la lista de compras.');
        $this->assertStringStartsWith('stock:' . $suc . ':', $stock[0]['clave'],
            'La clave es estable y del local: mañana, con el mismo faltante, es el mismo aviso.');

        // Y la barra lo dibuja, desde cualquier pantalla: no sólo el panel.
        $html = (string) $this->get(route('citas.agenda'))->assertOk()->getContent();
        $this->assertStringContainsString('Tintura prueba campanita', $html,
            'La campanita tiene que nombrar el producto que falta.');
        $this->assertStringContainsString('Inventario → Stock', $html,
            'Y decir dónde se resuelve.');

        // El panel ya no lo cuenta: se fue a la campanita.
        $this->get(route('panel'))->assertOk()->assertDontSee('Falta stock');

        // A quien no repone, nada.
        DB::delete("DELETE FROM rol_modulo WHERE id_rol = 2 AND modulo IN ('inventario','inventario.stock')");
        DB::update('UPDATE usuario SET id_rol = 2 WHERE id_usuario = 1');
        session(['rol' => 2]);
        Permisos::olvidar();
        $this->assertSame([], array_values(array_filter(Alertas::mias(), fn ($a) => $a['nivel'] === 'STOCK')),
            'El Profesional no repone stock: avisarle es ruido que le tapa lo que sí es suyo.');
    }

    /**
     * «Detalle» abre la cita en una ventana, y las acciones van en dos columnas.
     *
     * Pedido del usuario sobre la agenda: sacar el botón «Vienen 2 · alergias»
     * y que «Detalle» abra una ventana emergente con la información mejor
     * estructurada, y los botones de acción en dos columnas porque en una
     * fila se confundían. La fila se queda con lo que ADVIERTE —el estado, la
     * seña, las alergias— y todo lo demás va a la ventana: quién viene, qué
     * se pidió, lo que dejó dicho, lo cobrado.
     *
     * Se mide el andamiaje, que es lo que se rompe sin dar error: el botón
     * apunta a la ventana, la ventana existe fuera de la tabla, y trae lo que
     * la fila dejó de mostrar.
     */
    #[Test]
    public function la_agenda_abre_el_detalle_de_la_cita_en_una_ventana_y_las_acciones_en_dos_columnas(): void
    {
        $this->entrarComo('admin', 'admin123');
        $cita = $this->citaFuturaAgendada((int) session('id_sucursal'));

        // Lo que la ventana tiene que mostrar: una acompañante con su alergia
        // y lo que la clienta dejó dicho. Con la cita de dos, la fila nombra a
        // quién es cada alergia y la ventana lista a las dos.
        DB::update('UPDATE cita SET personas = 2, observaciones = ? WHERE id_cita = ?',
            ['Dejo dicho prueba ventana', (int) $cita->id_cita]);
        DB::insert('INSERT INTO cita_acompanante (id_cita, orden, nombre, apellido, alergias) VALUES (?, 2, ?, ?, ?)',
            [(int) $cita->id_cita, 'Josefina', 'Villalba Prueba', 'Amoniaco prueba']);

        $html = (string) $this->get(route('citas.agenda', ['dia' => $cita->dia]))->assertOk()->getContent();
        $id = (int) $cita->id_cita;

        // La fila: el botón «Detalle» apunta a la ventana, y las acciones van
        // en la grilla de dos columnas.
        $ini = strpos($html, '#detCita' . $id . '"');
        $this->assertNotFalse($ini, 'La fila tiene que ofrecer «Detalle» apuntando a la ventana de ESA cita.');
        $fila = substr($html, (int) $ini, (int) strpos($html, '</tr>', (int) $ini) - (int) $ini);
        // El botón entero: el `title` va antes del `data-bs-target`.
        $boton = substr($html, max(0, (int) $ini - 900), 900);
        $this->assertStringContainsString('title="Detalle"', $boton, 'El botón se llama «Detalle».');
        $this->assertStringContainsString('sgp-acciones', $boton,
            'Las acciones van en la grilla de dos columnas, no en una fila.');
        $this->assertStringNotContainsString('Vienen 2', $fila, 'El botón «Vienen 2 · alergias» se fue de la fila.');
        $this->assertStringNotContainsString('detAge' . $id, $html, 'El desplegable de la fila ya no existe: lo reemplaza la ventana.');

        // La ventana: existe, fuera de la tabla, y trae lo que la fila ya no muestra.
        $v = strpos($html, 'id="detCita' . $id . '"');
        $this->assertNotFalse($v, 'La ventana de la cita tiene que dibujarse.');
        $this->assertGreaterThan((int) strpos($html, '</table>'), $v,
            'Y fuera de la tabla: dentro de un <tr> hereda cualquier display:none y no se puede mostrar.');
        $finV = (int) strpos($html, 'modal fade', $v + 10) ?: strlen($html);
        $ventana = substr($html, $v, $finV - $v);
        foreach (['Dejo dicho prueba ventana', 'Josefina', 'Amoniaco prueba', 'La cita', 'Quién viene', 'Dejó dicho'] as $t) {
            $this->assertStringContainsString($t, $ventana, "La ventana tiene que decir «{$t}».");
        }
    }

    /**
     * El inicio del portal tiene la forma del panel: sus citas, su nivel, las pantallas.
     *
     * Pedido del usuario (7.118.1): *«adaptá el mismo panel actual para el
     * portal de los clientes»*. Antes mostraba UNA cita en una tarjeta y
     * cinco tarjetas apiladas. Se mide con una clienta con dos citas por
     * venir: las dos se listan —no sólo la primera—, el nivel y los puntos
     * salen de la misma fuente que Promociones, y las pantallas del portal
     * salen del catálogo, sin Inicio —que es ésta— ni Mi cuenta.
     */
    #[Test]
    public function el_inicio_del_portal_tiene_la_forma_del_panel(): void
    {
        $u = DB::selectOne(
            'SELECT u.id_usuario, c.id_cliente FROM usuario u
               JOIN cliente c ON c.id_persona = u.id_persona
              WHERE u.activo = 1 AND c.activo = 1 LIMIT 1'
        );
        $this->assertNotNull($u, 'Premisa: hace falta una cuenta de clienta.');
        $idc = (int) $u->id_cliente;

        // Dos citas por venir A SU NOMBRE. `citaFuturaAgendada()` elige una
        // clienta libre; se le cambia la dueña, que la fila ya está agendada y
        // el disparador del servicio repetido mira al insertar el servicio.
        $c1 = $this->citaFuturaAgendada();
        $c2 = $this->citaFuturaAgendada();
        DB::update('UPDATE cita SET id_cliente = ? WHERE id_cita IN (?, ?)', [$idc, (int) $c1->id_cita, (int) $c2->id_cita]);
        // Sin ninguna otra, para que «las dos» sea exactamente lo que se ve.
        DB::update('UPDATE cita SET id_estado_cita = 3 WHERE id_cliente = ? AND id_cita NOT IN (?, ?)
                      AND id_estado_cita IN (1, 2, 7)', [$idc, (int) $c1->id_cita, (int) $c2->id_cita]);

        session([
            'uid' => (int) $u->id_usuario, 'rol' => (int) config('permisos.rol_cliente', 4),
            'es_personal' => false, 'es_cliente' => true, 'id_cliente' => $idc,
        ]); $this->conSucursal();

        $html = (string) $this->get(route('portal.index'))->assertOk()->getContent();

        $this->assertStringContainsString('sgp-saludo', $html, 'El saludo es un título chico, como en el panel.');
        $this->assertStringContainsString('Tus próximas citas', $html);
        $this->assertStringContainsString('id="citaProxima' . (int) $c1->id_cita . '"', $html, 'La primera cita se lista.');
        $this->assertStringContainsString('id="citaProxima' . (int) $c2->id_cita . '"', $html,
            'Y la segunda: antes el inicio mostraba UNA sola.');
        $this->assertStringContainsString('ver todas mis citas', $html);

        $this->assertStringContainsString('Tu nivel y tus puntos', $html);
        $fid = DB::selectOne('SELECT visitas, puntos FROM vw_cliente_fidelizacion WHERE id_cliente = ?', [$idc]);
        $this->assertStringContainsString(number_format((int) ($fid->puntos ?? 0), 0, ',', '.'), $html,
            'Los puntos son los mismos que en Promociones.');

        // Las pastillas: las pantallas del portal, sin Inicio ni Mi cuenta.
        $this->assertStringContainsString('sgp-modulos', $html, 'Las pantallas van en la grilla del panel.');
        $ini = (int) strpos($html, 'class="sgp-modulos');
        $grilla = substr($html, $ini, (int) strpos($html, '</div>', $ini + 20) - $ini);
        foreach (['Reservar cita', 'Mis citas', 'Promociones', 'Valoraciones', 'Mi ficha', 'Mis recordatorios'] as $t) {
            $this->assertStringContainsString($t, $grilla, "La grilla ofrece «{$t}».");
        }
        $this->assertStringNotContainsString('>Inicio<', $grilla, 'Inicio es esta pantalla: no se ofrece a sí misma.');
        $this->assertStringNotContainsString('Mi cuenta', $grilla, 'Mi cuenta vive en el desplegable de la cuenta.');
    }

    // -----------------------------------------------------------------
    //  El enlace del correo mide la agenda IGUAL que el módulo
    // -----------------------------------------------------------------

    /**
     * Lo que ofrece el enlace del correo es lo que acepta, y con el local de
     * LA CITA.
     *
     * Se pidió asegurar «que las fechas y horas del reagendado desde el link
     * funcionen de la misma manera que el reagendado desde el módulo de
     * Agendamiento», y ahí había una diferencia de verdad: el guardado
     * comprobaba el hueco **sin decirle la sucursal**, así que `huecoLibre()`
     * caía en `Sucursales::activa()` — la sesión de quien tuviera el navegador
     * abierto—. El turno es del local desde la 7.39.0, de modo que alguien del
     * salón parado en otro local que abriera el enlace de una clienta hacía
     * que la comprobación corriera contra los turnos de la sede equivocada: el
     * enlace rechazaba justo el horario que su propio calendario acababa de
     * ofrecer, y sin decir por qué.
     *
     * Se mide en las dos direcciones que importan: los dos endpoints
     * devuelven las MISMAS horas, y la reprogramación entra **con una sucursal
     * ajena puesta en la sesión** —que es lo que fallaba—.
     */
    #[Test]
    public function el_enlace_del_correo_mide_la_agenda_igual_que_el_modulo(): void
    {
        $cita = $this->citaFuturaAgendada();
        $codigo = str_repeat('ab12cd34', 6);   // 48 hexadecimales, como el real
        DB::insert('INSERT INTO token_cita (id_cita, codigo, expira_en, usado)
                    VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), 0)', [(int) $cita->id_cita, $codigo]);

        $srv = array_map(fn ($r) => (int) $r->id_servicio,
            DB::select('SELECT id_servicio FROM cita_servicio WHERE id_cita = ?', [(int) $cita->id_cita]));

        // 1) Los días que ofrece el enlace, sin ninguna sesión.
        $porToken = $this->getJson(route('cita.disponibilidad', ['t' => $codigo]))->assertOk()->json();
        $this->assertTrue((bool) $porToken['ok'], 'El enlace tiene que poder consultar la agenda sin sesión.');
        $this->assertNotEmpty($porToken['dias'] ?? [], 'La premisa: tiene que quedar algún día con lugar.');
        $dia = (string) $porToken['dias'][0];

        // 2) Las horas de ese día, por el enlace y por el módulo.
        $horasToken = $this->getJson(route('cita.disponibilidad', ['t' => $codigo, 'fecha' => $dia]))
            ->assertOk()->json('horas');

        $this->entrarComo('admin', 'admin123');
        $horasModulo = $this->getJson(route('citas.disponibilidad', [
            'servicios' => $srv,
            'id_usuario' => (int) $cita->id_usuario,
            'sucursal' => (int) $cita->id_sucursal,
            'fecha' => $dia,
        ]))->assertOk()->json('horas');

        $soloHora = fn (array $hs) => array_map(fn ($h) => is_array($h) ? $h['hora'] : $h, $hs);
        $this->assertSame($soloHora($horasModulo ?: []), $soloHora($horasToken ?: []),
            'El enlace del correo y el módulo tienen que ofrecer exactamente las mismas horas.');
        $this->assertNotEmpty($horasToken, 'La premisa: ese día tiene que tener alguna hora.');

        // 3) Y lo que se ofreció, se acepta — **con OTRO local en la sesión**,
        //    que es el caso que rechazaba.
        //
        //    El local tiene que existir de verdad: `fn_verificar_disponibilidad`
        //    con una sucursal inventada se queda sin turnos que mirar y cae en
        //    el criterio permisivo, así que con un id al azar el defecto no se
        //    ve. Con una sucursal real donde esa persona no tiene turno, la
        //    función contesta que no — que es exactamente lo que pasaba cuando
        //    la comprobación se hacía contra el local de la sesión.
        $otroLocal = $this->otraSucursal();
        // Ese local **usa turnos** —hay uno cargado, de otra persona— pero el
        // profesional de la cita no tiene ninguno ahí. Sin esta parte el local
        // nuevo cae en el criterio permisivo del primer día (7.39.0) y el
        // defecto no se vería.
        DB::insert("INSERT INTO turno_laboral (id_sucursal, nombre, hora_inicio, hora_fin)
                    VALUES (?, 'Turno del otro local', '08:00:00', '18:00:00')", [$otroLocal]);
        $idTurno = (int) DB::scalar('SELECT LAST_INSERT_ID()');
        for ($d = 1; $d <= 7; $d++) {
            DB::insert('INSERT INTO turno_dia (id_turno, dia_semana) VALUES (?, ?)', [$idTurno, $d]);
        }
        $ajeno = (int) DB::scalar(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.activo = 1 AND r.es_personal = 1 AND u.id_usuario <> ?
              ORDER BY u.id_usuario LIMIT 1', [(int) $cita->id_usuario]);
        DB::insert('INSERT INTO usuario_turno (id_usuario, id_turno) VALUES (?, ?)', [$ajeno, $idTurno]);

        $hora = is_array($horasToken[0]) ? $horasToken[0]['hora'] : $horasToken[0];
        $nueva = $dia . ' ' . $hora . ':00';
        $this->assertFalse(
            Agenda::huecoLibre((int) $cita->id_usuario, $nueva, (int) $cita->dur, (int) $cita->id_cita, $otroLocal),
            'La premisa: en el otro local esa persona no tiene turno, así que ahí el horario NO está libre.'
        );
        session(['id_sucursal' => $otroLocal, 'sucursal_nom' => 'Otro local']);

        $this->post(route('cita.token.guardar'), ['t' => $codigo, 'fecha_hora' => $nueva])
            ->assertRedirect();

        $quedo = (string) DB::scalar('SELECT fecha_hora FROM cita WHERE id_cita = ?', [(int) $cita->id_cita]);
        $this->assertSame($nueva, $quedo,
            'La cita tiene que quedar en el horario que el propio enlace ofreció, '
            . 'aunque la sesión esté parada en otro local.');
    }

    /**
     * Desde el enlace del correo no se le puede cambiar el profesional.
     *
     * La pantalla dejó de ofrecer el combo en la 7.97.0 —los horarios que
     * muestra el selector se calculan para quien te atiende, así que cambiarlo
     * ahí los invalidaría— y **el servidor lo seguía aceptando**: con el token
     * en la mano se le reasignaba la cita a cualquier profesional activo.
     * Esconder el campo no es el control.
     */
    #[Test]
    public function el_enlace_del_correo_no_cambia_el_profesional(): void
    {
        $cita = $this->citaFuturaAgendada();
        $codigo = str_repeat('ff00aa55', 6);
        DB::insert('INSERT INTO token_cita (id_cita, codigo, expira_en, usado)
                    VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), 0)', [(int) $cita->id_cita, $codigo]);

        $otro = (int) DB::scalar(
            'SELECT u.id_usuario FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.activo = 1 AND r.es_personal = 1 AND u.id_usuario <> ?
              ORDER BY u.id_usuario LIMIT 1', [(int) $cita->id_usuario]
        );
        $this->assertNotSame(0, $otro, 'La premisa: hace falta otro profesional.');

        $porToken = $this->getJson(route('cita.disponibilidad', ['t' => $codigo]))->assertOk()->json();
        $this->assertNotEmpty($porToken['dias'] ?? [], 'La premisa: tiene que quedar algún día con lugar.');
        $dia = (string) $porToken['dias'][0];
        $horas = $this->getJson(route('cita.disponibilidad', ['t' => $codigo, 'fecha' => $dia]))
            ->assertOk()->json('horas');
        $this->assertNotEmpty($horas, 'La premisa: ese día tiene que tener alguna hora.');
        $hora = is_array($horas[0]) ? $horas[0]['hora'] : $horas[0];

        $this->post(route('cita.token.guardar'), [
            't' => $codigo,
            'fecha_hora' => $dia . ' ' . $hora . ':00',
            'id_usuario' => $otro,          // el POST forjado
        ])->assertRedirect();

        $this->assertSame((int) $cita->id_usuario,
            (int) DB::scalar('SELECT id_usuario FROM cita WHERE id_cita = ?', [(int) $cita->id_cita]),
            'La cita sigue con su profesional: el enlace no lo pregunta, así que tampoco lo acepta.');
    }

    // -----------------------------------------------------------------
    //  La agenda: lo que TRABA la cita se lee antes que la ficha
    // -----------------------------------------------------------------

    /**
     * En la fila, el aviso de lo que impide atender va ANTES que «Detalle».
     *
     * Se reportó al revés de como tiene que leerse: *«el botón de DETALLE se
     * pone encima de FALTA fichaje siendo que debe ser al revés»*. Primero qué
     * impide atender —que es lo accionable ahora mismo— y después la ficha,
     * que es información. Es la regla que este proyecto ya tiene escrita para
     * la ayuda contextual, aplicada al orden de la fila.
     */
    #[Test]
    public function en_la_agenda_lo_que_traba_la_cita_va_antes_que_el_detalle(): void
    {
        $this->entrarComo('admin', 'admin123');
        $suc = (int) session('id_sucursal');

        // Una cita de HOY, con su profesional sin fichar: es cuando el aviso
        // aparece. Se arma desde una futura y se la trae, que `slots()` de hoy
        // puede no tener ningún hueco a esta altura del día.
        $cita = $this->citaFuturaAgendada($suc);
        $hoy = date('Y-m-d') . ' ' . substr((string) $cita->fecha_hora, 11);
        DB::update('UPDATE cita SET fecha_hora = ? WHERE id_cita = ?', [$hoy, (int) $cita->id_cita]);
        DB::delete('DELETE FROM asistencia WHERE id_usuario = ? AND fecha = CURDATE()', [(int) $cita->id_usuario]);

        $html = (string) $this->get(route('citas.agenda', ['dia' => date('Y-m-d')]))->assertOk()->getContent();

        // **La posición, que es lo que se reportó.** El aviso puede ser
        // «falta fichaje» o «profesional ausente» según cómo esté la planilla
        // del día —la agenda marca sola las entradas vencidas al dibujarse—,
        // y los dos son lo mismo para esto: lo que impide atender. Lo que se
        // mide es que venga ANTES del botón de la ficha.
        $detalle = strpos($html, 'detCita' . (int) $cita->id_cita);
        $this->assertNotFalse($detalle, 'La fila de esa cita tiene que estar en la agenda.');
        $grilla = strrpos(substr($html, 0, $detalle), 'sgp-acciones');
        $this->assertNotFalse($grilla, 'La fila tiene que dibujar su grilla de acciones.');

        $aviso = false;
        foreach (['Primero hay que marcar la entrada', 'Ya está marcado como ausente hoy'] as $t) {
            $p = strpos($html, $t, (int) $grilla);
            if ($p !== false) {
                $aviso = $p;
                break;
            }
        }
        $this->assertNotFalse($aviso,
            'Una cita de hoy sin la entrada marcada tiene que decir por qué no se puede atender.');
        $this->assertLessThan($detalle, $aviso,
            'El aviso de lo que traba la cita va ARRIBA del botón «Detalle», no debajo: '
            . 'primero lo accionable, después la ficha.');
    }

    // -----------------------------------------------------------------
    //  La liquidación dice qué trabajo se está pagando
    // -----------------------------------------------------------------

    /**
     * El detalle de una liquidación abre los servicios que se pagaron.
     *
     * Decía el período y el estado, o sea nada que la fila no dijera ya: un
     * monto sin su desglose no se puede comprobar ni defender, y quien revisa
     * la planilla tres meses después no tiene de dónde agarrarse. Se pidió
     * «un botón de detalle que despliegue los servicios realizados, a quiénes,
     * con qué número de factura está ligado cada servicio, y demás información
     * útil del trabajo de ese profesional para ese pago».
     *
     * Se mide sobre una liquidación de verdad —la que arma `sp_pagar_personal`,
     * con su `detalle_pago_personal`— y no sobre datos inventados.
     */
    #[Test]
    public function la_liquidacion_dice_que_trabajo_se_esta_pagando(): void
    {
        $this->entrarComo('admin', 'admin123');
        $suc = (int) session('id_sucursal');

        // Un servicio realizado sin liquidar, con su renglón de factura: es lo
        // que hace que la columna del comprobante signifique algo.
        $sr = DB::selectOne(
            "SELECT sr.id_servicio_realizado, sr.id_usuario, s.nombre AS servicio,
                    fn_factura_nro(f.id_factura) AS nro,
                    CONCAT(pe.nombre,' ',pe.apellido) AS cliente
               FROM servicio_realizado sr
               JOIN servicio s ON s.id_servicio = sr.id_servicio
               JOIN cita c ON c.id_cita = sr.id_cita
               JOIN cliente cl ON cl.id_cliente = c.id_cliente
               JOIN persona pe ON pe.id_persona = cl.id_persona
               JOIN detalle_factura df ON df.id_detalle_factura = sr.id_detalle_factura
               JOIN factura f ON f.id_factura = df.id_factura
               LEFT JOIN detalle_pago_personal d ON d.id_servicio_realizado = sr.id_servicio_realizado
              WHERE d.id_detalle_pago IS NULL
              LIMIT 1"
        );
        $this->assertNotNull($sr, 'La premisa: hace falta un servicio realizado, facturado y sin liquidar.');

        // Los demás pendientes de esa persona se sacan del medio, para que la
        // liquidación sea exactamente ese servicio y el total se pueda leer.
        DB::insert('INSERT INTO pago_personal (id_usuario, id_usuario_registro, id_estado_pago, fecha, periodo)
                    VALUES (?, ?, 1, NOW(), ?)', [(int) $sr->id_usuario, (int) session('uid'), 'prueba']);
        $idPago = (int) DB::scalar('SELECT LAST_INSERT_ID()');
        DB::insert('INSERT INTO detalle_pago_personal (id_pago_personal, id_servicio_realizado, monto)
                    VALUES (?, ?, fn_comision_servicio(?))',
                   [$idPago, (int) $sr->id_servicio_realizado, (int) $sr->id_servicio_realizado]);

        $html = (string) $this->get(route('facturacion.pagos'))->assertOk()->getContent();

        $ini = strpos($html, 'id="detLiq' . $idPago . '"');
        $this->assertNotFalse($ini, 'La liquidación tiene que traer su bloque de detalle.');
        $det = substr($html, $ini, 6000);

        $this->assertStringContainsString($sr->servicio, $det, 'El detalle dice QUÉ servicio se está pagando.');
        $this->assertStringContainsString($sr->cliente, $det, 'Y a quién se le hizo.');
        $this->assertStringContainsString((string) $sr->nro, $det,
            'Y con qué comprobante está ligado ese servicio, que es lo que pidió el usuario.');
        $this->assertStringContainsString('Total liquidado', $det);
    }

    // -----------------------------------------------------------------
    //  7.121.0 — La cuenta bancaria es una caja dedicada al banco
    // -----------------------------------------------------------------

    /**
     * Una cuenta bancaria del local, para las pruebas de la caja del banco.
     * Con el saldo declarado hace un minuto, para que lo que entre y salga
     * en la prueba cuente (`fn_cuenta_saldo` suma desde esa fecha).
     */
    private function cuentaDePrueba(int $suc, float $declarado, int $paraSenas = 1, string $entidad = 'Banco de la prueba'): int
    {
        $banco = (int) DB::scalar("SELECT id_metodo_pago FROM metodo_pago WHERE tipo = 'BANCO' AND activo = 1 LIMIT 1");
        $this->assertNotSame(0, $banco, 'La premisa: hace falta un medio de pago bancario.');

        DB::insert('INSERT INTO cuenta_bancaria
                    (id_sucursal, id_metodo_pago, entidad, titular, numero_cuenta, orden, activo, para_senas,
                     saldo_declarado, saldo_declarado_en)
                    VALUES (?, ?, ?, ?, ?, 99, 1, ?, ?, DATE_SUB(NOW(), INTERVAL 1 MINUTE))',
            [$suc, $banco, $entidad, 'Salón de prueba', 'CTA-' . random_int(100000, 999999), $paraSenas, $declarado]);

        return (int) DB::scalar('SELECT LAST_INSERT_ID()');
    }

    /**
     * El cobro mixto manda el efectivo al cajón y la transferencia a la cuenta.
     *
     * Es lo que pidió el usuario (7.121.0): *«al cobrar, si se coloca efectivo
     * se despliegue un combo para elegir la caja, y si se coloca algún tipo de
     * movimiento bancario desplegar un combo correspondiente»*, y que **todo
     * movimiento bancario se registre en la cuenta y le sume**. Hasta acá la
     * cuenta era un piso —el sistema veía lo que salía del banco, no lo que
     * entraba— y una seña por transferencia no se sumaba a ningún lado.
     *
     * Se mide en las dos direcciones sobre el MISMO cobro: la línea en
     * efectivo sube el saldo del cajón y no el de la cuenta; la línea por
     * banco sube el de la cuenta y no el del cajón. Con `Facturacion::
     * anotarCuenta()` sacada, el cobro queda sin `id_cuenta` y la cuenta no
     * suma: falla.
     */
    #[Test]
    public function el_cobro_mixto_manda_el_efectivo_al_cajon_y_la_transferencia_a_la_cuenta(): void
    {
        $this->entrarComo('admin', 'admin123');
        $suc = (int) session('id_sucursal');
        $cajon = $this->cajonDe($suc);
        if (! DB::scalar('SELECT COUNT(*) FROM caja WHERE id_caja_fisica = ? AND id_estado_caja = 1', [$cajon])) {
            DB::insert('INSERT INTO caja (id_usuario, id_sucursal, id_caja_fisica, id_estado_caja, monto_inicial)
                        VALUES (1, ?, ?, 1, 0)', [$suc, $cajon]);
        }
        Caja::olvidar();
        $caja = (int) DB::scalar('SELECT id_caja FROM caja WHERE id_caja_fisica = ? AND id_estado_caja = 1', [$cajon]);
        $cuenta = $this->cuentaDePrueba($suc, 500000);

        $cita = $this->citaFuturaAgendada($suc);
        $this->assertGreaterThanOrEqual(2000, (float) DB::scalar('SELECT fn_cita_total(?)', [$cita->id_cita]),
            'La premisa: la cita tiene que valer al menos Gs. 2.000 para partir el cobro en dos.');

        $efectivo = (int) DB::scalar("SELECT MIN(id_metodo_pago) FROM metodo_pago WHERE activo = 1 AND tipo = 'EFECTIVO'");
        $banco = (int) DB::scalar("SELECT MIN(id_metodo_pago) FROM metodo_pago WHERE activo = 1 AND tipo = 'BANCO'");

        $cajaAntes = Caja::saldo($caja);
        $cuentaAntes = (float) DB::scalar('SELECT fn_cuenta_saldo(?)', [$cuenta]);

        // Mil en efectivo y mil por transferencia, en el mismo cobro. La
        // cuenta viaja por línea y por posición, como `metodo[]`.
        $this->post(route('facturacion.sena'), [
            'id_cita' => $cita->id_cita, 'dia' => $cita->dia,
            'metodo' => [$efectivo, $banco],
            'monto' => ['1.000', '1.000'],
            'cuenta' => [0, $cuenta],
            'referencia' => ['', 'TRF-' . random_int(1000, 9999)],
            'id_caja' => $caja,
        ])->assertRedirect();

        $cobros = DB::select(
            'SELECT co.id_cobro, mp.tipo, co.id_caja, co.id_cuenta FROM cobro co
               JOIN metodo_pago mp ON mp.id_metodo_pago = co.id_metodo_pago
              WHERE co.id_cita = ? AND co.id_estado_cobro = 1 ORDER BY co.id_cobro', [$cita->id_cita]);
        $this->assertCount(2, $cobros, 'Son dos líneas, así que dos cobros: uno por medio.');

        $porTipo = array_column($cobros, null, 'tipo');
        $this->assertNull($porTipo['EFECTIVO']->id_cuenta,
            'El efectivo no va a ninguna cuenta bancaria: guardarle una diría algo falso.');
        $this->assertSame($caja, (int) $porTipo['EFECTIVO']->id_caja,
            'El efectivo entra al cajón elegido.');
        $this->assertSame($cuenta, (int) $porTipo['BANCO']->id_cuenta,
            'La transferencia tiene que decir a qué cuenta del salón cayó: es lo que hace que la cuenta sume.');

        $this->assertSame(round($cajaAntes + 1000, 2), round(Caja::saldo($caja), 2),
            'El cajón sube SÓLO por la línea en efectivo: la transferencia no está en el cajón.');
        $this->assertSame(round($cuentaAntes + 1000, 2),
            round((float) DB::scalar('SELECT fn_cuenta_saldo(?)', [$cuenta]), 2),
            'La cuenta sube SÓLO por la transferencia: es la seña que hasta acá no se sumaba a ningún lado.');

        // Y el movimiento se lista en la cuenta, no en el cajón: la plata está
        // en el banco aunque se haya registrado en el puesto de una caja.
        $enCuenta = array_column(\App\Servicios\Movimientos::delDia(null, $cuenta), 'id_ref');
        $enCaja = array_column(\App\Servicios\Movimientos::delDia($cajon), 'id_ref');
        $this->assertContains((int) $porTipo['BANCO']->id_cobro, array_map('intval', $enCuenta),
            'Lo que entró por transferencia se lista en los movimientos de la cuenta.');
        $this->assertNotContains((int) $porTipo['BANCO']->id_cobro, array_map('intval', $enCaja),
            'Y NO en los del cajón: ahí leerlo haría creer que esa plata está en el cajón.');
        $this->assertContains((int) $porTipo['EFECTIVO']->id_cobro, array_map('intval', $enCaja),
            'El efectivo sí se lista en el cajón.');
    }

    /**
     * La liquidación por banco sale de la cuenta y no necesita caja abierta;
     * en efectivo, con la caja cerrada, se rechaza.
     *
     * Reportado tal cual (7.121.0): *«la caja se reinicia al cerrar y abrir,
     * y el pago no siempre puede salir de caja»*. El sueldo del mes no está
     * en el cajón de hoy: sale del banco, y la caja del banco es la cuenta.
     * `exigeCaja` se acota al efectivo — «sin caja abierta no se mueve un
     * guaraní EN EFECTIVO»— y por eso se miden las dos mitades: por banco
     * entra con la caja cerrada y descuenta de la cuenta; en efectivo, con
     * la caja cerrada, sigue rechazándose y manda a abrirla.
     */
    #[Test]
    public function la_liquidacion_por_banco_sale_de_la_cuenta_y_no_necesita_caja_abierta(): void
    {
        $this->entrarComo('admin', 'admin123');
        $suc = (int) session('id_sucursal');

        DB::update('UPDATE caja SET id_estado_caja = 2, fecha_cierre = NOW()
                     WHERE id_estado_caja = 1 AND id_sucursal = ?', [$suc]);
        Caja::olvidar();
        $this->assertNull(Caja::abierta(), 'La premisa: la caja de este local tiene que estar cerrada.');

        $cuenta = $this->cuentaDePrueba($suc, 10000000);
        $banco = (int) DB::scalar("SELECT MIN(id_metodo_pago) FROM metodo_pago WHERE activo = 1 AND tipo = 'BANCO'");
        $efectivo = (int) DB::scalar("SELECT MIN(id_metodo_pago) FROM metodo_pago WHERE activo = 1 AND tipo = 'EFECTIVO'");

        $prof = (int) DB::scalar(
            'SELECT sr.id_usuario FROM servicio_realizado sr
               LEFT JOIN detalle_pago_personal d ON d.id_servicio_realizado = sr.id_servicio_realizado
              WHERE d.id_detalle_pago IS NULL
              GROUP BY sr.id_usuario
             HAVING SUM(fn_comision_servicio(sr.id_servicio_realizado)) > 0 LIMIT 1'
        );
        if (! $prof) {
            $this->markTestSkipped('Hace falta alguien con comisión sin liquidar.');
        }
        $monto = (float) DB::scalar(
            'SELECT COALESCE(SUM(fn_comision_servicio(sr.id_servicio_realizado)), 0)
               FROM servicio_realizado sr
               LEFT JOIN detalle_pago_personal d ON d.id_servicio_realizado = sr.id_servicio_realizado
              WHERE sr.id_usuario = ? AND d.id_detalle_pago IS NULL', [$prof]
        );
        $vigentes = fn () => (int) DB::scalar(
            'SELECT COUNT(*) FROM pago_personal WHERE id_usuario = ? AND id_estado_pago = 1', [$prof]);
        $antes = $vigentes();

        // 1) Por banco, con la caja cerrada: entra, y sale de la cuenta.
        $this->post(route('facturacion.pagar_personal'), [
            'id_usuario' => $prof, 'periodo' => date('m/Y'),
            'id_metodo_pago' => $banco, 'id_cuenta' => $cuenta,
        ])->assertRedirect(route('facturacion.pagos'));

        $this->assertSame($antes + 1, $vigentes(),
            'La liquidación por transferencia tiene que registrarse con la caja cerrada: no sale del cajón.');
        $pago = DB::selectOne('SELECT id_pago_personal, id_caja, id_cuenta FROM pago_personal
                                WHERE id_usuario = ? ORDER BY id_pago_personal DESC LIMIT 1', [$prof]);
        $this->assertSame($cuenta, (int) $pago->id_cuenta, 'Tiene que decir de qué cuenta salió.');
        $this->assertNull($pago->id_caja, 'Y de ningún cajón: la plata no estaba ahí.');
        $this->assertSame(round(10000000 - $monto, 2),
            round((float) DB::scalar('SELECT fn_cuenta_saldo(?)', [$cuenta]), 2),
            'La cuenta descuenta la liquidación.');

        // 2) Se revierte para volver a tener qué liquidar, y en EFECTIVO con la
        //    caja cerrada se rechaza: esa plata sí sale del cajón.
        Bd::procedimiento('sp_revertir_pago_personal', [(int) $pago->id_pago_personal, 1]);
        $this->assertSame($antes, $vigentes(), 'La premisa: la reversión dejó los servicios pendientes otra vez.');

        $this->post(route('facturacion.pagar_personal'), [
            'id_usuario' => $prof, 'periodo' => date('m/Y'), 'id_metodo_pago' => $efectivo,
        ])->assertRedirect(route('facturacion.cajas'));
        $this->assertSame($antes, $vigentes(),
            'En efectivo con la caja cerrada no se liquida: quedaría fuera del arqueo.');
        $avisos = implode(' ', array_column(session('sgp_flash', []), 'msg'));
        $this->assertStringContainsString('Abrí la caja', $avisos,
            'Y el aviso dice qué hacer, no «no se puede».');
    }

    /**
     * La clienta ve SÓLO la cuenta marcada «Usar para señas», y se elige desde
     * Cuenta bancaria.
     *
     * Pedido del usuario (7.121.0): en Cuenta bancaria, un botón para elegir la
     * cuenta de las señas; el módulo Datos de pago se elimina. Hasta acá se le
     * mostraban todas las activas del local, y una cuenta puede existir para
     * pagarle a proveedores sin ser a la que el salón quiere que le
     * transfieran. Se mide con dos cuentas en el mismo local: aparece la
     * marcada y no la otra; el interruptor la marca y la desmarca; y una dada
     * de baja no se puede marcar.
     */
    #[Test]
    public function la_clienta_ve_solo_la_cuenta_marcada_para_senas(): void
    {
        $suc = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');
        $paraSenas = $this->cuentaDePrueba($suc, 0, 1, 'Banco para señas');
        $proveedores = $this->cuentaDePrueba($suc, 0, 0, 'Banco de proveedores');

        $veLaClienta = fn () => array_map(fn ($c) => $c->entidad, Cuenta::paraSenas([$suc])[$suc] ?? []);

        $this->assertContains('Banco para señas', $veLaClienta());
        $this->assertNotContains('Banco de proveedores', $veLaClienta(),
            'La cuenta que no se marcó para señas no se le muestra a la clienta: transferiría a donde el salón no espera la seña.');

        // El interruptor, por el mismo camino que la pantalla.
        $this->entrarComo('admin', 'admin123');
        $this->post(route('facturacion.cuentas.senas'), ['id_cuenta' => $proveedores])->assertRedirect();
        $this->assertContains('Banco de proveedores', $veLaClienta(),
            '«Usar para señas» tiene que marcarla: puede haber varias, el banco y la billetera.');

        $this->post(route('facturacion.cuentas.senas'), ['id_cuenta' => $proveedores])->assertRedirect();
        $this->assertNotContains('Banco de proveedores', $veLaClienta(), 'Y volver a apretarlo la desmarca.');

        // Una dada de baja no se ofrece aunque se la marque: no existe para cobrar.
        DB::update('UPDATE cuenta_bancaria SET activo = 0 WHERE id_cuenta = ?', [$proveedores]);
        $this->post(route('facturacion.cuentas.senas'), ['id_cuenta' => $proveedores])->assertRedirect();
        $this->assertSame(0, (int) DB::scalar('SELECT para_senas FROM cuenta_bancaria WHERE id_cuenta = ?', [$proveedores]),
            'Una cuenta dada de baja no se marca para señas: la clienta transferiría a una cuenta que el salón dejó de usar.');
    }

    /**
     * Un movimiento manual desde la cuenta descuenta el banco y no el cajón, y
     * no necesita caja abierta.
     *
     * «Movimiento de caja» pasa a ser sólo «Movimiento» (7.121.0): el gasto
     * que se pagó por transferencia se carga contra la cuenta, con su
     * comprobante como cualquier gasto, y el saldo del cajón no se mueve.
     * `chk_mc_donde` es lo que impide que un movimiento diga que salió de los
     * dos lados —o de ninguno—. Y las clases del cajón —el faltante, la
     * devolución— no entran contra una cuenta.
     */
    #[Test]
    public function el_movimiento_manual_desde_la_cuenta_descuenta_el_banco_y_no_el_cajon(): void
    {
        $this->entrarComo('admin', 'admin123');
        $suc = (int) session('id_sucursal');

        DB::update('UPDATE caja SET id_estado_caja = 2, fecha_cierre = NOW()
                     WHERE id_estado_caja = 1 AND id_sucursal = ?', [$suc]);
        Caja::olvidar();
        $cuenta = $this->cuentaDePrueba($suc, 300000);

        $gasto = (int) DB::scalar("SELECT id_tipo_mov_caja FROM tipo_movimiento_caja
                                    WHERE exige_documento = 1 AND signo = 'S' AND activo = 1 ORDER BY id_tipo_mov_caja LIMIT 1");
        $faltante = (int) DB::scalar("SELECT id_tipo_mov_caja FROM tipo_movimiento_caja
                                       WHERE nombre LIKE 'Faltante%' AND activo = 1 LIMIT 1");
        $this->assertNotSame(0, $gasto, 'La premisa: hace falta la clase «gasto con comprobante».');

        $cuantos = fn () => (int) DB::scalar('SELECT COUNT(*) FROM movimiento_caja WHERE id_cuenta = ? AND activo = 1', [$cuenta]);
        $tmp = tempnam(sys_get_temp_dir(), 'sgp') . '.png';
        file_put_contents($tmp, base64_decode(self::PNG_MINIMO));

        try {
            // 1) El gasto por transferencia, con la caja cerrada: entra contra la cuenta.
            $this->post(route('facturacion.caja.movimiento'), [
                'destino' => 'cuenta:' . $cuenta,
                'id_tipo_mov_caja' => $gasto,
                'monto' => '45.000', 'concepto' => 'insumos pagados por transferencia',
                'nro_comprobante' => '001-001-0001234', 'ruc_emisor' => '80012345-0',
                'archivo' => new UploadedFile($tmp, 'ticket.png', 'image/png', null, true),
            ])->assertRedirect(route('facturacion.movimientos'));

            $this->assertSame(1, $cuantos(),
                'El gasto por transferencia se registra contra la cuenta, y sin caja abierta: no toca el cajón.');
            $m = DB::selectOne('SELECT id_caja, id_cuenta, tipo FROM movimiento_caja WHERE id_cuenta = ? ORDER BY id_movimiento_caja DESC LIMIT 1', [$cuenta]);
            $this->assertNull($m->id_caja, 'De ningún cajón: `chk_mc_donde` pide uno u otro.');
            $this->assertSame('EGRESO', $m->tipo);
            $this->assertSame(255000.0, (float) DB::scalar('SELECT fn_cuenta_saldo(?)', [$cuenta]),
                'La cuenta descuenta el gasto: 300.000 declarados menos 45.000.');

            // 2) Un faltante de caja no se carga contra una cuenta: es una
            //    diferencia del arqueo del cajón.
            if ($faltante) {
                $this->post(route('facturacion.caja.movimiento'), [
                    'destino' => 'cuenta:' . $cuenta,
                    'id_tipo_mov_caja' => $faltante,
                    'monto' => '5.000', 'concepto' => 'faltante',
                ]);
                $this->assertSame(1, $cuantos(),
                    'El faltante es del cajón: contra el banco no significa nada.');
            }

            // 3) Y la base tampoco deja un movimiento sin cajón ni cuenta, ni con
            //    los dos: es la restricción que sostiene todo esto.
            try {
                DB::insert("INSERT INTO movimiento_caja (id_caja, id_cuenta, tipo, monto, concepto, id_usuario)
                            VALUES (NULL, NULL, 'EGRESO', 100, 'huérfano', 1)");
                $this->fail('Un movimiento sin cajón ni cuenta no sale de ningún arqueo: la base tiene que rechazarlo.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('chk_mc_donde', $e->getMessage());
            }
        } finally {
            @unlink($tmp);
            foreach (DB::select('SELECT archivo FROM movimiento_caja WHERE id_cuenta = ? AND archivo IS NOT NULL', [$cuenta]) as $f) {
                @unlink(storage_path('app/respaldos/' . $f->archivo));
            }
        }
    }
}
