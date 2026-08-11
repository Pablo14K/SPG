<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Auditoria;
use App\Servicios\Bd;
use App\Servicios\Caja;
use App\Servicios\Facturacion;
use App\Servicios\Listado;
use App\Servicios\Permisos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Facturación y caja.
 *
 * Todo lo que numera, calcula y anula vive en la base. Lo que agrega este
 * controlador es la validación previa y el guardián de la caja.
 */
class FacturacionController extends Controller
{
    public function index(): View
    {
        return view('facturacion.index', [
            'subs' => Permisos::tarjetasPermitidas([
                ['p' => 'facturacion.facturas', 'ruta' => 'facturacion.facturas', 'ic' => 'receipt',
                 't' => 'Facturas', 'd' => 'Comprobantes emitidos'],
                ['p' => 'facturacion.cobros', 'ruta' => 'facturacion.cobros', 'ic' => 'cash-coin',
                 't' => 'Cobros', 'd' => 'Pagos recibidos de clientes'],
                ['p' => 'facturacion.caja', 'ruta' => 'facturacion.caja', 'ic' => 'safe',
                 't' => 'Caja', 'd' => 'Apertura, cierre y saldo'],
                ['p' => 'facturacion.pagos', 'ruta' => 'facturacion.pagos', 'ic' => 'wallet2',
                 't' => 'Pagos al personal', 'd' => 'Comisiones y liquidaciones'],
                ['p' => 'facturacion.proveedores', 'ruta' => 'facturacion.proveedores', 'ic' => 'truck',
                 't' => 'Pagos a proveedores', 'd' => 'Cuentas por pagar de compras'],
                ['p' => 'facturacion.timbrados', 'ruta' => 'facturacion.timbrados', 'ic' => 'file-earmark-text',
                 't' => 'Timbrados', 'd' => 'Numeración de los comprobantes'],
            ]),
        ]);
    }

    // -----------------------------------------------------------------
    //  Sin caja abierta no se mueve plata
    //
    //  El arqueo del día tiene que cerrar. Todo lo que entra o sale se imputa
    //  a la caja abierta, así que un movimiento con la caja cerrada queda
    //  fuera del arqueo y la plata no aparece por ningún lado.
    // -----------------------------------------------------------------

    private function exigeCaja(string $queIbaAHacer): stdClass|RedirectResponse
    {
        if ($caja = Caja::abierta()) {
            return $caja;
        }

        // A quien no administra la caja no se lo manda a una pantalla que no
        // puede abrir: se le dice que la caja la tiene que abrir otra persona.
        $puedeCaja = Permisos::puede('facturacion.caja');
        flash($puedeCaja
            ? 'Abrí la caja antes de ' . $queIbaAHacer . ': con la caja cerrada el movimiento '
              . 'no entra en ningún arqueo y el saldo del día no cierra.'
            : 'No hay ninguna caja abierta, así que todavía no se puede ' . $queIbaAHacer . '. '
              . 'Pedile a quien maneja la caja que la abra.', 'error');

        return redirect()->route($puedeCaja ? 'facturacion.caja' : 'facturacion.index');
    }

    // -----------------------------------------------------------------
    //  Facturas
    // -----------------------------------------------------------------

    public function facturas(): View|StreamedResponse
    {
        $f = Listado::filtros([
            'q' => ['tipo' => 'texto', 'etiqueta' => 'Buscar', 'ph' => 'Nº de comprobante o cliente', 'ancho' => '260px'],
            'estado' => ['tipo' => 'select', 'etiqueta' => 'Estado', 'opciones' => ['' => 'Todos'] + $this->opciones('estado_factura', 'nombre', 'nombre')],
            'tipo' => ['tipo' => 'select', 'etiqueta' => 'Comprobante', 'opciones' => ['' => 'Todos'] + $this->opciones('tipo_comprobante', 'nombre', 'nombre')],
            'saldo' => ['tipo' => 'select', 'etiqueta' => 'Cobranza',
                        'opciones' => ['' => 'Todas', 'pend' => 'Con saldo', 'ok' => 'Saldadas']],
            'desde' => ['tipo' => 'fecha', 'etiqueta' => 'Desde'],
            'hasta' => ['tipo' => 'fecha', 'etiqueta' => 'Hasta'],
        ]);
        $f['csv'] = true;

        $w = ['1=1'];
        $par = [];
        if (Listado::hay($f, 'q')) {
            $w[] = Listado::likeVarias(['v.nro_comprobante', 'v.cliente'], Listado::valor($f, 'q'), 'q', $par);
        }
        if (Listado::hay($f, 'estado')) {
            $w[] = 'v.estado = :est';
            $par['est'] = Listado::valor($f, 'estado');
        }
        if (Listado::hay($f, 'tipo')) {
            $w[] = 'v.tipo_comprobante = :tip';
            $par['tip'] = Listado::valor($f, 'tipo');
        }
        if (Listado::hay($f, 'saldo')) {
            $w[] = Listado::valor($f, 'saldo') === 'pend' ? 'v.saldo > 0' : 'v.saldo <= 0';
        }
        if (Listado::hay($f, 'desde')) {
            $w[] = 'DATE(v.fecha_emision) >= :d';
            $par['d'] = Listado::valor($f, 'desde');
        }
        if (Listado::hay($f, 'hasta')) {
            $w[] = 'DATE(v.fecha_emision) <= :h';
            $par['h'] = Listado::valor($f, 'hasta');
        }

        $desde = 'FROM vw_factura_resumen v WHERE ' . implode(' AND ', $w);

        if (Listado::pideCsv()) {
            return Listado::csv('facturas',
                ['Nº', 'Fecha', 'Cliente', 'Comprobante', 'Total', 'Cobrado', 'Saldo', 'Estado'],
                array_map(fn ($r) => [$r->nro_comprobante, fecha($r->fecha_emision, 'd/m/Y H:i'), $r->cliente,
                    $r->tipo_comprobante, $r->total, $r->cobrado, $r->saldo, $r->estado],
                    DB::select("SELECT * $desde ORDER BY v.fecha_emision DESC", $par))
            );
        }

        $pag = Listado::paginacion((int) DB::scalar("SELECT COUNT(*) $desde", $par));

        // `vw_factura_resumen` ya trae el signo: sirve para no ofrecer «Cobrar»
        // sobre una nota de crédito, que no se cobra.
        return view('facturacion.facturas', [
            'rows' => DB::select("SELECT * $desde ORDER BY v.fecha_emision DESC LIMIT {$pag['porPagina']} OFFSET {$pag['offset']}", $par),
            // El `tipo` decide qué datos extra se piden (tarjeta / banco / cheque)
            'metodos' => DB::select('SELECT id_metodo_pago, nombre, tipo FROM metodo_pago WHERE activo = 1 ORDER BY id_metodo_pago'),
            'caja' => Caja::abierta(),
            'f' => $f,
            'pag' => $pag,
        ]);
    }

