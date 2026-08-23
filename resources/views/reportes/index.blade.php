@extends('layout.app')

@section('titulo', 'Reportes · ' . ($secciones[$seccion][0] ?? ''))

@section('contenido')
{{-- `spg-reporte` le da a las tablas del informe el aire que una lista de
     operación no necesita: acá los números se comparan entre sí. --}}
<div class="spg-reporte">
    <x-encabezado
        :sub="'Del <strong>' . fecha($desde, 'd/m/Y') . '</strong> al <strong>' . fecha($hasta, 'd/m/Y') . '</strong>. Los ingresos son los <strong>cobros registrados</strong>, que es la plata que entró de verdad, no lo facturado.'" />

    {{-- ---------------------------------------------------------------
         Filtros: un solo bloque compacto, con los atajos de período.

         El «Histórico» era un botón grande al lado de los otros dos, y hace
         exactamente lo mismo que ellos —poner un rango— así que va como un
         atajo más. --}}
    <div class="spg-panel spg-filtros-rep mb-3">
        {{-- La sección viaja escondida: cambiar un filtro no tiene por qué
             devolverte al Resumen si estabas mirando Ingresos. --}}
        <x-filtros :f="$f" :ocultos="['r' => $seccion]" />

        <div class="spg-atajos-per">
            @php
                $atajos = [
                    'Este mes' => [date('Y-m-01'), date('Y-m-t')],
                    'Mes pasado' => [date('Y-m-01', strtotime('-1 month')), date('Y-m-t', strtotime('-1 month'))],
                    'Últimos 3 meses' => [date('Y-m-01', strtotime('-2 months')), date('Y-m-t')],
                    'Todo' => [$inicio, date('Y-m-d')],
                ];
            @endphp
            @foreach ($atajos as $texto => [$dd, $hh])
                <a class="btn btn-sm {{ $desde === $dd && $hasta === $hh ? 'btn-oro' : 'btn-outline-neutro' }}"
                   href="{{ route('reportes.index', array_merge(request()->except(['desde', 'hasta', 'page']), ['desde' => $dd, 'hasta' => $hh])) }}">
                    {{ $texto }}</a>
            @endforeach

            <span class="spg-atajos-sep"></span>

            {{-- **Bajar lo que se está mirando.** Los tres salen del mismo
                 rango y los mismos filtros que la pantalla: el `.xls` lleva
                 además las barras, para que el número venga con su gráfico. --}}
            <a class="btn btn-sm btn-outline-neutro"
               href="{{ route('reportes.index', request()->except('export') + ['export' => 'xls']) }}"
               title="Planilla con los números y los gráficos">
                <i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
            <a class="btn btn-sm btn-outline-neutro"
               href="{{ route('reportes.index', request()->except('export') + ['export' => 'csv']) }}"
               title="Sólo los datos, para trabajarlos en una planilla">
                <i class="bi bi-filetype-csv"></i> CSV</a>
            <button type="button" class="btn btn-sm btn-outline-neutro"
                    data-bs-toggle="modal" data-bs-target="#modalImprimir">
                <i class="bi bi-printer"></i> Imprimir</button>
        </div>
    </div>

    {{-- ---------------------------------------------------------------
         Las pestañas. Cada una es una pantalla propia: el informe entero en
         una sola medía 2.600 px y para mirar una cosa había que pasar por
         las otras seis.

         Son enlaces de verdad (`<a href>`), no pestañas de JavaScript: así
         cada informe tiene su URL y se puede compartir o recargar, y anda con
         `app.js` caído. --}}
    <nav class="spg-tabs" aria-label="Informes">
        @foreach ($secciones as $clave => [$titulo, $ic, $ayuda])
            <a class="spg-tab {{ $seccion === $clave ? 'activo' : '' }}" title="{{ $ayuda }}"
               href="{{ route('reportes.index', array_merge(request()->except(['r', 'page', 'export']), ['r' => $clave])) }}">
                <i class="bi bi-{{ $ic }}"></i><span>{{ $titulo }}</span></a>
        @endforeach
    </nav>

    {{-- ---------------------------------------------------------------
         Las tarjetas, sólo donde dicen algo.

         En Compras el resumen es otro —lo comprado y lo que se debe— y lo pone
         su propia sección; repetir acá las citas sería ruido. --}}
    @if (! in_array($seccion, ['compras'], true))
        <div class="spg-metrics spg-metrics-compacto mb-3">
            <div class="spg-metric"><div class="lbl">Citas del período</div>
                <div class="val">{{ (int) $citas->total }}</div></div>
            <div class="spg-metric"><div class="lbl">Atendidas</div>
                <div class="val">{{ (int) $citas->atendidas }}</div>
                @if ($pctAsistencia !== null)
                    <div class="spg-metric-pie">{{ round($pctAsistencia, 1) }} % del total</div>
                @endif
            </div>
            <div class="spg-metric"><div class="lbl">Canceladas</div>
                <div class="val">{{ (int) $citas->canceladas }}</div>
                @if ($pctCancelacion !== null)
                    <div class="spg-metric-pie">{{ round($pctCancelacion, 1) }} % del total</div>
                @endif
            </div>
            <div class="spg-metric"><div class="lbl">No vino la clienta</div>
                <div class="val">{{ (int) $citas->ausencias }}</div>
                @if ($pctAusencia !== null)
                    <div class="spg-metric-pie">{{ round($pctAusencia, 1) }} % del total</div>
                @endif
            </div>
            <div class="spg-metric"><div class="lbl">Ingresos cobrados</div>
                <div class="val oro">{{ money($ingresos) }}</div></div>
            {{-- Lo devuelto sólo se muestra si hubo devoluciones: un «Gs. 0»
                 fijo sería ruido en un salón que no devuelve nunca. --}}
            @if ($devoluciones > 0)
                <div class="spg-metric"><div class="lbl">Ingreso neto</div>
                    <div class="val oro">{{ money($ingresos - $devoluciones) }}</div>
                    <div class="spg-metric-pie txt-no">− {{ money($devoluciones) }} devuelto</div>
                </div>
            @endif
            <div class="spg-metric"><div class="lbl">Ticket promedio</div>
                <div class="val">{{ money($ticket) }}</div>
                <div class="spg-metric-pie">por cita atendida</div>
            </div>
        </div>
    @endif

    @include('reportes._' . $seccion)

    {{-- El modal manda su propio formulario y **arrastra el período y los
         filtros que están puestos**: si no, el papel saldría de un rango
         distinto al que se está mirando en pantalla. --}}
    <div class="modal fade" id="modalImprimir" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="get" action="{{ route('reportes.imprimir') }}" class="modal-content" target="_blank">
                @foreach (request()->except(['bloques', 'page', 'export', 'r']) as $k => $v)
                    @if (! is_array($v))
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endif
                @endforeach

                <div class="modal-header">
                    <h5 class="modal-title" style="font-size:1rem">
                        <i class="bi bi-printer"></i> ¿Qué querés imprimir?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted-warm" style="font-size:.85rem">
                        Sale con el período y los filtros que tenés puestos ahora.
                        Si no marcás ninguno se imprime el informe entero.
                    </p>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="bloquesTodos"
                               data-marca-todo="#listaBloques" checked>
                        <label class="form-check-label fw-semibold" for="bloquesTodos">Todo</label>
                    </div>

                    <div id="listaBloques">
                        @foreach (\App\Http\Controllers\ReportesController::BLOQUES as $clave => $nombre)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="bloques[]"
                                       value="{{ $clave }}" id="bl{{ $clave }}" checked>
                                <label class="form-check-label" for="bl{{ $clave }}">{{ $nombre }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-oro"><i class="bi bi-printer"></i> Ver para imprimir</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
