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
    @file_put_contents(SIM_LOG . '/ops.jsonl', json_encode($fila, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
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

    public function req(string $metodo, string $uri, array $datos = []): self
    {
        $this->reset();
        sim_reloj();

        $req = Request::create($uri, $metodo, $datos, $this->cookies, [], [
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

    public function entrar(string $usuario, string $pass, bool $forzar = true): bool
    {
        $this->cookies = [];
        $this->quien = $usuario;
        $this->get('/entrar');
        $this->post('/entrar', ['usuario' => $usuario, 'password' => $pass, 'forzar' => $forzar ? '1' : '0']);
        // La primera vez ofrece la huella: se contesta «ahora no»
        if ($this->location && str_contains($this->location, 'huella/activar')) {
            $this->post('/huella/preguntado');
        }
        $this->uid = (int) (DB::scalar('SELECT id_usuario FROM usuario WHERE username = ?', [$usuario]) ?: 0);

        $ok = $this->status === 302 && ! str_contains((string) $this->location, '/entrar');
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
