<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Registro de lo que se hace en el sistema.
 *
 * CUIDADO antes de llamar a registrar(): fijate si la base ya lo audita sola.
 * Los disparadores `trg_factura_au`, `trg_cobro_au`, `trg_pagopersonal_au` y
 * `trg_pagoproveedor_au` escriben en `auditoria` cuando el estado pasa a
 * anulado o revertido, tomando el usuario de `@usuario_actual` (que dejan
 * puesto los `sp_anular_*` y `sp_revertir_*`). En esos casos registrar() deja
 * DOS filas por la misma acción: para eso está anotarMotivo(), que le agrega
 * el motivo a la fila que ya escribió el disparador.
 */
class Auditoria
{
    /**
     * Registra una acción a nombre de quien tiene la sesión abierta.
     * Si no hay sesión no se registra: `auditoria.id_usuario` es NOT NULL.
     */
    public static function registrar(string $accion, string $modulo, string $tabla, ?int $idRegistro = null, ?string $detalle = null): void
    {
        $uid = (int) session('uid', 0);
        if ($uid <= 0) {
            return;
        }
        self::escribir($uid, $accion, $modulo, $tabla, $idRegistro, $detalle);
    }

    /**
     * Registra a nombre de un usuario que se indica.
     *
     * Hace falta para lo que dispara la clienta desde el enlace del correo,
     * donde no hay sesión: se atribuye a su cuenta si la tiene y, si no —la
     * clienta que agendó en el local y nunca se registró—, al profesional de
     * la cita, aclarándolo en el detalle.
     */
    public static function registrarComo(int $idUsuario, string $accion, string $modulo, string $tabla, ?int $idRegistro = null, ?string $detalle = null): void
    {
        if ($idUsuario <= 0) {
            return;
        }
        self::escribir($idUsuario, $accion, $modulo, $tabla, $idRegistro, $detalle);
    }

    /**
     * Le agrega el motivo a la fila que escribió el disparador, en vez de
     * crear una segunda. Lo único que el disparador no puede saber es por qué
     * lo hizo la persona.
     */
    public static function anotarMotivo(string $tabla, int $idRegistro, string $motivo): void
    {
        if ($motivo === '') {
            return;
        }
        try {
            DB::update(
                "UPDATE auditoria SET detalle = CONCAT(COALESCE(detalle,''), ' Motivo: ', ?)
                  WHERE tabla_afectada = ? AND id_registro = ? AND accion IN ('ANULAR','REVERTIR')
                  ORDER BY id_auditoria DESC LIMIT 1",
                [$motivo, $tabla, $idRegistro]
            );
        } catch (Throwable) {
            // La auditoría nunca debe romper la operación principal
        }
    }

    private static function escribir(int $idUsuario, string $accion, string $modulo, string $tabla, ?int $idRegistro, ?string $detalle): void
    {
        try {
            DB::insert(
                'INSERT INTO auditoria (id_usuario, accion, modulo, tabla_afectada, id_registro, detalle)
                 VALUES (?,?,?,?,?,?)',
                [$idUsuario, $accion, $modulo, $tabla, $idRegistro, $detalle]
            );
        } catch (Throwable) {
            // La auditoría nunca debe romper la operación principal
        }
    }
}
