@extends('layout.app')

@section('titulo', 'Agenda')

@section('contenido')
    @php use App\Servicios\Navegacion; use App\Servicios\Permisos; @endphp

    <x-encabezado
        :sub="$verTodo ? 'Citas del día para todo el equipo.' : 'Tus citas del día.'"
        :accion="['ruta' => 'citas.form', 't' => 'Nueva cita', 'ic' => 'calendar-plus']" />

    {{-- Navegación por día: el salón trabaja mirando «hoy», y de ahí se mueve --}}
    <div class="spg-panel mb-3">
        <form method="get" class="d-flex gap-2 align-items-end flex-wrap">
            <div>
                <label class="form-label" for="dia">Día</label>
                <input type="date" class="form-control form-control-sm" id="dia" name="dia" value="{{ $dia }}">
            </div>
            <button class="btn btn-sm btn-oro"><i class="bi bi-calendar-week"></i> Ver</button>
            <a class="btn btn-sm btn-outline-neutro"
               href="{{ route('citas.agenda', ['dia' => date('Y-m-d', strtotime($dia . ' -1 day'))]) }}">
                <i class="bi bi-chevron-left"></i> Anterior</a>
            <a class="btn btn-sm btn-outline-neutro" href="{{ route('citas.agenda') }}">Hoy</a>
            <a class="btn btn-sm btn-outline-neutro"
               href="{{ route('citas.agenda', ['dia' => date('Y-m-d', strtotime($dia . ' +1 day'))]) }}">
                Siguiente <i class="bi bi-chevron-right"></i></a>
            <span class="ms-auto text-muted-warm" style="font-size:.85rem">
                {{ fecha_larga($dia) }}
            </span>
        </form>
    </div>

    {{-- **Los filtros del día.** Un día cargado son treinta filas, y buscar
         «las de Carmen» o «las que faltan atender» era recorrerlas a ojo.

         El día viaja con ellos en un campo oculto: sin eso, filtrar te
         devolvía a hoy y había que volver a elegir la fecha. --}}
    <x-filtros :f="$f" :ocultos="['dia' => $dia]" />

    @if ($puedeCobrar && ! $caja)
        <div class="alert alert-warning">
            La caja está cerrada. Para cobrar una seña hay que abrirla primero.
        </div>
    @endif

    <div class="spg-panel">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Hora</th><th>Cliente</th>
                        @if ($verTodo)<th>Profesional</th>@endif
                        <th>Servicios</th><th class="text-end">Duración</th>
                        <th>Estado</th><th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $c)
                        <tr>
                            <td style="white-space:nowrap"><strong>{{ fecha($c->fecha_hora, 'H:i') }}</strong></td>
                            <td>
                                {{-- **Arriba va quien SE ATIENDE; abajo y en chico, quien
                                     la pidió.** Con el badge «para Josefina» al lado del
                                     nombre de la clienta, el renglón tenía dos nombres del
                                     mismo tamaño y había que leer la etiqueta para saber
                                     cuál era cuál — y el que importa ese día es a quién
                                     hay que sentar en el sillón.

                                     Invertirlo no agrega píxeles: son las mismas dos
                                     líneas, con la jerarquía al derecho. El resto —cuántas
                                     van, qué dejó dicho— sigue en el modal, que es donde
                                     se mira una vez al preparar el turno. --}}
                                @if ($c->para_otra_persona)
                                    {{ $c->nombre_para ?: 'Otra persona' }}
                                    <div class="text-muted-warm" style="font-size:.78rem">
                                        la pidió {{ $c->cliente }}@if ((int) $c->personas > 1) ·
                                            {{ (int) $c->personas }} personas @endif
                                    </div>
                                @else
                                    {{ $c->cliente }}
                                    @if ((int) $c->personas > 1)
                                        <span class="badge-estado e-muted">{{ (int) $c->personas }} personas</span>
                                    @endif
                                @endif
                                @if ($c->observaciones || $c->para_otra_persona || (int) $c->personas > 1)
                                    <button type="button" class="btn btn-sm btn-outline-neutro spg-btn-mini"
                                            data-bs-toggle="modal" data-bs-target="#detCita{{ $c->id_cita }}"
                                            title="Ver lo que dejó dicho">
                                        <i class="bi bi-info-circle"></i></button>
                                @endif
                            </td>
                            @if ($verTodo)<td class="text-muted-warm">{{ $c->profesionales ?: $c->profesional }}</td>@endif
                            <td class="text-muted-warm">{{ $c->servicios ?: '—' }}</td>
                            <td class="text-end">{{ (int) $c->duracion_min }} min</td>
                            <td>
                                {!! estado_badge($c->estado) !!}
                                {{-- **Seña y cobro de la atención son dos badges, no uno.**
                                     `fn_cita_sena` suma todo lo que entró contra la cita, y
                                     desde la 7.19.0 eso incluye el cobro de la atención: una
                                     atención cobrada entera salía acá como «seña Gs. 280.000»,
                                     o sea el TOTAL de la cita presentado como adelanto. --}}
                                @if ((float) $c->sena > 0)
                                    <span class="badge-estado e-ok" title="Ya dejó una seña">seña {{ money($c->sena) }}</span>
                                @endif
                                @if ((float) ($c->cobrado_cita ?? 0) - (float) $c->sena > 0)
                                    <span class="badge-estado e-ok" title="Se cobró contra la cita, sin comprobante todavía">
                                        cobrado {{ money((float) $c->cobrado_cita - (float) $c->sena) }}</span>
                                @endif
                                {{-- Lo que la clienta registró desde el portal y todavía nadie
                                     confirmó. NO es plata que entró: no toca la caja hasta que
                                     alguien la confirme acá, cuando recibe el dinero. --}}
                                @if ((float) ($c->sena_pedida ?? 0) > 0)
                                    <span class="badge-estado e-warn" title="La clienta la registró desde el portal">
                                        seña {{ money($c->sena_pedida) }} a confirmar</span>
                                @elseif ((float) $c->sena <= 0 && (float) ($c->sena_requerida ?? 0) > 0)
                                    {{-- **La reserva no está confirmada**, y quien atiende
                                         tiene que verlo: el sistema le guarda el horario a
                                         la clienta por un plazo y después lo suelta solo.
                                         Sin esto, el salón la trata como cualquier otra
                                         cita y se entera el día que no aparece. --}}
                                    <span class="badge-estado e-no"
                                          title="Se le guarda el horario, pero se suelta si no confirma la seña">
                                        sin confirmar · falta seña {{ money($c->sena_requerida) }}</span>
                                @endif
                            </td>
                            <td class="text-end" style="white-space:nowrap">
                                {{-- **Ausente cierra la fila, igual que Cancelada.** La
                                     clienta no vino: no hay nada que marcar en proceso, ni
                                     que atender, ni que reprogramar. Los botones seguían
                                     ahí y eran seis promesas que el servidor rechaza una
                                     por una — y encima invitaban a «arreglarlo» tocando
                                     cosas sobre una cita que ya terminó.

                                     Lo único que sobrevive es cobrar lo que haya quedado
                                     debiendo, que sí puede pasar con una seña ya cobrada:
                                     eso está en la rama de abajo. --}}
                                @if (! in_array($c->estado, ['Cancelada', 'Atendida', 'Ausente'], true))
                                    {{-- **Con la cita ya en proceso, tres botones dejan de tener
                                         sentido y molestan.** Marcarla en proceso otra vez no hace
                                         nada; marcarla ausente contradice lo que se está viendo
                                         —la clienta está en el sillón—; y reprogramar una atención
                                         que ya empezó es mover a otro día algo que está pasando.
                                         Lo que queda es lo que sí se hace desde ahí: registrar la
                                         atención, y cancelar por si se cortó a mitad de camino. --}}
                                    @php
                                        $enCurso = $c->estado === 'En proceso';
                                        // **Atender y marcar en proceso son del DÍA de la
                                        // cita.** Mirando la agenda de mañana se podían
                                        // apretar igual, y una cita queda «en proceso» un
                                        // día en que nadie la está atendiendo. Cancelar y
                                        // reprogramar sí se hacen antes: son justamente
                                        // para lo que todavía no pasó.
                                        $esHoy = fecha($c->fecha_hora, 'Y-m-d') === fecha(ahora_bd(), 'Y-m-d');
                                    @endphp

                                    @unless ($enCurso || ! $esHoy)
                                        @if ($c->fichaje_ok ?? true)
                                            <form method="post" action="{{ route('citas.estado') }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="id_cita" value="{{ $c->id_cita }}">
                                                <input type="hidden" name="dia" value="{{ $dia }}">
                                                <input type="hidden" name="id_estado_cita" value="5">
                                                <button class="btn btn-sm btn-outline-neutro" title="Marcar en proceso">
                                                    <i class="bi bi-play-fill"></i></button>
                                            </form>
                                        @elseif ($c->prof_ausente ?? false)
                                            {{-- **«Falta fichaje» acá informaba mal.** Cuando a
                                                 esa persona ya se la marcó ausente, el problema
                                                 no es que todavía no fichó —no va a fichar— sino
                                                 que la cita se quedó sin quién la atienda. Decir
                                                 «falta fichaje» manda a esperar algo que no va a
                                                 pasar; lo que hay que hacer es cambiar el
                                                 profesional. --}}
                                            <span class="badge-estado e-no"
                                                  title="Ya está marcado como ausente hoy: hay que asignarle la cita a otra persona">
                                                <i class="bi bi-person-x"></i> profesional ausente</span>
                                        @else
                                            <span class="badge-estado e-warn" title="Primero hay que marcar la entrada en Asistencia">
                                                <i class="bi bi-person-check"></i> falta fichaje</span>
                                        @endif
                                    @endunless

                                    {{-- **Sin fichaje tampoco se registra la atención.**
                                         El servidor lo rechaza igual, así que ofrecer el
                                         botón es prometer algo que no va a cumplir: se
                                         aprieta, se carga la pantalla entera y el «no»
                                         llega al guardar. --}}
                                    @if ($esHoy && ($c->fichaje_ok ?? true)
                                         && $urlAtender = Navegacion::url('citas.atender'))
                                        <a class="btn btn-sm btn-outline-neutro" title="Registrar atención"
                                           href="{{ $urlAtender . '?id=' . $c->id_cita }}">
                                            <i class="bi bi-clipboard-check"></i></a>
                                    @endif

                                    @unless ($enCurso || ! $esHoy)
                                    <form method="post" action="{{ route('citas.estado') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="id_cita" value="{{ $c->id_cita }}">
                                        <input type="hidden" name="dia" value="{{ $dia }}">
                                        <input type="hidden" name="id_estado_cita" value="6">
                                        <button class="btn btn-sm btn-outline-neutro" title="Marcar ausente"
                                                data-confirmar="¿Marcar como ausente a {{ $c->cliente }}?">
                                            <i class="bi bi-person-x"></i></button>
                                    </form>
                                    @endunless

                                    {{-- **Cancelar una cita en curso no es cancelar.** La
                                         clienta está en el sillón: lo que corresponde es
                                         terminar de atenderla o registrar lo que se hizo. --}}
                                    @unless ($enCurso)
                                    <form method="post" action="{{ route('citas.cancelar') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="id_cita" value="{{ $c->id_cita }}">
                                        <input type="hidden" name="dia" value="{{ $dia }}">
                                        <button class="btn btn-sm btn-outline-neutro" title="Cancelar"
                                                data-confirmar="¿Cancelar la cita de {{ $c->cliente }} de las {{ fecha($c->fecha_hora, 'H:i') }}?">
                                            <i class="bi bi-x-lg"></i></button>
                                    </form>
                                    @endunless

                                    @unless ($enCurso)
                                        <button class="btn btn-sm btn-outline-neutro" title="Reprogramar"
                                                data-bs-toggle="modal" data-bs-target="#modalRepro{{ $c->id_cita }}">
                                            <i class="bi bi-calendar-event"></i></button>
                                    @endunless

                                    {{-- **El ícono cambió.** `person-gear` al lado de
                                         `person-x` —marcar ausente— son dos monigotes
                                         casi iguales, y las dos acciones no se parecen
                                         en nada: una anota que la clienta no vino y la
                                         otra le cambia quién la atiende. Las flechas
                                         dicen «pasa de uno a otro». --}}
                                    @if ($puedeReasignar && ! $enCurso)
                                        <button class="btn btn-sm btn-outline-neutro" title="Cambiar profesional"
                                                data-bs-toggle="modal" data-bs-target="#modalReasignar{{ $c->id_cita }}">
                                            <i class="bi bi-arrow-left-right"></i></button>
                                    @endif

                                    {{-- La seña mueve plata: solo para quien maneja cobros y con
                                         la caja abierta. Con la caja cerrada el aviso de arriba
                                         explica por qué no está el botón.

                                         **Y no va si ya se cobró ni con la cita en proceso.**
                                         La seña garantiza una reserva: con la clienta ya en el
                                         sillón no hay nada que garantizar, y una vez cobrada el
                                         botón ofrece cobrarla de nuevo. Lo que falte se cobra al
                                         terminar, desde «Cobrar». --}}
                                    @if ($puedeCobrar && $caja && $c->estado !== 'Ausente'
                                         && ! $enCurso && (float) ($c->cobrado_cita ?? 0) <= 0)
                                        <button class="btn btn-sm btn-outline-neutro" title="Cobrar una seña"
                                                data-bs-toggle="modal" data-bs-target="#modalSena{{ $c->id_cita }}">
                                            <i class="bi bi-cash-coin"></i></button>
                                    @endif
                                @elseif ($c->estado === 'Atendida')
                                    {{-- Atender y cobrar son dos pasos, y entre uno y
                                         otro la plata se olvidaba: la cita quedaba
                                         Atendida, acá no había más que un guión, y como
                                         la clienta no siempre pide factura nadie se
                                         acordaba de pasar por Facturación. Ahora el
                                         estado del cobro se ve y se resuelve desde acá.

                                         Son TRES situaciones distintas y cada una dice
                                         lo suyo: sin comprobante, con saldo, y saldada. --}}
                                    <a class="btn btn-sm btn-outline-neutro" title="Ver detalle de la atención"
                                       href="{{ route('citas.atender', ['id' => $c->id_cita]) }}">
                                        <i class="bi bi-eye"></i> Detalle</a>
                                    @if (! $c->id_factura)
                                        {{-- **Primero se cobra, después el comprobante.**
                                             Es el orden del mostrador: la clienta paga y recién
                                             ahí dice si quiere factura o comprobante de pago.
                                             Antes el botón llevaba a Emitir, o sea que obligaba
                                             a elegir el documento antes de tocar la plata.
                                             Se puede porque el cobro cuelga de la CITA y
                                             `fn_factura_saldo` ya lo descuenta al emitir. --}}
                                        @if ($puedeCobrar && $caja)
                                            <button class="btn btn-sm btn-oro" title="Cobrar esta atención"
                                                    data-bs-toggle="modal" data-bs-target="#modalSena{{ $c->id_cita }}">
                                                <i class="bi bi-cash-coin"></i> Cobrar</button>
                                        @elseif ($puedeFacturar)
                                            <a class="btn btn-sm btn-outline-neutro"
                                               title="Emitir el comprobante de esta atención"
                                               href="{{ route('facturacion.emitir', ['cita' => $c->id_cita]) }}">
                                                <i class="bi bi-receipt-cutoff"></i> Emitir</a>
                                        @else
                                            <span class="badge-estado e-warn" title="Todavía no se le emitió comprobante">
                                                sin cobrar</span>
                                        @endif
                                    @elseif ((float) $c->saldo > 0.01)
                                        <a class="btn btn-sm btn-oro"
                                           title="{{ $c->nro_comprobante }} · queda {{ money($c->saldo) }} por cobrar"
                                           href="{{ route('facturacion.facturas', ['q' => $c->nro_comprobante]) }}">
                                            <i class="bi bi-cash-coin"></i> Debe {{ money($c->saldo) }}</a>
                                    @else
                                        <a class="btn btn-sm btn-outline-neutro"
                                           title="Ver el comprobante {{ $c->nro_comprobante }}"
                                           href="{{ route('facturacion.factura_ver', ['id' => $c->id_factura]) }}">
                                            <i class="bi bi-check2-circle"></i> Cobrada</a>
                                    @endif
                                @else
                                    <span class="text-muted-warm" style="font-size:.8rem">—</span>
                                @endif
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="{{ $verTodo ? 7 : 6 }}">
                                <div class="spg-vacio">
                                    <i class="bi bi-calendar-week"></i>
                                    <div class="t">No hay citas para el {{ fecha($dia, 'd/m/Y') }}.</div>
                                    <div class="d">Agendá una con el botón «Nueva cita».</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

        @foreach ($rows as $c)
                        {{-- **Lo que la clienta dejó dicho al reservar.** Se guardaba
                             desde el portal y no se mostraba en ninguna pantalla: quien
                             atiende no sabía que la cita era para la hija, ni cuánta
                             gente esperar, ni lo que la clienta pidió por escrito.

                             Va en un modal y no en la fila porque es lo que se mira
                             una vez, al preparar el turno; la fila tiene que seguir
                             leyéndose de un vistazo.

                             **Y va FUERA de la tabla, que es lo que lo tenía roto.**
                             Estaba dentro de un `<tr class="d-none">`, y un ancestro
                             con `display:none` gana siempre: el modal no podía hacerse
                             visible ni con Bootstrap haciendo su trabajo. Se veía el
                             fondo gris y nada más. --}}
                        @if ($c->observaciones || $c->para_otra_persona || (int) $c->personas > 1)
                            <div class="modal fade" id="detCita{{ $c->id_cita }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" style="font-size:1rem">
                                                <i class="bi bi-info-circle"></i>
                                                {{ fecha($c->fecha_hora, 'H:i') }} · {{ $c->cliente }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                    aria-label="Cerrar"></button>
                                        </div>
                                        <div class="modal-body">
                                            <dl class="spg-ficha">
                                                @if ($c->para_otra_persona)
                                                    <dt>Es para</dt>
                                                    <dd>
                                                        <strong>{{ $c->nombre_para ?: 'otra persona' }}</strong>
                                                        <div class="text-muted-warm" style="font-size:.8rem">
                                                            La reservó {{ $c->cliente }}.
                                                        </div>
                                                    </dd>
                                                @endif
                                                @if ((int) $c->personas > 1)
                                                    <dt>Van</dt>
                                                    <dd>
                                                        {{ (int) $c->personas }} personas
                                                        {{-- **Quiénes, no sólo cuántas.** El número decía
                                                             que iban a llegar tres y quien atiende no sabía
                                                             a quién esperar. La primera es la clienta, así
                                                             que acá van las otras. --}}
                                                        @if (! empty($acompanantes[$c->id_cita]))
                                                            <div class="text-muted-warm mb-1" style="font-size:.85rem">
                                                                Con {{ $c->cliente }} vienen:
                                                            </div>
                                                            {{-- **Cada acompañante puede tener su ficha.** El
                                                                 salón la va a atender igual que a quien reservó,
                                                                 y sin ficha propia no hay dónde anotarle sus
                                                                 preferencias: el día que quiera abrir su cuenta
                                                                 arranca de cero. No se le crea sola —sería
                                                                 inventar una persona con un nombre a medias—:
                                                                 se ofrece, con el nombre ya puesto. --}}
                                                            <ul class="list-unstyled mb-0" style="font-size:.85rem">
                                                                @foreach ($acompanantes[$c->id_cita] as $ac)
                                                                    <li class="mb-1">
                                                                        {{ $ac->completo }}
                                                                        @if ($ac->id_cliente)
                                                                            <a class="btn btn-sm btn-outline-neutro py-0"
                                                                               href="{{ route('clientes.historial', $ac->id_cliente) }}">
                                                                                <i class="bi bi-clock-history"></i> Su historial</a>
                                                                        @elseif (Permisos::puede('clientes.registro'))
                                                                            <a class="btn btn-sm btn-rapido py-0"
                                                                               href="{{ route('clientes.form', ['nombre' => $ac->nombre,
                                                                                                                'apellido' => $ac->apellido]) }}">
                                                                                <i class="bi bi-person-plus"></i> Crear su ficha</a>
                                                                        @endif
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        @endif
                                                    </dd>
                                                @endif
                                                @if ($c->observaciones)
                                                    <dt>Dejó dicho</dt>
                                                    <dd>{{ $c->observaciones }}</dd>
                                                @endif
                                                <dt>Servicios</dt>
                                                <dd>{{ $c->servicios ?: '—' }}</dd>
                                            </dl>

                                            {{-- **Si es para otra persona, se le puede abrir su
                                                 ficha.** Quien vino no es la que reservó, y sin
                                                 ficha propia no hay dónde anotarle las
                                                 preferencias ni queda su historial. El nombre va
                                                 precargado; el resto lo completa quien atiende. --}}
                                            @if ($c->para_otra_persona && $c->nombre_para)
                                                @if ($c->id_cliente_para)
                                                    {{-- **Ya tiene ficha: lo que hace falta es su
                                                         HISTORIAL.** Quien la atiende necesita saber
                                                         qué le hicieron la vez pasada y con qué
                                                         color, y eso vive en su ficha, no en la de
                                                         quien reservó. Ofrecer «crear» otra vez
                                                         dejaría dos fichas de la misma persona y el
                                                         historial partido al medio. --}}
                                                    <a class="btn btn-sm btn-rapido"
                                                       href="{{ route('clientes.historial', $c->id_cliente_para) }}">
                                                        <i class="bi bi-clock-history"></i>
                                                        Ver el historial de {{ $c->nombre_para }}</a>
                                                    <div class="text-muted-warm mt-2" style="font-size:.8rem">
                                                        Qué se le hizo antes y qué prefiere.
                                                    </div>
                                                @elseif (Permisos::puede('clientes.registro'))
                                                    @php
                                                        $partes = preg_split('/\s+/', trim((string) $c->nombre_para), 2);
                                                    @endphp
                                                    <a class="btn btn-sm btn-rapido"
                                                       href="{{ route('clientes.form', ['nombre' => $partes[0] ?? '',
                                                                                        'apellido' => $partes[1] ?? '']) }}">
                                                        <i class="bi bi-person-plus"></i>
                                                        Crear la ficha de {{ $c->nombre_para }}</a>
                                                    <div class="text-muted-warm mt-2" style="font-size:.8rem">
                                                        Para guardarle sus preferencias y que tenga su propio historial.
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-neutro"
                                                    data-bs-dismiss="modal">Cerrar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
        @endforeach
        </div>
    </div>

    {{-- Un modal de reprogramación por cita --}}
    @foreach ($rows as $c)
        @continue (in_array($c->estado, ['Cancelada', 'Atendida'], true))
        <div class="modal fade" id="modalRepro{{ $c->id_cita }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="{{ route('citas.reprogramar') }}">
                        @csrf
                        <input type="hidden" name="id_cita" value="{{ $c->id_cita }}">
                        <input type="hidden" name="dia" value="{{ $dia }}">
                        <div class="modal-header">
                            <h5 class="modal-title" style="font-size:1rem">
                                <i class="bi bi-calendar-event"></i> Reprogramar la cita de {{ $c->cliente }}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted-warm" style="font-size:.85rem">
                                Ahora está para el <strong>{{ fecha($c->fecha_hora) }}</strong>
                                con {{ $c->profesional }}.
                            </p>
                            <label class="form-label" for="nf{{ $c->id_cita }}">Nueva fecha y hora</label>
                            <input type="datetime-local" class="form-control" id="nf{{ $c->id_cita }}"
                                   name="nueva_fecha" required>
                            <p class="text-muted-warm mt-2 mb-0" style="font-size:.78rem">
                                Se comprueba la disponibilidad antes de guardar: si el horario ya
                                está tomado, el sistema lo avisa.
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-oro">Reprogramar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

    @if ($puedeReasignar)
        {{-- Administración puede cambiar sólo la persona de esta cita. El
             servidor vuelve a comprobar turno, disponibilidad y estado. --}}
        @foreach ($rows as $c)
            @continue (in_array($c->estado, ['Cancelada', 'Atendida', 'Ausente'], true))
            <div class="modal fade" id="modalReasignar{{ $c->id_cita }}" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post" action="{{ route('citas.reasignar.una') }}">
                            @csrf
                            <input type="hidden" name="id_cita" value="{{ $c->id_cita }}">
                            <input type="hidden" name="dia" value="{{ $dia }}">
                            <div class="modal-header">
                                <h5 class="modal-title" style="font-size:1rem">
                                    <i class="bi bi-person-gear"></i> Cambiar profesional</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-muted-warm" style="font-size:.84rem">
                                    {{ $c->cliente }} · {{ fecha($c->fecha_hora, 'd/m H:i') }}<br>
                                    Profesional actual: <strong>{{ $c->profesional }}</strong>.
                                    Se conservarán los servicios y el horario.
                                </p>
                                <label class="form-label" for="reas{{ $c->id_cita }}">Atenderá *</label>
                                @php
                                    // **Sólo quien hace TODOS los servicios de esta cita.**
                                    // El combo listaba al equipo entero, así que se podía
                                    // pasar una coloración a la manicurista: el servidor lo
                                    // rechaza, pero el rechazo llegaba después del clic. Sale
                                    // de `fn_usuario_hace_servicio`, la misma autoridad que
                                    // valida el reparto al agendar.
                                    $aptos = $profsPorCita[$c->id_cita] ?? null;
                                    $ofrecidos = collect($profs)->filter(
                                        fn ($p) => (int) $p->id_usuario !== (int) $c->id_usuario
                                            && ($aptos === null || isset($aptos[(int) $p->id_usuario]))
                                    );
                                @endphp
                                <select class="form-select" id="reas{{ $c->id_cita }}" name="a" required>
                                    <option value="">— Elegí un profesional —</option>
                                    @foreach ($ofrecidos as $p)
                                        <option value="{{ $p->id_usuario }}">{{ $p->nombre }}</option>
                                    @endforeach
                                </select>
                                @if ($ofrecidos->isEmpty())
                                    {{-- **Se dice, no se deja el combo vacío.** Un desplegable
                                         con una sola opción vacía se lee como que el sistema se
                                         rompió; lo que pasa es que nadie más hace eso. --}}
                                    <div class="form-text txt-no">
                                        Nadie más del equipo hace todos los servicios de esta cita.
                                        Se puede repartir desde «Editar», dándole a cada servicio
                                        su profesional.
                                    </div>
                                @else
                                    <div class="form-text">Se ofrecen sólo los que hacen estos servicios.
                                        Igual tiene que trabajar ese día y quedar libre en ese horario.</div>
                                @endif

                                {{-- **El motivo se le manda a la clienta.** No es
                                     burocracia: va en el correo que le avisa el
                                     cambio, y es lo único que queda en la auditoría
                                     para saber por qué esa cita cambió de manos. --}}
                                <label class="form-label mt-3" for="motReas{{ $c->id_cita }}">¿Por qué se cambia? *</label><x-ayuda>Al menos 10 caracteres. Se le avisa por correo a la clienta, con este motivo.</x-ayuda>
                                <textarea class="form-control" id="motReas{{ $c->id_cita }}" name="motivo"
                                          rows="2" maxlength="200" minlength="10" required
                                          placeholder="Se lo vamos a contar a la clienta"></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                                <button class="btn btn-oro"><i class="bi bi-check-lg"></i> Cambiar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif

    {{-- Un modal de seña por cita.
         La seña se cobra ANTES de atender, así que todavía no hay factura: queda
         como un cobro atado a la cita, con id_factura en NULL. No hay que
         vincularla después al comprobante — `fn_factura_saldo` ya descuenta los
         cobros de la cita, y vinculándola se restaría dos veces. --}}
    @if ($puedeCobrar && $caja)
        @foreach ($rows as $c)
            {{-- Atendida SÍ entra —es el cobro de la atención, el caso normal—
                 salvo que ya tenga comprobante: ahí el cobro va contra él. --}}
            @continue (in_array($c->estado, ['Cancelada', 'Ausente'], true)
                       || ($c->estado === 'Atendida' && $c->id_factura))
            <div class="modal fade" id="modalSena{{ $c->id_cita }}" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post" action="{{ route('facturacion.sena') }}">
                            @csrf
                            <input type="hidden" name="id_cita" value="{{ $c->id_cita }}">
                            <input type="hidden" name="dia" value="{{ $dia }}">
                            {{-- Si viene de una solicitud del portal, se confirma ESA:
                                 el cobro queda enlazado y la solicitud deja de estar
                                 pendiente. Sin solicitud, es una seña cargada directo. --}}
                            @if ($c->id_solicitud)
                                <input type="hidden" name="id_solicitud" value="{{ $c->id_solicitud }}">
                            @endif
                            <div class="modal-header">
                                <h5 class="modal-title" style="font-size:1rem">
                                    <i class="bi bi-cash-coin"></i>
                                    {{-- **No se llama «seña» si el salón no pide ninguna.**
                                         Con los servicios sin `sena_porcentaje` cargado el
                                         modal proponía el TOTAL de la cita bajo el título
                                         «Seña de …», así que lo que se cobraba entero
                                         quedaba rotulado como adelanto. Una seña es un
                                         porcentaje que el salón decide; si no decidió
                                         ninguno, esto es un cobro. --}}
                                    @if ($c->estado === 'Atendida')
                                        Cobrar la atención de {{ $c->cliente }}
                                    @elseif ($c->id_solicitud)
                                        Confirmar la seña de {{ $c->cliente }}
                                    @elseif ((float) ($c->sena_requerida ?? 0) > 0)
                                        Seña de {{ $c->cliente }}
                                    @else
                                        Cobrar la cita de {{ $c->cliente }}
                                    @endif
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            @php
                                // **Cuánto falta cobrar.** El monto no se adivina: la
                                // base topea el cobro contra lo que valen los servicios
                                // de la cita, así que hasta ahora la única forma de
                                // enterarse del número era mandar uno de más y leer el
                                // rechazo. Se calcula acá con la misma cuenta.
                                $totalCita = (float) ($c->total_cita ?? 0);
                                // **Contra todo lo cobrado, no sólo contra la seña.**
                                // Con la atención cobrada en parte, restando sólo la seña
                                // el saldo salía de más y el modal proponía cobrar dos
                                // veces lo mismo.
                                $cobrado = (float) ($c->cobrado_cita ?? $c->sena);
                                $falta = max(0, $totalCita - $cobrado);

                                // **Lo que se propone es la SEÑA, no la cita entera.**
                                // El modal venía con el total y con eso se cobraba de
                                // más con un clic: una seña es un adelanto, y cuánto
                                // pide el salón lo dice `servicio.sena_porcentaje`.
                                //
                                // Prioridad: lo que la clienta anunció desde el portal
                                // —es lo que hay que confirmar—, si no lo que el salón
                                // pide, y recién si no hay ninguno, lo que falte.
                                //
                                // **Y una vez que la seña está cobrada, lo que se
                                // cobra es LO QUE FALTA.** `sena_requerida` es lo que
                                // el salón pide de adelanto y no cambia al cobrarse,
                                // así que seguía proponíendose el mismo número: se
                                // cobraba la seña dos veces y el comprobante quedaba
                                // con saldo pendiente por la diferencia.
                                $pide = (float) ($c->sena_requerida ?? 0);
                                // Con la atención en curso tampoco: la clienta está en el
                                // sillón, así que ya no hay reserva que garantizar.
                                $enCursoCobro = $c->estado === 'En proceso';
                                $yaSeno = (float) $c->sena > 0;
                                $sugerido = (float) ($c->sena_pedida ?? 0) > 0
                                    ? (float) $c->sena_pedida
                                    : (! $yaSeno && $pide > 0 ? min($pide, $falta) : $falta);
                            @endphp
                            <div class="modal-body">
                                <p class="text-muted-warm" style="font-size:.85rem">
                                    Cita del <strong>{{ fecha($c->fecha_hora) }}</strong>.
                                    @if ((float) $c->sena > 0)
                                        Ya dejó <strong>{{ money($c->sena) }}</strong> de seña.
                                    @endif
                                    @if ($cobrado - (float) $c->sena > 0)
                                        Ya se cobró <strong>{{ money($cobrado - (float) $c->sena) }}</strong>
                                        de la atención.
                                    @endif
                                    @if ($c->id_solicitud)
                                        La clienta registró <strong>{{ money($c->sena_pedida) }}</strong>
                                        desde el portal. Confirmá el monto que recibiste de verdad:
                                        puede no ser el mismo.
                                    @endif
                                </p>

                                {{-- **El comprobante que adjuntó.** Sin esto, confirmar una
                                     seña transferida es creerle de palabra o llamar al banco:
                                     la cita se reserva desde afuera del local, así que no hay
                                     nada físico que haya podido entregar. --}}
                                @if ($c->id_solicitud && ! empty($c->sena_comprobante))
                                    <a class="btn btn-sm btn-rapido mb-2" target="_blank" rel="noopener"
                                       href="{{ route('facturacion.sena.comprobante', ['id' => $c->id_solicitud]) }}">
                                        <i class="bi bi-paperclip"></i> Ver el comprobante que envió</a>
                                @elseif ($c->id_solicitud)
                                    <div class="text-muted-warm mb-2" style="font-size:.8rem">
                                        No adjuntó comprobante: confirmá sólo si el dinero ya está.
                                    </div>
                                @endif

                                {{-- Lo que hay que cobrar, arriba del campo y no en un
                                     rechazo posterior. Un modal que pide un monto sin
                                     decir cuál es el monto obliga a saberlo de memoria. --}}
                                @php
                                    $lista = (float) ($c->total_lista ?? $totalCita);
                                    $dg = $desglosesSena[$c->id_cita] ?? null;
                                @endphp
                                @if ($totalCita > 0)
                                    {{-- **Cada número con su origen.** Antes eran cuatro
                                         cifras en una línea —lista, descuento, total,
                                         seña— y no se podía decir de dónde salía ninguna:
                                         quien cobra no puede defenderlas si la clienta
                                         pregunta, y un total más bajo sin explicación se
                                         lee como un error de la pantalla. --}}
                                    <table class="table table-sm align-middle mb-2" style="font-size:.86rem">
                                        <tbody>
                                            @foreach (($dg['filas'] ?? []) as $fl)
                                                <tr>
                                                    <td>
                                                        {{ $fl->nombre }}
                                                        @if ((int) $fl->canjeado > 0)
                                                            <span class="badge-estado e-ok">canjeado</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-end text-muted-warm">{{ money($fl->precio) }}</td>
                                                </tr>
                                            @endforeach
                                            <tr>
                                                <td>Precio de lista</td>
                                                <td class="text-end">{{ money($lista) }}</td>
                                            </tr>
                                            @if ($lista > $totalCita)
                                                <tr class="txt-ok">
                                                    <td>
                                                        Descuento
                                                        {{-- **Cuál de los dos ganó.** El sistema
                                                             aplica uno solo —el mejor entre el
                                                             del nivel y la promoción vigente— y
                                                             sin decir cuál, el número no se puede
                                                             explicar. --}}
                                                        <span class="text-muted-warm" style="font-size:.8rem">
                                                            @if (! empty($dg['promo']))
                                                                por la promoción «{{ $dg['promo'] }}»
                                                            @elseif (! empty($dg['nivel']))
                                                                por su nivel {{ $dg['nivel'] }}
                                                            @endif
                                                        </span>
                                                    </td>
                                                    <td class="text-end">− {{ money($lista - $totalCita) }}</td>
                                                </tr>
                                            @endif
                                            <tr style="border-top:2px solid var(--gris-calido)">
                                                <th>Total de la cita</th>
                                                <th class="text-end">{{ money($totalCita) }}</th>
                                            </tr>
                                            @if ((float) $c->sena > 0)
                                                <tr>
                                                    <td class="text-muted-warm">Ya cobrado (seña)</td>
                                                    <td class="text-end text-muted-warm">− {{ money($c->sena) }}</td>
                                                </tr>
                                            @endif
                                            @if ($cobrado - (float) $c->sena > 0)
                                                <tr>
                                                    <td class="text-muted-warm">Ya cobrado (de la atención)</td>
                                                    <td class="text-end text-muted-warm">
                                                        − {{ money($cobrado - (float) $c->sena) }}</td>
                                                </tr>
                                            @endif
                                            <tr>
                                                {{-- **Con la atención ya registrada no se pide
                                                     seña.** La seña garantiza una reserva, y
                                                     con la clienta ya atendida no hay nada que
                                                     reservar: lo que queda es cobrar el saldo.
                                                     El modal seguía anunciando «seña que pide
                                                     el salón» sobre citas atendidas, y encima
                                                     ese número crece con los servicios que se
                                                     agregan en el sillón — que no se señan. --}}
                                                @if ($pide > 0 && $c->estado !== 'Atendida' && ! $enCursoCobro)
                                                    <th>Seña que pide el salón</th>
                                                    <th class="text-end txt-oro">{{ money($pide) }}</th>
                                                @else
                                                    <th>A cobrar</th>
                                                    <th class="text-end txt-oro">{{ money($falta) }}</th>
                                                @endif
                                            </tr>
                                        </tbody>
                                    </table>
                                @else
                                    {{-- Sin servicios cargados no hay monto que cobrar, y la
                                         base lo rechaza igual: mejor decirlo antes. --}}
                                    <div class="alert alert-warning py-2 mb-2" style="font-size:.82rem">
                                        Esta cita no tiene servicios cargados, así que no hay monto que cobrar.
                                    </div>
                                @endif

                                {{-- **El mismo desglose que ve la clienta.** Quien
                                     confirma el pago tiene que poder comprobar que el
                                     número está bien, y con un total suelto no puede:
                                     no sabe si esa seña es de un servicio o de tres.
                                     Es el mismo bloque, así que el salón y la clienta
                                     no pueden estar mirando cuentas distintas. --}}
                                @if ($c->estado !== 'Atendida' && ! empty($desglosesSena[$c->id_cita]['filas']))
                                    @include('facturacion._sena_desglose', [
                                        'desglose' => $desglosesSena[$c->id_cita],
                                        'yaPuesta' => (float) $c->sena,
                                    ])
                                @endif

                                {{-- **Las mismas líneas que en Facturas.** Acá había un
                                     solo monto y un solo medio: no se podía dividir el pago
                                     —mitad efectivo, mitad tarjeta, que en el mostrador es
                                     lo normal—, los campos de tarjeta y de banco no
                                     aparecían nunca y no había vuelto. Es el mismo
                                     componente, así que las dos pantallas no se pueden
                                     desfasar. --}}
                                {{-- **Confirmando una seña se propone la seña, no el total.**
                                 La clienta registró un monto desde el portal y lo que hay
                                 que confirmar es ESE; proponer lo que falta de la cita
                                 entera hacía cobrar de más con un clic. El tope sigue
                                 siendo lo que falta: se puede corregir hacia arriba si de
                                 verdad entregó más. --}}
                            @if ($c->id_solicitud)
                                {{-- **Confirmar no es fijar el monto.** Acá el trabajo es
                                     decir «sí, este dinero entró»: dejar el campo editable
                                     invita a corregirlo de memoria, y entonces lo que la
                                     clienta registró y lo que el salón cobró dejan de ser
                                     lo mismo sin que nada lo explique.

                                     El monto viaja en un `hidden` y se muestra al lado. Lo
                                     único que se elige es CON QUÉ pagó, que eso el portal
                                     no lo sabe. Si el dinero que llegó no es ése, se
                                     rechaza la solicitud y se cobra a mano. --}}
                                <div class="mb-2">
                                    <div class="form-label">Monto que registró la clienta</div>
                                    <div class="val oro" style="font-size:1.25rem">{{ money($sugerido) }}</div>
                                    <input type="hidden" name="monto[]" value="{{ monto_input($sugerido) }}">
                                </div>
                                @include('facturacion._caja_elegir', [
                                    'cajas' => \App\Servicios\Caja::abiertasDe(),
                                    'uid' => 'Sena' . $c->id_cita,
                                    'rotulo' => '¿A qué caja entra?',
                                ])
                                <div class="mb-2">
                                    <label class="form-label" for="mpSena{{ $c->id_cita }}">¿Con qué pagó?</label>
                                    <select class="form-select form-select-sm" name="metodo[]"
                                            id="mpSena{{ $c->id_cita }}" required>
                                        @foreach ($metodos as $m)
                                            <option value="{{ $m->id_metodo_pago }}">{{ $m->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @else
                            <x-cobro-lineas :uid="$c->id_cita" :max="$falta" :metodos="$metodos"
                                :sugerido="$sugerido" />
                            @endif

                                {{-- **La caja es del local, no de quien la abrió.** Desde la
                                     7.36.3 la sucursal del cobro se deduce de la cita, así que
                                     nombrar a la persona informaba mal: la plata entra al cajón
                                     de esta sucursal, la haya abierto quien la haya abierto. --}}
                                <p class="text-muted-warm mt-2 mb-0" style="font-size:.78rem">
                                    Entra en la caja de <strong>{{ session('sucursal_nom') ?: 'esta sucursal' }}</strong>
                                    (la abrió {{ $caja->responsable }})
                                    @if ($c->estado === 'Atendida')
                                        y después elegís el comprobante: factura o comprobante de pago,
                                        lo que pida la clienta. Sale saldado solo.
                                    @else
                                        y se descuenta sola del total cuando se facture la cita.
                                    @endif
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                                {{-- El título decía «Cobrar la atención» y el botón «Cobrar
                                     la seña»: dos nombres para el mismo clic. --}}
                                <button class="btn btn-oro">
                                    @if ($c->estado === 'Atendida')
                                        Cobrar
                                    @elseif ($c->id_solicitud)
                                        Confirmar la seña
                                    @elseif ((float) ($c->sena_requerida ?? 0) > 0)
                                        Cobrar la seña
                                    @else
                                        Cobrar
                                    @endif
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
@endsection
