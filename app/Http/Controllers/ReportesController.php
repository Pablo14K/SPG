<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Listado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Informes parametrizados.
 *
 * Antes esta pantalla mostraba SIEMPRE el mes en curso y nada más: no se le
 * podía pedir otra cosa. Un informe que no se puede parametrizar no sirve para
 * decidir — la pregunta real de la propietaria es «¿cómo me fue en marzo?» o
 * «¿cuánto facturó Rocío la semana pasada?».
 *
 * `datos()` vive aparte de las dos pantallas para que la vista de impresión
 * muestre EXACTAMENTE lo mismo que la de consulta: si cada una armara su
 * consulta, el PDF podría no coincidir con lo que se vio.
 */
class ReportesController extends Controller
{
    public function index(): View
    {
        $f = $this->rango();

        return view('reportes.index', $this->datos($f));
    }

    /**
     * Informe listo para papel: sin barra de módulos ni pie, maquetado para
     * A4. El botón «Descargar PDF» abre el diálogo de impresión del navegador,
     * donde se elige «Guardar como PDF». No hay librería de PDF a propósito:
     * traería Composer al proyecto sin agregar nada que el navegador no haga.
     */
    /**
     * Los bloques que se pueden imprimir, cada uno con su casilla.
     *
     * Antes el papel salía con TODO lo que había en pantalla, y quien quería
     * llevarse sólo las citas terminaba imprimiendo seis hojas para usar una.
     * Se probó con un `<select>` de un solo bloque, pero eso obliga a elegir
     * **uno**: con casillas se arman las combinaciones que hagan falta —el
     * resumen y el equipo, por ejemplo— que es lo que se pide de verdad.
     *
     * La clave es la de la casilla y también el nombre del bloque en la vista.
     * Para sumar un bloque se toca sólo acá.
     */
    public const BLOQUES = [
        'resumen' => 'Resumen del período',
        'servicios' => 'Servicios más solicitados',
        'demanda' => 'Demanda por hora y por día',
        'medios' => 'Medios de pago',
        'equipo' => 'El equipo',
        'prov' => 'Deuda con proveedores',
    ];

    public function imprimir(Request $request): View
    {
        $f = $this->rango();
        $datos = $this->datos($f);

        $datos['emisor'] = DB::selectOne(
            'SELECT nombre, ruc, telefono, direccion, ciudad FROM sucursal
              WHERE activo = 1 ORDER BY id_sucursal LIMIT 1'
        ) ?: (object) ['nombre' => config('app.name')];
        $datos['emitido'] = ahora_bd('d/m/Y H:i');
        $datos['porQuien'] = (string) session('nombre', '');

        // Qué bloques se imprimen. Se saneia contra las claves que existen, así
        // que lo que venga inventado en la URL se descarta; y si no queda
        // ninguno se imprime todo, que es lo que hacía antes: **nunca se
        // devuelve una hoja en blanco**.
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

        return view('reportes.imprimir', $datos);
    }

    /** Rango por defecto: el mes en curso, que es lo que se mira casi siempre. */
    private function rango(): array
    {
        $f = Listado::filtros([
            'desde' => ['tipo' => 'fecha', 'etiqueta' => 'Desde'],
            'hasta' => ['tipo' => 'fecha', 'etiqueta' => 'Hasta'],
            'prof' => ['tipo' => 'select', 'etiqueta' => 'Profesional', 'ancho' => '200px',
                       'opciones' => ['' => 'Todo el equipo'] + $this->profesionales()],
            'suc' => ['tipo' => 'select', 'etiqueta' => 'Sucursal', 'ancho' => '180px',
                      'opciones' => ['' => 'Todas'] + $this->sucursales()],
        ]);

        // Sin rango cargado se asume el mes en curso, pero se deja escrito en
        // los campos: así se ve qué período se está mirando.
        if (! Listado::hay($f, 'desde')) {
            $f['v']['desde'] = date('Y-m-01');
        }
        if (! Listado::hay($f, 'hasta')) {
            $f['v']['hasta'] = date('Y-m-t');
        }

        return $f;
    }

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

        // Los ingresos salen de los COBROS registrados, que es la plata que
        // entró de verdad: lo facturado puede cobrarse parcial o después.
        $wCob = ['DATE(co.fecha) BETWEEN :d AND :h', 'co.id_estado_cobro = 1'];
        $parC = ['d' => $d, 'h' => $h];
        if ($prof !== '') {
            $wCob[] = 'cc.id_usuario = :p';
            $parC['p'] = (int) $prof;
        }
        $joinCob = 'FROM cobro co
                    JOIN metodo_pago mp  ON mp.id_metodo_pago = co.id_metodo_pago
                    LEFT JOIN factura fa ON fa.id_factura = co.id_factura
                    LEFT JOIN cita cc    ON cc.id_cita = COALESCE(co.id_cita, fa.id_cita)
                    WHERE ' . implode(' AND ', $wCob);

        $ingresos = (float) DB::scalar("SELECT COALESCE(SUM(co.monto),0) $joinCob", $parC);

