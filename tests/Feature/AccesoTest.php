<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Servicios\Navegacion;
use App\Servicios\Pendientes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Entrar, salir y no pasar donde no corresponde.
 *
 * Corre contra `peluqueria_test`, que trae las cuentas del mes simulado.
 * Casi todo es de lectura, pero la prueba que abre las pantallas de la
 * operación diaria necesita **una cita atendida y sin comprobante** para que
 * la pantalla del receptor tenga algo que mostrar, y en la base puede no
 * haberla. Por eso `DatabaseTransactions`: se prepara el caso y se revierte.
 */
class AccesoTest extends TestCase
{
    use DatabaseTransactions;

    private const ADMIN = 'admin';

    private const CLAVE = 'admin123';

    protected function setUp(): void
    {
        parent::setUp();

        // La ruta de ingreso está limitada a 10 intentos por minuto para que
        // nadie pruebe contraseñas a fuerza bruta. Esa protección es correcta
        // en producción, pero acá haría fallar la suite entera por 429: estas
        // pruebas hacen más de diez ingresos en pocos segundos.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    #[Test]
    public function la_raiz_lleva_al_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    #[Test]
    public function el_panel_no_se_abre_sin_iniciar_sesion(): void
    {
        $this->get(route('panel'))->assertRedirect(route('login'));
    }

    #[Test]
    public function la_pantalla_de_ingreso_se_dibuja(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Usuario o email')
            ->assertSee(config('app.name'));
    }

    #[Test]
    public function una_contrasena_equivocada_no_deja_entrar(): void
    {
        $this->post(route('login'), ['usuario' => self::ADMIN, 'password' => 'no-es-la-clave'])
            ->assertSessionHasErrors('usuario');

        $this->assertNull(session('uid'), 'No tendría que haber quedado una sesión abierta.');
    }

    #[Test]
    public function el_mensaje_de_error_no_delata_si_la_cuenta_existe(): void
    {
        // El mismo texto para «no existe» y para «clave incorrecta»: si fueran
        // distintos, cualquiera podría averiguar qué cuentas están registradas.
        $mismoMensaje = ['usuario' => 'Usuario o contraseña incorrectos.'];

        $this->post(route('login'), ['usuario' => 'no-existe-nadie', 'password' => 'x'])
            ->assertInvalid($mismoMensaje);

        $this->flushSession();

        $this->post(route('login'), ['usuario' => self::ADMIN, 'password' => 'x'])
            ->assertInvalid($mismoMensaje);
    }

    #[Test]
    public function con_las_credenciales_correctas_se_entra_al_panel(): void
    {
        // **A dónde cae depende de cuántas sucursales tenga esa cuenta**, y las
        // dos respuestas son correctas: con una sola se entra derecho al panel
        // —preguntar algo de una única respuesta hace perder un clic— y con
        // varias hay que elegir el local antes de ver nada. La prueba acepta
        // las dos, porque si no depende de cuántas sucursales haya cargadas.
        $this->post(route('login'), ['usuario' => self::ADMIN, 'password' => self::CLAVE, 'forzar' => 1])
            ->assertRedirect(
                count(\App\Servicios\Sucursales::delUsuario()) > 1
                    ? route('sucursal.elegir')
                    : route('panel')
            );

        $this->assertNotNull(session('uid'));
        $this->conSucursal();

        $this->get(route('panel'))
            ->assertOk()
            ->assertSee('Panel principal')
            ->assertSee('Citas');
    }

    #[Test]
    public function al_salir_se_pierde_la_sesion(): void
    {
        $this->entrarComo(self::ADMIN, self::CLAVE);
        $this->assertNotNull(session('uid'));

        $this->post(route('salir'))->assertRedirect(route('login'));

        $this->assertNull(session('uid'));
        $this->get(route('panel'))->assertRedirect(route('login'));
    }

    #[Test]
    public function salir_no_funciona_por_get(): void
    {
        // Con GET, cualquier enlace o precarga del navegador cerraría la sesión.
        $this->entrarComo(self::ADMIN, self::CLAVE);

        $this->get('/salir')->assertStatus(405);
        $this->assertNotNull(session('uid'), 'La sesión tenía que seguir abierta.');
    }

    #[Test]
    public function el_administrador_ve_los_siete_modulos(): void
    {
        $this->entrarComo(self::ADMIN, self::CLAVE);

        $respuesta = $this->get(route('panel'))->assertOk();
        foreach (config('permisos.modulos') as $etiqueta) {
            $respuesta->assertSee($etiqueta);
        }
    }

    #[Test]
    public function las_pantallas_de_seguridad_se_dibujan_enteras(): void
    {
        // Seguridad junta lo que eran Personal y Configuración, así que sus
        // pantallas cambiaron de nombre de ruta en masa. Un `route()` que quedó
        // con el nombre viejo no se nota hasta que alguien abre la pantalla:
        // revienta al dibujarla, no al arrancar. Por eso se abren todas.
        $this->entrarComo(self::ADMIN, self::CLAVE);

        $pantallas = [
            'seguridad.index', 'seguridad.usuarios', 'seguridad.usuario_form',
            'seguridad.roles', 'seguridad.turnos', 'seguridad.asistencia',
            'seguridad.comisiones', 'seguridad.comision_form',
            'seguridad.sucursales', 'seguridad.sucursal_form',
            'seguridad.contacto', 'seguridad.pagos', 'seguridad.auditoria',
            // Los dos landings que salieron de Seguridad en la 7.57.0
            'seguridad.personal.index', 'seguridad.configuracion.index',
        ];

        foreach ($pantallas as $p) {
            $this->get(route($p))->assertOk("La pantalla $p no se dibujó.");
        }
    }

    /**
     * Las pantallas del día a día, por el mismo motivo que las de Seguridad.
     *
     * Una columna mal escrita en una consulta **no se nota corriendo las
     * pruebas**: revienta al dibujar la pantalla, no al arrancar. Al sumarle a
     * la agenda el estado del cobro se escribió `f.nro_comprobante`, que no es
     * una columna de `factura` sino `fn_factura_nro()`, y las 58 pruebas
     * siguieron en verde con la agenda tirando 500 — nadie la abría.
     *
     * Se abren con la agenda del día de una cita **atendida y facturada**, que
     * es el camino que recorre las tres ramas nuevas de la columna Acciones.
     */
    #[Test]
    public function las_pantallas_de_la_operacion_diaria_se_dibujan_enteras(): void
    {
        $this->entrarComo(self::ADMIN, self::CLAVE);

        $dia = DB::scalar(
            'SELECT DATE(c.fecha_hora) FROM cita c
               JOIN factura f ON f.id_cita = c.id_cita AND f.id_estado_factura = 1
              WHERE c.id_estado_cita = 4 ORDER BY c.fecha_hora DESC LIMIT 1'
        ) ?: date('Y-m-d');

        $idCita = (int) DB::scalar(
            'SELECT c.id_cita FROM cita c
              WHERE c.id_estado_cita = 4
                AND NOT EXISTS (SELECT 1 FROM factura f
                                 WHERE f.id_cita = c.id_cita AND f.id_estado_factura = 1)
              ORDER BY c.fecha_hora DESC LIMIT 1'
        );

        // Si no hay ninguna sin facturar, se prepara una: la pantalla del
        // receptor no se dibuja sin una cita facturable, y si la prueba la
        // saltea deja de cubrirla justo cuando la base está al día.
        if (! $idCita) {
            $idCita = (int) DB::scalar(
                'SELECT c.id_cita FROM cita c
                  WHERE EXISTS (SELECT 1 FROM cita_servicio cs WHERE cs.id_cita = c.id_cita)
                  ORDER BY c.id_cita DESC LIMIT 1'
            );
            DB::update('UPDATE cita SET id_estado_cita = 4 WHERE id_cita = ?', [$idCita]);
            DB::delete('DELETE FROM factura WHERE id_cita = ?', [$idCita]);
        }

        $pantallas = [
            ['citas.agenda', ['dia' => $dia]],
            ['citas.agenda', []],
            ['citas.form', []],
            ['citas.ausencias', []],
            // Zonas del cuerpo: es la pantalla que decide qué se puede hacer a
            // la vez, así que si revienta la agenda queda sin criterio.
            ['servicios.zonas', []],
            // Reasignar, vacía y con una persona elegida: la segunda arma
            // consultas propias que la primera no toca.
            ['citas.reasignar', []],
            ['facturacion.index', []],
            ['facturacion.movimientos', []],
            ['facturacion.facturas', []],
            ['facturacion.emitir', []],
            // Con la cita ya elegida, que es como se llega desde la agenda
            ['facturacion.emitir', ['cita' => $idCita]],
            // Los datos del receptor, el paso previo a emitir un electrónico
            ['facturacion.receptor', ['cita' => $idCita, 'tipo' => 1, 'condicion' => 1]],
            ['facturacion.cajas', []],
            ['facturacion.arqueo', []],
            ['inventario.productos', []],
            ['inventario.stock', []],
            ['inventario.ajuste', []],
            // **Nueva compra faltaba en esta lista**, y por eso nadie vio que
            // devolvía 500: filtraba por `producto.id_sucursal`, columna que la
            // 7.33.0 eliminó. No se podía registrar ninguna compra.
            ['inventario.compra_form', []],
            ['inventario.compras', []],
            ['clientes.canjes', []],
            ['reportes.index', []],
            // El informe en papel, entero y por bloques: cada uno arma sus
            // propias consultas y una mal escrita revienta al dibujar.
            ['reportes.imprimir', []],
            ['reportes.imprimir', ['bloques' => ['demanda']]],
            ['reportes.imprimir', ['bloques' => ['resumen', 'equipo']]],
            // Una clave inventada se descarta y cae en el informe entero: nunca
            // se devuelve una hoja en blanco.
            ['reportes.imprimir', ['bloques' => ['no-existe']]],
        ];

        // Reasignar con alguien elegido: se toma quien tenga citas futuras, que
        // es el caso en que la pantalla arma la tabla de verdad.
        $conCitas = (int) DB::scalar(
            'SELECT c.id_usuario FROM cita c JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
              WHERE ec.bloquea_agenda = 1 AND c.fecha_hora >= NOW() LIMIT 1'
        );
        if ($conCitas) {
            $pantallas[] = ['citas.reasignar', ['de' => $conCitas]];
        }

        foreach ($pantallas as [$ruta, $par]) {
            $this->get(route($ruta, $par))
                ->assertOk('La pantalla ' . $ruta . ' (' . json_encode($par) . ') no se dibujó.');
        }
    }

    /**
     * Con la caja cerrada, Caja tiene que ofrecer cómo abrirla.
     *
     * **Un 200 no alcanza para decir que una pantalla anda.** La 7.46.0 mudó
     * el bloque de movimientos a su propia pantalla y se llevó puesto el
     * `@else`, así que el formulario de abrir quedó dentro de la rama «hay
     * caja abierta»: con la caja cerrada la pantalla contestaba 200 y salía
     * **sin nada**. La lista de pantallas ya la abría y no vio nada raro,
     * porque medía el código de respuesta y no lo que se dibuja.
     *
     * Y sin caja no se cobra, no se factura y no se paga: la sucursal queda
     * sin mostrador hasta que alguien toque la base a mano.
     */
    #[Test]
    public function con_la_caja_cerrada_la_pantalla_ofrece_abrirla(): void
    {
        $this->entrarComo(self::ADMIN, self::CLAVE);

        // Se cierran las que haya: lo que se mide es el estado «cerrada», y
        // `DatabaseTransactions` lo revierte al terminar.
        DB::update('UPDATE caja SET id_estado_caja = 2, fecha_cierre = NOW() WHERE id_estado_caja = 1');

        $cajon = (int) DB::scalar('SELECT MIN(id_caja_fisica) FROM caja_fisica WHERE activo = 1');
        $this->assertNotSame(0, $cajon, 'Sin ningún cajón cargado no se puede cobrar: el salón necesita al menos uno.');

        // 1) La lista ofrece entrar a esa caja.
        $lista = (string) $this->get(route('facturacion.cajas'))->assertOk()->getContent();
        $this->assertStringContainsString(route('facturacion.caja_ver', $cajon), $lista,
            'La lista de cajas tiene que llevar a la caja para poder abrirla.');

        // 2) Y ahí está el formulario de apertura, con el cajón puesto.
        $html = (string) $this->get(route('facturacion.caja_ver', $cajon))->assertOk()->getContent();

        $this->assertStringContainsString(
            route('facturacion.caja.abrir'), $html,
            'Con la caja cerrada, su pantalla tiene que ofrecer el formulario para abrirla.'
        );
        $this->assertStringContainsString(
            'monto_inicial', $html,
            'El formulario de apertura pide el monto inicial: sin ese campo no hay nada que enviar.'
        );
        $this->assertStringContainsString(
            'name="id_caja_fisica"', $html,
            'El formulario tiene que decir QUÉ caja abre: con varios cajones, sin eso no se sabe cuál.'
        );
    }

    /** Las doce listas que ofrecen el botón de bajar. */
    private const LISTAS = [
        'clientes.lista', 'clientes.fidelizacion', 'clientes.valoraciones',
        'servicios.lista', 'inventario.productos', 'inventario.movimientos',
        'inventario.proveedores', 'inventario.compras',
        'facturacion.facturas', 'facturacion.cobros',
        'seguridad.usuarios', 'seguridad.auditoria',
    ];

    #[Test]
    public function las_listas_bajan_en_csv_y_en_pdf(): void
    {
        // Exportar recorre un camino distinto al de dibujar la pantalla: el
        // método devuelve un archivo en vez de una vista. Si la firma del
        // controlador no admite ese tipo de retorno, revienta con un TypeError
        // que NO se ve abriendo la pantalla — sólo al apretar el botón. Fue lo
        // que pasó con Auditoría, declarada `: View` mientras bajaba un CSV.
        $this->entrarComo(self::ADMIN, self::CLAVE);

        foreach (self::LISTAS as $ruta) {
            $this->get(route($ruta, ['export' => 'csv']))
                ->assertOk("$ruta no bajó el CSV.")
                ->assertDownload();

            $this->get(route($ruta, ['export' => 'pdf']))
                ->assertOk("$ruta no dibujó el PDF.")
                ->assertSee('Descargar PDF');
        }
    }

    #[Test]
    public function un_formato_de_exportacion_inventado_muestra_la_pantalla(): void
    {
        // `?export=xlsx` no tiene que bajar nada ni reventar: se ignora y se
        // dibuja la lista, que es lo que la persona tiene delante.
        $this->entrarComo(self::ADMIN, self::CLAVE);

        $this->get(route('clientes.lista', ['export' => 'xlsx']))
            ->assertOk()
            ->assertHeaderMissing('content-disposition');
    }

    #[Test]
    public function mi_cuenta_se_dibuja_entera(): void
    {
        // Faltaba en la lista, y se notó al sumarle el bloque de sucursal: el
        // controlador usaba `Sucursales::` sin importarla, y eso no es un
        // error de sintaxis — revienta al abrir la pantalla, no al arrancar.
        // Es la misma lección que dejó Auditoría con `a.fecha`.
        $this->entrarComo(self::ADMIN, self::CLAVE);

        $this->get(route('cuenta.index'))->assertOk()->assertSee('Tus datos');
    }

    #[Test]
    public function el_panel_muestra_los_numeros_que_da_la_base(): void
    {
        $this->entrarComo(self::ADMIN, self::CLAVE);

        $clientes = (int) DB::scalar('SELECT COUNT(*) FROM cliente WHERE activo = 1');

        $this->get(route('panel'))->assertOk()->assertSee((string) $clientes);
    }

    /**
     * El desplegable de la barra recorre el catálogo entero, y eso no puede
     * tumbar el sistema.
     *
     * `Navegacion::url()` hacía `route($clave)` sin más, así que una pantalla
     * que necesita un parámetro —`clientes.historial` es `clientes/{id}/historial`—
     * levantaba `UrlGenerationException`. Mientras nadie la pidiera sin el id el
     * agujero estaba tapado por casualidad; **apenas el menú recorrió el
     * catálogo, el panel entero devolvió 500**. Quien arma un menú no tiene ese
     * id ni tiene por qué saber cuáles lo piden.
     *
     * Se comprueba con la pantalla concreta que lo rompió y con todos los
     * módulos, que es lo que hace de verdad la barra en cada petición.
     */
    #[Test]
    public function el_menu_de_la_barra_se_arma_para_todos_los_modulos(): void
    {
        $this->entrarComo(self::ADMIN, self::CLAVE);

        $this->assertNull(Navegacion::url('clientes.historial'),
            'Una pantalla que necesita un parámetro no se puede nombrar sin él: '
            . 'tiene que dar null, no reventar.');

        foreach (Navegacion::modulos() as $m) {
            $pantallas = Navegacion::pantallasDe((string) $m['mod']);
            foreach ($pantallas as $p) {
                $this->assertNotNull($p['url'],
                    'El menú de ' . $m['titulo'] . ' ofrece «' . $p['t'] . '» sin URL.');
            }
        }

        // Y la barra se dibuja de verdad, con las pantallas adentro. Se mira
        // **dentro de un módulo y no en el Panel**: ahí la barra no va, porque
        // el Panel ya muestra los módulos en tarjetas y la repetiría.
        $this->get(route('clientes.lista'))->assertOk()
             ->assertSee('spg-nav-menu', false)
             ->assertSee('Nueva cita');

        $this->get(route('panel'))->assertOk()
             ->assertDontSee('spg-nav-menu', false);
    }

    /**
     * El desplegable muestra las SECCIONES del módulo, no sus pantallas de
     * acción.
     *
     * Llegó a listar «Cargar stock» y «Nueva compra» al lado de «Stock» y
     * «Compras», que son las secciones de las que cuelgan: el menú mezclaba dos
     * niveles y quedaba más largo que la propia tarjeta del módulo. La regla es
     * que **el desplegable diga lo mismo que la tarjeta**, porque las dos
     * contestan «¿qué hay adentro de este módulo?» y dos respuestas distintas a
     * la misma pregunta es peor que una sola.
     *
     * Se marcan con el cuarto valor del catálogo en `false`. La prueba compara
     * contra la pantalla del módulo dibujada de verdad, que es la única fuente
     * que no se puede desfasar de sí misma.
     */
    #[Test]
    public function el_desplegable_dice_lo_mismo_que_la_tarjeta_del_modulo(): void
    {
        $this->entrarComo(self::ADMIN, self::CLAVE);

        foreach (Navegacion::modulos() as $m) {
            $landing = $this->get($m['url'])->assertOk()->getContent();

            foreach (Navegacion::pantallasDe((string) $m['mod']) as $p) {
                $this->assertStringContainsString($p['t'], $landing,
                    'El menú de ' . $m['titulo'] . ' ofrece «' . $p['t'] . '», que no es una '
                    . 'sección del módulo: la tarjeta no la muestra. Si es una pantalla de '
                    . 'acción, marcala con `false` en config/navegacion.php.');
            }
        }
    }

    /**
     * El panel dice qué falta cargar, y sólo a quien puede cargarlo.
     *
     * **Es el motivo de existir del bloque**: la misma pregunta la contesta
     * `spg:pendientes`, pero quien configura el salón no abre una terminal, así
     * que un aviso que sólo vive ahí es un aviso que nadie lee — la función
     * apagada en silencio de siempre.
     *
     * Se comprueba en las dos direcciones, que es lo que le da valor: el
     * Administrador ve los renglones **y** el Profesional no ve ninguno,
     * porque no tiene ninguno de los permisos con que se arreglan. Sin la
     * segunda mitad, un bloque que se dibujara siempre pasaría igual.
     */
    #[Test]
    public function el_panel_dice_que_falta_cargar_y_solo_a_quien_puede_hacerlo(): void
    {
        // Se fabrica algo pendiente para no depender de cómo esté la base: un
        // profesional con turno y sin comisión vigente. `DatabaseTransactions`
        // lo revierte al terminar.
        $prof = DB::selectOne(
            'SELECT u.id_usuario FROM usuario u
               JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.activo = 1 AND r.es_personal = 1
                AND EXISTS (SELECT 1 FROM usuario_turno t WHERE t.id_usuario = u.id_usuario)
              LIMIT 1'
        );
        $this->assertNotNull($prof, 'Hace falta alguien con turno para esta prueba.');
        DB::update('UPDATE comision SET activo = 0 WHERE id_usuario = ?', [$prof->id_usuario]);

        $this->assertNotSame([], Pendientes::todo(),
            'Con una comisión de menos tiene que faltar algo.');

        $this->entrarComo(self::ADMIN, self::CLAVE);
        $panel = $this->get(route('panel'))->assertOk()->getContent();

        $this->assertStringContainsString('Falta cargar', $panel,
            'El panel del Administrador no muestra lo que falta cargar.');
        $this->assertStringContainsString('spg-falta-nivel', $panel);

        // Y la otra mitad, que es la que le da valor: quien no puede resolver
        // nada, no ve nada. **La sesión se arma a mano y no se ingresa con
        // contraseña**: probé con una y el ingreso fallaba en silencio, así que
        // la aserción quedaba dentro de un `if` que nunca se cumplía — la
        // prueba pasaba igual con el filtro por permiso sacado a propósito, o
        // sea que no medía nada.
        $rolProf = (int) DB::scalar("SELECT id_rol FROM rol WHERE nombre = 'Profesional' LIMIT 1");
        $uidProf = (int) DB::scalar(
            'SELECT id_usuario FROM usuario WHERE id_rol = ? AND activo = 1 LIMIT 1', [$rolProf]);
        $this->assertGreaterThan(0, $uidProf, 'Hace falta un Profesional para la otra mitad.');

        session(['uid' => $uidProf, 'rol' => $rolProf, 'es_personal' => true,
            'es_cliente' => false, 'id_sucursal' => 1]);
        $this->conMarcaDeSesion();

        $suyo = (string) $this->get(route('panel'))->assertOk()->getContent();
        $this->assertStringNotContainsString('spg-falta-nivel', $suyo,
            'El Profesional ve pendientes que no tiene permiso para resolver.');
    }

    /**
     * Las pantallas se dibujan con UNA sucursal y con VARIAS.
     *
     * **Es el defecto que este proyecto ya se hizo dos veces**, y que además
     * apareció compartiendo el proyecto entre dos computadoras: el mismo
     * código, la misma versión y la misma cuenta, pero una base con un local y
     * la otra con once — y la pantalla no se comporta igual.
     *
     * Eso está bien cuando es deliberado (`$varias` esconde la columna de
     * sucursal cuando hay una sola: preguntar algo de una única respuesta hace
     * perder un clic). Lo que NO puede pasar es que una pantalla reviente en
     * uno de los dos casos, porque quien desarrolla con once sucursales no lo
     * ve nunca — y el salón instala con una.
     *
     * La 7.31.3 lo tuvo con las pruebas: 86 en verde con una sucursal y 19
     * rojas con dos. La 7.35.0 lo tuvo con el catálogo: el segundo local nacía
     * sin servicios y la clienta no veía qué reservar.
     */
    #[Test]
    public function las_pantallas_andan_con_una_sucursal_y_con_varias(): void
    {
        $this->entrarComo(self::ADMIN, self::CLAVE);

        // Las de la operación diaria más las que dependen de la sucursal: son
        // las que cambian de forma según cuántos locales haya.
        $pantallas = [
            'panel', 'citas.agenda', 'citas.form', 'clientes.lista', 'clientes.fidelizacion',
            'servicios.lista', 'servicios.form', 'inventario.productos', 'inventario.stock',
            'inventario.compras', 'inventario.compra_form', 'facturacion.facturas',
            'facturacion.emitir', 'facturacion.cobros', 'facturacion.cajas', 'facturacion.arqueo',
            'facturacion.movimientos', 'facturacion.timbrados', 'reportes.index',
            'seguridad.usuarios', 'seguridad.usuario_form', 'seguridad.turnos',
            'seguridad.sucursales',
        ];

        $abrirTodas = function (string $cuando) use ($pantallas) {
            foreach ($pantallas as $r) {
                if (! \Illuminate\Support\Facades\Route::has($r)) {
                    continue;
                }
                $this->get(route($r))->assertOk(
                    'La pantalla «' . $r . '» no se dibuja ' . $cuando . '.');
            }
        };

        // --- Con UNA sucursal: lo que tiene el salón el día uno -------------
        $primera = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');
        DB::update('UPDATE sucursal SET activo = 0 WHERE id_sucursal <> ?', [$primera]);
        $this->conSucursal($primera);
        $abrirTodas('con una sola sucursal');

        // Y las secciones del informe, que es donde más cambia la forma.
        foreach (array_keys(\App\Http\Controllers\ReportesController::SECCIONES) as $sec) {
            $this->get(route('reportes.index', ['r' => $sec]))->assertOk(
                'El informe «' . $sec . '» no se dibuja con una sola sucursal.');
        }

        // --- Con DOS: lo que tiene quien ya abrió el segundo local ----------
        DB::insert('INSERT INTO sucursal (nombre, direccion, activo) VALUES (?, ?, 1)',
                   ['Sucursal de la prueba ' . uniqid(), 'Calle 11']);
        $otra = (int) DB::scalar('SELECT LAST_INSERT_ID()');
        DB::insert('INSERT IGNORE INTO usuario_sucursal (id_usuario, id_sucursal) VALUES (?,?)',
                   [(int) session('uid'), $otra]);

        $this->conSucursal($primera);
        $abrirTodas('con dos sucursales');
        foreach (array_keys(\App\Http\Controllers\ReportesController::SECCIONES) as $sec) {
            $this->get(route('reportes.index', ['r' => $sec]))->assertOk(
                'El informe «' . $sec . '» no se dibuja con dos sucursales.');
        }

        // Y paradas en el local nuevo, que no tiene ni una cita: es el caso
        // que más veces rompió algo —una lista vacía, una división por cero—.
        $this->conSucursal($otra);
        $abrirTodas('parado en un local recién abierto');
    }

    /**
     * La pantalla «Sin permiso» siempre tiene una salida que lleva a otro lado.
     *
     * **El caso que lo destapó**: una cuenta de cliente sin ficha vinculada. Su
     * inicio es el portal, y el portal es justamente el que le contesta 403, así
     * que «Volver al inicio» la devolvía a la misma pantalla — desde afuera, un
     * botón que no hace nada.
     *
     * Se comprueba en las dos direcciones: que **no** ofrezca el enlace circular
     * y que **sí** ofrezca cerrar sesión, que es la única salida real cuando la
     * cuenta no puede llegar a ninguna parte.
     */
    #[Test]
    public function sin_permiso_nunca_deja_a_la_persona_sin_salida(): void
    {
        // Una cuenta de cliente sin fila en `cliente`: entra, pero el portal no
        // la puede atender.
        DB::insert("INSERT INTO persona (nombre, apellido) VALUES ('Sin', 'Ficha')");
        $per = (int) DB::scalar('SELECT LAST_INSERT_ID()');
        DB::insert(
            "INSERT INTO usuario (id_persona, username, password_hash, id_rol, activo)
             VALUES (?, ?, ?, 4, 1)",
            [$per, 'sinficha' . $per, password_hash('x', PASSWORD_BCRYPT)]
        );
        $uid = (int) DB::scalar('SELECT LAST_INSERT_ID()');

        $r = $this->withSession([
            'uid' => $uid,
            'rol' => 4,
            'es_cliente' => true,
            'sesion_marca' => DB::scalar('SELECT sesion_activa FROM usuario WHERE id_usuario = ?', [$uid]),
        ])->get(route('portal.index'));

        $r->assertStatus(403);
        $r->assertDontSee(route('portal.index'), false);
        $r->assertSee(route('salir'), false);
    }

    /**
     * Crear un usuario funciona, y la ficha no esconde ningún campo obligatorio.
     *
     * **El defecto que lo motivó**: los tres bloques estaban en pestañas, y un
     * `required` dentro de una pestaña cerrada está en `display:none`. El
     * navegador se niega a enviar un formulario con un campo obligatorio que no
     * puede enfocar, **y no muestra nada** — se apretaba Guardar y no pasaba
     * absolutamente nada.
     *
     * Se comprueba lo que se puede comprobar desde el servidor: que la ficha
     * **no dibuje pestañas** (que es lo que escondía los campos) y que el POST
     * cree la cuenta de verdad.
     */
    #[Test]
    public function la_ficha_de_usuario_no_esconde_campos_y_el_alta_funciona(): void
    {
        $this->entrarComo('admin', 'admin123');

        // La persona tiene que existir antes: la cuenta se le crea a alguien
        // ya cargado en Personal → Profesionales.
        DB::insert("INSERT INTO persona (nombre, apellido, es_personal) VALUES ('Alta', 'De Prueba', 1)");
        $persona = (int) DB::scalar('SELECT LAST_INSERT_ID()');

        $ficha = $this->get(route('seguridad.usuario_form'));
        $ficha->assertOk();
        $ficha->assertDontSee('data-bs-toggle="pill"', false);

        // Los tres bloques tienen que estar en la misma pantalla.
        foreach (['name="id_persona"', 'name="username"', 'name="sucursales[]"'] as $campo) {
            $ficha->assertSee($campo, false);
        }

        $u = 'alta' . random_int(10000, 99999);
        $suc = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');
        $rol = (int) DB::scalar('SELECT MIN(id_rol) FROM rol WHERE es_personal = 1 AND activo = 1 AND id_rol <> 1');

        $this->post(route('seguridad.usuario.guardar'), [
            'id_usuario' => 0,
            'id_persona' => $persona,
            'username' => $u,
            'password' => 'clave123',
            'id_rol' => $rol,
            'sucursales' => [$suc],
        ]);

        $this->assertSame(1, (int) DB::scalar(
            'SELECT COUNT(*) FROM usuario WHERE username = ?', [$u]),
            'El alta de usuario no llegó a crear la cuenta.');

        // Editar sin tocar la contraseña NO la borra: vacío quiere decir «no la
        // cambies», no «dejala en null».
        $id = (int) DB::scalar('SELECT id_usuario FROM usuario WHERE username = ?', [$u]);
        $antes = (string) DB::scalar('SELECT password_hash FROM usuario WHERE id_usuario = ?', [$id]);

        $this->post(route('seguridad.usuario.guardar'), [
            'id_usuario' => $id,
            'id_persona' => $persona,
            'username' => $u . 'b',
            'password' => '',
            'id_rol' => $rol,
            'sucursales' => [$suc],
        ]);

        $this->assertSame($antes, (string) DB::scalar(
            'SELECT password_hash FROM usuario WHERE id_usuario = ?', [$id]),
            'Guardar con la contraseña vacía se la borró: vacío es «no la toques».');
        $this->assertSame($u . 'b', DB::scalar(
            'SELECT username FROM usuario WHERE id_usuario = ?', [$id]),
            'La edición no se guardó.');
    }

    /**
     * Cargar una cuenta de pago funciona de punta a punta.
     *
     * **La prueba anterior insertaba directo en la tabla**, así que medía el
     * aislamiento por sucursal y no el camino real: el controlador llamaba a
     * `Persona::error('documento', $doc)` —dos strings a un método que recibe
     * un arreglo— y la pantalla devolvía un TypeError 500 al guardar. Una
     * prueba que no pasa por el POST no ve eso.
     *
     * Se comprueba el alta con RUC y con cédula, porque el titular puede tener
     * cualquiera de los dos y validar contra uno solo rechazaría la mitad de
     * los casos legítimos.
     */
    #[Test]
    public function cargar_una_cuenta_de_pago_funciona_de_punta_a_punta(): void
    {
        $this->entrarComo('admin', 'admin123');

        $suc = (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');
        $medio = (int) DB::scalar("SELECT id_metodo_pago FROM metodo_pago WHERE tipo = 'BANCO' LIMIT 1");

        $cargar = function (string $doc, string $nro) use ($suc, $medio) {
            return $this->post(route('seguridad.pagos.guardar'), [
                'id_dato_pago' => 0,
                'id_sucursal' => $suc,
                'id_metodo_pago' => $medio,
                'entidad' => 'Banco de prueba',
                'titular' => 'Salón de prueba',
                'documento' => $doc,
                'numero_cuenta' => $nro,
                'alias' => 'alias.' . $nro,
                'tipo_cuenta' => 'Caja de ahorro',
            ]);
        };

        // Con RUC (lleva verificador) y con cédula (no lo lleva).
        $cargar('80012345-0', 'CTA-RUC-' . random_int(1000, 9999));
        $cargar('4200000', 'CTA-CI-' . random_int(1000, 9999));

        $this->assertSame(2, (int) DB::scalar(
            "SELECT COUNT(*) FROM dato_pago_sucursal
              WHERE id_sucursal = ? AND titular = 'Salón de prueba'", [$suc]),
            'El alta de cuentas de pago no llegó a guardar: revisá el POST, no la tabla.');

        // El alias se guarda: es lo que varios bancos usan para transferir, y
        // es más corto que el número.
        $this->assertNotNull(DB::scalar(
            "SELECT alias FROM dato_pago_sucursal
              WHERE id_sucursal = ? AND titular = 'Salón de prueba' LIMIT 1", [$suc]),
            'El alias no se guardó.');

        // Y un documento que no es ni cédula ni RUC se rechaza.
        $cargar('abc!!!', 'CTA-MALA-' . random_int(1000, 9999));
        $this->assertSame(2, (int) DB::scalar(
            "SELECT COUNT(*) FROM dato_pago_sucursal
              WHERE id_sucursal = ? AND titular = 'Salón de prueba'", [$suc]),
            'Un documento con letras y símbolos no puede entrar.');
    }
}
