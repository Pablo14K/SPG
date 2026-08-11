@extends('layout.app')

@section('titulo', 'Cobros')

@section('contenido')
    <x-encabezado :sub="'Total ' . ($f['activos'] ? 'de lo filtrado' : 'general')
                        . ': <strong class=\'txt-oro\'>' . money($totalFiltrado) . '</strong> (sin contar los anulados)'" />

    <div class="spg-panel">
        <x-filtros :f="$f" />

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Fecha</th><th>Cliente</th><th>Comprobante</th><th>Medio</th>
                        <th class="text-end">Monto</th><th>Referencia</th><th>Estado</th><th class="text-end">Anular</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td>{{ fecha($r->fecha) }}</td>
                            <td>
                                {{ $r->cliente ?: '—' }}
                                @if ($r->es_sena)<span class="badge-estado e-warn">seña</span>@endif
                            </td>
                            <td class="text-muted-warm">{{ $r->nro_comprobante ?: '—' }}</td>
                            <td>{{ $r->metodo }}</td>
                            <td class="text-end">{{ money($r->monto) }}</td>
                            <td class="text-muted-warm">{{ $r->referencia ?: '—' }}</td>
                            <td>{!! estado_badge($r->estado) !!}</td>
                            <td class="text-end">
                                @if ($r->estado !== 'Anulado')
                                    <button class="btn btn-sm btn-outline-neutro" title="Anular"
                                            data-bs-toggle="modal" data-bs-target="#modalAnular{{ $r->id_cobro }}">
                                        <i class="bi bi-x-circle"></i></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="spg-vacio">
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
