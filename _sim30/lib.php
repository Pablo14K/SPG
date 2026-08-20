<?php
/**
 * Harness de simulación operativa. NO forma parte del sistema.
 * Arranca Laravel apuntando a `peluqueria_sim` y maneja peticiones HTTP reales
 * a través del kernel, con frasco de cookies (sesión de verdad).
 */

declare(strict_types=1);

define('SIM_ROOT', dirname(__DIR__));
define('SIM_LOG', __DIR__ . '/log');

require SIM_ROOT . '/vendor/autoload.php';

$app = require SIM_ROOT . '/bootstrap/app.php';

// **El almacenamiento se muda a disco del contenedor, fuera del bind mount.**
//
// Laravel guarda las sesiones con `flock`, y `/app` viene montado desde una
// carpeta de Windows: ahí el candado no es fiable. Con dos procesos de
// concurrencia escribiendo su sesión a la vez, uno se quedaba con el candado y
// los demás dormían para siempre — la simulación se detenía en seco, sin error
// y sin aviso, siempre en el mismo escenario.
//
// Es una decisión DEL BANCO DE PRUEBAS, no del sistema: no se toca ni una línea
// de la aplicación, sólo se le dice dónde escribir sus archivos temporales.
$app->useStoragePath('/tmp/spg-sim-storage');
$GLOBALS['sim_app'] = $app;
$GLOBALS['sim_kernel'] = $app->make(Illuminate\Contracts\Http\Kernel::class);
$GLOBALS['sim_kernel']->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Sincroniza el reloj de MariaDB con el reloj (falseado) de PHP. */
function sim_reloj(): void
{
    try {
        DB::unprepared('SET timestamp = ' . time());
    } catch (Throwable $e) {
        // reintento tras reconexión
        DB::reconnect();
        DB::unprepared('SET timestamp = ' . time());
    }
}

