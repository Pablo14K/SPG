<?php

declare(strict_types=1);

namespace App\Servicios;

/**
 * Qué se carga en cada campo.
 *
 * El diccionario vive en `config/ayudas.php` y lo consume `<x-ayuda campo="…">`.
 * Acá está sólo lo que hace falta para encontrar la entrada correcta, que no es
 * una búsqueda directa: **el mismo dato aparece con nombres distintos según el
 * formulario**.
 *
 * Los formularios rápidos —crear una clienta sin salir de «Nueva cita», un
 * proveedor sin salir de la compra— prefijan sus campos para no chocar con los
 * del formulario grande, donde varios se llaman igual (`nombre` está en los
 * dos). Así que `cr_nombre`, `pr_nombre`, `pv_nombre` y `provNombre` son todos
 * el mismo «nombre», y sería absurdo repetir el texto cuatro veces: el que se
 * escribiera mal quedaría contradiciendo a los otros tres.
 */
class Ayuda
{
    /**
     * Los prefijos de los formularios rápidos y de los modales.
     *
     * Se sacan antes de buscar en el diccionario. Están escritos de más largo a
     * más corto porque se prueba en orden: con `pr_` adelante, un campo
     * `prov_x` perdería sólo las tres primeras letras.
     */
    private const PREFIJOS = ['prov', 'cr_', 'pr_', 'pv_', 'tr_', 'sr_', 'rn_', 'cf_', 'mc_'];

    /** El texto del campo, o cadena vacía si no hay ninguno cargado. */
    public static function de(string $campo): string
    {
        $dic = (array) config('ayudas', []);

        // Tal cual, que es el caso normal.
        if (isset($dic[$campo])) {
            return (string) $dic[$campo];
        }

        // Sin el prefijo del formulario rápido: `cr_nombre` → `nombre`.
        foreach (self::PREFIJOS as $p) {
            if (str_starts_with($campo, $p)) {
                $base = substr($campo, strlen($p));
                // `provNombre` viene en camello, no con guion bajo.
                $base = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $base) ?? $base);
                if (isset($dic[$base])) {
                    return (string) $dic[$base];
                }
            }
        }

        return '';
    }

    /** ¿Hay texto cargado para este campo? */
    public static function hay(string $campo): bool
    {
        return self::de($campo) !== '';
    }
}
