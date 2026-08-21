{{--
    Portada de un módulo: las tarjetas de sus submódulos.

    Es el tercer nivel de navegación —¿qué hay dentro de este módulo?— y solo
    muestra lo que el rol puede abrir: quien únicamente registra atenciones no
    ve Nueva cita ni las excepciones de la agenda.

        <x-landing titulo="Citas" icono="calendar-event"
                   desc="…" :subs="$subs" />
--}}
@props(['titulo', 'icono', 'desc' => '', 'subs' => []])

@php use App\Servicios\Navegacion; @endphp

<div class="spg-page-head d-flex justify-content-between align-items-end">
    <div>
        <a class="spg-back" href="{{ Navegacion::url('panel') }}"><i class="bi bi-arrow-left"></i> Panel</a>
        <h1 class="mt-1"><i class="bi bi-{{ $icono }}"></i> {{ $titulo }}</h1>
        <div class="sub">{{ $desc }}</div>
    </div>
</div>

@php
    // **Las tarjetas se pueden agrupar.** Con siete sueltas —el caso de
    // Tesorería— no se ve qué va con qué: facturar, cobrar, el cajón y pagar
    // son cuatro trabajos distintos. Sin `grupo` se dibuja como siempre.
    $porGrupo = [];
    foreach ($subs as $s) {
        $porGrupo[$s['grupo'] ?? ''][] = $s;
    }
@endphp

@foreach ($porGrupo as $grupo => $tarjetas)
    @if ($grupo !== '')
        <h2 class="spg-form-titulo mt-3 mb-2">{{ $grupo }}</h2>
    @endif
    <div class="spg-cards">
        @foreach ($tarjetas as $s)
            @php $url = Navegacion::url($s['ruta']) . ($s['ancla'] ?? ''); @endphp
            @if (Navegacion::url($s['ruta']))
                <a class="spg-card" href="{{ $url }}">
                    <div class="ic"><i class="bi bi-{{ $s['ic'] }}"></i></div>
                    <h3>{{ $s['t'] }}</h3>
                    <p>{{ $s['d'] }}</p>
                </a>
            @endif
        @endforeach
    </div>
@endforeach
