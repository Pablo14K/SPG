<?php
/**
 * Banco de pruebas adversarias. NO forma parte del sistema.
 *
 * Arranca Laravel contra `peluqueria_qa` —una copia, para no tocar la base de
 * trabajo— y manda peticiones HTTP reales a través del kernel, con frasco de
 * cookies. La idea no es comprobar que lo normal funciona: eso ya lo hacen las
 * 123 pruebas. Acá se prueba lo que NADIE hace a propósito.
 */

declare(strict_types=1);

define('QA_ROOT', dirname(__DIR__));

require QA_ROOT . '/vendor/autoload.php';

$app = require QA_ROOT . '/bootstrap/app.php';
$app->useStoragePath('/tmp/spg-qa-storage');

foreach (['framework/views', 'framework/cache', 'framework/sessions', 'logs', 'app'] as $d) {
    @mkdir('/tmp/spg-qa-storage/' . $d, 0777, true);
}

$GLOBALS['qa_app'] = $app;
$GLOBALS['qa_kernel'] = $app->make(Illuminate\Contracts\Http\Kernel::class);
$GLOBALS['qa_kernel']->bootstrap();

// **Contra la copia, no contra la base de trabajo.**
config(['database.connections.mariadb.database' => 'peluqueria_qa']);
Illuminate\Support\Facades\DB::purge('mariadb');

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$GLOBALS['qa_hallazgos'] = [];

/** Un hallazgo: algo que no cuadra. */
function hallazgo(string $sev, string $caso, string $detalle): void
{
    $GLOBALS['qa_hallazgos'][] = ['sev' => $sev, 'caso' => $caso, 'det' => $detalle];
    printf("  [%-8s] %-42s %s\n", $sev, $caso, $detalle);
}

function ok(string $caso, string $detalle = ''): void
{
    printf("  [%-8s] %-42s %s\n", 'ok', $caso, $detalle);
}

class Nav
{
    private array $cookies = [];

    public ?Symfony\Component\HttpFoundation\Response $res = null;

    public function req(string $metodo, string $uri, array $datos = []): self
    {
        // **Cada petición arranca con los cachés limpios.** El banco corre
        // todas en un solo proceso PHP, así que los `static` del sistema
        // —la caja abierta, la matriz de permisos, la configuración— viven
        // entre una y otra, cosa que en el servidor NO pasa: ahí cada
        // petición es un proceso nuevo. Sin esto el banco mide su propia
        // caché y no el sistema.
        App\Servicios\Caja::olvidar();
        App\Servicios\Permisos::olvidar();
        App\Servicios\Config::olvidar();

        $req = Request::create($uri, $metodo, $datos, $this->cookies, [], ['HTTPS' => 'off']);
        $this->res = $GLOBALS['qa_kernel']->handle($req);
        foreach ($this->res->headers->getCookies() as $c) {
            $this->cookies[$c->getName()] = $c->getValue();
        }

        return $this;
    }

    public function get(string $uri, array $q = []): self
    {
        return $this->req('GET', $uri . ($q ? '?' . http_build_query($q) : ''));
    }

    public function post(string $uri, array $d = []): self
    {
        $d['_token'] = app('session')->token();

        return $this->req('POST', $uri, $d);
    }

    public function codigo(): int
    {
        return $this->res?->getStatusCode() ?? 0;
    }

    public function html(): string
    {
        return (string) $this->res?->getContent();
    }

    /** El aviso que dejó el sistema, sin HTML. */
    public function aviso(): string
    {
        $f = app('session')->get('flash');
        if (is_array($f)) {
            return trim(strip_tags((string) ($f['msg'] ?? $f[0] ?? '')));
        }

        return trim(strip_tags((string) $f));
    }

    /**
     * Ingresa y **comprueba que la sesión sea de esa cuenta**.
     *
     * Sin esa comprobación el banco miente: como todas las peticiones corren
     * en un proceso, si el ingreso falla la sesión anterior sigue en pie y las
     * pantallas se abren igual — con el rol de la cuenta anterior. Así el
     * Profesional «entraba» a Timbrados, que en realidad estaba abriendo el
     * admin de la prueba de antes.
     */
    public function entrar(string $usuario, string $clave, ?int $sucursal = null): bool
    {
        $this->cookies = [];   // frasco limpio: sesión nueva de verdad
        $this->get('/entrar');
        $this->post('/entrar', ['usuario' => $usuario, 'password' => $clave, 'forzar' => 1]);

        $esperado = (int) DB::scalar('SELECT id_usuario FROM usuario WHERE username = ?', [$usuario]);
        if ((int) app('session')->get('uid') !== $esperado) {
            return false;
        }

        // La clienta no elige sucursal ni entra al panel: su casa es el portal.
        $esCliente = (int) DB::scalar(
            'SELECT r.id_rol FROM usuario u JOIN rol r ON r.id_rol = u.id_rol WHERE u.username = ?', [$usuario]
        ) === (int) config('permisos.rol_cliente', 4);

        if ($esCliente) {
            return $this->get('/portal')->codigo() === 200;
        }

        $this->post('/sucursal',
            ['id_sucursal' => $sucursal ?? (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1')]);

        return $this->get('/panel')->codigo() === 200;
    }
}

/** ¿La respuesta reventó? 500 es siempre un hallazgo. */
function revisar(Nav $n, string $caso, array $esperados = [200, 302]): bool
{
    $c = $n->codigo();
    if ($c >= 500) {
        hallazgo('CRITICO', $caso, 'HTTP ' . $c . ' — el sistema se rompió');

        return false;
    }
    if (! in_array($c, $esperados, true) && $c !== 403 && $c !== 404 && $c !== 419) {
        hallazgo('MEDIO', $caso, 'HTTP ' . $c . ' inesperado');

        return false;
    }

    return true;
}
