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

        $metricas = [
            'citas_hoy' => (int) DB::scalar(
                'SELECT COUNT(*) FROM cita WHERE DATE(fecha_hora) = ? AND id_estado_cita NOT IN (3,6)', [$hoy]
            ),
            'clientes' => (int) DB::scalar('SELECT COUNT(*) FROM cliente WHERE activo = 1'),
            'bajo_stock' => $this->bajoStock(),
            'ingresos_hoy' => (float) DB::scalar(
                'SELECT COALESCE(SUM(monto),0) FROM cobro WHERE DATE(fecha) = ? AND id_estado_cobro = 1', [$hoy]
            ),
        ];

        // Las próximas citas son LAS SUYAS, salvo que administre la agenda del
        // salón. Sin este filtro una profesional entraba y veía las citas de
        // sus compañeras: la misma regla que ya aplicaba la agenda no estaba
        // acá, así que el panel las mostraba todas.
        // `vw_agenda_citas` NO trae `id_usuario` —sólo el nombre del
        // profesional—, así que se une con `cita` para poder filtrar, igual
        // que hace la agenda.
        $par = [];
        $soloMias = '';
        if (! Permisos::veTodaLaAgenda()) {
            $soloMias = ' AND c.id_usuario = :yo';
            $par['yo'] = (int) session('uid');
        }

        $proximas = DB::select(
            "SELECT v.* FROM vw_agenda_citas v
               JOIN cita c ON c.id_cita = v.id_cita
              WHERE v.fecha_hora >= NOW() AND v.estado NOT IN ('Cancelada','Ausente') $soloMias
              ORDER BY v.fecha_hora LIMIT 6", $par
        );

        // **La caja, sólo a quien tiene la caja.** Antes se preguntaba por el
        // módulo padre `facturacion`, y eso lo cumple cualquiera que tenga
        // ALGÚN submódulo —así resuelve la jerarquía—: a quien le sacaban la
        // caja le seguía apareciendo la barra con el saldo del salón.
        $verCaja = Permisos::puede('facturacion.caja');

        return view('panel', [
            'm' => $metricas,
            'proximas' => $proximas,
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
