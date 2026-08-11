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
