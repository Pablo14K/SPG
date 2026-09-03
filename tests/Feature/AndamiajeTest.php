<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Servicios\Navegacion;
use App\Servicios\Permisos;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Que las piezas sigan enganchadas entre sí.
 *
 * Estas pruebas no comprueban una regla del negocio: comprueban que **el
 * andamiaje no se haya soltado**. Cada una nació de un error real de este
 * proyecto, y todos son de la misma familia — algo se renombró o se movió, lo
 * que apuntaba a eso quedó apuntando al vacío, y **nada dio error**:
 *
 *  · una clave de permiso renombrada dejó al Asistente sin la agenda completa;
 *  · el desplegable de la barra salía del nombre de la ruta y no del permiso,
 *    así que dos módulos quedaron sin un solo renglón;
 *  · `imprimir.css` apuntaba entero a clases que ninguna vista dibuja;
 *  · un formulario leía `$editar`, variable que había dejado de existir.
 *
 * Ninguno rompe nada al arrancar. Se descubren cuando alguien abre la pantalla
 * —o peor, cuando no la abre y da por hecho que anda—. Por eso van acá.
 */
class AndamiajeTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Toda clave de permiso que se pide existe en `config/permisos.php`.
     *
     * **Es el error de la 7.57.0.** Renombrar `seguridad.turnos` a
     * `personal.turnos` dejó dos lugares preguntando por la clave vieja:
     * `Permisos::puede()` contesta que no —no que la clave no existe— así que
     * el rol pierde la pantalla **en silencio**.
     */
    #[Test]
    public function toda_clave_de_permiso_que_se_pide_existe(): void
    {
        $validas = array_flip(Permisos::claves());
        foreach (array_keys(config('permisos.modulos', [])) as $m) {
            $validas[$m] = true;   // el módulo padre también se puede pedir
        }

        $malas = [];

        // 1) Los guardias de las rutas: `->middleware('modulo:x.y')`
        foreach (Route::getRoutes() as $r) {
            foreach ($r->gatherMiddleware() as $mw) {
                if (! is_string($mw) || ! str_starts_with($mw, 'modulo:')) {
                    continue;
                }
                $clave = substr($mw, 7);
                if (! isset($validas[$clave])) {
                    $malas[] = 'ruta ' . ($r->getName() ?: $r->uri()) . ' pide «' . $clave . '»';
                }
            }
        }

        // 2) Lo que el código pregunta a mano: `puede('x.y')` y `rolPuede(…, 'x.y')`
        foreach ($this->archivos(['app', 'resources/views']) as $f => $txt) {
            // Sólo las escritas como literal: `puede($mod['mod'])` se resuelve
            // en tiempo de ejecución y desde acá no se puede saber qué vale.
            preg_match_all("/(?:puede|rolPuede)\\(\\s*'([a-z_]+(?:\\.[a-z_]+)?)'\\s*[,)]/", $txt, $m);
            foreach ($m[1] as $clave) {
                if (! isset($validas[$clave])) {
                    $malas[] = basename($f) . ' pregunta por «' . $clave . '»';
                }
            }
        }

        // 3) Las claves del catálogo de pantallas
        foreach (config('navegacion.pantallas', []) as $ruta => $p) {
            if (! isset($validas[(string) $p[2]])) {
                $malas[] = 'navegacion: «' . $ruta . '» declara el permiso «' . $p[2] . '»';
            }
        }

        $this->assertSame([], $malas,
            "Hay claves de permiso que no existen. Si una se renombró, hay que tocar "
            . "los guardias, el catálogo y `equivalencias`:\n  " . implode("\n  ", $malas));
    }

    /**
     * Lo guardado en `rol_modulo` se sigue entendiendo.
     *
     * Una clave vieja no da error: se traduce con `equivalencias` o **se
     * pierde**. Esta prueba exige que toda clave guardada termine en una que
     * exista, que es lo que impide que un rol se quede sin pantalla al
     * actualizar el sistema.
     */
    #[Test]
    public function toda_clave_guardada_en_rol_modulo_sigue_significando_algo(): void
    {
        $validas = array_flip(Permisos::claves());
        foreach (array_keys(config('permisos.modulos', [])) as $m) {
            $validas[$m] = true;
        }

        $huerfanas = [];
        foreach (DB::select('SELECT DISTINCT modulo FROM rol_modulo') as $r) {
            $clave = (string) $r->modulo;
            foreach (Permisos::equivaler([$clave]) as $traducida) {
                if (! isset($validas[$traducida])) {
                    $huerfanas[] = $clave . ' → ' . $traducida;
                }
            }
        }

        $this->assertSame([], $huerfanas,
            "Hay permisos guardados que ya no llevan a ninguna pantalla:\n  "
            . implode("\n  ", $huerfanas));
    }

    /**
     * Cada pantalla del catálogo de navegación tiene su ruta declarada.
     *
     * El catálogo alimenta las migas, la barra y el desplegable. Una entrada
     * que nombra una ruta inexistente no revienta: **desaparece del menú**, y
     * con ella el único camino a esa pantalla.
     */
    #[Test]
    public function cada_pantalla_del_catalogo_tiene_su_ruta(): void
    {
        $sinRuta = [];
        foreach (array_keys(config('navegacion.pantallas', [])) as $nombre) {
            if (! Route::has((string) $nombre)) {
                $sinRuta[] = (string) $nombre;
            }
        }

        $this->assertSame([], $sinRuta,
            'El catálogo nombra rutas que no existen: ' . implode(', ', $sinRuta));
    }

    /**
     * Todo módulo con submódulos ofrece al menos una pantalla en su menú.
     *
     * **Es el error de la 7.58.0.** El desplegable agrupaba por el nombre de la
     * ruta, y como las pantallas no se mudaron de URL al partir Seguridad,
     * Personal y Configuración quedaron con el menú vacío. Nadie dio error:
     * simplemente no había nada que mostrar.
     */
    #[Test]
    public function ningun_modulo_se_queda_sin_pantallas_en_su_menu(): void
    {
        $vacios = [];
        foreach (array_keys(config('permisos.submodulos', [])) as $modulo) {
            $tiene = false;
            foreach (config('navegacion.pantallas', []) as $p) {
                $suyo = str_contains((string) $p[2], '.')
                    ? explode('.', (string) $p[2])[0]
                    : (string) $p[2];
                if ($suyo === $modulo) {
                    $tiene = true;
                    break;
                }
            }
            if (! $tiene) {
                $vacios[] = $modulo;
            }
        }

        $this->assertSame([], $vacios,
            'Estos módulos no tienen ninguna pantalla en el catálogo: ' . implode(', ', $vacios));
    }

    /**
     * Lo que el JavaScript busca existe en alguna vista.
     *
     * **Es el error de la 7.4.0 y de la 7.1.0**: código correcto, probado, y
     * apuntando a un marcado que nunca se escribió o que se dejó de usar. Un
     * `data-*` sin marcado no falla — la función simplemente no ocurre, y desde
     * afuera se lee como que el sistema no la tiene.
     */
    #[Test]
    public function lo_que_busca_el_javascript_existe_en_el_marcado(): void
    {
        $js = (string) file_get_contents(public_path('assets/js/app.js'));
        $marcado = implode("\n", $this->archivos(['resources/views']));

        preg_match_all("/\\[data-([a-z-]+)[\\]=]/", $js, $m);

        $sinUso = [];
        foreach (array_unique($m[1]) as $attr) {
            if (! str_contains($marcado, 'data-' . $attr)) {
                $sinUso[] = 'data-' . $attr;
            }
        }

        $this->assertSame([], $sinUso,
            "El JS busca atributos que ninguna vista dibuja:\n  " . implode("\n  ", $sinUso)
            . "\nO falta el marcado, o sobra el JS.");
    }

    /**
     * Las clases propias del CSS aparecen en alguna vista.
     *
     * **Es el error de la 7.54.0**: `imprimir.css` apuntaba entero a una
     * familia de clases que ninguna vista dibuja, así que sus 87 líneas no
     * aplicaban una sola regla. No se nota mirando el archivo: se nota
     * imprimiendo.
     */
    #[Test]
    public function las_clases_propias_del_css_se_usan_en_alguna_vista(): void
    {
        $marcado = implode("\n", $this->archivos(['resources/views', 'public/assets/js']));

        $sinUso = [];
        foreach (['app.css', 'imprimir.css'] as $hoja) {
            $css = (string) file_get_contents(public_path('assets/css/' . $hoja));
            // Sin comentarios: los de este proyecto NOMBRAN clases retiradas
            // para explicar por qué se fueron, y mencionarlas no es usarlas.
            $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

            // Sólo las propias: las de Bootstrap se dan por buenas.
            preg_match_all('/\.(spg-[a-z0-9-]+|comp-[a-z0-9-]+)\b/', $css, $m);
            foreach (array_unique($m[1]) as $clase) {
                if (! str_contains($marcado, $clase)) {
                    $sinUso[] = $hoja . ' → .' . $clase;
                }
            }
        }

        $this->assertSame([], $sinUso,
            "Hay CSS apuntando a clases que ningún marcado usa:\n  " . implode("\n  ", $sinUso));
    }

    /** Los archivos de esas carpetas, para buscar dentro. */
    private function archivos(array $dirs): array
    {
        $out = [];
        foreach ($dirs as $d) {
            $base = base_path($d);
            if (! is_dir($base)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));
            foreach ($it as $f) {
                if ($f->isFile() && preg_match('/\.(php|js)$/', $f->getFilename())) {
                    $out[$f->getPathname()] = (string) file_get_contents($f->getPathname());
                }
            }
        }

        return $out;
    }

    /**
     * La barra marca el módulo al que de verdad pertenece la pantalla.
     *
     * **Es el defecto del nombre de ruta, por tercera vez.** Al partir Seguridad
     * en tres (7.57.0) las pantallas no se mudaron de URL —Personal y
     * Configuración siguen viviendo bajo `/seguridad`— así que deducir el módulo
     * del prefijo del nombre encendía **Seguridad** estando en Personal.
     *
     * Ya había pasado en el desplegable (7.58.0) y en la tarjeta del módulo
     * (7.62.0). Acá quedaba el marcado del activo.
     *
     * Recorre el catálogo entero en vez de fijar tres casos: así una pantalla
     * nueva mal declarada también salta.
     */
    #[Test]
    public function la_barra_marca_el_modulo_del_permiso_y_no_el_del_nombre_de_ruta(): void
    {
        $modulos = array_map(fn ($m) => (string) $m['mod'], config('navegacion.modulos', []));

        // 1) La entrada de cada módulo se marca a sí misma.
        foreach (config('navegacion.modulos', []) as $m) {
            $this->assertSame((string) $m['mod'], Navegacion::moduloDe((string) $m['ruta']),
                'La entrada de ' . $m['mod'] . ' marcaría otro módulo en la barra.');
        }

        // 2) Cada pantalla del catálogo cae en el módulo de SU permiso.
        foreach (config('navegacion.pantallas', []) as $clave => $p) {
            $permiso = (string) $p[2];
            $suyo = str_contains($permiso, '.') ? explode('.', $permiso)[0] : $permiso;

            $this->assertSame($suyo, Navegacion::moduloDe((string) $clave),
                "La pantalla $clave marcaría un módulo que no es el suyo ($permiso).");
        }

        // 3) Y el caso concreto que lo destapó, escrito aparte: sin el arreglo,
        //    estas tres devuelven «seguridad» y la prueba falla.
        $this->assertSame('personal', Navegacion::moduloDe('seguridad.personal.index'));
        $this->assertSame('configuracion', Navegacion::moduloDe('seguridad.configuracion.index'));
        $this->assertSame('personal', Navegacion::moduloDe('seguridad.turnos'));
    }

    /**
     * El landing de cada módulo ofrece TODAS sus pantallas.
     *
     * **Séptimo patrón de los errores que este proyecto se hace a sí mismo**: la
     * tarjeta del landing se escribe a mano y el desplegable sale del catálogo,
     * así que al sumar una pantalla es fácil hacer sólo una de las dos. El
     * síntoma es exactamente el que se reportó: «Datos de pago» aparecía en el
     * menú de la barra y **no** en las tarjetas de Configuración.
     *
     * Ya había pasado con la tarjeta de Seguridad (7.62.0) y con la de Personal
     * (7.62.0), las dos veces al revés — anunciando de más o de menos.
     *
     * Se abre cada landing como Administrador y se comprueba que nombre todas
     * las pantallas que el catálogo declara para ese módulo.
     */
    #[Test]
    public function el_landing_de_cada_modulo_ofrece_todas_sus_pantallas(): void
    {
        $this->entrarComo('admin', 'admin123');

        foreach (config('navegacion.modulos', []) as $m) {
            $url = Navegacion::url((string) $m['ruta']);
            if ($url === null) {
                continue;
            }

            $html = (string) $this->get($url)->assertOk()->getContent();

            // **Se mira SÓLO el bloque de tarjetas.** La barra del layout ya
            // dibuja todas las pantallas en su desplegable, así que buscar la
            // URL en el HTML entero la encuentra siempre y la prueba no mide
            // nada — pasó al escribirla.
            $desde = strpos($html, '<div class="spg-cards">');
            $hasta = strrpos($html, '</main>');
            $tarjetas = $desde === false
                ? ''
                : substr($html, $desde, ($hasta !== false ? $hasta : strlen($html)) - $desde);

            foreach (Navegacion::pantallasDe((string) $m['mod']) as $pant) {
                // La entrada del módulo no se anuncia a sí misma.
                if ((string) $pant['url'] === $url) {
                    continue;
                }

                $this->assertStringContainsString((string) $pant['url'], $tarjetas,
                    'El landing de ' . $m['mod'] . ' no ofrece «' . $pant['t']
                    . '», que el catálogo sí declara. La barra la muestra y la tarjeta no.');
            }
        }
    }

    /**
     * **El Automatizador SIFEN no manda correos: manda el SPG.**
     *
     * Los dos saben mandarle el comprobante a la clienta y los dos adjuntan el
     * KuDE y el XML, pero cada uno lo haría **con su propia cuenta**: el SPG con
     * la del salón —que el Administrador cambia desde «Seguridad → Correo del
     * sistema»— y el Automatizador con la de su `.env`, que no se toca desde el
     * sistema. Con los dos prendidos la clienta recibe lo mismo dos veces desde
     * direcciones distintas, y cambiar la cuenta en la pantalla arregla la mitad.
     *
     * Por eso hay un único remitente, y esta guardia existe porque la forma de
     * romperlo es **llenar una línea de un archivo de ejemplo**: alguien copia
     * `.env.example` al servidor, ve `MAIL_USERNAME=tucorreo@gmail.com` y lo
     * completa de buena fe. Con `MAIL_FROM_EMAIL` vacío, `construirMail()`
     * devuelve null y el envío se saltea sin romper la declaración.
     */
    #[Test]
    public function el_automatizador_no_manda_correo_por_su_cuenta(): void
    {
        $env = base_path('_sifen/.env.example');
        if (! is_file($env)) {
            $this->markTestSkipped('El Automatizador no está en esta copia.');
        }

        $txt = (string) file_get_contents($env);

        foreach (['MAIL_FROM_EMAIL', 'MAIL_USERNAME', 'MAIL_PASSWORD'] as $clave) {
            $this->assertMatchesRegularExpression(
                '/^' . $clave . '=\s*$/m', $txt,
                $clave . ' del Automatizador tiene que quedar VACÍO en el .env.example: '
                . 'el que le manda el comprobante a la clienta es el SPG, con la cuenta de '
                . '«Seguridad → Correo del sistema». Con los dos mandando, le llega dos veces '
                . 'desde direcciones distintas.'
            );
        }
    }

    /**
     * **La ayuda contextual guarda el texto, no lo tira.**
     *
     * `<x-ayuda>` esconde la explicación detrás de un ícono para que la pantalla
     * no la muestre toda de golpe. El riesgo es obvio: que al esconderla se
     * pierda. Por eso se comprueba que el texto siga estando **en el marcado**,
     * dentro del `data-bs-content` que Bootstrap lee para el globo.
     *
     * Y se comprueba que el ícono lleve el disparador `focus`, que es lo único
     * de los cuatro de Bootstrap que da el comportamiento pedido: abre al
     * tocarlo y **cierra al tocar afuera**.
     */
    #[Test]
    public function la_ayuda_contextual_conserva_el_texto_que_esconde(): void
    {
        $this->entrarComo('admin', 'admin123');

        $html = (string) $this->get(route('citas.agenda'))->assertOk()->getContent();

        $this->assertStringContainsString('class="spg-ayuda"', $html,
            'La pantalla tendría que dibujar el ícono de ayuda del subtítulo.');
        $this->assertStringContainsString('data-bs-trigger="focus"', $html,
            'Sin el disparador `focus` el globo no se cierra al tocar afuera.');

        // El texto del subtítulo sigue estando, guardado en el globo.
        $this->assertMatchesRegularExpression(
            '/data-bs-content="[^"]*Citas del d[ií]a/u', $html,
            'El subtítulo se escondió y no quedó en el globo: se perdió, que es '
            . 'lo contrario de lo que hace este componente.'
        );
    }
}
