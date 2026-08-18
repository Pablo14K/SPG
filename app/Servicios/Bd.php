<?php

declare(strict_types=1);

namespace App\Servicios;

use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * El puente con la lógica que vive en la base.
 *
 * La regla número uno del proyecto es que la lógica de negocio vive en la base
 * de datos: 20 procedimientos, 30 funciones y 17 disparadores que ya validan
 * disponibilidad, numeración de la SET, stock, arqueo de caja y descuentos.
 * PHP no la reimplementa: la llama. Esta clase es el único lugar que sabe
 * *cómo* se la llama, para que los servicios de arriba se ocupen solo del qué.
 *
 * Tres cosas que resuelve y que no son detalles:
 *
 *  · **El cursor.** Un CALL deja un resultado abierto; si no se lo cierra, la
 *    siguiente consulta de la misma conexión revienta con «Cannot execute
 *    queries while other unbuffered queries are active». Por eso se trabaja
 *    con el PDO de abajo y se llama a closeCursor().
 *
 *  · **Los parámetros de salida.** MySQL los devuelve en una variable de
 *    sesión (`@x`), que hay que leer con un SELECT aparte sobre la MISMA
 *    conexión.
 *
 *  · **El mensaje.** Los procedimientos avisan con SIGNAL SQLSTATE '45000' y
 *    un texto pensado para un programador. Al usuario hay que contarle qué
 *    pasó en su idioma, y para eso está traducir().
 */
class Bd
{
    /**
     * Ejecuta un procedimiento que no devuelve nada.
     */
    public static function procedimiento(string $nombre, array $parametros = []): void
    {
        $pdo = DB::connection()->getPdo();
        $st = $pdo->prepare('CALL ' . $nombre . '(' . self::marcadores(count($parametros)) . ')');
        $st->execute(array_values($parametros));
        $st->closeCursor();
    }

    /**
     * Ejecuta un procedimiento con un parámetro de salida y devuelve su valor.
     *
     * Ejemplo: sp_agendar_cita(?,?,?,?,?, @salida) → el id de la cita nueva.
     */
    public static function procedimientoConSalida(string $nombre, array $parametros = [], string $variable = 'spg_salida'): ?string
    {
        $pdo = DB::connection()->getPdo();
        $marcadores = self::marcadores(count($parametros));
        $sql = 'CALL ' . $nombre . '(' . ($marcadores === '' ? '' : $marcadores . ', ') . '@' . $variable . ')';

        $st = $pdo->prepare($sql);
        $st->execute(array_values($parametros));
        $st->closeCursor();

        // La variable de sesión se lee sobre la misma conexión, si no viene NULL
        $valor = $pdo->query('SELECT @' . $variable)->fetchColumn();

        return $valor === false || $valor === null ? null : (string) $valor;
    }

    /**
     * Igual que el anterior pero devolviendo un entero, que es el caso normal
     * (todos los procedimientos del sistema devuelven un id).
     */
    public static function idDe(string $nombre, array $parametros = [], string $variable = 'spg_salida'): int
    {
        return (int) self::procedimientoConSalida($nombre, $parametros, $variable);
    }

    /**
     * Llama a una función de la base: fn_producto_stock(?), fn_factura_total(?)…
     *
     * Se le pasa la expresión completa para poder escribir cosas como
     * `fn_verificar_disponibilidad(?, ?, ?, NULL, ?)` sin inventar una firma.
     */
    public static function funcion(string $expresion, array $parametros = []): mixed
    {
        return DB::scalar('SELECT ' . $expresion, array_values($parametros));
    }

    /**
     * Envuelve algo en una transacción.
     *
     * NO es decorativo donde se agenda: `sp_agendar_cita` y
     * `sp_reprogramar_cita` toman un candado sobre la fila del profesional
     * (SELECT … FOR UPDATE) y el candado se suelta al confirmar. Sin
     * transacción dura lo que dura la consulta, y dos personas pueden quedarse
     * con el mismo horario. Se midieron 47 citas en 16 franjas con 46 solapes
     * antes de que existiera el candado.
     */
    public static function enTransaccion(Closure $trabajo): mixed
    {
        return DB::transaction($trabajo);
    }

    /**
     * Traduce el error de la base a algo que una persona entienda.
     *
     * El mapa va de fragmento buscado en el mensaje original → mensaje nuestro.
     * Se recorre en orden, así que lo más específico va primero.
     */
    public static function traducir(Throwable $e, array $mapa, string $porDefecto): string
    {
        $msg = $e->getMessage();
        foreach ($mapa as $fragmento => $amable) {
            if (stripos($msg, (string) $fragmento) !== false) {
                return $amable;
            }
        }

        return $porDefecto;
    }

    private static function marcadores(int $cuantos): string
    {
        return $cuantos > 0 ? implode(', ', array_fill(0, $cuantos, '?')) : '';
    }
}
