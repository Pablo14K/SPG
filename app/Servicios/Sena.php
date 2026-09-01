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
     * @return array{filas: array<int, object>, total: float, lista: float,
     *               descuento: float, promo: ?string, nivel: ?string}
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

        // El descuento del nivel de esta clienta: es el único de los de nivel
        // que le corresponde, los otros son de niveles que no tiene.
        $idNivel = (int) DB::scalar(
            'SELECT fn_cliente_descuento(c.id_cliente) FROM cita c WHERE c.id_cita = ?', [$idCita]);

        // **De dónde sale el descuento, y ahora pueden ser VARIOS.** Un total
        // más bajo sin explicación se lee como un error de la pantalla, y quien
        // cobra no puede defenderlo si la clienta pregunta.
        //
        // Desde que el descuento se calcula por servicio, nombrar uno solo sería
        // mentir por omisión: con una promo del 5 % en el corte y otra del 3 %
        // en el lavado, el número sale de las dos. Se listan las que de verdad
        // aportaron — la que gana en cada renglón.
        $aporta = DB::select(
            "SELECT d.nombre,
                    (SELECT COUNT(*) FROM nivel n WHERE n.id_descuento = d.id_descuento) AS es_nivel
               FROM descuento d
              WHERE d.activo = 1
                AND EXISTS (
                    SELECT 1 FROM cita_servicio cs
                      JOIN servicio s ON s.id_servicio = cs.id_servicio
                     WHERE cs.id_cita = :c1
                       AND NOT EXISTS (SELECT 1 FROM canje cj
                                        WHERE cj.id_cita = cs.id_cita
                                          AND cj.id_servicio = cs.id_servicio)
                       -- Este descuento le aplica a ese servicio…
                       AND (NOT EXISTS (SELECT 1 FROM servicio_descuento sd
                                         WHERE sd.id_descuento = d.id_descuento)
                            OR EXISTS (SELECT 1 FROM servicio_descuento sd
                                        WHERE sd.id_descuento = d.id_descuento
                                          AND sd.id_servicio = cs.id_servicio))
                       AND fn_descuento_monto(d.id_descuento, s.precio) > 0
                       -- …y es el mejor que le aplica: si no, no aportó nada.
                       AND fn_descuento_monto(d.id_descuento, s.precio) >= ALL (
                             SELECT fn_descuento_monto(d2.id_descuento, s.precio)
                               FROM descuento d2
                              WHERE d2.activo = 1
                                AND (d2.id_descuento = :niv2
                                     OR NOT EXISTS (SELECT 1 FROM nivel n2
                                                     WHERE n2.id_descuento = d2.id_descuento))
                                AND (NOT EXISTS (SELECT 1 FROM servicio_descuento sd2
                                                  WHERE sd2.id_descuento = d2.id_descuento)
                                     OR EXISTS (SELECT 1 FROM servicio_descuento sd2
                                                 WHERE sd2.id_descuento = d2.id_descuento
                                                   AND sd2.id_servicio = cs.id_servicio))))
                AND (d.id_descuento = :niv1
                     OR NOT EXISTS (SELECT 1 FROM nivel n WHERE n.id_descuento = d.id_descuento))
              ORDER BY d.nombre",
            ['c1' => $idCita, 'niv1' => $idNivel, 'niv2' => $idNivel]
        );

        $nombreNivel = (string) DB::scalar(
            'SELECT n.nombre FROM cita c JOIN cliente cl ON cl.id_cliente = c.id_cliente
               JOIN nivel n ON n.id_nivel = fn_cliente_nivel(cl.id_cliente)
              WHERE c.id_cita = ?', [$idCita]);

        $promos = [];
        $porNivel = false;
        foreach ($aporta as $a) {
            if ((int) $a->es_nivel > 0) {
                $porNivel = true;
            } else {
                $promos[] = (string) $a->nombre;
            }
        }

        return [
            'filas' => $filas,
            'total' => $total,
            'lista' => $lista,
            'descuento' => (float) DB::scalar('SELECT fn_cita_descuento_total(?, ?)', [$idCita, $idNivel]),
            'promo' => $promos ? implode('» y «', $promos) : null,
            'nivel' => $porNivel ? $nombreNivel : null,
        ];
    }
}
