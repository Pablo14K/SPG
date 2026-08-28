<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\DB;

/**
 * El desglose de la seña de una cita: cuánto pide cada servicio y por qué.
 *
 * **«Seña Gs. 210.000» sin explicación no se puede comprobar.** La clienta no
 * sabe de dónde sale ese número —si es de un servicio o de tres, ni qué
 * porcentaje se le aplicó— y quien confirma el pago en el mostrador tampoco:
 * los dos ven un total y tienen que creerle.
 *
 * El total sigue saliendo de `fn_cita_sena_requerida`, que es la autoridad y la
 * que hace cumplir el tope. Acá se arma **el mismo cálculo abierto por
 * servicio**, para poder mostrarlo; si alguna vez cambia el criterio de la
 * función, hay que cambiarlo también acá.
 */
class Sena
{
    /**
     * Una fila por servicio de la cita, más el total.
     *
     * **Lo canjeado no pide seña y aparece igual**, marcado: sacarlo de la
     * lista dejaría un total que no cierra con los servicios que se ven.
     *
     * @return array{filas: array<int, object>, total: float, lista: float}
     */
    public static function desglose(int $idCita): array
    {
        $filas = DB::select(
            "SELECT s.nombre,
                    s.precio,
                    s.sena_porcentaje,
                    -- El canje ya está pagado con puntos: cobrar una garantía
                    -- por algo que la clienta no va a pagar no tiene sentido.
                    (SELECT COUNT(*) FROM canje cj
                      WHERE cj.id_cita = cs.id_cita AND cj.id_servicio = cs.id_servicio) AS canjeado,
                    CASE
                        WHEN (SELECT COUNT(*) FROM canje cj
                               WHERE cj.id_cita = cs.id_cita AND cj.id_servicio = cs.id_servicio) > 0 THEN 0
                        WHEN s.sena_porcentaje IS NULL THEN 0
                        ELSE ROUND(s.precio * s.sena_porcentaje / 100)
                    END AS sena
               FROM cita_servicio cs
               JOIN servicio s ON s.id_servicio = cs.id_servicio
              WHERE cs.id_cita = ?
              ORDER BY s.nombre", [$idCita]
        );

        // **El total sale de la base, no de sumar las filas.** Es la que manda:
        // si las dos se separaran, el número que se muestra dejaría de ser el
        // que el sistema exige.
        $total = (float) DB::scalar('SELECT fn_cita_sena_requerida(?)', [$idCita]);

        $lista = 0.0;
        foreach ($filas as $f) {
            $lista += (float) $f->precio;
        }

        return ['filas' => $filas, 'total' => $total, 'lista' => $lista];
    }
}
