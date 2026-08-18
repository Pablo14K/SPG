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
    public static function catalogo(bool $soloActivos = true, ?int $idSucursal = null): array
    {
        $filtro = $soloActivos ? 'AND sc.activo = 1 AND s.activo = 1' : '';

        // **El catalogo es de cada local**, por decision del usuario: lo que la
        // casa central regala por 400 puntos no tiene por que regalarlo la otra
        // sede. `canjeable_sucursal` ya guardaba eso; lo que faltaba era
        // filtrar. Sin filas vale en todas, que es la convencion del proyecto y
        // lo que espera quien recien abre el segundo local.
        //
        // **El VALE ya canjeado no se filtra acá y es a propósito**: los puntos
        // son del salón —fidelización se comparte— así que el premio también.
        // La clienta canjea donde junta y lo usa donde le queda cómodo.
        $suc = $idSucursal ?? Sucursales::activa();

        return DB::select(
            "SELECT sc.id_servicio_canjeable, sc.id_servicio, sc.puntos, sc.dias_vigencia, sc.activo,
                    s.nombre, s.precio, s.duracion_min, s.activo AS servicio_activo,
                    cs.nombre AS categoria
               FROM servicio_canjeable sc
               JOIN servicio s ON s.id_servicio = sc.id_servicio
               JOIN categoria_servicio cs ON cs.id_categoria_servicio = s.id_categoria_servicio
              WHERE 1=1 $filtro
                AND (:s1 = 0
                     OR EXISTS (SELECT 1 FROM canjeable_sucursal cx
                                 WHERE cx.id_servicio_canjeable = sc.id_servicio_canjeable
                                   AND cx.id_sucursal = :s2)
                     OR NOT EXISTS (SELECT 1 FROM canjeable_sucursal cy
                                     WHERE cy.id_servicio_canjeable = sc.id_servicio_canjeable))
              ORDER BY sc.puntos, s.nombre",
            ['s1' => (int) $suc, 's2' => (int) $suc]
        );
    }

    /**
     * Los canjes disponibles de TODAS las clientas, para el alta de cita del
     * mostrador: ahí la clienta se elige en la misma pantalla, así que no se
     * sabe de quién son hasta que la eligen.
     *
     * Cada fila trae su `id_cliente` para que la pantalla muestre sólo los de
     * la clienta elegida. **El filtro de la pantalla no es el control**: quien
     * decide es `aplicarACita()`, que comprueba contra la clienta de la cita.
     *
     * @return list<object>
     */
    public static function disponiblesDelSalon(): array
    {
        return DB::select(
            "SELECT c.id_canje, c.id_cliente, c.id_servicio, c.puntos, c.vence_en,
                    s.nombre, s.precio,
                    DATEDIFF(c.vence_en, CURDATE()) AS dias_restantes
               FROM canje c
               JOIN servicio s ON s.id_servicio = c.id_servicio
              WHERE c.id_cita IS NULL AND c.vence_en >= CURDATE()
              ORDER BY c.vence_en, s.nombre"
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
     * **Y el servicio del canje tiene que estar en la cita.** Si no está, el
     * canje no se aplica: aplicarlo gastaría el vale sin que el servicio se
     * haga, y la clienta perdería los puntos por nada. Es el caso de quien
     * marca el canje y se olvida de marcar el servicio de arriba, que las dos
     * pantallas piden hacer pero ninguna obligaba.
     *
     * @param  list<int>  $idsCanje
     */
    public static function aplicarACita(array $idsCanje, int $idCita, int $idCliente): int
    {
        $ids = array_unique(array_filter(array_map('intval', $idsCanje)));
        if (! $ids) {
            return 0;
        }

        $servicios = array_map(
            fn ($r) => (int) $r->id_servicio,
            DB::select('SELECT id_servicio FROM cita_servicio WHERE id_cita = ?', [$idCita])
        );
        if (! $servicios) {
            return 0;
        }
        $huecos = implode(',', array_fill(0, count($servicios), '?'));

        $n = 0;
        foreach ($ids as $idCanje) {
            $n += DB::update(
                "UPDATE canje SET id_cita = ?
                  WHERE id_canje = ? AND id_cliente = ?
                    AND id_cita IS NULL AND vence_en >= CURDATE()
                    AND id_servicio IN ($huecos)",
                array_merge([$idCita, $idCanje, $idCliente], $servicios)
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