    /**
     * Detalle e impresión de un comprobante.
     *
     * Todo sale de las vistas de la base: vw_factura_resumen para la cabecera,
     * vw_detalle_factura para las líneas y vw_factura_impuestos para el
     * desglose de IVA, que en Paraguay va incluido en el precio y se desglosa,
     * no se suma aparte.
     */
    public function facturaVer(Request $request): View|RedirectResponse
    {
        $id = (int) $request->query('id', 0);

        $f = DB::selectOne('SELECT * FROM vw_factura_resumen WHERE id_factura = ?', [$id]);
        if (! $f) {
            flash('Esa factura no existe.', 'error');

            return redirect()->route('facturacion.facturas');
        }

        return view('facturacion.factura_ver', [
            'f' => $f,
            'lineas' => DB::select('SELECT * FROM vw_detalle_factura WHERE id_factura = ? ORDER BY clase, item', [$id]),
            'imp' => DB::selectOne('SELECT * FROM vw_factura_impuestos WHERE id_factura = ?', [$id]),
            'emisor' => DB::selectOne(
                'SELECT s.nombre, s.ruc, s.telefono, s.direccion, s.ciudad,
                        t.nro_timbrado, t.fecha_inicio AS timbrado_desde, t.fecha_fin AS timbrado_hasta
                   FROM factura fa
                   JOIN timbrado t ON t.id_timbrado = fa.id_timbrado
                   JOIN sucursal s ON s.id_sucursal = t.id_sucursal
                  WHERE fa.id_factura = ?', [$id]
            ),
            'cli' => DB::selectOne(
                'SELECT pe_c.nombre, pe_c.apellido, pe_c.cedula, pe_c.ruc, pe_c.telefono, pe_c.email
                   FROM factura fa JOIN cliente c ON c.id_cliente = fa.id_cliente
                   JOIN persona pe_c ON pe_c.id_persona = c.id_persona
                  WHERE fa.id_factura = ?', [$id]
            ),
            // Los cobros de la factura y también la seña, que va atada a la cita
            // y no a la factura. `cobrado` de vw_factura_resumen ya la suma: si
            // no se mostrara acá, el total cobrado no cuadraría con la lista.
            'cobros' => DB::select(
                'SELECT co.id_cobro, co.fecha, co.monto, co.referencia,
                        mp.nombre AS metodo, mp.tipo, ec.nombre AS estado,
                        (co.id_factura IS NULL) AS es_sena,
                        ct.marca, ct.tipo_tarjeta, ct.cuotas, ct.ultimos_4, ct.nro_boleta, ct.cod_autorizacion,
                        cb.banco, cb.nro_cheque, cb.nro_operacion, cb.fecha_emision
                   FROM cobro co
                   JOIN metodo_pago mp  ON mp.id_metodo_pago = co.id_metodo_pago
                   JOIN estado_cobro ec ON ec.id_estado_cobro = co.id_estado_cobro
                   LEFT JOIN cobro_tarjeta ct ON ct.id_cobro = co.id_cobro
                   LEFT JOIN cobro_banco   cb ON cb.id_cobro = co.id_cobro
                  WHERE co.id_factura = :f
                     OR co.id_cita = (SELECT fa.id_cita FROM factura fa WHERE fa.id_factura = :f2)
                  ORDER BY co.fecha', ['f' => $id, 'f2' => $id]
            ),
            'notas' => DB::select(
                'SELECT n.id_factura, fn_factura_nro(n.id_factura) AS nro, n.fecha_emision,
                        n.observaciones AS motivo, fn_factura_total(n.id_factura) AS total
                   FROM factura n WHERE n.id_factura_origen = ? AND n.id_estado_factura = 1
                  ORDER BY n.fecha_emision', [$id]
            ),
        ]);
    }

    /** Citas atendidas que todavía no tienen factura. */
    public function emitir(): View
    {
        return view('facturacion.emitir', [
            'citas' => DB::select(
                "SELECT c.id_cita, c.id_cliente, c.fecha_hora,
                        CONCAT(pe_cl.nombre,' ',pe_cl.apellido) AS cliente,
                        (SELECT GROUP_CONCAT(s.nombre SEPARATOR ', ')
                           FROM cita_servicio cs JOIN servicio s ON s.id_servicio = cs.id_servicio
                          WHERE cs.id_cita = c.id_cita) AS servicios,
                        (SELECT COALESCE(SUM(s.precio),0)
                           FROM cita_servicio cs JOIN servicio s ON s.id_servicio = cs.id_servicio
                          WHERE cs.id_cita = c.id_cita) AS total
                   FROM cita c
                   JOIN cliente cl    ON cl.id_cliente = c.id_cliente
                   JOIN persona pe_cl ON pe_cl.id_persona = cl.id_persona
                  WHERE c.id_estado_cita = 4
                    AND NOT EXISTS (SELECT 1 FROM factura f WHERE f.id_cita = c.id_cita AND f.id_estado_factura = 1)
                  ORDER BY c.fecha_hora DESC LIMIT 100"
            ),
            // Solo los comprobantes de venta que hoy tienen timbrado vigente
            'tipos' => DB::select(
                'SELECT tc.id_tipo_comprobante, tc.nombre
                   FROM tipo_comprobante tc
                  WHERE tc.activo = 1 AND tc.signo = 1 AND tc.requiere_origen = 0
                    AND fn_timbrado_vigente(tc.id_tipo_comprobante, CURDATE()) IS NOT NULL
                  ORDER BY tc.id_tipo_comprobante'
            ),
            'condiciones' => DB::select(
                'SELECT id_condicion_venta, nombre, dias_credito FROM condicion_venta WHERE activo = 1
                  ORDER BY id_condicion_venta'
            ),
        ]);
    }

