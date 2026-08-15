@extends('layout.app')

@section('titulo', 'Reservar cita')

@section('contenido')
    <div class="spg-page-head">
        <a class="spg-back" href="{{ route('portal.index') }}"><i class="bi bi-arrow-left"></i> Mi portal</a>
        <h1 class="mt-1">Reservar una cita</h1>
        <div class="sub">Elegí los servicios y te mostramos los horarios que quedan libres de verdad.</div>
    </div>

    <div class="spg-panel" style="max-width:760px">
        <form method="post" action="{{ route('portal.guardar_reserva') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label">¿Qué te querés hacer? *</label>
                <div class="spg-check-lista">
                    @foreach ($servicios as $s)
                        <div class="d-flex align-items-center gap-2 flex-wrap py-1">
                            <div class="form-check mb-0 flex-grow-1">
                                <input class="form-check-input srv" type="checkbox" name="servicios[]"
                                       value="{{ $s->id_servicio }}" id="srv{{ $s->id_servicio }}">
                                <label class="form-check-label" for="srv{{ $s->id_servicio }}">
                                    {{ $s->nombre }}
                                    <span class="text-muted-warm">
                                        · {{ money($s->precio) }} · {{ (int) $s->duracion_min }} min</span>
                                </label>
                            </div>
                            <select class="form-select form-select-sm" style="width:auto"
                                    name="prof_servicio[{{ $s->id_servicio }}]">
                                <option value="0">quien me atienda</option>
                                @foreach ($profs as $p)
                                    <option value="{{ $p->id_usuario }}">con {{ $p->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- ------------------------------------------------------------
                 Canjes disponibles.
                 Va DESPUÉS de los servicios y ANTES del horario, que es el
                 orden en que se decide: qué me hago, con qué lo pago, cuándo.

                 **Marcar el canje no agrega el servicio a la cita**: hay que
                 marcarlo arriba como cualquier otro, porque tiene que ocupar
                 su tiempo en la agenda y su profesional. El canje sólo dice
                 que ese servicio no se cobra.
                 ------------------------------------------------------------ --}}
            @if (!empty($canjes))
                <div class="mb-3">
                    <label class="form-label">
                        <i class="bi bi-gift txt-oro"></i> ¿Usás algún canje?
                    </label>
                    <p class="text-muted-warm" style="font-size:.82rem">
                        Marcá el canje <strong>y también el servicio de arriba</strong>: así reserva
                        el tiempo que hace falta. Con el canje marcado, ese servicio no se te cobra.
                    </p>
                    <div class="spg-check-lista">
                        @foreach ($canjes as $c)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="canjes[]"
                                       value="{{ $c->id_canje }}" id="cj{{ $c->id_canje }}">
                                <label class="form-check-label" for="cj{{ $c->id_canje }}">
                                    {{ $c->nombre }}
                                    <span class="text-muted-warm">
                                        · vale {{ money($c->precio) }} ·
                                        te queda(n) {{ (int) $c->dias_restantes }} día(s)
                                    </span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Acá había un «¿Con quién?» para toda la cita, y confundía: cada
                 servicio ya trae su propio selector arriba, con «quien me
                 atienda» por defecto. Eran dos formas de contestar lo mismo, y
                 no era evidente cuál ganaba. Sin preferencia, el profesional lo
                 asigna el servidor al reservar (Agenda::profesionalLibre). --}}

            {{-- El selector de huecos lo maneja app.js, el mismo que usa Nueva
                 cita. Acá no hay combo de profesional a propósito, así que la
                 agenda que se consulta sale de los selectores por servicio. --}}
            <div class="mb-3">
                <label class="form-label">¿Cuándo? *</label>
                <div data-agenda="{{ route('portal.disponibilidad') }}"
                     data-agenda-sujeto="Tu cita"
                     data-agenda-boton="#btnReservar">
                    <div data-agenda-aviso class="text-muted-warm" style="font-size:.85rem">
                        Elegí primero los servicios para ver los horarios disponibles.
                    </div>
                    <div data-agenda-dias class="spg-dias mt-2"></div>
                    <div data-agenda-horas class="spg-horas mt-2"></div>
                </div>
                <input type="hidden" name="fecha_hora" id="fecha_hora">
            </div>

            <div class="mb-3">
                <label class="form-label" for="observaciones">¿Algo que quieras avisarnos?</label>
                <textarea class="form-control" id="observaciones" name="observaciones" rows="2" maxlength="300"></textarea>
            </div>

            <button class="btn btn-oro" id="btnReservar" disabled>
                <i class="bi bi-calendar-check"></i> Reservar</button>
        </form>
    </div>
@endsection

