<?php

declare(strict_types=1);

namespace App\Servicios;

/**
 * El archivo .ics para que la clienta se guarde la cita en su teléfono.
 *
 * Es texto plano, sin librerías ni servicios de terceros: lo abren por igual
 * Android, iPhone, Google Calendar y Outlook. El bloque VALARM es la alarma
 * del dispositivo, así el recordatorio queda por dos vías independientes — el
 * correo y la alerta del propio teléfono, que suena aunque no abra el correo.
 *
 * OJO CON LA HORA: va en «hora flotante», sin `Z` y sin convertir a UTC. Es a
 * propósito y no hay que «corregirlo». Paraguay quedó fijo en UTC−3 al dejar
 * sin efecto el horario de verano, y varias bases de zonas horarias todavía
 * creen que hay verano: si se convirtiera, al teléfono le llegaría la cita una
 * hora corrida. Sin conversión no hay desfase posible — el teléfono lee 17:00
 * y muestra 17:00.
 */
class Calendario
{
    /** Arma el contenido del .ics. $avisoMin es la anticipación de la alarma. */
    public static function deCita(object $cita, int $avisoMin = 120, string $lugar = ''): string
    {
        $inicio = strtotime((string) $cita->fecha_hora);
        $fin = $inicio + max(15, (int) ($cita->duracion_min ?? 60)) * 60;

        $resumen = trim((string) ($cita->servicios ?: 'Cita en el salón'));
        $descripcion = 'Con ' . ($cita->profesional ?? 'el equipo') . '. '
            . 'Si necesitás cambiarla, escribinos.';

        $lineas = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//' . self::escapar((string) config('app.name')) . '//Citas//ES',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:cita-' . (int) $cita->id_cita . '@' . parse_url((string) config('app.url'), PHP_URL_HOST),
            // DTSTAMP sí va en UTC: es cuándo se generó el archivo, no la cita
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            // Estas dos NO llevan Z: son hora flotante, la del reloj del salón
            'DTSTART:' . date('Ymd\THis', $inicio),
            'DTEND:' . date('Ymd\THis', $fin),
            'SUMMARY:' . self::escapar($resumen),
            'DESCRIPTION:' . self::escapar($descripcion),
        ];

        if ($lugar !== '') {
            $lineas[] = 'LOCATION:' . self::escapar($lugar);
        }

        $lineas[] = 'STATUS:CONFIRMED';
        $lineas[] = 'BEGIN:VALARM';
        $lineas[] = 'TRIGGER:-PT' . max(5, $avisoMin) . 'M';
        $lineas[] = 'ACTION:DISPLAY';
        $lineas[] = 'DESCRIPTION:' . self::escapar('Recordatorio: ' . $resumen);
        $lineas[] = 'END:VALARM';
        $lineas[] = 'END:VEVENT';
        $lineas[] = 'END:VCALENDAR';

        // El .ics se separa con CRLF por norma (RFC 5545)
        return implode("\r\n", $lineas) . "\r\n";
    }

    /**
     * Enlace para agregar la cita a Google Calendar, sin descargar nada.
     *
     * **Es el camino que de verdad funciona en el celular.** El .ics se sirve
     * como archivo adjunto, y Android lo baja a la carpeta de descargas en vez
     * de abrirlo con el calendario: la clienta toca el botón, no ve pasar nada
     * y da por hecho que está roto. Este enlace abre la app o la web de Google
     * con la cita ya cargada, y sólo hay que confirmar.
     *
     * La hora va en local con `ctz=America/Asuncion`, y es a propósito: la base
     * de zonas horarias de Google sí está al día, así que conviene que la
     * conversión la haga él. Es la contraparte de la hora flotante del .ics —
     * las dos evitan el mismo desfase, cada una a su manera.
     */
    public static function urlGoogle(object $cita, string $lugar = ''): string
    {
        $inicio = strtotime((string) $cita->fecha_hora);
        $fin = $inicio + max(15, (int) ($cita->duracion_min ?? 60)) * 60;

        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action' => 'TEMPLATE',
            'text' => trim((string) ($cita->servicios ?: 'Cita en el salón')),
            // Sin la `Z` final: es hora local, y `ctz` dice de qué huso.
            'dates' => date('Ymd\THis', $inicio) . '/' . date('Ymd\THis', $fin),
            'ctz' => 'America/Asuncion',
            'details' => 'Con ' . ($cita->profesional ?? 'el equipo') . '. Si necesitás cambiarla, escribinos.',
            'location' => $lugar,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** Dirección del salón para el campo LOCATION. */
    public static function lugar(): string
    {
        $s = \Illuminate\Support\Facades\DB::selectOne(
            'SELECT nombre, direccion, ciudad FROM sucursal WHERE activo = 1 ORDER BY id_sucursal LIMIT 1'
        );
        if (! $s) {
            return '';
        }

        return trim(implode(', ', array_filter([$s->nombre, $s->direccion, $s->ciudad])));
    }

    /** Escapa los caracteres que el formato reserva. */
    private static function escapar(string $t): string
    {
        return str_replace(["\\", "\n", ',', ';'], ['\\\\', '\\n', '\\,', '\\;'], $t);
    }
}
