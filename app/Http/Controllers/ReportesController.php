<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Servicios\Listado;
use App\Servicios\Permisos;
use App\Servicios\Sucursales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Informes parametrizados, repartidos en seis pantallas.
 *
 * **Antes era una sola pantalla con todo adentro**: siete tablas y dos
 * gráficos apilados, 2.600 px de alto, y para mirar una cosa había que pasar
 * por las otras seis. Un informe que muestra todo junto no se lee: se hojea.
 *
 * Ahora hay un **Resumen** —lo que se mira todos los días— y cinco informes
 * especializados a los que se entra cuando hace falta el detalle. La navegación
 * es de pestañas, que es lo que ya usa el sistema en otros lados y no trae nada
 * nuevo.
 *
 * `datos()` sigue siendo una sola función para las tres salidas —pantalla,
 * papel y planilla—: si cada una armara su consulta, el PDF podría no coincidir
 * con lo que se vio, que es justamente lo que un informe no puede hacer.
 */
class ReportesController extends Controller
{
    /**
     * Las pantallas del módulo. La clave viaja en `?r=`.
     *
     * El orden es el de las pestañas y también el del informe impreso completo.
     */
    public const SECCIONES = [
        'resumen' => ['Resumen', 'speedometer2', 'Lo que hay que mirar todos los días'],
        'citas' => ['Citas', 'calendar-check', 'Estados, y a qué hora y qué día se llena'],
        'servicios' => ['Servicios', 'scissors', 'Qué se hace más y cuánto deja'],
        'equipo' => ['Profesionales', 'people', 'Qué hizo cada una y cuánto generó'],
        'ingresos' => ['Ingresos', 'cash-coin', 'De dónde viene la plata'],
        'compras' => ['Compras', 'truck', 'Proveedores y lo que se les debe'],
        'sucursales' => ['Por sucursal', 'shop', 'Los locales, uno al lado del otro'],
    ];

    /**
     * Los bloques que se pueden imprimir, cada uno con su casilla.
     *
     * Se probó con un `<select>` de un solo bloque, pero eso obliga a elegir
     * **uno**: con casillas se arman las combinaciones que hagan falta —el
     * resumen y el equipo, por ejemplo— que es lo que se pide de verdad.
     */
    public const BLOQUES = [
        'resumen' => 'Resumen del período',
        'servicios' => 'Servicios más solicitados',
        'demanda' => 'Demanda por hora y por día',
        'medios' => 'Medios de pago',
        'equipo' => 'El equipo',
        'sucursales' => 'Por sucursal',
        'prov' => 'Deuda con proveedores',
    ];

    public function index(Request $request): View|StreamedResponse
    {
        $f = $this->rango();
        $seccion = (string) $request->query('r', 'resumen');
        if (! isset(self::SECCIONES[$seccion])) {
            $seccion = 'resumen';
        }

        $datos = $this->datos($f);

        // **La planilla sale de los mismos datos que la pantalla.** Es lo que
        // garantiza que el Excel diga lo mismo que se estaba mirando: si se
        // rearmara la consulta, un filtro olvidado bastaría para que no
        // coincidieran y nadie se daría cuenta hasta comparar a mano.
        // **Sólo Excel.** La planilla se abre igual en cualquier programa de
        // hojas de cálculo y trae las barras al lado de cada número.
        if ((string) $request->query('export', '') === 'xls') {
            return $this->exportar($seccion, $datos, 'xls');
        }

        // **Con un solo local, «Por sucursal» no se ofrece.** No hay nada que
        // comparar y la pestaña llevaría siempre al mismo aviso; es el mismo
        // criterio con el que el resto del sistema esconde lo de sucursales
        // cuando hay una sola.
        $secciones = self::SECCIONES;
        if (count($this->misSucursales()) < 2) {
            unset($secciones['sucursales']);
            if ($seccion === 'sucursales') {
                $seccion = 'resumen';
            }
        }

        $datos['seccion'] = $seccion;
        $datos['secciones'] = $secciones;

        return view('reportes.index', $datos);
    }

