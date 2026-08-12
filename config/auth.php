<?php

declare(strict_types=1);

/**
 * Este sistema NO usa la autenticación de Laravel.
 *
 * Las cuentas viven en `usuario`, los datos personales en `persona` y el
 * alcance en `rol`, tal como los define el esquema del TCC. Quien arma la
 * sesión es `App\Servicios\Sesion`, y quien decide si se pasa o no son los
 * middleware propios (`sesion`, `personal`, `modulo`, `admin`).
 *
 * Meter un modelo Eloquent con las convenciones del framework obligaría a
 * agregarle columnas a esas tablas y a romper la 3FN, que es requisito del
 * trabajo. Por eso acá no hay proveedor `eloquent` ni modelo `User`: el
 * archivo conserva la forma mínima que el framework espera encontrar, y nada
 * más. Antes apuntaba a `App\Models\User`, que era andamiaje que este
 * proyecto nunca instanció.
 *
 * Si algún día se usara `Auth::`, hay que declarar su proveedor acá.
 */
return [

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    // Sin modelo: no hay tabla `users`, ni se quiere una dentro de la base que
    // se entrega al salón. La recuperación de contraseña del sistema tampoco
    // pasa por acá — usa `token_seguridad` y `App\Servicios\Seguridad`.
    'providers' => [],

    'passwords' => [],

    'password_timeout' => 10800,
];
