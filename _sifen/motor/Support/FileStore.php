<?php

declare(strict_types=1);
/*
 * DOCUMENTACION DEL ARCHIVO
 * Que hace: Utilidad de filesystem. Asegura carpetas y guarda archivos generados.
 * Donde se usa: forma parte del flujo de facturacion electronica SIFEN documentado en docs/DOCUMENTACION_TECNICA_COMPLETA.md.
 * Nota: los detalles de variables, palabras reservadas y cambios posibles estan centralizados en docs/GUIA_COMENTARIOS_CODIGO.md para no duplicar ruido en cada linea.
 */


namespace App\Support;

use RuntimeException;

/**
 * Comentario de codigo: clase FileStore. Agrupa la responsabilidad principal indicada en la cabecera del archivo.
 */
final class FileStore
{
    /**
     * Crea un directorio si no existe.
     */
    public static function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio: ' . $dir);
        }
    }

    /**
     * Escribe un archivo asegurando primero el directorio padre.
     */
    public static function put(string $path, string $content): void
    {
        self::ensureDir(dirname($path));
        file_put_contents($path, $content);
    }

    /**
     * Resuelve la ruta REAL de un adjunto guardado en la BD, de forma portable.
     *
     * Si la ruta almacenada existe tal cual, se usa. Si no (típico al mover el
     * proyecto o restaurar la BD en otro servidor/cPanel), se reconstruye a partir
     * del nombre de archivo bajo APP_ROOT/storage/{xml|kude|emails} según su
     * extensión. Si tampoco existe, devuelve la ruta original (el llamador decide).
     */
    public static function resolveStorage(string $path): string
    {
        if ($path === '') {
            return $path;
        }
        if (is_file($path)) {
            return $path;
        }

        $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 3);
        $base = basename(str_replace('\\', '/', $path));
        $ext  = strtolower(pathinfo($base, PATHINFO_EXTENSION));
        $sub  = match ($ext) {
            'pdf'   => 'kude',
            'xml'   => 'xml',
            'eml'   => 'emails',
            default => null,
        };
        if ($sub === null) {
            return $path;
        }

        $candidate = $root . '/storage/' . $sub . '/' . $base;
        return is_file($candidate) ? $candidate : $path;
    }
}
