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
                                    <span class="badge-estado e-warn" title="Ya dejó una seña">seña {{ money($c->sena) }}</span>
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
            @continue (in_array($c->estado, ['Cancelada', 'Atendida', 'Ausente'], true))
            <div class="modal fade" id="modalSena{{ $c->id_cita }}" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post" action="{{ route('facturacion.sena') }}">
                            @csrf
                            <input type="hidden" name="id_cita" value="{{ $c->id_cita }}">
                            <input type="hidden" name="dia" value="{{ $dia }}">
                            <div class="modal-header">
                                <h5 class="modal-title" style="font-size:1rem">
                                    <i class="bi bi-cash-coin"></i> Seña de {{ $c->cliente }}
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-muted-warm" style="font-size:.85rem">
                                    Cita del <strong>{{ fecha($c->fecha_hora) }}</strong>.
                                    @if ((float) $c->sena > 0)
                                        Ya dejó <strong>{{ money($c->sena) }}</strong>.
                                    @endif
                                </p>

                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label" for="sm{{ $c->id_cita }}">Monto</label>
                                        <input class="form-control input-miles" id="sm{{ $c->id_cita }}"
                                               name="monto" data-min="1" inputmode="numeric" required>
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

                                <p class="text-muted-warm mt-2 mb-0" style="font-size:.78rem">
                                    Entra en la caja de <strong>{{ $caja->responsable }}</strong> y se descuenta
                                    sola del total cuando se facture la cita.
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                                <button class="btn btn-oro">Cobrar la seña</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
@endsection
