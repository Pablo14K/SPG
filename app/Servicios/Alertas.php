<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Lo que está pasando AHORA y alguien tiene que mirar: la campanita del panel.
 *
 * No es `Pendientes`, y la diferencia vale tenerla escrita. `Pendientes` dice
 * qué falta CARGAR —un timbrado, un turno, el correo—: decisiones sin tomar,
 * que se resuelven una vez. Esto dice qué está ocurriendo en la operación de
 * hoy y se corrige en el momento: una caja que quedó abierta desde ayer, un
 * producto que llegó al mínimo. Un aviso de configuración y uno de operación mezclados
 * en la misma lista hacen que el segundo se lea como el primero, y el segundo
 * es el que no puede esperar.
 *
 * Cada renglón lleva dónde se resuelve y con qué permiso, igual que
 * `Pendientes`: la campanita de quien no maneja la caja no tiene por qué
 * sonar por una caja.
 */
class Alertas
{
    /** @var list<array{nivel:string,que:string,donde:string,ruta:?string,permiso:string,clave:string}> */
    private static array $puntos = [];

    /**
     * Sólo lo que ESTA persona puede resolver, en el local en el que está.
     *
     * Cada renglón viene además con **si ya lo vio** (`visto`), que es lo que
     * hace bajar el numerito rojo de la campanita al abrirla. Ver un aviso no
     * lo resuelve —la caja sigue abierta—, así que **el renglón se queda en la
     * bandeja**: lo que cambia es que deja de contar, como cualquier bandeja
     * de correo.
     *
     * @return list<array{nivel:string,que:string,donde:string,ruta:?string,permiso:string,clave:string,visto:bool}>
     */
    public static function mias(): array
    {
        $puntos = array_values(array_filter(self::todas(),
            static fn (array $a) => Permisos::puede($a['permiso'])));

        $vistas = self::vistas(array_column($puntos, 'clave'));

        return array_map(
            static fn (array $a) => $a + ['visto' => in_array($a['clave'], $vistas, true)],
            $puntos
        );
    }

    /**
     * Cuáles de estas claves ya vio quien está en sesión.
     *
     * @param  list<string>  $claves
     * @return list<string>
     */
    private static function vistas(array $claves): array
    {
        $uid = (int) session('uid');
        if (! $uid || ! $claves) {
            return [];
        }

        try {
            return array_column(DB::select(
                'SELECT clave FROM alerta_vista WHERE id_usuario = ? AND clave IN ('
                . implode(',', array_fill(0, count($claves), '?')) . ')',
                array_merge([$uid], $claves)
            ), 'clave');
        } catch (Throwable) {
            // La bandeja no puede tirar la pantalla: sin la tabla, todo cuenta.
            return [];
        }
    }

    /**
     * Deja marcado que esta persona ya vio estos avisos.
     *
     * Lo llama la campanita al abrirse. **Sólo se aceptan claves que hoy
     * existan para esta persona**: el navegador manda una lista, y sin eso se
     * podría marcar como visto cualquier cosa —o llenar la tabla de basura—
     * escribiendo otra cosa en el POST.
     *
     * @param  list<string>  $claves
     * @return int  cuántas quedaron marcadas
     */
    public static function marcarVistas(array $claves): int
    {
        $uid = (int) session('uid');
        if (! $uid) {
            return 0;
        }

        $validas = array_values(array_intersect(
            array_map('strval', $claves),
            array_column(self::mias(), 'clave')
        ));
        if (! $validas) {
            return 0;
        }

        foreach ($validas as $clave) {
            DB::statement(
                'INSERT INTO alerta_vista (id_usuario, clave) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE visto_en = NOW()', [$uid, $clave]
            );
        }

        return count($validas);
    }

    /**
     * Todo lo que está pasando, sin filtrar por quién mira.
     *
     * @return list<array{nivel:string,que:string,donde:string,ruta:?string,permiso:string,clave:string}>
     */
    public static function todas(): array
    {
        self::$puntos = [];

        // Cada uno en su propio `try`: un aviso que falla no puede tirar el
        // panel —es un aviso—, y tampoco tiene por qué llevarse a los demás.
        foreach (['cajasAbiertasDeMas', 'faltaStock'] as $punto) {
            try {
                self::$punto();
            } catch (Throwable) {
                continue;
            }
        }

        return self::$puntos;
    }

