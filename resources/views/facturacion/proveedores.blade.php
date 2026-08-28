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
                    <tr><th>Fecha</th><th>Proveedor</th><th>Compra que pagó</th><th>Medio</th><th>Referencia</th>
                        <th class="text-end">Monto</th><th>Estado</th><th class="text-end">Anular</th></tr>
                </thead>
                <tbody>
                    @forelse ($pagos as $p)
                        <tr>
                            <td>{{ fecha($p->fecha) }}</td>
                            <td>{{ $p->proveedor }}</td>
                            {{-- **Qué compra pagó.** El pago SÍ queda ligado a la
                                 compra —`sp_pagar_compra` escribe el detalle— pero acá
                                 no se veía: con el mismo proveedor repetido no había
                                 forma de saber cuál de las cuatro compras se pagó.
                                 Un pago puede cubrir varias, y por eso salen todas. --}}
                            <td class="text-muted-warm" style="font-size:.83rem">
                                {{ $p->compras ?: '—' }}
                                {{-- **El papel que llega después del pago.** La compra
                                     saldada ya no está en «Cuentas por pagar», así que
                                     éste es el único lugar desde donde se la puede
                                     alcanzar. --}}
                                @if ($p->compra_sin_factura && $p->estado !== 'Anulado')
                                    <button type="button" class="btn btn-sm btn-rapido mt-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalNroFac{{ $p->compra_sin_factura }}">
                                        <i class="bi bi-receipt"></i> Cargar la factura</button>
                                @endif
                            </td>
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
                        <tr><td colspan="8" class="text-center text-muted-warm py-3">Todavía no hay pagos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Cargar el número de la factura de una compra ya pagada. --}}
    @php $yaPuesto = []; @endphp
    @foreach ($pagos as $p)
        @if ($p->compra_sin_factura && ! in_array($p->compra_sin_factura, $yaPuesto, true))
            @php $yaPuesto[] = $p->compra_sin_factura; @endphp
            <div class="modal fade" id="modalNroFac{{ $p->compra_sin_factura }}" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <form method="post" action="{{ route('inventario.compra.factura') }}" class="modal-content">
                        @csrf
                        <input type="hidden" name="id_compra" value="{{ $p->compra_sin_factura }}">
                        <input type="hidden" name="desde" value="pagos">
                        <div class="modal-header">
                            <h5 class="modal-title" style="font-size:1rem">
                                <i class="bi bi-receipt"></i> Factura del proveedor</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted-warm" style="font-size:.86rem">
                                {{ $p->proveedor }} · {{ $p->compras }}
                            </p>
                            <label class="form-label" for="nf{{ $p->compra_sin_factura }}">Número</label>
                            <input class="form-control" id="nf{{ $p->compra_sin_factura }}"
                                   name="nro_factura_proveedor" required maxlength="30"
                                   placeholder="001-001-0001234">
                            <div class="form-text">
                                Es el número del papel que entregó el proveedor. Queda
                                pegado a la compra, así que el pago y la factura se
                                pueden rastrear juntos.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-oro"><i class="bi bi-check2"></i> Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endforeach

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
                                {{-- **El número de la factura del proveedor se carga
                                     acá si todavía no está.** El papel casi siempre
                                     llega con el pago, y una vez saldada la compra
                                     desaparece de esta lista: desde ahí ya no había
                                     dónde vincularlo. --}}
                                @unless (trim((string) ($c->nro_factura_proveedor ?? '')) !== '')
                                    <div class="mb-3">
                                        <label class="form-label" for="nfp{{ $c->id_compra }}">
                                            Nº de factura del proveedor
                                            <span class="text-muted-warm">(opcional)</span></label>
                                        <input class="form-control" id="nfp{{ $c->id_compra }}"
                                               name="nro_factura_proveedor" maxlength="30"
                                               placeholder="001-001-0001234">
                                        <div class="form-text">
                                            Si el papel vino con el pago, cargalo ahora: después
                                            la compra sale de esta lista.
                                        </div>
                                    </div>
                                @endunless

                                <p class="mb-3">
                                    Saldo de la compra: <strong class="txt-oro">{{ money($c->saldo) }}</strong>
                                    {{-- **El pago parcial ya se podía y no se decía.** Viene
                                         propuesto el saldo entero, pero el monto es
                                         editable: escribiendo menos queda el resto
                                         pendiente y la compra sigue apareciendo acá. --}}
                                    <br><span style="font-size:.82rem">Podés pagar menos: lo que quede
                                    sigue como saldo pendiente de esta compra.</span>
                                </p>
                                {{-- **De qué cajón sale la plata.** Con dos abiertos,
                                     tomar «el último» deja el egreso en el arqueo de
                                     otra persona y se descubre al cerrar. Son los del
                                     local DE LA COMPRA, que es de donde sale. --}}
                                @include('facturacion._caja_elegir', [
                                    'cajas' => $cajasPorCompra[$c->id_compra] ?? [],
                                    'uid' => 'Prov' . $c->id_compra,
                                    'rotulo' => '¿De qué caja sale la plata?',
                                    'ayuda' => 'El egreso entra al arqueo de esa caja. Son las abiertas en '
                                        . $c->sucursal . ', que es el local de la compra.',
                                ])

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
                                        {{-- **No es la factura del proveedor.** Se leía
                                             como que el sistema la pedía dos veces: acá
                                             va el comprobante de ESTE pago —el número
                                             que devuelve el banco al transferir, o el
                                             recibo que firma el proveedor—, que es lo
                                             que permite rastrearlo el día que reclamen
                                             que no se pagó. En efectivo casi nunca hay
                                             ninguno, y por eso es opcional. --}}
                                        <label class="form-label" for="refp{{ $c->id_compra }}">
                                            Comprobante de este pago
                                            <span class="text-muted-warm">(opcional)</span></label>
                                        <input class="form-control" id="refp{{ $c->id_compra }}"
                                               name="referencia" maxlength="60"
                                               placeholder="Nº de transferencia, recibo…">
                                        <div class="form-text">
                                            Es el respaldo de la salida de plata, no la factura del
                                            proveedor: esa es la de arriba y se carga una sola vez.
                                        </div>
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