    /**
     * Informe listo para papel: sin barra de módulos ni pie, maquetado para A4.
     *
     * «Descargar PDF» genera el documento del lado del servidor para que el
     * navegador lo baje directamente, con los mismos filtros y bloques elegidos.
     */
    public function imprimir(Request $request): Response
    {
        $f = $this->rango();
        $datos = $this->datos($f);

        // **El emisor es el del local que se está mirando**, no el primero de
        // la lista: con el filtro puesto en una sucursal, el papel tiene que
        // decir de qué local salió.
        $suc = Listado::valor($f, 'suc');
        $datos['emisor'] = ($suc !== '' ? DB::selectOne(
            'SELECT nombre, ruc, telefono, direccion, ciudad FROM sucursal WHERE id_sucursal = ?', [(int) $suc]
        ) : null) ?: DB::selectOne(
            'SELECT nombre, ruc, telefono, direccion, ciudad FROM sucursal
              WHERE activo = 1 ORDER BY id_sucursal LIMIT 1'
        ) ?: (object) ['nombre' => config('app.name')];

        $datos['emitido'] = ahora_bd('d/m/Y H:i');
        $datos['porQuien'] = (string) session('nombre', '');

        // Qué bloques se imprimen. Se sanea contra las claves que existen, así
        // que lo que venga inventado en la URL se descarta; y si no queda
        // ninguno se imprime todo: **nunca se devuelve una hoja en blanco**.
        $elegidos = array_values(array_intersect(
            array_map('strval', (array) $request->query('bloques', [])),
            array_keys(self::BLOQUES)
        ));
        if (! $elegidos) {
            $elegidos = array_keys(self::BLOQUES);
        }

        $datos['bloques'] = $elegidos;
        $datos['bloqueNombre'] = count($elegidos) === count(self::BLOQUES)
            ? 'Informe completo'
            : implode(' · ', array_map(fn ($b) => self::BLOQUES[$b], $elegidos));
        $datos['ver'] = fn (string $cual) => in_array($cual, $elegidos, true);
        $datos['pdf'] = true;

        $html = view('reportes.imprimir', $datos)->render();
        $opciones = new Options();
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($opciones);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="informe_' . $datos['desde'] . '_' . $datos['hasta'] . '.pdf"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    // -----------------------------------------------------------------
    //  Filtros
    // -----------------------------------------------------------------

    /** Rango por defecto: el mes en curso, que es lo que se mira casi siempre. */
    private function rango(): array
    {
        // **El selector de sucursal ofrece SÓLO las de esta persona.** Antes
        // listaba todas las de la base, así que quien tiene asignado un local
        // podía pedir el informe de otro con cambiar el combo — y los números
        // salían. `Sucursales::delUsuario()` es la misma regla con la que se
        // decide a qué local puede entrar.
        $mias = $this->misSucursales();

        $campos = [
            'desde' => ['tipo' => 'fecha', 'etiqueta' => 'Desde'],
            'hasta' => ['tipo' => 'fecha', 'etiqueta' => 'Hasta'],
            'prof' => ['tipo' => 'select', 'etiqueta' => 'Profesional', 'ancho' => '190px',
                       'opciones' => ['' => 'Todo el equipo'] + $this->profesionales()],
        ];

        // Con un solo local la pregunta no significa nada: todo lo que hay es
        // de acá, y un combo de una opción sólo ocupa lugar.
        if (count($mias) > 1) {
            $campos['suc'] = ['tipo' => 'select', 'etiqueta' => 'Sucursal', 'ancho' => '170px',
                              'opciones' => ['' => 'Todas'] + $mias];
        }

        $f = Listado::filtros($campos);

        // Sin rango cargado se asume el mes en curso, pero se deja escrito en
        // los campos: así se ve qué período se está mirando.
        if (! Listado::hay($f, 'desde')) {
            $f['v']['desde'] = date('Y-m-01');
        }
        if (! Listado::hay($f, 'hasta')) {
            $f['v']['hasta'] = date('Y-m-t');
        }

        // **Con una sola sucursal el filtro se pone solo, no se ofrece.** Si no,
        // quien tiene un local vería el consolidado del salón entero, que es
        // exactamente lo que el aislamiento por sucursal viene a impedir.
        if (count($mias) === 1) {
            $f['v']['suc'] = (string) array_key_first($mias);
        }

        return $f;
    }

    /** Las sucursales que esta persona puede mirar, como opciones del combo. */
    private function misSucursales(): array
    {
        $out = [];
        foreach (Sucursales::delUsuario() as $s) {
            $out[(string) $s->id_sucursal] = $s->nombre;
        }

        return $out;
    }

    /**
     * El equipo que se puede elegir.
     *
     * Sólo el personal activo, y **sólo el de las sucursales de esta persona**:
     * ofrecer a alguien de otro local es ofrecer un informe vacío en el mejor
     * caso y datos ajenos en el peor.
     */
    private function profesionales(): array
    {
        $mias = array_keys($this->misSucursales());
        $par = [];
        $w = '';
        if ($mias) {
            // **Dos juegos de marcadores, no uno repetido.** La conexión va con
            // las preparadas nativas de MySQL, que NO admiten el mismo nombre
            // dos veces: con `:m0` en los dos IN, la consulta revienta con
            // «Invalid parameter number».
            $a = [];
            $b = [];
            foreach (array_values($mias) as $i => $id) {
                $a[] = ':ma' . $i;
                $b[] = ':mb' . $i;
                $par['ma' . $i] = (int) $id;
                $par['mb' . $i] = (int) $id;
            }
            $w = ' AND (u.id_sucursal IN (' . implode(',', $a) . ')
                        OR EXISTS (SELECT 1 FROM usuario_sucursal us
                                    WHERE us.id_usuario = u.id_usuario
                                      AND us.id_sucursal IN (' . implode(',', $b) . ')))';
        }

        $out = [];
        foreach (DB::select(
            "SELECT u.id_usuario, CONCAT(pe.nombre,' ',pe.apellido) n
               FROM usuario u JOIN persona pe ON pe.id_persona = u.id_persona
               JOIN rol r ON r.id_rol = u.id_rol
              WHERE r.es_personal = 1 AND u.activo = 1 $w
              ORDER BY pe.nombre", $par
        ) as $p) {
            $out[(string) $p->id_usuario] = $p->n;
        }

        return $out;
    }

    // -----------------------------------------------------------------
    //  Los datos
    // -----------------------------------------------------------------

