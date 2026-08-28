@extends('layout.app')

@section('titulo', 'Stock')

@section('contenido')
    <x-encabezado
        sub="Existencias de todos los productos activos. El stock lo calcula la base sumando los movimientos: no hay una columna que pueda quedar vieja."
        :accion="['ruta' => 'inventario.ajuste', 't' => 'Cargar stock', 'ic' => 'plus-slash-minus']" />

    {{-- **La lista de compras, no una tabla de estado.** Antes tenía seis
         columnas con «Hay», «Mínimo», «Falta» y «Costo de reponer» — cuatro
         números por renglón para contestar una sola pregunta: *cuánto hay que
         comprar de esto*. Con la mitad en cero se leía peor todavía.

         Ahora cada renglón dice **cuánto comprar** y, en chico y debajo, de
         dónde sale ese número. Las columnas que quedan son las dos que se
         miran: la cantidad y lo que cuesta. --}}
    @if ($bajo)
        <div class="spg-panel mb-3" style="border-left:3px solid var(--oro)">
            <h2 class="spg-form-titulo mb-1">
                <i class="bi bi-cart-plus"></i> Lista de compras
            </h2>
            {{-- **El mínimo es el punto de reposición**, así que llegar a él ya
                 es motivo de aviso: por eso entra también el que está justo en
                 su mínimo. Lo que se sugiere comprar nunca es cero — con cero
                 el producto se queda donde está, y la lista decía «comprar 0 ·
                 Gs. 0», que se lee como que está bien. --}}
            <p class="text-muted-warm mb-2" style="font-size:.85rem">
                {{ count($bajo) }} producto{{ count($bajo) === 1 ? '' : 's' }}
                llegó a su mínimo o quedó por debajo. Comprando esto, cada uno
                vuelve a estar por encima.
            </p>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-2">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th class="text-end">Comprar</th>
                            <th class="text-end">Cuánto cuesta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $totalRepo = 0; @endphp
                        @foreach ($bajo as $b)
                            @php $totalRepo += (float) $b->faltante * (float) $b->precio_costo; @endphp
                            <tr>
                                <td>
                                    {{ $b->nombre }}
                                    <div class="text-muted-warm" style="font-size:.78rem">
                                        {{ $b->categoria }} · hay {{ cant($b->stock_actual) }}
                                        {{ $b->unidad_medida ?? '' }} y el mínimo es
                                        {{ cant($b->stock_minimo) }}
                                    </div>
                                </td>
                                <td class="text-end">
                                    <strong>{{ cant($b->faltante) }}</strong>
                                    <div class="text-muted-warm" style="font-size:.78rem">
                                        {{ $b->unidad_medida ?? 'unidades' }}</div>
                                </td>
                                <td class="text-end">
                                    {{ money((float) $b->faltante * (float) $b->precio_costo) }}
                                    <div class="text-muted-warm" style="font-size:.78rem">
                                        a {{ money($b->precio_costo) }} c/u</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="border-top:2px solid var(--gris-calido)">
                            <th colspan="2" class="text-end">Con cuánta plata hay que ir</th>
                            <th class="text-end txt-oro">{{ money($totalRepo) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @if (\App\Servicios\Navegacion::existe('inventario.compra_form'))
                {{-- **El botón lleva la lista cargada.** Se llama «Registrar la
                     compra» debajo de una lista de faltantes: lo que espera quien
                     lo aprieta es encontrarlos ya puestos, no volver a tipear uno
                     por uno lo que la pantalla acaba de calcular. --}}
                <a class="btn btn-sm btn-oro" href="{{ route('inventario.compra_form', ['reponer' => 1]) }}">
                    <i class="bi bi-bag-plus"></i>
                    Registrar la compra de estos {{ count($bajo) }}</a>
                <a class="btn btn-sm btn-outline-neutro" href="{{ route('inventario.compra_form') }}">
                    Cargar otra compra</a>
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
