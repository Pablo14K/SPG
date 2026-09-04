<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Quiénes vienen con la clienta.
 *
 * `cita.personas` decía CUÁNTAS venían y nada más, así que el salón sabía que
 * iban a llegar tres y no a quiénes esperar. Los nombres viven en
 * `cita_acompanante`, una fila por persona — **nunca una lista adentro de un
 * campo**, que es la falta a la 1FN que este proyecto ya evita con `turno_dia`.
 *
 * **La primera persona no está acá y es a propósito**: es la clienta que pidió
 * la cita, y ya está en `cita.id_cliente`. Guardarla otra vez sería el mismo
 * dato dos veces, que es lo que prohíbe la regla número dos. Por eso `orden`
 * arranca en 2: es el lugar que ocupa en el grupo.
 *
 * Está en un servicio y no en cada controlador porque lo escriben **dos**
 * pantallas —el portal y Nueva cita— y copiado se desfasan, que es un error que
 * este proyecto ya se hizo varias veces.
 */
class Acompanantes
{
    /**
     * Guarda los que vienen con la clienta. Se rehace la lista entera: lo que
     * el formulario ya no manda, deja de estar.
     *
     * @param  array<int|string, mixed>  $nombres    acomp_nombre[orden]
     * @param  array<int|string, mixed>  $apellidos  acomp_apellido[orden]
     */
    public static function guardar(int $idCita, array $nombres, array $apellidos, int $personas): void
    {
        try {
            DB::delete('DELETE FROM cita_acompanante WHERE id_cita = ?', [$idCita]);

            foreach ($nombres as $orden => $nombre) {
                $orden = (int) $orden;
                $nombre = trim((string) $nombre);

                // **Fuera del grupo declarado no entra nadie.** El número de
                // personas es lo que manda: si dice 3, hay lugar para el 2 y el
                // 3 y nada más. Sin esto, bajar el número dejaría colgados a los
                // que ya estaban cargados y la agenda mostraría más gente de la
                // que la clienta anunció.
                if ($orden < 2 || $orden > $personas || $orden > 20) {
                    continue;
                }
                // Un nombre de una letra no identifica a nadie, y el `CHECK` de
                // la base lo rechaza: se descarta acá para no reventar por algo
                // que la clienta simplemente dejó a medias.
                if (mb_strlen($nombre) < 2) {
                    continue;
                }

                $apellido = trim((string) ($apellidos[$orden] ?? ''));

                DB::insert(
                    'INSERT INTO cita_acompanante (id_cita, orden, nombre, apellido) VALUES (?,?,?,?)',
                    [$idCita, $orden, mb_substr($nombre, 0, 60), $apellido !== '' ? mb_substr($apellido, 0, 60) : null]
                );
            }
        } catch (Throwable $e) {
            // **No se cae la reserva por esto.** La cita ya está agendada y el
            // horario tomado; los nombres son un dato del pedido, no de la
            // disponibilidad. Queda en el log, que es donde hace falta.
            report($e);
        }
    }

    /**
     * Los que vienen con la clienta, por cita.
     *
     * Devuelve `[id_cita => [ {nombre, apellido, completo, id_cliente} ]]`. Se
     * pide para TODAS las citas de la pantalla de una vez: una consulta por
     * fila sería una por cada renglón de la agenda.
     *
     * **`id_cliente` viene resuelto y es lo que hace falta para el botón.**
     * Quien viene acompañando es una persona que el salón va a atender, y sin
     * ficha propia no hay dónde anotarle sus preferencias ni le queda
     * historial — el día que quiera abrir su cuenta, arranca de cero. Se busca
     * por nombre y apellido, el mismo criterio con el que la agenda resuelve
     * la ficha de «para otra persona».
     *
     * **No se le crea la ficha sola**, que es la regla de siempre: sería
     * inventar una persona que el salón no registró, y con un nombre a medias.
     * Lo que se hace es ofrecerla, con el nombre ya puesto.
     *
     * @param  array<int>  $idsCita
     * @return array<int, array<int, object>>
     */
    public static function deCitas(array $idsCita): array
    {
        $ids = array_values(array_filter(array_map('intval', $idsCita)));
        if (! $ids) {
            return [];
        }

        $in = implode(',', array_fill(0, count($ids), '?'));
        $filas = DB::select(
            "SELECT a.id_cita, a.orden, a.nombre, a.apellido,
                    (SELECT cl.id_cliente
                       FROM cliente cl
                       JOIN persona pe ON pe.id_persona = cl.id_persona
                      WHERE cl.activo = 1
                        AND LOWER(TRIM(CONCAT(pe.nombre, ' ', COALESCE(pe.apellido, ''))))
                            = LOWER(TRIM(CONCAT(a.nombre, ' ', COALESCE(a.apellido, ''))))
                      ORDER BY cl.id_cliente LIMIT 1) AS id_cliente
               FROM cita_acompanante a
              WHERE a.id_cita IN ($in) ORDER BY a.id_cita, a.orden", $ids
        );

        $out = [];
        foreach ($filas as $f) {
            $out[(int) $f->id_cita][] = (object) [
                'nombre' => (string) $f->nombre,
                'apellido' => (string) ($f->apellido ?? ''),
                'completo' => trim($f->nombre . ' ' . (string) $f->apellido),
                'id_cliente' => $f->id_cliente ? (int) $f->id_cliente : null,
            ];
        }

        return $out;
    }
}
