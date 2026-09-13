@extends('layout.app')

@section('titulo', 'Cobros')

@section('contenido')
    <x-encabezado :sub="'Total ' . ($f['activos'] ? 'de lo filtrado' : 'general')
                        . ': <strong class=\'txt-oro\'>' . money($totalFiltrado) . '</strong> (sin contar los anulados)'" />

    {{-- **Lo que falta cobrar, arriba del historial** (pedido del usuario,
         7.119.0). La pantalla era sólo el historial, así que la atención que
         la clienta debía no aparecía en ningún lado. El botón abre el cobro
         donde vive su ventana —la agenda, con la ventana ya abierta— o, si la
         cita ya tiene comprobante, Facturas, que es contra lo que se cobra. --}}
    @if (! empty($porCobrar))
        <div class="sgp-panel mb-3" style="border-left:3px solid var(--oro)">
            <h2 class="sgp-form-titulo mb-2">
                <i class="bi bi-cash-coin"></i>
                Falta cobrar {{ count($porCobrar) }} {{ count($porCobrar) === 1 ? 'atención' : 'atenciones' }}
            </h2>
            <div class="table-responsive sgp-tabla-movil">
                <table class="table table-sm align-middle mb-0" style="font-size:.86rem">
                    <thead>
                        <tr><th>Cuándo</th><th>Clienta</th><th class="text-end">Total</th>
                            <th class="text-end">Cobrado</th><th class="text-end">Falta</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($porCobrar as $pc)
                            <tr id="porCobrar{{ (int) $pc->id_cita }}">
                                <td class="text-muted-warm sgp-movil-titulo" style="white-space:nowrap" data-label="Cuándo">
                                    {{ fecha($pc->fecha_hora, 'd/m H:i') }}</td>
                                <td data-label="Clienta">
                                    {{ $pc->cliente }}
                                    @if ((int) $pc->personas > 1)
                                        <span class="badge-estado e-muted">{{ (int) $pc->personas }} personas</span>
                                    @endif
                                </td>
                                {{-- El total es lo que YA SE COBRA O SE VA A COBRAR: lo
                                     facturado vale lo que dice el comprobante y lo que
                                     falta facturar, lo que dice la cita. Así Total −
                                     Cobrado da la misma «Falta» que la columna. --}}
                                <td class="text-end" data-label="Total">{{ money((float) $pc->cobrado + (float) $pc->falta) }}</td>
                                <td class="text-end text-muted-warm" data-label="Cobrado">{{ (float) $pc->cobrado > 0 ? money($pc->cobrado) : '—' }}</td>
                                <td class="text-end" data-label="Falta"><strong class="txt-no">{{ money($pc->falta) }}</strong></td>
                                <td class="text-end sgp-movil-acciones">
                                    {{-- A donde está la deuda: si la deben sus comprobantes, a
                                         Facturas; si es la parte sin comprobante, a la
                                         ventana de cobro de la agenda. --}}
                                    @if ((float) $pc->saldo_fact > 0.5)
                                        <a class="btn btn-sm btn-oro" title="Ya tiene comprobante: se cobra contra él, en Facturas"
                                           href="{{ route('facturacion.facturas', ['q' => $pc->cliente, 'saldo' => 'pend']) }}">
                                            <i class="bi bi-cash-coin"></i> Cobrar</a>
                                    @else
                                        <a class="btn btn-sm btn-oro" title="Abre la ventana de cobro de esta cita"
                                           href="{{ route('citas.agenda', ['dia' => fecha($pc->fecha_hora, 'Y-m-d'), 'cobrar' => $pc->id_cita]) }}">
                                            <i class="bi bi-cash-coin"></i> Cobrar</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="sgp-panel">
        <x-filtros :f="$f" />

        <div class="table-responsive sgp-tabla-movil">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Fecha</th><th>Cliente</th>
                        <th class="text-end">Monto</th><th>Estado</th><th class="text-end">Anular</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        {{-- Main row: only essential columns --}}
                        <tr>
                            <td class="sgp-movil-titulo" data-label="Fecha">{{ fecha($r->fecha) }}</td>
                            <td data-label="Cliente">
                                {{ $r->cliente ?: '—' }}
                            </td>
                            <td class="text-end" data-label="Monto">{{ money($r->monto) }}</td>
                            <td data-label="Estado">{!! estado_badge($r->estado) !!}</td>
                            <td class="text-end sgp-movil-acciones" style="white-space:nowrap">
                                <button class="sgp-btn-detalle" data-bs-toggle="collapse"
                                        data-bs-target="#detCob{{ $r->id_cobro }}" aria-expanded="false"
                                        aria-controls="detCob{{ $r->id_cobro }}">
                                    <i class="bi bi-chevron-down"></i> Detalle
                                </button>
                                @if ($r->estado !== 'Anulado')
                                    <button class="btn btn-sm btn-outline-neutro" title="Anular"
                                            data-bs-toggle="modal" data-bs-target="#modalAnular{{ $r->id_cobro }}">
                                        <i class="bi bi-x-circle"></i></button>
                                @endif
                            </td>
                        </tr>
                        {{-- Expandable detail row --}}
                        <tr class="sgp-fila-detalle">
                            <td colspan="5">
                                <div class="collapse" id="detCob{{ $r->id_cobro }}">
                                    <div class="sgp-det-cuerpo">
                                        <div class="sgp-det-grid">
                                            @if ($r->es_sena)
                                            <div>
                                                <dt>Tipo</dt>
                                                <dd><span class="badge-estado e-warn">seña</span></dd>
                                            </div>
                                            @endif
                                            <div>
                                                <dt>Comprobante</dt>
                                                <dd>
                                                    {{-- El número abre el comprobante. El Comprobante de
                                                         pago NO es una factura, así que buscarlo bajo
                                                         «Facturas» no se le ocurre a nadie: se lo busca
                                                         acá, en Cobros, y desde acá se llega. --}}
                                                    @if ($r->id_factura)
                                                        <a class="link-oro" href="{{ route('facturacion.factura_ver', ['id' => $r->id_factura]) }}"
                                                           title="Ver el comprobante">{{ $r->nro_comprobante }}</a>
                                                        <div class="text-muted-warm" style="font-size:.75rem">{{ $r->tipo_comprobante }}</div>
                                                    @else
                                                        <span class="text-muted-warm">—</span>
                                                    @endif
                                                </dd>
                                            </div>
                                            <div>
                                                <dt>Medio</dt>
                                                <dd>{{ $r->metodo }}</dd>
                                            </div>
                                            <div>
                                                <dt>Referencia</dt>
                                                <dd>{{ $r->referencia ?: '—' }}</dd>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="sgp-vacio">
                                    <i class="bi bi-cash-coin"></i>
                                    <div class="t">{{ $f['activos'] ? 'Ningún cobro coincide con esos filtros.' : 'Todavía no hay cobros registrados.' }}</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-paginacion :pag="$pag" :f="$f" />
    </div>

    @foreach ($rows as $r)
        @continue ($r->estado === 'Anulado')
        <div class="modal fade" id="modalAnular{{ $r->id_cobro }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="{{ route('facturacion.cobro.anular') }}">
                        @csrf
                        <input type="hidden" name="id_cobro" value="{{ $r->id_cobro }}">
                        <div class="modal-header">
                            <h5 class="modal-title" style="font-size:1rem">Anular el cobro de {{ money($r->monto) }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label" for="mot{{ $r->id_cobro }}">Motivo *</label>
                            <input class="form-control" id="mot{{ $r->id_cobro }}" name="motivo" required maxlength="200">
                            <p class="text-muted-warm mt-2 mb-0" style="font-size:.8rem">
                                El motivo queda en la auditoría. El cobro no se borra: cambia de estado y
                                el saldo de la factura se recalcula solo.
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-oro">Anular el cobro</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection
