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
                            <label class="form-label" for="id_proveedor">Proveedor *</label><x-ayuda campo="id_proveedor" />
                            <select class="form-select" id="id_proveedor" name="id_proveedor" required>
                                <option value="">— elegí un proveedor —</option>
                                @foreach ($proveedores as $p)
                                    <option value="{{ $p->id_proveedor }}"
                                        @selected((int) old('id_proveedor', $sel_proveedor) === (int) $p->id_proveedor)>{{ $p->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="id_condicion_venta">Condición *</label><x-ayuda campo="id_condicion_venta" />
                            <select class="form-select" id="id_condicion_venta" name="id_condicion_venta" required>
                                @foreach ($condiciones as $cv)
                                    <option value="{{ $cv->id_condicion_venta }}"
                                        data-dias="{{ (int) $cv->dias_credito }}"
                                        @selected((int) old('id_condicion_venta', 0) === (int) $cv->id_condicion_venta)>{{ $cv->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="nro_factura_proveedor">Nº de factura</label><x-ayuda campo="nro_factura_proveedor" />
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
                        <h2 class="spg-form-titulo mb-1"><i class="bi bi-calendar2-week"></i> ¿En cuántas cuotas?<x-ayuda>Se reparte el total en partes iguales y se propone una fecha por mes. Cambiá lo que haga falta: no todos los proveedores cobran igual.</x-ayuda></h2>

                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-auto">
                                <label class="form-label" for="cantCuotas">Cuotas</label><x-ayuda campo="cantCuotas" />
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

                    <h2 class="spg-form-titulo mb-1"><i class="bi bi-list-ul"></i> Productos<x-ayuda>Elegí el producto de la lista siempre que exista. Si escribís el nombre a mano y ya estaba cargado —aunque sea con un espacio de más—, el sistema lo reconoce y le suma el stock en vez de crear un duplicado.</x-ayuda></h2>

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

                        // **Se llega desde «Registrar la compra» de Stock con la
                        // lista de faltantes.** El botón está debajo de esa lista:
                        // quien lo aprieta espera encontrarlos puestos, no volver a
                        // tipear uno por uno lo que la pantalla acaba de calcular.
                        //
                        // Sólo si el formulario no viene de un rechazo: ahí manda lo
                        // que la persona había cargado.
                        if (! $vNombre && ! empty($reponer)) {
                            foreach ($reponer as $r) {
                                $vNombre[] = $r->nombre;
                                $vId[] = (int) $r->id_producto;
                                $vCant[] = cant($r->faltante);
                                $vPrecio[] = monto_input($r->precio_costo);
                            }
                        }

                        $cuantas = max(3, count($vNombre));
                    @endphp

                    @if (! empty($reponer) && ! (array) old('nombre', []))
                        <div class="alert alert-warning py-2" style="font-size:.85rem">
                            <i class="bi bi-cart-check"></i>
                            Cargamos los {{ count($reponer) }} productos que están por debajo
                            del mínimo, con la cantidad que falta y el último precio de costo.
                            Corregí lo que haga falta antes de guardar.
                        </div>
                    @endif
                    {{-- Los rótulos de las columnas: con cinco campos por fila
                         y sólo placeholders, hay que adivinar cuál es cuál. --}}
                    {{-- **El subtotal necesita su propia columna.** Con `col-md-1`
                         compartía lugar con el botón de quitar, así que el número se
                         derramaba sobre el combo de categoría y los dos se leían como
                         uno solo. 4+2+2+2+2 = 12. --}}
                    <div class="row g-2 mb-1 text-muted-warm d-none d-md-flex" style="font-size:.78rem">
                        <div class="col-md-4">Producto</div>
                        <div class="col-md-2">Cantidad</div>
                        <div class="col-md-2">Precio unitario</div>
                        <div class="col-md-2">Categoría</div>
                        <div class="col-md-2 text-end">Subtotal</div>
                    </div>

                    <div id="filasCompra">
                        @for ($i = 0; $i < $cuantas; $i++)
                            <div class="row g-2 mb-2 filaCompra">
                                <div class="col-md-4">
                                    {{-- **La lupa abre el catálogo con el stock a la
                                         vista.** El `datalist` sugiere por nombre, que
                                         sirve cuando ya se sabe qué se busca; para
                                         decidir QUÉ comprar hace falta ver cuánto hay y
                                         cuánto tendría que haber, y eso obligaba a abrir
                                         Inventario en otra pestaña y volver. --}}
                                    <div class="input-group input-group-sm">
                                        <button type="button" class="btn btn-outline-neutro spg-buscar-prod"
                                                data-bs-toggle="modal" data-bs-target="#modalBuscarProd"
                                                title="Buscar en el catálogo" aria-label="Buscar en el catálogo">
                                            <i class="bi bi-search"></i></button>
                                        <input class="form-control form-control-sm nombreProd" name="nombre[]"
                                               list="listaProductos" placeholder="Producto" autocomplete="off"
                                               value="{{ $vNombre[$i] ?? '' }}">
                                    </div>
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
                                <div class="col-md-2">
                                    <select class="form-select form-select-sm" name="categoria[]">
                                        @foreach ($categorias as $c)
                                            <option value="{{ $c->id_categoria }}"
                                                @selected((int) ($vCat[$i] ?? 0) === (int) $c->id_categoria)>{{ $c->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                {{-- **El subtotal de la fila.** Sin él hay que
                                     multiplicar de cabeza para saber si un
                                     renglón está bien cargado, y el error
                                     aparece recién en el total. --}}
                                <div class="col-md-2 d-flex align-items-center justify-content-end gap-2">
                                    <span class="subtotalFila text-muted-warm text-nowrap" style="font-size:.85rem">—</span>
                                    {{-- **Quitar la fila.** Sin esto, una fila cargada
                                         por error sólo se podía «borrar» vaciando sus
                                         tres campos a mano — y si quedaba algo, el
                                         renglón entraba a la compra. --}}
                                    <button type="button" class="btn btn-sm btn-outline-neutro spg-quitar-fila"
                                            title="Quitar este renglón" aria-label="Quitar este renglón">
                                        <i class="bi bi-x-lg"></i></button>
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

                    {{-- **El buscador del catálogo.** Uno solo para todas las
                         filas: la lupa que se apretó queda anotada y ahí se
                         vuelcan el nombre, el id y el último precio. Dieciséis
                         modales iguales —uno por fila— serían el mismo HTML
                         repetido y un `id` distinto por renglón. --}}
                    <div class="modal fade" id="modalBuscarProd" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h2 class="modal-title fs-5">
                                        <i class="bi bi-search"></i> Buscar en el catálogo</h2>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Cerrar"></button>
                                </div>
                                <div class="modal-body">
                                    <input class="form-control mb-2" id="filtroProd" data-filtra="#tablaProd"
                                           placeholder="Nombre o categoría" autocomplete="off">
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0" id="tablaProd"
                                               style="font-size:.86rem">
                                            <thead>
                                                <tr>
                                                    <th>Producto</th>
                                                    <th class="text-end">Hay</th>
                                                    <th class="text-end">Mínimo</th>
                                                    <th class="text-end">Último precio</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($productos as $p)
                                                    @php $falta = (float) $p->hay < (float) $p->minimo; @endphp
                                                    <tr>
                                                        <td>
                                                            {{ $p->nombre }}
                                                            <div class="text-muted-warm" style="font-size:.78rem">
                                                                {{ $p->categoria }}</div>
                                                        </td>
                                                        <td class="text-end {{ $falta ? 'txt-no' : '' }}">
                                                            {{ cant($p->hay) }}</td>
                                                        <td class="text-end text-muted-warm">{{ cant($p->minimo) }}</td>
                                                        <td class="text-end text-muted-warm">
                                                            {{ $p->ultimo_precio !== null ? money($p->ultimo_precio) : '—' }}</td>
                                                        <td class="text-end">
                                                            <button type="button" class="btn btn-sm btn-oro spg-elegir-prod"
                                                                    data-bs-dismiss="modal"
                                                                    data-id="{{ $p->id_producto }}"
                                                                    data-nombre="{{ $p->nombre }}"
                                                                    data-precio="{{ $p->ultimo_precio !== null ? (int) $p->ultimo_precio : '' }}"
                                                                    data-falta="{{ $falta ? (int) ceil((float) $p->minimo - (float) $p->hay) : '' }}">
                                                                Elegir</button>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
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
                        <label class="form-label" for="observaciones">Observaciones</label><x-ayuda campo="observaciones" />
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
                    <h2 class="spg-form-titulo mb-2"><i class="bi bi-truck"></i> ¿El proveedor no está cargado?<x-ayuda>Crealo acá mismo, sin perder las líneas que ya escribiste.</x-ayuda></h2>
                    {{-- data-borrador: las filas de la compra ya cargadas
                         vuelven con el redirect. --}}
                    <form method="post" action="{{ route('inventario.proveedor.rapido') }}"
                          data-borrador="#formCompra">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label" for="pv_nombre">Nombre o razón social *</label><x-ayuda campo="pv_nombre" />
                            <input class="form-control form-control-sm" id="pv_nombre" name="nombre" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="pv_ruc">RUC</label><x-ayuda campo="pv_ruc" />
                            <input class="form-control form-control-sm" id="pv_ruc" name="ruc" data-solo="ruc" inputmode="text">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label" for="pv_contacto">Contacto</label><x-ayuda campo="pv_contacto" />
                                <input class="form-control form-control-sm" id="pv_contacto" name="contacto">
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="pv_telefono">Teléfono</label><x-ayuda campo="pv_telefono" />
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

            var sub = f.querySelector('.subtotalFila');
            if (sub) { sub.textContent = (c > 0 && pr > 0) ? miles(c * pr) : '—'; }
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
        // Al salir del campo también: elegir del datalist con el teclado no
        // siempre dispara `change` antes de que el foco se vaya.
        campo.addEventListener('blur', resolver);
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

    // -----------------------------------------------------------------
    // Quitar un renglón
    // -----------------------------------------------------------------
    // Se escucha en el contenedor y no fila por fila: las filas se clonan al
    // apretar «Agregar», así que un listener por botón dejaría sin efecto el
    // de las nuevas.
    //
    // **Nunca se queda sin ninguna**: con cero filas la pantalla no tiene
    // dónde cargar y el botón de agregar clona la primera, que ya no existe.
    // Si es la última, se vacía en vez de sacarse.
    document.getElementById('filasCompra').addEventListener('click', function (ev) {
        var boton = ev.target.closest('.spg-quitar-fila');
        if (!boton) return;

        var cont = this, fila = boton.closest('.filaCompra');
        if (cont.querySelectorAll('.filaCompra').length > 1) {
            fila.remove();
        } else {
            fila.querySelectorAll('input').forEach(function (i) {
                i.value = i.classList.contains('idProd') ? '0' : '';
            });
        }
        recalcular();
    });

    // -----------------------------------------------------------------
    // El buscador del catálogo (la lupa)
    // -----------------------------------------------------------------
    // Hay UN solo modal para todas las filas, así que hay que recordar cuál
    // lupa se apretó: sin eso, el producto elegido caería siempre en la
    // primera. `filaBuscando` es esa memoria.
    var filaBuscando = null;

    document.getElementById('filasCompra').addEventListener('click', function (ev) {
        var lupa = ev.target.closest('.spg-buscar-prod');
        if (lupa) filaBuscando = lupa.closest('.filaCompra');
    });

    document.querySelectorAll('.spg-elegir-prod').forEach(function (b) {
        b.addEventListener('click', function () {
            var fila = filaBuscando || document.querySelector('.filaCompra');
            if (!fila) return;

            fila.querySelector('.nombreProd').value = b.dataset.nombre || '';
            fila.querySelector('.idProd').value = b.dataset.id || '0';

            // El precio y la cantidad se COMPLETAN, no se imponen: lo que ya
            // se escribió a mano es lo que dice la factura de hoy.
            var precio = fila.querySelector('.precioProd');
            if (precio && precio.value.trim() === '' && b.dataset.precio) {
                precio.value = miles(parseFloat(b.dataset.precio));
            }
            var cantidad = fila.querySelector('[name="cantidad[]"]');
            if (cantidad && cantidad.value.trim() === '' && b.dataset.falta) {
                cantidad.value = b.dataset.falta;
            }
            recalcular();
        });
    });
})();
</script>
@endpush
