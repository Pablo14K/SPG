@extends('layout.app')

@section('titulo', 'Mis citas')

@section('contenido')
    <div class="spg-page-head spg-head-flex">
        <div class="spg-head-txt">
            <h1>Mis citas</h1>
            <div class="sub">Las que vienen y las que ya pasaron.</div>
        </div>
        <div class="spg-head-acciones">
            <a class="btn btn-oro" href="{{ route('portal.reservar') }}">
                <i class="bi bi-calendar-plus"></i> Reservar</a>
        </div>
    </div>

    <div class="spg-panel mb-3">
        <h2 class="spg-form-titulo mb-2"><i class="bi bi-calendar-event"></i> Próximas</h2>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Fecha</th><th>Servicios</th><th>Profesional</th><th>Estado</th><th class="text-end"></th></tr></thead>
                <tbody>
                    @forelse ($prox as $c)
                        <tr>
                            <td><strong>{{ fecha($c->fecha_hora) }}</strong></td>
                            <td class="text-muted-warm">{{ $c->servicios ?: '—' }}</td>
                            <td>{{ $c->profesional }}</td>
                            <td>
                                {!! estado_badge($c->estado) !!}
                                @if ($c->en_curso)<span class="badge-estado e-proc">en curso</span>@endif
                                @if ((float) $c->sena > 0)
                                    <span class="badge-estado e-ok" title="Ya recibida en el salón">
                                        seña {{ money($c->sena) }}</span>
                                @elseif ((float) $c->sena_pedida > 0)
                                    <span class="badge-estado e-warn" title="Falta confirmarla en el salón">
                                        seña {{ money($c->sena_pedida) }} a confirmar</span>
                                @endif
                            </td>
                            <td class="text-end" style="white-space:nowrap">
                                {{-- **Se puede seguir hasta que el pago esté cerrado.**
                                     Antes el botón sólo estaba con la cita «En proceso», así
                                     que la clienta veía el detalle mientras la atendían y lo
                                     perdía justo cuando quería revisar el comprobante. --}}
                                @if (in_array($c->estado, ['En proceso', 'Atendida'], true))
                                    <a class="btn btn-sm btn-oro" href="{{ route('portal.atencion', ['id' => $c->id_cita]) }}">
                                        <i class="bi bi-eye"></i> Ver</a>
                                @elseif (! in_array($c->estado, ['Atendida', 'Cancelada'], true))
                                    {{-- Los dos calendarios, como en la pantalla del correo. Acá
                                         estaba SÓLO el de Google, así que quien no usa Google no
                                         tenía forma de agendar la cita en su teléfono. El .ics es
                                         el genérico: lo abre el calendario que traiga el celular.
                                         Van con texto y no sólo con ícono porque esta pantalla se
                                         mira desde el celular, donde el `title` no se ve. --}}
                                    <a class="btn btn-sm btn-rapido" download
                                       href="{{ route('cita.calendario', ['id' => $c->id_cita]) }}">
                                        <i class="bi bi-phone"></i> Calendario</a>
                                    <a class="btn btn-sm btn-outline-neutro" target="_blank" rel="noopener"
                                       title="Agendar en Google Calendar"
                                       href="{{ \App\Servicios\Calendario::urlGoogle($c, $lugar) }}">
                                        <i class="bi bi-google"></i></a>
                                    {{-- Registrar la seña NO es pagarla: no hay pasarela de pago
                                         y no la va a haber. Es un aviso, y el salón lo confirma
                                         cuando recibe el dinero. Por eso el botón dice
                                         «Registrar» y el modal lo aclara. --}}
                                    @php
                                        // **Lo canjeado ya está pagado con puntos.** Si el canje
                                        // cubre toda la cita no queda nada que adelantar, y
                                        // ofrecerle una seña es pedirle plata dos veces. Si además
                                        // pidió algo sin canje, eso sí se puede señar — por eso es
                                        // una resta y no un «tiene canje: no muestres nada».
                                        $porPagar = (float) ($c->total_cita ?? 0) - (float) ($c->canjeado ?? 0);
                                    @endphp
                                    @if ((float) $c->sena <= 0 && (float) $c->sena_pedida <= 0 && $porPagar > 0)
                                        <button type="button" class="btn btn-sm btn-outline-neutro"
                                                data-bs-toggle="modal" data-bs-target="#modalSena{{ $c->id_cita }}">
                                            <i class="bi bi-cash-coin"></i> Seña</button>
                                    @endif

                                    <form method="post" action="{{ route('portal.cancelar') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="id_cita" value="{{ $c->id_cita }}">
                                        <button class="btn btn-sm btn-outline-neutro"
                                                data-confirmar="¿Cancelar tu cita del {{ fecha($c->fecha_hora) }}?">
                                            Cancelar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="spg-vacio">
                                    <i class="bi bi-calendar-week"></i>
                                    <div class="t">No tenés citas próximas.</div>
                                    <div class="d">Reservá una con el botón de arriba.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($pasadas)
        <div class="spg-panel">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-clock-history"></i> Anteriores</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Fecha</th><th>Servicios</th><th>Profesional</th><th>Estado</th></tr></thead>
                    <tbody>
                        @foreach ($pasadas as $c)
                            <tr>
                                <td>{{ fecha($c->fecha_hora) }}</td>
                                <td class="text-muted-warm">{{ $c->servicios ?: '—' }}</td>
                                <td>{{ $c->profesional }}</td>
                                <td>{!! estado_badge($c->estado) !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
    {{-- Un modal por cita próxima sin seña. --}}
    @foreach ($prox as $c)
        @continue (in_array($c->estado, ['Atendida', 'Cancelada', 'En proceso'], true)
                   || (float) $c->sena > 0 || (float) $c->sena_pedida > 0)
        <div class="modal fade" id="modalSena{{ $c->id_cita }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="{{ route('portal.sena') }}">
                        @csrf
                        <input type="hidden" name="id_cita" value="{{ $c->id_cita }}">
                        <div class="modal-header">
                            <h5 class="modal-title" style="font-size:1rem">
                                <i class="bi bi-cash-coin"></i> Dejar una seña</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted-warm" style="font-size:.85rem">
                                Para tu cita del <strong>{{ fecha($c->fecha_hora) }}</strong>.
                            </p>

                            <label class="form-label" for="ps{{ $c->id_cita }}">¿Cuánto vas a dejar?</label>
                            <input class="form-control input-miles" id="ps{{ $c->id_cita }}"
                                   name="monto" data-min="1" inputmode="numeric" required>

                            {{-- Que quede clarísimo que acá no se paga: la clienta
                                 no tiene que quedarse esperando un cobro que no
                                 va a llegar, ni creer que ya está saldado. --}}
                            <div class="alert alert-warning mt-3 mb-0" style="font-size:.82rem">
                                <strong>Acá no se paga.</strong> Lo anotamos para que el salón lo
                                sepa, y queda confirmado cuando entregues el dinero en el local.
                                Se descuenta solo del total cuando te facturen.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-oro">Registrar la seña</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection
