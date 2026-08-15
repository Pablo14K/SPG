@extends('layout.app')

@section('titulo', $p ? 'Editar producto' : 'Nuevo producto')

@section('contenido')
    @php $id = $p->id_producto ?? 0; @endphp

    <div class="spg-page-head">
        <a class="spg-back" href="{{ route('inventario.productos') }}"><i class="bi bi-arrow-left"></i> Productos</a>
        <h1 class="mt-1">{{ $id ? 'Editar producto' : 'Nuevo producto' }}</h1>
    </div>

    <div class="spg-panel" style="max-width:760px">
        <form method="post" action="{{ route('inventario.producto.guardar') }}">
            @csrf
            <input type="hidden" name="id_producto" value="{{ $id }}">

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="nombre">Nombre *</label>
                    <input class="form-control" id="nombre" name="nombre" required
                           value="{{ old('nombre', $p->nombre ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="id_categoria">Categoría *</label>
                    <select class="form-select" id="id_categoria" name="id_categoria" required>
                        @foreach ($cats as $c)
                            <option value="{{ $c->id_categoria }}"
                                @selected((int) old('id_categoria', $p->id_categoria ?? 0) === (int) $c->id_categoria)>
                                {{ $c->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label" for="descripcion">Descripción</label>
                    <input class="form-control" id="descripcion" name="descripcion"
                           value="{{ old('descripcion', $p->descripcion ?? '') }}">
                </div>
            </div>

            <hr class="my-4">

            {{-- El frasco y el mililitro --}}
            <h2 class="spg-form-titulo mb-1"><i class="bi bi-rulers"></i> Cómo se compra y cómo se gasta</h2>
            <p class="text-muted-warm mb-3" style="font-size:.8rem">
                El shampoo se compra por frasco de 1 litro y se usa de a 30 ml. Si cargás las dos casillas
                de abajo, quien registre la atención va a poder anotar <strong>30 ml</strong> y el sistema
                descuenta la fracción de frasco que corresponde. El stock siempre se guarda en la unidad
                de compra, que es la que factura el proveedor.
            </p>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="unidad_medida">Unidad de compra</label>
                    <input class="form-control" id="unidad_medida" name="unidad_medida"
                           placeholder="frasco, unidad, caja…"
                           value="{{ old('unidad_medida', $p->unidad_medida ?? 'unidad') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="contenido">Contenido de cada unidad</label>
                    <input class="form-control input-miles" id="contenido" name="contenido" data-decimales="2"
                           data-min="0" placeholder="1000"
                           value="{{ old('contenido', $p->contenido ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="unidad_consumo">Se gasta en</label>
                    <input class="form-control" id="unidad_consumo" name="unidad_consumo" placeholder="ml, g, aplicación…"
                           value="{{ old('unidad_consumo', $p->unidad_consumo ?? '') }}">
                    <div class="form-text">Las dos van juntas o ninguna.</div>
                </div>
            </div>

            <hr class="my-4">

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="stock_minimo">Stock mínimo</label>
                    <input class="form-control input-miles" id="stock_minimo" name="stock_minimo"
                           data-decimales="2" data-min="0"
                           value="{{ old('stock_minimo', $p->stock_minimo ?? 0) }}">
                    <div class="form-text">Por debajo de esto, aparece el aviso de reposición.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="precio_costo">Precio de costo</label>
                    <div class="input-group">
                        <span class="input-group-text">{{ config('spg.moneda') }}</span>
                        <input class="form-control input-miles" id="precio_costo" name="precio_costo" data-min="0"
                               value="{{ monto_input(old('precio_costo', $p->precio_costo ?? 0)) }}">
                    </div>
                </div>
                {{-- **Precio de venta: fuera de alcance desde la 7.24.0.**
                     El salón vende servicios, no productos —los consume atendiendo—, así que
                     pedir a cuánto se vendería prometía algo que ninguna pantalla hace: en los
                     90 días de la simulación no se facturó un solo producto (hallazgo IN-03).

                     Queda comentado y NO borrado a propósito, por si se revierte la decisión.
                     Para volver a encenderlo hacen falta las cuatro cosas, no sólo ésta:
                     este campo, la columna de la lista, el alta rápida de «Cargar stock» y la
                     línea de `InventarioController::productoGuardar` que lo lee. La columna
                     `producto.precio_venta` sigue en la base, en NOT NULL DEFAULT 0.
                <div class="col-md-3">
                    <label class="form-label" for="precio_venta">Precio de venta</label>
                    <div class="input-group">
                        <span class="input-group-text">{{ config('spg.moneda') }}</span>
                        <input class="form-control input-miles" id="precio_venta" name="precio_venta" data-min="0"
                               value="{{ monto_input(old('precio_venta', $p->precio_venta ?? 0)) }}">
                    </div>
                </div>
                --}}
                <div class="col-md-3">
                    <label class="form-label" for="tasa_iva">IVA</label>
                    <select class="form-select" id="tasa_iva" name="tasa_iva">
                        @foreach ([10 => '10 %', 5 => '5 %', 0 => 'Exento'] as $v => $t)
                            <option value="{{ $v }}" @selected((int) old('tasa_iva', $p->tasa_iva ?? 10) === $v)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>

                @unless ($id)
                    <div class="col-md-4">
                        <label class="form-label" for="stock_inicial">Stock inicial</label>
                        <input class="form-control input-miles" id="stock_inicial" name="stock_inicial"
                               data-decimales="2" data-min="0" value="0">
                        <div class="form-text">
                            Lo que ya tenés en el depósito. Queda registrado como un movimiento de entrada.
                        </div>
                    </div>
                @endunless
            </div>

            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-oro"><i class="bi bi-check-lg"></i> Guardar</button>
                <a class="btn btn-outline-neutro" href="{{ route('inventario.productos') }}">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
