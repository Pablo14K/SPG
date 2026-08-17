<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Los números del negocio que decide el salón, no quien programa.
 *
 * `config/spg.php` sigue siendo el lugar de las constantes **técnicas** —el
 * paso de la agenda, cuántas filas por página, la gracia del fichaje—. Lo que
 * vive acá es distinto: son decisiones comerciales que cambian sin desplegar
 * nada, y que por eso están en la base y se editan desde una pantalla.
 *
 * **`config/spg.php` queda igual y hace de respaldo.** Si la tabla todavía no
 * existe —una base vieja que no se reimportó— se usa el valor de ahí en vez de
 * reventar: el salón sigue acumulando puntos como siempre hasta que actualice.
 */
class Config
{
    /** Se lee una vez por petición: la factura la consulta por cada emisión. */
    private static ?int $puntosCadaGs = null;

    /**
     * Cuántos guaraníes facturados valen 1 punto.
     *
     * Con 10.000, una factura de Gs. 320.000 le deja 32 puntos al cliente.
     */
    public static function puntosCadaGs(): int
    {
        if (self::$puntosCadaGs !== null) {
            return self::$puntosCadaGs;
        }

        $porDefecto = (int) config('spg.puntos_cada_gs', 10000);

        try {
            $v = (int) DB::scalar('SELECT puntos_cada_gs FROM configuracion WHERE id_configuracion = 1');
        } catch (Throwable) {
            $v = 0;   // la tabla no está: base sin actualizar
        }

        return self::$puntosCadaGs = ($v > 0 ? $v : $porDefecto);
    }

    /**
     * Guarda la relación. Devuelve el valor que quedó, o null si no se pudo.
     *
     * El rango lo hace cumplir el `CHECK` de la base, que es donde no se puede
     * esquivar; acá se comprueba para poder decirlo con palabras antes de
     * intentarlo.
     */
    public static function guardarPuntosCadaGs(int $gs): ?int
    {
        if ($gs < 100 || $gs > 10000000) {
            return null;
        }

        try {
            DB::update('UPDATE configuracion SET puntos_cada_gs = ? WHERE id_configuracion = 1', [$gs]);
        } catch (Throwable) {
            return null;
        }
        self::$puntosCadaGs = $gs;

        return $gs;
    }

    /** La fila entera, leída una vez por petición. */
    private static ?object $identidad = null;

    /**
     * Cómo se llama el salón y con qué logo se presenta.
     *
     * **Vivían en `APP_NAME`**, así que cambiarlos era editar el `.env` y volver
     * a desplegar —y en Docker, además, entrar al contenedor—. Es el mismo caso
     * que `puntos_cada_gs` en la 7.27.0: un dato del negocio escondido detrás de
     * un despliegue. Ahora lo edita el salón desde una pantalla, y **el cambio
     * se ve en todas partes a la vez** porque todas las pantallas leen de acá.
     *
     * `config('app.name')` queda de respaldo, para una base que todavía no se
     * reimportó: sin la columna se sigue mostrando lo de siempre en vez de
     * reventar la pantalla de ingreso, que es la peor de todas para romper.
     */
    public static function nombreSalon(): string
    {
        $n = trim((string) (self::identidad()->nombre_salon ?? ''));

        return $n !== '' ? $n : (string) config('app.name', 'SPG');
    }

    /**
     * URL del logo, o null si el salón no cargó ninguno.
     *
     * Null NO es un error: sin logo se dibuja la tijera de siempre, que es la
     * identidad por defecto del sistema.
     */
    public static function logo(): ?string
    {
        $l = trim((string) (self::identidad()->logo ?? ''));
        if ($l === '' || ! is_file(public_path('assets/logo/' . $l))) {
            return null;
        }

        return recurso('logo/' . $l);
    }

    private static function identidad(): object
    {
        if (self::$identidad !== null) {
            return self::$identidad;
        }

        try {
            $f = DB::selectOne('SELECT nombre_salon, logo FROM configuracion WHERE id_configuracion = 1');
        } catch (Throwable) {
            $f = null;   // la tabla o las columnas no están: base sin actualizar
        }

        return self::$identidad = $f ?: (object) ['nombre_salon' => '', 'logo' => null];
    }

    /** Para las pruebas, que cambian el valor dentro de una transacción. */
    public static function olvidar(): void
    {
        self::$puntosCadaGs = null;
        self::$identidad = null;
    }
}
