{{-- **Por sucursal: los locales uno al lado del otro.**

     El selector deja mirar una por vez, pero para decidir dónde reforzar hace
     falta verlas juntas — con un local por consulta hay que anotar los números
     en un papel y compararlos a mano.

     Con una sucursal elegida esta pantalla no tiene qué comparar, y lo dice en
     vez de mostrar una fila que repite el resumen. --}}
@if ($sucElegida !== '')
    <div class="spg-panel">
        <div class="spg-vacio">
            <i class="bi bi-shop"></i>
            <div class="t">Estás mirando un solo local</div>
            <div class="d">
                Para comparar sucursales, poné el filtro de arriba en
                <strong>Todas</strong>. Con una elegida, esta tabla tendría una fila
                y diría lo mismo que el resumen.
            </div>
        </div>
    </div>
@elseif (! $porSucursal)
    <div class="spg-panel">@include('reportes._sindatos', ['ic' => 'shop'])</div>
@else
    @php
        // Los ingresos vienen de otra consulta —salen de la caja, no de la
        // cita— así que se cruzan por nombre para armar una sola tabla.
        $ingPorSuc = [];
        foreach ($ingresoSucursal as $i) { $ingPorSuc[$i->sucursal] = (float) $i->total; }
        $maxCitas = 0;
        foreach ($porSucursal as $s) { $maxCitas = max($maxCitas, (int) $s->citas); }
    @endphp

    <div class="spg-panel">
        <h2 class="spg-form-titulo mb-2"><i class="bi bi-shop"></i> Comparativa de locales</h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" data-ordenable>
                <thead>
                    <tr>
                        <th>Sucursal</th>
                        <th class="text-end">Citas</th>
                        <th style="width:16%">Participación</th>
                        <th class="text-end">Atendidas</th>
                        <th class="text-end">Canceladas</th>
                        <th class="text-end">No vino</th>
                        <th class="text-end">Clientas</th>
                        <th class="text-end">Servicios</th>
                        <th class="text-end">Cobrado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($porSucursal as $s)
                        <tr>
                            <td>{{ $s->sucursal }}</td>
                            <td class="text-end">{{ (int) $s->citas }}</td>
                            <td>
                                <span class="spg-graf-pista"><span class="spg-graf-barra"
                                      style="width:{{ $maxCitas ? round((int) $s->citas * 100 / $maxCitas) : 0 }}%"></span></span>
                            </td>
                            <td class="text-end">{{ (int) $s->atendidas }}</td>
                            <td class="text-end">{{ (int) $s->canceladas }}</td>
                            <td class="text-end">{{ (int) $s->ausentes }}</td>
                            <td class="text-end">{{ (int) $s->clientes }}</td>
                            <td class="text-end">{{ (int) $s->servicios }}</td>
                            <td class="text-end">
                                {{-- **Cobrado sale de la CAJA**, no de la cita: es donde
                                     entró la plata. Un local puede no tener cobros y sí
                                     citas —si todavía no cerró—, y eso también es una
                                     respuesta. --}}
                                @if (isset($ingPorSuc[$s->sucursal]))
                                    {{ money($ingPorSuc[$s->sucursal]) }}
                                @else
                                    <span class="text-muted-warm">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    {{-- **Los totales se calculan antes**, no dentro del `<th>`:
                         `@endphp` pegado a `{{ }}` no lo compila Blade y sale el
                         marcador crudo en la pantalla. --}}
                    @php
                        $tCitas = $tAtend = $tCanc = $tAus = $tServ = 0;
                        foreach ($porSucursal as $s) {
                            $tCitas += (int) $s->citas;
                            $tAtend += (int) $s->atendidas;
                            $tCanc  += (int) $s->canceladas;
                            $tAus   += (int) $s->ausentes;
                            $tServ  += (int) $s->servicios;
                        }
                    @endphp
                    <tr>
                        <th>Total</th>
                        <th class="text-end">{{ $tCitas }}</th>
                        <th></th>
                        <th class="text-end">{{ $tAtend }}</th>
                        <th class="text-end">{{ $tCanc }}</th>
                        <th class="text-end">{{ $tAus }}</th>
                        <th></th>
                        <th class="text-end">{{ $tServ }}</th>
                        <th class="text-end">{{ money(array_sum($ingPorSuc)) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif
