<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Revisión del entorno: contesta si esta instalación puede funcionar.
 *
 * Se corre en la PC de desarrollo y, sobre todo, en el servidor apenas se
 * despliega. Las tres cosas que rompen el sistema sin dar la cara son la zona
 * horaria (el fichaje queda corrido), los DEFINER de las rutinas (error 1449)
 * y que falte alguna de las rutinas de la base, que es donde vive la lógica.
 */
class Diagnostico extends Command
{
    protected $signature = 'spg:diagnostico {--produccion : Además, revisa lo que solo importa en el servidor}';

    protected $description = 'Revisa la conexión, la hora, las rutinas de la base y los permisos';

    /** Cuántas rutinas tiene que haber, según el esquema del TCC */
    private const ESPERADO = ['PROCEDURE' => 21, 'FUNCTION' => 36, 'trigger' => 17, 'vista' => 17];

    /**
     * Las restricciones CHECK, que un export de phpMyAdmin se come.
     *
     * Son **57** desde la 7.2.0, que sumó `chk_pref_tema`. Este número se
     * quedó atrás **dos veces ya**: en 54 cuando la 7.0.0 lo llevó a 56, y en
     * 56 cuando la 7.2.0 lo llevó a 57. Como la comparación es «menos que»,
     * quedarse corto no hace saltar nada — o sea que el desfase esconde
     * justamente lo que este número tendría que detectar. **Al agregar un
     * CHECK, actualizalo acá en la misma tanda.**
     */
    private const CHECKS = 77;