    private function datos(array $f): array
    {
        $d = Listado::valor($f, 'desde');
        $h = Listado::valor($f, 'hasta');
        $prof = Listado::valor($f, 'prof');
        $suc = Listado::valor($f, 'suc');

        // --- Filtro común de citas y de lo que cuelga de ellas ---
        $wCita = ['DATE(c.fecha_hora) BETWEEN :d AND :h'];
        $par = ['d' => $d, 'h' => $h];
        if ($prof !== '') {
            $wCita[] = 'c.id_usuario = :p';
            $par['p'] = (int) $prof;
        }
        if ($suc !== '') {
            // La sucursal de la CITA, no la de la ficha del profesional: desde
            // que una persona puede estar asignada a varios locales, dónde
            // trabaja habitualmente ya no dice dónde ocurrió la atención.
            $wCita[] = 'c.id_sucursal = :s';
            $par['s'] = (int) $suc;
        }
        $joinCita = 'FROM cita c
                     JOIN usuario u  ON u.id_usuario = c.id_usuario
                     JOIN persona pe ON pe.id_persona = u.id_persona
                     WHERE ' . implode(' AND ', $wCita);

        $citas = DB::selectOne(
            "SELECT COUNT(*) total,
                    SUM(c.id_estado_cita = 4) atendidas,
                    SUM(c.id_estado_cita = 3) canceladas,
                    SUM(c.id_estado_cita = 6) ausencias
               $joinCita", $par
        );

        // Todos los estados, no sólo los cuatro del resumen: el informe de
        // Citas los muestra enteros, y así entra uno nuevo sin tocar código.
        $estados = DB::select(
            "SELECT ec.nombre estado, COUNT(*) cantidad
               FROM cita c
               JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
               JOIN usuario u  ON u.id_usuario = c.id_usuario
               JOIN persona pe ON pe.id_persona = u.id_persona
              WHERE " . implode(' AND ', $wCita) . '
              GROUP BY c.id_estado_cita, ec.nombre ORDER BY cantidad DESC', $par
        );

        // --- Cobros: la plata que entró de verdad ---
        //
        // **El filtro de sucursal también va acá, y ese era el defecto.** Las
        // citas se filtraban y los cobros no, así que pidiendo el informe de un
        // local salían las citas de ese local con los ingresos de TODOS — dos
        // números que no se pueden comparar y que nadie iba a notar.
        //
        // La sucursal del cobro sale de su caja, que es donde entró la plata;
        // los pocos que puedan no tenerla —una seña vieja— se ubican por la
        // cita. Es el mismo criterio con el que el panel aisló los ingresos.
        $wCob = ['DATE(co.fecha) BETWEEN :d AND :h', 'co.id_estado_cobro = 1'];
        $parC = ['d' => $d, 'h' => $h];
        if ($prof !== '') {
            $wCob[] = 'cc.id_usuario = :p';
            $parC['p'] = (int) $prof;
        }
        if ($suc !== '') {
            $wCob[] = 'COALESCE(k.id_sucursal, cc.id_sucursal) = :s';
            $parC['s'] = (int) $suc;
        }
        $joinCob = 'FROM cobro co
                    JOIN metodo_pago mp  ON mp.id_metodo_pago = co.id_metodo_pago
                    LEFT JOIN caja k     ON k.id_caja = co.id_caja
                    LEFT JOIN factura fa ON fa.id_factura = co.id_factura
                    LEFT JOIN cita cc    ON cc.id_cita = COALESCE(co.id_cita, fa.id_cita)
                    WHERE ' . implode(' AND ', $wCob);

        $ingresos = (float) DB::scalar("SELECT COALESCE(SUM(co.monto),0) $joinCob", $parC);

        // **Lo devuelto por notas de crédito** (FA-04). Los ingresos salen de
        // los cobros, y una nota de crédito no genera un cobro negativo: sin
        // esta línea, una venta acreditada se seguía contando entera y el
        // informe decía que entró plata que se devolvió.
        $wDev = ['DATE(f.fecha_emision) BETWEEN :d AND :h', 'f.id_estado_factura = 1', 'tc.signo = -1'];
        $parD = ['d' => $d, 'h' => $h];
        if ($prof !== '') {
            $wDev[] = 'cd.id_usuario = :p';
            $parD['p'] = (int) $prof;
        }
        if ($suc !== '') {
            // La sucursal del comprobante: la suya si la tiene, y si no la del
            // timbrado con el que se numeró (7.49.0).
            $wDev[] = 'COALESCE(f.id_sucursal, ti.id_sucursal) = :s';
            $parD['s'] = (int) $suc;
        }
        $devoluciones = (float) DB::scalar(
            'SELECT COALESCE(SUM(fn_factura_total(f.id_factura)),0)
               FROM factura f
               JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = f.id_tipo_comprobante
               LEFT JOIN timbrado ti ON ti.id_timbrado = f.id_timbrado
               LEFT JOIN cita cd ON cd.id_cita = f.id_cita
              WHERE ' . implode(' AND ', $wDev), $parD
        );

        $atendidas = (int) $citas->atendidas;
        $totalCitas = (int) $citas->total;

        // --- Demanda ---
        $demanda = DB::select(
            "SELECT HOUR(c.fecha_hora) hora, COUNT(*) citas,
                    SUM(c.id_estado_cita = 4) atendidas,
                    SUM(c.id_estado_cita = 6) ausencias
               $joinCita GROUP BY HOUR(c.fecha_hora) ORDER BY hora", $par
        );
        $maxDemanda = 0;
        foreach ($demanda as $x) {
            $maxDemanda = max($maxDemanda, (int) $x->citas);
        }

        // `WEEKDAY()+1` da 1=lunes … 7=domingo, que es la convención del
        // proyecto (`turno_dia.dia_semana`). NO se usa `DAYOFWEEK()`, que
        // arranca en domingo: mezclarlos corre todo un día.
        $demandaDia = DB::select(
            "SELECT WEEKDAY(c.fecha_hora) + 1 dia, COUNT(*) citas,
                    SUM(c.id_estado_cita = 4) atendidas,
                    SUM(c.id_estado_cita = 6) ausencias
               $joinCita GROUP BY WEEKDAY(c.fecha_hora) + 1 ORDER BY dia", $par
        );
        $maxDemandaDia = 0;
        foreach ($demandaDia as $x) {
            $maxDemandaDia = max($maxDemandaDia, (int) $x->citas);
        }

        // --- Servicios ---
        //
        // **El ingreso sale de `servicio_realizado`, uno por fila.** No hay
        // riesgo de que el JOIN a `cita` lo multiplique porque la relación es
        // N:1 —cada realizado pertenece a una sola cita—; el `COUNT` va sobre
        // la clave del realizado, no sobre `*`, para que quede explícito.
        $servicios = DB::select(
            'SELECT s.nombre AS servicio, cs.nombre AS categoria,
                    COUNT(sr.id_servicio_realizado) AS veces_realizado,
                    COALESCE(SUM(s.precio),0) AS ingreso_generado
               FROM servicio_realizado sr
               JOIN servicio s  ON s.id_servicio = sr.id_servicio
               JOIN categoria_servicio cs ON cs.id_categoria_servicio = s.id_categoria_servicio
               JOIN cita c      ON c.id_cita = sr.id_cita
               JOIN usuario u   ON u.id_usuario = c.id_usuario
               JOIN persona pe  ON pe.id_persona = u.id_persona
              WHERE ' . implode(' AND ', $wCita) . '
              GROUP BY s.id_servicio, s.nombre, cs.nombre
              ORDER BY veces_realizado DESC, ingreso_generado DESC', $par
        );
        $totalServicios = 0;
        foreach ($servicios as $s) {
            $totalServicios += (int) $s->veces_realizado;
        }

        // --- El equipo ---
        //
        // **Los subconsultas también filtran por sucursal**, y no lo hacían:
        // la columna «Citas» salía del local elegido y «Servicios», «Generado»
        // y «Comisión» del salón entero. Dos columnas de la misma fila
        // midiendo cosas distintas es peor que no tenerlas.
        $wSr = '';
        $wAs = '';
        if ($suc !== '') {
            $wSr = ' AND EXISTS (SELECT 1 FROM cita cx WHERE cx.id_cita = sr.id_cita AND cx.id_sucursal = :ssr)';
            $wAs = ' AND EXISTS (SELECT 1 FROM turno_laboral tl
                                  WHERE tl.id_turno = a.id_turno AND tl.id_sucursal = :sas)';
        }

