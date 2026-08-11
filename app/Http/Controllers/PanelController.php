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

        $proximas = DB::select(
            "SELECT * FROM vw_agenda_citas
              WHERE fecha_hora >= NOW() AND estado NOT IN ('Cancelada','Ausente')
              ORDER BY fecha_hora LIMIT 6"
        );

        // La caja solo se le muestra a quien maneja plata
        $verCaja = Permisos::puede('facturacion');

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
