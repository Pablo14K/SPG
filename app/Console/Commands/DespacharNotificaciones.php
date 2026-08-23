<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Servicios\Notificaciones;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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

    /**
     * Cuánto se tolera antes de marcar una cita como atrasada.
     *
     * No es cero: una clienta que llega cinco minutos tarde no está atrasada,
     * está llegando. Media hora sin que nadie la ponga En proceso ya es otra
     * cosa — o no vino, o el salón se olvidó de marcarla.
     */
    private const GRACIA_MIN = 30;

    public function handle(): int
    {
        $atrasadas = $this->marcarAtrasadas();
        $nuevos = Notificaciones::generarRecordatorios();
        $cerrados = Notificaciones::cerrarInternas();
        // Las reservas que pedían seña y nadie confirmó a tiempo: el horario se
        // les guardó, pero no para siempre.
        $sinSena = Notificaciones::cancelarSenasVencidas();
        $r = Notificaciones::despachar((int) $this->option('max'));

        $this->line("  citas marcadas atrasadas: $atrasadas");
        $this->line("  recordatorios nuevos: $nuevos");
        $this->line("  avisos internos cerrados: $cerrados");
        $this->line("  reservas soltadas por seña sin confirmar: $sinSena");
        $this->line("  enviados: {$r['enviadas']} · fallidos: {$r['fallidas']} · sin correo: {$r['sin_correo']}");
        if ($r['sin_destinatario'] > 0) {
            // Los que este despachador no iba a tomar nunca y quedaban en
            // PENDIENTE para siempre, ensuciando la cola (NO-02).
            $this->line("  cerrados por no tener destinatario: {$r['sin_destinatario']}");
        }

        // Un fallo de envío no es un fallo del comando: la fila queda en
        // PENDIENTE y se reintenta en la próxima pasada. Si devolviera error, el
        // cron del panel mandaría un correo de alerta por cada red intermitente.
        return self::SUCCESS;
    }

    /**
     * Pasa a «Atrasada» la cita cuya hora ya pasó y que nadie puso En proceso.
     *
     * **La asistencia no es automática, y ése es el punto.** El sistema no
     * puede saber si la clienta llegó: eso lo sabe quien atiende, y lo dice
     * apretando «En proceso». Lo que sí puede saber el sistema es que la hora
     * pasó y nadie tocó nada, y eso no es lo mismo que «ausente» —marcarla
     * ausente sola sería inventar un hecho—. Atrasada es exactamente lo que
     * consta: se hizo la hora y la cita sigue como estaba.
     *
     * Sigue bloqueando la agenda, porque el sillón sigue comprometido hasta
     * que alguien la atienda o la dé por ausente.
     *
     * Lo corre el mismo cron que despacha los avisos, cada diez minutos: no
     * hace falta una tarea aparte, y así hay un solo lugar que tocar.
     */
    private function marcarAtrasadas(): int
    {
        return DB::update(
            "UPDATE cita
                SET id_estado_cita = 7
              WHERE id_estado_cita IN (1, 2)
                AND fecha_hora < DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [self::GRACIA_MIN]
        );
    }
}
