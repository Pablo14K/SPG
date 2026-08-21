@extends('layout.app')

@section('titulo', 'Productos')

@section('contenido')
    <x-encabezado
        sub="Catálogo de productos. <strong>El stock no se guarda</strong>: lo calcula la base sumando los movimientos, así que nunca puede quedar desfasado de la realidad."
        :accion="['ruta' => 'inventario.producto_form', 't' => 'Nuevo producto', 'ic' => 'plus-lg']"
        :acciones="[['ruta' => 'inventario.ajuste', 't' => 'Cargar stock', 'ic' => 'plus-slash-minus']]" />

    {{-- **Traer el catálogo entero de otra sede.** Un local que abre arranca
         vacío, y traer los productos de a uno son treinta clics para dejarlo
         igual que la casa central. No copia stock: sólo dice qué se maneja
         acá, y cada sede lleva el suyo desde cero. --}}
    @if (($otras ?? []) && collect($otras)->sum('faltan') > 0)
        <div class="spg-panel mb-3">
            <form method="post" action="{{ route('inventario.productos.traer_todos') }}"
                  class="d-flex gap-2 align-items-end flex-wrap">
                @csrf
                <div>
                    <label class="form-label mb-1" for="traerDe">
                        <i class="bi bi-box-arrow-in-down"></i> Traer todo el catálogo de otra sucursal
                    </label>
                    <select class="form-select form-select-sm" id="traerDe" name="id_sucursal_origen"
                            style="min-width:260px" required>
                        <option value="">— Elegí de dónde —</option>
                        @foreach ($otras as $o)
                            @if ((int) $o->faltan > 0)
                                <option value="{{ $o->id_sucursal }}">
                                    {{ $o->nombre }} · {{ (int) $o->faltan }} que acá falta(n)
                                </option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-sm btn-rapido"
                        data-confirmar="Van a pasar a manejarse acá todos los productos de esa sucursal que todavía no estén. El stock arranca en cero. ¿Seguimos?">
                    <i class="bi bi-check-lg"></i> Traer
                </button>
            </form>
        </div>
    @endif

    <div class="spg-panel">
        <x-filtros :f="$f" />

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Producto</th><th>Categoría</th><th class="text-end">Stock</th>
                        <th class="text-end">Mínimo</th><th class="text-end">Costo</th>
                        {{-- <th class="text-end">Venta</th> --}}<th>Estado</th><th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $p)
                        {{-- **`aca` en NULL no es «cero stock»: es «este local no maneja
                             este producto».** Son cosas distintas y la fila lo dice, con
                             el botón para traerlo en vez de volver a cargarlo con otro
                             nombre. El catálogo es único desde la 7.33.0. --}}
                        @php
                            $mio = (bool) $p->aca;
                            $bajo = $mio && (float) $p->stock_actual < (float) $p->stock_minimo;
                        @endphp
                        <tr class="{{ $mio ? '' : 'text-muted-warm' }}">
                            <td>
                                {{ $p->nombre }}
                                @if (producto_fraccionado((array) $p))
                                    <span class="badge-estado e-prog" title="Se consume por partes">
                                        {{ cant($p->contenido) }} {{ $p->unidad_consumo }} por {{ $p->unidad_medida }}</span>
                                @endif
                            </td>
                            <td class="text-muted-warm">{{ $p->categoria }}</td>
                            <td class="text-end">
                                @if ($mio)
                                    <strong class="{{ $bajo ? 'txt-no' : '' }}">{{ cant($p->stock_actual) }}</strong>
                                    <span class="text-muted-warm">{{ $p->unidad_medida }}</span>
                                    @if (producto_fraccionado((array) $p))
                                        <div class="text-muted-warm" style="font-size:.72rem">
                                            {{ cant(stock_a_consumo((array) $p, (float) $p->stock_actual)) }}
                                            {{ $p->unidad_consumo }}
                                        </div>
                                    @endif
                                @else
                                    <span class="text-muted-warm">—</span>
                                @endif
                            </td>
                            <td class="text-end text-muted-warm">{{ $mio ? cant($p->stock_minimo) : '—' }}</td>
                            <td class="text-end">{{ money($p->precio_costo) }}</td>
                            {{-- Precio de venta: fuera de alcance (ver el formulario del producto).
                            <td class="text-end">{{ money($p->precio_venta) }}</td>
                            --}}
                            <td>
                                @if (! $mio)
                                    <span class="badge-estado e-muted">En otra sucursal</span>
                                @elseif (! $p->activo)
                                    <span class="badge-estado e-muted">Inactivo</span>
                                @elseif ($bajo)
                                    <span class="badge-estado e-warn">Reponer</span>
                                @else
                                    <span class="badge-estado e-ok">Activo</span>
                                @endif
                            </td>
                            @if (! $mio)
                                <td class="text-end" style="white-space:nowrap">
                                    <form method="post" action="{{ route('inventario.producto.traer') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="id_producto" value="{{ $p->id_producto }}">
                                        <button class="btn btn-sm btn-rapido" title="Manejarlo también en esta sucursal">
                                            <i class="bi bi-plus-lg"></i> Traer acá</button>
                                    </form>
                                </td>
                            @else
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
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">{{-- eran 8 con «Venta», que quedó fuera de alcance --}}
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
