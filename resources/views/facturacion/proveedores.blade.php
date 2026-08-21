@extends('layout.app')

@section('titulo', 'Pagos a proveedores')

@section('contenido')
    <x-encabezado sub="Las compras confirmadas que todavía se deben. <strong>Un pago en efectivo no puede superar lo que hay en el cajón</strong>; los pagos por banco o transferencia no se frenan, porque no salen de ahí." />

    @if (! $caja)
        <div class="alert alert-warning">
            La caja está cerrada: se puede ver la deuda, pero no registrar pagos.
        </div>
    @endif

    <div class="spg-panel mb-3">
        <h2 class="spg-form-titulo mb-2"><i class="bi bi-cash-stack"></i> Cuentas por pagar</h2>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Proveedor</th><th>Compra</th><th>Vencimiento</th>
                        <th class="text-end">Total</th><th class="text-end">Saldo</th><th class="text-end">Pagar</th></tr>
                </thead>
                <tbody>
                    @forelse ($cuentas as $c)
                        <tr>
                            <td>{{ $c->proveedor }}</td>
                            <td class="text-muted-warm">
                                {{ fecha($c->fecha, 'd/m/Y') }}
                                @if ($c->nro_factura_proveedor ?? null) · {{ $c->nro_factura_proveedor }} @endif
                            </td>
                            <td>
                                @if ($c->vencida)
                                    <span class="badge-estado e-no">vencida</span>
                                @endif
                                <span class="text-muted-warm">
                                    {{ $c->vencimiento ? fecha($c->vencimiento, 'd/m/Y') : '—' }}</span>
                            </td>
                            <td class="text-end">{{ money($c->total) }}</td>
                            <td class="text-end"><strong class="txt-no">{{ money($c->saldo) }}</strong></td>
                            <td class="text-end">
                                @if ($caja)
                                    <button class="btn btn-sm btn-oro" data-bs-toggle="modal"
                                            data-bs-target="#modalPago{{ $c->id_compra }}">
                                        <i class="bi bi-cash-coin"></i> Pagar</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="spg-vacio">
                                    <i class="bi bi-check-circle"></i>
                                    <div class="t">No hay deudas pendientes con proveedores.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="spg-panel">
        <h2 class="spg-form-titulo mb-2"><i class="bi bi-clock-history"></i> Pagos registrados</h2>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Fecha</th><th>Proveedor</th><th>Medio</th><th>Referencia</th>
                        <th class="text-end">Monto</th><th>Estado</th><th class="text-end">Anular</th></tr>
                </thead>
                <tbody>
                    @forelse ($pagos as $p)
                        <tr>
                            <td>{{ fecha($p->fecha) }}</td>
                            <td>{{ $p->proveedor }}</td>
                            <td>{{ $p->metodo }}</td>
                            <td class="text-muted-warm">{{ $p->referencia ?: '—' }}</td>
                            <td class="text-end">{{ money($p->monto) }}</td>
                            <td>{!! estado_badge($p->estado) !!}</td>
                            <td class="text-end">
                                @if ($p->estado !== 'Anulado')
                                    <button class="btn btn-sm btn-outline-neutro" title="Anular"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalAnPago{{ $p->id_pago_proveedor }}">
                                        <i class="bi bi-x-circle"></i></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted-warm py-3">Todavía no hay pagos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Un modal de pago por cuenta pendiente --}}
    @if ($caja)
        @foreach ($cuentas as $c)
            <div class="modal fade" id="modalPago{{ $c->id_compra }}" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post" action="{{ route('facturacion.pagar_proveedor') }}">
                            @csrf
                            <input type="hidden" name="id_compra" value="{{ $c->id_compra }}">
                            <div class="modal-header">
                                <h5 class="modal-title" style="font-size:1rem">
                                    Pagar a {{ $c->proveedor }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-3">
                                    Saldo de la compra: <strong class="txt-oro">{{ money($c->saldo) }}</strong>
                                    {{-- **El pago parcial ya se podía y no se decía.** Viene
                                         propuesto el saldo entero, pero el monto es
                                         editable: escribiendo menos queda el resto
                                         pendiente y la compra sigue apareciendo acá. --}}
                                    <br><span style="font-size:.82rem">Podés pagar menos: lo que quede
                                    sigue como saldo pendiente de esta compra.</span>
                                </p>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label">Medio de pago</label>
                                        <select class="form-select" name="id_metodo_pago" required>
                                            @foreach ($metodos as $m)
                                                <option value="{{ $m->id_metodo_pago }}">{{ $m->nombre }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Monto</label>
                                        <div class="input-group">
                                            <span class="input-group-text">{{ config('spg.moneda') }}</span>
                                            <input class="form-control input-miles" name="monto" data-min="0"
                                                   value="{{ monto_input($c->saldo) }}" required>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Referencia</label>
                                        <input class="form-control" name="referencia" maxlength="60"
                                               placeholder="Nº de operación, recibo…">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                                <button class="btn btn-oro">Registrar el pago</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif

    @foreach ($pagos as $p)
        @continue ($p->estado === 'Anulado')
        <div class="modal fade" id="modalAnPago{{ $p->id_pago_proveedor }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="{{ route('facturacion.anular_pago_proveedor') }}">
                        @csrf
                        <input type="hidden" name="id_pago_proveedor" value="{{ $p->id_pago_proveedor }}">
                        <div class="modal-header">
                            <h5 class="modal-title" style="font-size:1rem">
                                Anular el pago de {{ money($p->monto) }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted-warm" style="font-size:.85rem">
                                El saldo de la compra vuelve a subir y el egreso deja de descontarse de la caja.
                            </p>
                            <label class="form-label" for="motPp{{ $p->id_pago_proveedor }}">Motivo *</label>
                            <input class="form-control" id="motPp{{ $p->id_pago_proveedor }}"
                                   name="motivo" required maxlength="200">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-oro">Anular</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection
