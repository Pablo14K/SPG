{{-- **Servicios: qué se hace más y cuánto deja.**

     El resumen muestra los ocho primeros; acá va el catálogo entero con el
     porcentaje sobre el total, que es lo que permite decir «la mitad de lo que
     hacemos son cortes» sin sacar la cuenta a mano. --}}
@if ($servicios)
    <div class="spg-panel">
        <h2 class="spg-form-titulo mb-2"><i class="bi bi-scissors"></i> Servicios realizados</h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Servicio</th><th>Categoría</th>
                        <th class="text-end">Veces</th>
                        <th style="width:22%">Participación</th>
                        <th class="text-end">%</th>
                        <th class="text-end">Ingreso generado</th>
                    </tr>
                </thead>
                <tbody>
                    @php $maxVeces = (int) ($servicios[0]->veces_realizado ?? 1); @endphp
                    @foreach ($servicios as $s)
                        @php
                            $veces = (int) $s->veces_realizado;
                            $pctTotal = $totalServicios ? $veces * 100 / $totalServicios : 0;
                            $pctBarra = $maxVeces ? round($veces * 100 / $maxVeces) : 0;
                        @endphp
                        <tr>
                            <td>{{ $s->servicio }}</td>
                            <td class="text-muted-warm">{{ $s->categoria }}</td>
                            <td class="text-end">{{ $veces }}</td>
                            <td>
                                <span class="spg-graf-pista"><span class="spg-graf-barra"
                                      style="width:{{ $pctBarra }}%"></span></span>
                            </td>
                            <td class="text-end text-muted-warm">{{ round($pctTotal, 1) }} %</td>
                            <td class="text-end">{{ money($s->ingreso_generado) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="2">Total</th>
                        <th class="text-end">{{ $totalServicios }}</th>
                        <th></th><th></th>
                        <th class="text-end">
                            @php $tot = 0; foreach ($servicios as $s) { $tot += (float) $s->ingreso_generado; } @endphp
                            {{ money($tot) }}
                        </th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="text-muted-warm mt-2 mb-0" style="font-size:.8rem">
            <i class="bi bi-info-circle"></i> El «ingreso generado» es el precio de lista de lo
            que se hizo. Lo <strong>cobrado</strong> puede ser menor: hay descuentos, canjes y
            saldos pendientes — ese número está en <strong>Ingresos</strong>.
        </p>
    </div>
@else
    <div class="spg-panel">@include('reportes._sindatos', ['ic' => 'scissors'])</div>
@endif
