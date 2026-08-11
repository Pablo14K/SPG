<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * No perder lo escrito al usar un alta rápida.
 *
 * Crear una sucursal desde «Nuevo usuario», un cliente desde «Nueva cita» o un
 * producto desde «Cargar stock» manda **su propio POST** y vuelve con un
 * redirect. El navegador, en ese envío, manda sólo los campos del formulario
 * chico: los del formulario grande NO viajan. Así que la pantalla se dibujaba
 * de nuevo vacía y había que cargar otra vez nombre, apellido, usuario, email…
 *
 * `withInput()` a secas NO alcanza para esto —y de hecho empeora las cosas—,
 * porque lo único que hay para reponer son los campos del alta rápida, y varios
 * se llaman igual que los del formulario grande (`nombre` está en los dos). El
 * resultado sería la ficha de la persona con el nombre de la sucursal adentro.
 *
 * Por eso el formulario chico se lleva una copia del grande: `app.js` escucha
 * su envío, serializa el formulario que le indica `data-borrador="#formUsuario"`
 * y la manda en un campo oculto `_borrador`. Acá se lo lee y se lo devuelve a la
 * sesión, donde `old()` lo encuentra al redibujar la pantalla.
 *
 *     return Borrador::conservar($destino, $request);
 *
 * Para sumar un alta rápida nueva hacen falta las dos mitades, y se olvida
 * fácil una: `data-borrador` en el formulario chico **y** esta llamada en el
 * controlador. Sin el atributo no se manda nada; sin la llamada se recibe y se
 * tira.
 */
class Borrador
{
    /** Lo que nunca vuelve a la sesión, por más que venga en el JSON. */
    private const PROHIBIDOS = ['password', 'password_confirmation', '_token', '_borrador'];

    /** Tope de tamaño: un borrador es una pantalla, no un archivo. */
    private const MAX = 20000;

    /**
     * Le pega al redirect lo que la persona tenía escrito, si vino algo.
     *
     * Si no vino nada se devuelve el redirect intacto **a propósito**: llamar a
     * `withInput()` con un arreglo vacío deja la sesión con un `old()` vacío, y
     * en una pantalla de edición eso borraría los valores que la vista muestra
     * como `old('nombre', $u->nombre)`.
     */
    public static function conservar(RedirectResponse $destino, Request $request, array $ademas = []): RedirectResponse
    {
        // `$ademas` es para cuando el alta rápida FALLA: ahí conviene reponer
        // también sus propios campos, así la persona corrige el error en vez de
        // reescribir todo. Si un nombre está en los dos lados —`nombre` está en
        // la ficha de la persona y en la de la sucursal— gana el formulario
        // grande, que es donde había más cargado.
        $datos = array_merge($ademas, self::leer($request));
        foreach (self::PROHIBIDOS as $clave) {
            unset($datos[$clave]);
        }

        return $datos ? $destino->withInput($datos) : $destino;
    }

    /** El formulario grande, tal como lo serializó el navegador. */
    public static function leer(Request $request): array
    {
        $crudo = (string) $request->input('_borrador', '');
        if ($crudo === '' || strlen($crudo) > self::MAX) {
            return [];
        }

        $datos = json_decode($crudo, true);
        if (! is_array($datos)) {
            return [];
        }

        // La contraseña no vuelve a la sesión ni por un rato: el formulario de
        // usuario tiene su campo, y el resto del sistema se cuida de no dejarla
        // nunca en texto plano. Tampoco se repone en la vista, así que sacarla
        // no le cambia nada a la persona.
        foreach (self::PROHIBIDOS as $clave) {
            unset($datos[$clave]);
        }

        return $datos;
    }
}
