<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\DB;

/**
 * En qué sucursal se está trabajando.
 *
 * El sistema dejaba crear sucursales y asignarles gente, pero la operación
 * entera pasaba sobre una sola: la agenda, la caja y el stock no sabían en qué
 * local ocurrían. Acá vive la pieza que faltaba — quién puede entrar a qué
 * sucursal, y cuál está activa.
 *
 * **La sucursal activa vive en la sesión, no en la cuenta.** `usuario.id_sucursal`
 * dice dónde trabaja habitualmente esa persona; `usuario_sucursal` dice a cuáles
 * puede entrar. Cuál eligió HOY es de esta sesión, porque la misma persona puede
 * cubrir en otro local por la tarde sin que eso le cambie la ficha.
 */
class Sucursales
{
    /**
     * Las sucursales a las que esta persona puede entrar.
     *
     * El Administrador entra a todas —es superadministrador, la misma regla que
     * `Permisos::esAdmin()`—. El resto, a las que tenga asignadas en
     * `usuario_sucursal`; si no tiene ninguna asignada se cae a la de su ficha,
     * para que una cuenta vieja no quede sin poder entrar a ningún lado.
     */
    public static function delUsuario(?int $idUsuario = null, ?int $rol = null): array
    {
        $idUsuario ??= (int) session('uid', 0);
        $rol ??= (int) session('rol', 0);

        if (! $idUsuario) {
            return [];
        }

        if (Permisos::esAdmin($rol)) {
            return DB::select('SELECT id_sucursal, nombre, direccion, ciudad FROM sucursal
                                WHERE activo = 1 ORDER BY nombre');
        }

        $suyas = DB::select(
            'SELECT s.id_sucursal, s.nombre, s.direccion, s.ciudad
               FROM usuario_sucursal us
               JOIN sucursal s ON s.id_sucursal = us.id_sucursal AND s.activo = 1
              WHERE us.id_usuario = ?
              ORDER BY s.nombre', [$idUsuario]
        );
        if ($suyas) {
            return $suyas;
        }

        // Sin asignaciones: la de su ficha. Es la red para las cuentas creadas
        // antes de que las sucursales importaran.
        return DB::select(
            'SELECT s.id_sucursal, s.nombre, s.direccion, s.ciudad
               FROM usuario u JOIN sucursal s ON s.id_sucursal = u.id_sucursal AND s.activo = 1
              WHERE u.id_usuario = ?', [$idUsuario]
        );
    }

    /** ¿Puede esta persona entrar a esa sucursal? */
    public static function puedeEntrar(int $idSucursal, ?int $idUsuario = null, ?int $rol = null): bool
    {
        foreach (self::delUsuario($idUsuario, $rol) as $s) {
            if ((int) $s->id_sucursal === $idSucursal) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deja esa sucursal como la activa de la sesión.
     *
     * Comprueba el permiso acá y no en la pantalla: el id viaja en un
     * formulario, y esconder una opción no impide mandar otra.
     */
    public static function entrar(int $idSucursal): bool
    {
        if (! self::puedeEntrar($idSucursal)) {
            return false;
        }

        $s = DB::selectOne('SELECT nombre FROM sucursal WHERE id_sucursal = ?', [$idSucursal]);
        session(['id_sucursal' => $idSucursal, 'sucursal_nom' => (string) ($s->nombre ?? '')]);

        return true;
    }

    /**
     * El pedacito de `WHERE` que aísla una consulta por sucursal.
     *
     *     $soloMias .= Sucursales::filtro('c', $par);   // c = alias de la tabla
     *
     * Va como método y no escrito a mano en cada consulta por lo mismo que
     * `veTodaLaAgenda()`: son muchas pantallas, y una que se olvide del filtro
     * no falla — muestra de más, que es la peor forma de romperse.
     *
     * **Con 0 no filtra nada**, y es a propósito: es lo que corre sin sesión
     * —el cron de avisos, los comandos, las pruebas—, donde no hay una
     * sucursal «activa» y filtrar por ninguna dejaría todo en blanco.
     *
     * El marcador lleva un sufijo propio porque la conexión va con las
     * preparadas nativas de MySQL, que no admiten repetir un nombre.
     */
    public static function filtro(string $alias, array &$par, string $marca = 'suc'): string
    {
        $activa = self::activa();
        if (! $activa) {
            return '';
        }

        $par[$marca] = $activa;

        return " AND $alias.id_sucursal = :$marca";
    }

    /** La sucursal en la que se está trabajando ahora (0 si todavía no eligió). */
    public static function activa(): int
    {
        return (int) session('id_sucursal', 0);
    }

    public static function nombreActiva(): string
    {
        return (string) session('sucursal_nom', '');
    }

    /**
     * ¿Hace falta que elija? Con una sola sucursal se entra sola: preguntar
     * algo que tiene una única respuesta es hacer perder un clic.
     */
    public static function debeElegir(): bool
    {
        return count(self::delUsuario()) > 1;
    }

    /**
     * Al entrar: si tiene una sola, se la deja puesta y no se pregunta nada.
     * Devuelve true si quedó resuelta.
     */
    public static function resolverAlIngresar(): bool
    {
        $suyas = self::delUsuario();
        if (count($suyas) === 1) {
            return self::entrar((int) $suyas[0]->id_sucursal);
        }

        return false;
    }
}
