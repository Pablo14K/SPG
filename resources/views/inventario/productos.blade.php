@extends('layout.app')

@section('titulo', 'Productos')

@section('contenido')
    <x-encabezado
        sub="Catálogo de productos. <strong>El stock no se guarda</strong>: lo calcula la base sumando los movimientos, así que nunca puede quedar desfasado de la realidad."
        :accion="['ruta' => 'inventario.producto_form', 't' => 'Nuevo producto', 'ic' => 'plus-lg']"
        :acciones="[['ruta' => 'inventario.ajuste', 't' => 'Cargar stock', 'ic' => 'plus-slash-minus']]" />

    <div class="spg-panel">
        <x-filtros :f="$f" />

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Producto</th><th>Categoría</th><th class="text-end">Stock</th>
                        <th class="text-end">Mínimo</th><th class="text-end">Costo</th>
                        <th class="text-end">Venta</th><th>Estado</th><th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $p)
                        @php $bajo = (float) $p->stock_actual < (float) $p->stock_minimo; @endphp
                        <tr>
                            <td>
                                {{ $p->nombre }}
                                @if (producto_fraccionado((array) $p))
                                    <span class="badge-estado e-prog" title="Se consume por partes">
                                        {{ cant($p->contenido) }} {{ $p->unidad_consumo }} por {{ $p->unidad_medida }}</span>
                                @endif
                            </td>
                            <td class="text-muted-warm">{{ $p->categoria }}</td>
                            <td class="text-end">
                                <strong class="{{ $bajo ? 'txt-no' : '' }}">{{ cant($p->stock_actual) }}</strong>
                                <span class="text-muted-warm">{{ $p->unidad_medida }}</span>
                                @if (producto_fraccionado((array) $p))
                                    <div class="text-muted-warm" style="font-size:.72rem">
                                        {{ cant(stock_a_consumo((array) $p, (float) $p->stock_actual)) }}
                                        {{ $p->unidad_consumo }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-end text-muted-warm">{{ cant($p->stock_minimo) }}</td>
                            <td class="text-end">{{ money($p->precio_costo) }}</td>
                            <td class="text-end">{{ money($p->precio_venta) }}</td>
                            <td>
                                @if (! $p->activo)
                                    <span class="badge-estado e-muted">Inactivo</span>
                                @elseif ($bajo)
                                    <span class="badge-estado e-warn">Reponer</span>
                                @else
                                    <span class="badge-estado e-ok">Activo</span>
                                @endif
                            </td>
                            <td class="text-end" style="white-space:nowrap">
                                <a class="btn btn-sm btn-outline-neutro" title="Cargar stock"
                                   href="{{ route('inventario.ajuste', ['producto' => $p->id_producto]) }}">
                                    <i class="bi bi-plus-slash-minus"></i></a>
                                <a class="btn btn-sm btn-outline-neutro" title="Movimientos"
                                   href="{{ route('inventario.movimientos', ['producto' => $p->id_producto]) }}">
                                    <i class="bi bi-arrow-left-right"></i></a>
                                <a class="btn btn-sm btn-outline-neutro" title="Editar"
                                   href="{{ route('inventario.producto_form', $p->id_producto) }}">
                                    <i class="bi bi-pencil"></i></a>
                                <form method="post" action="{{ route('inventario.producto.baja') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="id_producto" value="{{ $p->id_producto }}">
                                    <button class="btn btn-sm btn-outline-neutro"
                                            title="{{ $p->activo ? 'Desactivar' : 'Activar' }}"
                                            data-confirmar="¿{{ $p->activo ? 'Desactivar' : 'Activar' }} «{{ $p->nombre }}»?">
                                        <i class="bi bi-toggle-{{ $p->activo ? 'on' : 'off' }}"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="spg-vacio">
                                    <i class="bi bi-box-seam"></i>
                                    <div class="t">{{ $f['activos'] ? 'Ningún producto coincide con esos filtros.' : 'Todavía no hay productos cargados.' }}</div>
                                    <div class="d">Sin productos no se puede registrar el consumo de una atención.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-paginacion :pag="$pag" :f="$f" />
    </div>
@endsection