    public function emitirGuardar(Request $request): RedirectResponse
    {
        $idCita = (int) $request->input('id_cita', 0);
        $idTipo = (int) $request->input('id_tipo_comprobante', 1) ?: 1;
        $idCond = (int) $request->input('id_condicion_venta', 1) ?: 1;

        // El cliente se toma de la cita, no del formulario: así nadie puede
        // facturarle a un tercero manipulando el campo oculto.
        $cita = DB::selectOne('SELECT id_cliente, id_estado_cita FROM cita WHERE id_cita = ?', [$idCita]);
        if (! $cita) {
            flash('Esa cita no existe.', 'error');

            return redirect()->route('facturacion.emitir');
        }
        if ((int) $cita->id_estado_cita !== 4) {
            flash('Solo se factura una cita ya atendida.', 'error');

            return redirect()->route('facturacion.emitir');
        }
        if (DB::scalar('SELECT COUNT(*) FROM factura WHERE id_cita = ? AND id_estado_factura = 1', [$idCita])) {
            flash('Esa cita ya tiene una factura emitida.', 'warning');

            return redirect()->route('facturacion.facturas');
        }
        if (! DB::scalar('SELECT COUNT(*) FROM cita_servicio WHERE id_cita = ?', [$idCita])) {
            flash('La cita no tiene servicios cargados, no hay nada que facturar.', 'error');

            return redirect()->route('facturacion.emitir');
        }
        if (! Facturacion::hayTimbrado($idTipo)) {
            flash('No hay un timbrado vigente para ese comprobante. Cargalo en Facturación → Timbrados.', 'error');

            return redirect()->route('facturacion.timbrados');
        }

        try {
            $idf = Facturacion::emitir((int) $cita->id_cliente, $idCita, (int) session('uid'), $idTipo, $idCond);
            $nro = Facturacion::numero($idf);
            Auditoria::registrar('EMISION', 'Facturacion', 'factura', $idf,
                'Comprobante ' . $nro . ' de la cita #' . $idCita);

            $puntos = Facturacion::acumularPuntos($idf, (int) $cita->id_cliente);
            flash('Factura ' . $nro . ' emitida correctamente.'
                . ($puntos ? ' El cliente sumó ' . $puntos . ' punto(s) de fidelización.' : ''));
        } catch (Throwable $ex) {
            $msg = $ex->getMessage();
            flash(str_contains($msg, 'timbrado') ? 'No hay timbrado vigente para la factura.'
                : (str_contains($msg, 'agotado') ? 'Se agotó el rango de numeración del timbrado. Cargá uno nuevo.'
                    : 'No se pudo emitir la factura.'), 'error');

            return redirect()->route('facturacion.emitir');
        }

        return redirect()->route('facturacion.facturas');
    }

    // -----------------------------------------------------------------
    //  Cobros
    // -----------------------------------------------------------------

    public function cobrar(Request $request): RedirectResponse
    {
        $idFactura = (int) $request->input('id_factura', 0);
        $metodos = (array) $request->input('metodo', []);
        $montos = (array) $request->input('monto', []);
        $volver = redirect()->route('facturacion.facturas');

        $lineas = [];
        foreach ($metodos as $i => $m) {
            $idm = (int) $m;
            $mto = num($montos[$i] ?? 0);
            if ($idm <= 0 && $mto <= 0) {
                continue;   // fila vacía: se ignora
            }

            $mp = DB::selectOne('SELECT nombre, tipo FROM metodo_pago WHERE id_metodo_pago = ? AND activo = 1', [$idm]);
            if (! $mp) {
                flash('Hay una línea con un método de pago que no existe o está inactivo.', 'error');

                return $volver;
            }
            if ($mto <= 0) {
                flash('El monto de ' . $mp->nombre . ' tiene que ser mayor a cero.', 'error');

                return $volver;
            }

            $lineas[] = [
                'metodo' => $idm, 'monto' => $mto, 'tipo' => $mp->tipo, 'nombre' => $mp->nombre,
                'referencia' => trim((string) (((array) $request->input('referencia', []))[$i] ?? '')) ?: null,
                'detalle' => $this->detalleDeLinea($request, $i),
            ];
        }

        if (! $lineas) {
            flash('Cargá al menos un medio de pago con su monto.', 'error');

            return $volver;
        }

        $saldo = Facturacion::saldo($idFactura);
        $suma = array_sum(array_column($lineas, 'monto'));
        if ($suma - $saldo > 0.01) {
            flash('La suma de los medios (' . money($suma) . ') supera el saldo pendiente ('
                . money($saldo) . ').', 'error');

            return $volver;
        }

        $caja = $this->exigeCaja('registrar un cobro');
        if ($caja instanceof RedirectResponse) {
            return $caja;
        }

        try {
            $r = Facturacion::cobrar($idFactura, (int) session('uid'), $lineas, (int) $caja->id_caja);

            Auditoria::registrar('COBRO', 'Facturacion', 'factura', $idFactura,
                'Cobro ' . money($r['total'])
                . (count($lineas) > 1 ? ' en ' . count($lineas) . ' medios: ' . implode(' + ', $r['detalle']) : ''));

            $saldoNuevo = Facturacion::saldo($idFactura);
            flash('Cobro registrado por ' . money($r['total']) . '.'
                . (count($lineas) > 1 ? ' (' . implode(' + ', $r['detalle']) . ')' : '')
                . ($saldoNuevo > 0.01 ? ' Queda un saldo de ' . money($saldoNuevo) . '.' : ' La factura quedó saldada.'));
        } catch (Throwable $ex) {
            $msg = $ex->getMessage();
            flash((str_contains($msg, 'saldo') ? 'El monto supera el saldo pendiente de la factura.'
                : (str_contains($msg, 'anulada') ? 'La factura está anulada.'
                    : (str_contains($msg, 'no se cobra') ? 'Ese tipo de comprobante no se cobra.'
                        : (str_contains($msg, 'tarjeta') ? 'El detalle de tarjeta no corresponde a ese medio de pago.'
                            : (str_contains($msg, 'banco') ? 'El detalle bancario no corresponde a ese medio de pago.'
                                : 'No se pudo registrar el cobro.')))))
                . ' No se guardó ninguna de las líneas.', 'error');
        }

        return $volver;
    }

    private function detalleDeLinea(Request $request, int $i): array
    {
        $campos = ['marca', 'tipo_tarjeta', 'cuotas', 'ultimos_4', 'nro_boleta', 'cod_autorizacion',
                   'banco', 'nro_cheque', 'nro_operacion', 'fecha_emision'];
        $out = [];
        foreach ($campos as $c) {
            $out[$c] = ((array) $request->input($c, []))[$i] ?? null;
        }

        return $out;
    }

