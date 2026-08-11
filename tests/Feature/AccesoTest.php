<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Entrar, salir y no pasar donde no corresponde.
 *
 * Corre contra `peluqueria_test`, que trae las cuentas del mes simulado.
 * Estas pruebas solo leen: el login no escribe nada salvo la fila de auditoría.
 */
class AccesoTest extends TestCase
{
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
        $this->post(route('login'), ['usuario' => self::ADMIN, 'password' => self::CLAVE])
            ->assertRedirect(route('panel'));

        $this->assertNotNull(session('uid'));

        $this->get(route('panel'))
            ->assertOk()
            ->assertSee('Panel principal')
            ->assertSee('Citas y agenda');
    }

    #[Test]
    public function al_salir_se_pierde_la_sesion(): void
    {
        $this->post(route('login'), ['usuario' => self::ADMIN, 'password' => self::CLAVE]);
        $this->assertNotNull(session('uid'));

        $this->post(route('salir'))->assertRedirect(route('login'));

        $this->assertNull(session('uid'));
        $this->get(route('panel'))->assertRedirect(route('login'));
    }

    #[Test]
    public function salir_no_funciona_por_get(): void
    {
        // Con GET, cualquier enlace o precarga del navegador cerraría la sesión.
        $this->post(route('login'), ['usuario' => self::ADMIN, 'password' => self::CLAVE]);

        $this->get('/salir')->assertStatus(405);
        $this->assertNotNull(session('uid'), 'La sesión tenía que seguir abierta.');
    }

    #[Test]
    public function el_administrador_ve_los_ocho_modulos(): void
    {
        $this->post(route('login'), ['usuario' => self::ADMIN, 'password' => self::CLAVE]);

        $respuesta = $this->get(route('panel'))->assertOk();
        foreach (config('permisos.modulos') as $etiqueta) {
            $respuesta->assertSee($etiqueta);
        }
    }

    #[Test]
    public function el_panel_muestra_los_numeros_que_da_la_base(): void
    {
        $this->post(route('login'), ['usuario' => self::ADMIN, 'password' => self::CLAVE]);

        $clientes = (int) DB::scalar('SELECT COUNT(*) FROM cliente WHERE activo = 1');

        $this->get(route('panel'))->assertOk()->assertSee((string) $clientes);
    }
}
