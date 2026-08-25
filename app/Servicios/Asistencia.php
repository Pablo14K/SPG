<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\DB;

/** Reglas de asistencia que deben poder correr desde la pantalla y desde cron. */
final class Asistencia
{
    /**
     * Cierra como falta sin aviso las entradas que ya vencieron su tolerancia.
     *
     * No toca una fila que alguien ya justificó, marcó como falta o fichó. Si
     * todavía no existe la fila de asistencia la crea, para que el estado sea
     * visible tanto en Asistencia como al intentar iniciar una cita.
     */
    public static function marcarEntradasVencidas(): int
    {
        $pendientes = DB::select(
            "SELECT ut.id_usuario, t.id_turno, t.nombre, t.hora_inicio,
                    t.flexibilidad_entrada_min, a.id_asistencia
               FROM usuario_turno ut
               JOIN usuario u ON u.id_usuario = ut.id_usuario AND u.activo = 1
               JOIN turno_laboral t ON t.id_turno = ut.id_turno AND t.activo = 1
               JOIN turno_dia td ON td.id_turno = t.id_turno
                                AND td.dia_semana = WEEKDAY(CURDATE()) + 1
               LEFT JOIN asistencia a ON a.id_usuario = ut.id_usuario
                                     AND a.id_turno = t.id_turno
                                     AND a.fecha = CURDATE()
              WHERE TIME(NOW()) > ADDTIME(t.hora_inicio,
                                           SEC_TO_TIME(t.flexibilidad_entrada_min * 60))
                AND a.id_asistencia IS NULL
              UNION ALL
             SELECT ut.id_usuario, t.id_turno, t.nombre, t.hora_inicio,
                    t.flexibilidad_entrada_min, a.id_asistencia
               FROM usuario_turno ut
               JOIN usuario u ON u.id_usuario = ut.id_usuario AND u.activo = 1
               JOIN turno_laboral t ON t.id_turno = ut.id_turno AND t.activo = 1
               JOIN turno_dia td ON td.id_turno = t.id_turno
                                AND td.dia_semana = WEEKDAY(CURDATE()) + 1
               JOIN asistencia a ON a.id_usuario = ut.id_usuario
                                AND a.id_turno = t.id_turno
                                AND a.fecha = CURDATE()
              WHERE TIME(NOW()) > ADDTIME(t.hora_inicio,
                                           SEC_TO_TIME(t.flexibilidad_entrada_min * 60))
                AND a.hora_entrada IS NULL
                AND a.justificada IS NULL"
        );

        $marcadas = 0;
        foreach ($pendientes as $fila) {
            $detalle = 'Marcado automáticamente al vencer la tolerancia de entrada del turno «'
                . $fila->nombre . '» (' . (int) $fila->flexibilidad_entrada_min . ' min).';

            if ($fila->id_asistencia) {
                $marcadas += DB::update(
                    "UPDATE asistencia
                        SET justificada = 0,
                            motivo_ausencia = 'Sin aviso (llegada fuera de tolerancia)',
                            observaciones = ?
                      WHERE id_asistencia = ?
                        AND hora_entrada IS NULL
                        AND justificada IS NULL",
                    [$detalle, (int) $fila->id_asistencia]
                );
                continue;
            }

            DB::insert(
                "INSERT INTO asistencia
                    (id_usuario, id_turno, fecha, id_usuario_registro,
                     hora_entrada, hora_salida, motivo_ausencia, horas_extras,
                     observaciones, justificada)
                 VALUES (?, ?, CURDATE(), NULL, NULL, NULL,
                         'Sin aviso (llegada fuera de tolerancia)', 0, ?, 0)",
                [(int) $fila->id_usuario, (int) $fila->id_turno, $detalle]
            );
            $marcadas++;
        }

        return $marcadas;
    }
}
