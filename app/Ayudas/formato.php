<?php

declare(strict_types=1);

/**
 * Formato de números, dinero, cantidades y fechas.
 *
 * Son funciones globales a propósito: se usan en cada vista y quedan mejor
 * `money($f->total)` que una fachada. Laravel hace lo mismo con `str()`,
 * `collect()` o `e()` — de hecho `e()` ya viene con el framework y hace
 * exactamente lo que hacía la del sistema anterior, así que no se reescribe.
 */

use Illuminate\Support\Facades\DB;

if (! function_exists('recurso')) {
    /**
     * URL de un archivo de assets con su fecha de modificación pegada al final.
     *
     * Sin el `?v=`, el navegador se queda con el CSS o el JS viejo en la caché
     * y los cambios de estilo no se ven hasta forzar un refresco. Los assets
     * son archivos propios servidos tal cual (no pasan por Vite: Bootstrap va
     * por CDN y app.css se escribió a mano), así que el versionado lo pone
     * esta función.
     */
    function recurso(string $ruta): string
    {
        $rel = 'assets/' . ltrim($ruta, '/');
        $abs = public_path($rel);
        $v = is_file($abs) ? (string) filemtime($abs) : '1';

        return asset($rel) . '?v=' . $v;
    }
}

if (! function_exists('flash')) {
    /**
     * Mensaje para la próxima pantalla. Se acumulan: una acción puede dejar
     * más de uno («Compra registrada» + «Se crearon 2 productos nuevos»).
     *
     * Tipos: success · error · warning · info
     */
    function flash(string $mensaje, string $tipo = 'success'): void
    {
        $cola = session()->get('spg_flash', []);
        $cola[] = ['msg' => $mensaje, 'tipo' => $tipo];
        session()->flash('spg_flash', $cola);
    }
}

if (! function_exists('money')) {
    /** Dinero en guaraníes: sin decimales, separador de miles con punto. */
    function money(mixed $n): string
    {
        return config('spg.moneda', 'Gs.') . ' ' . number_format((float) $n, 0, ',', '.');
    }
}

if (! function_exists('monto_input')) {
    /** Igual que money() pero sin el símbolo, para meter dentro de un input. */
    function monto_input(mixed $n): string
    {
        return number_format((float) $n, 0, ',', '.');
    }
}

if (! function_exists('cant')) {
    /**
     * Cantidad: sin decimales si es un número entero (12 y no 12,00), y con
     * decimales solo cuando el producto se consume fraccionado (0,5).
     */
    function cant(mixed $n): string
    {
        $v = (float) $n;
        if (abs($v - round($v)) < 0.005) {
            return number_format($v, 0, ',', '.');
        }

        return rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
    }
}

if (! function_exists('num')) {
    /**
     * Interpreta un número escrito por una persona.
     *
     * Los montos se muestran y se escriben con separador de miles ("7.000"),
     * así que lo que llega por POST no se puede castear con (float) directo:
     * (float)"7.000" da 7. Entiende el formato paraguayo (punto = miles,
     * coma = decimales) y también el crudo.
     */
    function num(mixed $v, float $default = 0.0): float
    {
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = preg_replace('/[^\d,.\-]/', '', trim((string) $v));
        if ($s === '' || $s === '-') {
            return $default;
        }

        $coma = strrpos($s, ',');
        $punto = strrpos($s, '.');

        if ($coma !== false && $punto !== false) {
            // El separador decimal es el que está más a la derecha
            $dec = $coma > $punto ? ',' : '.';
            $mil = $dec === ',' ? '.' : ',';
            $s = str_replace($mil, '', $s);
            $s = str_replace($dec, '.', $s);
        } elseif ($coma !== false) {
            // "1,234,567" es agrupación; "0,5" es decimal
            $s = preg_match('/^-?\d{1,3}(,\d{3})+$/', $s) ? str_replace(',', '', $s) : str_replace(',', '.', $s);
        } elseif ($punto !== false) {
            // "7.000" es agrupación; "7.5" es decimal
            $s = preg_match('/^-?\d{1,3}(\.\d{3})+$/', $s) ? str_replace('.', '', $s) : $s;
        }

        return is_numeric($s) ? (float) $s : $default;
    }
}

if (! function_exists('entero')) {
    /** Cantidad entera (unidades de producto, stock, cuotas). */
    function entero(mixed $v, int $default = 0): int
    {
        return (int) round(num($v, (float) $default));
    }
}

