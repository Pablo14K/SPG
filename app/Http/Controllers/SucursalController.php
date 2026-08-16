<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Auditoria;
use App\Servicios\Sucursales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * En qué local se trabaja hoy.
 *
 * Se mete entre el ingreso y el panel, como la pantalla de la huella. Por eso
 * vale la misma regla que aprendimos ahí: **la salida no puede depender del
 * JavaScript**, así que acá todo son formularios de verdad.
 *
 * Con una sola sucursal esta pantalla no se ve nunca: `Sesion::inicio()` la
 * resuelve sola al entrar.
 */
class SucursalController extends Controller
{
    public function elegir(): View|RedirectResponse
    {
        $suyas = Sucursales::delUsuario();

        // Sin ninguna sucursal no hay sistema al que entrar. Pasa si el
        // Administrador desactivó el local al que esa persona estaba asignada.
        if (! $suyas) {
            return redirect()->route('login')
                ->withErrors(['usuario' => 'Tu cuenta no tiene ninguna sucursal activa asignada. '
                    . 'Pedile al Administrador que te asigne una.']);
        }

        if (count($suyas) === 1 && Sucursales::activa() === 0) {
            Sucursales::entrar((int) $suyas[0]->id_sucursal);

            return redirect()->route('panel');
        }

        return view('sucursal.elegir', [
            'sucursales' => $suyas,
            'activa' => Sucursales::activa(),
        ]);
    }

    public function entrar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_sucursal', 0);

        if (! Sucursales::entrar($id)) {
            flash('No tenés acceso a esa sucursal.', 'error');

            return redirect()->route('sucursal.elegir');
        }

        // Queda en la auditoría porque desde acá se decide sobre qué caja,
        // qué stock y qué agenda va a operar esa persona: si algo no cuadra
        // en un local, lo primero es saber quién estuvo trabajando ahí.
        Auditoria::registrar('SUCURSAL', 'Seguridad', 'sucursal', $id,
            'Ingresó a la sucursal ' . Sucursales::nombreActiva());

        flash('Estás trabajando en ' . Sucursales::nombreActiva() . '.');

        return redirect()->route('panel');
    }
}
