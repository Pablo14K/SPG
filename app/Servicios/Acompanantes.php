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
     * Devuelve `[id_cita => ['Ana Villalba', 'Josefina Villalba']]`, listo para
     * mostrar. Se pide para TODAS las citas de la pantalla de una vez: una
     * consulta por fila sería una por cada renglón de la agenda.
     *
     * @param  array<int>  $idsCita
     * @return array<int, array<int, string>>
     */
    public static function deCitas(array $idsCita): array
    {
        $ids = array_values(array_filter(array_map('intval', $idsCita)));
        if (! $ids) {
            return [];
        }

        $in = implode(',', array_fill(0, count($ids), '?'));
        $filas = DB::select(
            "SELECT id_cita, orden, nombre, apellido
               FROM cita_acompanante WHERE id_cita IN ($in) ORDER BY id_cita, orden", $ids
        );

        $out = [];
        foreach ($filas as $f) {
            $out[(int) $f->id_cita][] = trim($f->nombre . ' ' . (string) $f->apellido);
        }

        return $out;
    }
}
