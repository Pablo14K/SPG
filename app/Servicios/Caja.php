<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\DB;
use Throwable;

// El saldo, la apertura y el cierre los resuelve la base: acá sólo se llama.

/**
 * Las cajas del salón.
 *
 * **`caja` es una SESIÓN de trabajo, no el cajón.** El cajón es
 * `caja_fisica` —tiene nombre y vive en un local— y cada apertura abre una
 * sesión sobre él. Antes el cajón no existía en el modelo, así que «una caja
 * abierta por sucursal» era en realidad «un cajón por local» sin decirlo: un
 * salón con dos puestos de cobro no lo podía representar.
 *
 * Se trabaja con **una sesión abierta por cajón**, y sin caja abierta no se
 * mueve un guaraní: el movimiento quedaría fuera del arqueo y el cierre no
 * cerraría.
 *
 * El saldo lo calcula `fn_caja_saldo` en la base, y devuelve el EFECTIVO que
 * tiene que estar físicamente en el cajón para contarlo al cerrar: lo que
 * entra por tarjeta o transferencia se registra igual pero no toca el cajón.
 */
class Caja
{
    /**
     * `false` significa «todavía no se preguntó»; `null`, «no hay caja
     * abierta». Son dos cosas distintas: sin distinguirlas, cada pantalla que
     * mira la caja cerrada volvería a consultar la base.
     */
    private static object|false|null $cache = false;

    /**
     * La caja abierta de esta persona, o la primera del local.
     *
     * **Con varios cajones hay que elegir**, y se prefiere el que abrió quien
     * está operando: es su arqueo el que va a tener que cuadrar. Si no abrió
     * ninguno, se toma cualquiera del local — alguien puede cobrar en el
     * puesto de otro.
     */
    public static function abierta(): ?object
    {
        if (self::$cache !== false) {
            return self::$cache;
        }
        try {
            self::$cache = DB::selectOne(
                "SELECT c.id_caja, c.id_usuario, c.id_caja_fisica, cf.nombre AS caja_nombre,
                        c.fecha_apertura, c.monto_inicial,
                        CONCAT(pe_u.nombre,' ',pe_u.apellido) AS responsable,
                        fn_caja_saldo(c.id_caja) AS saldo
                   FROM caja c
                   JOIN caja_fisica cf ON cf.id_caja_fisica = c.id_caja_fisica
                   JOIN usuario u   ON u.id_usuario = c.id_usuario
                   JOIN persona pe_u ON pe_u.id_persona = u.id_persona
                  WHERE c.id_estado_caja = 1
                    AND (:s = 0 OR c.id_sucursal = :s2)
                  ORDER BY (c.id_usuario = :yo) DESC, c.fecha_apertura DESC LIMIT 1",
                // **La caja es del local, no del salón.** Cada sucursal cuenta
                // su propio cajón: sin este filtro, abrir la caja en un local
                // dejaba «caja abierta» en todos, y el arqueo de uno se comía
                // los cobros del otro. El 0 es la red para lo que corre sin
                // sesión —el cron, un comando—: ahí no se filtra.
                ['s' => Sucursales::activa(), 's2' => Sucursales::activa(),
                 'yo' => (int) session('uid', 0)]
            );
        } catch (Throwable) {
            self::$cache = null;
        }

        return self::$cache;
    }

    /**
     * Las cajas abiertas de un local, para elegir a cuál entra la plata.
     *
     * **Con una sola no hay que preguntar nada**: la pantalla no dibuja el
     * combo y el procedimiento la resuelve como siempre. Con dos o más, elegir
     * mal deja el arqueo de otra persona descuadrado y nada lo dice.
     *
     * @return array<int, object>
     */
    public static function abiertasDe(?int $idSucursal = null): array
    {
        $suc = $idSucursal ?: Sucursales::activa();

        return DB::select(
            "SELECT c.id_caja, cf.nombre, su.nombre AS sucursal,
                    TRIM(CONCAT_WS(' ', pe.nombre, pe.apellido)) AS responsable,
                    (c.id_usuario = ?) AS es_mia
               FROM caja c
               JOIN caja_fisica cf ON cf.id_caja_fisica = c.id_caja_fisica
               JOIN sucursal su ON su.id_sucursal = c.id_sucursal
               LEFT JOIN usuario u  ON u.id_usuario = c.id_usuario
               LEFT JOIN persona pe ON pe.id_persona = u.id_persona
              WHERE c.id_estado_caja = 1 AND (? = 0 OR c.id_sucursal = ?)
              ORDER BY es_mia DESC, cf.nombre",
            [(int) session('uid', 0), $suc, $suc]
        );
    }

    public static function olvidar(): void
    {
        self::$cache = false;
    }

