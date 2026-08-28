<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\DB;

/**
 * Cierre automático de citas que quedaron pendientes.
 *
 * El reloj y la fecha que mandan son los de MariaDB. La consulta usa la
 * `fecha_hora` actual de la cita, así que reprogramar al futuro reinicia el
 * plazo sin guardar una fecha duplicada ni mantener estado derivado.
 */
final class CitasVencidas
{
    public const HORAS = 24;

    /**
     * Minutos que se esperan antes de dar la cita por ausente.
     *
     * **Es una decisión del salón, no del sistema, y conviene tenerla escrita.**
     * Este proyecto sostenía que la asistencia no se marca sola —a los quince
     * minutos la clienta todavía puede estar llegando, y darla por ausente es
     * inventar un hecho que aún puede desmentirse—. El usuario pidió el cierre
     * automático igual, así que queda acá, en un solo lugar y con nombre: subir
     * el plazo es cambiar este número.
     *
     * Lo que NO cambia: una cita ya puesta En proceso no se toca, porque la
     * clienta está en el sillón; y marcar Ausente se puede deshacer desde la
     * agenda volviéndola a Programada.
     */
    public const MINUTOS_SIN_PRESENTARSE = 15;

    /**
     * Da por ausente a la clienta que no se presentó pasados los minutos de
     * tolerancia.
     *
     * Es lo mismo que hace `cerrarPendientes()` pero mucho antes, así que se
     * apoya en la misma consulta: sólo cambia la unidad del plazo.
     */
    public static function cerrarNoPresentadas(): int
    {
        return self::cerrar('MINUTE', self::MINUTOS_SIN_PRESENTARSE,
            'El sistema la cerró como ausente: pasaron más de '
            . self::MINUTOS_SIN_PRESENTARSE . ' minutos de su hora y nadie la atendió.');
    }

    /**
     * Cierra como Ausente las citas Programadas, Reprogramadas o Atrasadas
     * cuya fecha y hora vigente pasó hace más de 24 horas.
     *
     * «En proceso» queda fuera a propósito: indica que la atención comenzó y
     * no corresponde que el sistema invente una ausencia mientras sigue
     * abierta. Atendida, Cancelada y Ausente tampoco se tocan.
     */
    public static function cerrarPendientes(): int
    {
        return self::cerrar('HOUR', self::HORAS,
            'El sistema la cerró como ausente: quedó pendiente más de '
            . self::HORAS . ' horas sin que nadie la atendiera ni la marcara.');
    }

    /**
     * El cierre, con su plazo y su explicación.
     *
     * Escrito una sola vez: los dos plazos hacen exactamente lo mismo y con dos
     * copias, la que se toque después se queda atrás.
     *
     * `$unidad` va concatenada y NO como parámetro porque MySQL no admite un
     * marcador donde va la unidad del `INTERVAL`; los únicos valores posibles
     * son los dos literales que pasa esta clase, así que no hay nada que
     * escapar.
     */
    private static function cerrar(string $unidad, int $plazo, string $detalle): int
    {
        $viejas = DB::select(
            "SELECT id_cita, id_usuario FROM cita
              WHERE id_estado_cita IN (1, 2, 7)
                AND fecha_hora < DATE_SUB(NOW(), INTERVAL ? $unidad)",
            [$plazo]
        );

        $cerradas = 0;
        foreach ($viejas as $cita) {
            // Revalidar el estado y la fecha evita cerrar una cita que el
            // profesional haya atendido o reprogramado entre ambas consultas.
            $actualizada = DB::update(
                "UPDATE cita SET id_estado_cita = 6
                   WHERE id_cita = ?
                     AND id_estado_cita IN (1, 2, 7)
                     AND fecha_hora < DATE_SUB(NOW(), INTERVAL ? $unidad)",
                [$cita->id_cita, $plazo]
            );
            if ($actualizada === 0) {
                continue;
            }

            Auditoria::registrarComo(
                (int) $cita->id_usuario,
                'AUSENCIA',
                'Citas',
                'cita',
                (int) $cita->id_cita,
                $detalle
            );
            $cerradas++;
        }

        return $cerradas;
    }
}