if (! function_exists('ahora_bd')) {
    /**
     * El reloj de pared, preguntado a la base.
     *
     * No se usa date() para sellar un momento que después se le muestra a una
     * persona. En desarrollo, porque la base de zonas horarias de PHP que trae
     * este XAMPP es anterior a que Paraguay dejara sin efecto el horario de
     * verano; en el servidor, porque quien manda es la zona del sistema
     * operativo, que es de donde saca la hora MariaDB. Preguntándole a la base
     * las dos puntas coinciden siempre.
     *
     * Donde más importa: el fichaje de asistencia, que registra la hora del
     * clic. Un fichaje corrido no sirve para nada.
     *
     * Se pregunta una sola vez por petición.
     */
    function ahora_bd(string $fmt = 'Y-m-d H:i:s'): string
    {
        static $ts = null;
        if ($ts === null) {
            try {
                $ts = strtotime((string) DB::scalar('SELECT NOW()')) ?: time();
            } catch (Throwable) {
                $ts = time();
            }
        }

        return date($fmt, $ts);
    }
}

if (! function_exists('fecha')) {
    /** Fecha y hora legibles. */
    function fecha(mixed $dt, string $fmt = 'd/m/Y H:i'): string
    {
        if (! $dt) {
            return '';
        }
        $ts = is_numeric($dt) ? (int) $dt : strtotime((string) $dt);

        return $ts ? date($fmt, $ts) : '';
    }
}

// ---------------------------------------------------------------------
//  El frasco y el mililitro
//
//  Lo que se compra y lo que se gasta no se miden igual: el shampoo se
//  compra por frasco de 1 litro y se usa de a 30 ml. El stock se guarda
//  siempre en la unidad de compra —que es la que factura el proveedor y la
//  que espera fn_producto_stock—, así que la conversión pasa al entrar y al
//  salir, y nunca queda guardada en dos unidades.
// ---------------------------------------------------------------------

if (! function_exists('producto_fraccionado')) {
    /** ¿Este producto se gasta por partes? */
    function producto_fraccionado(array|object $p): bool
    {
        $p = (array) $p;

        return ! empty($p['contenido']) && (float) $p['contenido'] > 0 && ! empty($p['unidad_consumo']);
    }
}

if (! function_exists('consumo_a_stock')) {
    /** Lo que escribió la persona (30 ml) → lo que se descuenta (0,03 frascos). */
    function consumo_a_stock(array|object $p, float $cantidad): float
    {
        if (! producto_fraccionado($p)) {
            return $cantidad;
        }

        return round($cantidad / (float) ((array) $p)['contenido'], 4);
    }
}

if (! function_exists('stock_a_consumo')) {
    /** Al revés, para mostrar cuánto queda: 0,5 frascos → 500 ml. */
    function stock_a_consumo(array|object $p, float $stock): float
    {
        if (! producto_fraccionado($p)) {
            return $stock;
        }

        return round($stock * (float) ((array) $p)['contenido'], 2);
    }
}

if (! function_exists('unidad_consumo')) {
    /** «ml» o la unidad de medida del producto. */
    function unidad_consumo(array|object $p): string
    {
        $a = (array) $p;

        return producto_fraccionado($a) ? (string) $a['unidad_consumo'] : (string) ($a['unidad_medida'] ?? 'unidad');
    }
}

if (! function_exists('fecha_larga')) {
    /**
     * «Lunes 10 de agosto de 2026». Se arma a mano y no con strftime(), que
     * está obsoleto desde PHP 8.1 y además depende del locale del sistema, que
     * en Windows no siempre está en español.
     */
    function fecha_larga(mixed $dt): string
    {
        $ts = is_numeric($dt) ? (int) $dt : strtotime((string) $dt);
        if (! $ts) {
            return '';
        }
        $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                  'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        return $dias[(int) date('w', $ts)] . ' ' . date('j', $ts) . ' de '
            . $meses[(int) date('n', $ts) - 1] . ' de ' . date('Y', $ts);
    }
}

if (! function_exists('estado_badge')) {
    /**
     * Badge de estado.
     *
     * El criterio de color no es decorativo: lo que está EN CURSO lleva el
     * acento dorado, lo que está agendado o cerrado va en neutros cálidos, y
     * el resultado en los semánticos (verde salió bien, rojo se anuló). Así el
     * badge dorado señala algo en vez de ser adorno.
     */
    function estado_badge(string $estado): string
    {
        $map = [
            'Programada' => 'prog', 'Reprogramada' => 'prog', 'En proceso' => 'proc',
            'Atendida' => 'ok', 'Confirmada' => 'ok', 'Emitida' => 'ok', 'Registrado' => 'ok',
            'Cancelada' => 'no', 'Ausente' => 'no', 'Anulada' => 'no', 'Anulado' => 'no', 'Revertido' => 'no',
            'Pendiente' => 'warn', 'Abierta' => 'ok', 'Cerrada' => 'muted',
        ];
        $k = $map[$estado] ?? 'muted';

        return '<span class="badge-estado e-' . $k . '">' . e($estado) . '</span>';
    }
}
