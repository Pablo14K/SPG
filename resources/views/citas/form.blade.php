@extends('layout.app')

@section('titulo', 'Nueva cita')

@section('contenido')
    @php use App\Servicios\Permisos; @endphp

    <x-encabezado sub="La fecha no se escribe a mano: se eligen los servicios y el sistema muestra los horarios que quedan libres de verdad." />

    <div class="sgp-panel" style="max-width:900px">
        {{-- `data-para-titular`: cómo se rotula a la persona 1 en los «¿para
             quién?» de cada servicio mientras no haya clienta elegida. Con una
             elegida, `app.js` toma su nombre del combo. --}}
        <form method="post" action="{{ route('citas.guardar') }}" id="formCita" data-para-titular="La clienta">
            @csrf

            {{-- **El mismo asistente que el portal**, por pedido del usuario:
                 «realizar tanto para portal cliente como para los demás».

                 Nueva cita pedía clienta, servicios, quién hace cada uno, día,
                 hora y detalles en una sola pantalla: en el mostrador eso se
                 llena rápido cuando ya se sabe todo, y se pierde cuando la
                 clienta está decidiendo por teléfono. Un paso por vez deja ver
                 qué falta.

                 **Sin `app.js` se ve todo junto, como antes.** --}}
            <div class="sgp-wiz" data-asistente data-asistente-inicio="{{ old('fecha_hora') ? 99 : 0 }}">

            <div data-paso="Cliente" data-paso-requiere="#id_cliente"
                 data-paso-error="Elegí la clienta para seguir.">
            <div class="row g-3">
                {{-- 1. Cliente --}}
                <div class="col-md-8">
                    <label class="form-label" for="id_cliente">Cliente *</label>
                    <input class="form-control form-control-sm mb-1" data-filtra="#id_cliente"
                           placeholder="Buscar por nombre o cédula…" autocomplete="off">
                    <select class="form-select" id="id_cliente" name="id_cliente" required size="1">
                        <option value="">— Elegí un cliente —</option>
                        @foreach ($clientes as $c)
                            <option value="{{ $c->id_cliente }}"
                                data-alergias="{{ $c->alergias }}"
                                data-nombre="{{ $c->nombre }} {{ $c->apellido }}"
                                @selected((int) old('id_cliente', $sel_cliente) === (int) $c->id_cliente)>
                                {{ $c->apellido }}, {{ $c->nombre }}
                                @if ($c->cedula) · {{ $c->cedula }} @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                @if (Permisos::puede('clientes.registro'))
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="button" class="btn btn-rapido w-100" data-bs-toggle="modal"
                                data-bs-target="#modalClienteRapido">
                            <i class="bi bi-person-plus"></i> Cliente nuevo
                        </button>
                    </div>
                @endif

                {{-- **El combo suelto de «Profesional» salió, por pedido del
                     usuario.** Preguntaba lo mismo que el combo que aparece al
                     lado de cada servicio, y desde dos lados: para entender qué
                     hacía el de abajo —«lo hace el principal»— había que saber
                     primero qué era el principal.

                     La cita sigue teniendo dueño: sale de
                     `Agenda::principalDelReparto()`, que es el criterio desde la
                     5.3.0 y el que la 7.64.0 dejó valiendo también acá. Sin
                     nadie elegido en ningún servicio, decide el sistema — que es
                     exactamente lo que hacía «sin preferencia». --}}

            </div>
            </div>

            {{-- **Primero, para cuántas personas es.** Ordena todo lo que sigue:
                 con más de una, cada servicio pregunta para quién es, y al final
                 cada una puede cobrar y facturar lo suyo por separado. Antes
                 esto se preguntaba en «Detalles», después de los servicios,
                 cuando ya no se podía decir de quién era cada uno. --}}
            <div data-paso="Personas" data-paso-requiere="#personas"
                 data-paso-error="Indicá para cuántas personas es la cita.">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="personas">¿Cuántas personas se atienden? *</label><x-ayuda>Entre 1 y 20. Con más de una, cada servicio pregunta para quién es.</x-ayuda>
                    <input class="form-control" id="personas" name="personas"
                           value="{{ old('personas', 1) }}" style="max-width:140px"
                           data-solo="numeros" inputmode="numeric" maxlength="2" required pattern="([1-9]|1[0-9]|20)" title="Un número entre 1 y 20"
                           data-acomp="#bloqueAcompCita">
                </div>

                {{-- Quiénes vienen, no sólo cuántas. La primera no se pide: es
                     la clienta de la cita, que ya está elegida en el paso anterior. --}}
                <div class="col-12" id="bloqueAcompCita"
                     data-acomp-titulo="¿Quién más viene?|¿Quiénes más vienen?"
                     data-acomp-previos="{{ json_encode(collect(old('acomp_nombre', []))->mapWithKeys(fn ($v, $k) => [$k => [
                         'nombre' => $v,
                         'apellido' => old('acomp_apellido.' . $k, ''),
                         'alergias' => old('acomp_alergias.' . $k, ''),
                     ]])) }}"></div>

                {{-- **La cita puede ser para otra persona, también acá.** El
                     portal lo pregunta desde la 7.57.0 y el mostrador no, así que
                     la clienta que llama por teléfono para reservarle a su hija
                     quedaba cargada como si fuera para ella: la agenda esperaba a
                     una y venía otra, y el control de solape lo tomaba por error
                     —esas citas SÍ se superponen a propósito, son dos personas—.

                     No se crea una ficha de cliente para quien se atiende: sería
                     inventar una persona que el salón no registró. El nombre va
                     como texto en la cita, igual que en el portal. --}}
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="para_otra_persona" value="1"
                               id="paraOtro" @checked(old('para_otra_persona'))>
                        <label class="form-check-label" for="paraOtro">
                            La cita es para otra persona
                        </label>
                    </div>
                    {{-- Arranca visible y lo esconde el JS: sin `app.js` se ven
                         los dos campos y se agenda igual. --}}
                    <div id="bloqueParaQuien" class="mt-2">
                        <label class="form-label" for="nombre_para">¿Para quién?</label><x-ayuda>Con el nombre completo: es lo que ve quien atiende ese día.</x-ayuda>
                        <input class="form-control" id="nombre_para" name="nombre_para" maxlength="120"
                               placeholder="Nombre y apellido de quien se atiende"
                               value="{{ old('nombre_para') }}">

                        {{-- **Sus alergias, que no son las de quien reservó.** La
                             que se sienta en el sillón es ella, así que la alergia
                             de la ficha de al lado no dice nada de lo que se le
                             puede poner. Queda en la cita: no se le abre una ficha
                             a alguien que el salón no registró. --}}
                        <label class="form-label mt-2" for="alergias_para">Sus alergias</label><x-ayuda campo="alergias_para" />
                        <textarea class="form-control" id="alergias_para" name="alergias_para" rows="2"
                                  maxlength="300" placeholder="Amoníaco, tinturas con PPD, látex…">{{ old('alergias_para') }}</textarea>
                    </div>
                </div>

                {{-- **Las alergias de la clienta, acá y no sólo en su ficha.**

                     Al agendar es cuando la clienta las cuenta por teléfono, y
                     mandar a quien atiende a otra pantalla para anotarlas es
                     pedirle que se acuerde después. Es el único dato de la ficha
                     que puede lastimar a alguien si nadie lo mira.

                     **Éstas van a la FICHA y no a la cita**, al revés que las de
                     los demás: la clienta está registrada, así que le quedan para
                     la próxima. El campo se llena solo con lo que ya tiene
                     cargado al elegirla — ver el script del pie. --}}
                <div class="col-md-6">
                    <label class="form-label" for="alergias_titular">Alergias de la clienta</label><x-ayuda campo="alergias_titular" />
                    <textarea class="form-control" id="alergias_titular" name="alergias_titular" rows="2"
                              maxlength="300" placeholder="Amoníaco, tinturas con PPD, látex…">{{ old('alergias_titular') }}</textarea>
                    {{-- Con qué se dibujó el campo. El guardado sólo escribe si
                         cambió: sin esto, agendarle una cita sin tocar el campo le
                         borraría las alergias que ya tenía —y en silencio—. --}}
                    <input type="hidden" name="alergias_titular_base" id="alergias_titular_base"
                           value="{{ old('alergias_titular_base', '') }}">
                </div>
            </div>
            </div>

            <div data-paso="Servicios" data-paso-requiere="servicios"
                 data-paso-error="Elegí al menos un servicio para seguir.">
            <div class="row g-3">
                {{-- 2. Servicios --}}
                <div class="col-12">
                    <label class="form-label">Servicios *</label>
                    <p class="text-muted-warm mb-2" style="font-size:.8rem">
                        Se puede repartir la cita entre varias personas: elegí quién hace cada servicio.
                        Dos servicios de la <em>misma zona del cuerpo</em> no se pueden hacer
                        a la vez —van uno después del otro— y los de zonas distintas sí
                        conviven.
                    </p>

                    {{-- **Tarjetas con la imagen de referencia.** El
                         funcionamiento no cambió: es el mismo checkbox, con el
                         mismo `name` y los mismos `data-`, así que la agenda,
                         el reparto y los canjes siguen igual. Lo que cambió es
                         que quien atiende ve la foto de lo que le están
                         pidiendo — «mechas» es una palabra, la foto es el
                         resultado. --}}
                    <div class="sgp-srv-grid" id="listaServicios" data-canjes="#bloqueCanjes">
                        @foreach ($servicios as $s)
                            <x-servicio-tarjeta :s="$s" :id="'srv' . $s->id_servicio" :para-quien="true"
                                :marcado="in_array($s->id_servicio, old('servicios', []), false)">

                                @php $profSel = (int) (old('prof_servicio', [])[$s->id_servicio] ?? 0); @endphp
                                {{-- El combo aparece con su servicio: ver `data-prof-de` en app.js --}}
                                <select class="form-select form-select-sm mt-1"
                                        name="prof_servicio[{{ $s->id_servicio }}]"
                                        data-prof-de="#srv{{ $s->id_servicio }}">
                                    <option value="0">quien esté libre</option>
                                    @php
                                        // **Sólo quienes hacen ESTE servicio.** El combo
                                        // listaba al equipo entero, así que se podía
                                        // repartir una coloración a quien sólo hace uñas
                                        // y el rechazo llegaba **después** de haber
                                        // elegido día y hora: «Fulana no hace tal cosa».
                                        // El portal ya filtraba desde la 7.90.0 y esta
                                        // pantalla se había quedado afuera.
                                        //
                                        // Criterio permisivo de siempre: sin nadie
                                        // cargado para ese servicio, lo hacen todos.
                                        $suyos = $haceServicio[$s->id_servicio] ?? [];
                                        $ofrecer = $suyos
                                            ? collect($profs)->filter(fn ($x) => in_array((int) $x->id_usuario, $suyos, true))
                                            : collect($profs);
                                    @endphp
                                    {{-- Con su turno al lado: quien atiende también
                                         necesita ver de un vistazo si esa persona
                                         está a la mañana o a la tarde antes de
                                         repartir los servicios. --}}
                                    @foreach ($ofrecer as $p)
                                        <option value="{{ $p->id_usuario }}"
                                            @selected($profSel === (int) $p->id_usuario)>{{ $p->nombre }}@if (! empty($p->turnos)) · {{ $p->turnos }}@endif</option>
                                    @endforeach
                                </select>
                            </x-servicio-tarjeta>
                        @endforeach
                    </div>
                </div>

            </div>
            </div>

            {{-- **Quién hace cada servicio, todo junto.** El combo sigue
                 viviendo dentro de su tarjeta —aparece con su servicio desde la
                 7.51.0— y este paso **mueve** esos mismos nodos acá para poder
                 mirarlos de una: copiarlos mandaría dos valores para el mismo
                 servicio y ganaría el último. --}}
            <div data-paso="Profesionales">
                <label class="form-label">Quién hace cada servicio</label>
                <p class="text-muted-warm mb-2" style="font-size:.8rem">
                    Dos servicios de la <em>misma zona del cuerpo</em> no se pueden hacer
                    a la vez —van uno después del otro— y los de zonas distintas sí
                    conviven. Dejalo en «quien esté libre» y lo decide el sistema.
                </p>
                <div data-paso-profesionales></div>
            </div>

            <div data-paso="Fecha y hora" data-paso-requiere="#fecha_hora"
                 data-paso-error="Elegí el día y el horario para seguir.">
            <div class="row g-3">
                {{-- 3. Fecha y hora, ofrecidas por el motor de disponibilidad.
                     El selector lo maneja app.js, el mismo que usa el portal. --}}
                <div class="col-12">
                    <label class="form-label">Fecha y hora *</label>
                    <div data-agenda="{{ route('citas.disponibilidad') }}"
                         data-agenda-sujeto="La cita"
                         data-agenda-boton="#btnAgendar">
                        <div data-agenda-aviso class="text-muted-warm" style="font-size:.85rem">
                            Elegí primero los servicios para ver los horarios disponibles.
                        </div>

                        <div data-agenda-dias class="sgp-dias mt-2"></div>
                        <div data-agenda-horas class="sgp-horas mt-2"></div>
                    </div>

                    <input type="hidden" name="fecha_hora" id="fecha_hora" value="{{ old('fecha_hora') }}">
                </div>

            </div>
            </div>

            <div data-paso="Detalles">
            <div class="row g-3">
                {{-- Canjes por puntos de la clienta elegida.
                     Vienen los de TODAS y el JS muestra los de la elegida,
                     porque la clienta se elige en esta misma pantalla. El
                     filtro es comodidad, no control: quien decide es
                     Canje::aplicarACita(), que comprueba contra la clienta de
                     la cita Y contra los servicios que la cita tiene. --}}
                @if (count($canjes))
                    <div class="col-12" id="bloqueCanjes" hidden>
                        <label class="form-label">
                            <i class="bi bi-gift txt-oro"></i> Canjes por puntos de esta clienta
                        </label>
                        <p class="text-muted-warm" style="font-size:.82rem">
                            Marcá el canje <strong>y también su servicio, en el paso «Servicios»</strong>: el canje no
                            reemplaza al servicio, lo acompaña. El servicio ocupa el mismo tiempo en la
                            agenda; lo único que cambia es que no se cobra.
                        </p>
                        <div class="sgp-check-lista">
                            @foreach ($canjes as $cj)
                                <div class="form-check sgp-canje" data-cliente="{{ $cj->id_cliente }}"
                                     data-servicio="{{ $cj->id_servicio }}" hidden>
                                    <input class="form-check-input" type="checkbox" name="canjes[]"
                                           value="{{ $cj->id_canje }}" id="cjm{{ $cj->id_canje }}"
                                           @checked(in_array((string) $cj->id_canje, (array) old('canjes', []), false))>
                                    <label class="form-check-label" for="cjm{{ $cj->id_canje }}">
                                        {{ $cj->nombre }}
                                        <span class="text-muted-warm">
                                            · vale {{ money($cj->precio) }} ·
                                            vence el {{ fecha($cj->vence_en, 'd/m/Y') }}
                                            ({{ (int) $cj->dias_restantes }} día(s))
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Para quién es, cuántas van y las alergias se preguntan en el
                     paso «Personas»: acá queda lo que no es de nadie en particular. --}}
                <div class="col-12">
                    <label class="form-label" for="observaciones">Observaciones</label><x-ayuda campo="observaciones" />
                    <textarea class="form-control" id="observaciones" name="observaciones"
                              rows="2" maxlength="300">{{ old('observaciones') }}</textarea>
                </div>
            </div>
            </div>

            {{-- El repaso, igual que en el portal: qué se le va a hacer, con
                 quién, qué día y cuánto sale. Quien atiende por teléfono lo lee
                 en voz alta antes de confirmar. --}}
            <div data-paso="Confirmar">
                <label class="form-label">La cita quedaría así</label>
                <div data-wiz-repaso class="mb-3"></div>
                <a class="btn btn-outline-neutro" href="{{ route('citas.agenda') }}" data-wiz-cancelar>Cancelar</a>
                <button class="btn btn-oro" id="btnAgendar" data-wiz-confirmar disabled>
                    <i class="bi bi-calendar-check"></i> Agendar
                </button>
            </div>

            </div> <!-- /asistente -->
        </form>
    </div>

    @if (Permisos::puede('clientes.registro'))
        <div class="modal fade" id="modalClienteRapido" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    {{-- data-borrador: los servicios y el horario ya elegidos
                         vuelven con el redirect en vez de perderse. --}}
                    <form method="post" action="{{ route('citas.cliente_rapido') }}"
                          data-borrador="#formCita">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" style="font-size:1rem">
                                <i class="bi bi-person-plus"></i> Registrar un cliente nuevo</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted-warm" style="font-size:.82rem">
                                Para cuando la persona llega al local sin estar registrada.
                            </p>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label" for="cr_nombre">Nombre *</label><x-ayuda campo="cr_nombre" />
                                    <input class="form-control" id="cr_nombre" name="nombre" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="cr_apellido">Apellido *</label><x-ayuda campo="cr_apellido" />
                                    <input class="form-control" id="cr_apellido" name="apellido" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="cr_cedula">Cédula</label><x-ayuda campo="cr_cedula" />
                                    <input class="form-control" id="cr_cedula" name="cedula" data-solo="documento" inputmode="numeric">
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="cr_telefono">Teléfono</label><x-ayuda campo="cr_telefono" />
                                    <input class="form-control" id="cr_telefono" name="telefono" data-solo="telefono" inputmode="tel">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="cr_email">Email</label><x-ayuda campo="cr_email" />
                                    <input type="email" class="form-control" id="cr_email" name="email">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-oro">Registrar y seguir</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