    public function handle(): int
    {
        $problemas = 0;

        $this->titulo('Conexión');
        try {
            $base = DB::selectOne('SELECT DATABASE() AS db, VERSION() AS v');
            $this->bien('Base «' . $base->db . '» sobre ' . $base->v);
            $this->linea('Driver de Laravel', config('database.default'));
        } catch (Throwable $e) {
            $this->mal('No se pudo conectar: ' . $e->getMessage());
            return self::FAILURE;
        }

        // ---- La hora -------------------------------------------------------
        // `ahora_bd()` le pregunta la hora a la base, y la base toma la del
        // sistema operativo. En un servidor en Miami eso es UTC.
        $this->titulo('La hora');
        $bd = (string) DB::scalar('SELECT NOW()');
        $php = date('Y-m-d H:i:s');
        $desfase = abs(strtotime($bd) - strtotime($php));
        // La que MANDA es `@@time_zone`, no `@@system_time_zone`. Mostrar la del
        // sistema confundía: en el contenedor da **-04**, porque la tzdata de
        // MariaDB 10.4 es anterior a que Paraguay dejara sin efecto el horario
        // de verano —la trampa que documenta CLAUDE.md—, mientras que la que
        // gobierna NOW() es la que fija el compose con --default-time-zone.
        // Se veía un -04 alarmante al lado de una hora perfectamente correcta.
        $zona = (string) DB::scalar('SELECT @@time_zone');
        if (strtoupper($zona) === 'SYSTEM') {
            $zona = 'SYSTEM → ' . DB::scalar('SELECT @@system_time_zone');
        }
        $this->linea('Reloj de la base', $bd . '  (zona: ' . $zona . ')');
        $this->linea('Reloj de PHP', $php . '  (zona: ' . config('app.timezone') . ')');
        if ($desfase > 90) {
            $this->mal('Los dos relojes no coinciden: ' . round($desfase / 60) . ' minutos de diferencia. '
                . 'El fichaje de asistencia va a quedar corrido. Fijá la zona horaria del servidor '
                . 'en America/Asuncion.');
            $problemas++;
        } else {
            $this->bien('Los dos relojes coinciden.');
        }

        // ---- Las rutinas: acá vive la lógica de negocio ---------------------
        $this->titulo('La lógica de la base');
        $conteo = [
            'PROCEDURE' => (int) DB::scalar("SELECT COUNT(*) FROM information_schema.routines
                                              WHERE routine_schema = DATABASE() AND routine_type = 'PROCEDURE'"),
            'FUNCTION' => (int) DB::scalar("SELECT COUNT(*) FROM information_schema.routines
                                             WHERE routine_schema = DATABASE() AND routine_type = 'FUNCTION'"),
            'trigger' => (int) DB::scalar('SELECT COUNT(*) FROM information_schema.triggers
                                            WHERE trigger_schema = DATABASE()'),
            'vista' => (int) DB::scalar("SELECT COUNT(*) FROM information_schema.views
                                          WHERE table_schema = DATABASE()"),
        ];
        foreach ($conteo as $que => $cuantos) {
            $esperado = self::ESPERADO[$que];
            $etiqueta = ['PROCEDURE' => 'Procedimientos', 'FUNCTION' => 'Funciones',
                         'trigger' => 'Disparadores', 'vista' => 'Vistas'][$que];
            if ($cuantos < $esperado) {
                $this->mal($etiqueta . ': ' . $cuantos . ' (se esperaban ' . $esperado . ')');
                $problemas++;
            } else {
                $this->bien($etiqueta . ': ' . $cuantos);
            }
        }

        // ---- ¿Los correos salen de verdad? ----------------------------------
        // El código de verificación, la recuperación de contraseña y el segundo
        // factor viajan SÓLO por correo. Con el driver en `log` no sale nada y
        // la pantalla igual dice «te enviamos un código»: parece roto y no lo
        // está. Se avisa acá porque es lo primero que se prueba al registrar
        // una clienta nueva.
        $this->titulo('El correo');
        $driver = (string) config('mail.default');
        if ($driver === 'log') {
            $this->linea('Driver', 'log — los correos NO se envían');
            $this->linea('Dónde queda el código', 'storage/logs/laravel.log');
            $this->linea('Para verlo', 'docker compose exec app tail -f storage/logs/laravel.log');
            $this->linea('Para que salga', 'poné MAIL_MAILER=smtp y las credenciales en docker/php/env.docker');
        } elseif ($driver === 'array') {
            $this->linea('Driver', 'array — los correos se descartan (es lo normal en las pruebas)');
        } else {
            $desde = (string) config('mail.from.address');
            $usuario = (string) config('mail.mailers.smtp.username');
            $this->bien('Driver: ' . $driver . ' por ' . config('mail.mailers.smtp.host'));
            if ($usuario === '') {
                $this->mal('Falta MAIL_USERNAME: el servidor va a rechazar el envío.');
                $problemas++;
            } elseif ($desde !== '' && $usuario !== '' && ! str_contains($desde, explode('@', $usuario)[1] ?? '@')) {
                // Gmail y la mayoría rechazan un From de otro dominio.
                $this->linea('Ojo', 'el remitente (' . $desde . ') no es del dominio de ' . $usuario);
            }
        }

        // ---- ¿La base coincide con el .sql que se entrega? ------------------
        $problemas += $this->revisarEsquema();

        // ---- ¿Las rutinas se pueden ejecutar de verdad? ---------------------
        // Si el DEFINER apunta a un usuario que no existe en este servidor,
        // MySQL contesta 1449 y el sistema entero deja de andar.
        $this->titulo('¿Responden las rutinas?');
        foreach ([
            'fn_producto_stock(1,1)' => 'stock de un producto',
            'fn_cliente_nivel(1)' => 'nivel de fidelización',
            'fn_verificar_disponibilidad(1, NOW(), 30, NULL, NULL)' => 'disponibilidad de la agenda',
        ] as $llamada => $para) {
            try {
                DB::scalar('SELECT ' . $llamada);
                $this->bien($para . ' — ' . strtok($llamada, '('));
            } catch (Throwable $e) {
                $this->mal($para . ' — ' . $e->getMessage());
                $problemas++;
            }
        }

        // Y las 56 restricciones CHECK, que un export de phpMyAdmin se come sin
        // avisar: la copia acepta valores que la base real rechaza.
        $checks = (int) DB::scalar("SELECT COUNT(*) FROM information_schema.table_constraints
                                     WHERE constraint_schema = DATABASE() AND constraint_type = 'CHECK'");
        if ($checks < self::CHECKS) {
            $this->mal('Restricciones CHECK: ' . $checks . ' (se esperaban ' . self::CHECKS
                . '). El .sql se exportó desde phpMyAdmin, que las pierde: regeneralo con mysqldump.');
            $problemas++;
        } else {
            $this->bien('Restricciones CHECK: ' . $checks);
        }

        // ---- DEFINER: el problema número uno al mudar de servidor ----------
        $this->titulo('Definidores de las rutinas');
        $definidores = DB::select("SELECT definer, COUNT(*) AS cuantas FROM information_schema.routines
                                    WHERE routine_schema = DATABASE() GROUP BY definer");
        $usuario = (string) DB::scalar('SELECT CURRENT_USER()');
        foreach ($definidores as $d) {
            // ¿Ese definidor existe en este servidor? Se pregunta a mysql.user
            // si se puede: en el VPS el usuario del grupo NO tiene permiso para
            // leer esa tabla, así que ahí se compara contra el usuario propio.
            $existe = $this->definidorExiste((string) $d->definer, $usuario);
            $detalle = $d->definer . ' — ' . $d->cuantas . ' rutina(s)';

            if ($existe === true) {
                $this->bien($detalle);
            } elseif ($existe === false) {
                $this->mal($detalle . ': ese usuario NO existe en este servidor. Todo lo que las llame '
                    . 'va a contestar error 1449. Preparalo antes de importar con: '
                    . 'php artisan spg:preparar-sql <archivo.sql> ' . strtok($usuario, '@'));
                $problemas++;
            } else {
                // Las llamadas de prueba de arriba ya pasaron, así que en la
                // práctica responden; solo no se pudo confirmar el usuario.
                $this->linea($detalle, 'no se pudo verificar (sin permiso sobre mysql.user)');
            }
        }
        $this->linea('Conectado como', $usuario);

        // ---- Que Laravel no haya ensuciado la base -------------------------
        $this->titulo('La base sigue limpia');
        $intrusas = DB::select("SELECT table_name AS t FROM information_schema.tables
                                 WHERE table_schema = DATABASE()
                                   AND table_name IN ('users','sessions','cache','cache_locks','jobs',
                                                      'job_batches','failed_jobs','migrations',
                                                      'password_reset_tokens','personal_access_tokens')");
        if ($intrusas) {
            $this->mal('Hay tablas de Laravel dentro de la base del TCC: '
                . implode(', ', array_column($intrusas, 't'))
                . '. Esa base es la que se entrega con el sistema y no puede llevarlas.');
            $problemas++;
        } else {
            $this->bien('Ninguna tabla de Laravel dentro de peluqueria_bd.');
        }

        if ($this->option('produccion')) {
            $problemas += $this->revisarProduccion();
        }

        $this->newLine();
        if ($problemas === 0) {
            $this->info('  Todo en orden.');
            // **Sano no es lo mismo que configurado.** Este comando contesta si
            // el sistema está bien; qué le falta cargar al salón lo contesta
            // otro, y sin nombrarlo acá nadie se entera de que existe.
            $this->line('  <fg=gray>Qué le falta cargar al salón: php artisan spg:pendientes</>');

            return self::SUCCESS;
        }
        $this->warn('  ' . $problemas . ' cosa(s) para revisar antes de seguir.');

        return self::FAILURE;
    }

    /**
     * ¿El definidor de las rutinas existe en este servidor?
     *
     * true / false, o null cuando no se pudo averiguar: leer `mysql.user` pide
     * un permiso que el usuario de un hosting no tiene, y eso no es un fallo
     * del sistema — es lo normal en el VPS compartido.
     */
    /**
     * ¿La base que está conectada coincide con el `.sql` que se entrega?
     *
     * Es la comprobación que más falta hacía, y la escribió un caso real: una
     * computadora que ya tenía las bases importadas de antes levantó el
     * proyecto actualizado y **el ingreso murió con un 500** —«Columna
     * desconocida 'tema'»— porque el volumen de MariaDB ya tenía datos y el
     * guion de importación **corre una sola vez, cuando está vacío**. El
     * código estaba al día y la base no, y nada lo decía hasta que alguien
     * abría la pantalla que usaba la columna nueva.
     *
     * Se comparan las columnas declaradas en el `.sql` contra las que hay de
     * verdad. Sobrar no es problema —una base de trabajo puede tener cosas de
     * más—; lo que rompe es que FALTE.
     */
    private function revisarEsquema(): int
    {
        $this->titulo('¿La base coincide con el .sql que se entrega?');

        $archivo = base_path('basededatos/peluqueria_bd(base).sql');
        if (! is_file($archivo)) {
            $this->linea('No se pudo comparar', 'falta ' . $archivo);

            return 0;
        }

        $esperadas = $this->columnasDelSql((string) file_get_contents($archivo));
        if (! $esperadas) {
            $this->linea('No se pudo comparar', 'no se leyó ninguna tabla del .sql');

            return 0;
        }

        $reales = [];
        foreach (DB::select('SELECT table_name AS t, column_name AS c FROM information_schema.columns
                              WHERE table_schema = DATABASE()') as $f) {
            $reales[strtolower($f->t)][strtolower($f->c)] = true;
        }

        $tablasQueFaltan = [];
        $columnasQueFaltan = [];
        foreach ($esperadas as $tabla => $columnas) {
            if (! isset($reales[$tabla])) {
                $tablasQueFaltan[] = $tabla;

                continue;
            }
            foreach ($columnas as $col) {
                if (! isset($reales[$tabla][$col])) {
                    $columnasQueFaltan[] = $tabla . '.' . $col;
                }
            }
        }

        if (! $tablasQueFaltan && ! $columnasQueFaltan) {
            $this->bien('Las ' . count($esperadas) . ' tablas del .sql están completas.');

            return 0;
        }

        foreach ($tablasQueFaltan as $t) {
            $this->mal('Falta la tabla ' . $t);
        }
        foreach (array_slice($columnasQueFaltan, 0, 12) as $c) {
            $this->mal('Falta la columna ' . $c);
        }
        if (count($columnasQueFaltan) > 12) {
            $this->linea('', 'y ' . (count($columnasQueFaltan) - 12) . ' columna(s) más');
        }

        $this->newLine();
        $this->linea('Qué pasó', 'la base es más vieja que el código: quedó de una importación anterior.');
        $this->linea('En Docker', 'docker compose down -v && docker compose up');
        $this->linea('', '(el -v borra el volumen; sin él, MariaDB NO vuelve a importar)');
        $this->linea('A mano', 'recargá la base con basededatos/peluqueria_bd(base).sql');

        return 1;
    }

    /**
     * Las columnas de cada `CREATE TABLE` del volcado.
     *
     * Se leen sólo las líneas que empiezan con un nombre entre acentos graves,
     * que en un `mysqldump` son exactamente las columnas: los índices y las
     * claves foráneas arrancan con PRIMARY/UNIQUE/KEY/CONSTRAINT.
     *
     * @return array<string, list<string>>
     */
    private function columnasDelSql(string $sql): array
    {
        $out = [];
        $tabla = null;
        foreach (explode("\n", $sql) as $linea) {
            if (preg_match('/^CREATE TABLE `([^`]+)`/', $linea, $m)) {
                $tabla = strtolower($m[1]);
                $out[$tabla] = [];

                continue;
            }
            if ($tabla === null) {
                continue;
            }
            if (str_starts_with($linea, ')')) {
                $tabla = null;

                continue;
            }
            if (preg_match('/^\s+`([^`]+)`\s+\S/', $linea, $m)) {
                $out[$tabla][] = strtolower($m[1]);
            }
        }

        return array_filter($out);
    }

    private function definidorExiste(string $definer, string $usuarioActual): ?bool
    {
        try {
            return (bool) DB::scalar(
                'SELECT COUNT(*) FROM mysql.user WHERE CONCAT(user, ?, host) = ?', ['@', $definer]
            );
        } catch (Throwable) {
            // Sin acceso a mysql.user: al menos, si el definidor es uno mismo,
            // seguro que existe.
            return $definer === $usuarioActual ? true : null;
        }
    }

    /**
     * Lo que solo se puede revisar (y solo importa) en el servidor.
     *
     * Son las cosas que en desarrollo están mal a propósito —APP_DEBUG en true,
     * APP_URL apuntando a localhost— y que en producción son un problema real:
     * un enlace de correo que no abre, una traza de error con la contraseña de
     * la base adentro, un .env descargable por HTTP.
     */
    private function revisarProduccion(): int
    {
        $problemas = 0;

        $this->titulo('Servidor: configuración');

        if (config('app.debug')) {
            $this->mal('APP_DEBUG está en true. Cualquier error le muestra al visitante la traza '
                . 'completa, con la contraseña de la base adentro. Poné APP_DEBUG=false.');
            $problemas++;
        } else {
            $this->bien('APP_DEBUG=false.');
        }

        if (config('app.env') !== 'production') {
            $this->mal('APP_ENV=' . config('app.env') . ', debería ser «production».');
            $problemas++;
        } else {
            $this->bien('APP_ENV=production.');
        }

        $url = (string) config('app.url');
        if ($url === '' || str_contains($url, 'localhost') || str_contains($url, '127.0.0.1')) {
            $this->mal('APP_URL es «' . $url . '». De ahí salen los enlaces de los correos '
                . '(reprogramar, cancelar, agregar al calendario): con este valor le llegan al '
                . 'cliente apuntando a su propia computadora. Poné el subdominio real.');
            $problemas++;
        } elseif (! str_starts_with($url, 'https://')) {
            $this->mal('APP_URL no usa https. WebAuthn (el ingreso con huella) no funciona sin HTTPS.');
            $problemas++;
        } else {
            $this->bien('APP_URL: ' . $url);
        }

        if (! config('app.key')) {
            $this->mal('Falta APP_KEY: corré «php artisan key:generate».');
            $problemas++;
        } else {
            $this->bien('APP_KEY cargada.');
        }

        // La zona horaria, otra vez pero explícita: en el VPS de Miami el
        // sistema operativo arranca en UTC y nadie lo nota hasta ver un fichaje
        // marcado a las 12 de la noche.
        $zonaBd = (string) DB::scalar('SELECT @@system_time_zone');
        if (in_array(strtoupper($zonaBd), ['UTC', 'GMT'], true)) {
            $this->mal('La base corre en ' . $zonaBd . '. Fijá la zona del servidor con '
                . '«timedatectl set-timezone America/Asuncion» y reiniciá MySQL, o el fichaje de '
                . 'asistencia queda corrido 3 o 4 horas.');
            $problemas++;
        } else {
            $this->bien('Zona horaria de la base: ' . $zonaBd);
        }

        // ---- Que el .env no sea descargable --------------------------------
        $this->titulo('Servidor: qué queda expuesto');
        $publico = realpath(public_path()) ?: public_path();
        $raiz = realpath(base_path()) ?: base_path();
        $this->linea('La carpeta pública tiene que ser', $publico);
        $this->linea('NO la raíz del proyecto', $raiz);

        $expuestos = 0;
        foreach (['.env', 'composer.json', 'artisan'] as $archivo) {
            if (file_exists($publico . DIRECTORY_SEPARATOR . $archivo)) {
                $this->mal('Hay un ' . $archivo . ' dentro de public/. Sacalo: todo lo que está ahí '
                    . 'se descarga por HTTP.');
                $expuestos++;
            }
        }
        foreach (glob($publico . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $sql) {
            $this->mal('Hay un .sql dentro de public/ (' . basename($sql) . '): le entrega el esquema '
                . 'completo de la base a cualquiera que pida la URL.');
            $expuestos++;
        }
        if ($expuestos === 0) {
            $this->bien('No hay archivos sensibles dentro de public/.');
        }
        $problemas += $expuestos;

        // ---- Caché de producción -------------------------------------------
        $this->titulo('Servidor: rendimiento');
        $cacheados = ['configuración' => base_path('bootstrap/cache/config.php'),
                      'rutas' => base_path('bootstrap/cache/routes-v7.php')];
        $faltan = [];
        foreach ($cacheados as $que => $ruta) {
            file_exists($ruta) ? $this->bien(ucfirst($que) . ' en caché.') : $faltan[] = $que;
        }
        if ($faltan) {
            $this->mal('Sin cachear: ' . implode(' y ', $faltan) . '. Corré «php artisan optimize». '
                . 'En un VPS de 4 GB compartido entre varios grupos, no es un lujo.');
            $problemas++;
        }

        // ---- El correo y la cola -------------------------------------------
        $this->titulo('Servidor: correo y tareas');
        $host = (string) config('mail.mailers.smtp.host');
        $puerto = (int) config('mail.mailers.smtp.port');
        if (config('mail.default') === 'log' || $host === '') {
            $this->mal('El correo está en «' . config('mail.default') . '»: no sale nada. Por ahí van '
                . 'el código de verificación, la recuperación de contraseña, el segundo factor y los '
                . 'recordatorios. Configurá SMTP.');
            $problemas++;
        } else {
            $this->bien('SMTP: ' . $host . ':' . $puerto);
            if ($puerto !== 587 && $puerto !== 465) {
                $this->linea('Ojo', 'el puerto habitual es el 587; confirmá que el proveedor no lo bloquee');
            }
        }

        // El scheduler es lo que despacha la cola de avisos. Sin el cron del
        // panel, los correos salen solo cuando alguien entra al sistema.
        $pendientes = (int) DB::scalar("SELECT COUNT(*) FROM notificacion WHERE estado = 'PENDIENTE'");
        $ultima = DB::scalar("SELECT MAX(fecha_envio) FROM notificacion WHERE estado = 'ENVIADA'");
        $this->linea('Avisos pendientes', (string) $pendientes);
        $this->linea('Último despachado', $ultima ? (string) $ultima : 'nunca');
        $this->linea('El cron del panel tiene que correr',
            '* * * * * cd ' . $raiz . ' && php artisan schedule:run >> /dev/null 2>&1');

        return $problemas;
    }

    private function titulo(string $t): void
    {
        $this->newLine();
        $this->line('  <fg=yellow>' . mb_strtoupper($t) . '</>');
    }

    private function bien(string $t): void
    {
        $this->line('  <fg=green>OK</>   ' . $t);
    }

    private function mal(string $t): void
    {
        $this->line('  <fg=red>FALLA</> ' . $t);
    }

    private function linea(string $k, string $v): void
    {
        $this->line('       ' . $k . ': <fg=gray>' . $v . '</>');
    }
}
