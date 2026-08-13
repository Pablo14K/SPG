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
    'version' => '7.12.0',
    'version_fecha' => '2026-08-13',

    'moneda' => 'Gs.',

    // --- Fidelización -----------------------------------------------------
    // Cuántos guaraníes facturados valen un punto. Con 10.000, una factura de
    // Gs. 320.000 le deja 32 puntos al cliente. El *nivel* no depende de esto:
    // va por cantidad de visitas y lo resuelve fn_cliente_nivel en la base.
    'puntos_cada_gs' => 10000,

    // --- Agenda -----------------------------------------------------------
    'agenda' => [
        'paso_min' => 15,    // cada cuántos minutos se ofrece un horario
        'dias_vista' => 60,  // hasta cuántos días para adelante se reserva
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
];
