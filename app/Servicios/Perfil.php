<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\DB;

/**
 * La cara de quien está usando el sistema: su foto, o sus iniciales.
 *
 * **La foto es de la PERSONA, no de la cuenta** (`persona.foto`), que es donde
 * la regla número dos manda los datos de alguien. Así la tiene también quien
 * trabaja en el salón sin cuenta de sistema, y una persona con dos cuentas no
 * termina con dos caras.
 *
 * **Sin foto NO se dibuja un monigote genérico**: van las iniciales sobre el
 * oro. Un avatar por defecto igual para todos no distingue a nadie, que es lo
 * único que un avatar tiene que hacer; las iniciales sí.
 */
class Perfil
{
    /** Se lee una vez por petición: la barra la pide en cada pantalla. */
    private static ?array $cache = null;

    /**
     * La URL de la foto de quien está en sesión, o null si no cargó ninguna.
     *
     * Null no es un error: la barra dibuja las iniciales.
     */
    public static function foto(): ?string
    {
        return self::mio()['url'];
    }

    /** Las iniciales de quien está en sesión, para cuando no hay foto. */
    public static function iniciales(): string
    {
        return self::mio()['iniciales'];
    }

    /** La foto de una persona cualquiera, por su id. */
    public static function fotoDe(?string $archivo): ?string
    {
        return Imagen::url($archivo, 'personas');
    }

    /**
     * Hasta dos letras: la del nombre y la del apellido.
     *
     * Se toma el primer carácter de cada palabra y no las dos primeras letras
     * del nombre: «Ana Propietaria» es AP, no AN — dos personas que se llaman
     * igual de nombre se distinguen por el apellido, que es de lo que se trata.
     */
    public static function inicialesDe(string $nombre, string $apellido = ''): string
    {
        $letras = '';
        foreach ([$nombre, $apellido] as $parte) {
            $p = trim($parte);
            if ($p !== '') {
                $letras .= mb_strtoupper(mb_substr($p, 0, 1));
            }
        }

        return $letras !== '' ? $letras : '?';
    }

    /** @return array{url: ?string, iniciales: string} */
    private static function mio(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $uid = (int) session('uid');
        if ($uid <= 0) {
            return self::$cache = ['url' => null, 'iniciales' => '?'];
        }

        // Se defiende sola: en una base que todavía no se actualizó la columna
        // no está, y la barra tiene que seguir dibujándose igual.
        try {
            $p = DB::selectOne(
                'SELECT pe.foto, pe.nombre, pe.apellido
                   FROM usuario u JOIN persona pe ON pe.id_persona = u.id_persona
                  WHERE u.id_usuario = ?', [$uid]
            );
        } catch (\Throwable) {
            $p = null;
        }

        return self::$cache = [
            'url' => Imagen::url($p->foto ?? null, 'personas'),
            'iniciales' => self::inicialesDe((string) ($p->nombre ?? ''), (string) ($p->apellido ?? '')),
        ];
    }

    /** Para las pruebas, que cambian la foto dentro de una transacción. */
    public static function olvidar(): void
    {
        self::$cache = null;
    }
}
