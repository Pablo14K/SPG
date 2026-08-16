<?php

declare(strict_types=1);

namespace Tests\Feature;

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
            'seguridad.contacto', 'seguridad.auditoria',
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
            // Reasignar, vacía y con una persona elegida: la segunda arma
            // consultas propias que la primera no toca.
            ['citas.reasignar', []],
            ['facturacion.index', []],
            ['facturacion.facturas', []],
            ['facturacion.emitir', []],
            // Con la cita ya elegida, que es como se llega desde la agenda
            ['facturacion.emitir', ['cita' => $idCita]],
            // Los datos del receptor, el paso previo a emitir un electrónico
            ['facturacion.receptor', ['cita' => $idCita, 'tipo' => 1, 'condicion' => 1]],
            ['facturacion.caja', []],
            ['inventario.productos', []],
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
}
