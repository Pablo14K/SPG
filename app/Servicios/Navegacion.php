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
            $out[] = $m;
        }

        return $out;
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
