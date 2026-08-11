<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Los datos personales viven en `persona`, no repartidos.
 *
 * Nombre, cédula, teléfono y email están UNA sola vez: `usuario`, `cliente` y
 * `proveedor` la referencian con `id_persona`. Si aparece una entidad nueva
 * con nombre y contacto, se enlaza acá en vez de darle columnas propias — dos
 * copias del mismo dato terminan siempre con una vieja.
 *
 * Esta clase es el único lugar que escribe esa tabla, así ninguna pantalla se
 * olvida de un campo ni de una validación.
 */
class Persona
{
    /**
     * Largo de cada columna, para no depender de que MySQL avise.
     *
     * Sin STRICT_TRANS_TABLES, MariaDB recorta en silencio: un nombre de 500
     * caracteres entraba como 120 y nadie se enteraba de que se perdió la
     * mitad. Laravel abre la conexión en modo estricto, con lo cual ahora
     * fallaría — pero con una excepción fea en vez de un mensaje útil.
     */
    public const LARGOS = [
        'nombre' => 120, 'apellido' => 80, 'cedula' => 20, 'ruc' => 20,
        'telefono' => 20, 'email' => 120, 'direccion' => 255,
    ];

    private const ETIQUETAS = [
        'nombre' => 'nombre', 'apellido' => 'apellido', 'cedula' => 'cédula',
        'ruc' => 'RUC', 'telefono' => 'teléfono', 'email' => 'email', 'direccion' => 'dirección',
    ];

    /**
     * Valida los datos antes de escribirlos. Devuelve el mensaje del problema,
     * o null si está todo bien.
     */
    public static function error(array $d): ?string
    {
        foreach (self::LARGOS as $campo => $max) {
            $v = trim((string) ($d[$campo] ?? ''));
            if ($v !== '' && mb_strlen($v, 'UTF-8') > $max) {
                return 'El ' . self::ETIQUETAS[$campo] . ' no puede pasar de ' . $max . ' caracteres '
                    . '(escribiste ' . mb_strlen($v, 'UTF-8') . ').';
            }
        }

        // La cédula paraguaya es numérica. Se aceptan puntos y espacios porque
        // la gente los escribe, pero no letras ni símbolos: sin esto entraba
        // cualquier cosa como número de documento.
        $ci = trim((string) ($d['cedula'] ?? ''));
        if ($ci !== '' && ! preg_match('/^[0-9][0-9\.\s-]{2,19}$/', $ci)) {
            return 'La cédula sólo puede tener números (se admiten puntos o guiones).';
        }

        $ruc = trim((string) ($d['ruc'] ?? ''));
        if ($ruc !== '' && ! preg_match('/^[0-9][0-9\.\s-]{2,18}-?[0-9kK]?$/', $ruc)) {
            return 'El RUC no tiene un formato válido (ej: 80012345-6).';
        }

        return null;
    }

    /**
     * Crea o actualiza una persona y devuelve su id.
     * Solo toca las claves que se le pasan: lo que no venga se deja como está.
     */
    public static function guardar(?int $idPersona, array $d): int
    {
        $campos = ['nombre', 'apellido', 'cedula', 'ruc', 'telefono', 'email', 'direccion', 'fecha_nacimiento'];
        $datos = array_intersect_key($d, array_flip($campos));

        // Los identificadores únicos van NULL si vienen vacíos: así dos
        // personas sin cédula no chocan contra el índice único.
        foreach (['cedula', 'ruc', 'email', 'telefono', 'direccion', 'fecha_nacimiento', 'apellido'] as $c) {
            if (array_key_exists($c, $datos) && trim((string) $datos[$c]) === '') {
                $datos[$c] = null;
            }
        }

        if ($idPersona) {
            if ($datos) {
                $sets = implode(', ', array_map(fn ($c) => "`$c` = :$c", array_keys($datos)));
                DB::update("UPDATE persona SET $sets WHERE id_persona = :id", $datos + ['id' => $idPersona]);
            }

            return $idPersona;
        }

        if (empty($datos['nombre'])) {
            throw new InvalidArgumentException('Una persona necesita al menos un nombre.');
        }

        $cols = implode(',', array_map(fn ($c) => "`$c`", array_keys($datos)));
        $vals = implode(',', array_map(fn ($c) => ":$c", array_keys($datos)));
        DB::insert("INSERT INTO persona ($cols) VALUES ($vals)", $datos);

        return (int) DB::getPdo()->lastInsertId();
    }

    /**
     * ¿Ya hay otra persona con esa cédula o ese RUC? Devuelve su id o null.
     *
     * Sirve para avisar «esta persona ya está cargada» en vez de chocar contra
     * el índice único con un error feo. Ojo: la cédula es única a nivel de
     * PERSONA, no de cliente — quien aparece puede ser la misma persona
     * cargada como empleada o proveedora.
     */
    public static function porDocumento(?string $cedula, ?string $ruc = null, int $excepto = 0): ?int
    {
        $cedula = trim((string) $cedula) ?: null;
        $ruc = trim((string) $ruc) ?: null;
        if (! $cedula && ! $ruc) {
            return null;
        }

        $id = DB::scalar(
            'SELECT id_persona FROM persona
              WHERE ((:ced IS NOT NULL AND cedula = :ced2) OR (:ruc IS NOT NULL AND ruc = :ruc2))
                AND id_persona <> :ex LIMIT 1',
            ['ced' => $cedula, 'ced2' => $cedula, 'ruc' => $ruc, 'ruc2' => $ruc, 'ex' => $excepto]
        );

        return $id ? (int) $id : null;
    }
}
