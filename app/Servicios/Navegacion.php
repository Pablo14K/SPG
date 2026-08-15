<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\Route;

/**
 * Arma los cuatro niveles de navegación a partir de config/navegacion.php.
 *
 * Mientras dura la migración conviven pantallas ya migradas y pantallas que
 * todavía no existen. Por eso todo pasa por url(): si la ruta no está
 * registrada, el enlace no se dibuja. Así el menú va apareciendo a medida que
 * cada módulo se migra, en vez de romper con «Route not defined».
 */
class Navegacion
{
    /** URL de una pantalla del catálogo, o null si todavía no está migrada. */
    public static function url(string $clave, array $parametros = []): ?string
    {
        return Route::has($clave) ? route($clave, $parametros) : null;
    }

    public static function existe(string $clave): bool
    {
        return Route::has($clave);
    }

    /**
     * Módulos que este rol puede ver, para la barra de navegación y el pie.
     * Se saltean los que todavía no tienen ruta.
     */
    public static function modulos(): array
    {
        $out = [];
        foreach (config('navegacion.modulos', []) as $m) {
            if (! Permisos::puede((string) $m['mod'])) {
                continue;
            }
            $m['url'] = self::url((string) $m['ruta']);
            if ($m['url'] === null) {
                continue;   // módulo todavía no migrado
            }
            $m['sub'] = self::subDe((string) $m['mod'], (string) $m['sub']);
            $out[] = $m;
        }

        return $out;
    }

    /**
     * Qué dice la tarjeta del módulo por debajo del título.
     *
     * **Se arma con lo que este rol puede abrir de verdad, no con una lista
     * fija.** El texto venía escrito a mano en `config/navegacion.php`
     * —«Usuarios · Roles · Turnos · Asistencia · Auditoría»— así que una
     * profesional a la que le revocaron Roles seguía viendo «Roles» anunciado
     * en la tarjeta de Seguridad. No podía entrar, pero la tarjeta se lo
     * ofrecía igual: el permiso funcionaba y el cartel mentía.
     *
     * Sale del catálogo de pantallas, que ya declara la clave del permiso de
     * cada una — la misma que pide el middleware, así que lo que se anuncia y
     * lo que se puede abrir no se pueden desfasar.
     *
     * Si el módulo no tiene pantallas catalogadas (Reportes es una sola
     * pantalla), se queda con el texto escrito a mano, que ahí no promete nada
     * que no esté.
     */
    public static function subDe(string $modulo, string $porDefecto): string
    {
        $titulos = [];
        foreach (config('navegacion.pantallas', []) as $clave => $p) {
            if (! str_starts_with((string) $clave, $modulo . '.')) {
                continue;
            }
            [$titulo, , $permiso] = $p;
            if (! Permisos::puede((string) $permiso)) {
                continue;
            }
            // Sin repetir: varias pantallas comparten permiso y nombre corto
            // («Usuarios» y «Nuevo usuario» son las dos de `seguridad.usuarios`).
            $titulos[(string) $permiso] ??= (string) $titulo;
        }

        return $titulos ? implode(' · ', $titulos) : $porDefecto;
    }

    /**
     * Accesos rápidos de la pantalla actual: lo que uno suele hacer después.
     * Se filtran por permiso con la misma clave que pide el middleware, así el
     * atajo aparece exactamente cuando se puede entrar.
     */
    public static function accesosRapidos(string $rutaActual): array
    {
        $pantallas = config('navegacion.pantallas', []);
        $relaciones = config('navegacion.relaciones', []);
        $out = [];

        // OJO: acá NO sirve config('navegacion.relaciones.' . $ruta). Las
        // claves llevan punto («clientes.lista») y config() interpreta el punto
        // como anidamiento, así que iría a buscar relaciones → clientes →
        // lista y no encontraría nada. Se trae el arreglo y se indexa a mano.
        foreach ($relaciones[$rutaActual] ?? [] as $clave) {
            if (! isset($pantallas[$clave])) {
                continue;
            }
            [$titulo, $icono, $permiso] = $pantallas[$clave];
            if (! Permisos::puede($permiso)) {
                continue;
            }
            if (($url = self::url($clave)) === null) {
                continue;
            }
            $out[] = ['url' => $url, 'titulo' => $titulo, 'icono' => $icono];
        }

        return $out;
    }

    // Las migas de pan (Panel › Módulo › Pantalla) las arma el componente
    // <x-encabezado> con el catálogo de abajo. Acá vivía una segunda versión
    // que nadie llamaba: dos implementaciones de lo mismo y una sola en uso.

    /** Etiqueta, ícono y permiso de una pantalla del catálogo. */
    public static function pantalla(string $clave): ?array
    {
        // Mismo cuidado que en accesosRapidos(): la clave lleva punto y
        // config() lo tomaría como anidamiento.
        $p = config('navegacion.pantallas', [])[$clave] ?? null;

        return $p ? ['titulo' => $p[0], 'icono' => $p[1], 'permiso' => $p[2]] : null;
    }

    /** Secciones del portal, para el pie cuando quien mira es una clienta. */
    public static function portal(): array
    {
        $out = [];
        foreach (config('navegacion.portal', []) as $p) {
            if (($url = self::url((string) $p['ruta'])) !== null) {
                $out[] = ['titulo' => $p['titulo'], 'url' => $url];
            }
        }

        return $out;
    }

    /** Centro de Ayuda y Soporte del pie. */
    public static function contactos(): array
    {
        return Contacto::delSalon();
    }
}
