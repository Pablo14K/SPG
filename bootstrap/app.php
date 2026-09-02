<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // **Detrás de un proxy, Laravel no sabe que la conexión era HTTPS.**
        //
        // En el servidor la cadena es:
        //   navegador --HTTPS--> Traefik --HTTP--> Caddy --FastCGI--> php-fpm
        //
        // Lo que php-fpm ve es la última pata, que es HTTP en claro. Sin esto,
        // `url()` y `route()` generan `http://`, y eso se paga donde más
        // duele: **los enlaces de los correos** —reprogramar la cita,
        // cancelarla, agregarla al calendario— le llegan a la clienta
        // apuntando a HTTP, y con HSTS puesto el navegador ni los abre.
        //
        // Traefik manda `X-Forwarded-Proto: https` y Caddy se lo pasa a PHP;
        // esto es lo que autoriza a creerle. Se confía **sólo en las redes
        // privadas**, que es de donde puede venir el proxy: confiar en
        // cualquier origen dejaría que un visitante mintiera sobre el esquema
        // y sobre su propia IP.
        //
        // En desarrollo no cambia nada: ahí `artisan serve` atiende directo y
        // no llega ninguna de esas cabeceras.
        $middleware->trustProxies(at: [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '127.0.0.1',
        ]);

        // Los guardias del sistema, con nombre corto para poder leerlos en las
        // rutas: ->middleware('modulo:facturacion.timbrados')
        $middleware->alias([
            'sesion' => \App\Http\Middleware\ExigeSesion::class,
            'personal' => \App\Http\Middleware\ExigePersonal::class,
            'modulo' => \App\Http\Middleware\ExigeModulo::class,
            'admin' => \App\Http\Middleware\ExigeAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
