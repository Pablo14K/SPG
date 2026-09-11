@extends('layout.app')

@section('titulo', 'Reservar cita')

@section('contenido')
    <div class="sgp-page-head">
        <a class="sgp-back" href="{{ route('portal.index') }}"><i class="bi bi-arrow-left"></i> Mi portal</a>
        <h1 class="mt-1">Reservar una cita<x-ayuda lado="bottom">Elegí los servicios y te mostramos sólo los horarios que quedan libres de verdad, con el tiempo que lleva todo junto.</x-ayuda></h1>
        <span class="sgp-titulo-linea"></span>
    </div>

    {{-- **Primero el local.** Los servicios, los horarios y los profesionales
         son de una sucursal, así que mostrarlos antes de saber cuál sería
         ofrecer algo que después puede no existir ahí. Con una sola sucursal
         este bloque no aparece: se elige sola. --}}
    @if (count($sucursales) > 1)
        <div class="sgp-panel mb-3">
            <label class="form-label">¿En qué local? *</label>
            <div class="d-flex flex-wrap gap-2 mt-1">
                @foreach ($sucursales as $s)
                    <a class="sgp-chip {{ (int) $s->id_sucursal === $sucursal ? 'activo' : '' }}"
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
        <div class="sgp-panel">
            <div class="sgp-vacio">
                <i class="bi bi-shop"></i>
                <div class="t">Elegí primero la sucursal.</div>
                <div class="d">Cada local tiene sus servicios, sus profesionales y sus horarios.</div>
            </div>
        </div>
    @else
    <div class="sgp-panel">
        {{-- `data-para-titular`: el nombre con el que `app.js` rotula a la
             persona 1 en los «¿para quién?» de cada servicio. En el portal la
             titular es quien está reservando. --}}
        <form method="post" action="{{ route('portal.guardar_reserva') }}" data-para-titular="{{ $nombreTitular ?? 'Vos' }}">
            @csrf
            <input type="hidden" name="id_sucursal" value="{{ $sucursal }}">
            
            {{-- **Una decisión por pantalla.**

                 Reservar pedía cinco cosas en una sola página —servicios, quién
                 atiende cada uno, día, hora y los detalles— y en el celular eso
                 son varias pantallas de scroll donde no se ve dónde se está ni
                 cuánto falta. Peor: el botón vivía al final, así que la única
                 forma de saber si faltaba algo era llegar abajo y encontrarlo
                 deshabilitado, sin decir por qué.

                 **Sin `app.js` se ven todos los pasos uno abajo del otro y se
                 reserva igual**: el CSS esconde sólo bajo la clase que pone el
                 script. Es la regla de siempre — lo que adorna puede faltar. --}}
            {{-- **Después de un rechazo abre en el último paso, no en el primero.**
                 El servidor devuelve esta misma pantalla con el aviso arriba y todo
                 lo cargado de vuelta por `old()`; arrancando en el paso 1 se leía
                 como que el sistema «retrocedió» y había que recorrer otra vez
                 cinco pasos ya contestados. El 99 lo acota el script al último
                 paso que exista. --}}
            <div class="sgp-wiz" data-asistente data-asistente-inicio="{{ old('fecha_hora') ? 99 : 0 }}">

            {{-- **Primero, para cuántas personas es.** Lo pidió el usuario y
                 ordena todo lo demás: con más de una, cada servicio pregunta
                 para quién es, y al final cada una puede cobrar y facturar lo
                 suyo por separado. Antes esto se preguntaba en «Detalles», o
                 sea después de elegir los servicios — cuando ya no se podía
                 decir de quién era cada uno. --}}
            <div class="sgp-seccion" data-paso="Personas" data-paso-requiere="#personas"
                 data-paso-error="Decinos para cuántas personas es la cita.">
                <div class="sgp-seccion-head">
                    <label class="form-label">¿Para quiénes es la cita? *</label>
                </div>
                <div class="sgp-detalle-grupo">
                    <div class="mb-3" style="max-width:220px">
                        <label class="form-label" for="personas">¿Cuántas personas se atienden?</label><x-ayuda campo="personas" />
                        <input class="form-control" id="personas" name="personas" value="{{ old('personas', 1) }}"
                               data-solo="numeros" inputmode="numeric" maxlength="2" required pattern="([1-9]|1[0-9]|20)" title="Un número entre 1 y 20"
                               data-acomp="#bloqueAcomp">
                    </div>

                    {{-- **Quiénes vienen, no sólo cuántas.** El número decía que iban a
                         llegar tres y el salón no sabía a quiénes esperar. Los campos
                         los dibuja `app.js` según el número: la primera persona NO se
                         pide, porque es la clienta que está reservando y su nombre ya
                         lo tiene el sistema. --}}
                    <div class="mb-3" id="bloqueAcomp" style="max-width:420px"
                         data-acomp-titulo="¿Quién viene con vos?|¿Quiénes vienen con vos?"
                         data-acomp-previos="{{ json_encode(collect(old('acomp_nombre', []))->mapWithKeys(fn ($v, $k) => [$k => [
                             'nombre' => $v,
                             'apellido' => old('acomp_apellido.' . $k, ''),
                             'alergias' => old('acomp_alergias.' . $k, ''),
                         ]])) }}"></div>

                    {{-- **La cita puede ser para otra persona.** Una clienta reserva
                         para su hija o su madre, y esas citas SÍ se superponen con la
                         suya a propósito: son dos personas. Sin declararlo, la
                         validación de solape lo tomaba por un error.

                         Arranca oculto el nombre y lo muestra el JS; sin `app.js` se
                         ven los dos campos y se reserva igual. --}}
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="para_otra_persona" value="1"
                                   id="paraOtro" @checked(old('para_otra_persona'))>
                            <label class="form-check-label" for="paraOtro">
                                La cita no es para mí: es para otra persona
                            </label>
                        </div>
                        <div id="bloqueParaQuien" class="mt-2" style="max-width:320px">
                            <label class="form-label" for="nombre_para">¿Para quién?</label><x-ayuda campo="nombre_para" />
                            <input class="form-control" id="nombre_para" name="nombre_para" maxlength="120"
                                   value="{{ old('nombre_para') }}" placeholder="Nombre de quien se atiende">

                            {{-- **Sus alergias, que no son las tuyas.** La que se
                                 sienta en el sillón es ella, así que la alergia de
                                 tu ficha no dice nada de lo que se le puede poner.
                                 Queda guardada en esta cita: no le abrimos una
                                 ficha a alguien que el salón no registró. --}}
                            <label class="form-label mt-2" for="alergias_para">Sus alergias</label><x-ayuda campo="alergias_para" />
                            <textarea class="form-control" id="alergias_para" name="alergias_para" rows="2"
                                      maxlength="300" placeholder="Amoníaco, tinturas con PPD, látex…">{{ old('alergias_para') }}</textarea>
                        </div>
                    </div>

                    {{-- **Tus alergias, acá y no sólo en Mi ficha.**

                         Existen desde la 7.110.0 y hay que ir a buscarlas: al
                         reservar es cuando una se acuerda —«ojo que la tintura
                         me irrita»— y es el único dato que puede lastimar a
                         alguien si nadie lo mira. Viene con lo que ya está
                         cargado, así que no se pisa sin querer.

                         **Éstas van a la FICHA y no a la cita**, al revés que
                         las de los demás: es un dato de la persona, y el salón
                         la tiene registrada — así le queda para la próxima. --}}
                    <div class="mb-0" style="max-width:420px">
                        <label class="form-label" for="alergias_titular">Mis alergias</label><x-ayuda campo="alergias_titular" />
                        <textarea class="form-control" id="alergias_titular" name="alergias_titular" rows="2"
                                  maxlength="300" placeholder="Amoníaco, tinturas con PPD, látex…">{{ old('alergias_titular', $misAlergias) }}</textarea>
                        {{-- Con qué se dibujó el campo: el guardado sólo escribe
                             si cambió, así que reservar sin tocarlo no te borra lo
                             que ya tenías. --}}
                        <input type="hidden" name="alergias_titular_base" value="{{ $misAlergias }}">
                    </div>
                </div>
            </div>

            <div class="sgp-seccion" data-paso="Servicios"
                 data-paso-requiere="servicios"
                 data-paso-error="Elegí al menos un servicio para seguir.">
                <div class="sgp-seccion-head">
                    <label class="form-label">¿Qué te querés hacer? *</label>
                </div>
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

                {{-- **Los botones de turno, y el filtro silencioso.**

                     El problema que resuelven: eligiendo a alguien de la mañana
                     para un servicio y a alguien de la tarde para otro no hay
                     ningún horario donde las dos estén, y la clienta lo
                     descubría al final, sin saber cuál de sus decisiones
                     fallaba. Explicarlo con un aviso ayudaba; impedirlo es
                     mejor.

                     Elegir un turno acota **todo lo demás**: los combos sólo
                     ofrecen a quien trabaja en esa franja, y los días y las
                     horas se recortan a ella. Y si no se elige ninguno, el
                     turno se deduce del primer profesional que se pida — que es
                     la misma decisión tomada de otra forma.

                     **Volviendo todo a «quien me atienda» el filtro se suelta
                     solo**: si no hay nadie pedido, no hay turno que deducir y
                     no corresponde esconderle nada. --}}
                @if (count($turnos) > 1)
                    <div class="mb-2" data-turnos-caja>
                        <div class="form-label mb-1">
                            ¿A qué hora te queda mejor?<x-ayuda>Elegí un turno y te mostramos sólo los profesionales y los horarios de esa franja. Si no elegís ninguno, se toma el del primer profesional que pidas.</x-ayuda>
                        </div>
                        <input type="hidden" name="id_turno" id="idTurno" value="{{ old('id_turno') }}">
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="sgp-chip" data-turno="0">Cualquier hora</button>
                            @foreach ($turnos as $t)
                                <button type="button" class="sgp-chip" data-turno="{{ $t->id_turno }}">
                                    {{ $t->nombre }} · {{ $t->desde }}-{{ $t->hasta }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif

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
                <div class="sgp-srv-grid" data-canjes="#bloqueCanjes">
                    @foreach ($servicios as $s)
                        <x-servicio-tarjeta :s="$s" :id="'srv' . $s->id_servicio" :para-quien="true"
                            :marcado="in_array((string) $s->id_servicio, (array) old('servicios', []), true)"
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
                                    <option value="{{ $p->id_usuario }}"
                                        data-turnos="{{ $p->turnos_ids ?? '' }}"
                                        @selected((int) old('prof_servicio.' . $s->id_servicio) === (int) $p->id_usuario)>con {{ $p->nombre }}@if (! empty($p->turnos)) · {{ $p->turnos }}@endif</option>
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
                    <div class="sgp-check-lista" id="bloqueCanjes">
                        @foreach ($canjes as $c)
                            <div class="form-check sgp-canje" data-servicio="{{ $c->id_servicio }}">
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
            {{-- **Quién atiende cada servicio, todo junto.** El combo vive dentro
                 de su tarjeta desde la 7.51.0 —aparece con su servicio y no hay
                 quince colgando de servicios que nadie pidió— y eso se conserva:
                 este paso **mueve** esos mismos combos acá para poder mirarlos de
                 una. Copiarlos mandaría dos valores para el mismo servicio. --}}
            <div class="sgp-seccion" data-paso="Profesionales">
                <div class="sgp-seccion-head">
                    <label class="form-label">¿Con quién?</label>
                </div>
                <p class="text-muted-warm" style="font-size:.85rem">
                    Podés elegir a quién preferís para cada servicio, o dejarlo en
                    «sin preferencia» y lo asignamos al reservar.
                </p>
                <div data-paso-profesionales></div>
            </div>

            <div class="sgp-seccion" data-paso="Fecha y hora"
                 data-paso-requiere="#fecha_hora"
                 data-paso-error="Elegí el día y el horario para seguir.">
                <div class="sgp-seccion-head">
                    <label class="form-label">¿Cuándo? *</label>
                </div>
                <div class="sgp-cuando-caja">
                    <div data-agenda="{{ route('portal.disponibilidad') }}"
                         data-agenda-sujeto="Tu cita"
                         data-agenda-boton="#btnReservar">
                        <div data-agenda-aviso class="text-muted-warm" style="font-size:.85rem">
                            Elegí primero los servicios para ver los horarios disponibles.
                        </div>
                        <div data-agenda-dias class="sgp-dias mt-2"></div>
                        <div data-agenda-horas class="sgp-horas mt-2"></div>
                    </div>
                </div>
                {{-- Con valor: el selector lo lee al arrancar y devuelve marcados
                     el día y la hora que ya estaban elegidos. --}}
                <input type="hidden" name="fecha_hora" id="fecha_hora" value="{{ old('fecha_hora') }}">
            </div>

            {{-- **La cita puede ser para otra persona.** Una clienta reserva
                 para su hija o su madre, y esas citas SÍ se superponen con la
                 suya a propósito: son dos personas. Sin declararlo, la
                 validación de solape lo tomaba por un error.

                 Arranca oculto el nombre y lo muestra el JS; sin `app.js` se
                 ven los dos campos y se reserva igual. --}}
            {{-- Para quién es, cuántas van y las alergias se preguntan en el
                 primer paso, «Personas»: acá queda lo que no es de nadie en
                 particular. --}}
            <div class="sgp-seccion" data-paso="Detalles">
                <div class="sgp-seccion-head">
                    <label class="form-label">Detalles</label>
                </div>
                <div class="sgp-detalle-grupo">
                    <div class="mb-0">
                        <label class="form-label" for="observaciones">¿Algo que quieras avisarnos?</label><x-ayuda campo="observaciones" />
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="2" maxlength="300">{{ old('observaciones') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- **El último paso es mirar la cita armada.** Es lo que no se
                 podía ver antes de confirmar: qué servicios, con quién, qué día
                 y a qué hora, y cuánto sale todo junto. Lo dibuja `app.js` con
                 los `data-` que las tarjetas ya traen, así que no puede quedar
                 desfasado de lo que la clienta está viendo. --}}
            <div class="sgp-seccion" data-paso="Confirmar">
                <div class="sgp-seccion-head">
                    <label class="form-label">Tu cita quedaría así</label>
                </div>
                <p class="text-muted-warm" style="font-size:.85rem">
                    Revisá los detalles antes de confirmar.
                </p>

                <div data-wiz-repaso class="mb-3"></div>

            {{-- **De este bloque queda sólo la seña.**

                 Los servicios, la duración y el total los dice ahora el repaso
                 de arriba, con el día, la hora y quién atiende cada uno — que es
                 más de lo que este resumen mostraba. Dejarlo entero sería la
                 misma cuenta dos veces en la misma pantalla.

                 **La seña sí se queda**, porque no está en el repaso y contesta
                 otra pregunta: no cuánto sale, sino **cuánto hay que adelantar
                 para que la reserva valga**. El JS pone cada dato con un `if`
                 sobre su elemento, así que sacar los otros tres no rompe nada. --}}
            {{-- El contenedor queda sin estilo propio: adentro va un solo aviso, que
                 ya trae el suyo. Una caja dentro de otra caja para un renglón. --}}
            <div class="mb-3" id="resumenCita" style="display:none">
                <div class="sgp-resumen-sena" data-resumen="sena-caja" style="display:none">
                    <i class="bi bi-cash-coin"></i>
                    Para confirmarla hace falta una seña de
                    <strong data-resumen="sena">Gs. 0</strong>.
                    {{-- **Que es un MÍNIMO hay que decirlo acá.** Dejando menos, el
                         horario no queda confirmado y la clienta se entera recién
                         cuando el salón le rechaza el aviso — o peor, el día de la
                         cita. Es la misma frase que el modal de «Dejar una seña». --}}
                    Es el mínimo: con menos, el horario no queda confirmado.

                    {{-- **Y de dónde sale ese número, servicio por servicio.**

                         Con un solo servicio con seña el total se explica solo; con
                         dos, «Gs. 315.000» es una cifra que la clienta no puede
                         comprobar — se reportó así. Es el mismo desglose que ve
                         quien confirma el pago en el mostrador, sólo que acá lo
                         arma el navegador con los `data-` que ya trae cada tarjeta:
                         así no puede quedar desfasado de lo que está marcado. --}}
                    <ul class="sgp-sena-detalle" data-resumen="sena-detalle"></ul>

                    Después de reservar te mostramos dónde registrar el comprobante.
                </div>
            </div>

            {{-- `data-wiz-confirmar` lo manda a la botonera del paso, al lado de
                 «Volver»: es la acción principal de esta pantalla y tiene que
                 quedar donde el pulgar ya estaba apretando «Siguiente». --}}
            <button class="btn btn-oro sgp-reservar-btn" id="btnReservar" data-wiz-confirmar disabled>
                <i class="bi bi-calendar-check"></i> Confirmar cita</button>
            </div> <!-- /paso Confirmar -->

            </div> <!-- /asistente -->

        </form>
    </div>
    @endif
@endsection

