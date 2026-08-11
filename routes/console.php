<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
|
| En el servidor, el cron del panel llama una sola vez por minuto a:
|
|     * * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
|
| y Laravel decide qué corresponde correr. Es lo que reemplaza al Programador
| de tareas de Windows.
|
| NADA de procesos residentes: los 4 GB de RAM del VPS se comparten con los
| demás grupos de la facultad, así que no se deja un worker de colas corriendo.
|
*/

// Los avisos a las clientas. Cada 10 minutos alcanza: el recordatorio se manda
// con un día de anticipación, no al minuto.
Schedule::command('spg:notificaciones')
    ->everyTenMinutes()
    ->withoutOverlapping()   // si una pasada se demora, la siguiente no se le encima
    ->runInBackground();
