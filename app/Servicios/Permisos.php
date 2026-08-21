<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Quién puede entrar a qué.
 *
 * Los permisos de un rol viven en `rol_modulo`, una fila por clave. La
 * jerarquía se resuelve en los dos sentidos:
 *
 *  · quien tiene el módulo padre (`facturacion`) tiene todos sus submódulos
 *    — es la red que deja andar a un rol guardado antes de que ese módulo se
 *    dividiera;
 *  · quien tiene aunque sea un submódulo (`personal.asistencia`) entra al
 *    módulo, porque si no no tendría cómo llegar hasta él.
 *
 * Por eso el landing de cada módulo pide el padre y todo lo demás la clave
 * fina.
 */
class Permisos
{
    /** Permisos ya leídos, por rol. Dibujar el menú preguntaba una vez por tarjeta. */
    private static array $cache = [];

    /**
     * ¿Esta persona ve la agenda de TODO el equipo, o sólo la suya?
     *
     * Vive acá y no en un controlador porque lo preguntan dos pantallas —la
     * agenda y el panel—, y con la regla escrita en un solo lado no puede
     * pasar lo que pasó: el panel listaba las próximas citas **de todos**, así
     * que una profesional veía las de sus compañeras al entrar.
     */
    public static function veTodaLaAgenda(): bool
    {
        return self::esAdmin() || self::puede('seguridad.turnos');
    }

    public static function esAdmin(?int $rol = null): bool
    {
        $rol ??= (int) session('rol', 0);

        return $rol === (int) config('permisos.rol_admin', 1);
    }

    /**
     * ¿Ese rol tiene habilitado el módulo o submódulo?
     * El Administrador siempre: es superadministrador.
     */
    public static function rolPuede(int $rol, string $clave): bool
    {
        if ($clave === '') {
            return false;
        }
        if (self::esAdmin($rol)) {
            return true;
        }

        $tiene = self::$cache[$rol] ??= self::leer($rol);

        if (in_array($clave, $tiene, true)) {
            return true;
        }

        // Un submódulo también lo habilita tener el módulo padre completo…
        $punto = strpos($clave, '.');
        if ($punto !== false) {
            return in_array(substr($clave, 0, $punto), $tiene, true);
        }

        // …y al revés: con un submódulo alcanza para entrar al módulo.
        foreach ($tiene as $m) {
            if (str_starts_with($m, $clave . '.')) {
                return true;
            }
        }

        return false;
    }

    /** Atajo para las vistas: usa el rol de la sesión. */
    public static function puede(string $clave): bool
    {
        return self::rolPuede((int) session('rol', 0), $clave);
    }

    /**
     * Filtra tarjetas dejando las que el rol puede abrir.
     *
     * Cada tarjeta trae su permiso en 'p'. Va como campo y no como clave del
     * arreglo porque varias pueden compartirlo: Agenda y Nueva cita son las
     * dos `citas.agenda`, y un arreglo no admite la clave repetida.
     */
    public static function tarjetasPermitidas(array $tarjetas): array
    {
        $out = [];
        foreach ($tarjetas as $t) {
            // **Sin clave, la tarjeta es de todos.** «Mi cuenta» no tiene
            // permiso que pedir: es de cada persona y siempre está.
            if (($t['p'] ?? null) === null || self::puede((string) $t['p'])) {
                unset($t['p']);
                $out[] = $t;
            }
        }

        return $out;
    }

    /** La matriz de Seguridad → Roles, ya armada: módulo con sus hijos. */
    public static function matriz(): array
    {
        $subs = config('permisos.submodulos', []);
        $out = [];
        foreach (config('permisos.modulos', []) as $clave => $etiqueta) {
            $out[] = ['clave' => $clave, 'etiqueta' => $etiqueta, 'hijos' => $subs[$clave] ?? []];
        }

        return $out;
    }

    /** Las claves sueltas que la matriz manda por POST. */
    public static function claves(): array
    {
        $out = [];
        foreach (self::matriz() as $m) {
            if ($m['hijos']) {
                foreach (array_keys($m['hijos']) as $h) {
                    $out[] = $h;
                }
            } else {
                $out[] = $m['clave'];
            }
        }

        return $out;
    }

    /**
     * ¿Va marcada esa casilla? Un rol configurado antes de que existieran los
     * submódulos tiene guardado el módulo padre: se muestran todos sus hijos
     * marcados, y al guardar la matriz quedan escritos uno por uno.
     */
    public static function marcado(array $permisos, string $clave): bool
    {
        if (! empty($permisos[$clave])) {
            return true;
        }
        $punto = strpos($clave, '.');

        return $punto !== false && ! empty($permisos[substr($clave, 0, $punto)]);
    }

    /** Etiqueta legible, para los mensajes de «sin permiso». */
    public static function nombreModulo(string $clave): string
    {
        $mods = config('permisos.modulos', []);
        if (isset($mods[$clave])) {
            return $mods[$clave];
        }
        foreach (config('permisos.submodulos', []) as $padre => $subs) {
            if (isset($subs[$clave])) {
                return $mods[$padre] . ' → ' . $subs[$clave];
            }
        }

        return $clave;
    }

    /** Se vacía al cambiar los permisos de un rol, para no servir lo viejo. */
    public static function olvidar(?int $rol = null): void
    {
        if ($rol === null) {
            self::$cache = [];

            return;
        }
        unset(self::$cache[$rol]);
    }

    /**
     * Traduce las claves que quedaron guardadas con el nombre viejo.
     *
     * Cuando dos módulos se juntan o uno se parte, las filas de `rol_modulo` de
     * las bases ya instaladas siguen diciendo lo de antes. Sin esto, el rol no
     * daría error: perdería el permiso en silencio, que es peor.
     *
     * Se traduce al leer y no con un UPDATE porque el `.sql` que se entrega ya
     * viene con los nombres nuevos: esto es solo para las bases que están
     * andando. Al guardar la matriz de Roles quedan escritas como corresponde.
     */
    public static function equivaler(array $claves): array
    {
        $mapa = config('permisos.equivalencias', []);
        $out = [];
        foreach ($claves as $c) {
            foreach ($mapa[$c] ?? [$c] as $nueva) {
                $out[$nueva] = true;
            }
        }

        return array_keys($out);
    }

    private static function leer(int $rol): array
    {
        try {
            return self::equivaler(array_map(
                fn ($f) => (string) $f->modulo,
                DB::select('SELECT modulo FROM rol_modulo WHERE id_rol = ?', [$rol])
            ));
        } catch (Throwable) {
            // Si la tabla todavía no existe no se bloquea al personal: se
            // aplica el criterio por defecto, todo menos Seguridad.
            return array_values(array_diff(array_keys(config('permisos.modulos', [])), ['seguridad']));
        }
    }
}
