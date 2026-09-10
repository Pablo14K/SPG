<?php

declare(strict_types=1);

namespace App\Servicios;

/**
 * Los tipos de alias con los que se transfiere en Paraguay.
 *
 * **En el SIPAP el alias es el único dato necesario para transferir**:
 * reemplaza al número de cuenta, a la entidad y al nombre del destinatario. Y
 * no es texto libre — es uno de cuatro, y saber cuál importa por dos motivos:
 * valida el valor, y sobre todo **le dice a la clienta cómo buscarlo** en la
 * app de su banco, que es como funcionan esas pantallas.
 *
 * **Vive acá y no en el controlador porque lo miran los dos lados**: la
 * pantalla donde el salón carga la cuenta y la del portal donde la clienta la
 * lee. Escrito dos veces se desfasa, y entonces el salón guarda «CELULAR» y la
 * clienta lee «alias» a secas.
 */
class Pagos
{
    /** Cómo se llama cada tipo, tal como se le muestra a una persona. */
    public const ALIAS_TIPOS = [
        'CI' => 'Cédula',
        'RUC' => 'RUC',
        'CELULAR' => 'Nº de celular',
        'EMAIL' => 'Correo',
    ];

    /**
     * Cómo se ve cada uno, para que la pantalla lo muestre de ejemplo.
     *
     * Un placeholder que cambia con el tipo es lo que evita el error de tipeo
     * antes de que ocurra: quien ve «80012345-6» no escribe el RUC sin guion.
     */
    public const ALIAS_EJEMPLOS = [
        'CI' => '4200000',
        'RUC' => '80012345-6',
        'CELULAR' => '0981123456',
        'EMAIL' => 'salon@correo.com',
    ];

    /**
     * Qué caracteres deja escribir cada tipo (`data-solo` de `app.js`).
     *
     * **La pantalla no puede ser más estricta que el servidor**, así que cada
     * juego copia la regla de `Persona::error()`. El correo queda libre: no hay
     * juego de caracteres que lo describa sin dejar afuera uno válido.
     */
    public const ALIAS_FILTROS = [
        'CI' => 'numeros',
        'RUC' => 'ruc',
        'CELULAR' => 'telefono',
        'EMAIL' => '',
    ];

    /**
     * El rótulo del alias con su tipo entre paréntesis: «Alias (Cédula)».
     *
     * **Antes el tipo iba abajo, como una instrucción**: «buscalo por cédula».
     * Se reportó que confundía — un renglón más debajo de un número, en un
     * bloque donde ya hay cuatro datos con su rótulo. Puesto en el propio
     * rótulo, el tipo se lee como lo que es: qué clase de alias es ese número.
     *
     * Sin tipo cargado queda «Alias» a secas, que es lo honesto: el salón
     * puede haberlo cargado sin decir de cuál se trata.
     */
    public static function rotuloAlias(?string $tipo): string
    {
        $t = self::ALIAS_TIPOS[(string) $tipo] ?? '';

        return $t !== '' ? 'Alias (' . $t . ')' : 'Alias';
    }
}
