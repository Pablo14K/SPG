<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Las alergias de CADA persona que se atiende en una cita.
 *
 * `cliente.alergias` (7.108.0) alcanza mientras la cita sea de una sola
 * persona y esa persona tenga ficha. En una cita para otra persona, o en una
 * de tres, el sistema sólo podía anotar la alergia de **una** — y una alergia
 * es el único dato de la ficha que puede lastimar a alguien si nadie lo mira.
 *
 * Las tres viven donde vive el NOMBRE de cada una, que es lo que las hace
 * consistentes con el modelo que ya estaba:
 *
 * | Quién | Su nombre | Sus alergias |
 * |---|---|---|
 * | La clienta titular | `persona` | `cliente.alergias` — su ficha |
 * | Para quien es la cita | `cita.nombre_para` | `cita.alergias_para` |
 * | Cada acompañante | `cita_acompanante.nombre` | `cita_acompanante.alergias` |
 *
 * **Las dos últimas son un dato de la VISITA y no de una persona**, y por eso
 * no van a `persona`: quien acompaña no tiene ficha, y crearle una sería
 * inventar a alguien que el salón no registró —la regla que este proyecto
 * sostiene desde la 7.97.0—. La de la titular sí es de su ficha, así que se
 * guarda ahí y le queda para la próxima cita.
 *
 * Está en un servicio porque lo escriben **dos** pantallas —el portal y Nueva
 * cita— y lo leen otras dos —la agenda y «Mis citas»—: copiado se desfasa, que
 * es un error que este proyecto ya se hizo varias veces.
 */
class Alergias
{
    /** Lo que admite la columna, en las tres tablas. */
    public const MAX = 300;

    /**
     * Lo que escribió la persona, listo para guardar.
     *
     * **Vacío es NULL y no una cadena vacía**, que es la distinción que las
     * pantallas dicen con todas las letras: NULL quiere decir «no se
     * registró», no «no tiene ninguna». Y menos de dos caracteres no advierte
     * de nada —lo mismo que un nombre de una letra no identifica a nadie—,
     * así que se descarta acá en vez de reventar contra el `CHECK`.
     */
    public static function limpiar(mixed $valor): ?string
    {
        $v = trim((string) $valor);

        return mb_strlen($v) >= 2 ? mb_substr($v, 0, self::MAX) : null;
    }

    /**
     * Guarda las alergias de la clienta TITULAR, que van a su ficha.
     *
     * **Sólo escribe si cambiaron.** El formulario manda además el valor con
     * el que se dibujó (`alergias_titular_base`): sin esa comparación, abrir
     * «Nueva cita» sin JavaScript —donde el campo arranca vacío porque la
     * clienta se elige en esa misma pantalla— **le borraría las alergias que
     * ya tenía cargadas**, en silencio y al agendarle una cita. Con la
     * comparación, vacío contra vacío no toca nada; y borrarlas a propósito
     * sigue funcionando, porque ahí el valor sí difiere del que se dibujó.
     */
    public static function guardarDelTitular(Request $request, int $idCliente): void
    {
        if ($idCliente <= 0) {
            return;
        }

        $nuevo = self::limpiar($request->input('alergias_titular'));
        $base = self::limpiar($request->input('alergias_titular_base'));

        if ($nuevo === $base) {
            return;
        }

        DB::update('UPDATE cliente SET alergias = ? WHERE id_cliente = ?', [$nuevo, $idCliente]);
    }

    /**
     * Quiénes se atienden en esta cita y qué declaró cada una.
     *
     * **No consulta nada**: compone con lo que la pantalla ya trajo, porque
     * las dos que la usan listan citas —una consulta por fila sería una por
     * cada renglón de la agenda—. Lo que la cita tiene que traer es
     * `cliente`, `para_otra_persona`, `nombre_para`, `alergias` y
     * `alergias_para`; los acompañantes salen de `Acompanantes::deCitas()`.
     *
     * Devuelve **todas** las personas, tengan o no alergias cargadas: quien
     * llama decide si muestra las vacías. En la fila de la agenda sólo se
     * dibujan las que advierten algo; en el detalle van todas, porque ahí
     * «sin registrar» es una respuesta y no un renglón en blanco.
     *
     * **La titular figura sólo si es ella la que se atiende.** Con la cita
     * marcada «para otra persona», la que viene es la del `nombre_para`: ésa
     * ocupa el lugar 1 del grupo, que es el mismo motivo por el que
     * `cita_acompanante.orden` arranca en 2.
     *
     * @param  array<int,object>  $acompanantes  los de ESTA cita, en orden
     * @return array<int,object>  {quien, alergias, ficha}
     */
    public static function deLaCita(object $cita, array $acompanantes = []): array
    {
        $gente = [];

        if (! empty($cita->para_otra_persona)) {
            $gente[] = (object) [
                'quien' => trim((string) ($cita->nombre_para ?? '')) ?: 'Otra persona',
                'alergias' => self::limpiar($cita->alergias_para ?? null),
                // Su alergia es de esta cita: no hay ficha donde dejarla.
                'ficha' => false,
            ];
        } else {
            $gente[] = (object) [
                'quien' => trim((string) ($cita->cliente ?? '')) ?: 'La clienta',
                'alergias' => self::limpiar($cita->alergias ?? null),
                // Ésta sí sale de `cliente.alergias` y le queda para siempre.
                'ficha' => true,
            ];
        }

        foreach ($acompanantes as $ac) {
            $gente[] = (object) [
                'quien' => trim((string) ($ac->completo ?? '')) ?: 'Acompañante',
                'alergias' => self::limpiar($ac->alergias ?? null),
                'ficha' => false,
            ];
        }

        return $gente;
    }

    /**
     * Las de esta cita que de verdad advierten algo.
     *
     * @param  array<int,object>  $acompanantes
     * @return array<int,object>
     */
    public static function conAlgo(object $cita, array $acompanantes = []): array
    {
        return array_values(array_filter(
            self::deLaCita($cita, $acompanantes),
            fn ($p) => $p->alergias !== null
        ));
    }
}
