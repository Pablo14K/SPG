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
        $this->conMarcaDeSesion();

        return $id;
    }

    /**
     * Le copia a la sesión la marca de sesión única que tenga la cuenta.
     *
     * **Una sesión armada a mano tiene que ser creíble entera, no a medias.** La
     * 7.13.0 puso una sola sesión por cuenta: `usuario.sesion_activa` guarda cuál
     * es la buena y `ExigeSesion` echa a la que no coincida. Un `session([...])`
     * escrito en la prueba no pone esa marca, así que si la cuenta tiene una
     * sesión abierta —la dejó otra prueba que sí ingresó por el formulario, o
     * alguien usando el sistema— la petición termina en 302 hacia el ingreso.
     *
     * Eso hacía que la batería contestara **según lo que hubiera quedado de
     * antes**: 86 en verde en una máquina y 11 rotas en la de al lado, con el
     * mismo código y los mismos datos. Es el mismo problema que resolvieron
     * `conSucursal()` en la 7.31.3 y `clienteLibreHoy()` acá: el defecto está en
     * el andamiaje, no en la regla, y la regla no se toca.
     */
    protected function conMarcaDeSesion(): void
    {
        $uid = (int) session('uid', 0);
        if ($uid) {
            session(['sesion_marca' => DB::scalar('SELECT sesion_activa FROM usuario WHERE id_usuario = ?', [$uid])]);
        }
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

    /**
     * Una clienta que hoy no tenga ninguna cita.
     *
     * **`peluqueria_test` trae el mes simulado, y ese mes se mueve con el
     * calendario.** Una prueba que agarra la primera clienta (`LIMIT 1`) y le
     * agenda para hoy funciona todos los días salvo aquellos en que la
     * simulación ya le puso una cita: ahí `trg_citaserv_bi` la rechaza con «esa
     * clienta ya tiene ese servicio agendado para el mismo día». Pasó el
     * 17/08/2026 con dos pruebas que el día anterior estaban en verde.
     *
     * Es el mismo defecto que la 7.31.3 corrigió con `conSucursal()`: **una
     * batería que dice cosas distintas según el día —o según cuántos locales
     * haya— no sirve para decir si el sistema anda.** El disparador estaba
     * haciendo su trabajo; el que elegía mal era el andamiaje.
     *
     * La otra salida —mudar la cita lejos, como se hizo en la 7.28.0— acá no
     * vale: atender exige que la cita ya haya llegado, así que tiene que ser hoy.
     */
    protected function clienteLibreHoy(): int
    {
        return (int) DB::scalar(
            'SELECT c.id_cliente
               FROM cliente c
              WHERE c.activo = 1
                AND NOT EXISTS (SELECT 1 FROM cita ci
                                 WHERE ci.id_cliente = c.id_cliente
                                   AND DATE(ci.fecha_hora) = CURDATE())
              ORDER BY c.id_cliente LIMIT 1'
        );
    }
}
