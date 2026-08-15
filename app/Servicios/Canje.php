<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\DB;

/**
 * Canje de puntos por servicios.
 *
 * El programa de fidelización sólo sumaba: en los 90 días de la simulación se
 * acumularon 1.414 puntos y **no había forma de gastarlos** (hallazgo IN-03).
 *
 * Son dos cosas distintas y conviene no mezclarlas:
 *
 *  · el **catálogo** (`servicio_canjeable`), que arma el salón: qué servicios
 *    se pueden canjear, por cuántos puntos y cuántos días vale el canje;
 *  · el **canje** (`canje`), que es el hecho: esta clienta cambió sus puntos
 *    por este servicio, tal día, y lo tiene que usar antes de tal fecha.
 *
 * **El estado del canje no se guarda: se deduce**, igual que en
 * `sena_solicitud` — sin cita y sin vencer es *disponible*, con cita está
 * *usado*, y sin cita y pasada la fecha está *vencido*. Es lo que pide la 3FN
 * y además evita el clásico de un estado guardado que se olvidó de actualizar.
 */
class Canje
{
    /** Servicios que hoy se pueden canjear, con lo que cuestan. */
    public static function catalogo(bool $soloActivos = true): array
    {
        $filtro = $soloActivos ? 'AND sc.activo = 1 AND s.activo = 1' : '';

        return DB::select(
            "SELECT sc.id_servicio_canjeable, sc.id_servicio, sc.puntos, sc.dias_vigencia, sc.activo,
                    s.nombre, s.precio, s.duracion_min, s.activo AS servicio_activo,
                    cs.nombre AS categoria
               FROM servicio_canjeable sc
               JOIN servicio s ON s.id_servicio = sc.id_servicio
               JOIN categoria_servicio cs ON cs.id_categoria_servicio = s.id_categoria_servicio
              WHERE 1=1 $filtro
              ORDER BY sc.puntos, s.nombre"
        );
    }

    /**
     * Los canjes de una clienta, con su estado deducido.
     *
     * `$soloDisponibles` es lo que hace falta al agendar: ahí sólo sirven los
     * que todavía se pueden usar.
     */
    public static function deCliente(int $idCliente, bool $soloDisponibles = false): array
    {
        $filtro = $soloDisponibles ? 'AND c.id_cita IS NULL AND c.vence_en >= CURDATE()' : '';

        return DB::select(
            "SELECT c.id_canje, c.id_servicio, c.puntos, c.fecha, c.vence_en, c.id_cita,
                    fn_canje_estado(c.id_canje) AS estado,
                    s.nombre, s.duracion_min, s.precio,
                    DATEDIFF(c.vence_en, CURDATE()) AS dias_restantes
               FROM canje c
               JOIN servicio s ON s.id_servicio = c.id_servicio
              WHERE c.id_cliente = ? $filtro
              ORDER BY (c.id_cita IS NULL AND c.vence_en >= CURDATE()) DESC, c.vence_en, c.id_canje DESC",
            [$idCliente]
        );
    }

    /** Puntos que tiene hoy la clienta. Los calcula la base. */
    public static function puntos(int $idCliente): int
    {
        return (int) DB::scalar('SELECT fn_cliente_puntos(?)', [$idCliente]);
    }

    /**
     * Canjea un servicio. Devuelve el id del canje.
     *
     * Va en transacción porque `sp_canjear_servicio` toma un candado sobre la
     * clienta antes de leerle los puntos, y un candado sin transacción se
     * suelta al instante: dos canjes simultáneos con saldo para uno solo
     * entrarían los dos. Es el mismo patrón del cobro y del stock.
     */
    public static function canjear(int $idCliente, int $idServicio): int
    {
        return Bd::enTransaccion(
            fn () => Bd::idDe('sp_canjear_servicio', [$idCliente, $idServicio])
        );
    }

    /**
     * Marca los canjes usados en esa cita.
     *
     * Se comprueba **contra la clienta de la cita y no contra lo que mandó el
     * formulario**: con el id suelto, alguien podría gastar el canje de otra
     * persona cambiando un campo oculto. Devuelve cuántos se aplicaron.
     *
     * @param  list<int>  $idsCanje
     */
    public static function aplicarACita(array $idsCanje, int $idCita, int $idCliente): int
    {
        $n = 0;
        foreach (array_unique(array_filter(array_map('intval', $idsCanje))) as $idCanje) {
            $n += DB::update(
                'UPDATE canje SET id_cita = ?
                  WHERE id_canje = ? AND id_cliente = ?
                    AND id_cita IS NULL AND vence_en >= CURDATE()',
                [$idCita, $idCanje, $idCliente]
            );
        }

        return $n;
    }

    /**
     * Suelta los canjes de una cita que se cancela.
     *
     * **No se le devuelven los puntos**: nunca los perdió — el canje vuelve a
     * quedar disponible y lo puede usar en otra cita. Devolver los puntos y
     * dejar el canje sería regalarle las dos cosas.
     *
     * Ojo con el vencimiento: si venció mientras la cita estaba agendada, el
     * canje vuelve vencido. Es lo correcto —el plazo corre desde el canje— y
     * la pantalla lo muestra como tal.
     */
    public static function soltarDeCita(int $idCita): int
    {
        return DB::update('UPDATE canje SET id_cita = NULL WHERE id_cita = ?', [$idCita]);
    }

    /** Los canjes aplicados a una cita, para mostrarlos en la atención. */
    public static function deCita(int $idCita): array
    {
        return DB::select(
            'SELECT c.id_canje, c.id_servicio, c.puntos, s.nombre
               FROM canje c JOIN servicio s ON s.id_servicio = c.id_servicio
              WHERE c.id_cita = ? ORDER BY s.nombre', [$idCita]
        );
    }
}