        $parEq = $par + ['d2' => $d, 'h2' => $h, 'd3' => $d, 'h3' => $h,
                         'd4' => $d, 'h4' => $h, 'd5' => $d, 'h5' => $h,
                         'd6' => $d, 'h6' => $h, 'd7' => $d, 'h7' => $h];
        if ($suc !== '') {
            $parEq += ['ssr' => (int) $suc, 'ssr2' => (int) $suc, 'ssr3' => (int) $suc,
                       'sas' => (int) $suc, 'sas2' => (int) $suc];
        }
        $wSr2 = str_replace(':ssr', ':ssr2', $wSr);
        $wSr3 = str_replace(':ssr', ':ssr3', $wSr);
        $wAs2 = str_replace(':sas', ':sas2', $wAs);

        $equipo = DB::select(
            // **«Ausencias» en una tabla de profesionales se lee como faltas
            // del profesional, y no lo era: contaba las citas en las que no
            // vino LA CLIENTA.** Son dos cosas distintas y las dos importan.
            "SELECT CONCAT(pe.nombre,' ',pe.apellido) profesional,
                    COUNT(DISTINCT c.id_cita) citas,
                    SUM(c.id_estado_cita = 4) atendidas,
                    SUM(c.id_estado_cita = 6) clienta_no_vino,
                    SUM(c.id_estado_cita = 3) canceladas,
                    -- Las faltas del PROFESIONAL salen del fichaje, no de
                    -- las citas: `justificada` NULL es que vino, 1 falta
                    -- con aviso y 0 sin aviso.
                    (SELECT COUNT(*) FROM asistencia a
                      WHERE a.id_usuario = u.id_usuario AND a.justificada IS NOT NULL
                        AND a.fecha BETWEEN :d6 AND :h6 $wAs) falto,
                    (SELECT COUNT(*) FROM asistencia a
                      WHERE a.id_usuario = u.id_usuario AND a.justificada = 0
                        AND a.fecha BETWEEN :d7 AND :h7 $wAs2) falto_sin_aviso,
                    (SELECT COUNT(*) FROM servicio_realizado sr WHERE sr.id_usuario = u.id_usuario
                       AND DATE(sr.fecha_hora) BETWEEN :d2 AND :h2 $wSr) servicios,
                    -- Lo que trajo al salón, y lo que le toca a ella. La
                    -- comisión la calcula la base: mira el porcentaje o el
                    -- monto fijo vigente de esa persona para ese servicio.
                    (SELECT COALESCE(SUM(s.precio),0) FROM servicio_realizado sr
                       JOIN servicio s ON s.id_servicio = sr.id_servicio
                      WHERE sr.id_usuario = u.id_usuario
                        AND DATE(sr.fecha_hora) BETWEEN :d4 AND :h4 $wSr2) generado,
                    (SELECT COALESCE(SUM(fn_comision_servicio(sr.id_servicio_realizado)),0)
                       FROM servicio_realizado sr
                      WHERE sr.id_usuario = u.id_usuario
                        AND DATE(sr.fecha_hora) BETWEEN :d5 AND :h5 $wSr3) comision,
                    -- Un «Gs. 0» es ambiguo: puede ser que no ganó nada o
                    -- que NADIE LE CARGÓ LA COMISIÓN, que es lo que pasa
                    -- casi siempre. Sin esto, el informe miente por omisión.
                    EXISTS (SELECT 1 FROM comision co
                             WHERE co.id_usuario = u.id_usuario AND co.activo = 1) tiene_comision,
                    (SELECT ROUND(AVG(cal.puntaje),2) FROM calificacion cal
                       JOIN cita c2 ON c2.id_cita = cal.id_cita
                      WHERE c2.id_usuario = u.id_usuario AND DATE(c2.fecha_hora) BETWEEN :d3 AND :h3) puntaje
               $joinCita
              GROUP BY u.id_usuario, pe.nombre, pe.apellido
              ORDER BY atendidas DESC, citas DESC", $parEq
        );