    public function cobros(): View|StreamedResponse
    {
        $f = Listado::filtros([
            'q' => ['tipo' => 'texto', 'etiqueta' => 'Buscar', 'ph' => 'Cliente o referencia', 'ancho' => '240px'],
            'metodo' => ['tipo' => 'select', 'etiqueta' => 'Medio de pago',
                         'opciones' => ['' => 'Todos'] + $this->opciones('metodo_pago', 'id_metodo_pago', 'nombre')],
            'estado' => ['tipo' => 'select', 'etiqueta' => 'Estado',
                         'opciones' => ['' => 'Todos'] + $this->opciones('estado_cobro', 'id_estado_cobro', 'nombre')],
            'desde' => ['tipo' => 'fecha', 'etiqueta' => 'Desde'],
            'hasta' => ['tipo' => 'fecha', 'etiqueta' => 'Hasta'],
        ]);
        $f['csv'] = true;

        $w = ['1=1'];
        $par = [];
        if (Listado::hay($f, 'q')) {
            $w[] = Listado::likeVarias(["CONCAT(pe_cl.nombre,' ',pe_cl.apellido)", 'co.referencia'],
                Listado::valor($f, 'q'), 'q', $par);
        }
        if (Listado::hay($f, 'metodo')) {
            $w[] = 'co.id_metodo_pago = :m';
            $par['m'] = (int) Listado::valor($f, 'metodo');
        }
        if (Listado::hay($f, 'estado')) {
            $w[] = 'co.id_estado_cobro = :e';
            $par['e'] = (int) Listado::valor($f, 'estado');
        }
        if (Listado::hay($f, 'desde')) {
            $w[] = 'DATE(co.fecha) >= :d';
            $par['d'] = Listado::valor($f, 'desde');
        }
        if (Listado::hay($f, 'hasta')) {
            $w[] = 'DATE(co.fecha) <= :h';
            $par['h'] = Listado::valor($f, 'hasta');
        }

        $desde = 'FROM cobro co
                  JOIN metodo_pago mp   ON mp.id_metodo_pago = co.id_metodo_pago
                  JOIN estado_cobro ec  ON ec.id_estado_cobro = co.id_estado_cobro
                  LEFT JOIN factura fa   ON fa.id_factura = co.id_factura
                  LEFT JOIN cliente cl   ON cl.id_cliente = fa.id_cliente
                  LEFT JOIN persona pe_cl ON pe_cl.id_persona = cl.id_persona
                  WHERE ' . implode(' AND ', $w);
        $cols = "co.id_cobro, co.fecha, co.monto, co.referencia, mp.nombre AS metodo, ec.nombre AS estado,
                 (co.id_factura IS NULL AND co.id_cita IS NOT NULL) AS es_sena,
                 fn_factura_nro(co.id_factura) AS nro_comprobante,
                 CONCAT(pe_cl.nombre,' ',pe_cl.apellido) AS cliente";

        if (Listado::pideCsv()) {
            return Listado::csv('cobros',
                ['Fecha', 'Cliente', 'Comprobante', 'Medio', 'Monto', 'Referencia', 'Estado'],
                array_map(fn ($r) => [fecha($r->fecha, 'd/m/Y H:i'), $r->cliente ?: '(seña sin factura)',
                    $r->nro_comprobante, $r->metodo, $r->monto, $r->referencia, $r->estado],
                    DB::select("SELECT $cols $desde ORDER BY co.fecha DESC", $par))
            );
        }

        $pag = Listado::paginacion((int) DB::scalar("SELECT COUNT(*) $desde", $par));

        return view('facturacion.cobros', [
            'rows' => DB::select("SELECT $cols $desde ORDER BY co.fecha DESC LIMIT {$pag['porPagina']} OFFSET {$pag['offset']}", $par),
            // El total del filtro es el dato que más se mira: cuánto se cobró en
            // ese período o por ese medio. Se suma sobre TODO lo filtrado.
            'totalFiltrado' => (float) DB::scalar(
                "SELECT COALESCE(SUM(CASE WHEN co.id_estado_cobro = 1 THEN co.monto ELSE 0 END),0) $desde", $par),
            'f' => $f,
            'pag' => $pag,
        ]);
    }

    public function anularCobro(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_cobro', 0);
        $motivo = trim((string) $request->input('motivo', ''));
        $destino = redirect()->route('facturacion.cobros');

        $c = DB::selectOne('SELECT id_cobro, monto, id_estado_cobro FROM cobro WHERE id_cobro = ?', [$id]);
        if (! $c) {
            flash('Ese cobro no existe.', 'error');

            return $destino;
        }
        if ((int) $c->id_estado_cobro === 3) {
            flash('Ese cobro ya estaba anulado.', 'warning');

            return $destino;
        }
        if ($motivo === '') {
            flash('Escribí el motivo de la anulación: queda en la auditoría.', 'error');

            return $destino;
        }

        // Anular un cobro le resta plata al arqueo: la caja tiene que estar abierta
        $caja = $this->exigeCaja('anular un cobro');
        if ($caja instanceof RedirectResponse) {
            return $caja;
        }

        try {
            Facturacion::anularCobro($id, (int) session('uid'));
            Auditoria::anotarMotivo('cobro', $id, $motivo);
            flash('Cobro de ' . money($c->monto) . ' anulado. El saldo de la factura se recalculó solo.');
        } catch (Throwable) {
            flash('No se pudo anular el cobro.', 'error');
        }

        return $destino;
    }

    // -----------------------------------------------------------------
    //  Anulaciones y nota de crédito
    // -----------------------------------------------------------------

    public function anularFactura(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_factura', 0);
        $motivo = trim((string) $request->input('motivo', ''));
        $volver = redirect()->route('facturacion.factura_ver', ['id' => $id]);

        $f = DB::selectOne(
            'SELECT f.id_factura, f.id_cliente, f.id_estado_factura, fn_factura_nro(f.id_factura) AS nro,
                    (SELECT COUNT(*) FROM cobro c WHERE c.id_factura = f.id_factura AND c.id_estado_cobro = 1) AS cobros
               FROM factura f WHERE f.id_factura = ?', [$id]
        );
        if (! $f) {
            flash('Esa factura no existe.', 'error');

            return redirect()->route('facturacion.facturas');
        }
        if ((int) $f->id_estado_factura === 2) {
            flash('Esa factura ya estaba anulada.', 'warning');

            return $volver;
        }
        // La base exige el orden: primero se anulan los cobros, después la factura
        if ((int) $f->cobros > 0) {
            flash('Esa factura tiene ' . (int) $f->cobros . ' cobro(s) registrado(s). '
                . 'Anulá primero los cobros y después la factura.', 'warning');

            return $volver;
        }
        if ($motivo === '') {
            flash('Escribí el motivo de la anulación: queda en la auditoría.', 'error');

            return $volver;
        }

        $caja = $this->exigeCaja('anular un comprobante');
        if ($caja instanceof RedirectResponse) {
            return $caja;
        }

        try {
            Facturacion::anularFactura($id, (int) session('uid'));
            Auditoria::anotarMotivo('factura', $id, $motivo);
            $devueltos = Facturacion::revertirPuntos($id, (int) $f->id_cliente);
            flash('Comprobante ' . $f->nro . ' anulado.'
                . ($devueltos ? ' Se le descontaron al cliente los ' . $devueltos . ' punto(s) que había sumado.' : ''));
        } catch (Throwable $ex) {
            flash(str_contains($ex->getMessage(), 'cobros')
                ? 'Anulá primero los cobros de esta factura.' : 'No se pudo anular la factura.', 'error');
        }

        return $volver;
    }

