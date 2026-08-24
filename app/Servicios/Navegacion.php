<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Routing\Exceptions\UrlGenerationException;
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
    /**
     * URL de una pantalla del catálogo, o null si todavía no está migrada.
     *
     * **Devuelve null también cuando la ruta necesita un parámetro que no se le
     * dio**, en vez de reventar. `clientes.historial` es `clientes/{id}/historial`
     * y sin el id `route()` levanta `UrlGenerationException`: quien la llama
     * armando un menú no tiene ese id ni tiene por qué saber cuáles lo piden, y
     * una pantalla que no se puede nombrar sin dato es una que ese menú no puede
     * ofrecer. Antes nadie la pedía sin parámetros, así que el agujero estaba
     * tapado por casualidad — y apareció apenas el desplegable recorrió el
     * catálogo entero: **500 en el panel**, o sea el sistema entero caído.
     */
    public static function url(string $clave, array $parametros = []): ?string
    {
        if (! Route::has($clave)) {
            return null;
        }

        try {
            return route($clave, $parametros);
        } catch (UrlGenerationException) {
            return null;
        }
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
        // **Sale de `pantallasDe()`, que es la misma lista que dibuja el
        // desplegable.** Escrito aparte se desfasa, y se desfasó: esto filtraba
        // por el NOMBRE de la ruta, así que al partir Seguridad en tres
        // (7.57.0) la tarjeta del Panel seguía anunciando los ocho renglones de
        // antes —turnos, asistencia, comisiones, sucursales y contacto— aunque
        // esas pantallas ya son de Personal y de Configuración. Es exactamente
        // el defecto que la 7.58.0 corrigió en el desplegable **y no acá**.
        $titulos = [];
        foreach (self::pantallasDe($modulo) as $p) {
            // Sin repetir: varias pantallas comparten permiso y nombre corto
            // («Usuarios» y «Nuevo usuario» son las dos de `seguridad.usuarios`).
            $titulos[$p['permiso']] ??= $p['t'];
        }

        return $titulos ? implode(' · ', $titulos) : $porDefecto;
    }

    /**
     * Las pantallas de un módulo, para el desplegable de la barra.
     *
     * Sale del **mismo catálogo** que la tarjeta del módulo y que los accesos
     * rápidos, con el **mismo filtro por permiso** —la clave que pide el
     * middleware—, así que el desplegable no puede ofrecer algo que conteste
     * «Sin permiso». Es la corrección de la 7.24.0 aplicada acá desde el
     * principio: un menú que anuncia lo que no se puede abrir miente.
     *
     * **No se deduplica por permiso**, al revés que `subDe()`: ahí el texto es
     * un resumen y repetir «Usuarios» dos veces sobra, pero acá cada renglón es
     * un destino distinto —«Usuarios» lleva a la lista y «Nuevo usuario» al
     * formulario— y perder uno sería perder media navegación.
     */
    public static function pantallasDe(string $modulo): array
    {
        $out = [];
        foreach (config('navegacion.pantallas', []) as $clave => $p) {
            [$titulo, $ic, $permiso] = $p;

            // **El módulo sale del PERMISO, no del nombre de la ruta.** Al
            // partir Seguridad en tres (7.57.0) las pantallas no se mudaron de
            // URL —siguen llamándose `seguridad.turnos`— así que filtrar por el
            // nombre dejaba a Personal y Configuración sin un solo renglón, y a
            // Seguridad con los ocho de antes. El permiso sí se mudó, y es lo
            // que de verdad dice a qué módulo pertenece la pantalla.
            $suModulo = str_contains((string) $permiso, '.')
                ? explode('.', (string) $permiso)[0]
                : (string) $permiso;
            if ($suModulo !== $modulo) {
                continue;
            }
            // **El cuarto valor dice si es una entrada del módulo.** Sin marcar
            // es que sí, que es el caso normal. Se marca `false` la pantalla de
            // detalle, la que no significa nada sin un dato: «Ver comprobante»
            // necesita saber cuál, «Informe para imprimir» es el papel del
            // informe que se está mirando. Ofrecerlas en un menú es prometer
            // una pantalla que no se puede abrir desde ahí.
            if (($p[3] ?? true) === false || ! Permisos::puede((string) $permiso)) {
                continue;
            }
            $url = self::url((string) $clave);
            if ($url === null) {
                continue;   // pantalla catalogada sin ruta declarada
            }
            $out[] = [
                't' => (string) $titulo, 'ic' => (string) $ic, 'url' => $url,
                'clave' => (string) $clave,
                'permiso' => (string) $permiso,
                // El quinto valor agrupa los renglones del desplegable. Con
                // ocho pantallas sueltas —Tesorería— no se ve qué va con qué.
                'grupo' => (string) ($p[4] ?? ''),
            ];
        }

        // **Las prestadas de otro módulo**, con el título de acá. La ficha del
        // equipo la abre `seguridad.usuarios` pero es donde Personal carga qué
        // hace cada profesional: sin esto, Personal ofrecía cuatro tarjetas y
        // anunciaba tres.
        // **El catálogo se lee entero y se indexa a mano**: las claves llevan
        // un punto adentro (`seguridad.usuarios`), y la notación de puntos de
        // `config()` lo interpreta como otro nivel — o sea que devuelve null
        // sin quejarse, y la pantalla no aparece.
        $catalogo = (array) config('navegacion.pantallas', []);
        foreach ((array) config('navegacion.tambien.' . $modulo, []) as $clave => $titulo) {
            $p = $catalogo[$clave] ?? null;
            if (! $p || ! Permisos::puede((string) $p[2])) {
                continue;
            }
            $url = self::url((string) $clave);
            if ($url === null) {
                continue;
            }
            // Van ADELANTE, que es donde la tarjeta del módulo las dibuja:
            // «Profesionales» es la primera de Personal, no la última.
            array_unshift($out, [
                't' => (string) $titulo, 'ic' => (string) $p[1], 'url' => $url,
                'clave' => (string) $clave, 'permiso' => (string) $p[2], 'grupo' => '',
            ]);
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

    /**
     * A qué módulo pertenece la pantalla que se está mirando.
     *
     * **Sale del PERMISO, no del nombre de la ruta**, y es la tercera vez que
     * este proyecto tropieza con lo mismo. Al partir Seguridad en tres (7.57.0)
     * las pantallas no se mudaron de URL —Personal y Configuración siguen
     * viviendo bajo `/seguridad` y llamándose `seguridad.*`— así que deducir el
     * módulo del nombre marcaba **Seguridad** en la barra estando en Personal.
     *
     * Lo mismo le había pasado al desplegable (7.58.0) y a la tarjeta del
     * módulo (7.62.0); acá quedaba el marcado del activo.
     *
     * Cae al prefijo del nombre de ruta cuando la pantalla no está en el
     * catálogo: es lo que hacía antes, y sirve para las que no son de ningún
     * módulo (el panel, mi cuenta, el portal).
     */
    public static function moduloDe(string $rutaActual): string
    {
        // La entrada del módulo: `seguridad.personal.index` es de Personal, no
        // de Seguridad. Va primero porque es la que más se equivocaba.
        foreach (config('navegacion.modulos', []) as $m) {
            if (($m['ruta'] ?? '') === $rutaActual) {
                return (string) $m['mod'];
            }
        }

        $p = self::pantalla($rutaActual);
        if ($p) {
            $permiso = (string) $p['permiso'];

            return str_contains($permiso, '.') ? explode('.', $permiso)[0] : $permiso;
        }

        return (string) strtok($rutaActual, '.');
    }

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
                $out[] = [
                    'titulo' => $p['titulo'],
                    'url' => $url,
                    'clave' => (string) $p['ruta'],
                    'ic' => (string) ($p['ic'] ?? 'circle'),
                    // Qué va en la barra de arriba. «Mi cuenta» y los
                    // recordatorios se buscan en el desplegable de la cuenta,
                    // no en la barra: ahí competirían con lo que la clienta
                    // viene a hacer.
                    'barra' => (bool) ($p['barra'] ?? false),
                ];
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
