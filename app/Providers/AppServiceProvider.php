<?php

namespace App\Providers;

use App\Servicios\Config;
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
    }
}
