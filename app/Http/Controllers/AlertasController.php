<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Alertas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La campanita, cuando alguien la abre.
 *
 * Lo único que hace es dejar marcado qué avisos ya vio esta persona, que es lo
 * que baja el numerito rojo. **No resuelve nada**: la caja sigue abierta y el
 * renglón se queda en la bandeja — lo que cambia es que deja de contar, como
 * cualquier bandeja de correo.
 *
 * **No toca los pendientes**, y es la excepción que pidió el usuario: lo que
 * falta cargar sigue contando hasta que alguien lo cargue. Verlo no lo
 * resuelve, así que marcarlo como leído sería apagarle el aviso al salón.
 * `Alertas::marcarVistas()` sólo acepta claves que hoy estén en la campanita
 * de quien llama, así que un POST armado a mano no puede marcar otra cosa.
 */
class AlertasController extends Controller
{
    public function vistas(Request $request): JsonResponse
    {
        $claves = (array) $request->input('claves', []);

        return response()->json(['marcadas' => Alertas::marcarVistas($claves)]);
    }
}