    public function notaCredito(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_factura', 0);
        $motivo = trim((string) $request->input('motivo', ''));
        $volver = redirect()->route('facturacion.factura_ver', ['id' => $id]);

        $f = DB::selectOne(
            'SELECT f.id_factura, f.id_cliente, f.id_estado_factura, tc.signo,
                    fn_factura_nro(f.id_factura) AS nro,
                    (SELECT COUNT(*) FROM factura n
                      WHERE n.id_factura_origen = f.id_factura AND n.id_estado_factura = 1) AS notas
               FROM factura f
               JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = f.id_tipo_comprobante
              WHERE f.id_factura = ?', [$id]
        );
        if (! $f) {
            flash('Esa factura no existe.', 'error');

            return redirect()->route('facturacion.facturas');
        }
        if ((int) $f->signo !== 1) {
            flash('Solo se puede acreditar un comprobante de venta, no otra nota de crédito.', 'error');

            return $volver;
        }
        if ((int) $f->id_estado_factura === 2) {
            flash('Ese comprobante está anulado: no hace falta acreditarlo.', 'warning');

            return $volver;
        }
        if ((int) $f->notas > 0) {
            flash('Ese comprobante ya tiene una nota de crédito emitida.', 'warning');

            return $volver;
        }
        if ($motivo === '') {
            flash('Escribí el motivo de la nota de crédito: se imprime en el comprobante.', 'error');

            return $volver;
        }
        // El timbrado de notas de crédito (tipo 5) es distinto del de facturas
        if (! Facturacion::hayTimbrado(5)) {
            flash('No hay timbrado vigente para notas de crédito. Cargalo en Facturación → Timbrados.', 'error');

            return redirect()->route('facturacion.timbrados');
        }

        // Una nota de crédito le devuelve plata al cliente: es un movimiento y
        // tiene que quedar dentro de un arqueo, igual que el cobro.
        $caja = $this->exigeCaja('emitir una nota de crédito');
        if ($caja instanceof RedirectResponse) {
            return $caja;
        }

        try {
            $idNota = Facturacion::notaCredito($id, (int) session('uid'), $motivo);
            $nroNota = Facturacion::numero($idNota);
            Auditoria::registrar('NOTA_CREDITO', 'Facturacion', 'factura', $idNota,
                'Nota de crédito ' . $nroNota . ' sobre ' . $f->nro . ' — ' . $motivo);

            $devueltos = Facturacion::revertirPuntos($id, (int) $f->id_cliente, 'Nota de crédito');
            flash('Nota de crédito ' . $nroNota . ' emitida sobre ' . $f->nro . '.'
                . ($devueltos ? ' Se le descontaron al cliente los ' . $devueltos . ' punto(s) de esa venta.' : ''));

            return redirect()->route('facturacion.factura_ver', ['id' => $idNota]);
        } catch (Throwable $ex) {
            $msg = $ex->getMessage();
            flash(str_contains($msg, 'timbrado') ? 'No hay timbrado vigente para notas de crédito.'
                : (str_contains($msg, 'agotado') ? 'Se agotó la numeración del timbrado de notas de crédito.'
                    : (str_contains($msg, 'venta') ? 'Solo se puede acreditar un comprobante de venta.'
                        : 'No se pudo emitir la nota de crédito.')), 'error');

            return $volver;
        }
    }

    // -----------------------------------------------------------------
    //  Seña
    // -----------------------------------------------------------------

    public function sena(Request $request): RedirectResponse
    {
        $idCita = (int) $request->input('id_cita', 0);
        $idMetodo = (int) $request->input('id_metodo_pago', 0);
        $monto = num($request->input('monto'));
        $ref = trim((string) $request->input('referencia', '')) ?: null;
        $dia = (string) $request->input('dia', date('Y-m-d'));
        $volver = redirect()->route('citas.agenda', ['dia' => $dia]);

        $cita = DB::selectOne(
            "SELECT c.id_cita, c.id_estado_cita, CONCAT(pe_cl.nombre,' ',pe_cl.apellido) AS cliente
               FROM cita c JOIN cliente cl ON cl.id_cliente = c.id_cliente
               JOIN persona pe_cl ON pe_cl.id_persona = cl.id_persona WHERE c.id_cita = ?", [$idCita]
        );

        $error = null;
        if (! $cita) {
            $error = 'Esa cita no existe.';
        } elseif (in_array((int) $cita->id_estado_cita, [3, 6], true)) {
            $error = 'No se puede señar una cita cancelada o marcada como ausente.';
        } elseif ((int) $cita->id_estado_cita === 4) {
            $error = 'Esa cita ya fue atendida: cobrala desde la factura, no como seña.';
        } elseif ($monto <= 0) {
            $error = 'Ingresá un monto mayor a cero.';
        } elseif (! $idMetodo || ! DB::scalar('SELECT COUNT(*) FROM metodo_pago WHERE id_metodo_pago = ? AND activo = 1', [$idMetodo])) {
            $error = 'Elegí un método de pago válido.';
        }
        if ($error) {
            flash($error, 'error');

            return $volver;
        }

        $caja = $this->exigeCaja('recibir una seña');
        if ($caja instanceof RedirectResponse) {
            return $caja;
        }

        try {
            $idCobro = Facturacion::sena($idCita, $idMetodo, (int) session('uid'), $monto, $ref, (int) $caja->id_caja);
            Auditoria::registrar('SENA', 'Facturacion', 'cobro', $idCobro,
                'Seña de ' . money($monto) . ' por la cita #' . $idCita . ' (' . $cita->cliente . ')');
            flash('Seña de ' . money($monto) . ' registrada para ' . $cita->cliente
                . '. Se va a descontar sola del total cuando se facture la cita.');
        } catch (Throwable $ex) {
            flash(str_contains($ex->getMessage(), 'cero')
                ? 'La seña tiene que ser mayor que cero.' : 'No se pudo registrar la seña.', 'error');
        }

        return $volver;
    }

    // -----------------------------------------------------------------
    //  Caja
    // -----------------------------------------------------------------

    public function caja(): View
    {
        $abierta = DB::selectOne("SELECT * FROM vw_caja_resumen WHERE estado = 'Abierta' ORDER BY fecha_apertura DESC LIMIT 1");

        return view('facturacion.caja', [
            'rows' => DB::select('SELECT * FROM vw_caja_resumen ORDER BY fecha_apertura DESC LIMIT 60'),
            'abierta' => $abierta,
            // Arqueo por medio de pago: sin esto no se puede cuadrar la plata
            // física contra lo cargado (el efectivo tiene que estar en el cajón;
            // la tarjeta y el cheque, no).
            'porMedio' => $abierta ? DB::select(
                'SELECT mp.nombre AS medio, mp.tipo, COUNT(*) AS cantidad, SUM(co.monto) AS total
                   FROM cobro co JOIN metodo_pago mp ON mp.id_metodo_pago = co.id_metodo_pago
                  WHERE co.id_caja = ? AND co.id_estado_cobro = 1
                  GROUP BY mp.id_metodo_pago, mp.nombre, mp.tipo
                  ORDER BY total DESC', [(int) $abierta->id_caja]
            ) : [],
        ]);
    }

