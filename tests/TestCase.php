<?php

declare(strict_types=1);

namespace Tests;

use App\Servicios\Agenda;
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

    /**
     * Una cita futura que ocupa la agenda, **creada por la prueba**.
     *
     * **Media docena de pruebas la buscaban en la base y se salteaban si no
     * la encontraban**, y el día que el mes simulado se quedó sin citas
     * futuras —que llega solo, con el calendario— cinco dejaron de medir
     * nada. Se saltean, así que ni siquiera se ven: la batería informa
     * «157 passed» y la cobertura se adelgaza en silencio.
     *
     * Es el defecto que este proyecto ya tiene anotado —*una prueba tiene que
     * GARANTIZAR su premisa, no esperar a encontrarla*— aplicado al caso que
     * más veces lo dispara.
     *
     * El horario NO se inventa: sale de `Agenda::slots()`, que es el mismo
     * motor que dibuja la pantalla, así que la cita cae en un hueco que de
     * verdad está libre para alguien que trabaja ese día. Si se eligiera una
     * fecha fija, la prueba mediría el calendario en vez de la regla.
     *
     * Devuelve la fila con `id_cita`, `id_cliente`, `id_usuario`,
     * `fecha_hora`, `id_sucursal` y `dur`. Vive dentro de la transacción de
     * `DatabaseTransactions`, así que no queda nada en la base.
     */
    protected function citaFuturaAgendada(?int $idSucursal = null): object
    {
        $suc = $idSucursal ?: (int) DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1');

        $srv = DB::selectOne(
            'SELECT id_servicio, duracion_min FROM servicio
              WHERE activo = 1 AND duracion_min > 0 ORDER BY duracion_min LIMIT 1'
        );
        $this->assertNotNull($srv, 'La premisa: hace falta al menos un servicio en el catálogo.');
        $dur = (int) $srv->duracion_min;

        // El primer hueco de verdad libre, mirando desde mañana: hoy puede
        // haber pasado ya la última hora del turno.
        $cuando = null;
        $quien = 0;
        for ($i = 1; $i <= 45 && $cuando === null; $i++) {
            $dia = date('Y-m-d', strtotime("+$i day"));
            foreach (Agenda::slots(null, $dia, $dur, null, $suc, [(int) $srv->id_servicio]) as $h) {
                if (! empty($h['profesionales'])) {
                    $cuando = $dia . ' ' . $h['hora'] . ':00';
                    $quien = (int) $h['profesionales'][0];
                    break;
                }
            }
        }
        $this->assertNotNull($cuando,
            'La premisa: hace falta un horario libre en los próximos 45 días para armar la cita.');

        // La clienta no puede ser una que ya tenga ese servicio ese día:
        // `trg_citaserv_bi` lo rechaza, y la prueba moriría por otra regla.
        $cliente = (int) DB::scalar(
            'SELECT c.id_cliente FROM cliente c
              WHERE c.activo = 1
                AND NOT EXISTS (SELECT 1 FROM cita ci
                                  JOIN cita_servicio cs ON cs.id_cita = ci.id_cita
                                  JOIN estado_cita ec ON ec.id_estado_cita = ci.id_estado_cita
                                 WHERE ci.id_cliente = c.id_cliente
                                   AND ec.bloquea_agenda = 1
                                   AND DATE(ci.fecha_hora) = DATE(?)
                                   AND cs.id_servicio = ?)
              ORDER BY c.id_cliente LIMIT 1', [$cuando, (int) $srv->id_servicio]
        );
        $this->assertNotSame(0, $cliente, 'La premisa: hace falta una clienta libre ese día.');

        DB::insert('INSERT INTO cita (id_cliente, id_usuario, id_sucursal, fecha_hora, id_estado_cita)
                    VALUES (?,?,?,?,1)', [$cliente, $quien, $suc, $cuando]);
        $id = (int) DB::scalar('SELECT LAST_INSERT_ID()');
        DB::insert('INSERT INTO cita_servicio (id_cita, id_servicio) VALUES (?,?)',
                   [$id, (int) $srv->id_servicio]);

        return (object) [
            'id_cita' => $id,
            'id_cliente' => $cliente,
            'id_usuario' => $quien,
            'id_sucursal' => $suc,
            'fecha_hora' => $cuando,
            'dia' => substr($cuando, 0, 10),
            'dur' => (int) DB::scalar('SELECT fn_cita_duracion(?)', [$id]),
        ];
    }

    /**
     * El cajón de una sucursal, creándolo si no lo tiene.
     *
     * **`caja` es una SESIÓN sobre un cajón desde la 7.69.0**, así que una
     * prueba que abra caja a mano necesita decir sobre cuál. Se crea al vuelo
     * en vez de darlo por hecho: `peluqueria_test` puede no tenerlo para una
     * sucursal recién insertada por la propia prueba.
     */
    protected function cajonDe(int $idSucursal): int
    {
        $id = DB::scalar('SELECT id_caja_fisica FROM caja_fisica WHERE id_sucursal = ? LIMIT 1',
            [$idSucursal]);
        if ($id) {
            return (int) $id;
        }

        DB::insert("INSERT INTO caja_fisica (id_sucursal, nombre) VALUES (?, 'Caja 1')", [$idSucursal]);

        return (int) DB::scalar('SELECT LAST_INSERT_ID()');
    }
}