// Muestra sólo los canjes de la clienta elegida, y marca su servicio cuando se
// marca el canje —el canje NO reemplaza al servicio: lo acompaña, y sin el
// servicio marcado el vale no se aplica y se pierde el viaje—.
(function () {
    var bloque = document.getElementById('bloqueCanjes');
    var sel = document.getElementById('id_cliente');
    if (!bloque || !sel) { return; }

    var filas = bloque.querySelectorAll('.sgp-canje');

    function refrescar() {
        var cli = sel.value;
        var visibles = 0;
        filas.forEach(function (f) {
            var mio = cli && f.dataset.cliente === cli;
            f.hidden = !mio;
            // Un canje que deja de verse no puede irse marcado en el POST
            if (!mio) { f.querySelector('input').checked = false; }
            if (mio) { visibles++; }
        });
        bloque.hidden = visibles === 0;
    }

    sel.addEventListener('change', refrescar);

    // Al marcar el canje se marca su servicio, que es lo que hace falta para
    // que el canje sirva de algo. El evento se dispara a mano porque de él
    // cuelga el recálculo de horarios del selector de agenda.
    filas.forEach(function (f) {
        f.querySelector('input').addEventListener('change', function () {
            if (!this.checked) { return; }
            var srv = document.querySelector('.srv[value="' + f.dataset.servicio + '"]');
            if (srv && !srv.checked) {
                srv.checked = true;
                srv.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });

    refrescar();
})();

// **Las alergias de la clienta elegida, en su campo.**
//
// Se la elige en esta misma pantalla, así que el campo tiene que seguirla: en
// blanco parecería que no tiene ninguna, y quien agenda sin mirarlo se las
// borraría —el guardado sólo escribe si el valor cambió respecto del que se
// dibujó, y ese valor es el que este script pone en el campo escondido—.
//
// Es el mismo patrón que los canjes: vienen los de todas y el navegador
// muestra los de la elegida. Quien atiende ve todas las fichas igual.
(function () {
    var sel = document.getElementById('id_cliente'),
        campo = document.getElementById('alergias_titular'),
        base = document.getElementById('alergias_titular_base');
    if (!sel || !campo || !base) { return; }

    function traer() {
        var op = sel.options[sel.selectedIndex],
            val = (op && op.dataset.alergias) || '';
        campo.value = val;
        base.value = val;
    }

    sel.addEventListener('change', traer);

    // Tras un rechazo el formulario vuelve con lo que se había escrito: ahí no
    // se pisa nada, o se perdería justo lo que se estaba por corregir.
    @if (old('alergias_titular') === null)
        traer();
    @endif
})();
</script>
@endpush

