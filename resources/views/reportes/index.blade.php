@extends('layout.app')

@section('titulo', 'Reportes')

@section('contenido')
    <x-encabezado
        :sub="'Del <strong>' . fecha($desde, 'd/m/Y') . '</strong> al <strong>' . fecha($hasta, 'd/m/Y') . '</strong>. Los ingresos son los <strong>cobros registrados</strong>, que es la plata que entró de verdad, no lo facturado.'"
        :accion="['ruta' => 'reportes.imprimir', 't' => 'Ver para imprimir', 'ic' => 'printer']" />

    <div class="spg-panel mb-3">
        <x-filtros :f="$f" />
        <div class="mt-2">
            <a class="btn btn-sm btn-outline-neutro"
               href="{{ route('reportes.index', ['desde' => $inicio, 'hasta' => date('Y-m-d')]) }}">
                <i class="bi bi-clock-history"></i> Histórico (todo lo que haya)</a>
            <a class="btn btn-sm btn-outline-neutro"
               href="{{ route('reportes.index', ['desde' => date('Y-m-01'), 'hasta' => date('Y-m-t')]) }}">
                Este mes</a>
            <a class="btn btn-sm btn-outline-neutro"
               href="{{ route('reportes.index', ['desde' => date('Y-m-01', strtotime('-1 month')), 'hasta' => date('Y-m-t', strtotime('-1 month'))]) }}">
                Mes pasado</a>
        </div>

        {{-- Qué se lleva al papel. Antes el informe salía SIEMPRE entero, así
             que quien quería sólo las citas imprimía seis hojas para usar una.
             Va como formulario GET y arrastra el período y los filtros que ya
             estaban puestos: si no, el papel saldría de otro rango que el que
             se está mirando. --}}
        <form method="get" action="{{ route('reportes.imprimir') }}"
              class="d-flex align-items-end gap-2 flex-wrap mt-3 pt-3 border-top">
            @foreach (['desde', 'hasta', 'prof', 'suc'] as $campo)
                @if (request()->query($campo))
                    <input type="hidden" name="{{ $campo }}" value="{{ request()->query($campo) }}">
                @endif
            @endforeach
            {{-- Casillas y no un `<select>`: con el select había que elegir UN
                 bloque, y lo que se pide de verdad es armar la combinación —el
                 resumen y el equipo, por ejemplo—. Vienen todas marcadas, así
                 que quien no toca nada imprime el informe entero, como antes.

                 La casilla maestra es la misma pieza que usa la matriz de
                 permisos (`data-marca-todo` en app.js): refleja lo que hay
                 marcado y prende o apaga todo de una. No lleva `name`, así que
                 no se envía. --}}
            <div style="flex:1;min-width:260px">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <label class="form-label mb-0">Qué imprimir</label>
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="bloquesTodos"
                               data-marca-todo="#listaBloques" checked>
                        <label class="form-check-label" for="bloquesTodos" style="font-size:.82rem">Todo</label>
                    </div>
                </div>
                <div id="listaBloques" class="d-flex flex-wrap gap-3 p-2 rounded"
                     style="border:1px solid var(--gris-calido)">
                    @foreach (\App\Http\Controllers\ReportesController::BLOQUES as $clave => $nombre)
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="bloques[]"
                                   value="{{ $clave }}" id="bl{{ $clave }}" checked>
                            <label class="form-check-label" for="bl{{ $clave }}"
                                   style="font-size:.85rem">{{ $nombre }}</label>
                        </div>
                    @endforeach
                </div>
            </div>
            <button class="btn btn-sm btn-oro"><i class="bi bi-printer"></i> Ver para imprimir</button>
        </form>
    </div>

    <div class="spg-metrics mb-3">
        <div class="spg-metric"><div class="lbl">Citas del período</div><div class="val">{{ (int) $citas->total }}</div></div>
        <div class="spg-metric"><div class="lbl">Atendidas</div><div class="val">{{ (int) $citas->atendidas }}</div></div>
        <div class="spg-metric">
            <div class="lbl">Canceladas / ausentes</div>
            <div class="val">{{ (int) $citas->canceladas }} / {{ (int) $citas->ausencias }}</div>
        </div>
        <div class="spg-metric"><div class="lbl">Ingresos cobrados</div><div class="val oro">{{ money($ingresos) }}</div></div>
        {{-- Lo devuelto sólo se muestra si hubo devoluciones: un «Gs. 0» fijo
             sería ruido en la pantalla de un salón que no devuelve nunca. --}}
        @if ($devoluciones > 0)
            <div class="spg-metric">
                <div class="lbl">Devuelto (notas de crédito)</div>
                <div class="val txt-no">− {{ money($devoluciones) }}</div>
            </div>
            <div class="spg-metric">
                <div class="lbl">Ingreso neto</div>
                <div class="val oro">{{ money($ingresos - $devoluciones) }}</div>
            </div>
        @endif
        <div class="spg-metric"><div class="lbl">Ticket promedio</div><div class="val">{{ money($ticket) }}</div></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="spg-panel">
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-scissors"></i> Servicios más solicitados</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Servicio</th><th>Categoría</th><th class="text-end">Veces</th>
                            <th class="text-end">Ingreso</th></tr></thead>
                        <tbody>
                            @forelse ($servicios as $s)
                                <tr>
                                    <td>{{ $s->servicio }}</td>
                                    <td class="text-muted-warm">{{ $s->categoria }}</td>
                                    <td class="text-end">{{ (int) $s->veces_realizado }}</td>
                                    <td class="text-end">{{ money($s->ingreso_generado) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted-warm py-3">Sin servicios en el período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="spg-panel">
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-cash-coin"></i> Cómo pagó la gente</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Medio</th><th class="text-end">Cobros</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                            @forelse ($medios as $m)
                                <tr>
                                    <td>
                                        {{ $m->medio }}
                                        @if ($m->tipo === 'EFECTIVO')
                                            <span class="badge-estado e-ok">efectivo</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ (int) $m->cantidad }}</td>
                                    <td class="text-end">{{ money($m->total) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted-warm py-3">Sin cobros en el período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="spg-panel">
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-people"></i> El equipo</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        {{-- Ausencias y canceladas por profesional: el total del
                             período no dice a quién le fallan más, y ahí puede
                             estar el horario o el recordatorio. --}}
                        <thead><tr><th>Profesional</th><th class="text-end">Citas</th><th class="text-end">Atendidas</th>
                            <th class="text-end">Ausencias</th><th class="text-end">Canceladas</th>
                            <th class="text-end">Servicios</th>
                            <th class="text-end">Generado</th><th class="text-end">Comisión</th>
                            <th class="text-end">Puntaje</th></tr></thead>
                        <tbody>
                            @forelse ($equipo as $e)
                                <tr>
                                    <td>{{ $e->profesional }}</td>
                                    <td class="text-end">{{ (int) $e->citas }}</td>
                                    <td class="text-end">{{ (int) $e->atendidas }}</td>
                                    <td class="text-end {{ (int) $e->ausencias ? 'txt-no' : '' }}">
                                        {{ (int) $e->ausencias ?: '—' }}</td>
                                    <td class="text-end">{{ (int) $e->canceladas ?: '—' }}</td>
                                    <td class="text-end">{{ (int) $e->servicios }}</td>
                                    <td class="text-end">{{ money($e->generado) }}</td>
                                    <td class="text-end @if ($e->tiene_comision) txt-oro @else text-muted-warm @endif">
                                        @if ($e->tiene_comision)
                                            {{ money($e->comision) }}
                                        @else
                                            <span title="Todavía no se le cargó una comisión en Seguridad → Comisiones">sin cargar</span>
                                        @endif
                                    </td>
                                    <td class="text-end txt-oro">{{ $e->puntaje ? cant($e->puntaje) . ' ★' : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="text-center text-muted-warm py-3">Sin actividad en el período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="spg-panel">
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-clock"></i> Demanda por hora</h2>
                @forelse ($demanda as $h)
                    @php $ancho = $maxDemanda ? round((int) $h->citas * 100 / $maxDemanda) : 0; @endphp
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span style="width:48px;font-size:.8rem" class="text-muted-warm">
                            {{ sprintf('%02d:00', (int) $h->hora) }}</span>
                        <div style="flex:1;background:var(--gris-calido);border-radius:4px;height:14px;overflow:hidden">
                            <div style="width:{{ $ancho }}%;background:var(--oro);height:100%"></div>
                        </div>
                        <span style="width:28px;text-align:right;font-size:.8rem">{{ (int) $h->citas }}</span>
                    </div>
                @empty
                    <p class="text-muted-warm mb-0" style="font-size:.85rem">Sin citas en el período.</p>
                @endforelse
            </div>

            {{-- Por hora se ve a qué hora reforzar; por día, qué días conviene
                 tener más gente. Son dos preguntas distintas, por eso van las
                 dos. `dia` viene 1=lunes … 7=domingo, que es la convención del
                 proyecto (`turno_dia.dia_semana`). --}}
            @php
                $dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
                         5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
            @endphp
            <div class="spg-panel mt-3">
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-calendar-week"></i> Demanda por día</h2>
                @forelse ($demandaDia as $x)
                    @php $ancho = $maxDemandaDia ? round((int) $x->citas * 100 / $maxDemandaDia) : 0; @endphp
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span style="width:70px;font-size:.8rem" class="text-muted-warm">
                            {{ $dias[(int) $x->dia] ?? $x->dia }}</span>
                        <div style="flex:1;background:var(--gris-calido);border-radius:4px;height:14px;overflow:hidden">
                            <div style="width:{{ $ancho }}%;background:var(--oro);height:100%"></div>
                        </div>
                        <span style="width:28px;text-align:right;font-size:.8rem">{{ (int) $x->citas }}</span>
                    </div>
                @empty
                    <p class="text-muted-warm mb-0" style="font-size:.85rem">Sin citas en el período.</p>
                @endforelse
            </div>
        </div>

        @if ($prov)
            <div class="col-12">
                <div class="spg-panel">
                    <h2 class="spg-form-titulo mb-2">
                        <i class="bi bi-truck"></i> Deuda con proveedores
                        <span class="text-muted-warm" style="font-weight:400;font-size:.8rem">
                            — no depende del período: es deuda viva</span>
                    </h2>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Proveedor</th><th>Vencimiento</th><th class="text-end">Saldo</th></tr></thead>
                            <tbody>
                                @foreach ($prov as $p)
                                    <tr>
                                        <td>{{ $p->proveedor }}</td>
                                        <td>
                                            @if ($p->vencida)<span class="badge-estado e-no">vencida</span>@endif
                                            <span class="text-muted-warm">
                                                {{ $p->vencimiento ? fecha($p->vencimiento, 'd/m/Y') : '—' }}</span>
                                        </td>
                                        <td class="text-end">{{ money($p->saldo) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