        // --- Compras y proveedores ---
        //
        // La deuda **no depende del período**: es deuda viva, lo que se le
        // debe hoy a cada proveedor. Las compras sí, y por eso van aparte.
        $wComp = ['DATE(cp.fecha) BETWEEN :d AND :h'];
        $parP = ['d' => $d, 'h' => $h];
        if ($suc !== '') {
            $wComp[] = 'cp.id_sucursal = :s';
            $parP['s'] = (int) $suc;
        }
        $compras = DB::selectOne(
            'SELECT COUNT(*) cantidad,
                    COALESCE(SUM(fn_compra_total(cp.id_compra)),0) total,
                    COALESCE(SUM(fn_compra_saldo(cp.id_compra)),0) saldo
               FROM compra cp WHERE ' . implode(' AND ', $wComp), $parP
        );
        $comprasProv = DB::select(
            "SELECT CONCAT(pe.nombre,' ',COALESCE(pe.apellido,'')) proveedor,
                    COUNT(*) compras,
                    COALESCE(SUM(fn_compra_total(cp.id_compra)),0) total,
                    COALESCE(SUM(fn_compra_saldo(cp.id_compra)),0) saldo
               FROM compra cp
               JOIN proveedor pr ON pr.id_proveedor = cp.id_proveedor
               JOIN persona pe   ON pe.id_persona = pr.id_persona
              WHERE " . implode(' AND ', $wComp) . '
              GROUP BY pr.id_proveedor, pe.nombre, pe.apellido
              ORDER BY total DESC', $parP
        );

