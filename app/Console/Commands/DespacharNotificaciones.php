<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Servicios\Auditoria;
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

    /**
     * Cuántas horas atrasada antes de darla por ausente.
     *
     * **Es el día siguiente, no el mismo día.** Atrasada quiere decir «se hizo
     * la hora y nadie tocó nada», y eso durante la jornada es una situación
     * real: la clienta viene tarde, quien atiende está ocupada, se marca
     * después. Pasado un día entero ya no queda ninguna lectura razonable —
     * o no vino, o el salón se olvidó de cerrarla— y en las dos la cita ya no
     * está pasando.
     */
    private const AUSENTE_HS = 24;

    public function handle(): int
    {
        $atrasadas = $this->marcarAtrasadas();
        $ausentes = $this->cerrarAtrasadasViejas();
        $nuevos = Notificaciones::generarRecordatorios();
        $cerrados = Notificaciones::cerrarInternas();
        // Las reservas que pedían seña y nadie confirmó a tiempo: el horario se
        // les guardó, pero no para siempre.
        $sinSena = Notificaciones::cancelarSenasVencidas();
        $r = Notificaciones::despachar((int) $this->option('max'));

        $this->line("  citas marcadas atrasadas: $atrasadas");
        $this->line("  atrasadas cerradas como ausente: $ausentes");
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

    /**
     * Cierra como Ausente lo que quedó atrasado más de un día.
     *
     * **Atrasada es un estado de paso, y estaba quedando permanente.** Bloquea
     * la agenda a propósito —el sillón sigue comprometido hasta que alguien la
     * atienda o la dé por ausente— pero eso vale mientras la cita todavía
     * pueda ocurrir. Se midieron citas con **más de 800 horas** ahí adentro:
     * el profesional se olvidó de cerrarlas y nadie volvió a mirarlas.
     *
     * Lo que arrastra es peor que el número feo: cada una sigue contando como
     * cita viva en el panel, en «Clientes atrasados» y en los informes, así
     * que el porcentaje de asistencia sale mal y el salón decide con eso.
     *
     * **Esto NO contradice «la asistencia no es automática».** Esa regla es
     * sobre el mismo día, cuando marcar ausente sola sería inventar un hecho
     * que todavía puede desmentirse. Pasado un día entero el hecho ya está:
     * esa cita no se atendió. Lo único que hace el sistema es dejar de
     * anunciarla como pendiente.
     *
     * Queda en auditoría a nombre del profesional de la cita, para que se
     * distinga de una que alguien cerró a mano.
     */
    private function cerrarAtrasadasViejas(): int
    {
        $viejas = DB::select(
            'SELECT id_cita, id_usuario, fecha_hora FROM cita
              WHERE id_estado_cita = 7
                AND fecha_hora < DATE_SUB(NOW(), INTERVAL ? HOUR)',
            [self::AUSENTE_HS]
        );

        foreach ($viejas as $c) {
            DB::update('UPDATE cita SET id_estado_cita = 6 WHERE id_cita = ?', [$c->id_cita]);
            Auditoria::registrarComo((int) $c->id_usuario, 'AUSENCIA', 'Citas', 'cita', (int) $c->id_cita,
                'El sistema la cerró como ausente: quedó atrasada más de '
                . self::AUSENTE_HS . ' horas sin que nadie la atendiera ni la marcara.');
        }

        return count($viejas);
    }
}
