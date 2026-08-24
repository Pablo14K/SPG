<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Subir una imagen que el salón carga: el logo, la foto de un servicio.
 *
 * **Se guarda el NOMBRE del archivo, no el archivo.** Un BLOB en la base la
 * hincha, complica el volcado que se entrega y obliga a servir la imagen por
 * PHP en cada carga de la pantalla.
 *
 * Las tres defensas, que son las mismas desde la 7.35.0:
 *
 * - se comprueba que sea **una imagen de verdad** con `getimagesize`, no la
 *   extensión que diga el nombre;
 * - se limita el tamaño;
 * - **el archivo se escribe antes de tocar la base**, así que si falla la
 *   escritura no queda una fila apuntando a un archivo que no está.
 *
 * **SVG no entra a propósito**: se sirve como marcado y puede traer scripts
 * adentro.
 */
class Imagen
{
    /** Los tres formatos que se aceptan, por su contenido y no por su nombre. */
    private const TIPOS = [
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_WEBP => 'webp',
    ];

    /**
     * Guarda el archivo y devuelve su nombre, o lanza el mensaje del problema.
     *
     * El `$prefijo` va en el nombre y **la fecha también**: sin ella el
     * navegador se queda con la imagen vieja en caché, y se pisaría la anterior
     * mientras alguien la está mirando.
     *
     * @throws \RuntimeException con el mensaje que se le muestra a la persona
     */
    public static function guardar(UploadedFile $archivo, string $carpeta, string $prefijo,
        int $maxKb = 512): string
    {
        if (! $archivo->isValid()) {
            throw new \RuntimeException('La imagen no llegó completa. Probá de nuevo.');
        }
        if ($archivo->getSize() > $maxKb * 1024) {
            throw new \RuntimeException(
                'La imagen no puede pesar más de ' . $maxKb . ' KB. Achicala y volvé a subirla.');
        }

        // La extensión la elige quien sube el archivo; esto mira el contenido.
        $info = @getimagesize($archivo->getRealPath());
        if (! $info || ! isset(self::TIPOS[$info[2]])) {
            throw new \RuntimeException('Tiene que ser una imagen PNG, JPG o WEBP.');
        }

        $nombre = $prefijo . '-' . date('YmdHis') . '-' . random_int(100, 999)
            . '.' . self::TIPOS[$info[2]];

        try {
            $archivo->move(public_path('assets/' . $carpeta), $nombre);
        } catch (Throwable $e) {
            Log::error('No se pudo guardar la imagen: ' . $e->getMessage());
            throw new \RuntimeException('No se pudo guardar la imagen. El detalle quedó registrado.');
        }

        return $nombre;
    }

    /**
     * La URL de una imagen guardada, o null si el archivo ya no está.
     *
     * **Null NO es un error**: la pantalla dibuja su placeholder. Comprobar que
     * el archivo exista evita el ícono roto cuando alguien lo borró a mano del
     * servidor y la fila quedó apuntando ahí.
     */
    public static function url(?string $nombre, string $carpeta): ?string
    {
        $n = trim((string) $nombre);
        if ($n === '' || ! is_file(public_path('assets/' . $carpeta . '/' . $n))) {
            return null;
        }

        return recurso($carpeta . '/' . $n);
    }

    /** Borra el archivo de una imagen que se reemplazó o se sacó. */
    public static function borrar(?string $nombre, string $carpeta): void
    {
        $n = trim((string) $nombre);
        if ($n === '' || ! is_file(public_path('assets/' . $carpeta . '/' . $n))) {
            return;
        }

        // Si no se puede borrar no es grave: queda un archivo huérfano, y
        // fallar acá impediría guardar el cambio que sí importa.
        try {
            @unlink(public_path('assets/' . $carpeta . '/' . $n));
        } catch (Throwable $e) {
            Log::warning('No se pudo borrar la imagen ' . $n . ': ' . $e->getMessage());
        }
    }
}