        // **Lo devuelto por notas de crédito** (FA-04). Los ingresos salen de
        // los cobros, y una nota de crédito no genera un cobro negativo: sin
        // esta línea, una venta acreditada se seguía contando entera y el
        // informe decía que entró plata que se devolvió.
        //
        // Se mide por el total de las notas vigentes del período. No se resta
        // en silencio: se muestra aparte y con el neto al lado, porque «cuánto
        // se cobró» y «cuánto se devolvió» son dos números que el salón quiere
        // ver por separado.
        $wDev = ['DATE(f.fecha_emision) BETWEEN :d AND :h', 'f.id_estado_factura = 1', 'tc.signo = -1'];
        $parD = ['d' => $d, 'h' => $h];
        if ($prof !== '') {
            $wDev[] = 'cd.id_usuario = :p';
            $parD['p'] = (int) $prof;
        }
        $devoluciones = (float) DB::scalar(
            'SELECT COALESCE(SUM(fn_factura_total(f.id_factura)),0)
               FROM factura f
               JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = f.id_tipo_comprobante
               LEFT JOIN cita cd ON cd.id_cita = f.id_cita
              WHERE ' . implode(' AND ', $wDev), $parD
        );

        $atendidas = (int) $citas->atendidas;

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

        // La demanda por hora contesta a qué hora abrir o reforzar; la de por
        // día, qué días conviene tener más gente. Son preguntas distintas y por
        // eso van las dos.
        //
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

        return [
            'f' => $f,
            'desde' => $d,
            'hasta' => $h,
            'citas' => $citas,
            'ingresos' => $ingresos,
            'devoluciones' => $devoluciones,
            'ticket' => $atendidas > 0 ? $ingresos / $atendidas : 0.0,
            'servicios' => DB::select(
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
                  ORDER BY veces_realizado DESC, ingreso_generado DESC LIMIT 15', $par
            ),
            'demanda' => $demanda,
            'maxDemanda' => $maxDemanda,
            'demandaDia' => $demandaDia,
            'maxDemandaDia' => $maxDemandaDia,
            'medios' => DB::select(
                "SELECT mp.nombre medio, mp.tipo, COUNT(*) cantidad, COALESCE(SUM(co.monto),0) total
                   $joinCob GROUP BY mp.id_metodo_pago, mp.nombre, mp.tipo ORDER BY total DESC", $parC
            ),
            'equipo' => DB::select(
                // Las ausencias son de quien NO vino a la cita, y por
                // profesional dicen algo que el total no: si a una le fallan
                // muchas, puede ser el horario o el recordatorio.
                "SELECT CONCAT(pe.nombre,' ',pe.apellido) profesional,
                        COUNT(DISTINCT c.id_cita) citas,
                        SUM(c.id_estado_cita = 4) atendidas,
                        SUM(c.id_estado_cita = 6) ausencias,
                        SUM(c.id_estado_cita = 3) canceladas,
                        (SELECT COUNT(*) FROM servicio_realizado sr WHERE sr.id_usuario = u.id_usuario
                           AND DATE(sr.fecha_hora) BETWEEN :d2 AND :h2) servicios,
                        -- Lo que trajo al salón, y lo que le toca a ella. La
                        -- comisión la calcula la base: mira el porcentaje o el
                        -- monto fijo vigente de esa persona para ese servicio.
                        (SELECT COALESCE(SUM(s.precio),0) FROM servicio_realizado sr
                           JOIN servicio s ON s.id_servicio = sr.id_servicio
                          WHERE sr.id_usuario = u.id_usuario
                            AND DATE(sr.fecha_hora) BETWEEN :d4 AND :h4) generado,
                        (SELECT COALESCE(SUM(fn_comision_servicio(sr.id_servicio_realizado)),0)
                           FROM servicio_realizado sr
                          WHERE sr.id_usuario = u.id_usuario
                            AND DATE(sr.fecha_hora) BETWEEN :d5 AND :h5) comision,
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
                  ORDER BY atendidas DESC, citas DESC",
                $par + ['d2' => $d, 'h2' => $h, 'd3' => $d, 'h3' => $h,
                        'd4' => $d, 'h4' => $h, 'd5' => $d, 'h5' => $h]
            ),
            // La deuda con proveedores no depende del período: es deuda viva
            'prov' => DB::select('SELECT * FROM vw_cuenta_proveedor WHERE saldo > 0 ORDER BY vencida DESC, vencimiento LIMIT 30'),
            // Para el atajo «Histórico»: desde el primer movimiento cargado
            'inicio' => (string) (DB::scalar(
                'SELECT LEAST(
                          COALESCE((SELECT DATE(MIN(fecha_hora)) FROM cita), CURDATE()),
                          COALESCE((SELECT DATE(MIN(fecha)) FROM cobro), CURDATE())
                        )'
            ) ?: date('Y-m-d')),
        ];
    }

    private function profesionales(): array
    {
        $out = [];
        foreach (DB::select(
            "SELECT u.id_usuario, CONCAT(pe.nombre,' ',pe.apellido) n
               FROM usuario u JOIN persona pe ON pe.id_persona = u.id_persona
               JOIN rol r ON r.id_rol = u.id_rol
              WHERE r.es_personal = 1 ORDER BY pe.nombre"
        ) as $p) {
            $out[(string) $p->id_usuario] = $p->n;
        }

        return $out;
    }

    private function sucursales(): array
    {
        $out = [];
        foreach (DB::select('SELECT id_sucursal, nombre FROM sucursal ORDER BY nombre') as $s) {
            $out[(string) $s->id_sucursal] = $s->nombre;
        }

        return $out;
    }
}