    /**
     * Abre una sesión sobre un CAJÓN y devuelve su id.
     *
     * **La sucursal ya no va como parámetro: sale del cajón.** Guardarla
     * aparte sería poder contradecirse — un cajón de un local con la sesión
     * marcada en otro.
     */
    public static function abrir(int $idUsuario, float $montoInicial, int $idCajaFisica,
        string $observacion = ''): int
    {
        $id = Bd::idDe('sp_abrir_caja', [$idUsuario, $montoInicial, $idCajaFisica, $observacion]);
        self::olvidar();

        return $id;
    }

    /**
     * Los cajones de un local, con el estado de su sesión.
     *
     * Es lo que dibuja la lista de Cajas: cada cajón dice si está abierto,
     * quién lo abrió y desde cuándo. Un cajón sin sesión abierta sale igual —
     * es justamente el que se puede abrir.
     */
    public static function cajones(?int $idSucursal = null, array $filtros = []): array
    {
        $w = ['cf.activo = 1'];
        $par = [];

        if ($idSucursal) {
            $w[] = 'cf.id_sucursal = :suc';
            $par['suc'] = $idSucursal;
        }
        if (($filtros['q'] ?? '') !== '') {
            $w[] = '(cf.nombre LIKE :q OR su.nombre LIKE :q2)';
            $par['q'] = '%' . $filtros['q'] . '%';
            $par['q2'] = '%' . $filtros['q'] . '%';
        }

        $sql = "SELECT cf.id_caja_fisica, cf.nombre, cf.id_sucursal, su.nombre AS sucursal,
                       c.id_caja, c.fecha_apertura, c.monto_inicial,
                       TRIM(CONCAT_WS(' ', pe.nombre, pe.apellido)) AS responsable,
                       fn_caja_saldo(c.id_caja) AS saldo
                  FROM caja_fisica cf
                  JOIN sucursal su ON su.id_sucursal = cf.id_sucursal
                  LEFT JOIN caja c ON c.id_caja_fisica = cf.id_caja_fisica AND c.id_estado_caja = 1
                  LEFT JOIN usuario u  ON u.id_usuario = c.id_usuario
                  LEFT JOIN persona pe ON pe.id_persona = u.id_persona
                 WHERE " . implode(' AND ', $w);

        // El estado se filtra sobre el resultado del LEFT JOIN: «abierta» es
        // tener sesión, «cerrada» es no tenerla.
        if (($filtros['estado'] ?? '') === '1') {
            $sql .= ' AND c.id_caja IS NOT NULL';
        } elseif (($filtros['estado'] ?? '') === '0') {
            $sql .= ' AND c.id_caja IS NULL';
        }

        return DB::select($sql . ' ORDER BY su.nombre, cf.nombre', $par);
    }

    /**
     * Cierra la caja con el arqueo: cuánto se contó y quién lo contó.
     *
     * **El conteo es lo que convierte el cierre en un arqueo.** Antes esto
     * sólo marcaba la caja como cerrada: el sistema sabía cuánto debería
     * haber y nunca preguntaba cuánto hay, así que no podía decir si cuadró.
     */
    public static function cerrar(int $idCaja, float $contado, int $idUsuario,
        string $observacion = '', string $motivo = ''): void
    {
        Bd::procedimiento('sp_cerrar_caja', [$idCaja, $contado, $idUsuario, $observacion, $motivo]);
        self::olvidar();
    }

    /**
     * Sobrante (+) o faltante (−) de un arqueo. NULL si no se contó.
     *
     * **No se guarda: se calcula.** Es `contado − esperado`, o sea una
     * columna derivada, y guardarla la dejaría separarse del valor real el
     * día que se anule un movimiento viejo — la regla número dos.
     */
    public static function diferencia(int $idCaja): ?float
    {
        $d = Bd::funcion('fn_caja_diferencia(?)', [$idCaja]);

        return $d === null ? null : (float) $d;
    }

    /** Cuánto efectivo hay ahora mismo en el cajón. */
    public static function saldo(int $idCaja): float
    {
        return (float) Bd::funcion('fn_caja_saldo(?)', [$idCaja]);
    }

    /**
     * ¿Este medio de pago mueve el cajón?
     *
     * `fn_caja_saldo` sólo cuenta el efectivo: lo que entra por tarjeta o
     * transferencia va a la cuenta del salón, no al cajón que se cuenta al
     * cerrar. Lo mismo al salir — pagarle a un proveedor por transferencia no
     * saca un guaraní de la caja.
     */
    public static function esEfectivo(int $idMetodoPago): bool
    {
        return DB::scalar('SELECT tipo FROM metodo_pago WHERE id_metodo_pago = ?', [$idMetodoPago]) === 'EFECTIVO';
    }
}
