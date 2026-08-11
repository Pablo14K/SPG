<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Deja el .sql listo para importarlo en el servidor.
 *
 * Es el paso que más veces rompe un despliegue, y rompe callado: las 50
 * rutinas, los 17 disparadores y las 17 vistas del TCC se crearon con
 * `DEFINER=`root`@`localhost``, pero en el VPS no entramos como root — cada
 * grupo tiene su propio usuario. Importado así, MySQL contesta **error 1449**
 * la primera vez que algo llame a una función… es decir, en la pantalla de
 * ingreso, y el sistema entero deja de andar.
 *
 * Este comando no toca el original: escribe una copia con el definidor
 * cambiado, y avisa cuántos reemplazó.
 *
 *   php artisan spg:preparar-sql "Referencias/peluqueria_bd(base).sql" spg_user
 */
class PrepararSql extends Command
{
    protected $signature = 'spg:preparar-sql
                            {archivo : El .sql a preparar}
                            {usuario : El usuario de MySQL del servidor (sin el @host)}
                            {--host=% : El host del usuario; % sirve para cualquiera}
                            {--salida= : Dónde escribir la copia (por defecto, junto al original)}';

    protected $description = 'Reemplaza los DEFINER del .sql por el usuario del servidor';

    public function handle(): int
    {
        $archivo = (string) $this->argument('archivo');
        if (! is_file($archivo)) {
            $this->error('No encuentro el archivo: ' . $archivo);

            return self::FAILURE;
        }

        $usuario = (string) $this->argument('usuario');
        $host = (string) $this->option('host');
        $nuevo = '`' . $usuario . '`@`' . $host . '`';

        $sql = (string) file_get_contents($archivo);

        // El definidor aparece como DEFINER=`root`@`localhost`, con o sin
        // comillas invertidas según quién haya generado el volcado.
        $sql = preg_replace(
            '/DEFINER\s*=\s*`?[^`@\s]+`?@`?[^`\s]+`?/i',
            'DEFINER=' . $nuevo,
            $sql,
            -1,
            $cuantos
        );

        // SQL SECURITY DEFINER hace que la rutina corra con los permisos de
        // quien la creó. En un servidor donde el usuario del grupo es el único
        // dueño, INVOKER es equivalente y no depende de que el definidor exista.
        $sql = str_replace('SQL SECURITY DEFINER', 'SQL SECURITY INVOKER', (string) $sql, $seguridad);

        $salida = (string) ($this->option('salida')
            ?: preg_replace('/\.sql$/i', '', $archivo) . '_servidor.sql');
        file_put_contents($salida, $sql);

        $this->newLine();
        $this->info('  Listo.');
        $this->line('  <fg=gray>Definidores reemplazados:</> ' . $cuantos . '  →  ' . $nuevo);
        $this->line('  <fg=gray>SQL SECURITY DEFINER → INVOKER:</> ' . $seguridad);
        $this->line('  <fg=gray>Archivo:</> ' . $salida);
        $this->newLine();

        $this->line('  <fg=yellow>ANTES DE IMPORTAR</>, el usuario ' . $usuario . ' necesita permiso para');
        $this->line('  crear rutinas, o las funciones no se van a crear y no se va a notar hasta usarlas:');
        $this->newLine();
        $this->line('    <fg=green>GRANT CREATE ROUTINE, ALTER ROUTINE, TRIGGER, EXECUTE ON peluqueria_bd.* TO \''
            . $usuario . '\'@\'' . $host . '\';</>');
        $this->line('    <fg=green>SET GLOBAL log_bin_trust_function_creators = 1;</>   <fg=gray>(desde root, si hay binlog)</>');
        $this->newLine();
        $this->line('  <fg=gray>Después de importar, comprobalo con:</> php artisan spg:diagnostico --produccion');

        return self::SUCCESS;
    }
}
