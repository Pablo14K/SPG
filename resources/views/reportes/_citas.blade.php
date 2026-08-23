{{-- **Citas: los estados y cuándo se llena el salón.**

     Los dos gráficos de demanda van en pestañas y no apilados: contestan
     preguntas distintas —a qué hora reforzar, qué días tener más gente— y se
     miran de a una. Apilados, el segundo queda siempre abajo del pliegue. --}}
@php
    $dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
             5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
    $totalEstados = 0;
    foreach ($estados as $e) { $totalEstados += (int) $e->cantidad; }
@endphp

<div class="row g-3">
    <div class="col-lg-5">
        <div class="spg-panel h-100">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-list-check"></i> Cómo terminaron</h2>
            {{-- **Todos los estados que existan**, no una lista escrita a mano:
                 así uno nuevo aparece solo. Es el mismo error que tuvo el panel
                 cuando enumeraba estados y se quedó corto al entrar «Atrasada». --}}
            @if ($estados)
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Estado</th><th class="text-end">Citas</th><th class="text-end">%</th></tr></thead>
                        <tbody>
                            @foreach ($estados as $e)
                                <tr>
                                    <td>{!! estado_badge($e->estado) !!}</td>
                                    <td class="text-end">{{ (int) $e->cantidad }}</td>
                                    <td class="text-end text-muted-warm">
                                        {{ $totalEstados ? round((int) $e->cantidad * 100 / $totalEstados, 1) : 0 }} %</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr>
                            <th>Total</th><th class="text-end">{{ $totalEstados }}</th><th></th>
                        </tr></tfoot>
                    </table>
                </div>
            @else
                @include('reportes._sindatos', ['ic' => 'calendar-x'])
            @endif
        </div>
    </div>

    <div class="col-lg-7">
        <div class="spg-panel h-100">
            <ul class="nav nav-pills spg-subtabs mb-3" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill"
                    data-bs-target="#demDia" type="button"><i class="bi bi-calendar-week"></i> Por día</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="pill"
                    data-bs-target="#demHora" type="button"><i class="bi bi-clock"></i> Por hora</button></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="demDia">
                    @include('reportes._barras', [
                        'filas' => $demandaDia, 'max' => $maxDemandaDia,
                        'rotulo' => fn ($x) => $dias[(int) $x->dia] ?? $x->dia,
                        'valor' => fn ($x) => (int) $x->citas,
                        'ancho' => '90px', 'vacio' => 'Sin citas en el período seleccionado.',
                    ])
                </div>
                <div class="tab-pane fade" id="demHora">
                    @include('reportes._barras', [
                        'filas' => $demanda, 'max' => $maxDemanda,
                        'rotulo' => fn ($x) => sprintf('%02d:00', (int) $x->hora),
                        'valor' => fn ($x) => (int) $x->citas,
                        'ancho' => '54px', 'vacio' => 'Sin citas en el período seleccionado.',
                    ])
                </div>
            </div>
            <p class="text-muted-warm mt-2 mb-0" style="font-size:.8rem">
                <i class="bi bi-info-circle"></i> Por hora se ve a qué hora reforzar; por día,
                qué días conviene tener más gente.
            </p>
        </div>
    </div>
</div>
