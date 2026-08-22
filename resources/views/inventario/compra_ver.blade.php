@extends('layout.app')

@section('titulo', 'Detalle de compra')

@section('contenido')
    <div class="spg-page-head">
        <a class="spg-back" href="{{ route('inventario.compras') }}"><i class="bi bi-arrow-left"></i> Compras</a>
        <h1 class="mt-1">Compra a {{ $compra->proveedor }}</h1>
        <div class="sub">
            {{ fecha($compra->fecha) }}
            @if ($compra->nro_factura_proveedor) · factura {{ $compra->nro_factura_proveedor }} @endif
            · {{ $compra->condicion }}
        </div>
    </div>

    {{-- **El número de factura se puede cargar después.** El papel no siempre
         llega con la mercadería: se recibe el pedido, se paga, y la factura
         aparece días más tarde. Pidiéndolo sólo al registrar la compra, o se
         inventaba uno o quedaba en blanco para siempre. --}}
    <div class="spg-panel mb-3">
        <form method="post" action="{{ route('inventario.compra.factura') }}"
              class="d-flex gap-2 align-items-end flex-wrap">
            @csrf
            <input type="hidden" name="id_compra" value="{{ $compra->id_compra }}">
            <div>
                <label class="form-label mb-1" for="nroFac">
                    <i class="bi bi-receipt"></i> Factura del proveedor
                </label>
                <input class="form-control form-control-sm" id="nroFac" name="nro_factura_proveedor"
                       data-solo="documento" inputmode="numeric" maxlength="30"
                       placeholder="001-001-0001234" style="min-width:200px"
                       list="facturasProveedor"
                       value="{{ $compra->nro_factura_proveedor }}">
                {{-- **Las que ya se anotaron al pagarle a este proveedor.** La
                     referencia de un pago suele ser el número del papel, así
                     que se ofrece como sugerencia — no se completa sola,
                     porque una referencia puede ser también un nº de
                     operación del banco. --}}
                <datalist id="facturasProveedor">
                    @foreach ($facturasSugeridas ?? [] as $ref)
                        <option value="{{ $ref }}"></option>
                    @endforeach
                </datalist>
            </div>
            <button class="btn btn-sm btn-rapido"><i class="bi bi-check-lg"></i>
                {{ $compra->nro_factura_proveedor ? 'Corregir' : 'Anotar' }}</button>
            @unless ($compra->nro_factura_proveedor)
                <span class="text-muted-warm" style="font-size:.82rem">
                    Todavía sin factura: anotala cuando el proveedor la entregue.
                </span>
            @endunless
        </form>
    </div>

    <div class="spg-metrics mb-3">
        <div class="spg-metric">
            <div class="lbl">Total</div>
            <div class="val oro">{{ money($compra->total) }}</div>
        </div>
        <div class="spg-metric">
            <div class="lbl">Saldo</div>
            <div class="val">{{ money($compra->saldo) }}</div>
        </div>
        <div class="spg-metric">
            <div class="lbl">Vencimiento</div>
            <div class="val" style="font-size:1rem">
                {{ $compra->vencimiento ? fecha($compra->vencimiento, 'd/m/Y') : '—' }}
            </div>
        </div>
        <div class="spg-metric">
            <div class="lbl">Estado</div>
            <div class="val" style="font-size:1rem">{!! estado_badge($compra->estado) !!}</div>
        </div>
    </div>

    <div class="spg-panel">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Producto</th><th>Categoría</th><th class="text-end">Cantidad</th>
                        <th class="text-end">Precio</th><th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lineas as $l)
                        <tr>
                            <td>{{ $l->nombre }}</td>
                            <td class="text-muted-warm">{{ $l->categoria }}</td>
                            <td class="text-end">{{ cant($l->cantidad) }} {{ $l->unidad_medida }}</td>
                            <td class="text-end">{{ money($l->precio_unitario) }}</td>
                            <td class="text-end">{{ money($l->total_linea) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end"><strong>Total</strong></td>
                        <td class="text-end"><strong class="txt-oro">{{ money($compra->total) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
