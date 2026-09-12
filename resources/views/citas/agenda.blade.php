@extends('layout.app')

@section('titulo', 'Agenda')

@section('vivo', 'agenda')

@section('contenido')
    @php use App\Servicios\Navegacion; use App\Servicios\Permisos; @endphp

    <x-encabezado
        :sub="$verTodo ? 'Citas del día para todo el equipo.' : 'Tus citas del día.'"
        :accion="['ruta' => 'citas.form', 't' => 'Nueva cita', 'ic' => 'calendar-plus']" />

    {{-- Navegación por día: el salón trabaja mirando «hoy», y de ahí se mueve --}}
    <div class="sgp-panel mb-3">
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
                {{-- Con un rango puesto, la fecha grande de la derecha mentiría:
                     dice el día y en la tabla hay una semana. --}}
                @if ($rango === '')
                    {{ fecha_larga($dia) }}
                @else
                    {{ $pag['total'] }} cita(s) ·
                    {{ ['sem' => 'semana del ' . fecha($dia, 'd/m'),
                        'mes' => 'mes de ' . fecha($dia, 'm/Y'),
                        'prox' => 'desde el ' . fecha($dia, 'd/m/Y'),
                        'todas' => 'todo el historial'][$rango] ?? '' }}
                @endif
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

    {{-- **La fila dice lo suyo de una, y el detalle es UNO.**

         Había dos botones en la misma fila y ninguno decía lo que abría: uno
         rotulado «vienen 2 · alergias» que repetía lo que la fila ya mostraba
         arriba —cuántas van y con qué son alérgicas— y abría un modal, y otro
         «Detalle» que desplegaba una fila con la duración y poco más. Se
         reportó como ambiguo, y lo era: la misma información en tres lugares
         y el desplegable sin contenido propio.

         Ahora la duración va debajo de la hora, el profesional tiene su columna
         cuando se ve la agenda entera, y queda **un solo «Detalle»**, que abre
         **la ficha de la cita en una ventana** (pedido del usuario, 7.118.0:
         primero fue una fila desplegable y se pidió la ventana, con la
         información mejor ordenada) con lo que no cabe en la fila: qué se
         hace y cuánto sale, para quién es, quiénes vienen y sus alergias, lo
         que dejó dicho, y lo ya cobrado. --}}
    @php $sgpColumnas = $verTodo ? 6 : 5; @endphp
    <div class="sgp-panel">
        <div class="table-responsive sgp-tabla-movil">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Hora</th><th>Cliente</th>
                        <th>Servicios</th>
                        @if ($verTodo)<th>Profesional</th>@endif
                        <th>Estado</th><th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $c)
                        @php
                            $sgpAcomp = $acompanantes[$c->id_cita] ?? [];
                            // **Quiénes se atienden en esta cita, y con qué es
                            // alérgica CADA una.** La fila mostraba una sola
                            // alergia —la de la ficha de quien reservó— así que
                            // en una cita de tres, dos personas se sentaban sin
                            // que nadie supiera con qué no se las puede tocar. Y
                            // en una «para otra persona» la única que salía era
                            // la de alguien que ese día ni viene.
                            $sgpGente = \App\Servicios\Alergias::deLaCita($c, $sgpAcomp);
                            $sgpAlergicas = array_values(array_filter($sgpGente, fn ($p) => $p->alergias !== null));
                            $sgpVarias = count($sgpGente) > 1;
                            // La cuenta por persona, sólo en la cita de varias.
                            $sgpCuenta = $cuentas[$c->id_cita] ?? null;
                            $sgpCobrado = (float) ($c->cobrado_cita ?? 0);
                            $sgpTotal = (float) ($c->total_cita ?? 0);
                            $sgpFalta = max(0, $sgpTotal - $sgpCobrado);
                        @endphp
                        <tr>
                            <td class="sgp-movil-titulo" data-label="Hora" style="white-space:nowrap">
                                <strong>{{ fecha($c->fecha_hora, 'H:i') }}</strong>
                                {{-- La duración va acá y no detrás de un botón: es lo
                                     que dice hasta cuándo ocupa el sillón. --}}
                                <div class="text-muted-warm" style="font-size:.76rem">{{ (int) $c->duracion_min }} min</div>
                            </td>
                            {{-- `sgp-movil-sujeto`: en el celular este renglón va sin el
                                 rótulo «CLIENTE» y en negrita, que es lo que deja leer la
                                 tarjeta de un vistazo — hora, quién, y abajo el resto con
                                 su rótulo. En escritorio no cambia nada. --}}
                            <td data-label="Cliente" class="sgp-movil-sujeto">
                                {{-- **Arriba va quien SE ATIENDE; abajo y en chico, quien
                                     la pidió.** Con el badge «para Josefina» al lado del
                                     nombre de la clienta, el renglón tenía dos nombres del
                                     mismo tamaño y había que leer la etiqueta para saber
                                     cuál era cuál — y el que importa ese día es a quién
                                     hay que sentar en el sillón. --}}
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

                                {{-- **La alergia se ve en la fila, no escondida en el detalle.**
                                     Es lo único de la ficha que puede lastimar a alguien, así
                                     que va en rojo y con su texto puesto: lo que ADVIERTE no se
                                     esconde. Y una por persona, con su nombre cuando hay
                                     varias: «maní» a secas en una cita de tres no dice a quién
                                     no se le puede dar. --}}
                                @foreach ($sgpAlergicas as $sgpA)
                                    <div class="mb-1">
                                        <span class="badge-estado e-no d-inline-flex align-items-center gap-1"
                                              title="Alergias de {{ $sgpA->quien }}: {{ $sgpA->alergias }}">
                                            <i class="bi bi-exclamation-triangle-fill"></i>
                                            <span>@if ($sgpVarias)<strong>{{ $sgpA->quien }}:</strong> @endif{{ \Illuminate\Support\Str::limit($sgpA->alergias, 40) }}</span>
                                        </span>
                                    </div>
                                @endforeach
                            </td>
                            <td class="text-muted-warm" data-label="Servicios">
                                {{-- **La fila es el resumen; de quién es cada servicio se
                                     lee en la ventana.** La 7.117.0 listaba acá persona por
                                     persona —«Andrea: Corte · Josefina: Corte, Manicura»— y
                                     se reportó que la fila «muestra información que se
                                     mostrará en la ventana emergente de detalle». Queda lo
                                     que dice `vw_agenda_citas`: «Corte de dama ×2, Manicura»,
                                     que es lo que hace falta para preparar el puesto. --}}
                                @if ($verTodo || ! $c->mis_servicios)
                                    {{ $c->servicios ?: '—' }}
                                @else
                                    {{-- **Lo que le pidieron A ELLA, primero.** De los
                                         cuatro servicios de la cita puede tocarle uno:
                                         leer los cuatro sin saber cuál es suyo obliga a
                                         abrir el detalle para preparar el puesto. --}}
                                    <span class="txt-oro">{{ $c->mis_servicios }}</span>
                                    @if ($c->otros_profesionales)
                                        <div style="font-size:.76rem">
                                            + {{ $c->servicios }}
                                        </div>
                                    @endif
                                @endif
                            </td>
                            @if ($verTodo)
                                {{-- Quién atiende, en su columna: era lo único que el
                                     desplegable tenía de propio, y es lo que quien mira
                                     la agenda entera necesita ver sin abrir nada. --}}
                                <td data-label="Profesional" style="font-size:.88rem">
                                    {{ $c->profesionales ?: $c->profesional }}
                                </td>
                            @endif
                            <td data-label="Estado">
                                {!! estado_badge($c->estado) !!}
                                {{-- **Lo que ADVIERTE se queda en la fila; lo que informa
                                     va al desplegable.** Que la reserva **no está
                                     confirmada** y que hay una seña **esperando
                                     confirmación** hay que verlo sin abrir nada; lo ya
                                     cobrado sí puede esperar un toque. --}}
                                @if ((float) ($c->sena_pedida ?? 0) > 0)
                                    <span class="badge-estado e-warn" title="La clienta la registró desde el portal">
                                        seña {{ money($c->sena_pedida) }} a confirmar</span>
                                @elseif ((float) $c->sena <= 0 && (float) ($c->sena_requerida ?? 0) > 0
                                         && ! in_array($c->estado, ['Cancelada', 'Ausente', 'Atendida'], true))
                                    <span class="badge-estado e-no"
                                          title="Se le guarda el horario, pero se suelta si no confirma la seña">
                                        sin confirmar · falta seña {{ money($c->sena_requerida) }}</span>
                                @endif
                            </td>
                            <td class="text-end sgp-movil-acciones" style="white-space:nowrap">
                                {{-- **Los botones van en DOS columnas y con su nombre**, no
                                     en una fila de íconos. Siete íconos seguidos se leían como
                                     una adivinanza —se reportó como «demasiado confuso»—; en
                                     dos columnas, con el rótulo al lado del ícono, cada uno
                                     dice lo que hace. El rótulo sale del mismo `title`, así
                                     que no se puede desfasar del tooltip. --}}
                                @php
                                    $enCurso = $c->estado === 'En proceso';
                                    // **Atender y marcar en proceso son del DÍA de la
                                    // cita.** Cancelar y reprogramar sí se hacen antes.
                                    $esHoy = fecha($c->fecha_hora, 'Y-m-d') === fecha(ahora_bd(), 'Y-m-d');
                                    // **Ausente cierra la fila, igual que Cancelada.** La
                                    // clienta no vino: no hay nada que marcar en proceso, ni
                                    // que atender, ni que reprogramar. Lo único que sobrevive
                                    // es cobrar lo que haya quedado debiendo.
                                    $sgpAbierta = ! in_array($c->estado, ['Cancelada', 'Atendida', 'Ausente'], true);
                                    // Lo que hoy IMPIDE atender: sin la entrada marcada no se
                                    // registra la atención, y con la profesional ya dada por
                                    // ausente no va a fichar nunca — ahí lo que hay que hacer
                                    // es cambiarle el profesional a la cita.
                                    $sgpTraba = $sgpAbierta && $esHoy && ! $enCurso && ! ($c->fichaje_ok ?? true)
                                        ? (($c->prof_ausente ?? false) ? 'ausente' : 'fichaje')
                                        : null;
                                @endphp
                                <div class="sgp-acciones">
                                {{-- **Lo que TRABA la cita va primero, antes que «Detalle».**
                                     Iba después, así que el botón quedaba encima del aviso y
                                     se reportó al revés de como tiene que leerse: primero qué
                                     impide atender —que es lo accionable ahora— y recién
                                     después la ficha. Es la regla de siempre: lo que ADVIERTE
                                     no se esconde ni se pone segundo. --}}
                                @if ($sgpTraba === 'ausente')
                                    <span class="badge-estado e-no"
                                          title="Ya está marcado como ausente hoy: hay que asignarle la cita a otra persona">
                                        <i class="bi bi-person-x"></i> profesional ausente</span>
                                @elseif ($sgpTraba === 'fichaje')
                                    <span class="badge-estado e-warn" title="Primero hay que marcar la entrada en Asistencia">
                                        <i class="bi bi-person-check"></i> falta fichaje</span>
                                @endif
                                {{-- **Detalle abre la ficha de la cita en una ventana**, no
                                     una fila desplegable debajo (pedido del usuario): lo que
                                     no cabe en la fila —servicios con precio, quién viene y
                                     sus alergias, lo que dejó dicho, lo ya cobrado— se lee
                                     mejor ordenado en una ventana que apretado entre dos
                                     citas. Siempre hay ficha, así que el botón va siempre. --}}
                                <button class="btn btn-sm btn-outline-neutro sgp-btn-ico" type="button" title="Detalle"
                                        data-bs-toggle="modal" data-bs-target="#detCita{{ $c->id_cita }}">
                                    <i class="bi bi-card-text"></i></button>
                                @if ($sgpAbierta)
                                    @unless ($enCurso || ! $esHoy)
                                        @if ($c->fichaje_ok ?? true)
                                            <form method="post" action="{{ route('citas.estado') }}">
                                                @csrf
                                                <input type="hidden" name="id_cita" value="{{ $c->id_cita }}">
                                                <input type="hidden" name="dia" value="{{ $dia }}">
                                                <input type="hidden" name="id_estado_cita" value="5">
                                                <button class="btn btn-sm btn-outline-neutro sgp-btn-ico" title="En proceso">
                                                    <i class="bi bi-play-fill"></i></button>
                                            </form>
                                        @endif
                                    @endunless

                                    {{-- Sin fichaje tampoco se registra la atención: el
                                         servidor lo rechaza igual, y ofrecer el botón es
                                         prometer algo que no va a cumplir. --}}
                                    @if ($esHoy && ($c->fichaje_ok ?? true)
                                         && $urlAtender = Navegacion::url('citas.atender'))
                                        <a class="btn btn-sm btn-outline-neutro sgp-btn-ico" title="Registrar atención"
                                           href="{{ $urlAtender . '?id=' . $c->id_cita }}">
                                            <i class="bi bi-clipboard-check"></i></a>
                                    @endif

                                    @unless ($enCurso || ! $esHoy)
                                    <form method="post" action="{{ route('citas.estado') }}">
                                        @csrf
                                        <input type="hidden" name="id_cita" value="{{ $c->id_cita }}">
                                        <input type="hidden" name="dia" value="{{ $dia }}">
                                        <input type="hidden" name="id_estado_cita" value="6">
                                        <button class="btn btn-sm btn-outline-neutro sgp-btn-ico" title="Ausente"
                                                data-confirmar="¿Marcar como ausente a {{ $c->cliente }}?">
                                            <i class="bi bi-person-x"></i></button>
                                    </form>
                                    @endunless

                                    {{-- Cancelar una cita en curso no es cancelar: la
                                         clienta está en el sillón. --}}
                                    @unless ($enCurso)
                                    <form method="post" action="{{ route('citas.cancelar') }}">
                                        @csrf
                                        <input type="hidden" name="id_cita" value="{{ $c->id_cita }}">
                                        <input type="hidden" name="dia" value="{{ $dia }}">
                                        <button class="btn btn-sm btn-outline-neutro sgp-btn-ico" title="Cancelar"
                                                data-confirmar="¿Cancelar la cita de {{ $c->cliente }} de las {{ fecha($c->fecha_hora, 'H:i') }}?">
                                            <i class="bi bi-x-lg"></i></button>
                                    </form>
                                    @endunless

                                    @unless ($enCurso)
                                        <button class="btn btn-sm btn-outline-neutro sgp-btn-ico" type="button" title="Reprogramar"
                                                data-bs-toggle="modal" data-bs-target="#modalRepro{{ $c->id_cita }}">
                                            <i class="bi bi-calendar-event"></i></button>
                                    @endunless

                                    {{-- Las flechas dicen «pasa de uno a otro»: `person-gear`
                                         al lado de `person-x` eran dos monigotes iguales
                                         para dos acciones que no se parecen en nada. --}}
                                    @if ($puedeReasignar && ! $enCurso)
                                        <button class="btn btn-sm btn-outline-neutro sgp-btn-ico" type="button" title="Cambiar profesional"
                                                data-bs-toggle="modal" data-bs-target="#modalReasignar{{ $c->id_cita }}">
                                            <i class="bi bi-arrow-left-right"></i></button>
                                    @endif

                                    {{-- La seña mueve plata: solo para quien maneja cobros y con
                                         la caja abierta. **Y no va si ya se cobró ni con la cita
                                         en proceso**: la seña garantiza una reserva. --}}
                                    @if ($puedeCobrar && $caja && $c->estado !== 'Ausente'
                                         && ! $enCurso && $sgpCobrado <= 0)
                                        <button class="btn btn-sm btn-outline-neutro sgp-btn-ico" type="button" title="Cobrar seña"
                                                data-bs-toggle="modal" data-bs-target="#modalSena{{ $c->id_cita }}">
                                            <i class="bi bi-cash-coin"></i></button>
                                    @endif
                                @elseif ($c->estado === 'Atendida')
                                    {{-- Atender y cobrar son dos pasos, y entre uno y otro la
                                         plata se olvidaba: acá el estado del cobro se ve y se
                                         resuelve. Se llama «Atención» y no «Detalle», que es
                                         la ficha de la cita. --}}
                                    <a class="btn btn-sm btn-outline-neutro" title="Ver la atención registrada"
                                       href="{{ route('citas.atender', ['id' => $c->id_cita]) }}">
                                        <i class="bi bi-eye"></i> Atención</a>

                                    @if ($c->id_factura)
                                        {{-- Un comprobante de toda la cita. --}}
                                        @if ((float) $c->saldo > 0.01)
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
                                        @php
                                            // **Cuántas de las personas ya tienen SU comprobante.**
                                            // En la cita de varias cada una puede irse con el
                                            // suyo, así que «facturada» es cuando no queda
                                            // ninguna sin él.
                                            $sgpConServ = $sgpCuenta
                                                ? array_filter($sgpCuenta, fn ($x, $p) => $p > 0 && $x['servicios'], ARRAY_FILTER_USE_BOTH)
                                                : [];
                                            $sgpConFact = array_filter($sgpConServ, fn ($x) => $x['id_factura']);
                                            $sgpTodasFacturadas = $sgpConServ && count($sgpConFact) === count($sgpConServ);
                                        @endphp
                                        @if ($sgpTodasFacturadas)
                                            @if ((float) $c->saldo_ind > 0.01)
                                                <a class="btn btn-sm btn-oro"
                                                   title="{{ (int) $c->facturas_ind }} comprobantes · queda {{ money($c->saldo_ind) }} por cobrar"
                                                   href="{{ route('facturacion.facturas', ['q' => $c->cliente]) }}">
                                                    <i class="bi bi-cash-coin"></i> Deben {{ money($c->saldo_ind) }}</a>
                                            @else
                                                <a class="btn btn-sm btn-outline-neutro"
                                                   title="{{ (int) $c->facturas_ind }} comprobantes, uno por persona"
                                                   href="{{ route('facturacion.facturas', ['q' => $c->cliente]) }}">
                                                    <i class="bi bi-check2-circle"></i> Cobrada · {{ (int) $c->facturas_ind }} comp.</a>
                                            @endif
                                        @elseif ($puedeCobrar && $caja && $sgpFalta > 0.5)
                                            {{-- **Primero se cobra, después el comprobante.** Es el
                                                 orden del mostrador: la clienta paga y recién ahí
                                                 dice si quiere factura. --}}
                                            <button class="btn btn-sm btn-oro" type="button" title="Cobrar esta atención"
                                                    data-bs-toggle="modal" data-bs-target="#modalSena{{ $c->id_cita }}">
                                                <i class="bi bi-cash-coin"></i> Cobrar
                                                @if ($sgpCobrado > 0) <span style="font-size:.75rem">(falta {{ money($sgpFalta) }})</span>@endif</button>
                                        @elseif ($sgpFalta <= 0.5 && $sgpCobrado > 0)
                                            {{-- **Ya está cobrada: el botón que queda es EMITIR, no
                                                 «Cobrar».** Dos administradores sobre la misma
                                                 agenda: uno cobraba la atención y al otro le seguía
                                                 apareciendo «Cobrar» —una foto de un minuto antes—,
                                                 y ahí se le podía cobrar dos veces a la clienta. La
                                                 base lo rechaza igual, pero después del clic y con
                                                 un mensaje que no decía que ya estaba cobrada. Ahora
                                                 la fila dice lo que hay, y la agenda se recarga sola
                                                 cuando el otro cobra (`vivo`). --}}
                                            @if ($puedeFacturar)
                                                <a class="btn btn-sm btn-oro"
                                                   title="Ya se cobró {{ money($sgpCobrado) }}: falta emitir el comprobante"
                                                   href="{{ route('facturacion.emitir', ['cita' => $c->id_cita]) }}">
                                                    <i class="bi bi-receipt-cutoff"></i> Emitir
                                                    @if ($sgpConFact) <span style="font-size:.75rem">(faltan {{ count($sgpConServ) - count($sgpConFact) }})</span>@endif</a>
                                            @else
                                                <span class="badge-estado e-ok" title="Se cobró {{ money($sgpCobrado) }}; falta que alguien emita el comprobante">
                                                    cobrada · sin comprobante</span>
                                            @endif
                                        @elseif ($puedeFacturar)
                                            <a class="btn btn-sm btn-outline-neutro"
                                               title="Emitir el comprobante de esta atención"
                                               href="{{ route('facturacion.emitir', ['cita' => $c->id_cita]) }}">
                                                <i class="bi bi-receipt-cutoff"></i> Emitir</a>
                                        @else
                                            <span class="badge-estado e-warn" title="Todavía no se le emitió comprobante">
                                                sin cobrar</span>
                                        @endif
                                    @endif
                                @endif
                                </div>
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="{{ $sgpColumnas }}">
                                <div class="sgp-vacio">
                                    <i class="bi bi-calendar-week"></i>
                                    <div class="t">
                                        @if ($rango === '')
                                            No hay citas para el {{ fecha($dia, 'd/m/Y') }}.
                                        @else
                                            Ninguna cita coincide con lo que buscaste.
                                        @endif
                                    </div>
                                    <div class="d">
                                        @if ($rango === '')
                                            Agendá una con el botón «Nueva cita», o mirá otro tramo con el filtro «Ver».
                                        @else
                                            Probá con otro tramo o soltá algún filtro.
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{-- El día viaja con la paginación por lo mismo que con los filtros:
                 sin él, pasar de página te devolvía a hoy. --}}
            <x-paginacion :pag="$pag" :f="$f" :ocultos="['dia' => $dia]" />
        </div>
    </div>


    {{-- **La ficha de cada cita, en una ventana.**

         Es lo que abre «Detalle». Estaba como fila desplegable debajo de la
         cita y se pidió que vuelva a ser una ventana emergente —la que abría
         el botón «vienen 2 · alergias» de antes—, con la información mejor
         ordenada. Va en cuatro bloques: la cita en sí (cuándo, cuánto, quién
         atiende, qué se hace y cuánto sale), quiénes vienen y con qué son
         alérgicas, lo que la clienta dejó dicho, y lo ya cobrado. Lo que la
         cita no tiene, no se dibuja: un rótulo con un guión al lado es ruido.

         **Fuera de la tabla**, por lo de la 7.87.4: un modal dibujado dentro
         de un `<tr>` hereda cualquier `display:none` del ancestro y no se
         puede mostrar ni con Bootstrap haciendo su trabajo. --}}
    @foreach ($rows as $c)
        @php
            $sgpAcomp = $acompanantes[$c->id_cita] ?? [];
            $sgpGente = \App\Servicios\Alergias::deLaCita($c, $sgpAcomp);
            $sgpAlergicas = array_values(array_filter($sgpGente, fn ($p) => $p->alergias !== null));
            $sgpVarias = count($sgpGente) > 1;
            $sgpCuenta = $cuentas[$c->id_cita] ?? null;
            $sgpCobrado = (float) ($c->cobrado_cita ?? 0);
            $sgpTotal = (float) ($c->total_cita ?? 0);
            $sgpLista = (float) ($c->total_lista ?? $sgpTotal);
            $sgpFalta = max(0, $sgpTotal - $sgpCobrado);
            $sgpFilas = $desglosesSena[$c->id_cita]['filas'] ?? [];
        @endphp
        <div class="modal fade" id="detCita{{ $c->id_cita }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" style="font-size:1rem">
                                <i class="bi bi-card-text txt-oro"></i>
                                Cita de {{ $c->para_otra_persona ? ($c->nombre_para ?: 'otra persona') : $c->cliente }}
                            </h5>
                            <div class="text-muted-warm" style="font-size:.82rem">
                                {{ fecha_larga(fecha($c->fecha_hora, 'Y-m-d')) }} · {{ fecha($c->fecha_hora, 'H:i') }}
                                · {{ (int) $c->duracion_min }} min
                                {!! estado_badge($c->estado) !!}
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="sgp-cita-grid">
                            {{-- ---- La cita: quién atiende, qué se hace, cuánto sale ---- --}}
                            <section class="sgp-cita-bloque">
                                <h6><i class="bi bi-scissors"></i> La cita</h6>
                                <dl>
                                    <dt>Atiende</dt>
                                    <dd>{{ $c->profesionales ?: $c->profesional }}</dd>

                                    <dt>Servicios</dt>
                                    <dd>
                                        {{-- Servicio por servicio, con su precio. De quién es
                                             cada uno lo dice «Quién viene», al lado. --}}
                                        @if ($sgpFilas)
                                            @foreach ($sgpFilas as $fl)
                                                <div class="d-flex justify-content-between gap-2">
                                                    <span>{{ $fl->nombre }}
                                                        @if ((int) $fl->canjeado > 0)<span class="badge-estado e-ok">canjeado</span>@endif</span>
                                                    <span class="text-muted-warm" style="white-space:nowrap">{{ money($fl->precio) }}</span>
                                                </div>
                                            @endforeach
                                        @else
                                            {{ $c->servicios ?: '—' }}
                                        @endif
                                    </dd>

                                    @if ($sgpTotal > 0)
                                        <dt>Total</dt>
                                        <dd>
                                            <strong class="txt-oro" style="font-size:1.05rem">{{ money($sgpTotal) }}</strong>
                                            @if ($sgpLista > $sgpTotal + 0.5)
                                                <span class="text-muted-warm" style="text-decoration:line-through">{{ money($sgpLista) }}</span>
                                                <span class="txt-ok" style="font-size:.8rem">
                                                    con descuento
                                                    @if (! empty($desglosesSena[$c->id_cita]['promo']))
                                                        por la promoción «{{ $desglosesSena[$c->id_cita]['promo'] }}»
                                                    @elseif (! empty($desglosesSena[$c->id_cita]['nivel']))
                                                        por su nivel {{ $desglosesSena[$c->id_cita]['nivel'] }}
                                                    @endif
                                                </span>
                                            @endif
                                        </dd>
                                    @endif
                                </dl>
                            </section>

                            {{-- ---- Quiénes vienen: UNA persona por renglón, con todo lo suyo ----
                                 Era tres listas —«Clienta», «Vienen», «Alergias»— y cada una
                                 volvía a nombrar a la misma gente: para saber qué se hace
                                 Josefina y a qué es alérgica había que leerla en tres lugares.
                                 Se reportó como «mal organizada» (7.119.0). Ahora cada
                                 persona es un renglón: quién es y qué papel tiene, sus
                                 servicios, su alergia —«sin registrar» ES una respuesta: nadie
                                 lo preguntó— y su ficha, si hay que abrirla o crearla. --}}
                            <section class="sgp-cita-bloque">
                                <h6><i class="bi bi-people"></i> Quién viene
                                    @if ((int) $c->personas > 1)
                                        <span class="badge-estado e-muted">{{ (int) $c->personas }} personas</span>
                                    @endif
                                </h6>
                                @php
                                    // Cada persona del grupo, por su lugar (1 = quien se
                                    // atiende en primer lugar; 2..N los acompañantes).
                                    $sgpFicha = [];   // [persona => ['url' => …, 'crear' => bool]]
                                    if ($c->para_otra_persona && $c->nombre_para) {
                                        if ($c->id_cliente_para) {
                                            $sgpFicha[1] = ['url' => route('clientes.historial', $c->id_cliente_para), 'crear' => false];
                                        } elseif (Permisos::puede('clientes.registro')) {
                                            $partes = preg_split('/\s+/', trim((string) $c->nombre_para), 2);
                                            $sgpFicha[1] = ['url' => route('clientes.form', ['nombre' => $partes[0] ?? '', 'apellido' => $partes[1] ?? '']), 'crear' => true];
                                        }
                                    } elseif (! $c->para_otra_persona && ! empty($c->id_cliente)) {
                                        $sgpFicha[1] = ['url' => route('clientes.historial', $c->id_cliente), 'crear' => false];
                                    }
                                    foreach ($sgpAcomp as $ac) {
                                        if ($ac->id_cliente) {
                                            $sgpFicha[(int) $ac->orden] = ['url' => route('clientes.historial', $ac->id_cliente), 'crear' => false];
                                        } elseif (Permisos::puede('clientes.registro')) {
                                            $sgpFicha[(int) $ac->orden] = ['url' => route('clientes.form', ['nombre' => $ac->nombre, 'apellido' => $ac->apellido]), 'crear' => true];
                                        }
                                    }
                                @endphp
                                <ul class="sgp-cita-gente">
                                    @foreach ($sgpGente as $sgpI => $sgpP)
                                        @php $sgpN = $sgpI + 1; $sgpX = $sgpCuenta[$sgpN] ?? null; @endphp
                                        <li>
                                            <div class="sgp-cita-gente-cab">
                                                <strong>{{ $sgpP->quien }}</strong>
                                                <span class="text-muted-warm">
                                                    @if ($sgpN === 1 && $c->para_otra_persona)
                                                        · es para ella; la reservó {{ $c->cliente }}
                                                    @elseif ($sgpN === 1)
                                                        · la clienta
                                                    @else
                                                        · acompaña
                                                    @endif
                                                </span>
                                                @if (isset($sgpFicha[$sgpN]))
                                                    <a class="btn btn-sm {{ $sgpFicha[$sgpN]['crear'] ? 'btn-rapido' : 'btn-outline-neutro' }} py-0 ms-auto"
                                                       href="{{ $sgpFicha[$sgpN]['url'] }}">
                                                        <i class="bi bi-{{ $sgpFicha[$sgpN]['crear'] ? 'person-plus' : 'clock-history' }}"></i>
                                                        {{ $sgpFicha[$sgpN]['crear'] ? 'Crear su ficha' : 'Su historial' }}</a>
                                                @endif
                                            </div>
                                            @if ($sgpX && $sgpX['servicios'])
                                                <div class="sgp-cita-gente-det">
                                                    <i class="bi bi-scissors"></i> {{ implode(', ', $sgpX['servicios']) }}
                                                    <span class="text-muted-warm">· {{ money($sgpX['total']) }}</span>
                                                </div>
                                            @endif
                                            <div class="sgp-cita-gente-det">
                                                @if ($sgpP->alergias !== null)
                                                    <strong class="txt-no"><i class="bi bi-exclamation-triangle-fill"></i> Alergias: {{ $sgpP->alergias }}</strong>
                                                @else
                                                    <span class="text-muted-warm"><i class="bi bi-shield-check"></i> Alergias: sin registrar</span>
                                                @endif
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </section>

                            {{-- ---- Lo que dejó dicho ---- --}}
                            @if (trim((string) $c->observaciones) !== '')
                                <section class="sgp-cita-bloque sgp-cita-ancho">
                                    <h6><i class="bi bi-chat-left-quote"></i> Dejó dicho</h6>
                                    <p class="mb-0">{{ $c->observaciones }}</p>
                                </section>
                            @endif

                            {{-- ---- Lo ya cobrado: informa, no advierte ---- --}}
                            @if ((float) $c->sena > 0 || $sgpCobrado > 0 || $c->id_factura || (int) ($c->facturas_ind ?? 0) > 0)
                                <section class="sgp-cita-bloque sgp-cita-ancho">
                                    <h6><i class="bi bi-cash-coin"></i> Cobros</h6>
                                    <dl class="sgp-cita-cobros">
                                        @if ((float) $c->sena > 0)
                                            <dt>Seña</dt>
                                            <dd><span class="badge-estado e-ok">{{ money($c->sena) }}</span></dd>
                                        @endif
                                        @if ($sgpCobrado - (float) $c->sena > 0)
                                            <dt>Cobrado</dt>
                                            <dd><span class="badge-estado e-ok" title="Contra la cita, sin comprobante todavía">{{ money($sgpCobrado - (float) $c->sena) }}</span></dd>
                                        @endif
                                        @if ($c->id_factura)
                                            <dt>Comprobante</dt>
                                            <dd>
                                                <a class="link-oro" href="{{ route('facturacion.factura_ver', ['id' => $c->id_factura]) }}">{{ $c->nro_comprobante }}</a>
                                                @if ((float) $c->saldo > 0.01)
                                                    <span class="txt-no">· debe {{ money($c->saldo) }}</span>
                                                @else
                                                    <span class="txt-ok">· saldado</span>
                                                @endif
                                            </dd>
                                        @elseif ($sgpTotal > 0 && $c->estado === 'Atendida')
                                            <dt>Falta cobrar</dt>
                                            <dd>
                                                @if ($sgpFalta > 0.5)
                                                    <strong class="txt-oro">{{ money($sgpFalta) }}</strong>
                                                @else
                                                    <span class="txt-ok">nada: falta emitir el comprobante</span>
                                                @endif
                                            </dd>
                                        @endif
                                        {{-- De quién es cada cobro y cada comprobante,
                                             cuando pagan por separado. --}}
                                        @if ($sgpCuenta)
                                            @foreach ($sgpCuenta as $sgpP => $sgpX)
                                                @continue ($sgpP === 0 || ($sgpX['cobrado'] <= 0 && ! $sgpX['id_factura']))
                                                <dt>{{ $sgpX['nombre'] }}</dt>
                                                <dd>
                                                    @if ($sgpX['cobrado'] > 0) cobró {{ money($sgpX['cobrado']) }} @endif
                                                    @if ($sgpX['id_factura'])
                                                        · <a class="link-oro" href="{{ route('facturacion.factura_ver', ['id' => $sgpX['id_factura']]) }}">{{ $sgpX['nro'] }}</a>
                                                        @if ($sgpX['saldo'] > 0.01)<span class="txt-no">debe {{ money($sgpX['saldo']) }}</span>@endif
                                                    @endif
                                                </dd>
                                            @endforeach
                                        @endif
                                    </dl>
                                </section>
                            @endif
                        </div>
                    </div>
                    <div class="modal-footer">
                        @if ($c->estado === 'Atendida')
                            <a class="btn btn-outline-neutro" href="{{ route('citas.atender', ['id' => $c->id_cita]) }}">
                                <i class="bi bi-eye"></i> Ver la atención</a>
                        @endif
                        <button type="button" class="btn btn-oro" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

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
                            {{-- **El mismo selector que usa la clienta, y ése era el
                                 defecto.**

                                 Acá había un `datetime-local` suelto: ofrecía
                                 **domingos, días en que esa persona no trabaja y
                                 horas fuera de su turno**, y el «no» llegaba
                                 recién al guardar. Es la regla del proyecto —*las
                                 pantallas no dejan escribir una fecha a mano*—
                                 que el portal cumple desde la 7.96.0 y el panel
                                 se había quedado sin aplicar: media corrección,
                                 el patrón de siempre.

                                 Los servicios, el profesional y la sucursal van
                                 **fijos**: reprogramar no pregunta qué se hace ni
                                 con quién, eso ya está decidido — lo único que se
                                 elige es cuándo. --}}
                            <label class="form-label">Nueva fecha y hora</label><x-ayuda campo="fecha_hora" />
                            <input type="hidden" name="fecha_hora" required>
                            <div data-agenda="{{ route('citas.disponibilidad') }}"
                                 data-agenda-sujeto="La cita"
                                 data-agenda-servicios="{{ $c->servicios_ids ?? '' }}"
                                 data-agenda-profesional="{{ (int) $c->id_usuario }}"
                                 data-agenda-sucursal="{{ (int) $c->id_sucursal }}"
                                 {{-- **Para cuántas personas es la cita.** Sin esto el modal
                                      medía el peor caso —todo en serie sobre una sola clienta— y
                                      una reserva para dos no ofrecía ni un día: decía «no entra en
                                      el turno» de algo que el salón atiende igual. --}}
                                 data-agenda-personas="{{ max(1, (int) ($c->personas ?? 1)) }}"
                                 data-agenda-boton="#btnRepro{{ $c->id_cita }}">
                                <div data-agenda-aviso class="text-muted-warm" style="font-size:.85rem"></div>
                                <div data-agenda-dias class="sgp-dias mt-2"></div>
                                <div data-agenda-horas class="sgp-horas mt-2"></div>
                            </div>
                            <p class="text-muted-warm mt-2 mb-0" style="font-size:.78rem">
                                Sólo se ofrecen los horarios en que {{ $c->profesional }} de verdad
                                atiende y está libre. Al guardar se vuelve a comprobar.
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-oro" id="btnRepro{{ $c->id_cita }}" disabled>Reprogramar</button>
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
            @php
                $sgpCuenta = $cuentas[$c->id_cita] ?? null;
                $sgpCobrado = (float) ($c->cobrado_cita ?? $c->sena);
                $sgpTotal = (float) ($c->total_cita ?? 0);
                $sgpFalta = max(0, $sgpTotal - $sgpCobrado);
            @endphp
            {{-- Atendida SÍ entra —es el cobro de la atención, el caso normal—
                 salvo que ya tenga comprobante: ahí el cobro va contra él. **Y
                 salvo que ya esté cobrada entera**: la fila ya no ofrece
                 «Cobrar», y un modal que nadie puede abrir es marcado de más. --}}
            @continue (in_array($c->estado, ['Cancelada', 'Ausente'], true)
                       || ($c->estado === 'Atendida' && ($c->id_factura || $sgpFalta <= 0.5)))
            {{-- **Ancha en la computadora, y en dos columnas** (pedido del usuario,
                 7.119.0): la cuenta a la izquierda —qué se cobra y por qué— y el
                 pago a la derecha —cómo pagan, con qué, a qué caja—. En 500 px
                 todo iba en una sola columna y había que scrollear el modal para
                 llegar al botón; en el celular sigue siendo una columna. --}}
            <div class="modal fade" id="modalSena{{ $c->id_cita }}" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
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
                                $totalCita = $sgpTotal;
                                // **Contra todo lo cobrado, no sólo contra la seña.**
                                // Con la atención cobrada en parte, restando sólo la seña
                                // el saldo salía de más y el modal proponía cobrar dos
                                // veces lo mismo.
                                $cobrado = $sgpCobrado;
                                $falta = $sgpFalta;

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

                                // **Grupal o por persona.** En la cita de varias, cada
                                // una puede pagar lo suyo y llevarse su comprobante, o
                                // pagar todo junto. Se pregunta sólo ahí, y no al
                                // confirmar una seña del portal, que ya vino como grupo.
                                $sgpPorPersona = $sgpCuenta && ! $c->id_solicitud;
                                $sgpAlguienConFactura = $sgpPorPersona
                                    && (bool) array_filter($sgpCuenta, fn ($x, $p) => $p > 0 && $x['id_factura'], ARRAY_FILTER_USE_BOTH);
                            @endphp
                            <div class="modal-body">
                              <div class="sgp-cobro-2col">
                              <div class="sgp-cobro-cuenta">
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
                                @php
                                    // **Abajo va el desglose de la seña, y dice lo mismo.**
                                    //
                                    // `_sena_desglose` lista servicio por servicio con su
                                    // precio y cierra en «Total de la cita», así que cuando
                                    // se dibuja, estas dos primeras partes de acá son la
                                    // misma información dos veces — el modal repetía los
                                    // servicios, el precio de lista y el total, uno debajo
                                    // del otro.
                                    //
                                    // Lo que **no** está abajo se queda: el descuento con su
                                    // origen, lo ya cobrado y lo que falta cobrar. Borrar la
                                    // tabla entera se llevaría eso puesto.
                                    $sgpHayDesglose = $c->estado !== 'Atendida'
                                        && ! empty($desglosesSena[$c->id_cita]['filas']);
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
                                            @unless ($sgpHayDesglose || $sgpPorPersona)
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
                                            @endunless
                                            @if ($sgpPorPersona)
                                                {{-- **Lo de cada una.** Con tres amigas en la cita,
                                                     «total Gs. 480.000» no dice cuánto le toca a
                                                     cada una: acá va persona por persona, con sus
                                                     servicios, lo que ya pagó y lo que le falta.
                                                     La parte de cada una sale del precio de lista
                                                     de lo suyo, con el descuento de la cita
                                                     repartido en proporción. --}}
                                                @foreach ($sgpCuenta as $sgpP => $sgpX)
                                                    @continue ($sgpP === 0 || ! $sgpX['servicios'])
                                                    <tr>
                                                        <td>
                                                            <strong>{{ $sgpX['nombre'] }}</strong>
                                                            <div class="text-muted-warm" style="font-size:.78rem">
                                                                {{ implode(', ', $sgpX['servicios']) }}
                                                                @if ($sgpX['id_factura'])
                                                                    · <span class="txt-ok">ya tiene {{ $sgpX['nro'] }}</span>
                                                                @elseif ($sgpX['cobrado'] > 0)
                                                                    · pagó {{ money($sgpX['cobrado']) }}
                                                                @endif
                                                            </div>
                                                        </td>
                                                        <td class="text-end">{{ money($sgpX['total']) }}</td>
                                                    </tr>
                                                @endforeach
                                                @if (! empty($sgpCuenta[0]) && $sgpCuenta[0]['cobrado'] > 0)
                                                    <tr>
                                                        <td class="text-muted-warm">Pagado por el grupo, sin decir de quién</td>
                                                        <td class="text-end text-muted-warm">− {{ money($sgpCuenta[0]['cobrado']) }}</td>
                                                    </tr>
                                                @endif
                                            @endif
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
                                                            @if ($sgpPorPersona) · ya repartido arriba @endif
                                                        </span>
                                                    </td>
                                                    <td class="text-end">− {{ money($lista - $totalCita) }}</td>
                                                </tr>
                                            @endif
                                            @unless ($sgpHayDesglose)
                                                <tr style="border-top:2px solid var(--gris-calido)">
                                                    <th>Total de la cita</th>
                                                    <th class="text-end">{{ money($totalCita) }}</th>
                                                </tr>
                                            @endunless
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
                                                    <th class="text-end txt-oro" data-cobro-falta>{{ money($falta) }}</th>
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

                              </div>
                              <div class="sgp-cobro-pago">
                                @if ($sgpPorPersona && $totalCita > 0)
                                    {{-- **¿Paga todo el grupo, o cada una lo suyo?** Dos amigas
                                         que se atienden juntas no siempre pagan juntas, y si
                                         pagan aparte cada una quiere SU comprobante. Con el
                                         cobro «de la cita» a secas no se podía: el sistema
                                         sumaba todo y hacía una factura sola.

                                         «Por persona» acota el cobro a lo que le falta a ésa
                                         (`cobro.persona`), y la factura de esa persona sale
                                         después con sólo sus servicios. «Todo junto» es lo de
                                         siempre. Cuando alguna ya tiene su comprobante, lo
                                         junto deja de tener sentido y se arranca por persona. --}}
                                    <div class="mb-2 sgp-cobro-modo" data-cobro-modo data-moneda="{{ config('sgp.moneda') }}">
                                        <div class="form-label mb-1">¿Cómo pagan?</div>
                                        <div class="d-flex gap-3 flex-wrap" style="font-size:.88rem">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="modo_pago"
                                                       id="modoJunto{{ $c->id_cita }}" value="junto"
                                                       data-falta="{{ (float) $falta }}"
                                                       @checked(! $sgpAlguienConFactura)
                                                       @disabled($sgpAlguienConFactura)>
                                                <label class="form-check-label" for="modoJunto{{ $c->id_cita }}">
                                                    Todo el grupo junto
                                                    @if ($sgpAlguienConFactura)
                                                        <span class="text-muted-warm">(alguna ya tiene su comprobante)</span>
                                                    @endif
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="modo_pago"
                                                       id="modoPersona{{ $c->id_cita }}" value="persona"
                                                       @checked($sgpAlguienConFactura)>
                                                <label class="form-check-label" for="modoPersona{{ $c->id_cita }}">
                                                    Cada una lo suyo
                                                </label>
                                            </div>
                                        </div>
                                        <select class="form-select form-select-sm mt-2" name="persona"
                                                id="personaCobro{{ $c->id_cita }}" data-persona-select
                                                aria-label="¿De quién es este pago?"
                                                @disabled(! $sgpAlguienConFactura)>
                                            @foreach ($sgpCuenta as $sgpP => $sgpX)
                                                @continue ($sgpP === 0 || ! $sgpX['servicios'])
                                                @php $sgpTapada = $sgpX['id_factura'] || $sgpX['falta'] <= 0.5; @endphp
                                                <option value="{{ $sgpP }}" data-falta="{{ (float) $sgpX['falta'] }}"
                                                        @disabled($sgpTapada)>
                                                    {{ $sgpX['nombre'] }} ·
                                                    @if ($sgpX['id_factura']) ya tiene su comprobante
                                                    @elseif ($sgpX['falta'] <= 0.5) ya pagó lo suyo
                                                    @else le faltan {{ money($sgpX['falta']) }} @endif
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="form-text">
                                            Por persona, el comprobante de cada una sale después con sólo lo suyo.
                                        </div>
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
                                        y después elegís el comprobante: factura declarada o sin
                                        nombre, lo que pida la clienta. Sale saldado solo.
                                    @else
                                        y se descuenta sola del total cuando se facture la cita.
                                    @endif
                                </p>
                              </div>
                              </div>
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

