@extends('layout.app')

@section('titulo', 'Stock')

@section('contenido')
    <x-encabezado
        sub="Existencias de todos los productos activos. El stock lo calcula la base sumando los movimientos: no hay una columna que pueda quedar vieja."
        :accion="['ruta' => 'inventario.ajuste', 't' => 'Cargar stock', 'ic' => 'plus-slash-minus']" />

    {{-- Lo que hay que ir a comprar. Si el shampoo se acaba a mitad de un
         servicio, el problema ya no tiene arreglo. --}}
    @if ($bajo)
        <div class="spg-panel mb-3" style="border-left:3px solid var(--oro)">
            <h2 class="spg-form-titulo mb-2">
                <i class="bi bi-exclamation-triangle"></i> Hay que reponer {{ count($bajo) }} producto(s)
            </h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-2">
                    <thead>
                        <tr><th>Producto</th><th>Categoría</th><th class="text-end">Hay</th>
                            <th class="text-end">Mínimo</th><th class="text-end">Falta</th>
                            <th class="text-end">Costo de reponer</th></tr>
                    </thead>
                    <tbody>
                        @php $totalRepo = 0; @endphp
                        @foreach ($bajo as $b)
                            @php $totalRepo += (float) $b->faltante * (float) $b->precio_costo; @endphp
                            <tr>
                                <td>{{ $b->nombre }}</td>
                                <td class="text-muted-warm">{{ $b->categoria }}</td>
                                <td class="text-end txt-no">{{ cant($b->stock_actual) }}</td>
                                <td class="text-end text-muted-warm">{{ cant($b->stock_minimo) }}</td>
                                <td class="text-end"><strong>{{ cant($b->faltante) }}</strong></td>
                                <td class="text-end">{{ money((float) $b->faltante * (float) $b->precio_costo) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="text-end"><strong>Con cuánta plata hay que ir</strong></td>
                            <td class="text-end"><strong class="txt-oro">{{ money($totalRepo) }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @if (\App\Servicios\Navegacion::existe('inventario.compra_form'))
                <a class="btn btn-sm btn-oro" href="{{ route('inventario.compra_form') }}">
                    <i class="bi bi-bag-plus"></i> Registrar la compra</a>
            @endif
        </div>
    @endif

    <div class="spg-panel">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Producto</th><th>Categoría</th><th class="text-end">Stock</th>
                        <th class="text-end">Mínimo</th><th class="text-end">Valor a costo</th><th class="text-end">Cargar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $p)
                        @php $bajoMin = (float) $p->stock_actual < (float) $p->stock_minimo; @endphp
                        <tr>
                            <td>{{ $p->nombre }}</td>
                            <td class="text-muted-warm">{{ $p->categoria }}</td>
                            <td class="text-end">
                                <strong class="{{ $bajoMin ? 'txt-no' : '' }}">{{ cant($p->stock_actual) }}</strong>
                                <span class="text-muted-warm">{{ $p->unidad_medida }}</span>
                                @if (producto_fraccionado((array) $p))
                                    <div class="text-muted-warm" style="font-size:.72rem">
                                        {{ cant(stock_a_consumo((array) $p, (float) $p->stock_actual)) }}
                                        {{ $p->unidad_consumo }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-end text-muted-warm">{{ cant($p->stock_minimo) }}</td>
                            <td class="text-end">{{ money((float) $p->stock_actual * (float) $p->precio_costo) }}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-neutro" title="Cargar o corregir stock"
                                   href="{{ route('inventario.ajuste', ['producto' => $p->id_producto]) }}">
                                    <i class="bi bi-plus-slash-minus"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="spg-vacio">
                                    <i class="bi bi-clipboard-data"></i>
                                    <div class="t">No hay productos activos.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
