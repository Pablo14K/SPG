@extends('layout.app')

@section('titulo', 'Reservar cita')

@section('contenido')
    <div class="spg-page-head">
        <a class="spg-back" href="{{ route('portal.index') }}"><i class="bi bi-arrow-left"></i> Mi portal</a>
        <h1 class="mt-1">Reservar una cita</h1>
        <div class="sub">Elegí los servicios y te mostramos los horarios que quedan libres de verdad.</div>
    </div>

    {{-- **Primero el local.** Los servicios, los horarios y los profesionales
         son de una sucursal, así que mostrarlos antes de saber cuál sería
         ofrecer algo que después puede no existir ahí. Con una sola sucursal
         este bloque no aparece: se elige sola. --}}
    @if (count($sucursales) > 1)
        <div class="spg-panel mb-3" style="max-width:760px">
            <label class="form-label">¿En qué local? *</label>
            <div class="d-flex flex-wrap gap-2 mt-1">
                @foreach ($sucursales as $s)
                    <a class="spg-chip {{ (int) $s->id_sucursal === $sucursal ? 'activo' : '' }}"
                       href="{{ route('portal.reservar', ['sucursal' => $s->id_sucursal]) }}">
                        <i class="bi bi-shop"></i> {{ $s->nombre }}
                        @if ($s->ciudad)<span class="text-muted-warm">· {{ $s->ciudad }}</span>@endif
                    </a>
                @endforeach
            </div>
            @unless ($sucursal)
                <p class="text-muted-warm mt-2 mb-0" style="font-size:.82rem">
                    Elegí el local y te mostramos sus servicios y horarios.
                </p>
            @endunless
        </div>
    @endif

    @if (! $sucursal)
        <div class="spg-panel" style="max-width:760px">
            <div class="spg-vacio">
                <i class="bi bi-shop"></i>
                <div class="t">Elegí primero la sucursal.</div>
                <div class="d">Cada local tiene sus servicios, sus profesionales y sus horarios.</div>
            </div>
        </div>
    @else
    <div class="spg-panel" style="max-width:760px">
        <form method="post" action="{{ route('portal.guardar_reserva') }}">
            @csrf
            <input type="hidden" name="id_sucursal" value="{{ $sucursal }}">

            <div class="mb-3">
                <label class="form-label">¿Qué te querés hacer? *</label>
                {{-- **Quién hace qué, a la vista.** `usuario_servicio` decide
                     desde la 7.42.0 con quién se puede reservar cada servicio,
                     y sólo lo miraba la validación: la clienta elegía a ciegas
                     y se enteraba al guardar. Sin nada cargado, esa persona
                     hace todo — el criterio permisivo de siempre. --}}
                @php
                    $hace = collect($haceCada ?? [])->keyBy('id_usuario');
                    $conLista = collect($profs)->filter(fn ($p) => trim((string) ($hace[$p->id_usuario]->servicios ?? '')) !== '');
                @endphp
                @if ($conLista->isNotEmpty())
                    <details class="mb-2">
                        <summary style="font-size:.85rem;cursor:pointer" class="text-muted-warm">
                            ¿Quién hace cada cosa?
                        </summary>
                        <ul class="list-unstyled mt-2 mb-0" style="font-size:.83rem">
                            @foreach ($profs as $p)
                                <li class="py-1" style="border-top:1px solid var(--gris-calido)">
                                    <strong>{{ $p->nombre }}</strong>
                                    <span class="text-muted-warm">
                                        · {{ trim((string) ($hace[$p->id_usuario]->servicios ?? '')) ?: 'hace todos los servicios' }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif

                <div class="spg-check-lista" data-canjes="#bloqueCanjes">
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
                            {{-- El combo aparece con su servicio: ver `data-prof-de` en app.js.
                                 Arranca visible a propósito, así que sin JavaScript se ven todos
                                 y se puede elegir profesional igual. --}}
                            <select class="form-select form-select-sm" style="width:auto"
                                    name="prof_servicio[{{ $s->id_servicio }}]"
                                    data-prof-de="#srv{{ $s->id_servicio }}">
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
                        Marcá el canje y <strong>el servicio se marca solo</strong> —y al revés—, así
                        queda reservado el tiempo que hace falta. Con el canje puesto, ese servicio
                        no se te cobra.
                    </p>
                    <div class="spg-check-lista" id="bloqueCanjes">
                        @foreach ($canjes as $c)
                            <div class="form-check spg-canje" data-servicio="{{ $c->id_servicio }}">
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

            {{-- **La cita puede ser para otra persona.** Una clienta reserva
                 para su hija o su madre, y esas citas SÍ se superponen con la
                 suya a propósito: son dos personas. Sin declararlo, la
                 validación de solape lo tomaba por un error.

                 Arranca oculto el nombre y lo muestra el JS; sin `app.js` se
                 ven los dos campos y se reserva igual. --}}
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="para_otra_persona" value="1"
                           id="paraOtro">
                    <label class="form-check-label" for="paraOtro">
                        La cita es para otra persona
                    </label>
                </div>
                <div id="bloqueParaQuien" class="mt-2" style="max-width:320px">
                    <label class="form-label" for="nombre_para">¿Para quién?</label>
                    <input class="form-control" id="nombre_para" name="nombre_para" maxlength="120"
                           placeholder="Nombre de quien se atiende">
                </div>
            </div>

            <div class="mb-3" style="max-width:180px">
                <label class="form-label" for="personas">¿Cuántas personas van?</label>
                <input class="form-control" id="personas" name="personas" value="1"
                       data-solo="numeros" inputmode="numeric" maxlength="2">
            </div>

            <div class="mb-3">
                <label class="form-label" for="observaciones">¿Algo que quieras avisarnos?</label>
                <textarea class="form-control" id="observaciones" name="observaciones" rows="2" maxlength="300"></textarea>
            </div>

            <button class="btn btn-oro" id="btnReservar" disabled>
                <i class="bi bi-calendar-check"></i> Reservar</button>
        </form>
    </div>
    @endif
@endsection

