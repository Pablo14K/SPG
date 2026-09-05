<?php

declare(strict_types=1);

/**
 * Constantes propias del sistema. Antes vivían sueltas en app/config.php,
 * app/agenda.php y app/listado.php; acá quedan todas juntas y se pueden mirar
 * (y cambiar) sin tocar código.
 */
return [

    // Versión del sistema, con versionado semántico X.Y.Z. Se muestra en el pie
    // de todas las pantallas. La migración a Laravel es un cambio estructural,
    // de los que rompen la compatibilidad: por eso 6.0.0.
    // Ciudades que se sugieren en los campos de ciudad. **Es una sugerencia,
    // no un catálogo**: el campo sigue aceptando cualquier texto, así que un
    // salón de una localidad que no esté acá no queda encerrado. Se listan las
    // del área metropolitana, que es de donde viene la clientela.
    'ciudades' => [
        'Luque', 'Asunción', 'San Lorenzo', 'Fernando de la Mora', 'Lambaré',
        'Capiatá', 'Ñemby', 'Mariano Roque Alonso', 'Villa Elisa', 'Limpio',
        'Itauguá', 'Areguá', 'San Antonio', 'Guarambaré', 'Ypané', 'Ypacaraí',
    ],

    'version' => '7.102.0',
    'version_fecha' => '2026-09-05',

    'moneda' => 'Gs.',

    // --- Fidelización -----------------------------------------------------
    // Cuántos guaraníes facturados valen un punto. Con 10.000, una factura de
    // Gs. 320.000 le deja 32 puntos al cliente. El *nivel* no depende de esto:
    // va por cantidad de visitas y lo resuelve fn_cliente_nivel en la base.
    //
    // **Desde la 7.27.0 este valor es sólo el RESPALDO.** El que manda vive en
    // `configuracion.puntos_cada_gs` y lo edita el salón desde Servicios →
    // Descuentos: es una decisión comercial, no técnica, y antes cambiarla
    // obligaba a tocar este archivo y volver a desplegar. Acá queda para que
    // una base que todavía no se reimportó siga acumulando puntos igual.
    // Se lee con `App\Servicios\Config::puntosCadaGs()`, nunca directo.
    'puntos_cada_gs' => 10000,

    // --- Agenda -----------------------------------------------------------
    'agenda' => [
        'paso_min' => 15,    // cada cuántos minutos se ofrece un horario
        'dias_vista' => 60,  // hasta cuántos días para adelante se reserva
        // Pausa mínima entre el fin de un turno y el comienzo del siguiente
        // en la misma sucursal. Se puede ajustar sin cambiar el esquema.
        'descanso_turnos_min' => 60,

        // **La jornada de un salón que todavía no cargó turnos.** Es la red del
        // criterio permisivo: mientras nadie tenga un turno, se ofrece esta
        // franja. Estaba clavada en 08:00–20:00 dentro del código, así que un
        // salón con otro horario le ofrecía a la clienta horas que no da. En
        // cuanto se cargan turnos, mandan los turnos y esto deja de usarse.
        'abre' => '08:00:00',
        'cierra' => '20:00:00',

        // **Cuántas horas se le guarda el lugar a quien todavía no señó.**
        //
        // La reserva de un servicio que pide seña no queda confirmada hasta que
        // el salón recibe el dinero, pero el horario **sí se reserva**: si no,
        // la clienta lo pierde mientras hace la transferencia y termina
        // llamando al salón, que es lo que esto viene a evitar.
        //
        // Pasado el plazo sin confirmar, `spg:notificaciones` la cancela y le
        // avisa. Sin plazo, un sillón queda bloqueado para siempre por alguien
        // que nunca pagó.
        'sena_horas' => 24,
    ],

    // --- Listados ---------------------------------------------------------
    'lista' => [
        'por_pagina' => 20,
        'max_por_pagina' => 200,   // nadie pide 5.000 filas de una, ni por la URL
    ],

    // --- Asistencia -------------------------------------------------------
    // Gracia para fichar: una hora antes de que empiece el turno y dos después
    // de que termine.
    'fichaje' => [
        'gracia_antes_min' => 60,
        'gracia_despues_min' => 120,
    ],

    /*
     * Cuántos minutos sin actividad cierran la sesión.
     *
     * **Se comprueba en `ExigeSesion`, no se deja en manos de Laravel**, y esa
     * es la diferencia que importa: cuando el framework descarta la sesión por
     * vencida no queda nada, así que la persona cae en el ingreso **sin saber
     * por qué** — y eso se lee como que el sistema la echó. Comprobándolo acá,
     * la sesión todavía existe cuando se decide cerrarla y se puede decir el
     * motivo.
     *
     * Por eso `SESSION_LIFETIME` del `.env` va MÁS ALTO que esto: es la red de
     * atrás, para que el archivo de sesión siga estando cuando este control
     * tiene que hablar. Si se lo pusiera igual, el que llegaría primero sería
     * el del framework y volveríamos al ingreso mudo.
     */
    'sesion' => [
        'inactividad_min' => 30,
    ],
];
