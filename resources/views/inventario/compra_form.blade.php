@extends('layout.app')

@section('titulo', 'Nueva compra')

@section('contenido')
    @php use App\Servicios\Permisos; @endphp

    <x-encabezado sub="La mercadería que llega con factura del proveedor. Al guardar, la base genera los movimientos de stock y deja registrada la deuda." />

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="spg-panel">
                <form method="post" action="{{ route('inventario.compra.guardar') }}">
                    @csrf

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="id_proveedor">Proveedor *</label>
                            <select class="form-select" id="id_proveedor" name="id_proveedor" required>
                                <option value="">— elegí un proveedor —</option>
                                @foreach ($proveedores as $p)
                                    <option value="{{ $p->id_proveedor }}"
                                        @selected($sel_proveedor === (int) $p->id_proveedor)>{{ $p->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="id_condicion_venta">Condición *</label>
                            <select class="form-select" id="id_condicion_venta" name="id_condicion_venta" required>
                                @foreach ($condiciones as $cv)
                                    <option value="{{ $cv->id_condicion_venta }}">{{ $cv->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="nro_factura_proveedor">Nº de factura</label>
                            <input class="form-control" id="nro_factura_proveedor" name="nro_factura_proveedor"
                                   maxlength="30" value="{{ old('nro_factura_proveedor') }}">
                        </div>
                    </div>

                    <h2 class="spg-form-titulo mb-1"><i class="bi bi-list-ul"></i> Productos</h2>
                    <p class="text-muted-warm mb-2" style="font-size:.8rem">
                        Elegí el producto de la lista siempre que exista. Si escribís el nombre a mano y ya
                        estaba cargado —aunque sea con un espacio de más—, el sistema lo reconoce y le suma
                        el stock en vez de crear un duplicado.
                    </p>

                    <div id="filasCompra">
                        @for ($i = 0; $i < 3; $i++)
                            <div class="row g-2 mb-2 filaCompra">
                                <div class="col-md-5">
                                    <input class="form-control form-control-sm nombreProd" name="nombre[]"
                                           list="listaProductos" placeholder="Producto" autocomplete="off">
                                    <input type="hidden" name="id_producto[]" class="idProd" value="0">
                                </div>
                                <div class="col-md-2">
                                    <input class="form-control form-control-sm input-miles" name="cantidad[]"
                                           data-decimales="2" data-min="0" placeholder="Cantidad">
                                </div>
                                <div class="col-md-2">
                                    <input class="form-control form-control-sm input-miles" name="precio[]"
                                           data-min="0" placeholder="Precio">
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select form-select-sm" name="categoria[]">
                                        @foreach ($categorias as $c)
                                            <option value="{{ $c->id_categoria }}">{{ $c->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endfor
                    </div>

                    <datalist id="listaProductos">
                        @foreach ($productos as $p)
                            <option value="{{ $p->nombre }}" data-id="{{ $p->id_producto }}"></option>
                        @endforeach
                    </datalist>

                    <button type="button" class="btn btn-sm btn-rapido mb-3" id="masFilas">
                        <i class="bi bi-plus-lg"></i> Otra fila
                    </button>

                    <div class="mb-3">
                        <label class="form-label" for="observaciones">Observaciones</label>
                        <input class="form-control" id="observaciones" name="observaciones" maxlength="150"
                               value="{{ old('observaciones') }}">
                    </div>

                    <button class="btn btn-oro"
                            data-confirmar="Al guardar, la mercadería entra al stock y queda registrada la deuda con el proveedor. ¿Confirmás?">
                        <i class="bi bi-check-lg"></i> Registrar la compra
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            @if (Permisos::puede('inventario.proveedores'))
                <div class="spg-panel">
                    <h2 class="spg-form-titulo mb-2"><i class="bi bi-truck"></i> ¿El proveedor no está cargado?</h2>
                    <p class="text-muted-warm" style="font-size:.82rem">
                        Crealo acá mismo, sin perder las líneas que ya escribiste.
                    </p>
                    <form method="post" action="{{ route('inventario.proveedor.rapido') }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label" for="pv_nombre">Nombre o razón social *</label>
                            <input class="form-control form-control-sm" id="pv_nombre" name="nombre" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="pv_ruc">RUC</label>
                            <input class="form-control form-control-sm" id="pv_ruc" name="ruc">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label" for="pv_contacto">Contacto</label>
                                <input class="form-control form-control-sm" id="pv_contacto" name="contacto">
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="pv_telefono">Teléfono</label>
                                <input class="form-control form-control-sm" id="pv_telefono" name="telefono">
                            </div>
                        </div>
                        <button class="btn btn-rapido w-100"><i class="bi bi-plus-lg"></i> Crear proveedor</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
// Cuando el nombre coincide con uno de la lista, se manda el id: así un espacio
// de más no termina creando un producto duplicado y partiendo el stock en dos.
(function () {
    var lista = document.getElementById('listaProductos');

    function idDe(nombre) {
        var op = Array.prototype.find.call(lista.options, function (o) {
            return o.value.trim().toLowerCase() === nombre.trim().toLowerCase();
        });
        return op ? op.dataset.id : 0;
    }

    function enganchar(fila) {
        var campo = fila.querySelector('.nombreProd'), oculto = fila.querySelector('.idProd');
        campo.addEventListener('input', function () { oculto.value = idDe(campo.value); });
        campo.addEventListener('change', function () { oculto.value = idDe(campo.value); });
    }

    document.querySelectorAll('.filaCompra').forEach(enganchar);

    document.getElementById('masFilas').addEventListener('click', function () {
        var cont = document.getElementById('filasCompra');
        var copia = cont.querySelector('.filaCompra').cloneNode(true);
        copia.querySelectorAll('input').forEach(function (i) { i.value = i.classList.contains('idProd') ? '0' : ''; });
        cont.appendChild(copia);
        enganchar(copia);
    });
})();
</script>
@endpush
