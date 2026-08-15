<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Caja;
use App\Servicios\Permisos;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/**
 * El panel principal: por dónde se entra a todo lo demás.
 *
 * Muestra cuatro números del día, el estado de la caja, las tarjetas de los
 * módulos que el rol puede abrir y las próximas citas.
 */
class PanelController extends Controller
{
    public function index(): View
    {
        $hoy = date('Y-m-d');
        $todaLaAgenda = Permisos::veTodaLaAgenda();

        // **Cada número se muestra sólo a quien tiene el módulo del que sale**
        // (SE-01). Antes se calculaban los cuatro sin filtrar y la vista los
        // dibujaba siempre, así que una empleada entraba y veía cuánto facturó
        // el salón hoy, cuántas citas hay en total y cuántos productos faltan.
        // Es la misma fuga que la 7.13.1 corrigió para la barra de caja: ahí se
        // arregló la barra y no las métricas de al lado.
        //
        // Las citas siguen la regla de siempre —`veTodaLaAgenda()`, que es la
        // que comparten la agenda y las próximas citas—: quien no administra la
        // agenda ve las suyas, y el rótulo lo dice.
        $par = ['d' => $hoy];
        $soloMias = '';
        if (! $todaLaAgenda) {
            $soloMias = ' AND id_usuario = :yo';
            $par['yo'] = (int) session('uid');
        }

        $metricas = [
            'citas_hoy' => (int) DB::scalar(
                "SELECT COUNT(*) FROM cita
                  WHERE DATE(fecha_hora) = :d AND id_estado_cita NOT IN (3,6) $soloMias", $par
            ),
            'clientes' => Permisos::puede('clientes.registro')
                ? (int) DB::scalar('SELECT COUNT(*) FROM cliente WHERE activo = 1')
                : null,
            'bajo_stock' => Permisos::puede('inventario.stock') ? $this->bajoStock() : null,
            'ingresos_hoy' => Permisos::puede('facturacion.cobros')
                ? (float) DB::scalar(
                    'SELECT COALESCE(SUM(monto),0) FROM cobro WHERE DATE(fecha) = ? AND id_estado_cobro = 1', [$hoy]
                )
                : null,
        ];

        // Las próximas citas son LAS SUYAS, salvo que administre la agenda del
        // salón. Sin este filtro una profesional entraba y veía las citas de
        // sus compañeras: la misma regla que ya aplicaba la agenda no estaba
        // acá, así que el panel las mostraba todas.
        // `vw_agenda_citas` NO trae `id_usuario` —sólo el nombre del
        // profesional—, así que se une con `cita` para poder filtrar, igual
        // que hace la agenda.
        $parProx = [];
        $soloMiasProx = '';
        if (! $todaLaAgenda) {
            $soloMiasProx = ' AND c.id_usuario = :yo';
            $parProx['yo'] = (int) session('uid');
        }

        $proximas = DB::select(
            "SELECT v.* FROM vw_agenda_citas v
               JOIN cita c ON c.id_cita = v.id_cita
              WHERE v.fecha_hora >= NOW() AND v.estado NOT IN ('Cancelada','Ausente') $soloMiasProx
              ORDER BY v.fecha_hora LIMIT 6", $parProx
        );

        // Las atrasadas van en su propio bloque, no mezcladas con las próximas.
        //
        // Son las que ya pasaron de hora y nadie puso En proceso: el sistema no
        // decide que la clienta no vino —eso lo sabe quien atiende—, sólo las
        // junta para que alguien las mire y las marque. Sin este bloque había
        // que ir a la agenda del día y buscarlas a ojo, y una cita atrasada de
        // ayer no la miraba nadie nunca más.
        //
        // Se filtran con la MISMA regla que las próximas: quien no administra
        // la agenda del salón ve sólo las suyas.
        $atrasadas = DB::select(
            "SELECT v.* FROM vw_agenda_citas v
               JOIN cita c ON c.id_cita = v.id_cita
              WHERE v.estado = 'Atrasada' $soloMiasProx
              ORDER BY v.fecha_hora LIMIT 8", $parProx
        );

        // **La caja, sólo a quien tiene la caja.** Antes se preguntaba por el
        // módulo padre `facturacion`, y eso lo cumple cualquiera que tenga
        // ALGÚN submódulo —así resuelve la jerarquía—: a quien le sacaban la
        // caja le seguía apareciendo la barra con el saldo del salón.
        $verCaja = Permisos::puede('facturacion.caja');

        return view('panel', [
            'm' => $metricas,
            'proximas' => $proximas,
            'atrasadas' => $atrasadas,
            'verTodo' => $todaLaAgenda,
            'caja' => $verCaja ? Caja::abierta() : null,
            'verCaja' => $verCaja,
        ]);
    }

    /** Productos que cayeron al mínimo o por debajo: es lo que hay que comprar. */
    private function bajoStock(): int
    {
        try {
            return (int) DB::scalar('SELECT COUNT(*) FROM vw_producto_bajo_stock');
        } catch (Throwable) {
            return 0;
        }
    }
}
