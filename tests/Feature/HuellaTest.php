<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La pantalla que ofrece activar la huella después del primer ingreso.
 *
 * Es la única pantalla del sistema que se mete ENTRE el ingreso y el panel, así
 * que si algo falla ahí la persona no entra: no es una pantalla más que se ve
 * fea, es la puerta trabada. Por eso se prueba que se dibuje entera y que la
 * salida funcione.
 *
 * Escribe en `preferencia_usuario`, así que va con DatabaseTransactions.
 */
class HuellaTest extends TestCase
{
    use DatabaseTransactions;

    private const ADMIN = 'admin';

    private const CLAVE = 'admin123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        // Se lo deja como un usuario al que todavía no se le preguntó, que es
        // el único caso en que la pantalla aparece.
        DB::statement(
            'INSERT INTO preferencia_usuario (id_usuario, biometrico_pregunt)
             SELECT id_usuario, 0 FROM usuario WHERE username = ?
             ON DUPLICATE KEY UPDATE biometrico_pregunt = 0', [self::ADMIN]
        );
    }

    #[Test]
    public function la_pantalla_de_la_huella_se_dibuja_con_su_javascript(): void
    {
        // El marco de las pantallas de acceso no tenía @stack('scripts'), así
        // que todo lo que esta vista manda con @push se perdía en silencio: la
        // página salía completa pero sin una línea de JavaScript, los dos
        // botones quedaban sin nada detrás y no se podía salir de ahí.
        $this->post(route('login'), ['usuario' => self::ADMIN, 'password' => self::CLAVE]);

        $this->get(route('webauthn.preguntar'))
            ->assertOk()
            ->assertSee('Ahora no')
            ->assertSee('webauthn.js')          // el <script> del @push llegó
            ->assertSee('btnActivar', false);   // y el manejador también
    }

    #[Test]
    public function ahora_no_saca_de_la_pantalla_sin_javascript(): void
    {
        // «Ahora no» es un formulario de verdad: es la única salida, así que
        // tiene que andar aunque el JavaScript no cargue. Un POST normal (sin
        // cabecera de AJAX) tiene que devolver un redirect, no un JSON que el
        // navegador mostraría como texto pelado.
        $this->post(route('login'), ['usuario' => self::ADMIN, 'password' => self::CLAVE]);

        $this->post(route('webauthn.preguntado'))
            ->assertRedirect();

        $this->assertSame(
            1,
            (int) DB::scalar(
                'SELECT biometrico_pregunt FROM preferencia_usuario p
                   JOIN usuario u ON u.id_usuario = p.id_usuario WHERE u.username = ?', [self::ADMIN]
            ),
            'Tendría que haber quedado marcado que ya se preguntó.'
        );

        // Y no se vuelve a preguntar: la pantalla manda directo al panel.
        $this->get(route('webauthn.preguntar'))->assertRedirect();
    }

    #[Test]
    public function el_fetch_sigue_recibiendo_json(): void
    {
        // El botón de activar usa fetch y espera JSON. Que «Ahora no» ahora
        // redirija no puede haber roto ese camino.
        $this->post(route('login'), ['usuario' => self::ADMIN, 'password' => self::CLAVE]);

        $this->postJson(route('webauthn.preguntado'))
            ->assertOk()
            ->assertJson(['ok' => true]);
    }
}
