{{-- **Resumen: sólo lo que se mira todos los días.**

     Antes esta pantalla tenía las siete tablas apiladas y medía 2.600 px. Acá
     van los números que importan y tres gráficos; el detalle vive en su propia
     pestaña, que es a donde se entra cuando hace falta. --}}
@php
    $dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
             5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
    $topServicios = array_slice($servicios, 0, 8);
@endphp

<div class="row g-3">
    <div class="col-lg-6">
        <div class="spg-panel h-100">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-scissors"></i> Servicios más solicitados</h2>
            @if ($topServicios)
                @include('reportes._barras', [
                    'filas' => $topServicios,
                    'max' => (int) ($topServicios[0]->veces_realizado ?? 0),
                    'rotulo' => fn ($s) => $s->servicio,
                    'valor' => fn ($s) => (int) $s->veces_realizado,
                    'ancho' => '150px',
                ])
                @if (count($servicios) > 8)
                    <a class="spg-graf-mas" href="{{ route('reportes.index', request()->except('r') + ['r' => 'servicios']) }}">
                        Ver los {{ count($servicios) }} servicios <i class="bi bi-arrow-right"></i></a>
                @endif
            @else
                @include('reportes._sindatos', ['ic' => 'scissors'])
            @endif
        </div>
    </div>

    <div class="col-lg-6">
        <div class="spg-panel h-100">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-cash-coin"></i> Ingresos por medio de pago</h2>
            @if ($medios)
                @php $maxMedio = 0; foreach ($medios as $m) { $maxMedio = max($maxMedio, (float) $m->total); } @endphp
                @foreach ($medios as $m)
                    @php $pct = $maxMedio > 0 ? round((float) $m->total * 100 / $maxMedio) : 0; @endphp
                    <div class="spg-graf-fila">
                        <span class="spg-graf-rot" style="width:150px">
                            {{ $m->medio }}
                            @if ($m->tipo === 'EFECTIVO')<span class="badge-estado e-ok">efectivo</span>@endif
                        </span>
                        <span class="spg-graf-pista"><span class="spg-graf-barra" style="width:{{ $pct }}%"></span></span>
                        <span class="spg-graf-val spg-graf-val-ancho">{{ money($m->total) }}</span>
                    </div>
                @endforeach
            @else
                @include('reportes._sindatos', ['ic' => 'cash-coin', 'd' => 'No se registró ningún cobro en este rango.'])
            @endif
        </div>
    </div>

    <div class="col-12">
        <div class="spg-panel">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-calendar-week"></i> Demanda por día</h2>
            @include('reportes._barras', [
                'filas' => $demandaDia,
                'max' => $maxDemandaDia,
                'rotulo' => fn ($x) => $dias[(int) $x->dia] ?? $x->dia,
                'valor' => fn ($x) => (int) $x->citas,
                'ancho' => '90px',
                'vacio' => 'Sin citas en el período seleccionado.',
            ])
        </div>
    </div>
</div>