function sim_log(array $fila): void
{
    $fila['t'] = date('Y-m-d H:i:s');

    // **Un archivo por proceso, sin `LOCK_EX`.** El log vive en un bind mount
    // de Windows, y ahí `flock` no es fiable: con los procesos de concurrencia
    // escribiendo a la vez, uno se quedaba con el candado y los demás dormían
    // para siempre — la simulación se detenía en seco, sin error y sin aviso.
    // Los archivos se juntan al final, que da exactamente lo mismo.
    static $archivo = null;
    $archivo ??= SIM_LOG . '/ops-' . getmypid() . '.jsonl';
    @file_put_contents($archivo, json_encode($fila, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
}

/** Un incidente: algo que no cuadra y va al informe. */
function sim_incidente(string $codigo, string $detalle, string $sev = 'ALTO', array $extra = []): void
{
    sim_log(['tipo' => 'INCIDENTE', 'cod' => $codigo, 'sev' => $sev, 'det' => $detalle] + $extra);
}

class Nav
{
    public array $cookies = [];
    public int $status = 0;
    public string $body = '';
    public ?string $location = null;
    public array $flash = [];
    public string $quien = '-';
    public ?int $uid = null;

    private function reset(): void
    {
        App\Servicios\Caja::olvidar();
        App\Servicios\Permisos::olvidar();
    }

    /**
     * Sube un archivo con el POST.
     *
     * **Sin esto, media pantalla queda sin cobertura y no se nota.** El gasto de
     * caja y el retiro exigen adjuntar el comprobante desde la 7.47.0, y la seña
     * de la clienta lo acepta desde la 7.45.0: sin poder subir un archivo, la
     * simulación de 30 días registró **cero** movimientos de efectivo y no
     * ejercitó ni una devolución. Un hueco de cobertura esconde defectos, no
     * ausencia de defectos.
     */
    public function postConArchivo(string $uri, array $datos, string $campo, string $tipo = 'jpg'): self
    {
        $ruta = sys_get_temp_dir() . '/sim-' . uniqid() . '.' . $tipo;
        // Un JPEG mínimo de verdad: el sistema mira el CONTENIDO con
        // `getimagesize`, no la extensión, así que un archivo de mentira lo
        // rechazaría y la prueba mediría la validación en vez de la operación.
        if ($tipo === 'pdf') {
            file_put_contents($ruta, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF");
        } else {
            // JPEG de 1x1 escrito a mano: el contenedor no trae GD, y de todos
            // modos lo que importa es que `getimagesize` lo reconozca, que es con
            // lo que el sistema valida — y `getimagesize` no depende de GD.
            file_put_contents($ruta, base64_decode(
                '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRof'
                . 'Hh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAAB'
                . 'AAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q=='
            ));
        }

        $archivo = new \Illuminate\Http\UploadedFile(
            $ruta, basename($ruta), $tipo === 'pdf' ? 'application/pdf' : 'image/jpeg', null, true
        );

        $r = $this->req('POST', $uri, $datos, [$campo => $archivo]);
        @unlink($ruta);

        return $r;
    }

    public function req(string $metodo, string $uri, array $datos = [], array $archivos = []): self
    {
        $this->reset();
        sim_reloj();

        $req = Request::create($uri, $metodo, $datos, $this->cookies, $archivos, [
            'HTTP_HOST' => 'localhost',
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'SPG-Simulador',
        ]);

        try {
            $res = $GLOBALS['sim_kernel']->handle($req);
        } catch (Throwable $e) {
            $this->status = 599;
            $this->body = get_class($e) . ': ' . $e->getMessage();
            $this->flash = [];
            sim_incidente('EXCEPCION_NO_CAPTURADA', $metodo . ' ' . $uri . ' → ' . $this->body, 'CRITICO');

            return $this;
        }

        $this->status = $res->getStatusCode();
        $this->location = $res->headers->get('Location');
        $this->body = $res->getContent() === false ? '' : (string) $res->getContent();

        foreach ($res->headers->getCookies() as $c) {
            if ($c->getValue() === '' || $c->getValue() === null) {
                unset($this->cookies[$c->getName()]);
            } else {
                $this->cookies[$c->getName()] = $c->getValue();
            }
        }

        // Los avisos que el sistema le deja a la próxima pantalla
        $this->flash = [];
        try {
            $s = $req->hasSession() ? $req->session() : null;
            if ($s) {
                foreach ((array) $s->get('spg_flash', []) as $f) {
                    $this->flash[] = ($f['tipo'] ?? '?') . ': ' . ($f['msg'] ?? '');
                }
                foreach ((array) $s->get('errors', []) as $e) {
                    // MessageBag
                }
                $errs = $s->get('errors');
                if ($errs && method_exists($errs, 'all')) {
                    foreach ($errs->all() as $m) {
                        $this->flash[] = 'validacion: ' . $m;
                    }
                }
            }
        } catch (Throwable) {
        }

        if ($this->status >= 500) {
            sim_incidente('HTTP_' . $this->status, $this->quien . ' ' . $metodo . ' ' . $uri . ' → ' . substr(strip_tags($this->body), 0, 400), 'CRITICO');
        }

        sim_log(['tipo' => 'REQ', 'quien' => $this->quien, 'm' => $metodo, 'uri' => $uri,
                 'st' => $this->status, 'flash' => $this->flash]);

        return $this;
    }

    public function get(string $uri, array $q = []): self
    {
        return $this->req('GET', $uri . ($q ? (str_contains($uri, '?') ? '&' : '?') . http_build_query($q) : ''));
    }

    public function post(string $uri, array $d = []): self
    {
        return $this->req('POST', $uri, $d);
    }

    /** Sigue el redirect (una vez) para dibujar la pantalla destino. */
    public function seguir(): self
    {
        if ($this->location) {
            $u = parse_url($this->location);
            $uri = ($u['path'] ?? '/') . (isset($u['query']) ? '?' . $u['query'] : '');
            $flash = $this->flash;
            $this->get($uri);
            $this->flash = array_merge($flash, $this->flash);
        }

        return $this;
    }

    public function dice(string $frag): bool
    {
        foreach ($this->flash as $f) {
            if (stripos($f, $frag) !== false) {
                return true;
            }
        }

        return stripos($this->body, $frag) !== false;
    }

    public function flashTxt(): string
    {
        return implode(' | ', $this->flash);
    }

    /** En qué sucursal quedó trabajando esta sesión. */
    public ?int $sucursal = null;

    /**
     * Entra y, si la cuenta llega a más de un local, elige.
     *
     * **Con una sola sucursal el sistema la resuelve solo** —preguntar algo de
     * una única respuesta hace perder un clic— y este método no hace nada
     * distinto de antes. Con dos o más, `ExigePersonal` manda a `/sucursal`
     * antes de dejar ver nada, así que hay que contestar: sin eso, cada
     * petición del día contestaría 302 y la simulación mediría el redirect en
     * vez del sistema.
     */
    public function entrar(string $usuario, string $pass, bool $forzar = true, ?int $sucursal = null): bool
    {
        $this->cookies = [];
        $this->quien = $usuario;
        $this->sucursal = null;
        $this->get('/entrar');
        $this->post('/entrar', ['usuario' => $usuario, 'password' => $pass, 'forzar' => $forzar ? '1' : '0']);
        // La primera vez ofrece la huella: se contesta «ahora no»
        if ($this->location && str_contains($this->location, 'huella/activar')) {
            $this->post('/huella/preguntado');
        }
        $this->uid = (int) (DB::scalar('SELECT id_usuario FROM usuario WHERE username = ?', [$usuario]) ?: 0);

        $ok = $this->status === 302 && ! str_contains((string) $this->location, '/entrar');

        // La elección de local, cuando corresponde.
        if ($ok && str_contains((string) $this->location, '/sucursal')) {
            $elegida = $sucursal ?: (int) (DB::scalar(
                'SELECT COALESCE((SELECT us.id_sucursal FROM usuario_sucursal us
                                   JOIN sucursal s ON s.id_sucursal = us.id_sucursal AND s.activo = 1
                                  WHERE us.id_usuario = ? ORDER BY us.id_sucursal LIMIT 1),
                                 (SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1))', [$this->uid]) ?: 1);

            $this->post('/sucursal', ['id_sucursal' => $elegida]);
            $ok = $this->status === 302 && ! str_contains((string) $this->location, '/sucursal');
            if (! $ok) {
                sim_incidente('SUCURSAL_NO_ELEGIDA',
                    "$usuario no pudo entrar a la sucursal $elegida: " . $this->flashTxt(), 'ALTO');
            }
        }

        // Sea por elección o porque el sistema la resolvió solo, se anota cuál
        // quedó: es contra esto que se comprueba el aislamiento.
        if ($ok) {
            $this->get('/panel');
            $this->sucursal = $sucursal ?: null;
        }

        if (! $ok) {
            sim_incidente('LOGIN_FALLIDO', "No pudo entrar $usuario: " . $this->flashTxt(), 'ALTO');
        }

        return $ok;
    }

    public function salir(): void
    {
        $this->post('/salir');
        $this->cookies = [];
        $this->quien = '-';
        $this->uid = null;
    }
}

/** Comprobación de invariante: registra si falla. */
function sim_check(bool $cond, string $codigo, string $detalle, string $sev = 'ALTO'): bool
{
    if (! $cond) {
        sim_incidente($codigo, $detalle, $sev);
    } else {
        sim_log(['tipo' => 'CHECK_OK', 'cod' => $codigo]);
    }

    return $cond;
}

function sim_esperado(Nav $n, string $frag, string $codigo, string $queSeEsperaba, string $sev = 'ALTO'): bool
{
    $ok = $n->dice($frag);
    if (! $ok) {
        sim_incidente($codigo, $queSeEsperaba . ' — el sistema contestó: ' . ($n->flashTxt() ?: ('HTTP ' . $n->status)), $sev);
    }

    return $ok;
}

sim_reloj();
