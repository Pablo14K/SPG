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
     * Cierra como Ausente las citas Programadas, Reprogramadas o Atrasadas
     * cuya fecha y hora vigente pasó hace más de 24 horas.
     *
     * «En proceso» queda fuera a propósito: indica que la atención comenzó y
     * no corresponde que el sistema invente una ausencia mientras sigue
     * abierta. Atendida, Cancelada y Ausente tampoco se tocan.
     */
    public static function cerrarPendientes(): int
    {
        $viejas = DB::select(
            'SELECT id_cita, id_usuario FROM cita
              WHERE id_estado_cita IN (1, 2, 7)
                AND fecha_hora < DATE_SUB(NOW(), INTERVAL ? HOUR)',
            [self::HORAS]
        );

        $cerradas = 0;
        foreach ($viejas as $cita) {
            // Revalidar el estado y la fecha evita cerrar una cita que el
            // profesional haya atendido o reprogramado entre ambas consultas.
            $actualizada = DB::update(
                'UPDATE cita SET id_estado_cita = 6
                   WHERE id_cita = ?
                     AND id_estado_cita IN (1, 2, 7)
                     AND fecha_hora < DATE_SUB(NOW(), INTERVAL ? HOUR)',
                [$cita->id_cita, self::HORAS]
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
                'El sistema la cerró como ausente: quedó pendiente más de '
                . self::HORAS . ' horas sin que nadie la atendiera ni la marcara.'
            );
            $cerradas++;
        }

        return $cerradas;
    }
}
