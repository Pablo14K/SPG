<?php

declare(strict_types=1);

namespace Tests;

use App\Servicios\Sucursales;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Deja una sucursal elegida en la sesión.
     *
     * **Hace falta desde que el sistema es multisucursal**, y por un motivo que
     * conviene entender antes de tocarlo: una sesión de verdad no está completa
     * hasta que la persona eligió local, y `ExigePersonal` lo exige — sin eso
     * los filtros recibirían 0, que es un filtro que no filtra.
     *
     * Las pruebas armaban la sesión a mano y se saltaban ese paso. Con UNA sola
     * sucursal no se notaba: `Sesion::inicio()` la resuelve sola. Con dos, cada
     * pantalla contestaba 302 hacia «elegí la sucursal» y 19 pruebas se caían
     * —dentro del contenedor, que tenía dos locales, mientras en la máquina de
     * al lado seguían en verde—. Una batería que depende de cuántas sucursales
     * tenga la base no sirve para decir si el sistema anda.
     *
     * Toma la primera activa: cuál sea da lo mismo, lo que importa es que haya
     * una y que sea la misma en toda la prueba.
     */
    protected function conSucursal(?int $id = null): int
    {
        $id ??= (int) DB::scalar('SELECT id_sucursal FROM sucursal WHERE activo = 1 ORDER BY id_sucursal LIMIT 1');
        $nombre = (string) DB::scalar('SELECT nombre FROM sucursal WHERE id_sucursal = ?', [$id]);

        session(['id_sucursal' => $id, 'sucursal_nom' => $nombre]);

        return $id;
    }

    /**
     * Entra con usuario y contraseña y deja la sucursal resuelta.
     *
     * Es lo que hace una persona: ingresa y, si tiene más de un local, elige.
     * Las pruebas que sólo necesitan estar adentro usan esto en vez de repetir
     * los dos pasos.
     */
    protected function entrarComo(string $usuario, string $clave): void
    {
        $this->post(route('login'), ['usuario' => $usuario, 'password' => $clave, 'forzar' => 1]);

        if (Sucursales::activa() === 0) {
            $this->conSucursal();
        }
    }
}
