@extends('layout.app')

@section('titulo', 'Cargar stock')

@section('contenido')
    @php use App\Servicios\Permisos; @endphp

    <x-encabezado sub="Para cargar mercadería sin factura, corregir un conteo o registrar una merma. Lo que llega con factura de proveedor se carga desde <strong>Compras</strong>, que además deja la deuda registrada." />

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="spg-panel">
                <form method="post" action="{{ route('inventario.ajuste.guardar') }}" id="formAjuste">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label" for="id_producto">Producto *</label>
                        <input class="form-control form-control-sm mb-1" data-filtra="#id_producto"
                               placeholder="Buscar un producto…" autocomplete="off">
                        <select class="form-select" id="id_producto" name="id_producto" required>
                            <option value="">— elegí un producto —</option>
                            @foreach ($prods as $p)
                                <option value="{{ $p->id_producto }}"
                                    @selected((int) old('id_producto', $sel) === (int) $p->id_producto)>
                                    {{ $p->nombre }} — hay {{ cant($p->stock) }} {{ $p->unidad_medida }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Dos modos, y la diferencia importa: «fijar» calcula solo
                         la diferencia contra lo que hay; «movimiento» registra
                         una entrada o salida puntual. --}}
                    <div class="mb-3">
                        <label class="form-label">¿Qué querés hacer?</label>

                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="modo" value="fijar"
                                   id="modoFijar" @checked(old('modo', 'fijar') === 'fijar')>
                            <label class="form-check-label" for="modoFijar">
                                <strong>Dejar el stock en un número</strong>
                                <span class="text-muted-warm">— conté el depósito y quiero que quede en eso</span>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="modo" value="movimiento" id="modoMov"
                                   @checked(old('modo') === 'movimiento')>
                            <label class="form-check-label" for="modoMov">
                                <strong>Registrar una entrada o una salida</strong>
                                <span class="text-muted-warm">— llegó mercadería, se rompió algo, se devolvió</span>
                            </label>
                        </div>
                    </div>

                    <div id="bloqueFijar">
                        <label class="form-label" for="stock_nuevo">El stock tiene que quedar en</label>
                        <input class="form-control input-miles" id="stock_nuevo" name="stock_nuevo"
                               data-decimales="2" data-min="0" value="{{ old('stock_nuevo') }}">
                        <div class="form-text mb-3">
                            El sistema calcula la diferencia y registra el ajuste que corresponda.
                        </div>
                    </div>

                    <div id="bloqueMov" style="display:none">
                        <div class="row g-2 mb-3">
                            <div class="col-md-7">
                                <label class="form-label" for="id_tipo_movimiento">Tipo de movimiento</label>
                                <select class="form-select" id="id_tipo_movimiento" name="id_tipo_movimiento">
                                    @foreach ($tipos as $t)
                                        <option value="{{ $t->id_tipo_movimiento }}"
                                            @selected((int) old('id_tipo_movimiento', 0) === (int) $t->id_tipo_movimiento)>
                                            {{ $t->nombre }} ({{ $t->signo === 'E' ? 'entrada' : 'salida' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="cantidad">Cantidad</label>
                                <input class="form-control input-miles" id="cantidad" name="cantidad"
                                       data-decimales="2" data-min="0" value="{{ old('cantidad') }}">
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label" for="precio_unitario">Precio unitario</label>
                            <div class="input-group">
                                <span class="input-group-text">{{ config('spg.moneda') }}</span>
                                <input class="form-control input-miles" id="precio_unitario"
                                       name="precio_unitario" data-min="0" value="{{ old('precio_unitario') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="referencia">Referencia</label>
                            <input class="form-control" id="referencia" name="referencia" maxlength="60"
                                   value="{{ old('referencia') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="observaciones">Observaciones</label>
                            <input class="form-control" id="observaciones" name="observaciones" maxlength="150"
                                   value="{{ old('observaciones') }}">
                        </div>
                    </div>

                    <button class="btn btn-oro"><i class="bi bi-check-lg"></i> Registrar</button>
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            @if (Permisos::puede('inventario.productos'))
                <div class="spg-panel">
                    <h2 class="spg-form-titulo mb-2"><i class="bi bi-plus-lg"></i> ¿El producto todavía no existe?</h2>
                    <p class="text-muted-warm" style="font-size:.82rem">
                        Crealo acá mismo con su stock inicial, sin perder lo que ya cargaste.
                    </p>
                    {{-- data-borrador: lo cargado en el ajuste vuelve con el
                         redirect en vez de perderse al crear el producto. --}}
                    <form method="post" action="{{ route('inventario.producto.rapido') }}"
                          data-borrador="#formAjuste">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label" for="pr_nombre">Nombre *</label>
                            <input class="form-control form-control-sm" id="pr_nombre" name="nombre" required>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-7">
                                <label class="form-label" for="pr_cat">Categoría *</label>
                                <select class="form-select form-select-sm" id="pr_cat" name="id_categoria" required>
                                    @foreach ($cats as $c)
                                        <option value="{{ $c->id_categoria }}">{{ $c->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-5">
                                <label class="form-label" for="pr_unidad">Unidad</label>
                                <input class="form-control form-control-sm" id="pr_unidad" name="unidad_medida"
                                       value="unidad">
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <label class="form-label" for="pr_stock">Stock</label>
                                <input class="form-control form-control-sm input-miles" id="pr_stock"
                                       name="stock_inicial" data-decimales="2" data-min="0" value="0">
                            </div>
                            <div class="col-4">
                                <label class="form-label" for="pr_costo">Costo</label>
                                <input class="form-control form-control-sm input-miles" id="pr_costo"
                                       name="precio_costo" data-min="0" value="0">
                            </div>
                            <div class="col-4">
                                <label class="form-label" for="pr_venta">Venta</label>
                                <input class="form-control form-control-sm input-miles" id="pr_venta"
                                       name="precio_venta" data-min="0" value="0">
                            </div>
                        </div>
                        <button class="btn btn-rapido w-100"><i class="bi bi-plus-lg"></i> Crear producto</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
// Mostrar solo el bloque del modo elegido
(function () {
    var fijar = document.getElementById('bloqueFijar'),
        mov = document.getElementById('bloqueMov');
    document.querySelectorAll('input[name="modo"]').forEach(function (r) {
        r.addEventListener('change', function () {
            var esFijar = document.getElementById('modoFijar').checked;
            fijar.style.display = esFijar ? '' : 'none';
            mov.style.display = esFijar ? 'none' : '';
        });
    });
})();
</script>
@endpush
