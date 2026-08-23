{{-- **Ingresos: de dónde viene la plata.**

     El resumen muestra el total; acá va el desglose. No se repite lo que ya
     está arriba en las tarjetas: lo que se agrega es por dónde entró, cuándo, y
     quién lo generó. --}}
@php
    $hayCobros = count($medios) > 0;
    $maxDia = 0;
    foreach ($ingresoDia as $x) { $maxDia = max($maxDia, (float) $x->total); }
@endphp

@if (! $hayCobros && ! $ingresoDia)
    <div class="spg-panel">
        @include('reportes._sindatos', ['ic' => 'cash-coin',
                 'd' => 'No se registró ningún cobro en este rango.'])
    </div>
@else
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="spg-panel h-100">
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-credit-card"></i> Por medio de pago</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Medio</th><th class="text-end">Cobros</th>
                            <th class="text-end">Total</th><th class="text-end">%</th></tr></thead>
                        <tbody>
                            @foreach ($medios as $m)
                                <tr>
                                    <td>
                                        {{ $m->medio }}
                                        @if ($m->tipo === 'EFECTIVO')<span class="badge-estado e-ok">efectivo</span>@endif
                                    </td>
                                    <td class="text-end">{{ (int) $m->cantidad }}</td>
                                    <td class="text-end">{{ money($m->total) }}</td>
                                    <td class="text-end text-muted-warm">
                                        {{ $ingresos > 0 ? round((float) $m->total * 100 / $ingresos, 1) : 0 }} %</td>
                                </tr>
                            @endforeach
                        </tbody>
                        {{-- **El total tiene que dar lo mismo que la tarjeta de
                             arriba.** Los dos salen de la misma consulta, así que
                             si no coinciden es que un filtro se aplicó en un lado
                             y no en el otro — que es el defecto que tenía la
                             sucursal antes de esta versión. --}}
                        <tfoot><tr>
                            <th>Total cobrado</th>
                            <th class="text-end">
                                @php $tc = 0; foreach ($medios as $m) { $tc += (int) $m->cantidad; } @endphp
                                {{ $tc }}</th>
                            <th class="text-end">{{ money($ingresos) }}</th>
                            <th></th>
                        </tr></tfoot>
                    </table>
                </div>
                @if ($devoluciones > 0)
                    <div class="spg-suma-at mt-3">
                        <div class="spg-suma-fila"><span>Cobrado</span><strong>{{ money($ingresos) }}</strong></div>
                        <div class="spg-suma-fila"><span>Devuelto (notas de crédito)</span>
                            <strong class="txt-no">− {{ money($devoluciones) }}</strong></div>
                        <div class="spg-suma-fila spg-suma-total"><span>Ingreso neto</span>
                            <strong class="val oro">{{ money($ingresos - $devoluciones) }}</strong></div>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-7">
            <div class="spg-panel h-100">
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-graph-up"></i> Cobrado día por día</h2>
                @if ($ingresoDia)
                    @foreach ($ingresoDia as $x)
                        @php $pct = $maxDia > 0 ? round((float) $x->total * 100 / $maxDia) : 0; @endphp
                        <div class="spg-graf-fila">
                            <span class="spg-graf-rot" style="width:86px">{{ fecha($x->dia, 'd/m/Y') }}</span>
                            <span class="spg-graf-pista"><span class="spg-graf-barra" style="width:{{ $pct }}%"></span></span>
                            <span class="spg-graf-val spg-graf-val-ancho">{{ money($x->total) }}</span>
                        </div>
                    @endforeach
                @else
                    <p class="spg-sin-datos">Sin cobros en el período seleccionado.</p>
                @endif
            </div>
        </div>

        <div class="col-lg-6">
            <div class="spg-panel h-100">
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-person-badge"></i> Generado por profesional</h2>
                @if ($equipo)
                    @php $maxGen = 0; foreach ($equipo as $e) { $maxGen = max($maxGen, (float) $e->generado); } @endphp
                    @foreach ($equipo as $e)
                        @continue((float) $e->generado <= 0)
                        <div class="spg-graf-fila">
                            <span class="spg-graf-rot" style="width:150px">{{ $e->profesional }}</span>
                            <span class="spg-graf-pista"><span class="spg-graf-barra"
                                  style="width:{{ $maxGen > 0 ? round((float) $e->generado * 100 / $maxGen) : 0 }}%"></span></span>
                            <span class="spg-graf-val spg-graf-val-ancho">{{ money($e->generado) }}</span>
                        </div>
                    @endforeach
                @else
                    <p class="spg-sin-datos">Sin atenciones en el período seleccionado.</p>
                @endif
            </div>
        </div>

        <div class="col-lg-6">
            <div class="spg-panel h-100">
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-scissors"></i> Generado por servicio</h2>
                @if ($servicios)
                    @php
                        $porIngreso = $servicios;
                        usort($porIngreso, fn ($a, $b) => (float) $b->ingreso_generado <=> (float) $a->ingreso_generado);
                        $porIngreso = array_slice($porIngreso, 0, 10);
                        $maxIng = (float) ($porIngreso[0]->ingreso_generado ?? 0);
                    @endphp
                    @foreach ($porIngreso as $s)
                        <div class="spg-graf-fila">
                            <span class="spg-graf-rot" style="width:150px">{{ $s->servicio }}</span>
                            <span class="spg-graf-pista"><span class="spg-graf-barra"
                                  style="width:{{ $maxIng > 0 ? round((float) $s->ingreso_generado * 100 / $maxIng) : 0 }}%"></span></span>
                            <span class="spg-graf-val spg-graf-val-ancho">{{ money($s->ingreso_generado) }}</span>
                        </div>
                    @endforeach
                @else
                    <p class="spg-sin-datos">Sin servicios en el período seleccionado.</p>
                @endif
            </div>
        </div>

        {{-- Por sucursal sólo aparece mirando todas: con una elegida sería una
             fila repitiendo la tarjeta de arriba. --}}
        @if ($ingresoSucursal && count($ingresoSucursal) > 1)
            <div class="col-12">
                <div class="spg-panel">
                    <h2 class="spg-form-titulo mb-2"><i class="bi bi-shop"></i> Cobrado por sucursal</h2>
                    @php $maxSuc = 0; foreach ($ingresoSucursal as $s) { $maxSuc = max($maxSuc, (float) $s->total); } @endphp
                    @foreach ($ingresoSucursal as $s)
                        <div class="spg-graf-fila">
                            <span class="spg-graf-rot" style="width:170px">{{ $s->sucursal }}</span>
                            <span class="spg-graf-pista"><span class="spg-graf-barra"
                                  style="width:{{ $maxSuc > 0 ? round((float) $s->total * 100 / $maxSuc) : 0 }}%"></span></span>
                            <span class="spg-graf-val spg-graf-val-ancho">{{ money($s->total) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endif
