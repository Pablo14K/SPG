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

<div class="spg-cards">
    @foreach ($subs as $s)
        @php $url = Navegacion::url($s['ruta']); @endphp
        @if ($url)
            <a class="spg-card" href="{{ $url }}">
                <div class="ic"><i class="bi bi-{{ $s['ic'] }}"></i></div>
                <h3>{{ $s['t'] }}</h3>
                <p>{{ $s['d'] }}</p>
            </a>
        @endif
    @endforeach
</div>
