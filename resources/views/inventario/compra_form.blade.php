@extends('layout.app')

@section('titulo', 'Nueva compra')

@section('contenido')
    @php use App\Servicios\Permisos; @endphp

    <x-encabezado sub="La mercadería que llega con factura del proveedor. Al guardar, la base genera los movimientos de stock y deja registrada la deuda." />

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="spg-panel">
                <form method="post" action="{{ route('inventario.compra.guardar') }}" id="formCompra">
                    @csrf

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="id_proveedor">Proveedor *</label>
                            <select class="form-select" id="id_proveedor" name="id_proveedor" required>
                                <option value="">— elegí un proveedor —</option>
                                @foreach ($proveedores as $p)
                                    <option value="{{ $p->id_proveedor }}"
                                        @selected((int) old('id_proveedor', $sel_proveedor) === (int) $p->id_proveedor)>{{ $p->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="id_condicion_venta">Condición *</label>
                            <select class="form-select" id="id_condicion_venta" name="id_condicion_venta" required>
                                @foreach ($condiciones as $cv)
                                    <option value="{{ $cv->id_condicion_venta }}"
                                        data-dias="{{ (int) $cv->dias_credito }}"
                                        @selected((int) old('id_condicion_venta', 0) === (int) $cv->id_condicion_venta)>{{ $cv->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="nro_factura_proveedor">Nº de factura</label>
                            <input class="form-control" id="nro_factura_proveedor" name="nro_factura_proveedor"
                                   maxlength="30" value="{{ old('nro_factura_proveedor') }}">
                        </div>
                    </div>

                    {{-- Las cuotas sólo tienen sentido a crédito, así que el
                         bloque aparece al elegirlo. Se oculta con clase y no se
                         saca del formulario: si se quitaran los campos, `old()`
                         perdería lo cargado al volver con un error.

                         Al proveedor se le paga en cuotas, cada una con su fecha
                         y su monto. Antes «Crédito» era UN vencimiento a 30 días
                         y el salón no tenía cómo saber cuánto le vence la semana
                         que viene. --}}
                    <div id="bloqueCuotas" class="d-none mb-3 p-3 rounded" style="border:1px solid var(--gris-calido)">
                        <h2 class="spg-form-titulo mb-1"><i class="bi bi-calendar2-week"></i> ¿En cuántas cuotas?</h2>
                        <p class="text-muted-warm mb-2" style="font-size:.8rem">
                            Se reparte el total en partes iguales y se propone una fecha por mes.
                            <strong>Cambiá lo que haga falta</strong>: no todos los proveedores cobran igual.
                        </p>

                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-auto">
                                <label class="form-label" for="cantCuotas">Cuotas</label>
                                <input class="form-control form-control-sm" id="cantCuotas" type="number"
                                       min="1" max="24" value="{{ count((array) old('cuota_fecha', [])) ?: 1 }}"
                                       style="width:90px">
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn-sm btn-rapido" id="repartirCuotas">
                                    <i class="bi bi-arrow-repeat"></i> Repartir</button>
                            </div>
                            <div class="col">
                                <span class="text-muted-warm" id="avisoCuotas" style="font-size:.8rem"></span>
                            </div>
                        </div>

                        <div id="filasCuotas"></div>

                        {{-- El molde de una cuota. Como <template> no se dibuja
                             ni se envía: sólo se clona. --}}
                        <template id="moldeCuota">
                            <div class="row g-2 align-items-end mb-2 filaCuota">
                                <div class="col-auto" style="width:70px">
                                    <label class="form-label nroCuota">1ª</label>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Vence el</label>
                                    <input class="form-control form-control-sm" name="cuota_fecha[]" type="date">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Monto</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">{{ config('spg.moneda') }}</span>
                                        <input class="form-control input-miles" name="cuota_monto[]" data-min="0">
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <h2 class="spg-form-titulo mb-1"><i class="bi bi-list-ul"></i> Productos</h2>
                    <p class="text-muted-warm mb-2" style="font-size:.8rem">
                        Elegí el producto de la lista siempre que exista. Si escribís el nombre a mano y ya
                        estaba cargado —aunque sea con un espacio de más—, el sistema lo reconoce y le suma
                        el stock en vez de crear un duplicado.
                    </p>

                    {{-- Las filas se redibujan con lo que había cargado: al crear
                         un proveedor desde el costado se vuelve acá, y perder la
                         mercadería ya tipeada obligaba a cargarla dos veces.
                         Siempre quedan al menos tres, como al entrar. --}}
                    @php
                        $vNombre = (array) old('nombre', []);
                        $vId = (array) old('id_producto', []);
                        $vCant = (array) old('cantidad', []);
                        $vPrecio = (array) old('precio', []);
                        $vCat = (array) old('categoria', []);
                        $cuantas = max(3, count($vNombre));
                    @endphp
                    <div id="filasCompra">
                        @for ($i = 0; $i < $cuantas; $i++)
                            <div class="row g-2 mb-2 filaCompra">
                                <div class="col-md-5">
                                    <input class="form-control form-control-sm nombreProd" name="nombre[]"
                                           list="listaProductos" placeholder="Producto" autocomplete="off"
                                           value="{{ $vNombre[$i] ?? '' }}">
                                    <input type="hidden" name="id_producto[]" class="idProd"
                                           value="{{ $vId[$i] ?? 0 }}">
                                </div>
                                <div class="col-md-2">
                                    <input class="form-control form-control-sm input-miles" name="cantidad[]"
                                           data-decimales="2" data-min="0" placeholder="Cantidad"
                                           value="{{ $vCant[$i] ?? '' }}">
                                </div>
                                <div class="col-md-2">
                                    {{-- **El precio viene del catálogo y se puede cambiar.**
                                         Lo trae `app.js` al elegir un producto que ya
                                         existe: es lo último que se le pagó al
                                         proveedor, no un valor fijo. Un proveedor sube
                                         los precios, así que el campo queda abierto. --}}
                                    <input class="form-control form-control-sm input-miles precioProd" name="precio[]"
                                           data-min="0" placeholder="Precio" value="{{ $vPrecio[$i] ?? '' }}">
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select form-select-sm" name="categoria[]">
                                        @foreach ($categorias as $c)
                                            <option value="{{ $c->id_categoria }}"
                                                @selected((int) ($vCat[$i] ?? 0) === (int) $c->id_categoria)>{{ $c->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endfor
                    </div>

                    {{-- **El total no estaba en ningún lado.** Se cargaban las
                         filas y había que sumarlas de cabeza para saber si
                         coincidía con la factura del proveedor: el error se
                         descubría al pagar. --}}
                    <div class="d-flex justify-content-end gap-3 mt-2"
                         style="border-top:1px solid var(--gris-calido);padding-top:.6rem">
                        <span class="text-muted-warm" id="compraLineas">0 renglones</span>
                        <strong>Total: <span class="txt-oro" id="compraTotal">Gs. 0</span></strong>
                    </div>

                    <datalist id="listaProductos">
                        @foreach ($productos as $p)
                            <option value="{{ $p->nombre }}" data-id="{{ $p->id_producto }}"
                                    data-precio="{{ $p->ultimo_precio !== null ? (int) $p->ultimo_precio : '' }}"></option>
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
                    {{-- data-borrador: las filas de la compra ya cargadas
                         vuelven con el redirect. --}}
                    <form method="post" action="{{ route('inventario.proveedor.rapido') }}"
                          data-borrador="#formCompra">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label" for="pv_nombre">Nombre o razón social *</label>
                            <input class="form-control form-control-sm" id="pv_nombre" name="nombre" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="pv_ruc">RUC</label>
                            <input class="form-control form-control-sm" id="pv_ruc" name="ruc" data-solo="ruc" inputmode="text">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label" for="pv_contacto">Contacto</label>
                                <input class="form-control form-control-sm" id="pv_contacto" name="contacto">
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="pv_telefono">Teléfono</label>
                                <input class="form-control form-control-sm" id="pv_telefono" name="telefono" data-solo="telefono" inputmode="tel">
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
// Las cuotas: aparecen al elegir una condición a crédito, y se proponen solas
// repartiendo el total en partes iguales, una por mes.
(function () {
    var cond   = document.getElementById('id_condicion_venta'),
        bloque = document.getElementById('bloqueCuotas'),
        filas  = document.getElementById('filasCuotas'),
        molde  = document.getElementById('moldeCuota'),
        cant   = document.getElementById('cantCuotas'),
        aviso  = document.getElementById('avisoCuotas');
    if (!cond || !bloque) { return; }

    var ORDINAL = ['1ª','2ª','3ª','4ª','5ª','6ª','7ª','8ª','9ª','10ª','11ª','12ª'];

    function aCredito() {
        var op = cond.options[cond.selectedIndex];
        return op && parseInt(op.dataset.dias || '0', 10) > 0;
    }

    // El total de la compra sale de las filas de productos, que es lo que se
    // va a repartir. Se recalcula al repartir y no antes: las filas cambian.
    function totalCompra() {
        var t = 0;
        document.querySelectorAll('.filaCompra').forEach(function (f) {
            var c = f.querySelector('[name="cantidad[]"]'), p = f.querySelector('[name="precio[]"]');
            var cn = parseFloat((c && c.value || '0').replace(/\./g, '').replace(',', '.')) || 0;
            var pn = parseFloat((p && p.value || '0').replace(/\./g, '').replace(',', '.')) || 0;
            t += cn * pn;
        });
        return t;
    }

    function repartir() {
        var n = Math.max(1, Math.min(24, parseInt(cant.value || '1', 10) || 1));
        var total = totalCompra();
        // Lo que no divide exacto va en la ÚLTIMA cuota, así la suma cierra
        // contra el total y no queda un guaraní suelto.
        var base = total > 0 ? Math.floor(total / n) : 0;
        var resto = total > 0 ? total - base * n : 0;

        filas.innerHTML = '';
        for (var i = 0; i < n; i++) {
            var fila = molde.content.firstElementChild.cloneNode(true);
            fila.querySelector('.nroCuota').textContent = ORDINAL[i] || (i + 1) + 'ª';

            var d = new Date();
            d.setMonth(d.getMonth() + i + 1);
            fila.querySelector('[name="cuota_fecha[]"]').value = d.toISOString().slice(0, 10);

            var monto = base + (i === n - 1 ? resto : 0);
            if (monto > 0) { fila.querySelector('[name="cuota_monto[]"]').value = String(monto); }
            filas.appendChild(fila);
            if (window.SPG) { window.SPG.prepararCampos(fila); }
        }
        aviso.textContent = total > 0
            ? 'Se reparten ' + total.toLocaleString('es-PY') + ' en ' + n + ' cuota(s).'
            : 'Cargá primero los productos y volvé a repartir para que calcule los montos.';
    }

    function ajustar() {
        var si = aCredito();
        bloque.classList.toggle('d-none', !si);
        if (si && !filas.children.length) { repartir(); }
        if (!si) { filas.innerHTML = ''; }
    }

    cond.addEventListener('change', ajustar);
    document.getElementById('repartirCuotas').addEventListener('click', repartir);
    ajustar();
})();

// Cuando el nombre coincide con uno de la lista, se manda el id: así un espacio
// de más no termina creando un producto duplicado y partiendo el stock en dos.
(function () {
    var lista = document.getElementById('listaProductos');

    function opcionDe(nombre) {
        return Array.prototype.find.call(lista.options, function (o) {
            return o.value.trim().toLowerCase() === nombre.trim().toLowerCase();
        });
    }

    function idDe(nombre) {
        var op = opcionDe(nombre);
        return op ? op.dataset.id : 0;
    }

    function miles(n) {
        return (Math.round(n) || 0).toLocaleString('es-PY', { maximumFractionDigits: 0 });
    }

    function aNumero(v) {
        return parseFloat((v || '0').replace(/\./g, '').replace(',', '.')) || 0;
    }

    // **El total de la compra, mientras se carga.** Antes había que sumar las
    // filas de cabeza para saber si coincidía con la factura del proveedor:
    // el error se descubría recién al pagar.
    function recalcular() {
        var total = 0, renglones = 0;
        document.querySelectorAll('.filaCompra').forEach(function (f) {
            var c = aNumero(f.querySelector('[name="cantidad[]"]').value);
            var pr = aNumero(f.querySelector('[name="precio[]"]').value);
            if (c > 0 && pr > 0) { renglones++; }
            total += c * pr;
        });
        var t = document.getElementById('compraTotal');
        var l = document.getElementById('compraLineas');
        if (t) { t.textContent = 'Gs. ' + miles(total); }
        if (l) { l.textContent = renglones + (renglones === 1 ? ' renglón' : ' renglones'); }
    }

    function enganchar(fila) {
        var campo = fila.querySelector('.nombreProd'),
            oculto = fila.querySelector('.idProd'),
            precio = fila.querySelector('.precioProd');

        function resolver() {
            var op = opcionDe(campo.value);
            oculto.value = op ? op.dataset.id : 0;

            // **El precio del catálogo se trae, pero no se impone.** Es lo
            // último que se le pagó a un proveedor, no un valor fijo: si el
            // proveedor subió, lo que vale es la factura de hoy. Por eso sólo
            // se completa cuando el campo está vacío — lo que ya se escribió
            // no se pisa.
            if (op && precio && precio.value.trim() === '' && op.dataset.precio) {
                precio.value = miles(parseFloat(op.dataset.precio));
            }
            recalcular();
        }

        campo.addEventListener('input', resolver);
        campo.addEventListener('change', resolver);
        fila.querySelectorAll('[name="cantidad[]"], [name="precio[]"]').forEach(function (i) {
            i.addEventListener('input', recalcular);
        });
    }

    document.querySelectorAll('.filaCompra').forEach(enganchar);
    recalcular();

    document.getElementById('masFilas').addEventListener('click', function () {
        var cont = document.getElementById('filasCompra');
        var copia = cont.querySelector('.filaCompra').cloneNode(true);
        copia.querySelectorAll('input').forEach(function (i) { i.value = i.classList.contains('idProd') ? '0' : ''; });
        cont.appendChild(copia);
        enganchar(copia);
        recalcular();
    });
})();
</script>
@endpush
