<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Los números del negocio que decide el salón, no quien programa.
 *
 * `config/spg.php` sigue siendo el lugar de las constantes **técnicas** —el
 * paso de la agenda, cuántas filas por página, la gracia del fichaje—. Lo que
 * vive acá es distinto: son decisiones comerciales que cambian sin desplegar
 * nada, y que por eso están en la base y se editan desde una pantalla.
 *
 * **`config/spg.php` queda igual y hace de respaldo.** Si la tabla todavía no
 * existe —una base vieja que no se reimportó— se usa el valor de ahí en vez de
 * reventar: el salón sigue acumulando puntos como siempre hasta que actualice.
 */
class Config
{
    /** Se lee una vez por petición: la factura la consulta por cada emisión. */
    private static ?int $puntosCadaGs = null;

    /**
     * Cuántos guaraníes facturados valen 1 punto.
     *
     * Con 10.000, una factura de Gs. 320.000 le deja 32 puntos al cliente.
     */
    public static function puntosCadaGs(): int
    {
        if (self::$puntosCadaGs !== null) {
            return self::$puntosCadaGs;
        }

        $porDefecto = (int) config('spg.puntos_cada_gs', 10000);

        try {
            $v = (int) DB::scalar('SELECT puntos_cada_gs FROM configuracion WHERE id_configuracion = 1');
        } catch (Throwable) {
            $v = 0;   // la tabla no está: base sin actualizar
        }

        return self::$puntosCadaGs = ($v > 0 ? $v : $porDefecto);
    }

    /**
     * Guarda la relación. Devuelve el valor que quedó, o null si no se pudo.
     *
     * El rango lo hace cumplir el `CHECK` de la base, que es donde no se puede
     * esquivar; acá se comprueba para poder decirlo con palabras antes de
     * intentarlo.
     */
    public static function guardarPuntosCadaGs(int $gs): ?int
    {
        if ($gs < 100 || $gs > 10000000) {
            return null;
        }

        try {
            DB::update('UPDATE configuracion SET puntos_cada_gs = ? WHERE id_configuracion = 1', [$gs]);
        } catch (Throwable) {
            return null;
        }
        self::$puntosCadaGs = $gs;

        return $gs;
    }

    /** La fila entera, leída una vez por petición. */
    private static ?object $identidad = null;

    /**
     * Cómo se llama el salón y con qué logo se presenta.
     *
     * **Vivían en `APP_NAME`**, así que cambiarlos era editar el `.env` y volver
     * a desplegar —y en Docker, además, entrar al contenedor—. Es el mismo caso
     * que `puntos_cada_gs` en la 7.27.0: un dato del negocio escondido detrás de
     * un despliegue. Ahora lo edita el salón desde una pantalla, y **el cambio
     * se ve en todas partes a la vez** porque todas las pantallas leen de acá.
     *
     * `config('app.name')` queda de respaldo, para una base que todavía no se
     * reimportó: sin la columna se sigue mostrando lo de siempre en vez de
     * reventar la pantalla de ingreso, que es la peor de todas para romper.
     */
    /**
     * La actividad económica del salón, como la pide el SIFEN.
     *
     * Va en el KuDE («Actividad: …») y es un dato del negocio, no del código:
     * el Automatizador la traía fija en su `.env` como «VENTA AL POR MENOR»,
     * que es del archivo de ejemplo y no describe a una peluquería.
     *
     * Devuelve `['cod' => …, 'desc' => …]`, los dos como texto.
     */
    public static function actividad(): array
    {
        $i = self::identidad();

        return [
            'cod' => trim((string) ($i->actividad_cod ?? '')),
            'desc' => trim((string) ($i->actividad_desc ?? '')),
        ];
    }

    /** El correo con el que el salón factura. Vacío si no se cargó. */
    public static function email(): string
    {
        return trim((string) (self::identidad()->email ?? ''));
    }

    public static function nombreSalon(): string
    {
        $n = trim((string) (self::identidad()->nombre_salon ?? ''));

        return $n !== '' ? $n : (string) config('app.name', 'SPG');
    }

    /**
     * URL del logo, o null si el salón no cargó ninguno.
     *
     * Null NO es un error: sin logo se dibuja la tijera de siempre, que es la
     * identidad por defecto del sistema.
     */
    public static function logo(): ?string
    {
        $l = trim((string) (self::identidad()->logo ?? ''));
        if ($l === '' || ! is_file(public_path('assets/logo/' . $l))) {
            return null;
        }

        return recurso('logo/' . $l);
    }

