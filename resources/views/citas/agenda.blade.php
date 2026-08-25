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
                                {{ $c->cliente }}
                                {{-- **La cita puede ser para otra persona**, y hasta ahora
                                     no se veía en ningún lado: quien atiende esperaba a la
                                     clienta y venía la hija. Igual con cuánta gente va. --}}
                                @if ($c->para_otra_persona)
                                    <span class="badge-estado e-warn"
                                          title="La reservó {{ $c->cliente }} para otra persona">
                                        para {{ $c->nombre_para ?: 'otra persona' }}</span>
                                @endif
                                @if ((int) $c->personas > 1)
                                    <span class="badge-estado e-muted">{{ (int) $c->personas }} personas</span>
                                @endif
                                @if ($c->observaciones || $c->para_otra_persona || (int) $c->personas > 1)
                                    <button type="button" class="btn btn-sm btn-outline-neutro spg-btn-mini"
                                            data-bs-toggle="modal" data-bs-target="#detCita{{ $c->id_cita }}"
                                            title="Ver lo que dejó dicho">
                                        <i class="bi bi-info-circle"></i></button>
                                @endif
                            </td>
                            @if ($verTodo)<td class="text-muted-warm">{{ $c->profesional }}</td>@endif
                            <td class="text-muted-warm">{{ $c->servicios ?: '—' }}</td>
                            <td class="text-end">{{ (int) $c->duracion_min }} min</td>
                            <td>
                                {!! estado_badge($c->estado) !!}
                                @if ((float) $c->sena > 0)
                                    <span class="badge-estado e-ok" title="Ya dejó una seña">seña {{ money($c->sena) }}</span>
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
                                @if (! in_array($c->estado, ['Cancelada', 'Atendida'], true))
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
                                        @else
                                            <span class="badge-estado e-warn" title="Primero hay que marcar la entrada en Asistencia">
                                                <i class="bi bi-person-check"></i> falta fichaje</span>
                                        @endif
                                    @endunless

                                    @if ($esHoy && $urlAtender = Navegacion::url('citas.atender'))
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

                                    @if ($puedeReasignar && ! $enCurso)
                                        <button class="btn btn-sm btn-outline-neutro" title="Cambiar profesional"
                                                data-bs-toggle="modal" data-bs-target="#modalReasignar{{ $c->id_cita }}">
                                            <i class="bi bi-person-gear"></i></button>
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
                                         && ! $enCurso && (float) $c->sena <= 0)
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

                        {{-- **Lo que la clienta dejó dicho al reservar.** Se guardaba
                             desde el portal y no se mostraba en ninguna pantalla: quien
                             atiende no sabía que la cita era para la hija, ni cuánta
                             gente esperar, ni lo que la clienta pidió por escrito.

                             Va en un modal y no en la fila porque es lo que se mira
                             una vez, al preparar el turno; la fila tiene que seguir
                             leyéndose de un vistazo. --}}
                        @if ($c->observaciones || $c->para_otra_persona || (int) $c->personas > 1)
                            <tr class="d-none"><td colspan="{{ $verTodo ? 7 : 6 }}">
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
                                                    <dd>{{ (int) $c->personas }} personas</dd>
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
                                            @if ($c->para_otra_persona && $c->nombre_para && Permisos::puede('clientes.registro'))
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
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-neutro"
                                                    data-bs-dismiss="modal">Cerrar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </td></tr>
                        @endif
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
                                <select class="form-select" id="reas{{ $c->id_cita }}" name="a" required>
                                    <option value="">— Elegí un profesional —</option>
                                    @foreach ($profs as $p)
                                        @if ((int) $p->id_usuario !== (int) $c->id_usuario)
                                            <option value="{{ $p->id_usuario }}">{{ $p->nombre }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <div class="form-text">Sólo se podrá guardar si trabaja ese día y queda libre para todos los servicios.</div>
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
                                    @if ($c->estado === 'Atendida')
                                        Cobrar la atención de {{ $c->cliente }}
                                    @else
                                        {{ $c->id_solicitud ? 'Confirmar la seña de' : 'Seña de' }} {{ $c->cliente }}
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
                                $falta = max(0, $totalCita - (float) $c->sena);

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
                                $yaSeno = (float) $c->sena > 0;
                                $sugerido = (float) ($c->sena_pedida ?? 0) > 0
                                    ? (float) $c->sena_pedida
                                    : (! $yaSeno && $pide > 0 ? min($pide, $falta) : $falta);
                            @endphp
                            <div class="modal-body">
                                <p class="text-muted-warm" style="font-size:.85rem">
                                    Cita del <strong>{{ fecha($c->fecha_hora) }}</strong>.
                                    @if ((float) $c->sena > 0)
                                        Ya dejó <strong>{{ money($c->sena) }}</strong>.
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
                                @if ($totalCita > 0)
                                    <div class="spg-cobro-cuenta mb-2">
                                        <span>La cita vale <strong>{{ money($totalCita) }}</strong></span>
                                        @if ((float) $c->sena > 0)
                                            <span>· ya cobrado <strong>{{ money($c->sena) }}</strong></span>
                                        @endif
                                        @if ($pide > 0)
                                            <strong class="spg-cobro-falta">Seña que pide el salón: {{ money($pide) }}</strong>
                                        @else
                                            <strong class="spg-cobro-falta">A cobrar {{ money($falta) }}</strong>
                                        @endif
                                    </div>
                                @else
                                    {{-- Sin servicios cargados no hay monto que cobrar, y la
                                         base lo rechaza igual: mejor decirlo antes. --}}
                                    <div class="alert alert-warning py-2 mb-2" style="font-size:.82rem">
                                        Esta cita no tiene servicios cargados, así que no hay monto que cobrar.
                                    </div>
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
                            <x-cobro-lineas :uid="$c->id_cita" :max="$falta" :metodos="$metodos"
                                :sugerido="$sugerido" />

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
                                    @else
                                        {{ $c->id_solicitud ? 'Confirmar la seña' : 'Cobrar la seña' }}
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
