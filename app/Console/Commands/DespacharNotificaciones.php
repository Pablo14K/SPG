<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Servicios\Notificaciones;
use Illuminate\Console\Command;

/**
 * Genera los recordatorios pendientes y despacha la cola de avisos.
 *
 * Lo dispara el programador de tareas (ver routes/console.php), NO una visita
 * al sistema. En la versión anterior el envío se disparaba cuando alguien
 * entraba, así que un domingo con el salón cerrado no salía ningún recordatorio
 * —justo el día en que más falta hace avisar la cita del lunes—.
 *
 * En el servidor lo llama el cron del panel. **No hay que dejar un worker
 * corriendo**: los 4 GB de RAM del VPS se comparten con otros grupos.
 */
class DespacharNotificaciones extends Command
{
    protected $signature = 'spg:notificaciones {--max=25 : Cuántos correos como mucho en esta pasada}';

    protected $description = 'Genera recordatorios y manda los avisos pendientes a las clientas';

    public function handle(): int
    {
        $nuevos = Notificaciones::generarRecordatorios();
        $cerrados = Notificaciones::cerrarInternas();
        $r = Notificaciones::despachar((int) $this->option('max'));

        $this->line("  recordatorios nuevos: $nuevos");
        $this->line("  avisos internos cerrados: $cerrados");
        $this->line("  enviados: {$r['enviadas']} · fallidos: {$r['fallidas']} · sin correo: {$r['sin_correo']}");

        // Un fallo de envío no es un fallo del comando: la fila queda en
        // PENDIENTE y se reintenta en la próxima pasada. Si devolviera error, el
        // cron del panel mandaría un correo de alerta por cada red intermitente.
        return self::SUCCESS;
    }
}
