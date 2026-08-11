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