    public function abrirCaja(Request $request): RedirectResponse
    {
        $volver = redirect()->route('facturacion.caja');

        if (Caja::abierta()) {
            flash('Ya hay una caja abierta. Cerrala antes de abrir otra.', 'warning');

            return $volver;
        }

        $monto = num($request->input('monto_inicial'));
        if ($monto < 0) {
            flash('El monto inicial no puede ser negativo.', 'error');

            return $volver;
        }

        try {
            $idCaja = Caja::abrir((int) session('uid'), $monto);
            Auditoria::registrar('CAJA_APERTURA', 'Facturacion', 'caja', $idCaja, 'Apertura con ' . money($monto));
            flash('Caja abierta con ' . money($monto) . '.');
        } catch (Throwable) {
            flash('No se pudo abrir la caja.', 'error');
        }

        return $volver;
    }

    public function cerrarCaja(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_caja', 0);
        $volver = redirect()->route('facturacion.caja');

        $caja = DB::selectOne('SELECT id_caja, id_usuario, id_estado_caja, fn_caja_saldo(id_caja) AS saldo
                                 FROM caja WHERE id_caja = ?', [$id]);
        if (! $caja) {
            flash('Esa caja no existe.', 'error');

            return $volver;
        }
        if ((int) $caja->id_estado_caja !== 1) {
            flash('Esa caja ya estaba cerrada.', 'warning');

            return $volver;
        }
        // La cierra quien la abrió, o el Administrador
        if ((int) $caja->id_usuario !== (int) session('uid') && ! Permisos::esAdmin()) {
            flash('Solo puede cerrar la caja quien la abrió o el Administrador.', 'error');

            return $volver;
        }

        try {
            Caja::cerrar($id);
            Auditoria::registrar('CAJA_CIERRE', 'Facturacion', 'caja', $id, 'Cierre con saldo ' . money($caja->saldo));
            flash('Caja cerrada. Saldo final: ' . money($caja->saldo) . '.');
        } catch (Throwable) {
            flash('No se pudo cerrar la caja.', 'error');
        }

        return $volver;
    }

    // -----------------------------------------------------------------
    //  Pagos al personal
    // -----------------------------------------------------------------

    public function pagos(): View
    {
        return view('facturacion.pagos', [
            'rows' => DB::select('SELECT * FROM vw_pago_personal_resumen ORDER BY fecha DESC LIMIT 200'),
            'profs' => DB::select(
                'SELECT u.id_usuario, pe_u.nombre, pe_u.apellido,
                        (SELECT COUNT(*) FROM servicio_realizado sr
                          LEFT JOIN detalle_pago_personal d ON d.id_servicio_realizado = sr.id_servicio_realizado
                          WHERE sr.id_usuario = u.id_usuario AND d.id_detalle_pago IS NULL) AS pendientes
                   FROM usuario u
                   JOIN persona pe_u ON pe_u.id_persona = u.id_persona
                   JOIN rol r ON r.id_rol = u.id_rol
                  WHERE u.activo = 1 AND r.es_personal = 1
                  ORDER BY pe_u.nombre, pe_u.apellido'
            ),
        ]);
    }

    /** Liquida los servicios realizados que todavía no se le pagaron. */
    public function pagarPersonal(Request $request): RedirectResponse
    {
        $idProf = (int) $request->input('id_usuario', 0);
        $periodo = trim((string) $request->input('periodo', '')) ?: date('m/Y');
        $volver = redirect()->route('facturacion.pagos');

        if (! $idProf) {
            flash('Elegí un profesional.', 'error');

            return $volver;
        }

        $pend = (int) DB::scalar(
            'SELECT COUNT(*) FROM servicio_realizado sr
              LEFT JOIN detalle_pago_personal d ON d.id_servicio_realizado = sr.id_servicio_realizado
             WHERE sr.id_usuario = ? AND d.id_detalle_pago IS NULL', [$idProf]
        );
        if (! $pend) {
            flash('Ese profesional no tiene servicios pendientes de liquidar.', 'warning');

            return $volver;
        }

        $caja = $this->exigeCaja('liquidarle a un profesional');
        if ($caja instanceof RedirectResponse) {
            return $caja;
        }

        try {
            $idPago = Bd::idDe('sp_registrar_pago_personal', [$idProf, (int) session('uid'), $periodo]);
            Auditoria::registrar('PAGO_PERSONAL', 'Facturacion', 'pago_personal', $idPago,
                "Liquidación $periodo ($pend servicios)");
            flash('Pago al profesional registrado.');
        } catch (Throwable $ex) {
            flash('No se pudo registrar el pago: ' . $ex->getMessage(), 'error');
        }

        return $volver;
    }

    /**
     * Revierte una liquidación: el procedimiento la marca como revertida y
     * borra el detalle, con lo cual esos servicios vuelven a quedar pendientes
     * y se pueden liquidar de nuevo.
     */
    public function revertirPagoPersonal(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_pago_personal', 0);
        $motivo = trim((string) $request->input('motivo', ''));
        $volver = redirect()->route('facturacion.pagos');

        $p = DB::selectOne(
            "SELECT p.id_pago_personal, p.id_estado_pago, p.periodo,
                    CONCAT(pe_us.nombre,' ',pe_us.apellido) AS beneficiario,
                    fn_pago_personal_monto(p.id_pago_personal) AS monto,
                    (SELECT COUNT(*) FROM detalle_pago_personal d
                      WHERE d.id_pago_personal = p.id_pago_personal) AS servicios
               FROM pago_personal p
               JOIN usuario us ON us.id_usuario = p.id_usuario
               JOIN persona pe_us ON pe_us.id_persona = us.id_persona
              WHERE p.id_pago_personal = ?", [$id]
        );

        $error = null;
        if (! $p) {
            $error = 'Ese pago no existe.';
        } elseif ((int) $p->id_estado_pago === 4) {
            $error = 'Ese pago ya estaba revertido.';
        } elseif ((int) $p->id_estado_pago === 3) {
            $error = 'Ese pago está anulado.';
        } elseif ($motivo === '') {
            $error = 'Escribí el motivo de la reversión: queda en la auditoría.';
        }
        if ($error) {
            flash($error, 'error');

            return $volver;
        }

        $caja = $this->exigeCaja('revertir una liquidación');
        if ($caja instanceof RedirectResponse) {
            return $caja;
        }

        try {
            Bd::procedimiento('sp_revertir_pago_personal', [$id, (int) session('uid')]);
            Auditoria::anotarMotivo('pago_personal', $id,
                money($p->monto) . ' a ' . $p->beneficiario . '. ' . $motivo);
            flash('Liquidación revertida. Los ' . (int) $p->servicios
                . ' servicio(s) vuelven a quedar pendientes de pago para ' . $p->beneficiario . '.');
        } catch (Throwable) {
            flash('No se pudo revertir el pago.', 'error');
        }

        return $volver;
    }

    // -----------------------------------------------------------------
    //  Pagos a proveedores
    // -----------------------------------------------------------------

    public function proveedores(): View
    {
        return view('facturacion.proveedores', [
            'cuentas' => DB::select('SELECT * FROM vw_cuenta_proveedor WHERE saldo > 0 ORDER BY vencida DESC, vencimiento'),
            // El monto no se guarda: lo calcula la función de la base
            'pagos' => DB::select(
                'SELECT pp.id_pago_proveedor, pp.fecha, pp.referencia,
                        fn_pago_proveedor_monto(pp.id_pago_proveedor) AS monto,
                        pe_pr.nombre AS proveedor, mp.nombre AS metodo, ep.nombre AS estado
                   FROM pago_proveedor pp
                   JOIN proveedor pr ON pr.id_proveedor = pp.id_proveedor
                   JOIN persona pe_pr ON pe_pr.id_persona = pr.id_persona
                   JOIN metodo_pago mp ON mp.id_metodo_pago = pp.id_metodo_pago
                   JOIN estado_pago_proveedor ep ON ep.id_estado_pago_proveedor = pp.id_estado_pago_proveedor
                  ORDER BY pp.fecha DESC LIMIT 100'
            ),
            'metodos' => DB::select('SELECT id_metodo_pago, nombre, tipo FROM metodo_pago WHERE activo = 1 ORDER BY id_metodo_pago'),
            'caja' => Caja::abierta(),
        ]);
    }

    public function pagarProveedor(Request $request): RedirectResponse
    {
        $idCompra = (int) $request->input('id_compra', 0);
        $idMetodo = (int) $request->input('id_metodo_pago', 0);
        $monto = num($request->input('monto'));
        $ref = trim((string) $request->input('referencia', '')) ?: null;
        $volver = redirect()->route('facturacion.proveedores');

        if ($monto <= 0) {
            flash('Ingresá un monto mayor a cero.', 'error');

            return $volver;
        }
        if (! $idMetodo || ! DB::scalar('SELECT COUNT(*) FROM metodo_pago WHERE id_metodo_pago = ? AND activo = 1', [$idMetodo])) {
            flash('Elegí un método de pago válido.', 'error');

            return $volver;
        }

        $caja = $this->exigeCaja('pagarle a un proveedor');
        if ($caja instanceof RedirectResponse) {
            return $caja;
        }

        // En efectivo no se puede entregar plata que no está en el cajón. Los
        // pagos por banco o tarjeta no se frenan: no salen del cajón, salen de
        // la cuenta (por eso `fn_caja_saldo` tampoco los resta).
        if (Caja::esEfectivo($idMetodo)) {
            $enCaja = Caja::saldo((int) $caja->id_caja);
            if ($monto > $enCaja + 0.01) {
                flash('En la caja hay ' . money($enCaja) . ' en efectivo y estás por pagar ' . money($monto)
                    . '. Pagá con otro medio, registrá primero el ingreso o pagá hasta ' . money($enCaja) . '.', 'error');

                return $volver;
            }
        }

        try {
            $idPago = Bd::idDe('sp_pagar_compra', [$idCompra, $idMetodo, (int) session('uid'), $monto, $ref]);
            if ($idPago) {
                // Igual que en el cobro: el procedimiento busca la caja del
                // propio usuario, y la del salón puede haberla abierto otra persona.
                DB::update('UPDATE pago_proveedor SET id_caja = ? WHERE id_pago_proveedor = ? AND id_caja IS NULL',
                    [(int) $caja->id_caja, $idPago]);
            }
            Auditoria::registrar('PAGO_PROVEEDOR', 'Facturacion', 'compra', $idCompra, 'Pago ' . money($monto));
            flash('Pago al proveedor registrado por ' . money($monto) . '.');
        } catch (Throwable $ex) {
            $msg = $ex->getMessage();
            flash(str_contains($msg, 'saldo') ? 'El monto supera el saldo pendiente de la compra.'
                : (str_contains($msg, 'confirmada') ? 'Solo se pueden pagar compras confirmadas.'
                    : 'No se pudo registrar el pago.'), 'error');
        }

        return $volver;
    }

    public function anularPagoProveedor(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_pago_proveedor', 0);
        $motivo = trim((string) $request->input('motivo', ''));
        $volver = redirect()->route('facturacion.proveedores');

        $p = DB::selectOne(
            'SELECT pp.id_pago_proveedor, pp.id_estado_pago_proveedor, pe_pr.nombre AS proveedor,
                    fn_pago_proveedor_monto(pp.id_pago_proveedor) AS monto
               FROM pago_proveedor pp
               JOIN proveedor pr ON pr.id_proveedor = pp.id_proveedor
               JOIN persona pe_pr ON pe_pr.id_persona = pr.id_persona
              WHERE pp.id_pago_proveedor = ?', [$id]
        );

        $error = null;
        if (! $p) {
            $error = 'Ese pago no existe.';
        } elseif ((int) $p->id_estado_pago_proveedor === 2) {
            $error = 'Ese pago ya estaba anulado.';
        } elseif ($motivo === '') {
            $error = 'Escribí el motivo de la anulación: queda en la auditoría.';
        }
        if ($error) {
            flash($error, 'error');

            return $volver;
        }

        $caja = $this->exigeCaja('anular un pago a proveedor');
        if ($caja instanceof RedirectResponse) {
            return $caja;
        }

        try {
            Bd::procedimiento('sp_anular_pago_proveedor', [$id, (int) session('uid')]);
            Auditoria::anotarMotivo('pago_proveedor', $id, $motivo);
            flash('Pago de ' . money($p->monto) . ' a ' . $p->proveedor
                . ' anulado. El saldo de la compra volvió a subir.');
        } catch (Throwable) {
            flash('No se pudo anular el pago.', 'error');
        }

        return $volver;
    }

    // -----------------------------------------------------------------
    //  Timbrados (Manual Técnico SIFEN v150, grupo C)
    //
    //  Timbrado 8 dígitos · establecimiento 3 · punto de expedición 3 ·
    //  correlativo 7. El número impreso queda 001-001-0000001.
    // -----------------------------------------------------------------

    public function timbrados(Request $request): View
    {
        $idEdit = (int) $request->query('editar', 0);

        return view('facturacion.timbrados', [
            'rows' => DB::select(
                'SELECT t.*, s.nombre AS sucursal, tc.nombre AS comprobante,
                        (SELECT COUNT(*) FROM factura f WHERE f.id_timbrado = t.id_timbrado) AS emitidos,
                        (SELECT COALESCE(MAX(f.nro_correlativo),0) FROM factura f WHERE f.id_timbrado = t.id_timbrado) AS ultimo,
                        (t.activo = 1 AND CURDATE() BETWEEN t.fecha_inicio AND t.fecha_fin) AS vigente
                   FROM timbrado t
                   JOIN sucursal s ON s.id_sucursal = t.id_sucursal
                   JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = t.id_tipo_comprobante
                  ORDER BY t.activo DESC, t.fecha_fin DESC'
            ),
            'sucursales' => DB::select('SELECT id_sucursal, nombre FROM sucursal WHERE activo = 1 ORDER BY nombre'),
            'tipos' => DB::select('SELECT id_tipo_comprobante, nombre FROM tipo_comprobante WHERE activo = 1 ORDER BY id_tipo_comprobante'),
            'editar' => $idEdit ? DB::selectOne('SELECT * FROM timbrado WHERE id_timbrado = ?', [$idEdit]) : null,
        ]);
    }

    public function timbradoGuardar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_timbrado', 0);
        // Solo dígitos: se rellena con ceros a la izquierda como pide la SET
        $nro = preg_replace('/\D/', '', (string) $request->input('nro_timbrado', ''));
        $est = str_pad(preg_replace('/\D/', '', (string) $request->input('establecimiento', '')) ?: '', 3, '0', STR_PAD_LEFT);
        $pun = str_pad(preg_replace('/\D/', '', (string) $request->input('punto_expedicion', '')) ?: '', 3, '0', STR_PAD_LEFT);

        $d = [
            'id_sucursal' => (int) $request->input('id_sucursal', 0),
            'id_tipo_comprobante' => (int) $request->input('id_tipo_comprobante', 0),
            'nro_timbrado' => $nro,
            'establecimiento' => $est,
            'punto_expedicion' => $pun,
            'fecha_inicio' => (string) $request->input('fecha_inicio', ''),
            'fecha_fin' => (string) $request->input('fecha_fin', ''),
            'nro_desde' => entero($request->input('nro_desde'), 1) ?: 1,
            'nro_hasta' => entero($request->input('nro_hasta'), 9999999) ?: 9999999,
        ];
        $volver = redirect()->route('facturacion.timbrados', $id ? ['editar' => $id] : []);

        $error = null;
        if (strlen($nro) !== 8) {
            $error = 'El número de timbrado debe tener exactamente 8 dígitos (ej. 12345678).';
        } elseif (strlen($est) !== 3 || strlen($pun) !== 3) {
            $error = 'El establecimiento y el punto de expedición son de 3 dígitos (ej. 001).';
        } elseif (! $d['id_sucursal'] || ! DB::scalar('SELECT COUNT(*) FROM sucursal WHERE id_sucursal = ?', [$d['id_sucursal']])) {
            $error = 'Elegí una sucursal válida.';
        } elseif (! $d['id_tipo_comprobante'] || ! DB::scalar('SELECT COUNT(*) FROM tipo_comprobante WHERE id_tipo_comprobante = ?', [$d['id_tipo_comprobante']])) {
            $error = 'Elegí un tipo de comprobante válido.';
        } elseif (! strtotime($d['fecha_inicio']) || ! strtotime($d['fecha_fin'])) {
            $error = 'Cargá las fechas de vigencia.';
        } elseif ($d['fecha_inicio'] > $d['fecha_fin']) {
            $error = 'La fecha de inicio no puede ser posterior a la de fin.';
        } elseif ($d['nro_desde'] < 1 || $d['nro_hasta'] > 9999999) {
            $error = 'La numeración va de 1 a 9999999 (7 dígitos).';
        } elseif ($d['nro_desde'] > $d['nro_hasta']) {
            $error = 'El número «desde» no puede ser mayor que el «hasta».';
        }
        if ($error) {
            flash($error, 'error');

            return $volver->withInput();
        }

        // No repetir la misma combinación timbrado + establecimiento + punto
        if (DB::scalar('SELECT COUNT(*) FROM timbrado
                         WHERE nro_timbrado = ? AND establecimiento = ? AND punto_expedicion = ? AND id_timbrado <> ?',
            [$nro, $est, $pun, $id])) {
            flash('Ya existe ese timbrado para el mismo establecimiento y punto de expedición.', 'error');

            return $volver->withInput();
        }

        try {
            if ($id) {
                // No se puede achicar el rango por debajo de lo ya emitido
                $ultimo = (int) DB::scalar('SELECT COALESCE(MAX(nro_correlativo),0) FROM factura WHERE id_timbrado = ?', [$id]);
                if ($ultimo && $d['nro_hasta'] < $ultimo) {
                    flash("Ya se emitieron comprobantes hasta el número $ultimo: el «hasta» no puede ser menor.", 'error');

                    return $volver->withInput();
                }
                DB::update(
                    'UPDATE timbrado SET id_sucursal=:id_sucursal, id_tipo_comprobante=:id_tipo_comprobante,
                        nro_timbrado=:nro_timbrado, establecimiento=:establecimiento, punto_expedicion=:punto_expedicion,
                        fecha_inicio=:fecha_inicio, fecha_fin=:fecha_fin, nro_desde=:nro_desde, nro_hasta=:nro_hasta
                      WHERE id_timbrado=:id', $d + ['id' => $id]
                );
                Auditoria::registrar('MODIFICACION', 'Facturacion', 'timbrado', $id, 'Timbrado ' . $nro);
                flash('Timbrado actualizado.');
            } else {
                DB::insert(
                    'INSERT INTO timbrado (id_sucursal,id_tipo_comprobante,nro_timbrado,establecimiento,punto_expedicion,
                        fecha_inicio,fecha_fin,nro_desde,nro_hasta,activo)
                     VALUES (:id_sucursal,:id_tipo_comprobante,:nro_timbrado,:establecimiento,:punto_expedicion,
                        :fecha_inicio,:fecha_fin,:nro_desde,:nro_hasta,1)', $d
                );
                Auditoria::registrar('ALTA', 'Facturacion', 'timbrado', (int) DB::getPdo()->lastInsertId(), 'Timbrado ' . $nro);
                flash('Timbrado cargado. Los comprobantes se numerarán ' . $est . '-' . $pun . '-0000001 en adelante.');
            }
        } catch (Throwable) {
            flash('No se pudo guardar el timbrado. Revisá que los datos cumplan el formato de la SET.', 'error');

            return $volver->withInput();
        }

        return redirect()->route('facturacion.timbrados');
    }

    public function timbradoBaja(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_timbrado', 0);
        $t = DB::selectOne('SELECT nro_timbrado, activo FROM timbrado WHERE id_timbrado = ?', [$id]);
        if (! $t) {
            flash('Ese timbrado no existe.', 'error');

            return redirect()->route('facturacion.timbrados');
        }

        DB::update('UPDATE timbrado SET activo = 1 - activo WHERE id_timbrado = ?', [$id]);
        Auditoria::registrar('MODIFICACION', 'Facturacion', 'timbrado', $id,
            ((int) $t->activo ? 'Desactivó' : 'Activó') . ' timbrado ' . $t->nro_timbrado);
        flash('Estado del timbrado actualizado.');

        return redirect()->route('facturacion.timbrados');
    }

    // -----------------------------------------------------------------

    private function opciones(string $tabla, string $clave, string $etiqueta): array
    {
        $out = [];
        foreach (DB::select("SELECT $clave AS k, $etiqueta AS v FROM $tabla ORDER BY $clave") as $r) {
            $out[(string) $r->k] = $r->v;
        }

        return $out;
    }
}
