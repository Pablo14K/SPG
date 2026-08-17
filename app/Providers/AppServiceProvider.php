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
    }
}
