<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Prototipo único de listado: filtros, paginación y exportación.
 *
 * Todas las pantallas de lista del sistema se dibujan igual: la barra de
 * filtros arriba, la tabla en el medio y el pie con «Mostrando 1–20 de 137».
 * Antes cada pantalla lo resolvía por su cuenta —o directamente no lo
 * resolvía: había UN solo buscador en todo el sistema y ninguna lista
 * paginaba. Las consultas cortaban con LIMIT 200 sin avisar, así que a partir
 * de la fila 201 los datos no existían para el usuario. Eso es peor que no
 * paginar, porque no se nota.
 *
 * Cuatro reglas al sumar una lista nueva:
 *
 *  1. El WHERE se arma UNA sola vez y lo comparten el COUNT(*) y la consulta
 *     de la página. Si se separan, el «de 137» del pie deja de coincidir.
 *  2. Nunca repitas un marcador con nombre: la conexión va con las preparadas
 *     nativas de MySQL, que no lo admiten. Para eso está likeVarias().
 *  3. Los filtros van por GET, así el resultado tiene su propia URL y se puede
 *     compartir o recargar.
 *  4. El CSV exporta lo filtrado SIN límite de página: si la persona filtró
 *     marzo, el archivo trae todo marzo.
 */
class Listado
{
    /**
     * Declara los filtros de una pantalla y devuelve sus valores ya saneados.
     *
     * Cada filtro se declara con su tipo, y el tipo decide cómo se limpia lo
     * que llega por la URL, así lo que sale entra directo en una consulta
     * preparada. Tipos: texto · select · fecha · numero
     */
    public static function filtros(array $spec): array
    {
        $v = [];
        foreach ($spec as $clave => $def) {
            $bruto = (string) Request::query($clave, '');

            $v[$clave] = match ($def['tipo'] ?? 'texto') {
                // Solo se acepta una opción que exista de verdad: así nadie
                // mete un valor cualquiera por la URL y llega a la consulta.
                'select' => array_key_exists($bruto, $def['opciones'] ?? []) ? $bruto : '',
                'fecha' => (preg_match('/^\d{4}-\d{2}-\d{2}$/', $bruto) && strtotime($bruto)) ? $bruto : '',
                'numero' => ($bruto !== '' && is_numeric($bruto)) ? (string) (int) $bruto : '',
                default => mb_substr(trim($bruto), 0, 80),
            };
        }

        // Un rango al revés (desde > hasta) no devuelve nada y parece que el
        // sistema está roto. Se dan vuelta y se sigue.
        if (isset($v['desde'], $v['hasta']) && $v['desde'] !== '' && $v['hasta'] !== '' && $v['desde'] > $v['hasta']) {
            [$v['desde'], $v['hasta']] = [$v['hasta'], $v['desde']];
        }

        return [
            'campos' => $spec,
            'v' => $v,
            'activos' => count(array_filter($v, fn ($x) => $x !== '')),
            'csv' => null,
        ];
    }

    /** Valor de un filtro (cadena vacía si no se cargó). */
    public static function valor(array $f, string $clave): string
    {
        return (string) ($f['v'][$clave] ?? '');
    }

    /** ¿Se cargó ese filtro? */
    public static function hay(array $f, string $clave): bool
    {
        return self::valor($f, $clave) !== '';
    }

    /** Los valores cargados, listos para pegar en una URL. */
    public static function query(array $f): array
    {
        return array_filter($f['v'], fn ($x) => $x !== '');
    }

    /**
     * Busca una palabra en varias columnas.
     *
     * CUIDADO, que esto ya rompió el buscador una vez. La conexión abre PDO
     * con la emulación apagada, así que MySQL prepara de verdad y **no admite
     * repetir un marcador con nombre**. La búsqueda de Clientes hacía
     * `WHERE nombre LIKE :q OR apellido LIKE :q …` y reventaba con «Invalid
     * parameter number» apenas se escribía algo: el único buscador que tenía
     * el sistema no funcionaba. Acá cada columna recibe su propio marcador.
     */
    public static function likeVarias(array $columnas, string $valor, string $prefijo, array &$par): string
    {
        $partes = [];
        foreach (array_values($columnas) as $i => $col) {
            $marca = $prefijo . $i;
            $partes[] = "$col LIKE :$marca";
            $par[$marca] = '%' . $valor . '%';
        }

        return '(' . implode(' OR ', $partes) . ')';
    }

    /**
     * Se le pasa el total ya contado (un COUNT(*) con los MISMOS filtros que
     * la consulta de la lista) y devuelve la rebanada que hay que pedir.
     */
    public static function paginacion(int $total, ?int $porPagina = null): array
    {
        $porPagina ??= (int) config('spg.lista.por_pagina', 20);
        $porPagina = max(5, min($porPagina, (int) config('spg.lista.max_por_pagina', 200)));
        $total = max(0, $total);
        $paginas = max(1, (int) ceil($total / $porPagina));

        // Si alguien pide la página 900 de una lista de 3, se le da la última:
        // es más útil que una tabla vacía sin explicación.
        $pagina = max(1, min((int) Request::query('p', 1), $paginas));
        $offset = ($pagina - 1) * $porPagina;

        return [
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'offset' => $offset,
            'total' => $total,
            'paginas' => $paginas,
            'desde' => $total ? $offset + 1 : 0,
            'hasta' => min($offset + $porPagina, $total),
        ];
    }

    /**
     * Exporta a CSV lo que la pantalla está mostrando, con los filtros puestos.
     *
     * El BOM del principio no es adorno: sin él, Excel en Windows abre el
     * archivo en ANSI y los nombres con ñ o tilde salen rotos. El separador es
     * punto y coma, que es lo que espera Excel en español, donde la coma es el
     * separador decimal.
     */
    public static function csv(string $nombre, array $encabezados, array $filas): StreamedResponse
    {
        return response()->streamDownload(function () use ($encabezados, $filas) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, $encabezados, ';');
            foreach ($filas as $fila) {
                fputcsv($salida, array_map(fn ($c) => $c === null ? '' : (string) $c, $fila), ';');
            }
            fclose($salida);
        }, $nombre . '_' . date('Ymd') . '.csv', [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** ¿La pantalla se está pidiendo como planilla? */
    public static function pideCsv(): bool
    {
        return Request::query('export') === 'csv';
    }
}
