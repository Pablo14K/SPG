<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Centro de Ayuda y Soporte: por dónde la clienta le escribe al salón.
 *
 * Son VARIOS, no uno: el salón puede publicar dos WhatsApp, un Instagram y un
 * correo, y ordenarlos. Y es uno para todo el salón, no por sucursal — la
 * clienta entra por un único portal y no está atada a ningún local.
 */
class Contacto
{
    /** Prefijo internacional del país donde está el salón. */
    private const PAIS = '595';

    /**
     * Los canales que se ofrecen, con lo que hay que mostrarle a quien carga.
     * De acá salen el selector del formulario, el ícono del pie y la
     * validación: para sumar otro alcanza con agregarlo y enseñarle a url()
     * cómo armar su enlace.
     */
    public static function canales(): array
    {
        return [
            'WHATSAPP' => ['etiqueta' => 'WhatsApp', 'icono' => 'whatsapp',
                           'ayuda' => 'Número con código de país (ej. +595981123456) o el enlace del canal o grupo.'],
            'TELEGRAM' => ['etiqueta' => 'Telegram', 'icono' => 'telegram',
                           'ayuda' => 'Usuario o canal (ej. @peluqueriluque), un número, o el enlace de t.me.'],
            'INSTAGRAM' => ['etiqueta' => 'Instagram', 'icono' => 'instagram',
                            'ayuda' => 'Usuario (ej. @peluqueriluque) o el enlace del perfil.'],
            'FACEBOOK' => ['etiqueta' => 'Facebook', 'icono' => 'facebook',
                           'ayuda' => 'El enlace de la página o del Messenger.'],
            'TELEFONO' => ['etiqueta' => 'Teléfono', 'icono' => 'telephone',
                           'ayuda' => 'Número para llamar. Se abre el marcador del teléfono.'],
            'EMAIL' => ['etiqueta' => 'Correo', 'icono' => 'envelope',
                        'ayuda' => 'Casilla de contacto. Se abre el programa de correo.'],
            'WEB' => ['etiqueta' => 'Sitio web', 'icono' => 'globe',
                      'ayuda' => 'La dirección completa, empezando con https://'],
        ];
    }

    /**
     * Arma el enlace que abre el chat. Acepta las tres formas en que la gente
     * tiene guardado su contacto: el enlace entero, un usuario o canal, o el
     * número. Devuelve null si no se puede formar algo válido, así nunca se
     * dibuja un enlace roto.
     */
    public static function url(string $canal, string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        // Ya es un enlace: se acepta solo si es http/https. Sin esta
        // comprobación, alguien con acceso a Configuración podría guardar un
        // `javascript:` y quedaría inyectado en el pie de TODAS las pantallas.
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $valor)) {
            return self::urlWebValida($valor) ? $valor : null;
        }

        if ($canal === 'EMAIL') {
            return filter_var($valor, FILTER_VALIDATE_EMAIL) ? 'mailto:' . $valor : null;
        }

        if ($canal === 'WEB') {
            $conEsquema = preg_match('#^https?://#i', $valor) ? $valor : 'https://' . $valor;

            return self::urlWebValida($conEsquema) ? $conEsquema : null;
        }

        if ($canal === 'INSTAGRAM') {
            $usuario = ltrim($valor, '@');

            return preg_match('/^[A-Za-z0-9._]{2,30}$/', $usuario) ? 'https://instagram.com/' . $usuario : null;
        }

        if ($canal === 'FACEBOOK') {
            $usuario = ltrim($valor, '@');

            return preg_match('/^[A-Za-z0-9.]{5,50}$/', $usuario) ? 'https://facebook.com/' . $usuario : null;
        }

        // Telegram admite usuario o canal, que no es un número. El nombre TIENE
        // que empezar con letra: sin eso, un «0981123456» pasaba como usuario y
        // armaba t.me/0981123456 en vez del enlace al número.
        if ($canal === 'TELEGRAM' && preg_match('/^[A-Za-z][A-Za-z0-9_]{3,31}$/', ltrim($valor, '@'))) {
            return 'https://t.me/' . ltrim($valor, '@');
        }

        // Queda un número: se normaliza a formato internacional. Sin esto, un
        // «0981123456» escrito como se marca acá daba wa.me/0981123456, un
        // enlace que abre y no encuentra a nadie.
        $e164 = self::aE164($valor);
        if ($e164 === null) {
            return null;
        }
        $digitos = ltrim($e164, '+');

        return match ($canal) {
            'WHATSAPP' => 'https://wa.me/' . $digitos,   // wa.me va sin el +
            'TELEGRAM' => 'https://t.me/+' . $digitos,   // t.me sí lo lleva
            'TELEFONO' => 'tel:' . $e164,
            default => null,
        };
    }

    /**
     * Número en formato internacional, o null si no se entiende.
     *
     * Se le saca el 0 troncal y se le pone el código de país del salón. Si ya
     * viene con «+», se acepta tal cual cuando el largo es plausible.
     */
    public static function aE164(string $valor): ?string
    {
        $digitos = preg_replace('/\D+/', '', $valor) ?? '';
        if ($digitos === '') {
            return null;
        }

        // Ya viene con código de país
        if (str_starts_with(trim($valor), '+')) {
            return (strlen($digitos) >= 10 && strlen($digitos) <= 15) ? '+' . $digitos : null;
        }
        if (str_starts_with($digitos, self::PAIS) && strlen($digitos) >= 11) {
            return '+' . $digitos;
        }

        // Número local: se le saca el 0 troncal
        $local = ltrim($digitos, '0');

        return (strlen($local) >= 8 && strlen($local) <= 11) ? '+' . self::PAIS . $local : null;
    }

    /** ¿Es una dirección web que se puede poner en un href sin riesgo? */
    public static function urlWebValida(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    /**
     * Los contactos que se muestran en el pie. Un contacto mal cargado NO
     * dibuja un enlace roto: se descarta la fila cuyo valor no se supo convertir.
     */
    public static function delSalon(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = [];
        try {
            $canales = self::canales();
            foreach (DB::select('SELECT canal, valor, etiqueta FROM contacto_soporte ORDER BY orden, id_contacto') as $c) {
                $url = self::url((string) $c->canal, (string) $c->valor);
                if (! $url) {
                    continue;
                }
                $def = $canales[$c->canal] ?? ['etiqueta' => $c->canal, 'icono' => 'chat-dots'];
                // La etiqueta propia gana: con dos WhatsApp hace falta poder
                // distinguirlos («WhatsApp» y «WhatsApp turnos»).
                $cache[] = [
                    'etiqueta' => trim((string) $c->etiqueta) ?: $def['etiqueta'],
                    'icono' => $def['icono'],
                    'url' => $url,
                ];
            }
        } catch (Throwable) {
            // Si la tabla todavía no existe, el pie sale sin esta parte
        }

        return $cache;
    }
}