@push('scripts')
<script>
/* **Cobrar desde Cobros.** Esa pantalla lista lo que falta cobrar y su botón
   trae acá con `?cobrar=<cita>`: la ventana de cobro de esa cita se abre
   sola, que es lo que hace que «cobrar» sea un clic y no encontrar la fila. */
(function () {
    var id = new URLSearchParams(location.search).get('cobrar');
    var m = id && document.getElementById('modalSena' + id);
    if (m && window.bootstrap) { bootstrap.Modal.getOrCreateInstance(m).show(); }
})();

/* **Grupal o por persona: el tope del cobro sigue a la elección.** El bloque
   de líneas (`.sgp-cobro`) nació con el saldo de la cita entera; al pasar a
   «cada una lo suyo» lo que se puede cobrar es lo que le falta a ESA persona,
   y el resumen de abajo tiene que decirlo con ese número. Se le avisa con un
   evento —`sgp:cobro-saldo`— que `app.js` escucha, en vez de tocar sus
   variables desde acá. Sin JavaScript el radio queda en «junto» y el
   selector deshabilitado, o sea el cobro grupal de siempre. */
(function () {
  'use strict';
  document.querySelectorAll('[data-cobro-modo]').forEach(function (bloque) {
    var form = bloque.closest('form');
    var caja = form && form.querySelector('.sgp-cobro');
    var sel = bloque.querySelector('[data-persona-select]');
    var junto = bloque.querySelector('input[value="junto"]');
    var persona = bloque.querySelector('input[value="persona"]');
    var celda = form && form.querySelector('[data-cobro-falta]');
    if (!sel || !junto || !persona) return;

    function miles(n) { return n.toLocaleString('es-PY', { maximumFractionDigits: 0 }); }

    function aplicar() {
      var porPersona = persona.checked;
      sel.disabled = !porPersona;
      var saldo;
      if (porPersona) {
        var op = sel.options[sel.selectedIndex];
        if (op && op.disabled) {
          // La primera que todavía deba algo: una tapada no se puede elegir.
          for (var i = 0; i < sel.options.length; i++) {
            if (!sel.options[i].disabled) { sel.selectedIndex = i; break; }
          }
          op = sel.options[sel.selectedIndex];
        }
        saldo = op ? parseFloat(op.getAttribute('data-falta') || '0') : 0;
      } else {
        saldo = parseFloat(junto.getAttribute('data-falta') || '0');
      }
      if (celda) celda.textContent = (bloque.getAttribute('data-moneda') || 'Gs.') + ' ' + miles(saldo);
      if (caja) {
        caja.dispatchEvent(new CustomEvent('sgp:cobro-saldo', { detail: { saldo: saldo, sugerido: saldo } }));
      }
    }
    junto.addEventListener('change', aplicar);
    persona.addEventListener('change', aplicar);
    sel.addEventListener('change', aplicar);
    if (persona.checked) aplicar();
  });
})();
</script>
@endpush
