@extends('layout.app')

@section('titulo', 'Agenda')

@section('contenido')
    @php use App\Servicios\Navegacion; @endphp

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
                            <td>{{ $c->cliente }}</td>
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
                                @endif
                            </td>
                            <td class="text-end" style="white-space:nowrap">
                                @if (! in_array($c->estado, ['Cancelada', 'Atendida'], true))
                                    {{-- En proceso: la clienta ya está en el sillón --}}
                                    <form method="post" action="{{ route('citas.estado') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="id_cita" value="{{ $c->id_cita }}">
                                        <input type="hidden" name="dia" value="{{ $dia }}">
                                        <input type="hidden" name="id_estado_cita" value="5">
                                        <button class="btn btn-sm btn-outline-neutro" title="Marcar en proceso">
                                            <i class="bi bi-play-fill"></i></button>
                                    </form>

                                    @if ($urlAtender = Navegacion::url('citas.atender'))
                                        <a class="btn btn-sm btn-outline-neutro" title="Registrar atención"
                                           href="{{ $urlAtender . '?id=' . $c->id_cita }}">
                                            <i class="bi bi-clipboard-check"></i></a>
                                    @endif

                                    <form method="post" action="{{ route('citas.estado') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="id_cita" value="{{ $c->id_cita }}">
                                        <input type="hidden" name="dia" value="{{ $dia }}">
                                        <input type="hidden" name="id_estado_cita" value="6">
                                        <button class="btn btn-sm btn-outline-neutro" title="Marcar ausente"
                                                data-confirmar="¿Marcar como ausente a {{ $c->cliente }}?">
                                            <i class="bi bi-person-x"></i></button>
                                    </form>

                                    <form method="post" action="{{ route('citas.cancelar') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="id_cita" value="{{ $c->id_cita }}">
                                        <input type="hidden" name="dia" value="{{ $dia }}">
                                        <button class="btn btn-sm btn-outline-neutro" title="Cancelar"
                                                data-confirmar="¿Cancelar la cita de {{ $c->cliente }} de las {{ fecha($c->fecha_hora, 'H:i') }}?">
                                            <i class="bi bi-x-lg"></i></button>
                                    </form>

                                    <button class="btn btn-sm btn-outline-neutro" title="Reprogramar"
                                            data-bs-toggle="modal" data-bs-target="#modalRepro{{ $c->id_cita }}">
                                        <i class="bi bi-calendar-event"></i></button>

                                    {{-- La seña mueve plata: solo para quien maneja cobros y con
                                         la caja abierta. Con la caja cerrada el aviso de arriba
                                         explica por qué no está el botón. --}}
                                    @if ($puedeCobrar && $caja && $c->estado !== 'Ausente')
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

                                {{-- Lo que hay que cobrar, arriba del campo y no en un
                                     rechazo posterior. Un modal que pide un monto sin
                                     decir cuál es el monto obliga a saberlo de memoria. --}}
                                @if ($totalCita > 0)
                                    <div class="spg-cobro-cuenta mb-2">
                                        <span>La cita vale <strong>{{ money($totalCita) }}</strong></span>
                                        @if ((float) $c->sena > 0)
                                            <span>· ya cobrado <strong>{{ money($c->sena) }}</strong></span>
                                        @endif
                                        <strong class="spg-cobro-falta">A cobrar {{ money($falta) }}</strong>
                                    </div>
                                @else
                                    {{-- Sin servicios cargados no hay monto que cobrar, y la
                                         base lo rechaza igual: mejor decirlo antes. --}}
                                    <div class="alert alert-warning py-2 mb-2" style="font-size:.82rem">
                                        Esta cita no tiene servicios cargados, así que no hay monto que cobrar.
                                    </div>
                                @endif

                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label" for="sm{{ $c->id_cita }}">Monto</label>
                                        {{-- Viene con lo que falta: en el mostrador se cobra
                                             el total casi siempre, y si no, se corrige. --}}
                                        <input class="form-control input-miles" id="sm{{ $c->id_cita }}"
                                               name="monto" data-min="1" data-max="{{ (int) $falta }}"
                                               inputmode="numeric" required
                                               value="{{ $c->id_solicitud
                                                          ? monto_input($c->sena_pedida)
                                                          : ($falta > 0 ? monto_input($falta) : '') }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label" for="sp{{ $c->id_cita }}">Medio de pago</label>
                                        <select class="form-select" id="sp{{ $c->id_cita }}" name="id_metodo_pago" required>
                                            @foreach ($metodos as $m)
                                                <option value="{{ $m->id_metodo_pago }}">{{ $m->nombre }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="sr{{ $c->id_cita }}">Referencia</label>
                                        <input class="form-control" id="sr{{ $c->id_cita }}" name="referencia"
                                               placeholder="Nº de operación, boleta… (opcional)">
                                    </div>
                                </div>

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