    /**
     * La cuenta de correo que ENVÍA los avisos: usuario, clave y remitente.
     *
     * Es distinta del correo fiscal (`email()`), que es sólo el que va en el
     * KuDE: ésta es la que Gmail autentica para mandar el código de
     * verificación, la recuperación de contraseña, el segundo factor y los
     * recordatorios. Vivía en `secretos.env`, así que cambiarla era editar un
     * archivo y volver a desplegar.
     *
     * La clave se guarda **cifrada** con la APP_KEY, así que un volcado de la
     * base no la deja legible. Vacío es «usá lo del `.env`», que es lo que hay
     * en la base de instalación: `aplicarAlMailer()` no pisa nada entonces.
     *
     * @return array{usuario:string, clave:string, desde:string}
     */
    public static function correoSistema(): array
    {
        $i = self::identidad();
        $clave = '';
        $guardada = trim((string) ($i->mail_clave ?? ''));
        if ($guardada !== '') {
            try {
                $clave = Crypt::decryptString($guardada);
            } catch (Throwable) {
                // La APP_KEY cambió desde que se guardó: la clave vieja ya no se
                // puede leer y no hay que reventar por eso. Queda como si no
                // estuviera cargada, y el sistema cae al `.env`.
                $clave = '';
                Log::warning('SPG: no se pudo descifrar la clave del correo del sistema; '
                    . 'se usa la del entorno. Volvé a cargarla en Seguridad → Correo del sistema.');
            }
        }

        return [
            'usuario' => trim((string) ($i->mail_usuario ?? '')),
            'clave' => $clave,
            'desde' => trim((string) ($i->mail_desde ?? '')),
        ];
    }

    /**
     * Guarda la cuenta que envía. Cadena vacía en la clave = «no la cambies»:
     * el campo nunca trae la que hay cargada, porque sería mandarla al navegador
     * en cada carga de la pantalla — el mismo criterio que la contraseña de una
     * cuenta.
     *
     * Devuelve true si guardó.
     */
    public static function guardarCorreoSistema(string $usuario, string $clave, string $desde): bool
    {
        try {
            if ($clave === '') {
                DB::update(
                    'UPDATE configuracion SET mail_usuario = ?, mail_desde = ? WHERE id_configuracion = 1',
                    [$usuario ?: null, $desde ?: null]
                );
            } else {
                DB::update(
                    'UPDATE configuracion SET mail_usuario = ?, mail_clave = ?, mail_desde = ? WHERE id_configuracion = 1',
                    [$usuario ?: null, Crypt::encryptString($clave), $desde ?: null]
                );
            }
        } catch (Throwable $e) {
            Log::error('SPG: no se pudo guardar el correo del sistema.', ['error' => $e->getMessage()]);

            return false;
        }
        self::$identidad = null;

        return true;
    }

    /**
     * Pisa la configuración del mailer con la cuenta cargada en la base.
     *
     * Se llama una vez al arrancar (`AppServiceProvider::boot`), así que vale
     * para la web **y** para el planificador —que es de donde salen los
     * recordatorios—. Si no hay usuario cargado no toca nada: queda lo del
     * `.env`.
     *
     * **Gmail rechaza un remitente que no sea la cuenta autenticada**, así que
     * el `From` se fija al usuario salvo que se haya cargado uno explícito.
     */
    public static function aplicarAlMailer(): void
    {
        $c = self::correoSistema();
        if ($c['usuario'] === '') {
            return;
        }

        config([
            'mail.mailers.smtp.username' => $c['usuario'],
            'mail.from.address' => $c['desde'] ?: $c['usuario'],
        ]);
        if ($c['clave'] !== '') {
            config(['mail.mailers.smtp.password' => $c['clave']]);
        }
    }

    private static function identidad(): object
    {
        if (self::$identidad !== null) {
            return self::$identidad;
        }

        try {
            $f = DB::selectOne(
                'SELECT nombre_salon, logo, actividad_cod, actividad_desc, email,
                        mail_usuario, mail_clave, mail_desde
                   FROM configuracion WHERE id_configuracion = 1'
            );
        } catch (Throwable) {
            $f = null;   // la tabla o las columnas no están: base sin actualizar
        }

        return self::$identidad = $f ?: (object) [
            'nombre_salon' => '', 'logo' => null,
            'actividad_cod' => null, 'actividad_desc' => null, 'email' => null,
            'mail_usuario' => null, 'mail_clave' => null, 'mail_desde' => null,
        ];
    }

    /** Para las pruebas, que cambian el valor dentro de una transacción. */
    public static function olvidar(): void
    {
        self::$puntosCadaGs = null;
        self::$identidad = null;
    }
}
