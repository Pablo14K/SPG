@extends('layout.app')

@section('titulo', 'Reservar cita')

@section('contenido')
    <div class="spg-page-head">
        <a class="spg-back" href="{{ route('portal.index') }}"><i class="bi bi-arrow-left"></i> Mi portal</a>
        <h1 class="mt-1">Reservar una cita<x-ayuda lado="bottom">Elegí los servicios y te mostramos sólo los horarios que quedan libres de verdad, con el tiempo que lleva todo junto.</x-ayuda></h1>
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
                {{-- **El catálogo completo vive en su propia pantalla.** Acá
                     estaba desplegable, pero esta página ya pide elegir
                     servicios, profesional, día y hora: un bloque más con el
                     equipo entero compite con lo único que hay que hacer.
                     Quien quiere saber quién hace qué lo mira antes.

                     Va siempre y no sólo cuando hay servicios cargados: aunque
                     todas hagan de todo, sigue sirviendo para saber quiénes
                     son y cómo las calificaron. --}}
                <a class="btn btn-sm btn-outline-neutro mb-2" href="{{ route('portal.profesionales', ['sucursal' => $sucursal]) }}">
                    <i class="bi bi-people"></i> ¿Quién hace cada cosa?</a>

                {{-- **La restricción se explica ANTES, no en el rechazo.**
                     Eligiendo a alguien de la mañana para un servicio y a
                     alguien de la tarde para otro no hay ningún horario donde
                     las dos estén, así que el selector no ofrece ni un día. El
                     sistema hace lo correcto y lo dice tarde: para entonces la
                     clienta ya eligió todo y no sabe cuál de sus decisiones es
                     la que falla.

                     Va acá y no como un aviso rojo: es una ayuda para elegir
                     bien, no un error — todavía no hizo nada mal. --}}
                <x-ayuda titulo="Elegir profesional">Podés dejar «quien me atienda» y el salón acomoda todo en el mismo turno. Si elegís a alguien en particular, mirá el horario que aparece al lado del nombre: pidiendo una persona de la mañana y otra de la tarde no va a quedar ningún horario libre, porque tu cita es una sola.</x-ayuda>

                {{-- **Tarjetas con la imagen de referencia.** La clienta
                     elige mirando el resultado y no una lista de nombres:
                     «mechas» es una palabra, la foto es lo que va a recibir.

                     El funcionamiento no cambió — mismo checkbox, mismo `name`,
                     mismos `data-` — así que la agenda, los canjes y el reparto
                     siguen exactamente igual. --}}
                <div class="spg-srv-grid" data-canjes="#bloqueCanjes">
                    @foreach ($servicios as $s)
                        <x-servicio-tarjeta :s="$s" :id="'srv' . $s->id_servicio"
                            {{-- **Que pide seña se avisa ANTES de reservar**, no
                                 después: es plata que hay que adelantar para que
                                 la cita quede confirmada, y enterarse al final
                                 cambia la decisión de haberla tomado. --}}
                            :badge="$s->sena_porcentaje
                                ? 'seña ' . money(round($s->precio * $s->sena_porcentaje / 100))
                                : null">

                            {{-- El combo aparece con su servicio: ver `data-prof-de` en app.js.
                                 Arranca visible a propósito, así que sin JavaScript se ven todos
                                 y se puede elegir profesional igual. --}}
                            <select class="form-select form-select-sm mt-1"
                                    name="prof_servicio[{{ $s->id_servicio }}]"
                                    data-prof-de="#srv{{ $s->id_servicio }}">
                                <option value="0">quien me atienda</option>
                                @php
                                    // **Sólo quienes hacen ESTE servicio.** El combo
                                    // listaba al equipo entero, así que se podía pedir
                                    // una coloración con quien sólo hace uñas y el «no»
                                    // llegaba el día de la cita.
                                    //
                                    // Sin nadie cargado para ese servicio vale el
                                    // criterio permisivo: lo hacen todos.
                                    $suyos = $haceServicio[$s->id_servicio] ?? [];
                                    $ofrecer = $suyos
                                        ? collect($profs)->filter(fn ($p) => in_array((int) $p->id_usuario, $suyos, true))
                                        : collect($profs);
                                @endphp
                                {{-- **El turno va al lado del nombre.** Decía sólo
                                     «con Lucía», así que la clienta no tenía cómo
                                     acordarse del horario de cada una: elegía a
                                     alguien de la mañana para un servicio y a
                                     alguien de la tarde para otro, y recién al
                                     buscar horarios descubría que no hay ninguno
                                     donde las dos estén. El sistema hacía lo
                                     correcto y lo decía tarde. --}}
                                @foreach ($ofrecer as $p)
                                    <option value="{{ $p->id_usuario }}">con {{ $p->nombre }}@if (! empty($p->turnos)) · {{ $p->turnos }}@endif</option>
                                @endforeach
                            </select>
                        </x-servicio-tarjeta>
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
                    <label class="form-label" for="nombre_para">¿Para quién?</label><x-ayuda campo="nombre_para" />
                    <input class="form-control" id="nombre_para" name="nombre_para" maxlength="120"
                           placeholder="Nombre de quien se atiende">
                </div>
            </div>

            <div class="mb-3" style="max-width:180px">
                <label class="form-label" for="personas">¿Cuántas personas van?</label><x-ayuda campo="personas" />
                <input class="form-control" id="personas" name="personas" value="1"
                       data-solo="numeros" inputmode="numeric" maxlength="2">
            </div>

            <div class="mb-3">
                <label class="form-label" for="observaciones">¿Algo que quieras avisarnos?</label><x-ayuda campo="observaciones" />
                <textarea class="form-control" id="observaciones" name="observaciones" rows="2" maxlength="300"></textarea>
            </div>

            {{-- El total de seña de lo que va marcando, para que no tenga que
                 sumarlo de cabeza. Lo calcula `app.js`; sin él, cada servicio ya
                 muestra el suyo al lado. --}}
            <div class="alert alert-warning py-2 mb-3" id="avisoSena" style="display:none;font-size:.86rem">
                <i class="bi bi-cash-coin"></i>
                Para confirmar esta cita hace falta una seña de
                <strong id="montoSena">Gs. 0</strong>. Después de reservar te
                mostramos dónde registrar el comprobante.
            </div>

            <button class="btn btn-oro" id="btnReservar" disabled>
                <i class="bi bi-calendar-check"></i> Reservar</button>
        </form>
    </div>
    @endif
@endsection

