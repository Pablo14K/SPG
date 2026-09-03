@extends('layout.app')

@section('titulo', 'Mi portal')

@section('contenido')
    <div class="spg-page-head">
        <h1>Hola, {{ session('nombre') }}</h1>
        <div class="sub">Reservá tu cita, mirá tus turnos y dejanos tu opinión.</div>
    </div>

    @if ($proxima)
        <div class="spg-panel mb-3" style="border-left:3px solid var(--oro)">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-calendar-event"></i> Tu próxima cita</h2>
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <div style="font-size:1.1rem">
                        <strong>{{ fecha($proxima->fecha_hora) }}</strong>
                        {{-- **El estado va acá, y no es un adorno.** Sin él la
                             tarjeta anunciaba «Tu próxima cita» igual para una
                             cita normal y para una que se pasó de hora, así que
                             el inicio y «Mis citas» parecían decir cosas
                             distintas de la misma cita. Sale del MISMO estado
                             que la lista, así que no se pueden desfasar. --}}
                        {!! estado_badge($proxima->estado_nombre ?? '') !!}
                    </div>
                    <div class="text-muted-warm">
                        {{ $proxima->servicios ?: 'Sin servicios cargados' }}
                        · con {{ $proxima->profesional }}
                        · {{ (int) $proxima->duracion_min }} min
                    </div>
                    @if (($proxima->estado_nombre ?? '') === 'Atrasada')
                        {{-- Atrasada quiere decir que la hora pasó y nadie la
                             tocó todavía. La clienta necesita ver eso —es la
                             que va a reclamar—, pero anunciada a secas como
                             «próxima» parece que todo está bien. --}}
                        <div class="txt-no mt-1" style="font-size:.85rem">
                            Te esperábamos a esta hora. Si ya no vas a poder venir, avisanos.
                        </div>
                    @endif
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    @if ($enCurso)
                        <a class="btn btn-oro" href="{{ route('portal.atencion', ['id' => $proxima->id_cita]) }}">
                            <i class="bi bi-eye"></i> Ver cómo va</a>
                    @endif
                    <a class="btn btn-outline-neutro" href="{{ route('portal.citas') }}">Mis citas</a>
                </div>
            </div>
        </div>
    @else
        <div class="spg-panel mb-3 text-center">
            <p class="mb-2">No tenés ninguna cita reservada.</p>
            <a class="btn btn-oro" href="{{ route('portal.reservar') }}">
                <i class="bi bi-calendar-plus"></i> Reservar una cita</a>
        </div>
    @endif

    <div class="spg-cards">
        <a class="spg-card" href="{{ route('portal.reservar') }}">
            <div class="ic"><i class="bi bi-calendar-plus"></i></div>
            <h3>Reservar cita</h3>
            <p>Elegí servicio, profesional y horario</p>
        </a>
        <a class="spg-card" href="{{ route('portal.citas') }}">
            <div class="ic"><i class="bi bi-calendar-week"></i></div>
            <h3>Mis citas</h3>
            <p>Próximas y anteriores</p>
        </a>
        <a class="spg-card" href="{{ route('portal.promociones') }}">
            <div class="ic"><i class="bi bi-percent"></i></div>
            <h3>Promociones</h3>
            <p>Tu nivel y los descuentos vigentes</p>
        </a>
        <a class="spg-card" href="{{ route('portal.valoraciones') }}">
            <div class="ic"><i class="bi bi-star"></i></div>
            <h3>Valoraciones</h3>
            <p>Contanos cómo te fue</p>
        </a>
        <a class="spg-card" href="{{ route('portal.preferencias') }}">
            <div class="ic"><i class="bi bi-bell"></i></div>
            <h3>Mis recordatorios</h3>
            <p>Con cuánta anticipación te avisamos</p>
        </a>
    </div>
@endsection