        return [
            'f' => $f,
            'desde' => $d,
            'hasta' => $h,
            'sucElegida' => $suc,
            'profElegido' => $prof,
            'citas' => $citas,
            'estados' => $estados,
            'ingresos' => $ingresos,
            'devoluciones' => $devoluciones,
            'ticket' => $atendidas > 0 ? $ingresos / $atendidas : 0.0,

            // **Los porcentajes salen de los mismos números de arriba**, así
            // que no pueden contradecirlos. Con cero citas no se muestran: un
            // «0 %» de asistencia sobre un período sin citas dice algo que no
            // pasó.
            'pctAsistencia' => $totalCitas > 0 ? $atendidas * 100 / $totalCitas : null,
            'pctCancelacion' => $totalCitas > 0 ? (int) $citas->canceladas * 100 / $totalCitas : null,
            'pctAusencia' => $totalCitas > 0 ? (int) $citas->ausencias * 100 / $totalCitas : null,

            'servicios' => $servicios,
            'totalServicios' => $totalServicios,
            'demanda' => $demanda,
            'maxDemanda' => $maxDemanda,
            'demandaDia' => $demandaDia,
            'maxDemandaDia' => $maxDemandaDia,

            // **Cada local, en una sola tabla.** El selector ya dejaba mirar una
            // sucursal por vez, pero para decidir dónde reforzar hace falta
            // verlas juntas. Sólo se arma cuando se están mirando todas — con
            // una elegida, la tabla tendría una fila y repetiría el resumen.
            'porSucursal' => $suc !== '' ? [] : DB::select(
                "SELECT su.nombre sucursal,
                        COUNT(DISTINCT c.id_cita) citas,
                        COUNT(DISTINCT CASE WHEN c.id_estado_cita = 4 THEN c.id_cita END) atendidas,
                        COUNT(DISTINCT CASE WHEN c.id_estado_cita = 3 THEN c.id_cita END) canceladas,
                        COUNT(DISTINCT CASE WHEN c.id_estado_cita = 6 THEN c.id_cita END) ausentes,
                        COUNT(DISTINCT c.id_cliente) clientes,
                        COUNT(DISTINCT c.id_usuario) profesionales,
                        -- **Los servicios se cuentan con un LEFT JOIN y DISTINCT**,
                        -- no con una subconsulta por sucursal: la subconsulta se
                        -- saltaba el filtro de profesional, así que con una
                        -- profesional elegida la columna «Citas» era de ella y
                        -- «Servicios» del local entero. Y el DISTINCT es lo que
                        -- evita que el JOIN multiplique el conteo de citas.
                        COUNT(DISTINCT sr.id_servicio_realizado) servicios
                   FROM cita c
                   JOIN sucursal su ON su.id_sucursal = c.id_sucursal
                   LEFT JOIN servicio_realizado sr ON sr.id_cita = c.id_cita
                  WHERE " . implode(' AND ', $wCita) . '
                  GROUP BY su.id_sucursal, su.nombre ORDER BY citas DESC',
                $par
            ),

            // Lo cobrado sale de la caja, que es donde entró la plata: es el
            // mismo criterio con el que el panel aisló los ingresos (7.36.4).
            'ingresoSucursal' => $suc !== '' ? [] : DB::select(
                "SELECT su.nombre sucursal, COALESCE(SUM(co.monto),0) total
                   FROM cobro co
                   JOIN caja cj ON cj.id_caja = co.id_caja
                   JOIN sucursal su ON su.id_sucursal = cj.id_sucursal
                  WHERE co.id_estado_cobro = 1 AND DATE(co.fecha) BETWEEN :d AND :h
                  GROUP BY su.id_sucursal, su.nombre",
                ['d' => $d, 'h' => $h]
            ),

            'medios' => DB::select(
                "SELECT mp.nombre medio, mp.tipo, COUNT(*) cantidad, COALESCE(SUM(co.monto),0) total
                   $joinCob GROUP BY mp.id_metodo_pago, mp.nombre, mp.tipo ORDER BY total DESC", $parC
            ),

            // Lo cobrado día por día, para ver la curva del período. Va en
            // Ingresos y no en el resumen: es detalle, no titular.
            'ingresoDia' => DB::select(
                "SELECT DATE(co.fecha) dia, COALESCE(SUM(co.monto),0) total
                   $joinCob GROUP BY DATE(co.fecha) ORDER BY dia", $parC
            ),

            'equipo' => $equipo,
            'compras' => $compras,
            'comprasProv' => $comprasProv,

            // La deuda con proveedores no depende del período: es deuda viva.
            'prov' => DB::select(
                'SELECT * FROM vw_cuenta_proveedor WHERE saldo > 0 ORDER BY vencida DESC, vencimiento LIMIT 30'),

            // Para el atajo «Histórico»: desde el primer movimiento cargado
            'inicio' => (string) (DB::scalar(
                'SELECT LEAST(
                          COALESCE((SELECT DATE(MIN(fecha_hora)) FROM cita), CURDATE()),
                          COALESCE((SELECT DATE(MIN(fecha)) FROM cobro), CURDATE())
                        )'
            ) ?: date('Y-m-d')),
        ];
    }

    // -----------------------------------------------------------------
    //  Bajar la sección
    // -----------------------------------------------------------------

    /**
     * La sección que se está mirando, en planilla.
     *
     * **El `.xls` lleva los gráficos, no sólo los números**, que es lo que se
     * pidió: cada tabla que en pantalla tiene barras las lleva también en la
     * planilla, dibujadas con celdas de color. Excel abre un HTML con estilos y
     * las respeta, así que no hace falta ninguna librería — la misma decisión
     * que ya está tomada con el PDF, que lo dibuja el navegador.
     *
     * El CSV se conserva para quien quiera seguir trabajando los datos: ahí no
     * van barras, porque un CSV no tiene formato y las celdas de más ensucian
     * las fórmulas.
     */
    private function exportar(string $seccion, array $datos, string $formato): StreamedResponse
    {
        return $this->xls('reporte_' . $seccion . '_' . date('Ymd'), $seccion,
            $this->hojasDe($seccion, $datos), $datos);
    }

    /**
     * Las tablas de cada sección, en un formato común.
     *
     * Cada fila es una lista de celdas; una celda puede ser un valor suelto o
     * `[valor, proporción]`, y en ese caso la planilla le dibuja la barra al
     * lado. Así el gráfico sale de los mismos datos que el número — no puede
     * decir otra cosa.
     */
    private function hojasDe(string $seccion, array $datos): array
    {
        $dias = [1 => 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        $c = $datos['citas'];

        $resumen = ['titulo' => 'Resumen del período', 'cols' => ['Indicador', 'Valor'], 'filas' => [
            ['Citas del período', (int) $c->total],
            ['Atendidas', (int) $c->atendidas],
            ['Canceladas', (int) $c->canceladas],
            ['No vino la clienta', (int) $c->ausencias],
            ['Ingresos cobrados', (float) $datos['ingresos']],
            ['Devuelto (notas de crédito)', (float) $datos['devoluciones']],
            ['Ingreso neto', (float) $datos['ingresos'] - (float) $datos['devoluciones']],
            ['Ticket promedio', (float) $datos['ticket']],
        ]];
        if ($datos['pctAsistencia'] !== null) {
            $resumen['filas'][] = ['Asistencia', round((float) $datos['pctAsistencia'], 1) . ' %'];
            $resumen['filas'][] = ['Cancelación', round((float) $datos['pctCancelacion'], 1) . ' %'];
        }

        $tServ = ['titulo' => 'Servicios más solicitados',
                  'cols' => ['Servicio', 'Categoría', 'Veces', '% del total', 'Ingreso generado'],
                  'filas' => array_map(function ($s) use ($datos) {
                      $t = max(1, (int) $datos['totalServicios']);

                      return [$s->servicio, $s->categoria,
                              [(int) $s->veces_realizado, (int) $s->veces_realizado / $t],
                              round((int) $s->veces_realizado * 100 / $t, 1) . ' %',
                              (float) $s->ingreso_generado];
                  }, $datos['servicios'])];

        $tMedios = ['titulo' => 'Medios de pago',
                    'cols' => ['Medio', 'Tipo', 'Cobros', 'Total'],
                    'filas' => array_map(function ($m) use ($datos) {
                        $t = max(0.01, (float) $datos['ingresos']);

                        return [$m->medio, $m->tipo, (int) $m->cantidad,
                                [(float) $m->total, (float) $m->total / $t]];
                    }, $datos['medios'])];

        $tDia = ['titulo' => 'Demanda por día',
                 'cols' => ['Día', 'Citas', 'Atendidas', 'No vino'],
                 'filas' => array_map(function ($x) use ($datos, $dias) {
                     $t = max(1, (int) $datos['maxDemandaDia']);

                     return [$dias[(int) $x->dia] ?? (string) $x->dia,
                             [(int) $x->citas, (int) $x->citas / $t],
                             (int) $x->atendidas, (int) $x->ausencias];
                 }, $datos['demandaDia'])];

        $tHora = ['titulo' => 'Demanda por hora',
                  'cols' => ['Hora', 'Citas', 'Atendidas', 'No vino'],
                  'filas' => array_map(function ($x) use ($datos) {
                      $t = max(1, (int) $datos['maxDemanda']);

                      return [sprintf('%02d:00', (int) $x->hora),
                              [(int) $x->citas, (int) $x->citas / $t],
                              (int) $x->atendidas, (int) $x->ausencias];
                  }, $datos['demanda'])];

        return match ($seccion) {
            'citas' => [
                ['titulo' => 'Citas por estado', 'cols' => ['Estado', 'Cantidad'],
                 'filas' => array_map(fn ($e) => [$e->estado, (int) $e->cantidad], $datos['estados'])],
                $tDia, $tHora,
            ],
            'servicios' => [$tServ],
            'equipo' => [['titulo' => 'El equipo',
                'cols' => ['Profesional', 'Citas', 'Atendidas', 'No vino la clienta', 'Canceladas',
                           'Faltó', 'Servicios', 'Generado', 'Comisión', 'Puntaje'],
                'filas' => array_map(function ($e) use ($datos) {
                    $maxGen = 0.0;
                    foreach ($datos['equipo'] as $x) {
                        $maxGen = max($maxGen, (float) $x->generado);
                    }

                    return [
                        $e->profesional, (int) $e->citas, (int) $e->atendidas, (int) $e->clienta_no_vino,
                        (int) $e->canceladas, (int) $e->falto, (int) $e->servicios,
                        [(float) $e->generado, $maxGen > 0 ? (float) $e->generado / $maxGen : 0],
                        $e->tiene_comision ? (float) $e->comision : 'sin cargar',
                        $e->puntaje !== null ? (float) $e->puntaje : '—',
                    ];
                }, $datos['equipo'])]],
            'ingresos' => [$tMedios, $tServ,
                ['titulo' => 'Cobrado por día', 'cols' => ['Día', 'Total'],
                 'filas' => array_map(fn ($x) => [fecha($x->dia, 'd/m/Y'), (float) $x->total], $datos['ingresoDia'])],
                ['titulo' => 'Generado por profesional', 'cols' => ['Profesional', 'Servicios', 'Generado'],
                 'filas' => array_map(fn ($e) => [$e->profesional, (int) $e->servicios, (float) $e->generado],
                     $datos['equipo'])]],
            'compras' => [
                ['titulo' => 'Compras del período', 'cols' => ['Indicador', 'Valor'], 'filas' => [
                    ['Compras registradas', (int) ($datos['compras']->cantidad ?? 0)],
                    ['Total comprado', (float) ($datos['compras']->total ?? 0)],
                    ['Saldo pendiente de esas compras', (float) ($datos['compras']->saldo ?? 0)],
                ]],
                ['titulo' => 'Por proveedor', 'cols' => ['Proveedor', 'Compras', 'Total', 'Saldo'],
                 'filas' => array_map(fn ($p) => [$p->proveedor, (int) $p->compras,
                     (float) $p->total, (float) $p->saldo], $datos['comprasProv'])],
                ['titulo' => 'Deuda viva (no depende del período)',
                 'cols' => ['Proveedor', 'Comprobante', 'Vencimiento', 'Saldo'],
                 'filas' => array_map(fn ($p) => [$p->proveedor ?? '', $p->nro_factura_proveedor ?? '',
                     $p->vencimiento ? fecha($p->vencimiento, 'd/m/Y') : '—', (float) $p->saldo], $datos['prov'])],
            ],
            'sucursales' => [
                ['titulo' => 'Por sucursal',
                 'cols' => ['Sucursal', 'Citas', 'Atendidas', 'Canceladas', 'No vino', 'Clientas', 'Servicios'],
                 'filas' => array_map(function ($s) use ($datos) {
                     $maxC = 0;
                     foreach ($datos['porSucursal'] as $x) {
                         $maxC = max($maxC, (int) $x->citas);
                     }

                     return [$s->sucursal, [(int) $s->citas, $maxC > 0 ? (int) $s->citas / $maxC : 0],
                             (int) $s->atendidas, (int) $s->canceladas, (int) $s->ausentes,
                             (int) $s->clientes, (int) $s->servicios];
                 }, $datos['porSucursal'])],
                ['titulo' => 'Cobrado por sucursal', 'cols' => ['Sucursal', 'Total'],
                 'filas' => array_map(fn ($s) => [$s->sucursal, (float) $s->total], $datos['ingresoSucursal'])],
            ],
            default => [$resumen, $tServ, $tMedios, $tDia],
        };
    }

    /**
     * La planilla, como HTML con el tipo de Excel.
     *
     * **No se agrega una librería** y es a propósito: el proyecto ya decidió lo
     * mismo para el PDF, y en el VPS la RAM se comparte con los demás grupos.
     * Excel abre un HTML con `Content-Type` de `application/vnd.ms-excel`, lo
     * pasa a celdas y respeta el color de fondo — que es lo que hace posible
     * que las barras viajen con los números.
     */
    private function xls(string $nombre, string $seccion, array $hojas, array $datos): StreamedResponse
    {
        $titulo = self::SECCIONES[$seccion][0] ?? 'Informe';
        $filtros = [];
        foreach (($datos['f']['v'] ?? []) as $k => $v) {
            if ($v === '') {
                continue;
            }
            $def = $datos['f']['campos'][$k] ?? [];
            $txt = ($def['tipo'] ?? '') === 'select'
                ? (string) ($def['opciones'][$v] ?? $v) : (string) $v;
            $filtros[] = ($def['etiqueta'] ?? $k) . ': ' . $txt;
        }

        return response()->streamDownload(function () use ($titulo, $hojas, $filtros) {
            // El BOM es lo que hace que Excel en Windows lea UTF-8; sin él, las
            // ñ y las tildes salen rotas. Es el mismo motivo que en el CSV.
            echo "\xEF\xBB\xBF";
            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" '
                . 'xmlns:x="urn:schemas-microsoft-com:office:excel" '
                . 'xmlns="http://www.w3.org/TR/REC-html40" lang="es"><head>';
            echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
            echo '<meta name="ProgId" content="Excel.Sheet">';
            echo '<style>
                @page{margin:.5in;size:landscape}
                table{border-collapse:collapse;font-family:Calibri,Arial,sans-serif;font-size:11pt}
                th{background:#0D0D0D;color:#C9A84C;font-weight:bold;text-align:left;
                   border:1px solid #8A6C1E;padding:6px 8px;white-space:nowrap}
                td{border:1px solid #E0DDD8;padding:4px 8px;vertical-align:top}
                td.n{text-align:right;mso-number-format:"#,##0.00"}
                td.c{text-align:right;mso-number-format:"[$₲-es-PY] #,##0.00"}
                .t{font-size:15pt;font-weight:bold;color:#8A6C1E;padding:4px 0}
                .s{font-size:10pt;color:#555;padding:2px 0}
                .hoja{page-break-inside:avoid;margin-bottom:12px}
                .grafico{width:220px;min-width:220px;padding:4px 8px}
                .grafico-fondo{width:200px;height:12px;background:#F1E7C3;border:1px solid #D7C58A}
                .grafico-barra{height:12px;background:#C9A84C}
            </style></head><body>';
            echo '<div class="t">' . e($titulo) . '</div>';
            if ($filtros) {
                echo '<div class="s">' . e(implode('  ·  ', $filtros)) . '</div>';
            }
            echo '<div class="s">Emitido ' . e(ahora_bd('d/m/Y H:i'))
                . ' · ' . e((string) session('nombre', '')) . '</div><br>';

            foreach ($hojas as $hoja) {
                echo '<div class="t" style="font-size:12pt">' . e($hoja['titulo']) . '</div>';
                if (! $hoja['filas']) {
                    echo '<div class="s">Sin datos para el período seleccionado.</div><br>';
                    continue;
                }
                echo '<div class="hoja"><table><tr>';
                foreach ($hoja['cols'] as $col) {
                    echo '<th>' . e($col) . '</th>';
                }
                // La columna del gráfico va al final y ocupa diez celdas: es la
                // barra, dibujada con el relleno de las celdas.
                $conBarra = false;
                foreach ($hoja['filas'] as $fila) {
                    foreach ($fila as $indice => $celda) {
                        if (is_array($celda)) {
                            $conBarra = true;
                        }
                    }
                }
                if ($conBarra) {
                    echo '<th class="grafico">Proporción</th>';
                }
                echo '</tr>';

                foreach ($hoja['filas'] as $fila) {
                    echo '<tr>';
                    $prop = null;
                    foreach ($fila as $indice => $celda) {
                        $val = is_array($celda) ? $celda[0] : $celda;
                        if (is_array($celda)) {
                            $prop = (float) $celda[1];
                        }
                        $num = is_int($val) || is_float($val);
                        $clase = '';
                        if ($num) {
                            // Los importes se reconocen como moneda y el resto
                            // de los números como cantidades, evitando que
                            // Excel los importe como texto.
                            $columna = mb_strtolower((string) ($hoja['cols'][$indice] ?? ''));
                            $clase = (str_contains($columna, 'ingreso')
                                || str_contains($columna, 'total')
                                || str_contains($columna, 'saldo')
                                || str_contains($columna, 'generado'))
                                ? 'c' : 'n';
                        }
                        echo '<td' . ($clase ? ' class="' . $clase . '"' : '') . '>'
                            . e($num ? (string) $val : (string) $val) . '</td>';
                    }
                    if ($conBarra) {
                        // Una sola celda con una barra proporcional evita que
                        // Excel estire diez columnas y convierta el gráfico en
                        // un bloque enorme, como ocurría con la exportación
                        // anterior.
                        $proporcion = max(0, min(1, (float) ($prop ?? 0)));
                        $ancho = round($proporcion * 200, 1);
                        echo '<td class="grafico"><div class="grafico-fondo">'
                            . '<div class="grafico-barra" style="width:' . $ancho . 'px">&nbsp;</div>'
                            . '</div></td>';
                    }
                    echo '</tr>';
                }
                echo '</table></div>';
            }
            echo '</body></html>';
        }, $nombre . '.xls', [
            'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
