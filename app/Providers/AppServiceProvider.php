<?php

namespace App\Providers;

use App\Servicios\Config;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Las credenciales de `secretos.env` tienen que llegar TAMBIÉN a la web.
     *
     * **`php artisan serve` le reenvía al proceso que atiende las peticiones
     * sólo una lista blanca** —`APP_ENV`, `PATH`, las de Xdebug— y descarta
     * todo lo demás. Este proyecto ya lo tiene anotado por `DB_HOST`, y por eso
     * el contenedor de desarrollo monta su propio `.env`; lo que faltaba decir
     * es que **la trampa vale para cualquier clave que entre por `env_file`**,
     * no sólo para ésa.
     *
     * Y muerde igual de mal, porque los dos lados contestan distinto: `php
     * artisan tinker` ve la cuenta de correo y la web la ve vacía. Con eso,
     * emitir una factura declaraba bien ante la DNIT y el comprobante **no
     * salía por correo**, con un «An email must have a "From" or a "Sender"
     * header» en el log y nada en pantalla que lo explicara.
     *
     * **En el servidor no hace falta, y por eso no se toca nada allá**: sirve
     * php-fpm y el entrypoint corre `php artisan optimize`, que hornea la
     * configuración cuando las variables todavía están puestas, así que los
     * hijos la leen de la caché.
     */
    private function pasarLosSecretosAlServidorDeDesarrollo(): void
    {
        if (! class_exists(ServeCommand::class)) {
            return;
        }

        // Sólo las que la web necesita y no puede deducir de otro lado. **No se
        // pasa el entorno entero a propósito**: la lista blanca existe para que
        // el servidor de desarrollo no herede media terminal.
        foreach ([
            'MAIL_MAILER', 'MAIL_SCHEME', 'MAIL_HOST', 'MAIL_PORT',
            'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME',
            'SIFEN_TOKEN',
        ] as $clave) {
            if (! in_array($clave, ServeCommand::$passthroughVariables, true)) {
                ServeCommand::$passthroughVariables[] = $clave;
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // **El nombre del salón se pisa acá y no en cada vista.**
        //
        // `config('app.name')` aparece en más de veinte lugares: el título de
        // cada pestaña, la barra de arriba, el pie, la pantalla de ingreso, las
        // tres plantillas de correo y las dos de impresión. Cambiarlos uno por
        // uno sería cambiar veinte y olvidarse de dos —y los que se olvidan son
        // justo los que nadie mira todos los días, como el pie del informe
        // impreso—, así que se reemplaza el valor una vez, al arrancar, y todas
        // lo toman solas. Es lo que quiere decir «el cambio debe verse para
        // todos».
        //
        // `Config::nombreSalon()` se defiende sola: si la tabla o la columna no
        // están —una base que todavía no se reimportó— devuelve el valor del
        // `.env` y acá no cambia nada.
        config(['app.name' => Config::nombreSalon()]);

        // **La cuenta que envía los avisos sale de la base, no sólo del `.env`.**
        //
        // El código de verificación, la recuperación de contraseña, el segundo
        // factor y los recordatorios salen por SMTP, y hasta ahora la cuenta
        // que los manda vivía en `secretos.env`: cambiarla era editar un archivo
        // y volver a desplegar. Ahora el Administrador la carga desde una
        // pantalla y se pisa acá, al arrancar, así que vale para la web **y**
        // para el planificador —que es de donde salen los recordatorios—.
        //
        // Se defiende sola: sin cuenta cargada no toca nada y queda lo del
        // `.env`, que es lo que hay en la base de instalación.
        Config::aplicarAlMailer();

        $this->pasarLosSecretosAlServidorDeDesarrollo();
    }
}
