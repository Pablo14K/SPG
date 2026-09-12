<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\ComprobanteCliente;
use App\Servicios\Acompanantes;
use App\Servicios\Auditoria;
use App\Servicios\Bd;
use App\Servicios\Caja;
use App\Servicios\Cuenta;
use App\Servicios\Facturacion;
use App\Servicios\Listado;
use App\Servicios\Permisos;
use App\Servicios\Sucursales;
use App\Servicios\Persona;
use App\Servicios\Sifen;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Tesorería.
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
                // **Las tarjetas van corridas, como en los demás módulos**
                // (pedido del usuario, 7.119.0). La 7.62.0 las había partido
                // en cuatro grupos —Facturación, Cobros, Caja, Pagos— y se
                // reportó que «no es necesario dividir sus submódulos en
                // categorías»: son ocho tarjetas, y con un título por cada
                // dos la pantalla era más rótulo que contenido. La agrupación
                // sigue viviendo en el desplegable de la barra, donde doce
                // renglones corridos sí se leían mal. El `<x-landing>`
                // conserva la maquinaria (`grupo`) para quien la necesite.
                ['p' => 'facturacion.facturas', 'ruta' => 'facturacion.facturas', 'ic' => 'receipt',
                 't' => 'Facturas', 'd' => 'Comprobantes emitidos'],
                ['p' => 'facturacion.timbrados', 'ruta' => 'facturacion.timbrados', 'ic' => 'file-earmark-text',
                 't' => 'Timbrados', 'd' => 'Numeración de los comprobantes'],

                ['p' => 'facturacion.cobros', 'ruta' => 'facturacion.cobros', 'ic' => 'cash-coin',
                 't' => 'Cobros', 'd' => 'Pagos recibidos de clientes'],

                // **Las anclas se fueron y con ellas dos tarjetas rotas.**
                // «Arqueo» apuntaba a `#arqueo` de esta misma pantalla desde
                // antes de que fuera su propia ruta (7.63.0), e «Historial de
                // caja» a `#historial`, un bloque que ya no existe. Las dos
                // llevaban a una pantalla que las ignoraba, y nada lo decía:
                // es el patrón de siempre — algo apunta al vacío y no da error.
                ['p' => 'facturacion.caja', 'ruta' => 'facturacion.cajas', 'ic' => 'safe',
                 't' => 'Cajas', 'd' => 'Los cajones del salón: abrir, ver y cerrar'],
                ['p' => 'facturacion.caja', 'ruta' => 'facturacion.arqueo', 'ic' => 'clipboard-check',
                 't' => 'Arqueos', 'd' => 'Cómo cerró cada caja y si cuadró'],
                ['p' => 'facturacion.movimientos', 'ruta' => 'facturacion.movimientos', 'ic' => 'cash-coin',
                 't' => 'Movimientos de caja', 'd' => 'Lo que entra o sale sin ser un cobro ni un pago'],

                ['p' => 'facturacion.pagos', 'ruta' => 'facturacion.pagos', 'ic' => 'wallet2',
                 't' => 'Pagos al profesional', 'd' => 'Comisiones y liquidaciones'],
                ['p' => 'facturacion.proveedores', 'ruta' => 'facturacion.proveedores', 'ic' => 'truck',
                 't' => 'Pagos a proveedores', 'd' => 'Cuentas por pagar de compras'],
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

        return redirect()->route($puedeCaja ? 'facturacion.cajas' : 'facturacion.index');
    }

    // -----------------------------------------------------------------
    //  Facturas
    // -----------------------------------------------------------------

    public function facturas(): View|StreamedResponse
    {
        // Estado y Comprobante ofrecen lo que HAY emitido en este local, no el
        // catálogo: `tipo_comprobante` tiene ocho tipos y el salón usa dos, y
        // un filtro con seis opciones que devuelven vacío no filtra nada. Van
        // acotados como la lista de abajo —por el timbrado— para que toda
        // opción tenga al menos una fila detrás.
        $parOpc = [];
        $enLocal = Sucursales::filtro('t', $parOpc);
        $opTipo = Listado::opcionesUsadas(
            "SELECT tc.nombre AS k, tc.nombre AS v
               FROM factura fa
               JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = fa.id_tipo_comprobante
               JOIN timbrado t ON t.id_timbrado = fa.id_timbrado
              WHERE 1=1 $enLocal
              GROUP BY tc.id_tipo_comprobante, tc.nombre
              ORDER BY tc.id_tipo_comprobante", $parOpc);
        $opEstado = Listado::opcionesUsadas(
            "SELECT ef.nombre AS k, ef.nombre AS v
               FROM factura fa
               JOIN estado_factura ef ON ef.id_estado_factura = fa.id_estado_factura
               JOIN timbrado t ON t.id_timbrado = fa.id_timbrado
              WHERE 1=1 $enLocal
              GROUP BY ef.id_estado_factura, ef.nombre
              ORDER BY ef.id_estado_factura", $parOpc);

        $f = Listado::filtros([
            'q' => ['tipo' => 'texto', 'etiqueta' => 'Buscar', 'ph' => 'Nº de comprobante o cliente', 'ancho' => '260px'],
            'estado' => ['tipo' => 'select', 'etiqueta' => 'Estado', 'opciones' => ['' => 'Todos'] + $opEstado],
            'tipo' => ['tipo' => 'select', 'etiqueta' => 'Comprobante', 'opciones' => ['' => 'Todos'] + $opTipo],
            'saldo' => ['tipo' => 'select', 'etiqueta' => 'Cobranza',
                        'opciones' => ['' => 'Todas', 'pend' => 'Con saldo', 'ok' => 'Saldadas']],
            'desde' => ['tipo' => 'fecha', 'etiqueta' => 'Desde'],
            'hasta' => ['tipo' => 'fecha', 'etiqueta' => 'Hasta'],
        ]);
        $f['csv'] = true;

        $w = ['1=1'];
        $par = [];
        // Los parámetros del bloque de «falta facturar» van aparte: comparten
        // consulta con nada, y mezclarlos repetiría marcadores.
        $parSf = [];
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

        // **Si esa venta ya fue acreditada** (FA-04). No se guarda: se deduce de
        // que exista una nota de crédito vigente apuntándole, que es lo que pide
        // la 3FN. Sin esto, el comprobante acreditado quedaba idéntico a
        // cualquier otro —«Emitida», saldo 0— y nada decía que la venta se
        // había devuelto: sólo se sabía entrando al comprobante.
        $acreditada = '(SELECT COUNT(*) FROM factura n
                          WHERE n.id_factura_origen = v.id_factura
                            AND n.id_estado_factura = 1) AS acreditada';

        // La sucursal de un comprobante NO es una columna suya: sale del
        // timbrado con el que se numeró, que ya era por sucursal desde
        // siempre. Por eso se une en vez de filtrar la vista directo.
        $w[] = ltrim(Sucursales::filtro('t', $par), ' AND') ?: '1=1';
        $desde = 'FROM vw_factura_resumen v
                   JOIN factura fa ON fa.id_factura = v.id_factura
                   JOIN timbrado t ON t.id_timbrado = fa.id_timbrado
                   LEFT JOIN cita ci ON ci.id_cita = fa.id_cita
                  WHERE ' . implode(' AND ', $w);

        // **De qué cita es cada comprobante, y de quién.** La lista decía sólo
        // la clienta, y con dos comprobantes de la misma clienta —o dos de la
        // misma cita, uno por amiga— no había forma de distinguirlos sin
        // abrirlos; se reportó tal cual (7.119.0). Va la fecha y hora de la
        // cita, lo que el comprobante factura —sus propios renglones, que
        // con el de UNA persona son sólo los de ella— y de quién es.
        $deLaCita = "fa.id_cita, fa.persona, ci.fecha_hora AS cita_fecha, ci.personas, ci.para_otra_persona, ci.nombre_para,
                     (SELECT GROUP_CONCAT(s.nombre ORDER BY s.nombre SEPARATOR ', ')
                        FROM detalle_factura df JOIN servicio s ON s.id_servicio = df.id_servicio
                       WHERE df.id_factura = v.id_factura) AS servicios";

        if (Listado::pideExport()) {
            return Listado::exportar('facturas',
                ['Nº', 'Fecha', 'Cliente', 'Comprobante', 'Total', 'Cobrado', 'Saldo', 'Estado'],
                array_map(fn ($r) => [$r->nro_comprobante, fecha($r->fecha_emision, 'd/m/Y H:i'), $r->cliente,
                    $r->tipo_comprobante, $r->total, $r->cobrado, $r->saldo,
                    $r->acreditada ? $r->estado . ' (acreditada)' : $r->estado],
                    DB::select("SELECT *, $acreditada $desde ORDER BY v.fecha_emision DESC", $par)),
                $f, 'Facturas'
            );
        }

        $pag = Listado::paginacion((int) DB::scalar("SELECT COUNT(*) $desde", $par));
        $rows = DB::select("SELECT v.*, $acreditada, $deLaCita $desde ORDER BY v.fecha_emision DESC LIMIT {$pag['porPagina']} OFFSET {$pag['offset']}", $par);

        // El nombre de la persona del comprobante de UNA, con la misma regla
        // que la agenda y el cobro (`Acompanantes::nombres()`).
        $conPersona = array_filter($rows, fn ($r) => (int) ($r->persona ?? 0) > 0 && $r->id_cita);
        $acomp = Acompanantes::deCitas(array_map(fn ($r) => (int) $r->id_cita, $conPersona));
        foreach ($conPersona as $r) {
            $r->de_quien = Acompanantes::nombres($r, $acomp[(int) $r->id_cita] ?? [])[(int) $r->persona] ?? ('Persona ' . (int) $r->persona);
        }

        // `vw_factura_resumen` ya trae el signo: sirve para no ofrecer «Cobrar»
        // sobre una nota de crédito, que no se cobra.
        return view('facturacion.facturas', [
            'rows' => $rows,
            // **A quién falta facturarle.** Atender y facturar son dos pasos, y
            // entre uno y otro la plata se olvida: la cita queda Atendida, la
            // clienta no siempre pide comprobante, y nadie vuelve a pasar por
            // acá. Esta lista muestra lo emitido — o sea, justo lo que NO
            // permite darse cuenta de lo que falta.
            //
            // Es del local activo, como todo lo demás, y se acota a lo reciente:
            // una cita de hace tres meses sin facturar ya no se factura, se
            // corrige.
            'sinFacturar' => DB::select(
                "SELECT c.id_cita, c.fecha_hora,
                        CONCAT(pe.nombre,' ',pe.apellido) AS cliente,
                        fn_cita_total(c.id_cita) AS total
                   FROM cita c
                   JOIN cliente cl ON cl.id_cliente = c.id_cliente
                   JOIN persona pe ON pe.id_persona = cl.id_persona
                  WHERE c.id_estado_cita = 4
                    AND c.fecha_hora >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    -- Sin comprobante de toda la cita, y con alguna persona sin
                    -- el suyo: la cita de varias a medio facturar sigue faltando.
                    AND NOT EXISTS (SELECT 1 FROM factura f
                                     WHERE f.id_cita = c.id_cita AND f.id_estado_factura = 1 AND f.persona IS NULL)
                    AND EXISTS (SELECT 1 FROM cita_servicio cs
                                 WHERE cs.id_cita = c.id_cita
                                   AND NOT EXISTS (SELECT 1 FROM factura f2 WHERE f2.id_cita = c.id_cita
                                                      AND f2.id_estado_factura = 1 AND f2.persona = cs.persona))
                    " . Sucursales::filtro('c', $parSf) . "
                  ORDER BY c.fecha_hora DESC LIMIT 20", $parSf
            ),
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
            // **Qué se pagó con puntos.** Un servicio canjeado va al comprobante
            // en CERO —se hizo, así que tiene que constar—, y un cero pelado no
            // se entiende: parece un error de la impresión. Con el canje al lado
            // se lee lo que es, que la clienta ya lo había pagado con sus puntos.
            'canjes' => DB::select(
                'SELECT s.nombre, s.precio, cj.puntos
                   FROM canje cj
                   JOIN servicio s ON s.id_servicio = cj.id_servicio
                  WHERE cj.id_cita = (SELECT id_cita FROM factura WHERE id_factura = ?)
                  ORDER BY s.nombre', [$id]
            ),
            'imp' => DB::selectOne('SELECT * FROM vw_factura_impuestos WHERE id_factura = ?', [$id]),
            // **Quién emite, igual que en el KuDE.** El local sale de la
            // factura y el timbrado queda de respaldo: desde la 7.49.0 la
            // sucursal no se deduce del timbrado, porque un local sin el
            // suyo numera con el de otra sede.
            'emisor' => DB::selectOne(
                'SELECT s.nombre AS sucursal, s.ruc, s.telefono, s.direccion, s.ciudad,
                        t.nro_timbrado, t.fecha_inicio AS timbrado_desde, t.fecha_fin AS timbrado_hasta,
                        cf.nombre_salon, cf.actividad_desc
                   FROM factura fa
                   JOIN timbrado t ON t.id_timbrado = fa.id_timbrado
                   JOIN sucursal s ON s.id_sucursal = COALESCE(fa.id_sucursal, t.id_sucursal)
                   LEFT JOIN configuracion cf ON cf.id_configuracion = 1
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
                        -- **Seña es lo que se cobró ANTES de atender.** Con
                        -- `id_factura IS NULL` a secas, el cobro de la atención
                        -- —que desde la 7.19.0 va contra la cita y no contra el
                        -- comprobante— salía rotulado «seña» en la factura, y no
                        -- lo es: es el pago del trabajo hecho. El procedimiento
                        -- deja la observación en «Sena de reserva» y el
                        -- controlador la pisa con «Cobro de la atencion», así
                        -- que el dato ya estaba: faltaba mirarlo.
                        (co.id_factura IS NULL
                         AND COALESCE(co.observaciones,\'\') NOT LIKE \'Cobro de la atencion%\') AS es_sena,
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
            // **Las cajas del local DE LA FACTURA, no del local donde estoy
            // parado.** `vw_factura_resumen` no trae `id_sucursal`, así que
            // `$f->id_sucursal` era siempre null: `Caja::abiertasDe(0)` cae en
            // `Sucursales::activa()` y el combo ofrecía los cajones de otra
            // sede — que es justo lo que el POST después rechaza. La pantalla
            // no puede ofrecer algo que el servidor va a negar.
            'cajasNota' => Caja::abiertasDe($this->sucursalDeFactura($id)),
            // Cuánto sale del cajón si se acredita todo. Con esto el modal
            // puede pedir la caja **sólo cuando hay efectivo que devolver** y
            // decir de cuánto se trata, en vez de un selector suelto.
            'efectivoNota' => $this->efectivoDevolvible($id),
            // Facturación electrónica: si el salón no la usa, no se dibuja nada.
            'sifen' => Sifen::activo(),
            'sifenEstado' => Sifen::activo() ? Sifen::estado($id) : null,
            // El tipo se pide aparte: `vw_factura_resumen` trae el NOMBRE del
            // comprobante, no su id, y acá hace falta el id para saber si se
            // declara ante la DNIT.
            'sifenAplica' => Sifen::activo() && Sifen::esElectronico(
                (int) DB::scalar('SELECT id_tipo_comprobante FROM factura WHERE id_factura = ?', [$id])
            ),
        ]);
    }

    /**
     * Declara el comprobante ante la DNIT, a través del Automatizador SIFEN.
     *
     * Va aparte de emitir a propósito: la factura ya existe y es válida, así
     * que un servicio caído no puede impedir que el salón cobre. Se reintenta
     * apretando de nuevo.
     */
    public function sifenEnviar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_factura', 0);
        $volver = redirect()->route('facturacion.factura_ver', ['id' => $id]);

        if (! Sifen::activo()) {
            flash('La facturación electrónica está apagada.', 'error');

            return $volver;
        }

        $r = Sifen::enviar($id);
        flash($r['mensaje'], $r['ok'] ? 'success' : 'error');

        return $volver;
    }

    /**
     * El KuDE, el XML o el TXT de un comprobante declarado, servidos desde acá.
     *
     * **No se redirige al Automatizador.** Las URL que él devuelve apuntan a su
     * dominio publicado —que hoy no responde: el botón mandaba a una página
     * caída— y además no llevan el token. La copia se baja al declarar y se
     * guarda en `storage/app/sifen/<factura>/`, así que el comprobante se
     * puede ver aunque el servicio esté apagado o haya cambiado de dirección.
     */
    public function sifenArchivo(Request $request): Response|RedirectResponse
    {
        $id = (int) $request->query('id', 0);
        $que = (string) $request->query('t', 'pdf');
        $volver = redirect()->route('facturacion.factura_ver', ['id' => $id]);

        if (! Sifen::activo() || ! in_array($que, ['pdf', 'xml', 'txt'], true)) {
            flash('Ese archivo no está disponible.', 'error');

            return $volver;
        }

        $ruta = Sifen::copia($id, $que);

        // Si la copia no está —el comprobante se declaró antes de que esto
        // existiera, o el servicio estaba caído al bajarla— se intenta una vez
        // más antes de darla por perdida.
        if (! $ruta && $que !== 'txt') {
            Sifen::bajarCopias($id);
            $ruta = Sifen::copia($id, $que);
        }

        if (! $ruta) {
            flash('Todavía no tenemos la copia de ese archivo. Se baja del Automatizador al declarar, '
                . 'así que probá cuando el servicio esté disponible.', 'warning');

            return $volver;
        }

        $nro = str_replace('/', '-', (string) Facturacion::numero($id));
        $tipo = ['pdf' => 'application/pdf', 'xml' => 'application/xml', 'txt' => 'text/plain'][$que];

        // El PDF se muestra en el navegador; el XML y el TXT se bajan, que es
        // lo que se hace con ellos.
        return response()->file($ruta, [
            'Content-Type' => $tipo,
            'Content-Disposition' => ($que === 'pdf' ? 'inline' : 'attachment')
                . '; filename="' . $nro . '.' . $que . '"',
        ]);
    }

    /**
     * Le manda el comprobante por correo a la clienta.
     *
     * Sirve para cualquiera, pero sobre todo para el **Comprobante de pago**,
     * que es el de todos los días y **no se declara ante la DNIT**: al no
     * pasar por el Automatizador, nadie le manda nada — antes la única forma
     * de que se lo llevara era imprimirlo.
     *
     * El correo viene precargado con el de su ficha y **se puede cambiar**:
     * puede pedir que se lo manden a otra dirección. Lo que se escriba acá
     * **no le toca la ficha**, porque es para este envío y no un dato nuevo
     * de la persona.
     */
    public function comprobanteEnviar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_factura', 0);
        $email = trim((string) $request->input('email', ''));
        $nota = trim((string) $request->input('nota', ''));
        $volver = redirect()->route('facturacion.factura_ver', ['id' => $id]);

        $nro = DB::scalar('SELECT fn_factura_nro(?)', [$id]);
        if (! $nro) {
            flash('Ese comprobante no existe.', 'error');

            return redirect()->route('facturacion.facturas');
        }
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Poné una dirección de correo válida.', 'error');

            return $volver;
        }

        if (! $this->mandarComprobante($id, $email, $nota)) {
            flash('No se pudo mandar el correo. El detalle quedó en el registro del sistema.', 'error');

            return $volver;
        }

        flash('Le mandamos el comprobante ' . $nro . ' a ' . $email . '.');

        return $volver;
    }

    /**
     * Manda el comprobante por correo. Devuelve si salió.
     *
     * Vive aparte porque lo usan **dos caminos**: el botón «Enviar por correo»
     * y el envío automático al emitir. Escrito dos veces, uno de los dos se
     * queda atrás y le llega a la clienta un correo distinto según por dónde
     * se haya emitido.
     */
    private function mandarComprobante(int $id, string $email, string $nota = ''): bool
    {
        $f = DB::selectOne(
            'SELECT v.*, fa.id_tipo_comprobante
               FROM vw_factura_resumen v JOIN factura fa ON fa.id_factura = v.id_factura
              WHERE v.id_factura = ?', [$id]
        );
        if (! $f) {
            return false;
        }

        try {
            Mail::to($email)->send(new ComprobanteCliente(
                $f,
                DB::select('SELECT * FROM vw_detalle_factura WHERE id_factura = ? ORDER BY clase, item', [$id]),
                DB::select(
                    'SELECT co.fecha, co.monto, mp.nombre AS metodo
                       FROM cobro co JOIN metodo_pago mp ON mp.id_metodo_pago = co.id_metodo_pago
                      WHERE (co.id_factura = :f OR co.id_cita = (SELECT id_cita FROM factura WHERE id_factura = :f2))
                        AND co.id_estado_cobro = 1
                      ORDER BY co.fecha', ['f' => $id, 'f2' => $id]
                ),
                (string) (DB::scalar('SELECT nombre FROM sucursal WHERE activo = 1 ORDER BY id_sucursal LIMIT 1')
                          ?: config('app.name')),
                $nota
            ));
        } catch (Throwable $ex) {
            Log::error('Comprobante ' . $id . ' por correo a ' . $email . ': ' . $ex->getMessage());

            return false;
        }

        Auditoria::registrar('ENVIO', 'Facturacion', 'factura', $id,
            'Comprobante ' . $f->nro_comprobante . ' enviado a ' . $email);

        return true;
    }

    /** Citas atendidas que todavía no tienen factura. */
    public function emitir(Request $request): View
    {
        $suc = Sucursales::activa();

        // Los comprobantes de venta que hoy se pueden emitir. **`fn_timbrado_vigente`
        // cae al timbrado de otra sede** cuando este local no tiene el suyo, así
        // que esta lista NO es «los que tienen timbrado acá»: es «los que se
        // pueden emitir», y con la caída puesta incluye los que van a salir con
        // el número de otra sucursal. De eso avisa `timbradoDeOtroLocal()`.
        $tipos = DB::select(
            'SELECT tc.id_tipo_comprobante, tc.nombre
               FROM tipo_comprobante tc
              WHERE tc.activo = 1 AND tc.signo = 1 AND tc.requiere_origen = 0
                AND fn_timbrado_vigente(tc.id_tipo_comprobante, CURDATE(), ?) IS NOT NULL
              ORDER BY tc.id_tipo_comprobante', [$suc]
        );

        // **Las citas de varias que ya tienen ALGÚN comprobante siguen acá**
        // hasta que lo tenga cada una (7.119.0). La lista excluía toda cita
        // con una factura activa, así que después del comprobante de la
        // primera amiga la cita desaparecía y a la segunda no había forma de
        // hacerle el suyo — reportado como que «no se genera el comprobante
        // de ese pago al registrarlo». Sigue excluida la que tiene el
        // comprobante de TODA la cita, que ya cubre a todas.
        $citas = DB::select(
                "SELECT c.id_cita, c.id_cliente, c.fecha_hora, c.personas, c.para_otra_persona, c.nombre_para,
                        CONCAT(pe_cl.nombre,' ',pe_cl.apellido) AS cliente,
                        (SELECT GROUP_CONCAT(s.nombre SEPARATOR ', ')
                           FROM cita_servicio cs JOIN servicio s ON s.id_servicio = cs.id_servicio
                          WHERE cs.id_cita = c.id_cita) AS servicios,
                        (SELECT COALESCE(SUM(s.precio),0)
                           FROM cita_servicio cs JOIN servicio s ON s.id_servicio = cs.id_servicio
                          WHERE cs.id_cita = c.id_cita) AS total,
                        -- **El descuento que va a aplicar, antes de emitir.** La
                        -- pantalla mostraba sólo la suma de los servicios, así
                        -- que el total impreso salía más bajo que el anunciado y
                        -- había que explicárselo a la clienta con el comprobante
                        -- ya emitido. Sale del descuento del NIVEL, que es el que
                        -- la base aplica sola; una promoción puede mejorarlo, y
                        -- por eso el aviso dice «al menos».
                        fn_cliente_descuento(c.id_cliente) AS id_descuento,
                        -- El monto lo calcula la base con su propia función, que es
                        -- la que sabe si el descuento es porcentaje o monto fijo y
                        -- si está vigente. Acá no se reimplementa la regla.
                        fn_descuento_monto(fn_cliente_descuento(c.id_cliente),
                                           (SELECT COALESCE(SUM(s3.precio),0)
                                              FROM cita_servicio cs3
                                              JOIN servicio s3 ON s3.id_servicio = cs3.id_servicio
                                             WHERE cs3.id_cita = c.id_cita)) AS desc_monto,
                        -- Lo que ya está pago con puntos: en el comprobante va a
                        -- cero, así que no forma parte de lo que se va a cobrar.
                        (SELECT COALESCE(SUM(s2.precio),0)
                           FROM canje cj JOIN servicio s2 ON s2.id_servicio = cj.id_servicio
                          WHERE cj.id_cita = c.id_cita) AS canjeado
                   FROM cita c
                   JOIN cliente cl    ON cl.id_cliente = c.id_cliente
                   JOIN persona pe_cl ON pe_cl.id_persona = cl.id_persona
                  WHERE c.id_estado_cita = 4
                    AND NOT EXISTS (SELECT 1 FROM factura f WHERE f.id_cita = c.id_cita
                                       AND f.id_estado_factura = 1 AND f.persona IS NULL)
                    AND EXISTS (SELECT 1 FROM cita_servicio cs
                                 WHERE cs.id_cita = c.id_cita
                                   AND NOT EXISTS (SELECT 1 FROM factura f2 WHERE f2.id_cita = c.id_cita
                                                      AND f2.id_estado_factura = 1 AND f2.persona = cs.persona))
                  ORDER BY (c.id_cita = :sel) DESC, c.fecha_hora DESC LIMIT 100",
                ['sel' => (int) $request->query('cita', 0)]
        );

        // La cuenta de cada persona en las citas de varias: nombres, qué se
        // hizo cada una, cuánto le toca y si ya tiene su comprobante. Es lo
        // que deja elegir «¿de quién?» al emitir.
        $cuentas = [];
        foreach ($citas as $c) {
            if ((int) $c->personas > 1) {
                $cuentas[(int) $c->id_cita] = Acompanantes::cuenta($c, Acompanantes::deCitas([(int) $c->id_cita])[(int) $c->id_cita] ?? []);
            }
        }

        return view('facturacion.emitir', [
            // Se llega acá desde la agenda con la cita ya elegida: la persona
            // termina de atender y cobra sin tener que buscar a la clienta en
            // una lista de cien. Se valida al guardar igual, así que un id
            // inventado en la URL no emite nada.
            'sel_cita' => (int) $request->query('cita', 0),
            // Y con la persona ya elegida cuando se cobró por persona: el
            // comprobante que sigue es el de ELLA.
            'sel_persona' => (int) $request->query('persona', 0),
            'citas' => $citas,
            'cuentas' => $cuentas,
            'tipos' => $tipos,
            'condiciones' => DB::select(
                'SELECT id_condicion_venta, nombre, dias_credito FROM condicion_venta WHERE activo = 1
                  ORDER BY id_condicion_venta'
            ),
            // El que viene marcado: Ticket, porque la mayoría no pide factura.
            'tipoDefecto' => (int) config('sifen.tipo_por_defecto', 3),
            // Su nombre, para poder decir «el Ticket» y no «el comprobante por
            // defecto», que no le dice nada a quien atiende.
            'tipoDefectoNombre' => (string) (DB::scalar(
                'SELECT nombre FROM tipo_comprobante WHERE id_tipo_comprobante = ?',
                [(int) config('sifen.tipo_por_defecto', 3)]
            ) ?: 'Ticket'),
            'sifen' => Sifen::activo(),

            // **Si este local no tiene timbrado propio, hay que decirlo.**
            //
            // `fn_timbrado_vigente` cae al timbrado de otra sede cuando el local
            // no tiene el suyo, y esa caída es deliberada: dejar de facturar
            // sería peor. Pero arrastra dos cosas que no se ven: el
            // establecimiento impreso —los tres dígitos con los que la DNIT sabe
            // de qué local salió el comprobante— dice la sede ajena, y desde la
            // 7.36.3 el cobro deduce su sucursal del timbrado, así que **la
            // plata entra al cajón del otro local**.
            //
            // El sistema hace lo que dice que hace; lo que faltaba era que se
            // notara. Una caída en silencio es indistinguible de un error.
            'timbradoAjeno' => $this->timbradoDeOtroLocal($tipos, $suc),
        ]);
    }

    /**
     * ¿Con qué local va a numerar este, si no es con el suyo?
     *
     * Devuelve el nombre de la sucursal cuyo timbrado se va a usar, o null si
     * el local tiene el propio para todo lo que la pantalla ofrece — que es el
     * caso normal y no necesita ningún cartel.
     *
     * **Se pregunta por cada tipo que se ofrece, no sólo por el que viene
     * marcado.** Un local puede tener timbrado de Factura y no de Recibo, y la
     * caída es por tipo: avisar sólo del primero dejaría al otro saliendo con
     * el número ajeno en silencio, que es exactamente lo que se quiere evitar.
     */
    private function timbradoDeOtroLocal(array $tipos, ?int $suc): ?string
    {
        if (! $suc || ! $tipos) {
            return null;
        }

        foreach ($tipos as $t) {
            $duenio = (int) DB::scalar(
                'SELECT id_sucursal FROM timbrado
                  WHERE id_timbrado = fn_timbrado_vigente(?, CURDATE(), ?)',
                [(int) $t->id_tipo_comprobante, $suc]
            );
            if ($duenio && $duenio !== $suc) {
                return (string) DB::scalar('SELECT nombre FROM sucursal WHERE id_sucursal = ?', [$duenio]);
            }
        }

        return null;
    }

    public function emitirGuardar(Request $request): RedirectResponse
    {
        $idCita = (int) $request->input('id_cita', 0);

        // **La factura SIN NOMBRE es la misma factura, sin datos del receptor.**
        // La DNIT la admite por debajo del tope, y es el caso de todos los días:
        // la clienta que no da su RUC. El combo ofrece las dos como opciones
        // distintas porque para quien cobra son dos cosas distintas —una pide
        // los datos y la otra no— aunque el tipo de comprobante sea el mismo.
        //
        // La marca viaja pegada al id (`1-inn`) y no en un campo aparte: un
        // `select` más un `checkbox` que hay que combinar bien es una forma de
        // equivocarse. `(int)` se queda con el número y descarta el sufijo.
        $bruto = (string) $request->input('id_tipo_comprobante', '1');
        $innominada = str_ends_with($bruto, '-inn');
        $idTipo = (int) $bruto ?: 1;
        $idCond = (int) $request->input('id_condicion_venta', 1) ?: 1;
        // **¿De quién es el comprobante?** 0 es de toda la cita; N, de esa
        // persona del grupo — sale con SUS servicios y descuenta SUS cobros.
        $persona = max(0, (int) $request->input('persona', 0));

        $cita = $this->citaFacturable($idCita, $idTipo, $persona);
        if ($cita instanceof RedirectResponse) {
            return $cita;
        }

        // **Sin nombre sólo por debajo del tope.** Es el rechazo 1321 de la
        // DNIT: a partir de Gs. 60.000.000 el comprobante tiene que decir a
        // quién se le vendió. Se avisa acá y no después de gastar el número.
        if ($innominada) {
            $total = $persona > 0 ? $this->totalDe($idCita, $persona) : (float) DB::scalar('SELECT fn_cita_total(?)', [$idCita]);
            if ($total >= Sifen::TOPE_INNOMINADO) {
                flash('Esta cita suma ' . money($total) . ' y desde ' . money(Sifen::TOPE_INNOMINADO)
                    . ' la factura tiene que llevar los datos de la clienta. '
                    . 'Elegí «Factura declarada».', 'error');

                return redirect()->route('facturacion.emitir', ['cita' => $idCita] + ($persona > 0 ? ['persona' => $persona] : []));
            }
        }

        // Un comprobante que se declara ante la DNIT necesita los datos del
        // receptor ANTES de emitirse: un rechazo por un RUC mal tipeado no se
        // reintenta —el número ya se gastó y hay que anular y rehacer—, así
        // que todo lo que se pueda comprobar sin salir del salón se comprueba
        // antes. El Ticket no pasa por acá: es interno y no se declara.
        //
        // **La innominada TAMBIÉN pasa por acá, y ése era el defecto.**
        //
        // Iba derecho al `try` de abajo con el argumento de que no lleva datos
        // del receptor, y eso se llevaba puestas las otras dos cosas que hace
        // este camino: **declararla ante la DNIT y mandársela a la clienta**.
        // El resultado es el que se reportó — la factura salía sin CDC, así
        // que no había KuDE ni XML que adjuntar y lo único que llegaba era el
        // resumen del cuerpo del correo.
        //
        // Y contradecía la regla escrita: la innominada **se declara**; lo que
        // cambia es qué datos lleva, no si se informa. Lo único que de verdad
        // no hay que pedirle es el documento y el nombre, así que la pantalla
        // del receptor entra en modo reducido y pregunta **sólo el correo**.
        if (Sifen::activo() && Sifen::esElectronico($idTipo)) {
            return redirect()->route('facturacion.receptor', array_filter([
                'cita' => $idCita, 'tipo' => $idTipo, 'condicion' => $idCond,
                'inn' => $innominada ? 1 : null,
                'persona' => $persona ?: null,
            ]));
        }

        try {
            $idf = Facturacion::emitir((int) $cita->id_cliente, $idCita, (int) session('uid'), $idTipo, $idCond, $persona ?: null);
            $nro = Facturacion::numero($idf);
            Auditoria::registrar('EMISION', 'Facturacion', 'factura', $idf,
                'Comprobante ' . $nro . ' de la cita #' . $idCita . ($persona > 0 ? ' — de la persona ' . $persona : ''));

            $puntos = Facturacion::acumularPuntos($idf, (int) $cita->id_cliente);
            $saldo = Facturacion::saldo($idf);
            flash('Comprobante ' . $nro . ' emitido.'
                . ($puntos ? ' El cliente sumó ' . $puntos . ' punto(s) de fidelización.' : '')
                . ($saldo > 0.01 ? ' Queda cobrar ' . money($saldo) . ': está abajo, con el botón Cobrar.' : ''));

            // **Se termina donde se cobra, no en la lista entera.**
            //
            // El botón de la agenda dice «Cobrar» y llevaba a Emitir; emitir
            // soltaba en la lista de facturas, y ahí había que buscar la
            // factura recién hecha entre todas para recién entonces cobrarla.
            // Desde afuera se leía como que el sistema pedía cobrar dos veces.
            // Ahora la lista vuelve filtrada por ESE comprobante, que es la
            // pantalla donde está el modal de cobro.
            if ($saldo > 0.01) {
                return redirect()->route('facturacion.facturas', ['q' => $nro]);
            }
        } catch (Throwable $ex) {
            $msg = $ex->getMessage();
            flash(str_contains($msg, 'timbrado') ? 'No hay timbrado vigente para la factura.'
                : (str_contains($msg, 'agotado') ? 'Se agotó el rango de numeración del timbrado. Cargá uno nuevo.'
                    : 'No se pudo emitir la factura. El detalle quedó en el registro del sistema: '
                        . 'mostrale este mensaje a quien lo mantiene.'), 'error');

            return redirect()->route('facturacion.emitir');
        }

        return redirect()->route('facturacion.facturas');
    }

    /**
     * ¿Se puede facturar esta cita con este comprobante?
     *
     * Devuelve la cita, o el redirect con el motivo. Está aparte porque lo
     * preguntan DOS entradas —el botón de la lista y el formulario del
     * receptor—, y la segunda no puede confiar en que la primera ya validó:
     * entre una y otra pasa una pantalla, y en el medio alguien pudo facturar
     * esa misma cita desde otra computadora.
     */
    private function citaFacturable(int $idCita, int $idTipo, int $persona = 0): stdClass|RedirectResponse
    {
        // El cliente se toma de la cita, no del formulario: así nadie puede
        // facturarle a un tercero manipulando el campo oculto.
        $cita = DB::selectOne('SELECT id_cliente, id_estado_cita, personas FROM cita WHERE id_cita = ?', [$idCita]);
        if (! $cita) {
            flash('Esa cita no existe.', 'error');

            return redirect()->route('facturacion.emitir');
        }
        if ((int) $cita->id_estado_cita !== 4) {
            flash('Solo se factura una cita ya atendida.', 'error');

            return redirect()->route('facturacion.emitir');
        }
        // **Un comprobante de toda la cita cierra la cita; los de UNA persona
        // cierran sólo la suya** (7.119.0). Con la cita de varias cada una
        // puede llevarse el suyo, así que «ya tiene factura» son dos
        // preguntas: si hay uno de todas, no se emite más nada; si hay de
        // algunas, se emite el de las que faltan — y ya no el de todas, que
        // volvería a cobrar lo de las que ya se fueron con el suyo.
        if (DB::scalar('SELECT COUNT(*) FROM factura WHERE id_cita = ? AND id_estado_factura = 1 AND persona IS NULL', [$idCita])) {
            flash('Esa cita ya tiene una factura emitida.', 'warning');

            return redirect()->route('facturacion.facturas');
        }
        $facturadas = array_map('intval', array_column(DB::select(
            'SELECT persona FROM factura WHERE id_cita = ? AND id_estado_factura = 1 AND persona IS NOT NULL', [$idCita]
        ), 'persona'));
        if ($persona === 0 && $facturadas) {
            flash('Alguna de las personas de esta cita ya tiene su comprobante: emití el de cada una de las que faltan, eligiendo «¿de quién?».', 'warning');

            return redirect()->route('facturacion.emitir', ['cita' => $idCita]);
        }
        if ($persona > 0) {
            if ($persona > max(1, (int) $cita->personas)) {
                flash('Esa cita es de ' . max(1, (int) $cita->personas) . ' persona(s): no hay a quién facturarle eso.', 'error');

                return redirect()->route('facturacion.emitir', ['cita' => $idCita]);
            }
            if (in_array($persona, $facturadas, true)) {
                flash('Esa persona ya tiene su comprobante: cobralo desde Facturas.', 'warning');

                return redirect()->route('facturacion.facturas');
            }
            if (! DB::scalar('SELECT COUNT(*) FROM cita_servicio WHERE id_cita = ? AND persona = ?', [$idCita, $persona])) {
                flash('Esa persona no tiene servicios en la cita: no hay nada que facturarle.', 'error');

                return redirect()->route('facturacion.emitir', ['cita' => $idCita]);
            }
        }
        if (! DB::scalar('SELECT COUNT(*) FROM cita_servicio WHERE id_cita = ?', [$idCita])) {
            flash('La cita no tiene servicios cargados, no hay nada que facturar.', 'error');

            return redirect()->route('facturacion.emitir');
        }
        if (! Facturacion::hayTimbrado($idTipo)) {
            flash('No hay un timbrado vigente para ese comprobante. Cargalo en Facturación → Timbrados.', 'error');

            return redirect()->route('facturacion.timbrados');
        }

        return $cita;
    }

    /**
     * Lo que le toca a UNA persona de la cita, con el descuento: su parte
     * proporcional del total —la misma cuenta que muestra la agenda—.
     */
    private function totalDe(int $idCita, int $persona): float
    {
        $cita = DB::selectOne('SELECT * FROM cita WHERE id_cita = ?', [$idCita]);
        $cuenta = $cita ? Acompanantes::cuenta($cita, Acompanantes::deCitas([$idCita])[$idCita] ?? []) : [];

        return (float) ($cuenta[$persona]['total'] ?? 0);
    }

    // -----------------------------------------------------------------
    //  Los datos del receptor, para el comprobante electrónico
    // -----------------------------------------------------------------

    /**
     * El formulario con los datos que la DNIT exige del receptor.
     *
     * Viene precargado desde la ficha del cliente y **todo se puede cambiar**:
     * la clienta puede pedir la factura a nombre de su empresa, o dar otro
     * correo para que le llegue el PDF. Lo que se corrija se guarda en su
     * ficha, que es donde viven los datos de las personas.
     */
    public function receptor(Request $request): View|RedirectResponse
    {
        $idCita = (int) $request->query('cita', 0);
        $idTipo = (int) $request->query('tipo', 1) ?: 1;
        $idCond = (int) $request->query('condicion', 1) ?: 1;
        $persona = max(0, (int) $request->query('persona', 0));

        $cita = $this->citaFacturable($idCita, $idTipo, $persona);
        if ($cita instanceof RedirectResponse) {
            return $cita;
        }

        // **Modo reducido: la factura sin nombre.** Va sin datos del receptor
        // —eso es lo que la hace innominada— así que lo único que queda por
        // preguntar es a dónde mandarla, «para que el cliente lo tenga
        // también». Es la MISMA pantalla y no una segunda: dos formularios
        // iguales se desfasan, que es un error que este proyecto ya se hizo.
        return view('facturacion.receptor', $this->datosReceptor(
            $idCita, $idTipo, $idCond, (int) $cita->id_cliente, $persona
        ) + ['inn' => (bool) $request->query('inn'), 'persona' => $persona]);
    }

    /**
     * Valida, emite y declara — en ese orden, que es el que importa.
     *
     * **La factura se emite ANTES de mandarla**, y si el envío falla no se
     * deshace: el comprobante ya es válido con su timbrado y su número, y
     * declararlo es un paso posterior que se puede repetir desde la pantalla
     * del comprobante. Si emitir dependiera de que el Automatizador conteste,
     * un corte de internet dejaría al salón sin poder cobrar.
     */
    public function receptorGuardar(Request $request): RedirectResponse
    {
        $idCita = (int) $request->input('id_cita', 0);
        $idTipo = (int) $request->input('id_tipo_comprobante', 1) ?: 1;
        $idCond = (int) $request->input('id_condicion_venta', 1) ?: 1;
        $persona = max(0, (int) $request->input('persona', 0));

        $cita = $this->citaFacturable($idCita, $idTipo, $persona);
        if ($cita instanceof RedirectResponse) {
            return $cita;
        }

        $innominada = (bool) $request->input('inn');

        $volver = redirect()->route('facturacion.receptor', array_filter([
            'cita' => $idCita, 'tipo' => $idTipo, 'condicion' => $idCond,
            'inn' => $innominada ? 1 : null,
            'persona' => $persona ?: null,
        ]));

        // **Sin nombre: el receptor va vacío a propósito.** No se lee del
        // formulario aunque llegue —la pantalla no lo dibuja, pero un POST
        // armado a mano sí podría mandarlo— porque lo que distingue a esta
        // factura de la declarada es justamente que no lleva esos datos.
        $rec = $innominada
            ? ['tipo_doc' => 'CF', 'documento' => '', 'nombre' => '',
               'email' => trim((string) $request->input('email', '')),
               'direccion' => '', 'telefono' => '']
            : [
                'tipo_doc' => strtoupper(trim((string) $request->input('tipo_doc', 'CF'))),
                'documento' => trim((string) $request->input('documento', '')),
                'nombre' => trim((string) $request->input('nombre', '')),
                'email' => trim((string) $request->input('email', '')),
                'direccion' => trim((string) $request->input('direccion', '')),
                'telefono' => trim((string) $request->input('telefono', '')),
            ];

        // El total se recalcula acá: es el que decide si se puede emitir a
        // consumidor final, y no puede salir de un campo del formulario. El
        // de UNA persona es el de sus servicios.
        $total = (float) DB::scalar(
            'SELECT COALESCE(SUM(s.precio),0) FROM cita_servicio cs
               JOIN servicio s ON s.id_servicio = cs.id_servicio
              WHERE cs.id_cita = ? AND (? = 0 OR cs.persona = ?)', [$idCita, $persona, $persona]
        );

        if ($error = Sifen::validarReceptor($rec, $total)) {
            flash($error, 'error');

            return $volver->withInput();
        }

        // Los datos corregidos van a `persona`, que es el único lugar donde
        // viven los datos de las personas. Así la próxima factura de esta
        // clienta ya sale bien y no hay que volver a tipear nada.
        $per = DB::selectOne(
            'SELECT pe.id_persona, pe.nombre, pe.apellido FROM cliente c
               JOIN persona pe ON pe.id_persona = c.id_persona WHERE c.id_cliente = ?', [$cita->id_cliente]
        );
        // **En la sin nombre la ficha no se toca.** El correo que se escribe
        // ahí es para ESE envío —«para que el cliente lo tenga también»— y no
        // un dato nuevo de la persona: es el mismo criterio que el botón de
        // «Enviar por correo» del comprobante.
        if ($per && ! $innominada) {
            $aGuardar = ['email' => $rec['email'], 'direccion' => $rec['direccion'], 'telefono' => $rec['telefono']];
            if ($rec['tipo_doc'] === 'RUC') {
                $aGuardar['ruc'] = $rec['documento'];
            } elseif ($rec['tipo_doc'] === 'CI') {
                $aGuardar['cedula'] = $rec['documento'];
            }
            // El nombre sólo se pisa si de verdad cambió: en la ficha va
            // partido en nombre y apellido, y el formulario lo muestra junto.
            //
            // Con RUC NO se parte. Una razón social no tiene apellido, y
            // cortarla por el primer espacio dejaba «Comercial Cliente SA»
            // como nombre «Comercial» y apellido «Cliente SA», que es lo que
            // después sale impreso en el comprobante.
            if ($rec['tipo_doc'] !== 'CF'
                && $rec['nombre'] !== trim($per->nombre . ' ' . $per->apellido)) {
                if ($rec['tipo_doc'] === 'RUC') {
                    $aGuardar['nombre'] = $rec['nombre'];
                    $aGuardar['apellido'] = '';
                } else {
                    $partes = preg_split('/\s+/', $rec['nombre'], 2);
                    $aGuardar['nombre'] = $partes[0];
                    $aGuardar['apellido'] = $partes[1] ?? '';
                }
            }

            if ($errPersona = Persona::error(array_merge((array) $per, $aGuardar))) {
                flash($errPersona, 'error');

                return $volver->withInput();
            }
            Persona::guardar((int) $per->id_persona, $aGuardar);
        }

        // ---- 1. Emitir. Desde acá el comprobante ya es válido. ----
        try {
            $idf = Facturacion::emitir((int) $cita->id_cliente, $idCita, (int) session('uid'), $idTipo, $idCond, $persona ?: null);
            $nro = Facturacion::numero($idf);
            Auditoria::registrar('EMISION', 'Facturacion', 'factura', $idf,
                'Comprobante ' . $nro . ' de la cita #' . $idCita . ($persona > 0 ? ' — de la persona ' . $persona : ''));
            $puntos = Facturacion::acumularPuntos($idf, (int) $cita->id_cliente);
        } catch (Throwable $ex) {
            $msg = $ex->getMessage();
            Log::error('Emisión de la cita ' . $idCita . ': ' . $msg);
            flash(str_contains($msg, 'timbrado') ? 'No hay timbrado vigente para la factura.'
                : (str_contains($msg, 'agotado') ? 'Se agotó el rango de numeración del timbrado. Cargá uno nuevo.'
                    : 'No se pudo emitir la factura. El detalle quedó en el registro del sistema.'), 'error');

            return $volver->withInput();
        }

        // ---- 2. Declarar. Si esto falla, la factura sigue emitida. ----
        $r = Sifen::enviar($idf, $rec);

        // ---- 3. Y se le manda a la clienta, sin apretar nada más. ----
        //
        // **Emitir y que le llegue son un solo acto para quien atiende.** El
        // botón «Enviar por correo» seguía estando, pero había que acordarse
        // de apretarlo: la clienta se iba del salón creyendo que ya lo tenía y
        // el comprobante quedaba en el sistema.
        //
        // Va DESPUÉS de emitir y no atado a eso, la regla de siempre: si el
        // correo falla, la factura sigue siendo válida y se reintenta desde el
        // comprobante. Por eso el aviso dice si salió y a dónde.
        $correo = trim((string) ($rec['email'] ?? ''));
        $mandado = $correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL)
            && $this->mandarComprobante($idf, $correo);

        flash('Factura ' . $nro . ' emitida.'
            . ($puntos ? ' El cliente sumó ' . $puntos . ' punto(s).' : '')
            . ' ' . $r['mensaje']
            . ($r['ok'] ? '' : ' La factura es válida igual: podés reintentar el envío desde el comprobante.')
            . ($mandado
                ? ' Se lo mandamos a ' . $correo . '.'
                : ($correo === ''
                    ? ' No tiene correo cargado, así que no se le pudo mandar: está el botón «Enviar por correo».'
                    : ' No se pudo mandar el correo a ' . $correo . ': reintentalo desde el comprobante.')),
            $r['ok'] && $mandado ? 'success' : 'warning');

        return redirect()->route('facturacion.factura_ver', ['id' => $idf]);
    }

    /** Lo que necesita la pantalla del receptor, precargado desde la ficha. */
    private function datosReceptor(int $idCita, int $idTipo, int $idCond, int $idCliente, int $persona = 0): array
    {
        $per = DB::selectOne(
            'SELECT pe.nombre, pe.apellido, pe.cedula, pe.ruc, pe.email, pe.telefono, pe.direccion
               FROM cliente c JOIN persona pe ON pe.id_persona = c.id_persona
              WHERE c.id_cliente = ?', [$idCliente]
        );

        // Con RUC cargado se asume que pide la factura a su nombre fiscal; si
        // no, la cédula; y sin ninguno de los dos, consumidor final.
        $tipo = match (true) {
            trim((string) ($per->ruc ?? '')) !== '' => 'RUC',
            trim((string) ($per->cedula ?? '')) !== '' => 'CI',
            default => 'CF',
        };

        return [
            'idCita' => $idCita,
            'idTipo' => $idTipo,
            'idCond' => $idCond,
            'tipoNombre' => (string) DB::scalar(
                'SELECT nombre FROM tipo_comprobante WHERE id_tipo_comprobante = ?', [$idTipo]),
            'condNombre' => (string) DB::scalar(
                'SELECT nombre FROM condicion_venta WHERE id_condicion_venta = ?', [$idCond]),
            'per' => $per,
            'tipoSugerido' => $tipo,
            'docSugerido' => $tipo === 'RUC' ? (string) $per->ruc : ($tipo === 'CI' ? (string) $per->cedula : ''),
            // **Los dos, para que cambiar de tipo cambie el número.** Con uno
            // solo, elegir «cédula» dejaba el RUC escrito en el campo: se
            // emitía con el documento equivocado, o la validación rebotaba
            // hablando de la cédula cuando lo que había era un RUC.
            'rucFicha' => trim((string) ($per->ruc ?? '')),
            'cedulaFicha' => trim((string) ($per->cedula ?? '')),
            // Los de toda la cita, o sólo los de ESA persona cuando el
            // comprobante es de una.
            'items' => DB::select(
                'SELECT s.nombre, s.precio FROM cita_servicio cs
                   JOIN servicio s ON s.id_servicio = cs.id_servicio
                  WHERE cs.id_cita = ? AND (? = 0 OR cs.persona = ?)', [$idCita, $persona, $persona]
            ),
            'total' => (float) DB::scalar(
                'SELECT COALESCE(SUM(s.precio),0) FROM cita_servicio cs
                   JOIN servicio s ON s.id_servicio = cs.id_servicio
                  WHERE cs.id_cita = ? AND (? = 0 OR cs.persona = ?)', [$idCita, $persona, $persona]
            ),
            'deQuien' => $persona > 0 ? $this->nombreDe($idCita, $persona) : '',
            'topeInnominado' => Sifen::TOPE_INNOMINADO,
        ];
    }

    /** Cómo se llama la persona N de la cita, para nombrarla en pantalla. */
    private function nombreDe(int $idCita, int $persona): string
    {
        $cita = DB::selectOne(
            "SELECT c.*, CONCAT(pe.nombre,' ',pe.apellido) AS cliente FROM cita c
               JOIN cliente cl ON cl.id_cliente = c.id_cliente
               JOIN persona pe ON pe.id_persona = cl.id_persona WHERE c.id_cita = ?", [$idCita]);

        return $cita
            ? (string) (Acompanantes::nombres($cita, Acompanantes::deCitas([$idCita])[$idCita] ?? [])[$persona] ?? 'Persona ' . $persona)
            : 'Persona ' . $persona;
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

        $lineas = $this->lineasDelPago($request, $volver);
        if ($lineas instanceof RedirectResponse) {
            return $lineas;
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

        // **A qué caja entra la plata lo dice la pantalla.** Con dos cajones
        // abiertos, dejar que el sistema elija manda el cobro al arqueo de otra
        // persona: quien cuenta al cerrar se encuentra con plata que no cobró.
        $idCaja = $this->cajaElegida($request, (int) $caja->id_caja);
        if ($idCaja instanceof RedirectResponse) {
            return $idCaja;
        }

        try {
            $r = Facturacion::cobrar($idFactura, (int) session('uid'), $lineas, $idCaja);

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
                                : 'No se pudo registrar el cobro. El detalle quedó en el registro del sistema.')))))
                . ' No se guardó ninguna de las líneas.', 'error');
        }

        return $volver;
    }

    /**
     * Las líneas de un pago mixto, tal como las manda el componente de cobro.
     *
     * Estaba escrito dentro de `cobrar()` y **sólo servía para cobrar contra una
     * factura**. Cobrar desde la agenda —que es como se cobra en el mostrador,
     * antes de que exista el comprobante— pasaba por otro camino con un solo
     * monto y un solo medio: no se podía dividir el pago ni cargar el detalle
     * de la tarjeta. Las dos pantallas usan el mismo componente, así que ahora
     * usan también el mismo lector.
     *
     * Devuelve el arreglo de líneas, o el redirect ya con el aviso puesto
     * cuando alguna no sirve — se valida acá y no adentro de la transacción,
     * porque si esto se cayera adentro se pierden las otras líneas.
     *
     * @return list<array<string,mixed>>|RedirectResponse
     */
    private function lineasDelPago(Request $request, RedirectResponse $volver): array|RedirectResponse
    {
        $metodos = (array) $request->input('metodo', []);
        $montos = (array) $request->input('monto', []);

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

            $detalle = $this->detalleDeLinea($request, $i);

            // `cobro_banco.banco` es NOT NULL: si se cargó el cheque o el número
            // de operación y falta el banco, se avisa acá en vez de dejar que la
            // base tire un 1048 que nadie sabe leer.
            $lleno = fn (string $k) => trim((string) ($detalle[$k] ?? '')) !== '';
            if (in_array($mp->tipo, ['BANCO', 'CHEQUE'], true)
                && ! $lleno('banco') && ($lleno('nro_cheque') || $lleno('nro_operacion'))) {
                flash('Poné el banco de la línea de ' . $mp->nombre . '.', 'error');

                return $volver;
            }

            // --- La fecha del cheque ---
            // Un cheque tiene fecha por algo: la diferida dice a partir de
            // cuándo se puede depositar, y la vencida ya no se cobra. Sin
            // control entraba cualquier cosa —un 2019 tipeado de más, un 2035—
            // y el arqueo lo daba por bueno igual.
            if ($mp->tipo === 'CHEQUE') {
                $error = $this->fechaDeChequeInvalida($detalle['fecha_emision'] ?? null, $lleno('fecha_emision'));
                if ($error !== null) {
                    flash('En la línea de ' . $mp->nombre . ': ' . $error, 'error');

                    return $volver;
                }
            }

            $lineas[] = [
                'metodo' => $idm, 'monto' => $mto, 'tipo' => $mp->tipo, 'nombre' => $mp->nombre,
                'referencia' => trim((string) (((array) $request->input('referencia', []))[$i] ?? '')) ?: null,
                'detalle' => $detalle,
            ];
        }

        return $lineas;
    }

    /**
     * Anular un movimiento de efectivo mal cargado.
     *
     * **Se anula, no se borra**, que es el mismo criterio que la factura y el
     * cobro: el arqueo tiene que poder explicar qué pasó, y una fila que
     * desaparece no explica nada. `fn_caja_saldo` suma sólo los activos, así
     * que el cajón vuelve a cuadrar en el momento.
     *
     * **Sólo mientras la caja siga abierta.** Después del cierre el arqueo ya
     * se contó y se firmó: cambiarlo por atrás dejaría el cierre diciendo un
     * número y la base otro. Si el error se descubre después, lo que
     * corresponde es cargar el movimiento contrario en la caja de hoy, y el
     * aviso lo dice en vez de contestar «no se puede».
     */
    public function movimientoCajaAnular(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_movimiento_caja', 0);
        $motivo = trim((string) $request->input('motivo', ''));
        $volver = redirect()->route('facturacion.cajas');

        $m = DB::selectOne(
            'SELECT mc.id_movimiento_caja, mc.tipo, mc.monto, mc.concepto, mc.activo, c.id_estado_caja
               FROM movimiento_caja mc JOIN caja c ON c.id_caja = mc.id_caja
              WHERE mc.id_movimiento_caja = ?', [$id]
        );

        $error = null;
        if (! $m) {
            $error = 'Ese movimiento no existe.';
        } elseif (! (int) $m->activo) {
            $error = 'Ese movimiento ya estaba anulado.';
        } elseif ((int) $m->id_estado_caja !== 1) {
            $error = 'Esa caja ya está cerrada, así que su arqueo no se toca. '
                . 'Cargá el movimiento contrario en la caja de hoy y explicá el motivo en el concepto.';
        } elseif ($motivo === '') {
            $error = 'Escribí por qué lo anulás: es lo único que explica ese movimiento al cerrar la caja.';
        }
        if ($error) {
            flash($error, 'error');

            return $volver;
        }

        DB::update('UPDATE movimiento_caja SET activo = 0, anulado_motivo = ? WHERE id_movimiento_caja = ?',
            [$motivo, $id]);

        Auditoria::registrar('ANULACION', 'Facturacion', 'movimiento_caja', $id,
            $m->tipo . ' de ' . money($m->monto) . ' (' . $m->concepto . ') — ' . $motivo);

        flash('Movimiento anulado. El saldo del cajón ya no lo cuenta.');

        return $volver;
    }

    /**
     * El comprobante que la clienta adjuntó al registrar su seña.
     *
     * **Se sirve desde acá y no desde `public/`**: es plata de una persona y no
     * tiene por qué quedar colgando de una URL que alguien adivine. Acá pasa
     * por la sesión y por el permiso de cobros, como todo lo demás del
     * mostrador.
     */
    public function senaComprobante(Request $request): Response|RedirectResponse
    {
        $id = (int) $request->query('id', 0);
        $nombre = (string) DB::scalar(
            'SELECT comprobante FROM sena_solicitud WHERE id_solicitud = ?', [$id]
        );

        // El nombre lo pone el sistema, pero se comprueba igual: si algún día lo
        // pusiera otra cosa, un `../` acá serviría cualquier archivo del disco.
        if ($nombre === '' || $nombre !== basename($nombre)) {
            flash('Esa seña no tiene comprobante adjunto.', 'warning');

            return redirect()->route('citas.agenda');
        }

        $ruta = storage_path('app/senas/' . $nombre);
        if (! is_file($ruta)) {
            flash('El comprobante ya no está guardado.', 'warning');

            return redirect()->route('citas.agenda');
        }

        return response()->file($ruta);
    }

    /**
     * ¿La fecha de este cheque sirve? Devuelve el motivo, o null si está bien.
     *
     * La fecha es obligatoria: es lo que distingue un cheque al día de uno
     * diferido, y sin ella no se sabe cuándo se puede depositar. Los dos topes
     * salen de cómo funciona el cheque, no de un número inventado:
     *
     *  · **Hacia atrás**, un cheque se presenta dentro de los 30 días de
     *    emitido. Se aceptan hasta 180 para dar lugar a que el salón lo cargue
     *    tarde, pero más que eso ya no es un cheque cobrable: es un error de
     *    tipeo (el año anterior, casi siempre).
     *  · **Hacia adelante**, un diferido de más de un año no existe.
     *
     * Se valida acá y no en la base porque el cobro entero va en una
     * transacción: si esto se cayera adentro, se pierden también las otras
     * líneas del pago mixto.
     */
    private function fechaDeChequeInvalida(?string $fecha, bool $vino): ?string
    {
        if (! $vino) {
            return 'cargá la fecha del cheque; es la que dice desde cuándo se puede depositar.';
        }

        $ts = strtotime((string) $fecha);
        if ($ts === false) {
            return 'la fecha del cheque no es válida.';
        }

        $hoy = strtotime(ahora_bd('Y-m-d'));
        $dias = (int) floor(($ts - $hoy) / 86400);

        if ($dias < -180) {
            return 'ese cheque está fechado el ' . fecha($ts, 'd/m/Y') . ', hace más de seis meses. '
                . 'Un cheque así ya no se cobra — revisá el año.';
        }
        if ($dias > 365) {
            return 'ese cheque está fechado el ' . fecha($ts, 'd/m/Y') . ', a más de un año. '
                . 'Un cheque diferido no llega tan lejos — revisá el año.';
        }

        return null;
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
        // Medio y Estado salen de los cobros que hay, no del catálogo — ver
        // `Listado::opcionesUsadas()`. Sin acotar por local, igual que la lista.
        $opMetodo = Listado::opcionesUsadas(
            'SELECT mp.id_metodo_pago AS k, mp.nombre AS v
               FROM cobro co
               JOIN metodo_pago mp ON mp.id_metodo_pago = co.id_metodo_pago
              GROUP BY mp.id_metodo_pago, mp.nombre
              ORDER BY mp.id_metodo_pago');
        $opEstado = Listado::opcionesUsadas(
            'SELECT ec.id_estado_cobro AS k, ec.nombre AS v
               FROM cobro co
               JOIN estado_cobro ec ON ec.id_estado_cobro = co.id_estado_cobro
              GROUP BY ec.id_estado_cobro, ec.nombre
              ORDER BY ec.id_estado_cobro');

        $f = Listado::filtros([
            'q' => ['tipo' => 'texto', 'etiqueta' => 'Buscar', 'ph' => 'Cliente o referencia', 'ancho' => '240px'],
            'metodo' => ['tipo' => 'select', 'etiqueta' => 'Medio de pago',
                         'opciones' => ['' => 'Todos'] + $opMetodo],
            'estado' => ['tipo' => 'select', 'etiqueta' => 'Estado',
                         'opciones' => ['' => 'Todos'] + $opEstado],
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

        // **La clienta también se busca por la CITA.** Se llegaba a ella sólo
        // por la factura, así que todo cobro contra la cita —la seña, y desde
        // la 7.19.0 también el cobro de la atención— salía sin nombre: una raya
        // en la columna Cliente. El dato estaba a un JOIN de distancia.
        $desde = 'FROM cobro co
                  JOIN metodo_pago mp   ON mp.id_metodo_pago = co.id_metodo_pago
                  JOIN estado_cobro ec  ON ec.id_estado_cobro = co.id_estado_cobro
                  LEFT JOIN factura fa   ON fa.id_factura = co.id_factura
                  LEFT JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = fa.id_tipo_comprobante
                  LEFT JOIN cita ci      ON ci.id_cita = co.id_cita
                  LEFT JOIN cliente cl   ON cl.id_cliente = COALESCE(fa.id_cliente, ci.id_cliente)
                  LEFT JOIN persona pe_cl ON pe_cl.id_persona = cl.id_persona
                  WHERE ' . implode(' AND ', $w);
        // `id_factura` y el tipo salen acá para que la lista pueda ABRIR el
        // comprobante: el Comprobante de pago no es una factura, y buscarlo
        // bajo «Facturas» es lo que no se le ocurre a nadie. Desde Cobros, que
        // es donde se lo busca, se llega de un clic.
        $cols = "co.id_cobro, co.fecha, co.monto, co.referencia, mp.nombre AS metodo, ec.nombre AS estado,
                 (co.id_factura IS NULL AND co.id_cita IS NOT NULL
                  AND COALESCE(co.observaciones, '') NOT LIKE 'Cobro de la atencion%') AS es_sena,
                 co.id_factura, tc.nombre AS tipo_comprobante,
                 fn_factura_nro(co.id_factura) AS nro_comprobante,
                 CONCAT(pe_cl.nombre,' ',pe_cl.apellido) AS cliente";

        if (Listado::pideExport()) {
            return Listado::exportar('cobros',
                ['Fecha', 'Cliente', 'Comprobante', 'Medio', 'Monto', 'Referencia', 'Estado'],
                array_map(fn ($r) => [fecha($r->fecha, 'd/m/Y H:i'), $r->cliente ?: '(seña sin factura)',
                    $r->nro_comprobante, $r->metodo, $r->monto, $r->referencia, $r->estado],
                    DB::select("SELECT $cols $desde ORDER BY co.fecha DESC", $par)),
                $f, 'Cobros'
            );
        }

        $pag = Listado::paginacion((int) DB::scalar("SELECT COUNT(*) $desde", $par));

        // **Lo que FALTA cobrar, arriba del historial.** Esta pantalla listaba
        // sólo lo ya cobrado, así que la atención que la clienta debía no
        // aparecía en ningún lado y la única forma de cobrarla era encontrar
        // la fila en la agenda; se reportó como «Cobros sólo es un módulo
        // historial» (7.119.0). Son las atendidas del local, de los últimos
        // treinta días, en las que lo cobrado —contra la cita o contra sus
        // comprobantes— no llega al total. El botón lleva a donde vive la
        // ventana de cobro: la agenda si no hay comprobante, Facturas si ya lo
        // hay — el cobro va contra el documento que exista.
        $parPc = [];
        $porCobrar = DB::select(
            "SELECT * FROM (
                SELECT c.id_cita, c.fecha_hora, c.personas,
                       CONCAT(pe.nombre,' ',pe.apellido) AS cliente,
                       fn_cita_total(c.id_cita) AS total,
                       (SELECT COALESCE(SUM(co.monto),0) FROM cobro co
                         WHERE co.id_estado_cobro = 1
                           AND (co.id_cita = c.id_cita
                                OR co.id_factura IN (SELECT f.id_factura FROM factura f
                                                      WHERE f.id_cita = c.id_cita AND f.id_estado_factura = 1))) AS cobrado,
                       (SELECT COUNT(*) FROM factura f WHERE f.id_cita = c.id_cita AND f.id_estado_factura = 1) AS facturas
                  FROM cita c
                  JOIN cliente cl ON cl.id_cliente = c.id_cliente
                  JOIN persona pe ON pe.id_persona = cl.id_persona
                 WHERE c.id_estado_cita = 4
                   AND c.fecha_hora >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                   " . Sucursales::filtro('c', $parPc) . "
             ) x
             WHERE x.cobrado < x.total - 0.5
             ORDER BY x.fecha_hora DESC LIMIT 30", $parPc
        );

        return view('facturacion.cobros', [
            'porCobrar' => $porCobrar,
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
        // **Sin esta línea la pantalla devolvía 500 y no se podía acreditar
        // nada.** El `$montoTexto` que lee el bloque de abajo se leía sin
        // existir: la línea que lo define había quedado en `anularFactura()`,
        // donde además no se usaba —anular no recibe monto—. Una variable
        // indefinida es `ErrorException` en Laravel, y como el `try` empieza
        // recién más abajo, salía sin traducir: la nota de crédito parcial que
        // trajo la 7.101.0 nunca llegó a funcionar.
        $montoTexto = trim((string) $request->input('monto', ''));
        $volver = redirect()->route('facturacion.factura_ver', ['id' => $id]);

        $f = DB::selectOne(
            'SELECT f.id_factura, f.id_cliente, f.id_estado_factura,
                    COALESCE(f.id_sucursal, t.id_sucursal) AS id_sucursal, tc.signo,
                    fn_factura_nro(f.id_factura) AS nro,
                    fn_factura_total(f.id_factura) AS total,
                    (SELECT COUNT(*) FROM factura n
                      WHERE n.id_factura_origen = f.id_factura AND n.id_estado_factura = 1) AS notas
               FROM factura f
               JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = f.id_tipo_comprobante
               LEFT JOIN timbrado t ON t.id_timbrado = f.id_timbrado
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
        $monto = null;
        if ($montoTexto !== '') {
            $montoNormalizado = str_replace(['.', ','], ['', '.'], $montoTexto);
            if (! is_numeric($montoNormalizado)) {
                flash('El monto de la reversa no es válido. Dejá vacío para acreditar todo.', 'error');

                return $volver;
            }
            $monto = round((float) $montoNormalizado, 2);
            if ($monto <= 0 || $monto > (float) $f->total) {
                flash('El monto debe ser mayor a cero y no superar el total de ' . money($f->total) . '.', 'error');

                return $volver;
            }
        }
        // El timbrado de notas de crédito (tipo 5) es distinto del de facturas
        if (! Facturacion::hayTimbrado(5)) {
            flash('No hay timbrado vigente para notas de crédito. Cargalo en Facturación → Timbrados.', 'error');

            return redirect()->route('facturacion.timbrados');
        }

        // **Cuánto se le devuelve en efectivo, y por lo tanto cuánto sale del
        // cajón** (FA-02). Sólo lo que la clienta pagó en efectivo: lo que pagó
        // con tarjeta o transferencia se le devuelve por el mismo camino y no
        // toca la caja, igual que al entrar.
        $enEfectivo = $this->efectivoDevolvible($id);
        $proporcion = $monto === null || $monto >= (float) $f->total
            ? 1.0 : $monto / max(0.01, (float) $f->total);
        $enEfectivo *= $proporcion;

        // **De qué cajón sale, y de cuál NO.** Es el pedido del usuario: la
        // nota descuenta la caja, y se elige cuál — entre las abiertas del
        // local **que emitió la factura**, no las del local donde está parada
        // la persona. La sucursal la dice el documento; el cajón, quien opera:
        // es la misma regla del cobro y de los dos pagos desde la 7.78.0.
        $idCajaDevolucion = (int) $request->input('id_caja', 0);
        $cajaDevolucion = null;

        // Por qué la devolución NO pudo salir del cajón, si no pudo. Se dice
        // en el aviso final: una devolución que no ocurre y no se nombra es
        // indistinguible de una que sí, que es el defecto que originó todo esto.
        $pendientePorque = '';

        if ($enEfectivo > 0.01) {
            $abiertas = Caja::abiertasDe((int) $f->id_sucursal);

            // **Con un solo cajón abierto no se pregunta.** Es la misma regla
            // que el cobro y los dos pagos: preguntar algo de una única
            // respuesta hace perder un clic. Con dos o más hay que elegir, o el
            // egreso cae en el arqueo de otra persona sin que nada lo diga.
            if (! $idCajaDevolucion && count($abiertas) === 1) {
                $idCajaDevolucion = (int) $abiertas[0]->id_caja;
            }

            // **El id del POST no se cree**: se comprueba que esté abierta y
            // que sea del local de la factura. Si no, cambiando un número
            // oculto se le saca plata al cajón de otra sucursal.
            $cajaDevolucion = $idCajaDevolucion ? DB::selectOne(
                'SELECT c.id_caja, cf.nombre, fn_caja_saldo(c.id_caja) AS saldo
                   FROM caja c
                   JOIN caja_fisica cf ON cf.id_caja_fisica = c.id_caja_fisica
                  WHERE c.id_caja = ? AND c.id_sucursal = ? AND c.id_estado_caja = 1',
                [$idCajaDevolucion, (int) $f->id_sucursal]
            ) : null;

            if (! $cajaDevolucion) {
                // Los dos casos se dicen distinto: no es lo mismo «no elegiste»
                // que «no hay ninguna abierta acá», y el segundo se resuelve
                // abriendo la caja, no volviendo a elegir.
                $pendientePorque = $abiertas
                    ? 'no se eligió de qué caja sale'
                    : 'la sucursal que emitió la factura no tiene ninguna caja abierta';
            } elseif ($enEfectivo > (float) $cajaDevolucion->saldo + 0.01) {
                // **Un egreso no puede dejar el cajón en negativo**, que es la
                // regla que ya valen el pago a proveedores y el movimiento de
                // efectivo. Lo que no corresponde es cancelar la nota por eso.
                $pendientePorque = 'la caja ' . $cajaDevolucion->nombre . ' tiene '
                    . money($cajaDevolucion->saldo) . ' y hacen falta ' . money($enEfectivo);
                $cajaDevolucion = null;
            }
        }

        // **La nota y la devolución se registran juntas**, por pedido del
        // usuario: en el mostrador la plata se entrega en el mismo acto, así
        // que separarlo obligaba a un segundo paso que nadie daba y el cajón
        // quedaba diciendo que ese dinero seguía adentro.
        //
        // Lo que la 7.48.0 vino a evitar —**dos salidas por la misma
        // devolución**— sigue estando cubierto, y por dos lados: el índice único
        // `uq_movcaja_devolucion (id_factura, activo)` no admite una segunda, y
        // `notasPorDevolver()` descarta la nota que ya tiene su egreso, así que
        // deja de ofrecerse en Movimiento de efectivo.

        try {
            $idNota = Facturacion::notaCredito($id, (int) session('uid'), $motivo, $monto);
            $nroNota = Facturacion::numero($idNota);

            // **El egreso va en su propio try, y no arrastra a la nota si
            // falla.** El comprobante ya está emitido y numerado: tirarlo abajo
            // porque el cajón no aceptó el movimiento sería perder un número de
            // la SET por un problema de caja. Si falla, la nota queda igual y
            // vuelve a aparecer en Movimiento de efectivo como devolución
            // pendiente — que es exactamente para lo que ese camino sigue ahí.
            $avisoCaja = '';
            if ($enEfectivo > 0.01 && $cajaDevolucion) {
                try {
                    // Los cuatro tipos son de salida (`signo = 'S'`); se pide
                    // explícito y no por descarte, que un `<> 'E'` se lee al revés.
                    $tipoDevolucion = (int) DB::scalar(
                        "SELECT id_tipo_mov_caja FROM tipo_movimiento_caja
                          WHERE activo = 1 AND signo = 'S' AND nombre LIKE 'Devoluci%'
                          ORDER BY id_tipo_mov_caja LIMIT 1");
                    if (! $tipoDevolucion) {
                        throw new \RuntimeException('No hay un tipo de movimiento «Devolución al cliente» activo.');
                    }
                    DB::insert(
                        'INSERT INTO movimiento_caja
                            (id_caja, id_tipo_mov_caja, id_factura, tipo, monto, concepto, nro_comprobante, id_usuario)
                         VALUES (?,?,?,?,?,?,?,?)',
                        [(int) $cajaDevolucion->id_caja, $tipoDevolucion,
                         $idNota, 'EGRESO', $enEfectivo,
                         'Devolución por ' . $nroNota . ' sobre ' . $f->nro, $nroNota,
                         (int) session('uid')]
                    );
                    Caja::olvidar();
                } catch (Throwable $exCaja) {
                    Log::error('Nota de crédito ' . $nroNota . ': no se pudo registrar el egreso de caja. '
                        . $exCaja->getMessage());
                    $enEfectivo = 0.0;
                    $avisoCaja = ' OJO: no se pudo descontar el efectivo del cajón, así que la devolución '
                        . 'de esa plata quedó pendiente en Tesorería → Movimiento de efectivo.';
                }
            } elseif ($pendientePorque !== '') {
                // **La nota se emite igual, y eso no es un descuido.** Es un
                // comprobante fiscal con su número: no se puede tirar abajo
                // porque el cajón esté corto o porque falte abrirlo, y su
                // número tampoco se reutiliza. La devolución vuelve a ser lo
                // que era antes de esta versión —el segundo acto, desde
                // Movimiento de efectivo— y acá se dice por qué.
                $avisoCaja = ' OJO: no se descontó nada del cajón porque ' . $pendientePorque
                    . ', así que la devolución de ' . money($enEfectivo) . ' quedó PENDIENTE '
                    . 'en Tesorería → Movimiento de efectivo.';
                $enEfectivo = 0.0;
            }

            Auditoria::registrar('NOTA_CREDITO', 'Facturacion', 'factura', $idNota,
                'Nota de crédito ' . $nroNota . ' sobre ' . $f->nro . ' — ' . $motivo);

            $devueltos = Facturacion::revertirPuntos($id, (int) $f->id_cliente, 'Nota de crédito', $proporcion);

            // **La nota de crédito también se declara ante la DNIT**, y hasta
            // acá no se mandaba nunca. `config/sifen.php` la lista en
            // `tipos_electronicos` junto con la factura desde la 7.0.0, pero
            // este método emitía, copiaba el detalle, descontaba el efectivo y
            // revertía los puntos **sin llamar a `Sifen::` en ninguna línea**:
            // en la simulación de 60 días se declararon 70 de 70 facturas y
            // 0 de 5 notas, así que la DNIT seguía viendo la venta original y
            // no su reverso. Un salón que devuelve todos los meses termina
            // declarando de más ante la DNIT sin que ninguna pantalla lo diga.
            //
            // Va **después** de emitir y no atada a ella, que es la regla de
            // siempre: la nota ya es válida sin la DNIT, así que si el envío
            // falla queda PENDIENTE y se reintenta desde el comprobante.
            $avisoSifen = '';
            if (Sifen::activo() && Sifen::esElectronico(5)) {
                $envio = Sifen::enviar($idNota);
                $avisoSifen = ' ' . $envio['mensaje']
                    . ($envio['ok'] ? '' : ' La nota es válida igual: podés reintentar el envío desde el comprobante.');
            }

            // **Y se le manda, sin apretar nada más.** La clienta tiene la
            // factura en el correo; la reversa le corresponde igual, y antes
            // había que acordarse de entrar a la nota y usar «Enviar por
            // correo». Va después de declarar —para que el KuDE y el XML ya
            // estén bajados— y **no atada a eso**: si el correo falla, la nota
            // sigue emitida y se reintenta desde su detalle.
            $correo = trim((string) $request->input('email', ''));
            $mandado = $correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL)
                && $this->mandarComprobante($idNota, $correo);

            flash('Nota de crédito ' . $nroNota . ' emitida sobre ' . $f->nro
                . ($monto !== null && $monto < (float) $f->total ? ' por ' . money($monto) : ' por el total') . '.'
                // **Se dice de QUÉ cajón salió.** Con dos abiertas en el mismo
                // local, «se descontó de la caja» no alcanza: quien cierra el
                // otro cajón no tiene cómo saber que ese egreso no era suyo.
                . ($enEfectivo > 0
                    ? ' Se descontaron ' . money($enEfectivo) . ' de ' . ($cajaDevolucion->nombre ?? 'la caja') . '.'
                    : ($avisoCaja !== ''
                        ? ''
                        : ' No se descontó nada del cajón: esa venta no se había cobrado en efectivo.'))
                . $avisoCaja
                . ($devueltos ? ' Se le descontaron al cliente los ' . $devueltos . ' punto(s) de esa venta.' : '')
                . $avisoSifen
                . ($correo !== ''
                    ? ($mandado ? ' Se la mandamos a ' . $correo . '.'
                                : ' No se pudo mandar a ' . $correo . ': reintentalo desde el comprobante.')
                    : ''));

            return redirect()->route('facturacion.factura_ver', ['id' => $idNota]);
        } catch (Throwable $ex) {
            $msg = $ex->getMessage();
            flash(str_contains($msg, 'timbrado') ? 'No hay timbrado vigente para notas de crédito.'
                : (str_contains($msg, 'agotado') ? 'Se agotó la numeración del timbrado de notas de crédito.'
                    : (str_contains($msg, 'venta') ? 'Solo se puede acreditar un comprobante de venta.'
                        : 'No se pudo emitir la nota de crédito. El comprobante original no se tocó, '
                        . 'así que se puede reintentar.')), 'error');

            return $volver;
        }
    }

    // -----------------------------------------------------------------
    //  Seña
    // -----------------------------------------------------------------

    public function sena(Request $request): RedirectResponse
    {
        $idCita = (int) $request->input('id_cita', 0);
        $dia = (string) $request->input('dia', date('Y-m-d'));
        $volver = redirect()->route('citas.agenda', ['dia' => $dia]);

        // **El pago se puede dividir, igual que contra una factura.** Antes acá
        // había un solo monto y un solo medio: mitad efectivo y mitad tarjeta
        // —que en el mostrador es lo normal— no se podía cargar, y el detalle
        // de la tarjeta o del banco no se pedía nunca. La pantalla usa el mismo
        // componente que Facturas, así que manda `metodo[]` y `monto[]`.
        //
        // Se conserva el formato viejo (`id_metodo_pago` + `monto` sueltos) por
        // si algo todavía lo manda: no cuesta nada y evita romper un camino que
        // no se ve desde acá.
        $lineas = $this->lineasDelPago($request, $volver);
        if ($lineas instanceof RedirectResponse) {
            return $lineas;
        }
        if (! $lineas) {
            $idm = (int) $request->input('id_metodo_pago', 0);
            $mto = num($request->input('monto'));
            if ($idm > 0 && $mto > 0) {
                $lineas = [['metodo' => $idm, 'monto' => $mto,
                            'ref' => trim((string) $request->input('referencia', '')) ?: null,
                            'detalle' => []]];
            }
        }

        $idMetodo = (int) ($lineas[0]['metodo'] ?? 0);
        $monto = (float) array_sum(array_column($lineas, 'monto'));
        $ref = $lineas[0]['ref'] ?? null;

        $cita = DB::selectOne(
            "SELECT c.id_cita, c.id_estado_cita, c.personas, c.para_otra_persona, c.nombre_para,
                    CONCAT(pe_cl.nombre,' ',pe_cl.apellido) AS cliente
               FROM cita c JOIN cliente cl ON cl.id_cliente = c.id_cliente
               JOIN persona pe_cl ON pe_cl.id_persona = cl.id_persona WHERE c.id_cita = ?", [$idCita]
        );

        // **¿De quién es este pago?** En la cita de varias personas cada una
        // puede pagar lo suyo y llevarse su comprobante, o pagar todo junto.
        // `modo_pago` lo dice; `persona` (1 = titular, 2..N acompañantes) sale
        // del selector, que la pantalla deshabilita —o sea, no manda— cuando
        // pagan juntas. Cero es el cobro de la cita entera, lo de siempre.
        $modo = (string) $request->input('modo_pago', 'junto');
        $persona = $modo === 'persona' ? (int) $request->input('persona', 0) : 0;

        // ¿Ya tiene comprobante emitido? Es lo que decide si esto es un cobro
        // contra la cita o hay que ir por la factura. **Y son dos preguntas
        // desde que hay comprobante por persona**: uno de toda la cita cierra
        // el cobro contra ella; el de UNA persona cierra sólo el suyo.
        $facturaGrupal = (bool) DB::scalar(
            'SELECT COUNT(*) FROM factura WHERE id_cita = ? AND id_estado_factura = 1 AND persona IS NULL',
            [$idCita]
        );
        $facturadas = array_map('intval', array_column(DB::select(
            'SELECT persona FROM factura WHERE id_cita = ? AND id_estado_factura = 1 AND persona IS NOT NULL',
            [$idCita]
        ), 'persona'));

        $cuenta = null;
        $error = null;
        if (! $cita) {
            $error = 'Esa cita no existe.';
        } elseif (in_array((int) $cita->id_estado_cita, [3, 6], true)) {
            $error = 'No se puede cobrar una cita cancelada o marcada como ausente.';
        } elseif ($facturaGrupal) {
            // Con comprobante emitido el cobro va contra ÉL, que es donde la
            // numeración de la DNIT lo puede rastrear.
            $error = 'Esa cita ya tiene comprobante emitido: cobralo desde Facturas.';
        } elseif ($persona > 0 && $persona > max(1, (int) $cita->personas)) {
            $error = 'Esa cita es de ' . max(1, (int) $cita->personas) . ' persona(s): no hay a quién cobrarle eso.';
        } elseif ($persona > 0 && in_array($persona, $facturadas, true)) {
            $error = 'Esa persona ya tiene su comprobante: cobralo desde Facturas.';
        } elseif ($persona === 0 && $facturadas) {
            // Alguien del grupo ya se fue con el suyo: lo que queda se cobra
            // por persona, o el cobro «de todas» le pisa lo ya facturado.
            $error = 'Alguna de las personas de esta cita ya tiene su comprobante: '
                . 'cobrá eligiendo «Cada una lo suyo».';
        } elseif ($persona > 0) {
            // **Lo que le falta a ESA persona**, con la misma cuenta que
            // muestra la agenda: su parte proporcional del total de la cita,
            // menos lo que ya pagó a su nombre. La base topa contra la cita
            // entera, así que sin esto se le podría cobrar a una lo de la otra.
            $cuenta = Acompanantes::cuenta($cita, Acompanantes::deCitas([$idCita])[$idCita] ?? []);
            $suya = $cuenta[$persona] ?? null;
            if (! $suya || ! $suya['servicios']) {
                $error = ($suya['nombre'] ?? 'Esa persona') . ' no tiene servicios en esta cita: no hay nada que cobrarle.';
            } elseif ($monto > $suya['falta'] + 0.5) {
                $error = 'A ' . $suya['nombre'] . ' le faltan ' . money($suya['falta'])
                    . ($suya['cobrado'] > 0 ? ' (ya pagó ' . money($suya['cobrado']) . ')' : '')
                    . ', así que no se le puede cobrar ' . money($monto) . '.';
            }
        }
        if (! $error && $monto <= 0) {
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

        // La caja la elige quien cobra cuando hay más de una abierta: si no, la
        // seña entra al arqueo del cajón equivocado.
        $idCaja = $this->cajaElegida($request, (int) $caja->id_caja);
        if ($idCaja instanceof RedirectResponse) {
            return $idCaja;
        }

        try {
            // **Una llamada por línea, todo en una transacción.** Es el mismo
            // modelo que el cobro contra factura: `cobro` es cada pago, no el
            // pago de la cita. Si una línea falla no queda media cita cobrada.
            $idCobro = 0;
            $cobrados = [];
            Bd::enTransaccion(function () use ($lineas, $idCita, $idCaja, $persona, &$idCobro, &$cobrados) {
                foreach ($lineas as $l) {
                    $nuevo = Facturacion::sena($idCita, (int) $l['metodo'], (int) session('uid'),
                        (float) $l['monto'], $l['referencia'] ?? ($l['ref'] ?? null), $idCaja,
                        $persona > 0 ? $persona : null);
                    $idCobro = $idCobro ?: $nuevo;
                    $cobrados[] = $nuevo;
                    if (! empty($l['detalle'])) {
                        Facturacion::guardarDetalle($nuevo, (string) ($l['tipo'] ?? ''), $l['detalle']);
                    }
                }
            });

            // Si esto confirma una seña que la clienta registró desde el
            // portal, se enlaza el cobro con la solicitud: es lo que la saca
            // de «a confirmar». El estado no se guarda —se deduce de que haya
            // cobro— así que alcanza con escribir el id. Se filtra por
            // `id_cita` además del id para que un id ajeno no cierre la
            // solicitud de otra cita.
            $idSolicitud = (int) $request->input('id_solicitud', 0);
            if ($idSolicitud > 0) {
                DB::update(
                    'UPDATE sena_solicitud SET id_cobro = ?, id_usuario = ?
                      WHERE id_solicitud = ? AND id_cita = ? AND id_cobro IS NULL AND rechazada_en IS NULL',
                    [$idCobro, (int) session('uid'), $idSolicitud, $idCita]
                );
            }

            // El procedimiento deja la observación fija en «Sena de reserva»,
            // que es cierto cuando se cobra antes de atender. Cobrando una
            // cita ya atendida es otra cosa, y el arqueo tiene que poder
            // distinguirlas.
            $atendida = (int) $cita->id_estado_cita === 4;
            if ($atendida) {
                DB::update('UPDATE cobro SET observaciones = ' . "'Cobro de la atencion'"
                    . ' WHERE id_cobro IN (' . implode(',', array_map('intval', $cobrados)) . ')');
            }

            $deQuien = $persona > 0 && $cuenta ? ' — de ' . $cuenta[$persona]['nombre'] : '';
            Auditoria::registrar('SENA', 'Facturacion', 'cobro', $idCobro,
                ($atendida ? 'Cobro de ' : 'Seña de ') . money($monto) . ' por la cita #' . $idCita
                . ' (' . $cita->cliente . ')' . $deQuien
                . ($idSolicitud > 0 ? ' — confirma la que registró la clienta desde el portal' : ''));

            // **Cobrado; ahora el comprobante que la clienta pida.**
            //
            // Es el orden del mostrador —cliente, cobro, y recién ahí factura o
            // comprobante de pago— y es el que estaba al revés: el sistema
            // obligaba a elegir el documento antes de tocar la plata. Se puede
            // porque el cobro cuelga de la CITA (`cobro.id_cita`, sin factura)
            // y `fn_factura_saldo` ya descuenta esos cobros: al emitir después,
            // el comprobante sale saldado solo.
            if ($atendida) {
                flash('Cobrado ' . money($monto) . ' a ' . ($persona > 0 && $cuenta ? $cuenta[$persona]['nombre'] : $cita->cliente)
                    . '. Ahora elegí el comprobante que pida: se descuenta solo del total.');

                // Cobrado por persona, el comprobante que sigue es el de ESA
                // persona: la pantalla de emitir lo deja elegido.
                return redirect()->route('facturacion.emitir',
                    ['cita' => $idCita] + ($persona > 0 ? ['persona' => $persona] : []));
            }

            flash('Seña de ' . money($monto) . ' registrada para ' . $cita->cliente
                . '. Se va a descontar sola del total cuando se facture la cita.');
        } catch (Throwable $ex) {
            // El tope de la seña (FA-03) se hace cumplir en la base, así que
            // acá sólo se traduce. Antes se aceptó una seña de Gs. 480.000
            // sobre una cita de Gs. 160.000, y al facturarla el saldo quedaba
            // negativo: no se podía cobrar nada más y la única salida era
            // anular el cobro.
            $total = (float) DB::scalar(
                'SELECT COALESCE(SUM(s.precio),0) FROM cita_servicio cs
                   JOIN servicio s ON s.id_servicio = cs.id_servicio WHERE cs.id_cita = ?', [$idCita]
            );
            $yaSenado = (float) DB::scalar('SELECT fn_cita_sena(?)', [$idCita]);

            flash(Bd::traducir($ex, [
                'cero' => 'El monto tiene que ser mayor que cero.',
                'no puede superar' => 'Esa cita vale ' . money($total)
                    . ($yaSenado > 0 ? ' y ya tiene ' . money($yaSenado) . ' cobrados' : '')
                    . ', así que no se puede cobrar ' . money($monto) . '.',
                'no tiene servicios' => 'Esa cita no tiene servicios cargados, así que no hay monto que cobrar.',
            ], 'No se pudo registrar el cobro. El detalle quedó registrado.'), 'error');

            if (! str_contains($ex->getMessage(), 'cero')
                && ! str_contains($ex->getMessage(), 'no puede superar')
                && ! str_contains($ex->getMessage(), 'no tiene servicios')) {
                Log::error('No se pudo registrar la seña o el cobro de la cita',
                    ['cita' => $idCita, 'error' => $ex->getMessage()]);
            }
        }

        return $volver;
    }

    // -----------------------------------------------------------------
    //  Caja
    // -----------------------------------------------------------------

    /**
     * El movimiento de efectivo a mano: el gasto de caja chica, el retiro, la
     * plata que se saca para el cambio.
     *
     * **Es su propia pantalla y su propio permiso.** Vivía dentro de Caja, y
     * son dos cosas distintas: abrir y cerrar el cajón es administrar el
     * arqueo; meter o sacar plata a mano es mover dinero **sin un documento
     * detrás** —no hay cobro ni pago que lo respalde, sólo un concepto
     * escrito—, así que es la parte que un salón puede querer dar por
     * separado. Mismo criterio que separó Timbrados en la 5.2.0.
     */
    /**
     * Movimientos de caja: TODO lo que movió la plata, no sólo lo manual.
     *
     * **Un pago a proveedor es un movimiento de caja, y un cobro también.**
     * Antes esta pantalla listaba únicamente `movimiento_caja` —el gasto, el
     * retiro, la devolución— así que en un salón que no carga ninguno se veía
     * vacía aunque la caja hubiera tenido setenta cobros. El nombre
     * «movimiento de efectivo» le hacía creer al lector que esos otros no
     * contaban.
     *
     * Las cuatro fuentes son exactamente las que suma `fn_caja_saldo`, así que
     * lo que se lista acá es lo que explica el arqueo:
     *
     * | Fuente | Signo |
     * |---|---|
     * | `cobro` | entra |
     * | `movimiento_caja` | según su clase |
     * | `pago_proveedor` | sale |
     * | `pago_personal` | sale |
     *
     * **El formulario de carga manual sigue siendo el mismo**: es lo único que
     * no sale de un documento, y por eso pide concepto y comprobante.
     */
    public function movimientos(): View
    {
        $mias = Sucursales::delUsuario();
        $abierta = Caja::abierta();

        $opCaja = ['' => 'Todas'];
        foreach (Caja::cajones(count($mias) === 1 ? (int) $mias[0]->id_sucursal : null) as $cf) {
            $opCaja[(string) $cf->id_caja_fisica] = $cf->nombre
                . (count($mias) > 1 ? ' · ' . $cf->sucursal : '');
        }

        $f = Listado::filtros([
            'q' => ['tipo' => 'texto', 'etiqueta' => 'Buscar', 'ph' => 'Concepto, cliente o proveedor', 'ancho' => '230px'],
            'caja' => ['tipo' => 'select', 'etiqueta' => 'Caja', 'opciones' => $opCaja, 'ancho' => '180px'],
            'clase' => ['tipo' => 'select', 'etiqueta' => 'Qué', 'ancho' => '190px',
                        'opciones' => ['' => 'Todo', 'cobro' => 'Cobros', 'manual' => 'Gastos y retiros',
                                       'prov' => 'Pagos a proveedores', 'pers' => 'Pagos al personal']],
            'desde' => ['tipo' => 'fecha', 'etiqueta' => 'Desde'],
            'hasta' => ['tipo' => 'fecha', 'etiqueta' => 'Hasta'],
        ]);

        // El aislamiento por sucursal sale de la caja del movimiento, que es de
        // dónde salió esa plata — no hace falta columna propia en cada tabla.
        $ids = array_map(fn ($su) => (int) $su->id_sucursal, $mias);
        $enSuc = 'c.id_sucursal IN (' . implode(',', $ids ?: [0]) . ')';

        $par = [];
        // **Cada fuente lleva sus PROPIOS marcadores.** La conexión abre PDO
        // con `ATTR_EMULATE_PREPARES` en `false`, así que MySQL prepara de
        // verdad y **no admite `:cf` cuatro veces** — con el filtro de caja
        // puesto, la consulta reventaba con «Invalid parameter number».
        //
        // Es el mismo error que el documento del proyecto ya anota, y por eso
        // el sufijo va por fuente: `:cf_cobro`, `:cf_manual`, …
        $filtros = function (string $campoFecha, string $suf) use ($f, &$par, $enSuc): string {
            $w = [$enSuc];
            if (Listado::hay($f, 'caja')) {
                $w[] = "c.id_caja_fisica = :cf_$suf";
                $par["cf_$suf"] = (int) Listado::valor($f, 'caja');
            }
            if (Listado::hay($f, 'desde')) {
                $w[] = "DATE($campoFecha) >= :d_$suf";
                $par["d_$suf"] = Listado::valor($f, 'desde');
            }
            if (Listado::hay($f, 'hasta')) {
                $w[] = "DATE($campoFecha) <= :h_$suf";
                $par["h_$suf"] = Listado::valor($f, 'hasta');
            }

            return implode(' AND ', $w);
        };

        $q = Listado::valor($f, 'q');
        $clase = (string) Listado::valor($f, 'clase');
        $como = $q !== '' ? '%' . $q . '%' : null;

        // Mismo motivo que arriba: `:q` aparecía en las cuatro y dos veces en
        // algunas. Cada uso se registra con su nombre propio.
        $buscar = function (array $campos, string $suf) use ($como, &$par): string {
            if ($como === null) {
                return '';
            }
            $ors = [];
            foreach ($campos as $i => $campo) {
                $par["q{$suf}{$i}"] = $como;
                $ors[] = "$campo LIKE :q{$suf}{$i}";
            }

            return ' AND (' . implode(' OR ', $ors) . ')';
        };

        $partes = $this->partesMovimientos($clase, $filtros, $buscar);

        $union = '(' . implode(') UNION ALL (', $partes) . ')';
        $pag = Listado::paginacion((int) DB::scalar("SELECT COUNT(*) FROM ($union) t", $par));

        return view('facturacion.movimientos', [
            'abierta' => $abierta,
            'tipos' => DB::select('SELECT id_tipo_mov_caja, nombre, signo, exige_documento
                                     FROM tipo_movimiento_caja WHERE activo = 1 ORDER BY id_tipo_mov_caja'),
            // **Las notas de crédito que todavía no se devolvieron.** La
            // devolución no se tipea: se elige la nota y el monto sale de ella,
            // que es lo que evita que queden dos salidas por la misma
            // devolución con números distintos.
            'notas' => $this->notasPorDevolver(),
            'f' => $f,
            'pag' => $pag,
            'movimientos' => DB::select(
                "SELECT * FROM ($union) t ORDER BY t.cuando DESC
                 LIMIT {$pag['porPagina']} OFFSET {$pag['offset']}", $par
            ),
        ]);
    }

    /**
     * Las cuatro fuentes que suma `fn_caja_saldo`, como consultas sueltas.
     *
     * **Una consulta por fuente, unidas con UNION.** Cada tabla nombra
     * distinto lo que pasó —un cobro tiene medio de pago, una liquidación
     * tiene a quién se le pagó— y forzarlas a un solo JOIN daría filas
     * duplicadas y un `CASE` de veinte líneas.
     *
     * Vive acá y no dentro de `movimientos()` porque la usan dos pantallas:
     * el listado con sus filtros y el modal del día de cada caja. Escrita
     * dos veces, una de las dos se queda atrás.
     *
     * @param  callable(string,string):string  $filtros  el WHERE de esa fuente
     * @param  callable(array,string):string   $buscar   el LIKE, o cadena vacía
     * @return string[]
     */
    private function partesMovimientos(string $clase, callable $filtros, callable $buscar): array
    {
        $partes = [];

        if ($clase === '' || $clase === 'cobro') {
            $partes[] = "SELECT 'cobro' AS clase, co.fecha AS cuando, cf.nombre AS caja_nombre,
                                CONCAT('Cobro', COALESCE(CONCAT(' · ', pe.nombre, ' ', COALESCE(pec.apellido,'')), '')) AS detalle,
                                mp.nombre AS medio, co.monto AS monto, 1 AS signo,
                                TRIM(CONCAT_WS(' ', pu.nombre, pu.apellido)) AS quien,
                                1 AS activo, NULL AS motivo, co.id_cobro AS id_ref
                           FROM cobro co
                           JOIN caja c ON c.id_caja = co.id_caja
                           JOIN caja_fisica cf ON cf.id_caja_fisica = c.id_caja_fisica
                           JOIN metodo_pago mp ON mp.id_metodo_pago = co.id_metodo_pago
                           LEFT JOIN usuario u ON u.id_usuario = co.id_usuario
                           LEFT JOIN persona pu ON pu.id_persona = u.id_persona
                           LEFT JOIN factura fa ON fa.id_factura = co.id_factura
                           LEFT JOIN cita ci ON ci.id_cita = COALESCE(co.id_cita, fa.id_cita)
                           LEFT JOIN cliente cl ON cl.id_cliente = COALESCE(ci.id_cliente, fa.id_cliente)
                           LEFT JOIN persona pe ON pe.id_persona = cl.id_persona
                           LEFT JOIN persona pec ON pec.id_persona = cl.id_persona
                          WHERE co.id_estado_cobro = 1 AND " . $filtros('co.fecha', 'cobro')
                . $buscar(['pe.nombre', 'mp.nombre'], 'co');
        }

        if ($clase === '' || $clase === 'manual') {
            $partes[] = "SELECT 'manual' AS clase, mc.fecha AS cuando, cf.nombre AS caja_nombre,
                                CONCAT(COALESCE(tmc.nombre, mc.tipo), ' · ', mc.concepto) AS detalle,
                                'Efectivo' AS medio, mc.monto AS monto,
                                IF(mc.tipo = 'INGRESO', 1, -1) AS signo,
                                TRIM(CONCAT_WS(' ', pu.nombre, pu.apellido)) AS quien,
                                mc.activo AS activo, mc.anulado_motivo AS motivo,
                                mc.id_movimiento_caja AS id_ref
                           FROM movimiento_caja mc
                           JOIN caja c ON c.id_caja = mc.id_caja
                           JOIN caja_fisica cf ON cf.id_caja_fisica = c.id_caja_fisica
                           LEFT JOIN tipo_movimiento_caja tmc ON tmc.id_tipo_mov_caja = mc.id_tipo_mov_caja
                           LEFT JOIN usuario u ON u.id_usuario = mc.id_usuario
                           LEFT JOIN persona pu ON pu.id_persona = u.id_persona
                          WHERE " . $filtros('mc.fecha', 'manual')
                . $buscar(['mc.concepto', 'mc.nro_comprobante'], 'mc');
        }

        if ($clase === '' || $clase === 'prov') {
            $partes[] = "SELECT 'prov' AS clase, pp.fecha AS cuando, cf.nombre AS caja_nombre,
                                CONCAT('Pago a proveedor · ', COALESCE(ppe.nombre, 'sin nombre')) AS detalle,
                                mp.nombre AS medio, fn_pago_proveedor_monto(pp.id_pago_proveedor) AS monto,
                                -1 AS signo,
                                TRIM(CONCAT_WS(' ', pu.nombre, pu.apellido)) AS quien,
                                1 AS activo, NULL AS motivo, pp.id_pago_proveedor AS id_ref
                           FROM pago_proveedor pp
                           JOIN caja c ON c.id_caja = pp.id_caja
                           JOIN caja_fisica cf ON cf.id_caja_fisica = c.id_caja_fisica
                           JOIN metodo_pago mp ON mp.id_metodo_pago = pp.id_metodo_pago
                           LEFT JOIN proveedor pr ON pr.id_proveedor = pp.id_proveedor
                           LEFT JOIN persona ppe ON ppe.id_persona = pr.id_persona
                           LEFT JOIN usuario u ON u.id_usuario = pp.id_usuario
                           LEFT JOIN persona pu ON pu.id_persona = u.id_persona
                          WHERE pp.id_estado_pago_proveedor = 1 AND " . $filtros('pp.fecha', 'prov')
                . $buscar(['ppe.nombre', 'pp.referencia'], 'pp');
        }

        if ($clase === '' || $clase === 'pers') {
            $partes[] = "SELECT 'pers' AS clase, pl.fecha AS cuando, cf.nombre AS caja_nombre,
                                CONCAT('Liquidación · ', TRIM(CONCAT_WS(' ', ppe.nombre, ppe.apellido))) AS detalle,
                                COALESCE(mp.nombre, 'Efectivo') AS medio,
                                fn_pago_personal_monto(pl.id_pago_personal) AS monto, -1 AS signo,
                                TRIM(CONCAT_WS(' ', pur.nombre, pur.apellido)) AS quien,
                                1 AS activo, NULL AS motivo,
                                pl.id_pago_personal AS id_ref
                           FROM pago_personal pl
                           JOIN caja c ON c.id_caja = pl.id_caja
                           JOIN caja_fisica cf ON cf.id_caja_fisica = c.id_caja_fisica
                           LEFT JOIN metodo_pago mp ON mp.id_metodo_pago = pl.id_metodo_pago
                           LEFT JOIN usuario u ON u.id_usuario = pl.id_usuario
                           LEFT JOIN persona ppe ON ppe.id_persona = u.id_persona
                           LEFT JOIN usuario ur ON ur.id_usuario = pl.id_usuario_registro
                           LEFT JOIN persona pur ON pur.id_persona = ur.id_persona
                          WHERE pl.id_estado_pago = 1 AND " . $filtros('pl.fecha', 'pers')
                . $buscar(['ppe.nombre'], 'pl');
        }

        return $partes;
    }

    /**
     * Los movimientos de HOY de un cajón, para el modal de la lista.
     *
     * **Es la pregunta del mostrador, no la del informe.** «¿Qué entró y salió
     * de esta caja hoy?» se contesta de un vistazo y sin salir de la pantalla;
     * para mirar los de la semana pasada está Movimientos, con sus filtros.
     *
     * Sale de las MISMAS cuatro fuentes que el listado —un pago a proveedor es
     * un movimiento de caja, y un cobro también— así que el modal y la
     * pantalla no pueden decir cosas distintas.
     *
     * @return array<int, object>
     */
    private function movimientosDelDia(int $idCajaFisica): array
    {
        $par = [];
        // Un marcador por fuente: la conexión prepara de verdad y no admite el
        // mismo nombre dos veces en la misma sentencia.
        $filtros = function (string $campoFecha, string $suf) use (&$par, $idCajaFisica): string {
            $par["cf_$suf"] = $idCajaFisica;

            return "c.id_caja_fisica = :cf_$suf AND DATE($campoFecha) = CURDATE()";
        };
        $buscar = fn (array $campos, string $suf): string => '';

        $partes = $this->partesMovimientos('', $filtros, $buscar);
        $union = '(' . implode(') UNION ALL (', $partes) . ')';

        return DB::select("SELECT * FROM ($union) t ORDER BY t.cuando DESC LIMIT 60", $par);
    }

    /**
     * Las cajas del salón: una fila por cajón.
     *
     * **Filtros arriba, tabla, paginación.** Es la misma forma que Movimientos
     * y Arqueos, y no cambia con el tamaño del salón: con 3 cajones o con 300
     * lo único que crece son las filas.
     *
     * Cada fila dice lo mínimo para elegir —cajón, estado, responsable, hora
     * de apertura— y nada más: el monto, los movimientos y el arqueo se
     * consultan entrando. Una tabla que lo muestra todo no se lee.
     */
    public function cajas(): View
    {
        $mias = Sucursales::delUsuario();
        $opciones = ['' => 'Todas'];
        foreach ($mias as $su) {
            $opciones[(string) $su->id_sucursal] = $su->nombre;
        }

        // **Un filtro que no aplica se SACA del arreglo, no se pone en null**:
        // `Listado::filtros()` lo tomaría como uno de texto sin tipo y saldría
        // un campo de búsqueda titulado «sucursal».
        $campos = [
            'q' => ['tipo' => 'texto', 'etiqueta' => 'Buscar', 'ph' => 'Nombre de la caja', 'ancho' => '220px'],
        ];
        // Con un solo local el filtro no significa nada: todo lo que hay es de acá.
        if (count($mias) > 1) {
            $campos['sucursal'] = ['tipo' => 'select', 'etiqueta' => 'Sucursal',
                                   'opciones' => $opciones, 'ancho' => '190px'];
        }
        $campos['estado'] = ['tipo' => 'select', 'etiqueta' => 'Estado', 'ancho' => '160px',
                             'opciones' => ['' => 'Todas', '1' => 'Abiertas', '0' => 'Cerradas']];

        $f = Listado::filtros($campos);

        $suc = Listado::hay($f, 'sucursal')
            ? (int) Listado::valor($f, 'sucursal')
            : (count($mias) === 1 ? (int) $mias[0]->id_sucursal : 0);

        $todas = Caja::cajones($suc ?: null, [
            'q' => (string) Listado::valor($f, 'q'),
            'estado' => (string) Listado::valor($f, 'estado'),
        ]);

        // Se pagina en memoria: son cajones, no movimientos — un salón con
        // cien ya sería raro, y la consulta trae una fila por cada uno.
        $pag = Listado::paginacion(count($todas));

        $rows = array_slice($todas, $pag['offset'], $pag['porPagina']);

        // **Cada tarjeta trae los movimientos de SU caja**, que es lo que hace
        // que la pantalla conteste sola: con dos cajones abiertos, leer el
        // arqueo de uno con los movimientos del otro es peor que no verlos.
        //
        // Se consulta sólo para las cajas de esta página —son las que se
        // dibujan— y sólo las del día: la historia entera está en Movimientos.
        $movs = [];
        if (Permisos::puede('facturacion.movimientos')) {
            foreach ($rows as $c) {
                $movs[(int) $c->id_caja_fisica] = $this->movimientosDelDia((int) $c->id_caja_fisica);
            }
        }

        // **El desglose del arqueo de cada caja abierta**, para el modal que
        // abre la tarjeta. Sale de `vw_caja_resumen`, la misma fila que usa la
        // pantalla de la caja: así el modal de acá y el de allá no pueden
        // decir números distintos. Sólo de las abiertas de esta página.
        $resumen = [];
        foreach ($rows as $c) {
            if ($c->id_caja) {
                $fila = DB::selectOne('SELECT * FROM vw_caja_resumen WHERE id_caja = ?', [(int) $c->id_caja]);
                if ($fila) {
                    $resumen[(int) $c->id_caja_fisica] = $fila;
                }
            }
        }

        return view('facturacion.cajas', [
            'rows' => $rows,
            'movs' => $movs,
            'resumen' => $resumen,
            'f' => $f,
            'pag' => $pag,
            'sucursales' => $mias,
            'puedeCrear' => Permisos::esAdmin(),
        ]);
    }

    /**
     * Una caja: lo que hace falta para trabajar con ella, y nada más.
     *
     * **Acá no se listan las otras cajas.** La lista sirve para elegir; esta
     * pantalla, para operar la elegida.
     */
    public function cajaVer(int $id): View|RedirectResponse
    {
        $cajon = DB::selectOne(
            'SELECT cf.*, su.nombre AS sucursal FROM caja_fisica cf
               JOIN sucursal su ON su.id_sucursal = cf.id_sucursal
              WHERE cf.id_caja_fisica = ?', [$id]
        );

        $suyas = array_map(fn ($s) => (int) $s->id_sucursal, Sucursales::delUsuario());
        if (! $cajon || ! in_array((int) $cajon->id_sucursal, $suyas, true)) {
            flash('Esa caja no existe o no es de un local al que entres.', 'error');

            return redirect()->route('facturacion.cajas');
        }

        $abierta = DB::selectOne(
            "SELECT * FROM vw_caja_resumen WHERE id_caja_fisica = ? AND estado = 'Abierta'
              ORDER BY fecha_apertura DESC LIMIT 1", [$id]
        );

        // **El desglose por medio de pago se fue a Movimientos**, por pedido
        // del usuario: ahí es donde se mira qué pasó con la plata de una caja,
        // y respeta los mismos filtros. Acá quedaría contestando la mitad de
        // una pregunta que se hace en otra pantalla.
        //
        // Al cerrar no se pierde nada: el modal del arqueo tiene su propio
        // desglose completo —inicial, cobros, ingresos, egresos, pagos—.
        return view('facturacion.caja_ver', [
            'cajon' => $cajon,
            'abierta' => $abierta,
            'saldo' => $abierta ? Caja::saldo((int) $abierta->id_caja) : null,
            // **Los movimientos del día ya NO se traen acá.** El modal que los
            // mostraba estaba repetido: la tarjeta de la lista lo abre, y desde
            // esa misma tarjeta se entra a esta pantalla — el mismo botón dos
            // veces. Esta pantalla es el arqueo, así que enlaza a la historia
            // filtrada por este cajón en vez de volver a consultarla.
        ]);
    }

    /**
     * Alta y **edición** de cajones. Es del Administrador: define cómo cobra
     * el salón.
     *
     * **El nombre se puede corregir.** Se cargaba una vez y quedaba para
     * siempre: un «Caja 2» tipeado mal, o el cajón que pasó a llamarse «Mostrador»,
     * no tenían arreglo desde la pantalla. Y renombrarlo no toca ninguna
     * historia — el arqueo, los cobros y los egresos cuelgan del **id**, no
     * del nombre.
     *
     * **La sucursal sí queda fija al crearlo**: moverlo de local reescribiría
     * de dónde salió la plata de todas sus sesiones anteriores.
     */
    public function cajaFisicaGuardar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_caja_fisica', 0);
        $nombre = trim((string) $request->input('nombre', ''));
        $suc = (int) $request->input('id_sucursal', 0);
        $suyas = array_map(fn ($s) => (int) $s->id_sucursal, Sucursales::delUsuario());

        $cf = $id ? DB::selectOne('SELECT * FROM caja_fisica WHERE id_caja_fisica = ?', [$id]) : null;
        if ($id && (! $cf || ! in_array((int) $cf->id_sucursal, $suyas, true))) {
            flash('Esa caja no existe.', 'error');

            return back();
        }

        $error = match (true) {
            mb_strlen($nombre) < 2 => 'Escribí un nombre para la caja.',
            ! $id && ! in_array($suc, $suyas, true) => 'Elegí una sucursal a la que tengas acceso.',
            default => null,
        };

        if ($error) {
            flash($error, 'error');

            return back();
        }

        try {
            if ($cf) {
                DB::update('UPDATE caja_fisica SET nombre = ? WHERE id_caja_fisica = ?', [$nombre, $id]);
            } else {
                DB::insert('INSERT INTO caja_fisica (id_sucursal, nombre) VALUES (?, ?)', [$suc, $nombre]);
            }
        } catch (QueryException $e) {
            flash(Bd::traducir($e, [
                'uq_caja_fisica' => 'Ya hay una caja con ese nombre en esa sucursal.',
            ], $cf ? 'No se pudo cambiar el nombre.' : 'No se pudo crear la caja.'), 'error');

            return back();
        }

        if ($cf) {
            // De cuánto a cuánto, que es lo que sirve dentro de tres meses.
            Auditoria::registrar('EDICION', 'Facturacion', 'caja_fisica', $id,
                'Caja renombrada: de «' . $cf->nombre . '» a «' . $nombre . '»');
            flash('La caja ahora se llama «' . $nombre . '».');

            return redirect()->route('facturacion.cajas');
        }

        $id = (int) DB::scalar('SELECT LAST_INSERT_ID()');
        Auditoria::registrar('ALTA', 'Facturacion', 'caja_fisica', $id, $nombre);
        flash('Caja «' . $nombre . '» creada.');

        return redirect()->route('facturacion.cajas');
    }

    /**
     * Borrar un cajón — **sólo si nunca se abrió**.
     *
     * La baja existe para el cajón que operó y se deja de usar: su historial
     * lo nombra, así que quitarlo rompería el arqueo. Pero el que se creó por
     * error hace dos minutos no tiene nada colgando, y darlo de baja lo deja
     * ahí para siempre ocupando lugar en la lista.
     *
     * El corte es objetivo y no una opinión: **si tiene alguna sesión de caja,
     * no se borra**. De ahí cuelga todo lo demás —arqueos, cobros, egresos—
     * así que sin sesiones no hay historia que romper.
     */
    public function cajaFisicaBorrar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_caja_fisica');
        $cf = DB::selectOne('SELECT * FROM caja_fisica WHERE id_caja_fisica = ?', [$id]);
        $suyas = array_map(fn ($s) => (int) $s->id_sucursal, Sucursales::delUsuario());

        if (! $cf || ! in_array((int) $cf->id_sucursal, $suyas, true)) {
            flash('Esa caja no existe.', 'error');

            return back();
        }

        $sesiones = (int) DB::scalar('SELECT COUNT(*) FROM caja WHERE id_caja_fisica = ?', [$id]);
        if ($sesiones > 0) {
            flash('Esa caja ya se usó (' . $sesiones . ' apertura(s)): su historial la nombra, '
                . 'así que no se puede borrar. Dale de baja y queda fuera de la lista sin perder el arqueo.', 'warning');

            return back();
        }

        DB::delete('DELETE FROM caja_fisica WHERE id_caja_fisica = ?', [$id]);
        Auditoria::registrar('BAJA', 'Facturacion', 'caja_fisica', $id,
            'Caja «' . $cf->nombre . '» borrada (nunca se abrió)');
        flash('Caja «' . $cf->nombre . '» borrada.');

        return redirect()->route('facturacion.cajas');
    }

    public function cajaFisicaBaja(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_caja_fisica');
        $cf = DB::selectOne('SELECT * FROM caja_fisica WHERE id_caja_fisica = ?', [$id]);
        $suyas = array_map(fn ($s) => (int) $s->id_sucursal, Sucursales::delUsuario());

        if (! $cf || ! in_array((int) $cf->id_sucursal, $suyas, true)) {
            flash('Esa caja no existe.', 'error');

            return back();
        }

        // **No se da de baja con la sesión abierta**: quedaría plata adentro de
        // un cajón que el sistema dejó de ofrecer, y nadie podría cerrarlo.
        if ($cf->activo && DB::scalar('SELECT COUNT(*) FROM caja WHERE id_caja_fisica = ? AND id_estado_caja = 1', [$id])) {
            flash('Esa caja está abierta. Cerrala antes de darla de baja.', 'warning');

            return back();
        }

        DB::update('UPDATE caja_fisica SET activo = 1 - activo WHERE id_caja_fisica = ?', [$id]);
        Auditoria::registrar($cf->activo ? 'BAJA' : 'ALTA', 'Facturacion', 'caja_fisica', $id, $cf->nombre);
        flash($cf->activo ? 'Caja dada de baja. Su historial queda.' : 'Caja habilitada de nuevo.');

        return redirect()->route('facturacion.cajas');
    }

    /**
     * Arqueos: cómo cerró cada caja, con filtros y paginación.
     *
     * **Es una tabla, no tarjetas.** Un salón acumula un arqueo por cajón y
     * por día, así que a los seis meses son cientos: lo que hace falta es
     * poder filtrar y paginar, no que cada uno ocupe más lugar.
     *
     * Las cuatro cifras de arriba salen de **lo filtrado**, no del total: si
     * se pide un local y un mes, «cuántas cuadraron» tiene que hablar de ese
     * local y ese mes — un resumen que mide otra cosa que la tabla es peor que
     * no tenerlo.
     */
    public function arqueo(): View
    {
        $mias = Sucursales::delUsuario();
        $opSuc = ['' => 'Todas'];
        foreach ($mias as $su) {
            $opSuc[(string) $su->id_sucursal] = $su->nombre;
        }

        $opCaja = ['' => 'Todas'];
        foreach (Caja::cajones(count($mias) === 1 ? (int) $mias[0]->id_sucursal : null) as $cf) {
            $opCaja[(string) $cf->id_caja_fisica] = $cf->nombre
                . (count($mias) > 1 ? ' · ' . $cf->sucursal : '');
        }

        $campos = [];
        if (count($mias) > 1) {
            $campos['sucursal'] = ['tipo' => 'select', 'etiqueta' => 'Sucursal',
                                   'opciones' => $opSuc, 'ancho' => '180px'];
        }
        $f = Listado::filtros($campos + [
            'caja' => ['tipo' => 'select', 'etiqueta' => 'Caja', 'opciones' => $opCaja, 'ancho' => '180px'],
            'desde' => ['tipo' => 'fecha', 'etiqueta' => 'Desde'],
            'hasta' => ['tipo' => 'fecha', 'etiqueta' => 'Hasta'],
            'estado' => ['tipo' => 'select', 'etiqueta' => 'Resultado', 'ancho' => '170px',
                         'opciones' => ['' => 'Todos', 'ok' => 'Cuadraron',
                                        'no' => 'No cuadraron', 'sin' => 'Sin conteo']],
        ]);

        $w = ['fecha_cierre IS NOT NULL'];
        $par = [];

        // Quien tiene un solo local no elige: se filtra solo, igual que en
        // Reportes. Con el consolidado vería lo que el aislamiento impide.
        if (Listado::hay($f, 'sucursal')) {
            $w[] = 'id_sucursal = :suc';
            $par['suc'] = (int) Listado::valor($f, 'sucursal');
        } elseif (count($mias) === 1) {
            $w[] = 'id_sucursal = :suc';
            $par['suc'] = (int) $mias[0]->id_sucursal;
        } else {
            $ids = array_map(fn ($su) => (int) $su->id_sucursal, $mias);
            $w[] = 'id_sucursal IN (' . implode(',', $ids ?: [0]) . ')';
        }

        if (Listado::hay($f, 'caja')) {
            $w[] = 'id_caja_fisica = :cf';
            $par['cf'] = (int) Listado::valor($f, 'caja');
        }
        if (Listado::hay($f, 'desde')) {
            $w[] = 'DATE(fecha_cierre) >= :d';
            $par['d'] = Listado::valor($f, 'desde');
        }
        if (Listado::hay($f, 'hasta')) {
            $w[] = 'DATE(fecha_cierre) <= :h';
            $par['h'] = Listado::valor($f, 'hasta');
        }

        // Menos de un guaraní es cuadrar: la columna tiene dos decimales y
        // comparar contra 0 exacto haría saltar un redondeo como faltante.
        $est = (string) Listado::valor($f, 'estado');
        if ($est === 'ok') {
            $w[] = 'monto_contado IS NOT NULL AND ABS(diferencia) < 0.01';
        } elseif ($est === 'no') {
            $w[] = 'monto_contado IS NOT NULL AND ABS(diferencia) >= 0.01';
        } elseif ($est === 'sin') {
            $w[] = 'monto_contado IS NULL';
        }

        $desde = 'FROM vw_caja_resumen WHERE ' . implode(' AND ', $w);

        // El resumen sale de LO FILTRADO, con una consulta aparte: contarlo
        // sobre la página daría los números de veinte filas.
        $r = DB::selectOne(
            "SELECT COUNT(*) AS cerradas,
                    SUM(monto_contado IS NULL) AS sin_conteo,
                    SUM(monto_contado IS NOT NULL AND ABS(diferencia) < 0.01) AS cuadran,
                    COALESCE(SUM(CASE WHEN monto_contado IS NOT NULL AND ABS(diferencia) >= 0.01
                                      THEN diferencia ELSE 0 END), 0) AS dif_total
               $desde", $par
        );

        $pag = Listado::paginacion((int) $r->cerradas);

        return view('facturacion.arqueo', [
            'rows' => DB::select("SELECT * $desde ORDER BY fecha_cierre DESC
                                  LIMIT {$pag['porPagina']} OFFSET {$pag['offset']}", $par),
            'f' => $f,
            'pag' => $pag,
            'cerradas' => (int) $r->cerradas,
            'sinConteo' => (int) $r->sin_conteo,
            'cuadran' => (int) $r->cuadran,
            'difTotal' => (float) $r->dif_total,
        ]);
    }

    /**
     * Un movimiento de efectivo cargado a mano: el gasto de caja chica, el
     * retiro del dueño, la plata que se pone para dar cambio.
     *
     * **Es lo que faltaba de CJ-02.** `fn_caja_saldo` resta `movimiento_caja`
     * desde siempre y esa tabla **no la escribía nadie**: cero filas en los 90
     * días de la primera simulación, y en los 60 de la segunda sólo la
     * escribía la nota de crédito. O sea que el gasto real del mostrador —el
     * delivery, el taxi, la plata que se saca para el cambio— quedaba fuera
     * del arqueo y el cierre no cuadraba sin que se supiera por qué.
     */
    /**
     * Un movimiento de efectivo, con el respaldo que le corresponda.
     *
     * **Antes esto pedía tipo, monto y un texto libre**, así que quien tuviera
     * la clave sacaba cualquier monto escribiendo «varios». Fiscalmente no se
     * sostiene: el dinero no entra ni sale de la nada.
     *
     * Y metía en la misma bolsa tres cosas que NO son lo mismo:
     *
     *  · **el gasto** —taxi, delivery, insumos— que tiene factura y ahora la
     *    exige: número de comprobante, RUC de quien la emitió y la foto;
     *  · **el retiro de la propietaria**, que no es un gasto sino retiro de
     *    utilidades — pero **también se factura**: ella tiene su propio RUC y su
     *    propio timbrado (el salón emite con el punto 001-001 y ella con el
     *    001-002), así que le factura al salón por lo que retira;
     *  · **el fondo de cambio**, que no es ni una cosa ni la otra — es plata
     *    que sale y vuelve, y por eso tiene su par de tipos.
     *
     * El signo lo pone el TIPO, no un `<select>` aparte: un gasto no puede ser
     * un ingreso, y dejarlo elegir invitaba a cargar un egreso como entrada.
     */
    public function movimientoCaja(Request $request): RedirectResponse
    {
        $cajaFiltro = (int) $request->input('caja', 0);
        $volver = $cajaFiltro
            ? redirect()->route('facturacion.movimientos', ['caja' => $cajaFiltro])
            : redirect()->route('facturacion.movimientos');
        $idTipo = (int) $request->input('id_tipo_mov_caja', 0);
        $monto = num($request->input('monto'));
        $concepto = trim((string) $request->input('concepto', ''));
        $nroComp = trim((string) $request->input('nro_comprobante', ''));
        $ruc = strtoupper(trim((string) $request->input('ruc_emisor', '')));

        $caja = $this->exigeCaja('cargar un movimiento de caja');
        if ($caja instanceof RedirectResponse) {
            return $caja;
        }

        $t = DB::selectOne(
            'SELECT id_tipo_mov_caja, nombre, signo, exige_documento
               FROM tipo_movimiento_caja WHERE id_tipo_mov_caja = ? AND activo = 1', [$idTipo]
        );

        // **La devolución no se tipea: se elige la nota y el monto sale de ella.**
        // Emitir la nota y devolver la plata son dos actos, y antes el primero
        // escribía el egreso solo mientras el segundo dejaba cargar otro a mano:
        // dos salidas por la misma devolución, con montos distintos si quien la
        // cargaba escribía otro número. Acá el monto lo pone el documento.
        $esDevolucion = $t && str_starts_with($t->nombre, 'Devolución');
        $nota = null;
        if ($esDevolucion) {
            $idNota = (int) $request->input('id_factura', 0);
            $nota = collect($this->notasPorDevolver())->firstWhere('id_factura', $idNota);

            if (! $nota) {
                flash('Elegí la nota de crédito que estás devolviendo. '
                    . 'Si no está en la lista, es que ya se devolvió o es de otra sucursal.', 'error');

                return $volver->withInput();
            }
            if ((float) $nota->en_efectivo <= 0) {
                flash('Esa venta no se pagó en efectivo, así que no sale nada del cajón: '
                    . 'la devolución va por el mismo medio con el que pagó.', 'error');

                return $volver->withInput();
            }

            $monto = (float) $nota->en_efectivo;
            $concepto = $concepto ?: ('Devolución por ' . $nota->nro . ' a ' . $nota->cliente);
            $nroComp = $nota->nro;
        }

        $error = null;
        if (! $t) {
            $error = 'Elegí qué clase de movimiento es.';
        } elseif ($monto <= 0) {
            $error = 'El monto tiene que ser mayor a cero.';
        } elseif ($concepto === '') {
            $error = 'Escribí el concepto: es lo único que explica ese movimiento al cerrar la caja.';
        } elseif (mb_strlen($concepto) > 150) {
            $error = 'El concepto no puede pasar de 150 caracteres.';
        } elseif ($t->exige_documento && $nroComp === '') {
            $error = 'Un gasto necesita el número del comprobante: sin él no hay cómo respaldarlo.';
        } elseif ($t->exige_documento && $ruc === '') {
            $error = 'Poné el RUC o la cédula de quien emitió el comprobante.';
        } elseif ($t->exige_documento && ! $this->documentoValido($ruc)) {
            $error = 'Ese RUC no es válido: revisá el número y el dígito verificador.';
        }
        if ($error) {
            flash($error, 'error');

            return $volver->withInput();
        }

        $tipo = $t->signo === 'E' ? 'INGRESO' : 'EGRESO';

        // **No se saca del cajón lo que no está**, la misma regla que ya tenían
        // el pago a proveedores y la liquidación al personal.
        if ($tipo === 'EGRESO') {
            $enCaja = Caja::saldo((int) $caja->id_caja);
            if ($monto > $enCaja + 0.01) {
                flash('En la caja hay ' . money($enCaja) . ' en efectivo y querés sacar ' . money($monto)
                    . '. Registrá primero el ingreso o cargá un monto menor.', 'error');

                return $volver->withInput();
            }
        }

        // La foto del ticket. Es obligatoria para el gasto: el número suelto se
        // puede escribir de memoria, el papel no.
        $archivo = null;
        if ($request->hasFile('archivo')) {
            $archivo = $this->guardarRespaldo($request->file('archivo'), 'mov');
            if ($archivo === false) {
                flash('El comprobante tiene que ser una imagen (PNG, JPG o WEBP) o un PDF de hasta 3 MB.', 'error');

                return $volver->withInput();
            }
        }
        if ($t->exige_documento && ! $archivo) {
            flash('Adjuntá la foto del comprobante. Es lo que respalda que esa plata salió por algo.', 'error');

            return $volver->withInput();
        }

        try {
            DB::insert(
                'INSERT INTO movimiento_caja
                    (id_caja, id_tipo_mov_caja, id_factura, tipo, monto, concepto,
                     nro_comprobante, ruc_emisor, archivo, id_usuario)
                 VALUES (?,?,?,?,?,?,?,?,?,?)',
                [(int) $caja->id_caja, (int) $t->id_tipo_mov_caja,
                 $nota ? (int) $nota->id_factura : null,
                 $tipo, $monto, $concepto,
                 $nroComp ?: null, $ruc ?: null, $archivo, (int) session('uid')]
            );

            Auditoria::registrar('MOVIMIENTO_CAJA', 'Facturacion', 'movimiento_caja',
                (int) DB::getPdo()->lastInsertId(),
                $t->nombre . ' de ' . money($monto) . ' — ' . $concepto
                . ($nroComp ? ' · comp. ' . $nroComp . ' (' . $ruc . ')' : ''));

            flash($t->nombre . ' de ' . money($monto)
                . ' registrado. En la caja quedan ' . money(Caja::saldo((int) $caja->id_caja)) . '.');
        } catch (Throwable $ex) {
            flash('No se pudo registrar el movimiento. El detalle quedó registrado.', 'error');
            Log::error('Movimiento de caja', ['caja' => (int) $caja->id_caja, 'error' => $ex->getMessage()]);
        }

        return $volver;
    }

    /**
     * En qué local se emitió la factura.
     *
     * **La factura lo guarda desde la 7.49.0 y el timbrado queda de respaldo**:
     * un local sin timbrado propio numera con el de otra sede, así que deducirlo
     * del timbrado solo mandaría la devolución al cajón equivocado.
     */
    private function sucursalDeFactura(int $idFactura): int
    {
        return (int) DB::scalar(
            'SELECT COALESCE(f.id_sucursal, t.id_sucursal)
               FROM factura f
               LEFT JOIN timbrado t ON t.id_timbrado = f.id_timbrado
              WHERE f.id_factura = ?', [$idFactura]
        );
    }

    /**
     * Cuánto de esa venta se cobró EN EFECTIVO, que es lo único que sale del cajón.
     *
     * Lo que la clienta pagó con tarjeta o transferencia se le devuelve por el
     * mismo camino y el arqueo no se toca.
     *
     * **Se resuelve en un solo lugar a propósito**: lo consultan la pantalla
     * —para decidir si pide la caja— y el guardado —para escribir el egreso—, y
     * escrito dos veces uno de los dos se queda atrás y el modal termina
     * pidiendo una caja para un egreso que no ocurre, o al revés.
     */
    private function efectivoDevolvible(int $idFactura): float
    {
        return (float) DB::scalar(
            "SELECT COALESCE(SUM(co.monto),0)
               FROM cobro co
               JOIN metodo_pago mp ON mp.id_metodo_pago = co.id_metodo_pago
              WHERE co.id_estado_cobro = 1 AND mp.tipo = 'EFECTIVO'
                AND (co.id_factura = :f1
                     OR co.id_cita = (SELECT id_cita FROM factura WHERE id_factura = :f2))",
            ['f1' => $idFactura, 'f2' => $idFactura]
        );
    }

    /**
     * Notas de crédito emitidas y todavía sin devolver, de este local.
     *
     * El monto que se devuelve **en efectivo** es lo que la clienta había
     * pagado en efectivo: lo que pagó con tarjeta o transferencia se le
     * devuelve por el mismo camino y no toca el cajón, igual que al entrar.
     */
    private function notasPorDevolver(): array
    {
        return DB::select(
            "SELECT nc.id_factura, fn_factura_nro(nc.id_factura) AS nro,
                    fn_factura_total(nc.id_factura) AS total,
                    CONCAT(pe.nombre,' ',pe.apellido) AS cliente,
                    (SELECT COALESCE(SUM(co.monto),0)
                       FROM cobro co
                       JOIN metodo_pago mp ON mp.id_metodo_pago = co.id_metodo_pago
                      WHERE co.id_estado_cobro = 1 AND mp.tipo = 'EFECTIVO'
                        AND (co.id_factura = nc.id_factura_origen
                             OR co.id_cita = (SELECT id_cita FROM factura
                                               WHERE id_factura = nc.id_factura_origen))) AS en_efectivo
               FROM factura nc
               JOIN timbrado t   ON t.id_timbrado = nc.id_timbrado
               JOIN cliente cl   ON cl.id_cliente = nc.id_cliente
               JOIN persona pe   ON pe.id_persona = cl.id_persona
              WHERE nc.id_tipo_comprobante = 5
                AND nc.id_estado_factura = 1
                AND (:s = 0 OR t.id_sucursal = :s2)
                AND NOT EXISTS (SELECT 1 FROM movimiento_caja mc
                                 WHERE mc.id_factura = nc.id_factura AND mc.activo = 1)
              ORDER BY nc.fecha_emision DESC LIMIT 50",
            ['s' => Sucursales::activa(), 's2' => Sucursales::activa()]
        );
    }

    /**
     * ¿Sirve ese RUC o esa cédula? La cédula es numérica; el RUC lleva su
     * dígito verificador, que se comprueba con el mismo módulo 11 del SIFEN —
     * el mismo que evita el rechazo 1309 de la DNIT.
     */
    private function documentoValido(string $doc): bool
    {
        $doc = trim($doc);

        if (str_contains($doc, '-')) {
            // El RUC puede terminar en cualquier dígito verificador válido;
            // no se debe confundir el ejemplo histórico «…-8» con una regla.
            return Sifen::rucValido($doc);
        }

        // Sin guion se acepta como cédula: no todo el mundo tiene RUC.
        return (bool) preg_match('/^\d{3,10}$/', $doc);
    }

    /**
     * Guarda el respaldo de un movimiento. Devuelve el nombre, o false si el
     * archivo no sirve.
     *
     * **Fuera de `public/`**, igual que el comprobante de la seña: es
     * documentación de plata y no tiene por qué quedar colgando de una URL.
     * Se mira el contenido, no la extensión.
     */
    private function guardarRespaldo(mixed $archivo, string $prefijo): string|false
    {
        if (! $archivo || ! $archivo->isValid() || $archivo->getSize() > 3 * 1024 * 1024) {
            return false;
        }

        $info = @getimagesize($archivo->getRealPath());
        $tipos = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];
        $esPdf = str_starts_with((string) @file_get_contents($archivo->getRealPath(), false, null, 0, 5), '%PDF-');

        if (! $esPdf && (! $info || ! isset($tipos[$info[2]]))) {
            return false;
        }

        $nombre = $prefijo . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.'
            . ($esPdf ? 'pdf' : $tipos[$info[2]]);
        try {
            $archivo->move(storage_path('app/respaldos'), $nombre);
        } catch (Throwable $e) {
            Log::error('No se pudo guardar el respaldo: ' . $e->getMessage());

            return false;
        }

        return $nombre;
    }

    public function abrirCaja(Request $request): RedirectResponse
    {
        $idCajon = (int) $request->input('id_caja_fisica', 0);
        // Se vuelve a la LISTA: la apertura se hace desde la tarjeta desde que
        // el formulario vive en un modal, y la tarjeta ya muestra la caja
        // abierta con su saldo. Mandar a la pantalla de la caja era el «doble
        // paso» que se reportó.
        $volver = redirect()->route('facturacion.cajas');

        // **Ya no se pregunta «¿hay alguna caja abierta?»**: con varios cajones
        // eso no impide nada — lo que importa es si ESTE está abierto, y de eso
        // se encarga `trg_caja_bi`, que es donde no hay carrera posible.
        $cajon = DB::selectOne('SELECT * FROM caja_fisica WHERE id_caja_fisica = ? AND activo = 1', [$idCajon]);
        $suyas = array_map(fn ($su) => (int) $su->id_sucursal, Sucursales::delUsuario());

        if (! $cajon || ! in_array((int) $cajon->id_sucursal, $suyas, true)) {
            flash('Elegí una caja de un local al que entres.', 'error');

            return redirect()->route('facturacion.cajas');
        }

        $monto = num($request->input('monto_inicial'));
        if ($monto < 0) {
            flash('El monto inicial no puede ser negativo.', 'error');

            return $volver;
        }

        try {
            $idCaja = Caja::abrir((int) session('uid'), $monto, $idCajon,
                trim((string) $request->input('observacion', '')));
            Auditoria::registrar('CAJA_APERTURA', 'Facturacion', 'caja', $idCaja, 'Apertura con ' . money($monto));
            flash('Caja abierta con ' . money($monto) . '.');
        } catch (QueryException $e) {
            // El `if` de arriba mira y después inserta, así que dos aperturas a
            // la vez lo pasaban las dos: en la simulación quedaron 6 pares de
            // cajas solapadas y ningún cierre cuadraba. Ahora la regla la hace
            // cumplir `trg_caja_bi`, que es donde no hay carrera posible, y acá
            // sólo se traduce lo que contesta.
            flash(Bd::traducir($e, [
                'caja abierta' => 'Otra persona abrió la caja recién. Trabajen sobre esa.',
            ], 'No se pudo abrir la caja. El detalle quedó registrado.'), 'warning');
            Log::error('No se pudo abrir la caja', ['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            flash('No se pudo abrir la caja. El detalle quedó registrado.', 'error');
            Log::error('No se pudo abrir la caja', ['error' => $e->getMessage()]);
        }

        return $volver;
    }

    public function cerrarCaja(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_caja', 0);
        $volver = redirect()->route('facturacion.cajas');

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

        // **Sin conteo no hay arqueo.** El campo es obligatorio en la pantalla
        // aunque la columna admita NULL: eso último es para las cajas que se
        // cerraron antes de que esto existiera, donde un 0 sería mentir —no se
        // distinguiría de un arqueo que cuadró exacto.
        if (trim((string) $request->input('monto_contado', '')) === '') {
            flash('Contá el efectivo del cajón y escribí cuánto hay: eso es el arqueo.', 'error');

            return $volver;
        }

        $contado = num($request->input('monto_contado'));
        if ($contado < 0) {
            flash('El dinero contado no puede ser negativo.', 'error');

            return $volver;
        }

        $obsCierre = trim((string) $request->input('observacion', ''));
        $motivo = trim((string) $request->input('motivo_diferencia', ''));

        // **Una diferencia sin motivo es un número y nada más.** Es lo único
        // que convierte un faltante en algo sobre lo que se puede hacer algo:
        // al día siguiente nadie se acuerda de qué pasó. Se pide sólo cuando
        // la hay — obligarlo siempre haría escribir «ok» todos los días, que
        // es peor que no pedirlo.
        $difPrevia = $contado - (float) $caja->saldo;
        if (abs($difPrevia) >= 0.01 && $motivo === '') {
            flash('La caja no cuadra por ' . money(abs($difPrevia))
                . '. Escribí a qué se debe antes de cerrar: mañana nadie se va a acordar.', 'error');

            return $volver;
        }

        try {
            Caja::cerrar($id, $contado, (int) session('uid'), $obsCierre, $motivo);

            // La diferencia se lee DESPUÉS de cerrar, que es cuando el conteo
            // ya está guardado; y se calcula, no se guarda.
            $dif = Caja::diferencia($id);
            $detalle = 'Esperado ' . money($caja->saldo) . ' · contado ' . money($contado)
                . ' · ' . self::textoDiferencia($dif);
            Auditoria::registrar('CAJA_CIERRE', 'Facturacion', 'caja', $id, $detalle);

            // **El aviso dice si cuadró, que es lo único que se quiere saber.**
            flash('Caja cerrada. ' . $detalle, ($dif !== null && abs($dif) >= 0.01) ? 'warning' : 'exito');
        } catch (QueryException $e) {
            flash(Bd::traducir($e, [
                'ya estaba cerrada' => 'Otra persona la cerró recién.',
            ], 'No se pudo cerrar la caja. El detalle quedó registrado.'), 'warning');
            Log::error('No se pudo cerrar la caja', ['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            flash('No se pudo cerrar la caja. El detalle quedó registrado.', 'error');
            Log::error('No se pudo cerrar la caja', ['error' => $e->getMessage()]);
        }

        return $volver;
    }

    /** Cómo se nombra un sobrante, un faltante o un arqueo que cuadró. */
    private static function textoDiferencia(?float $dif): string
    {
        if ($dif === null) {
            return 'sin conteo';
        }
        // Menos de un guaraní es cuadrar: la columna tiene dos decimales y
        // comparar contra 0 exacto haría saltar un redondeo como faltante.
        if (abs($dif) < 0.01) {
            return 'la caja cuadra';
        }

        return $dif > 0
            ? 'SOBRAN ' . money($dif)
            : 'FALTAN ' . money(abs($dif));
    }

    // -----------------------------------------------------------------
    //  Pagos al personal
    // -----------------------------------------------------------------

    public function pagos(): View
    {
        // **El historial paginado, como el resto de las listas del sistema.**
        // Cortaba con `LIMIT 200` sin decirlo, que es peor que no paginar: a
        // partir de la fila 201 las liquidaciones dejaban de existir para
        // quien mira la pantalla.
        $f = Listado::filtros([
            'q' => ['tipo' => 'texto', 'etiqueta' => 'Buscar', 'ph' => 'Profesional o período', 'ancho' => '220px'],
            'estado' => ['tipo' => 'select', 'etiqueta' => 'Estado',
                         'opciones' => ['' => 'Todos', 'Pagado' => 'Pagado', 'Revertido' => 'Revertido']],
            'desde' => ['tipo' => 'fecha', 'etiqueta' => 'Desde'],
            'hasta' => ['tipo' => 'fecha', 'etiqueta' => 'Hasta'],
        ]);

        $w = ['1=1'];
        $par = [];
        if (Listado::hay($f, 'q')) {
            $w[] = Listado::likeVarias(['v.beneficiario', 'v.periodo'], Listado::valor($f, 'q'), 'q', $par);
        }
        if (Listado::hay($f, 'estado')) {
            $w[] = 'v.estado = :est';
            $par['est'] = Listado::valor($f, 'estado');
        }
        if (Listado::hay($f, 'desde')) {
            $w[] = 'DATE(v.fecha) >= :d';
            $par['d'] = Listado::valor($f, 'desde');
        }
        if (Listado::hay($f, 'hasta')) {
            $w[] = 'DATE(v.fecha) <= :h';
            $par['h'] = Listado::valor($f, 'hasta');
        }
        $desde = 'FROM vw_pago_personal_resumen v WHERE ' . implode(' AND ', $w);
        $pag = Listado::paginacion((int) DB::scalar("SELECT COUNT(*) $desde", $par));

        return view('facturacion.pagos', [
            'f' => $f,
            'pag' => $pag,
            'rows' => DB::select(
                "SELECT v.* $desde ORDER BY v.fecha DESC LIMIT {$pag['porPagina']} OFFSET {$pag['offset']}", $par),
            'profs' => DB::select(
                // **Cuánto se le debe, no sólo cuántos servicios.** La tabla
                // decía «3 pendientes» y el botón «Liquidar», así que había
                // que apretar para enterarse del monto — o sea, decidir un
                // pago sin ver la cifra. La comisión la calcula
                // `fn_comision_servicio`, que es la misma autoridad con la que
                // después se liquida: escrita de nuevo acá, las dos cuentas se
                // separarían.
                'SELECT u.id_usuario, pe_u.nombre, pe_u.apellido,
                        COALESCE(p.pendientes, 0) AS pendientes,
                        COALESCE(p.a_pagar, 0) AS a_pagar,
                        p.desde_cuando
                   FROM usuario u
                   JOIN persona pe_u ON pe_u.id_persona = u.id_persona
                   JOIN rol r ON r.id_rol = u.id_rol
                   LEFT JOIN (
                        SELECT sr.id_usuario,
                               COUNT(*) AS pendientes,
                               SUM(fn_comision_servicio(sr.id_servicio_realizado)) AS a_pagar,
                               MIN(sr.fecha_hora) AS desde_cuando
                          FROM servicio_realizado sr
                          LEFT JOIN detalle_pago_personal d
                                 ON d.id_servicio_realizado = sr.id_servicio_realizado
                         WHERE d.id_detalle_pago IS NULL
                         GROUP BY sr.id_usuario
                   ) p ON p.id_usuario = u.id_usuario
                  WHERE u.activo = 1 AND r.es_personal = 1
                  ORDER BY COALESCE(p.pendientes, 0) DESC, pe_u.nombre, pe_u.apellido'
            ),
            // Con qué se le paga: lo que sale en efectivo baja del cajón y lo
            // que sale por banco, no. Sin este dato el arqueo no cerraba.
            'metodos' => DB::select(
                "SELECT id_metodo_pago, nombre, tipo FROM metodo_pago
                  WHERE activo = 1 ORDER BY (tipo = 'EFECTIVO') DESC, nombre"
            ),
            // **De qué cajón sale la liquidación.** Hasta acá salía del que
            // devolviera `Caja::abierta()`, o sea el último abierto: con dos
            // puestos, el egreso caía en el arqueo de otra persona sin que
            // nada lo dijera.
            'cajas' => Caja::abiertasDe(),
            // **Y de qué CUENTA sale, cuando no sale del cajón.** Una
            // transferencia no toca la caja, así que el control del efectivo no
            // la miraba: se podía liquidar el mes entero contra una cuenta
            // vacía y enterarse cuando el banco rechazara la transferencia.
            'cuentasBanco' => Cuenta::deSucursal((int) Sucursales::activa()),
        ]);
    }

    /** Liquida los servicios realizados que todavía no se le pagaron. */
    public function pagarPersonal(Request $request): RedirectResponse
    {
        $idProf = (int) $request->input('id_usuario', 0);
        $periodo = trim((string) $request->input('periodo', '')) ?: date('m/Y');
        $idMetodo = (int) $request->input('id_metodo_pago', 0);
        $volver = redirect()->route('facturacion.pagos');

        if (! $idProf) {
            flash('Elegí un profesional.', 'error');

            return $volver;
        }
        if (! $idMetodo || ! DB::scalar('SELECT COUNT(*) FROM metodo_pago WHERE id_metodo_pago = ? AND activo = 1', [$idMetodo])) {
            flash('Elegí con qué le vas a pagar.', 'error');

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

        // **De qué cajón sale la plata lo elige quien liquida.** Con dos
        // abiertos, `Caja::abierta()` devuelve uno de los dos y el egreso caía
        // en el arqueo de otra persona sin que nada lo dijera — se descubre al
        // cerrar. **El id del POST no se cree**: `cajaElegida()` comprueba que
        // esa caja esté abierta y sea de un local al que esta persona entra.
        $idCaja = $this->cajaElegida($request, (int) $caja->id_caja);
        if ($idCaja instanceof RedirectResponse) {
            return $idCaja;
        }

        // Cuánto se le va a pagar, para poder comprobarlo ANTES de registrarlo.
        $monto = (float) DB::scalar(
            'SELECT COALESCE(SUM(fn_comision_servicio(sr.id_servicio_realizado)),0)
               FROM servicio_realizado sr
               LEFT JOIN detalle_pago_personal d ON d.id_servicio_realizado = sr.id_servicio_realizado
              WHERE sr.id_usuario = ? AND d.id_detalle_pago IS NULL', [$idProf]
        );

        // **En efectivo no se puede entregar plata que no está en el cajón**, la
        // misma regla que ya tenía el pago a proveedores. Hasta la 7.22.0 la
        // liquidación no pasaba por ningún control porque **no tocaba la caja
        // en absoluto**: se liquidaron Gs. 1.868.250 en 90 días sin que el
        // arqueo registrara un solo egreso.
        if (Caja::esEfectivo($idMetodo)) {
            // Contra el cajón ELEGIDO: mirar otro haría que el control no
            // signifique nada, que es el defecto que la 7.55.0 corrigió en el
            // pago a proveedores.
            $enCaja = Caja::saldo($idCaja);
            if ($monto > $enCaja + 0.01) {
                flash('En la caja hay ' . money($enCaja) . ' en efectivo y la liquidación es de '
                    . money($monto) . '. Pagale con otro medio o registrá primero el ingreso.', 'error');

                return $volver;
            }
        }

        // **Y si no sale del cajón, sale de una cuenta.** El id viaja en el
        // formulario, así que se valida contra el local — la misma regla que
        // `cajaElegida()`. En efectivo no se anota ninguna: de un cajón no sale
        // ninguna transferencia, y guardarla diría algo falso.
        $idCuenta = Caja::esEfectivo($idMetodo)
            ? 0
            : Cuenta::valida((int) $request->input('id_dato_pago', 0), (int) Sucursales::activa());

        // **Avisa, no impide.** `fn_cuenta_saldo` es un piso —el sistema conoce
        // lo que sale del banco, no lo que entra— así que bloquear con un
        // número que sabemos incompleto frenaría un pago legítimo. Con el
        // efectivo es al revés: ese saldo es exacto y por eso arriba sí rechaza.
        $avisoCuenta = $idCuenta ? Cuenta::aviso($idCuenta, $monto) : '';

        try {
            $idPago = Bd::idDe('sp_registrar_pago_personal',
                [$idProf, (int) session('uid'), $periodo, $idMetodo, $idCaja]);
            if ($idPago && $idCuenta) {
                DB::update('UPDATE pago_personal SET id_dato_pago = ? WHERE id_pago_personal = ?',
                    [$idCuenta, $idPago]);
            }
            Auditoria::registrar('PAGO_PERSONAL', 'Facturacion', 'pago_personal', $idPago,
                "Liquidación $periodo ($pend servicios) por " . money($monto));
            flash('Liquidación de ' . money($monto) . ' registrada.'
                . (Caja::esEfectivo($idMetodo) ? ' Se descontó del efectivo de la caja.' : ''));
            if ($avisoCuenta) {
                flash($avisoCuenta, 'warning');
            }
        } catch (Throwable $ex) {
            flash('No se pudo registrar el pago. El detalle quedó registrado.', 'error');
            Log::error('Liquidación al personal', ['profesional' => $idProf, 'error' => $ex->getMessage()]);
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
        // **La plata sale del cajón del LOCAL DE LA COMPRA**, no del local
        // donde está parada la persona: es lo que hace `sp_pagar_compra` desde
        // la 7.36.3. Por eso las cajas se buscan por compra y no una sola vez.
        $cuentas = DB::select(
            'SELECT v.*, c.id_sucursal, su.nombre AS sucursal
               FROM vw_cuenta_proveedor v
               JOIN compra c ON c.id_compra = v.id_compra
               JOIN sucursal su ON su.id_sucursal = c.id_sucursal
              WHERE v.saldo > 0 ORDER BY v.vencida DESC, v.vencimiento'
        );

        $cajasPorCompra = [];
        $bancosPorCompra = [];
        foreach ($cuentas as $c) {
            $cajasPorCompra[(int) $c->id_compra] = Caja::abiertasDe((int) $c->id_sucursal);
            // Las cuentas del banco del MISMO local, por el mismo motivo que
            // los cajones: la plata sale de donde se hizo la compra.
            $bancosPorCompra[(int) $c->id_compra] = Cuenta::deSucursal((int) $c->id_sucursal);
        }

        return view('facturacion.proveedores', [
            'cuentas' => $cuentas,
            'cajasPorCompra' => $cajasPorCompra,
            'bancosPorCompra' => $bancosPorCompra,
            // El monto no se guarda: lo calcula la función de la base
            'pagos' => DB::select(
                "SELECT pp.id_pago_proveedor, pp.fecha, pp.referencia,
                        fn_pago_proveedor_monto(pp.id_pago_proveedor) AS monto,
                        pe_pr.nombre AS proveedor, mp.nombre AS metodo, ep.nombre AS estado,
                        -- **A qué compra se aplicó.** El pago SÍ queda ligado
                        -- —`sp_pagar_compra` escribe `detalle_pago_proveedor`—
                        -- pero la lista no lo mostraba: se veía «pagué
                        -- Gs. 1.150.000 a Distribuidora» sin decir por cuál de
                        -- las cuatro compras, y con el proveedor repetido no
                        -- había forma de saberlo sin entrar a la base.
                        --
                        -- Un pago puede cubrir varias compras, así que se
                        -- concatenan: es una relación N:M y aplastarla a una
                        -- sola diría algo falso.
                        (SELECT GROUP_CONCAT(
                                    CONCAT(COALESCE(NULLIF(c2.nro_factura_proveedor, ''),
                                                    CONCAT('compra #', c2.id_compra)),
                                           ' (', DATE_FORMAT(c2.fecha, '%d/%m/%Y'), ')')
                                    ORDER BY c2.fecha SEPARATOR ' · ')
                           FROM detalle_pago_proveedor d2
                           JOIN compra c2 ON c2.id_compra = d2.id_compra
                          WHERE d2.id_pago_proveedor = pp.id_pago_proveedor) AS compras,
                        -- **La compra que todavía no tiene su número de factura.**
                        -- Una vez pagada desaparece de «Cuentas por pagar», así que
                        -- desde ahí ya no se le puede cargar el papel: el proveedor
                        -- muchas veces lo trae después, y quedaba sin forma de
                        -- vincularlo. Acá sí está, porque el pago no se va nunca.
                        (SELECT d3.id_compra
                           FROM detalle_pago_proveedor d3
                           JOIN compra c3 ON c3.id_compra = d3.id_compra
                          WHERE d3.id_pago_proveedor = pp.id_pago_proveedor
                            AND COALESCE(NULLIF(TRIM(c3.nro_factura_proveedor), ''), '') = ''
                          LIMIT 1) AS compra_sin_factura
                   FROM pago_proveedor pp
                   JOIN proveedor pr ON pr.id_proveedor = pp.id_proveedor
                   JOIN persona pe_pr ON pe_pr.id_persona = pr.id_persona
                   JOIN metodo_pago mp ON mp.id_metodo_pago = pp.id_metodo_pago
                   JOIN estado_pago_proveedor ep ON ep.id_estado_pago_proveedor = pp.id_estado_pago_proveedor
                  LEFT JOIN caja cj ON cj.id_caja = pp.id_caja
                  WHERE (:s = 0 OR cj.id_sucursal IS NULL OR cj.id_sucursal = :s2)
                  ORDER BY pp.fecha DESC LIMIT 100",
                ['s' => Sucursales::activa(), 's2' => Sucursales::activa()]
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

        // **El cajón del que sale la plata es el del LOCAL DE LA COMPRA**, no
        // el de donde está parada la persona. `sp_pagar_compra` lo resuelve
        // desde `compra.id_sucursal` (7.36.3) y acá se validaba contra el de la
        // sucursal activa: pagando desde el local A una compra del local B, el
        // control miraba el saldo de A y la plata salía del cajón de B. El
        // efecto es el que se reportó — un pago mayor al disponible que entra
        // sin quejarse— y es el mismo desajuste que la 7.36.3 corrigió del
        // lado del procedimiento, que quedó a medias del lado de la pantalla.
        // **Y de QUÉ cajón de ese local sale, lo elige quien paga.** Con dos
        // abiertos, tomar «el último» dejaba el egreso en el arqueo de otra
        // persona sin que nada lo dijera. El combo sólo aparece cuando hay más
        // de uno: con uno solo la pregunta no significa nada.
        $idCajaElegida = (int) $request->input('id_caja', 0);
        if ($idCajaElegida) {
            $valida = (int) DB::scalar(
                'SELECT COUNT(*) FROM caja k JOIN compra c ON c.id_compra = ?
                  WHERE k.id_caja = ? AND k.id_estado_caja = 1 AND k.id_sucursal = c.id_sucursal',
                [$idCompra, $idCajaElegida]
            );
            if (! $valida) {
                flash('Esa caja no está abierta o no es del local de la compra.', 'error');

                return $volver;
            }
        }

        $idCajaPago = $idCajaElegida ?: (int) (DB::scalar(
            'SELECT k.id_caja FROM compra c
               JOIN caja k ON k.id_sucursal = c.id_sucursal AND k.id_estado_caja = 1
              WHERE c.id_compra = ? ORDER BY k.id_caja DESC LIMIT 1', [$idCompra]
        ) ?: $caja->id_caja);

        // En efectivo no se puede entregar plata que no está en el cajón. Los
        // pagos por banco o tarjeta no se frenan: no salen del cajón, salen de
        // la cuenta (por eso `fn_caja_saldo` tampoco los resta).
        if (Caja::esEfectivo($idMetodo)) {
            $enCaja = Caja::saldo($idCajaPago);
            if ($monto > $enCaja + 0.01) {
                flash('En la caja hay ' . money($enCaja) . ' en efectivo y estás por pagar ' . money($monto)
                    . '. Pagá con otro medio, registrá primero el ingreso o pagá hasta ' . money($enCaja) . '.', 'error');

                return $volver;
            }
        }

        // **De qué CUENTA sale, cuando no sale del cajón.** El banco no tenía
        // ningún control: el comentario de arriba lo dice —«no salen del cajón,
        // salen de la cuenta»— y de la cuenta no se sabía nada. Se valida contra
        // el local DE LA COMPRA, igual que el cajón.
        $sucCompra = (int) DB::scalar('SELECT id_sucursal FROM compra WHERE id_compra = ?', [$idCompra]);
        $idCuenta = Caja::esEfectivo($idMetodo)
            ? 0
            : Cuenta::valida((int) $request->input('id_dato_pago', 0), $sucCompra);

        // Avisa y no impide: el saldo de la cuenta es un piso, no un dato
        // exacto. Ver `App\Servicios\Cuenta`.
        $avisoCuenta = $idCuenta ? Cuenta::aviso($idCuenta, $monto) : '';

        try {
            // La caja la elige quien paga cuando hay más de una abierta: sin
            // eso, el egreso salía del arqueo del cajón equivocado.
            $idPago = Bd::idDe('sp_pagar_compra',
                [$idCompra, $idMetodo, (int) session('uid'), $monto, $ref, $idCajaElegida ?: null]);
            if ($idPago && $idCuenta) {
                DB::update('UPDATE pago_proveedor SET id_dato_pago = ? WHERE id_pago_proveedor = ?',
                    [$idCuenta, $idPago]);
            }
            if ($idPago) {
                // Igual que en el cobro: el procedimiento busca la caja del
                // propio usuario, y la del salón puede haberla abierto otra persona.
                DB::update('UPDATE pago_proveedor SET id_caja = ? WHERE id_pago_proveedor = ? AND id_caja IS NULL',
                    [$idCajaPago, $idPago]);
            }
            // **El papel casi siempre llega con el pago.** Se acepta acá para
            // no obligar a entrar a la compra: una vez saldada sale de
            // «Cuentas por pagar» y desde ahí ya no se la alcanza.
            $nroFac = trim((string) $request->input('nro_factura_proveedor', ''));
            if ($nroFac !== '' && preg_match('/^[0-9][0-9-]{2,29}$/', $nroFac)) {
                DB::update("UPDATE compra SET nro_factura_proveedor = ?
                             WHERE id_compra = ? AND COALESCE(NULLIF(TRIM(nro_factura_proveedor), ''), '') = ''",
                    [$nroFac, $idCompra]);
            }

            Auditoria::registrar('PAGO_PROVEEDOR', 'Facturacion', 'compra', $idCompra, 'Pago ' . money($monto));
            flash('Pago al proveedor registrado por ' . money($monto) . '.');
            if ($avisoCuenta) {
                flash($avisoCuenta, 'warning');
            }
        } catch (Throwable $ex) {
            $msg = $ex->getMessage();
            flash(str_contains($msg, 'saldo') ? 'El monto supera el saldo pendiente de la compra.'
                : (str_contains($msg, 'confirmada') ? 'Solo se pueden pagar compras confirmadas.'
                    : 'No se pudo registrar el pago.'), 'error');
        }

        return $volver;
    }

    /**
     * A qué caja abierta va la plata: la que eligió la pantalla, o la de siempre.
     *
     * **Se valida que esté abierta y que sea de un local al que la persona
     * entra.** El id viaja en el formulario, así que se puede cambiar: sin esta
     * comprobación se podría meter un cobro en el arqueo de otra sucursal.
     */
    private function cajaElegida(Request $request, int $porDefecto): int|RedirectResponse
    {
        $id = (int) $request->input('id_caja', 0);
        if (! $id || $id === $porDefecto) {
            return $porDefecto;
        }

        $suyas = array_map(fn ($su) => (int) $su->id_sucursal, Sucursales::delUsuario());
        $ok = (int) DB::scalar(
            'SELECT COUNT(*) FROM caja WHERE id_caja = ? AND id_estado_caja = 1
              AND id_sucursal IN (' . implode(',', $suyas ?: [0]) . ')', [$id]
        );

        if (! $ok) {
            flash('Esa caja no está abierta o no es de un local al que entres.', 'error');

            return back();
        }

        return $id;
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
            // Solo dígitos: se rellena con ceros a la izquierda como pide la DNIT
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
            flash('No se pudo guardar el timbrado. Revisá que los datos cumplan el formato de la DNIT.', 'error');

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

}
