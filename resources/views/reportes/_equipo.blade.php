{{-- **Profesionales.**

     La tabla tenía diez columnas y no entraba: había que scrollear en
     horizontal para llegar a la comisión, que es justamente la que se busca.
     Ahora se parte en dos vistas —Atención y Producción— con las mismas filas,
     así cada una entra en pantalla sin sacrificar ningún dato.

     Se ordena en el navegador (`data-ordenable`), sin recargar: es la misma
     tabla ya dibujada. --}}
@if ($equipo)
    <div class="spg-panel">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <h2 class="spg-form-titulo mb-0"><i class="bi bi-people"></i> El equipo</h2>
            <ul class="nav nav-pills spg-subtabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill"
                    data-bs-target="#eqAtencion" type="button">Atención</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="pill"
                    data-bs-target="#eqProduccion" type="button">Producción</button></li>
            </ul>
        </div>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="eqAtencion">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" data-ordenable>
                        <thead>
                            <tr>
                                <th>Profesional</th>
                                <th class="text-end">Citas</th>
                                <th class="text-end">Atendidas</th>
                                <th class="text-end">No vino la clienta</th>
                                <th class="text-end">Canceladas</th>
                                <th class="text-end">Faltó</th>
                                <th class="text-end">Puntaje</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($equipo as $e)
                                <tr>
                                    <td>{{ $e->profesional }}</td>
                                    <td class="text-end">{{ (int) $e->citas }}</td>
                                    <td class="text-end">{{ (int) $e->atendidas }}</td>
                                    <td class="text-end">{{ (int) $e->clienta_no_vino }}</td>
                                    <td class="text-end">{{ (int) $e->canceladas }}</td>
                                    {{-- **Dos ausencias distintas.** «No vino la clienta» sale
                                         de la cita; «Faltó» sale del fichaje y es del
                                         profesional. Juntarlas en una columna fue el error
                                         que corrigió la 7.35.0. --}}
                                    <td class="text-end">
                                        {{ (int) $e->falto }}
                                        @if ((int) $e->falto_sin_aviso > 0)
                                            <span class="text-muted-warm" style="font-size:.75rem">
                                                ({{ (int) $e->falto_sin_aviso }} sin aviso)</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if ($e->puntaje !== null)
                                            {{ $e->puntaje }} <i class="bi bi-star-fill txt-oro"></i>
                                        @else
                                            <span class="text-muted-warm">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="eqProduccion">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" data-ordenable>
                        <thead>
                            <tr>
                                <th>Profesional</th>
                                <th class="text-end">Servicios</th>
                                <th style="width:20%">Participación</th>
                                <th class="text-end">Generado</th>
                                <th class="text-end">Comisión</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $maxGen = 0;
                                foreach ($equipo as $e) { $maxGen = max($maxGen, (float) $e->generado); }
                            @endphp
                            @foreach ($equipo as $e)
                                <tr>
                                    <td>{{ $e->profesional }}</td>
                                    <td class="text-end">{{ (int) $e->servicios }}</td>
                                    <td>
                                        <span class="spg-graf-pista"><span class="spg-graf-barra"
                                              style="width:{{ $maxGen > 0 ? round((float) $e->generado * 100 / $maxGen) : 0 }}%"></span></span>
                                    </td>
                                    <td class="text-end">{{ money($e->generado) }}</td>
                                    {{-- Un «Gs. 0» es ambiguo: casi siempre no es que ganó
                                         cero, es que NADIE LE CARGÓ LA COMISIÓN. Sin esto el
                                         informe miente por omisión. --}}
                                    <td class="text-end @if ($e->tiene_comision) txt-oro @else text-muted-warm @endif">
                                        @if ($e->tiene_comision)
                                            {{ money($e->comision) }}
                                        @else
                                            sin cargar
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="text-end">
                                    @php $ts = 0; foreach ($equipo as $e) { $ts += (int) $e->servicios; } @endphp
                                    {{ $ts }}</th>
                                <th></th>
                                <th class="text-end">
                                    @php $tg = 0; foreach ($equipo as $e) { $tg += (float) $e->generado; } @endphp
                                    {{ money($tg) }}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
@else
    <div class="spg-panel">@include('reportes._sindatos', ['ic' => 'people'])</div>
@endif
