@extends('layout.app')

@section('titulo', 'Nuestro equipo')

@section('contenido')
    <div class="spg-page-head">
        <a class="spg-back" href="{{ route('portal.reservar') }}">
            <i class="bi bi-arrow-left"></i> Reservar</a>
        <h1 class="mt-1">Nuestro equipo</h1>
        <div class="sub">Quién te puede atender y qué hace cada una.</div>
    </div>

    {{-- **Vive acá y no en la pantalla de reservar.** Ahí ya hay que elegir
         servicios, profesional, día y hora: el equipo entero desplegado abajo
         compite con lo único que se viene a hacer. Esto se mira antes, una vez,
         y por eso tiene su propia pantalla. --}}
    @if (count($sucursales) > 1)
        <div class="spg-panel mb-3">
            <form method="get" class="d-flex gap-2 align-items-end flex-wrap">
                <div>
                    <label class="form-label" for="suc">Local</label>
                    <select class="form-select form-select-sm" id="suc" name="sucursal"
                            onchange="this.form.submit()">
                        @foreach ($sucursales as $s)
                            <option value="{{ $s->id_sucursal }}"
                                @selected((int) $sucursal === (int) $s->id_sucursal)>{{ $s->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <noscript><button class="btn btn-sm btn-oro">Ver</button></noscript>
            </form>
        </div>
    @endif

    @if (! $equipo)
        <div class="spg-panel">
            <div class="spg-vacio">
                <i class="bi bi-people"></i>
                <div class="t">Todavía no hay profesionales en este local</div>
                <div class="d">Podés reservar igual: te asignamos a quien esté disponible.</div>
            </div>
        </div>
    @else
        <div class="row g-3">
            @foreach ($equipo as $p)
                <div class="col-md-6 col-lg-4">
                    <div class="spg-panel h-100">
                        <h2 class="spg-form-titulo mb-1">
                            <i class="bi bi-person-circle"></i> {{ $p->nombre }}</h2>

                        @if ($p->puntaje !== null)
                            <div class="mb-2" style="font-size:.85rem">
                                <span class="txt-oro">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <i class="bi bi-star{{ $i <= round((float) $p->puntaje) ? '-fill' : '' }}"></i>
                                    @endfor
                                </span>
                                <span class="text-muted-warm">{{ $p->puntaje }}</span>
                            </div>
                        @endif

                        {{-- **Sin servicios cargados hace todos**, que es el criterio
                             permisivo de siempre. Decir «no hace nada» sería mentir:
                             lo que pasa es que el salón todavía no lo administró. --}}
                        @if (trim((string) $p->servicios) === '')
                            <p class="text-muted-warm mb-0" style="font-size:.85rem">
                                Hace todos nuestros servicios.
                            </p>
                        @else
                            <div class="d-flex flex-wrap gap-1">
                                @foreach (explode('|', (string) $p->servicios) as $srv)
                                    <span class="badge-estado e-prog">{{ $srv }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-3">
            <a class="btn btn-oro" href="{{ route('portal.reservar', ['sucursal' => $sucursal]) }}">
                <i class="bi bi-calendar-plus"></i> Reservar una cita</a>
        </div>
    @endif
@endsection