    /**
     * Productos que cayeron al mínimo o por debajo: hay que reponer.
     *
     * **Vivía en el panel como un número —«Falta stock: 3»— y salió de ahí**
     * (pedido del usuario, 7.118.0): un número suelto no dice qué falta ni a
     * dónde ir, y sólo se veía desde el inicio. Acá va con los nombres, con
     * el enlace a la lista de compras, y se ve desde cualquier pantalla. Del
     * local en el que se está parado: el faltante del otro no es algo que
     * esta persona pueda resolver desde acá.
     *
     * La identidad del aviso es **QUÉ falta**, no cuántos: si mañana se
     * agrega un producto a la lista, es un aviso nuevo y vuelve a contar —
     * marcarlo visto ayer no tapa el que apareció hoy—. Se resuelve solo al
     * reponer: sin productos por debajo del mínimo, no hay renglón.
     */
    private static function faltaStock(): void
    {
        $par = [];
        $filas = DB::select(
            'SELECT id_producto, nombre, faltante FROM vw_producto_bajo_stock WHERE 1=1'
            . Sucursales::filtro('vw_producto_bajo_stock', $par) . ' ORDER BY nombre', $par
        );
        if (! $filas) {
            return;
        }

        $n = count($filas);
        $nombres = array_map(static fn ($f) => (string) $f->nombre, $filas);
        $lista = implode(', ', array_slice($nombres, 0, 3))
            . ($n > 3 ? ' y ' . ($n - 3) . ' más' : '');
        $ids = array_map(static fn ($f) => (int) $f->id_producto, $filas);
        sort($ids);

        self::$puntos[] = [
            'nivel' => 'STOCK',
            'que' => ($n === 1 ? 'Un producto llegó al mínimo: ' : $n . ' productos llegaron al mínimo: ')
                . $lista . '. Hay que reponer antes de que falte en el sillón.',
            'donde' => 'Inventario → Stock',
            'ruta' => 'inventario.stock',
            'permiso' => 'inventario.stock',
            'clave' => 'stock:' . (int) Sucursales::activa() . ':' . substr(md5(implode(',', $ids)), 0, 12),
        ];
    }

    /**
     * Una caja abierta desde hace demasiado.
     *
     * **La caja se abre a la mañana y se cierra a la noche, con su arqueo.**
     * Una que sigue abierta al día siguiente es una que nadie contó: los
     * cobros del día nuevo entran al mismo arqueo que los de ayer, y cuando
     * alguien la cierre la diferencia ya no dice de qué día vino. Se avisa a
     * partir de `sgp.caja.horas_abierta_aviso` horas, o si la apertura fue
     * otro día —lo que pase primero—.
     *
     * Del local en el que se está parado: la caja del otro local no es algo
     * que esta persona pueda cerrar desde acá.
     */
    private static function cajasAbiertasDeMas(): void
    {
        $horas = max(1, (int) config('sgp.caja.horas_abierta_aviso', 12));
        $par = ['h' => $horas];
        $filtro = Sucursales::filtro('c', $par);

        $cajas = DB::select(
            "SELECT c.id_caja, cf.nombre, c.fecha_apertura,
                    CONCAT(pe.nombre, ' ', pe.apellido) AS responsable,
                    TIMESTAMPDIFF(HOUR, c.fecha_apertura, NOW()) AS horas
               FROM caja c
               JOIN caja_fisica cf ON cf.id_caja_fisica = c.id_caja_fisica
               JOIN usuario u ON u.id_usuario = c.id_usuario
               JOIN persona pe ON pe.id_persona = u.id_persona
              WHERE c.id_estado_caja = 1
                AND (TIMESTAMPDIFF(HOUR, c.fecha_apertura, NOW()) >= :h
                     OR DATE(c.fecha_apertura) < CURDATE())
                $filtro
              ORDER BY c.fecha_apertura", $par
        );

        foreach ($cajas as $c) {
            $h = (int) $c->horas;
            $desde = $h >= 48
                ? 'hace ' . intdiv($h, 24) . ' días'
                : ($h >= 24 ? 'desde ayer' : 'hace ' . $h . ' horas');

            self::$puntos[] = [
                'nivel' => 'CAJA',
                'que' => 'La ' . $c->nombre . ' está abierta ' . $desde
                    . ' (la abrió ' . $c->responsable . ' el ' . fecha($c->fecha_apertura, 'd/m') . ' a las '
                    . fecha($c->fecha_apertura, 'H:i') . '). Hay que hacer el arqueo y cerrarla: '
                    . 'con dos días en el mismo arqueo, una diferencia ya no dice de qué día vino.',
                'donde' => 'Tesorería → Cajas',
                // **`facturacion.cajas`, la lista, y no `facturacion.caja`**:
                // aquélla es la pantalla de UNA caja, que necesita su id, así
                // que `Navegacion::url()` devuelve null y el aviso salía sin
                // enlace — un aviso que no lleva a ningún lado es la mitad
                // de un aviso.
                'ruta' => 'facturacion.cajas',
                'permiso' => 'facturacion.caja',
                // **La identidad es el CAJÓN, no el texto.** Mañana el mismo
                // aviso va a decir «hace 3 días» en vez de «hace 2», y sigue
                // siendo el mismo: marcarlo visto tiene que aguantar eso. Si
                // esa sesión se cierra y se abre otra, la clave es otra y
                // vuelve a contar, que es lo correcto.
                'clave' => 'caja:' . (int) $c->id_caja,
            ];
        }
    }
}
