@extends('layout.app')

@section('titulo', 'Registrar atención')

@section('contenido')
@php
    // **Con la cita ya atendida la pantalla es un detalle, no un formulario.**
    // El candado por factura emitida existía desde antes, pero es más tarde:
    // entre atender y facturar quedaba una ventana en la que se podían marcar
    // servicios nuevos sobre una atención terminada.
    //
    // Se reusa `$factura` en vez de repetir la condición en veinte `@disabled`:
    // los dos casos significan lo mismo —esto ya no se toca— y con dos
    // variables, la próxima que se agregue se olvida en la mitad de los campos.
    $factura = $factura ?? null;
    if (($soloLectura ?? false) && ! $factura) {
        $factura = (object) ['id_factura' => 0, 'nro' => null, 'solo_lectura' => true];
    }
@endphp
    @php use App\Servicios\Navegacion; @endphp

    <x-encabezado :sub="'Cita de <strong>' . e($cita->cliente) . '</strong> con ' . e($cita->profesional)
                        . ' · ' . e(fecha($cita->fecha_hora))" />

    {{-- El fichaje se avisa ACÁ, antes de que la persona cargue todo y le
         rebote al guardar. Y se distinguen los dos casos, que antes salían con
         el mismo texto: si la cita todavía no llegó no falta fichar, faltan
         días, y mandarla a Asistencia era mandarla a un rechazo seguro. --}}
    @if (! $fichaje['ok'])
        <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
            @if ($fichaje['futura'])
                <span>
                    Esta cita es del <strong>{{ fecha($fichaje['dia'], 'd/m/Y') }}</strong>: todavía no llegó ese día.
                    Vas a poder registrar la atención cuando se atienda.
                </span>
            @elseif ($fichaje['turno'])
                <span>
                    <strong>{{ $cita->profesional }}</strong> todavía no marcó su entrada de hoy, y sin eso no se
                    puede registrar la atención: la comisión se le liquidaría a alguien que no figura trabajando.
                </span>
                <form method="post" action="{{ route('seguridad.asistencia.marcar') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="accion" value="entrada">
                    <input type="hidden" name="id_usuario" value="{{ $cita->id_usuario }}">
                    <input type="hidden" name="id_turno" value="{{ $fichaje['turno']->id_turno }}">
                    <input type="hidden" name="fecha" value="{{ $fichaje['dia'] }}">
                    {{-- Vuelve acá en vez de a Asistencia: el trabajo estaba acá --}}
                    <input type="hidden" name="volver_cita" value="{{ $cita->id_cita }}">
                    <button class="btn btn-sm btn-oro">
                        <i class="bi bi-box-arrow-in-right"></i>
                        Marcar entrada ({{ substr((string) $fichaje['turno']->hora_inicio, 0, 5) }}
                        a {{ substr((string) $fichaje['turno']->hora_fin, 0, 5) }})</button>
                </form>
            @else
                <span>
                    <strong>{{ $cita->profesional }}</strong> no tiene marcada la entrada del
                    <strong>{{ fecha($fichaje['dia'], 'd/m/Y') }}</strong>. Como es un día que ya pasó, no se ficha:
                    se corrige la planilla en <strong>Seguridad → Asistencia</strong>.
                </span>
            @endif
        </div>
    @endif

    @if ($factura && ! ($factura->solo_lectura ?? false))
        <div class="alert alert-warning">
            Esta cita ya fue facturada con el comprobante <strong>{{ $factura->nro }}</strong>.
            No se le pueden agregar más servicios ni productos: la factura quedaría corta.
            @if ($url = Navegacion::url('facturacion.factura_ver'))
                <a class="link-oro" href="{{ $url . '?id=' . $factura->id_factura }}">Ver el comprobante</a>
            @endif
        </div>
    @elseif ($factura)
        {{-- **Atendida y facturada no son lo mismo, y el aviso tiene que
             decir cuál es.** Con el texto de la factura sobre una cita que
             todavía no se facturó, se buscaba un comprobante que no existe. --}}
        <div class="alert alert-warning">
            Esta atención ya está registrada. Abajo está lo que se hizo y lo que se usó:
            es el detalle, no se puede modificar.
        </div>
    @endif

    {{-- Lo que la clienta pidió desde su celular mientras la atienden. Si no se
         muestra acá, el pedido no le llega a nadie. --}}
    @php $pendientes = array_filter($pedidos, fn ($p) => ! $p->atendido); @endphp
    @if ($pendientes)
        <div class="sgp-panel mb-3" style="border-left:3px solid var(--oro)">
            <h2 class="sgp-form-titulo mb-2"><i class="bi bi-chat-dots"></i> Pedidos de la clienta</h2>
            @foreach ($pendientes as $p)
                <div class="d-flex justify-content-between align-items-center gap-2 py-1">
                    <div>
                        {{ $p->observaciones }}
                        <div class="text-muted-warm" style="font-size:.76rem">{{ fecha($p->fecha_registro) }}</div>
                    </div>
                    <form method="post" action="{{ route('citas.pedido_visto') }}">
                        @csrf
                        <input type="hidden" name="id_pedido" value="{{ $p->id_pedido }}">
                        <button class="btn btn-sm btn-outline-neutro">Resuelto</button>
                    </form>
                </div>
            @endforeach
            <p class="text-muted-warm mb-0 mt-1" style="font-size:.78rem">
                Un pedido no agrega nada a la cuenta por sí solo: si se puede hacer, cargalo abajo como servicio.
            </p>
        </div>
    @endif

    <form method="post" action="{{ route('citas.atender.guardar') }}" id="formAtencion">
        @csrf
        <input type="hidden" name="id_cita" value="{{ $cita->id_cita }}">
        <input type="hidden" name="dia" value="{{ substr((string) $cita->fecha_hora, 0, 10) }}">

        {{-- 1. Servicios realizados --}}
        <div class="sgp-panel mb-3">
            <h2 class="sgp-form-titulo mb-1"><i class="bi bi-scissors"></i> ¿Qué se le hizo?<x-ayuda>Vienen marcados los que se habían agendado. Lo que quede sin marcar y no se haya realizado antes se saca de la cita, así no se le cobra a la clienta un servicio que no recibió.</x-ayuda></h2>

            <input class="form-control form-control-sm mb-2" data-filtra="#listaServiciosAt"
                   placeholder="Buscar un servicio…" autocomplete="off">

            {{-- **Lo que la clienta pidio, separado de lo que se le suma en el
                 sillon.** Estaban todos en una sola lista, asi que para saber que
                 se habia agendado habia que leer los badges uno por uno. Son dos
                 cosas distintas: lo agendado es lo que se acordo, lo demas es un
                 agregado que se decide en el momento y que la clienta no esta
                 esperando pagar. --}}
            @php
                $pedidos = collect($servicios)->filter(fn ($x) => $x->agendado || $x->ya);
                $extras  = collect($servicios)->reject(fn ($x) => $x->agendado || $x->ya);
            @endphp

            {{-- **Cada profesional cierra SU parte.** Una cita de dos horas
                 repartida entre dos dejaba ocupadas dos horas a las dos: la que
                 hace la manicura termina en diez minutos y seguía sin poder
                 recibir a nadie. Al guardar, lo que se marca queda cerrado y esa
                 agenda se libera desde esa hora — la cita sigue abierta hasta que
                 cierren todas. --}}
            @if (! $soloLectura && ($faltanCerrar ?? 0) > 0)
                <div class="alert alert-warning py-2 mb-2" style="font-size:.85rem">
                    <i class="bi bi-people"></i>
                    @if ($puedeTodo)
                        Quedan <strong>{{ $faltanCerrar }}</strong> servicio(s) sin cerrar en esta cita.
                        Podés cerrar la parte de cada profesional por separado, con lo que usó cada una.
                    @else
                        Marcá <strong>lo tuyo</strong> y guardá: tu agenda queda libre desde ese momento,
                        sin esperar a que termine el resto de la cita.
                    @endif
                    <strong>Se va a poder cobrar y facturar cuando estén todos cerrados.</strong>
                </div>
            @endif

            {{-- **De quién se cierra la parte.** Sólo lo ve quien puede cerrar la
                 de cualquiera. No es un filtro de pantalla: define qué servicios
                 se dan por no realizados y salen de la cita, así que cerrar la
                 parte de una no puede llevarse lo que las demás todavía no
                 hicieron. --}}
            @if (! $soloLectura && $puedeTodo && count($abiertasDe) > 1)
                <div class="mb-2" style="max-width:340px">
                    <label class="form-label" for="cerrarDe">¿De quién estás cerrando?</label>
                    <select class="form-select form-select-sm" id="cerrarDe" name="cerrar_de">
                        <option value="0">Todas — cierro la cita entera</option>
                        @foreach ($abiertasDe as $a)
                            <option value="{{ $a->id_usuario }}">Sólo la parte de {{ $a->nombre }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">
                        Lo que quede sin marcar de esa parte sale de la cita: no se le cobra
                        a la clienta. Lo de las demás no se toca.
                    </div>
                </div>
            @endif

            <div class="sgp-check-lista" id="listaServiciosAt">
                @if ($pedidos->isNotEmpty())
                    <div class="sgp-grupo-rotulo">Lo que se agendo</div>
                    @foreach ($pedidos as $s)
                        @include('citas._servicio_check')
                    @endforeach
                @endif

                @if ($extras->isNotEmpty())
                    <div class="sgp-grupo-rotulo mt-2">Se agrega durante la atencion</div>
                    @foreach ($extras as $s)
                        @include('citas._servicio_check')
                    @endforeach
                @endif
            </div>

            {{-- **Cuánto va sumando.**

                 La pantalla mostraba el precio de cada servicio y no sumaba
                 ninguno: se agregaba una manicura en el sillón y no había un
                 número que lo dijera. Ahora se recalcula al marcar, con los
                 precios que ya viajan en el marcado (`data-precio`).

                 **Con seña la cuenta es otra**, y es donde se confunde: la
                 seña ya está cobrada y no cambia, así que agregar un servicio
                 sube el total Y sube lo que falta cobrar en la misma medida.
                 Por eso los tres renglones van juntos y no sólo el total.

                 Arranca con el número del servidor, así que sin `app.js` se
                 ve igual lo que hay marcado ahora. --}}
            @php
                $sgpTotalIni = collect($servicios)
                    ->filter(fn ($x) => $x->agendado || $x->ya)
                    ->sum(fn ($x) => (float) $x->precio);
                $sgpSena = (float) ($senaCobrada ?? 0);
            @endphp
            <div class="sgp-suma-at" id="sumaAtencion"
                 data-sena="{{ $sgpSena }}">
                <div class="sgp-suma-fila">
                    <span>Servicios marcados</span>
                    <strong data-suma="total">{{ money($sgpTotalIni) }}</strong>
                </div>
                @if ($sgpSena > 0)
                    <div class="sgp-suma-fila">
                        <span>Ya pagó de seña</span>
                        <strong class="txt-ok">− {{ money($sgpSena) }}</strong>
                    </div>
                @endif
                <div class="sgp-suma-fila sgp-suma-total">
                    <span>{{ $sgpSena > 0 ? 'Queda por cobrar' : 'A cobrar' }}</span>
                    <strong class="val oro" data-suma="cobrar">{{ money(max(0, $sgpTotalIni - $sgpSena)) }}</strong>
                </div>
            </div>
        </div>

        {{-- 2. Productos usados

             **En «Detalle» esto se MIRA, no se carga.** La 7.82.0 sacó el
             formulario de la cita ya atendida y lo hizo a medias: se aplicó a la
             lista de servicios —que pasa a mostrar sólo los realizados— y este
             bloque quedó dibujando sus tres filas vacías igual. Así que el botón
             «Detalle» abría una cita ya facturada con tres selectores en «— sin
             producto —», y lo que de verdad se usó no aparecía por ningún lado:
             se reportó exactamente así, «se ve el precio y el servicio pero no
             carga los productos utilizados».

             Con la cita cerrada, lo que corresponde es lo que se consumió. Y si
             no se cargó ninguno **se dice**, en vez de dejar el hueco: un bloque
             que desaparece no distingue «no se usó nada» de «esto se rompió». --}}
        @if ($soloLectura)
            <div class="sgp-panel mb-3">
                <h2 class="sgp-form-titulo mb-2">
                    <i class="bi bi-box-seam"></i> Productos que se usaron
                </h2>
                @if ($usados)
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Producto</th><th>Servicio</th><th class="text-end">Cantidad</th></tr></thead>
                            <tbody>
                                @foreach ($usados as $u)
                                    <tr>
                                        <td>{{ $u->nombre }}</td>
                                        <td class="text-muted-warm">{{ $u->servicio }}</td>
                                        <td class="text-end">
                                            {{ cant(stock_a_consumo((array) $u, (float) $u->cantidad)) }}
                                            {{ unidad_consumo((array) $u) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="sgp-vacio">
                        <i class="bi bi-box-seam"></i>
                        <div class="t">No se cargó ningún producto en esta atención.</div>
                        <div class="d">
                            Puede ser que no se haya usado nada, o que al registrarla el
                            descuento de stock no haya entrado — eso se avisa en el momento
                            y queda en el registro del sistema.
                        </div>
                    </div>
                @endif
            </div>
        @else
        <div class="sgp-panel mb-3">
            <h2 class="sgp-form-titulo mb-1"><i class="bi bi-box-seam"></i> ¿Qué productos se usaron?<x-ayuda>Cargá lo que realmente se usó: no es una cantidad fija por servicio, cambia según el pelo de cada clienta. Los productos fraccionados van en su unidad de consumo —30 ml de un frasco de 1 litro— y el sistema traduce solo lo que descuenta del stock.</x-ayuda></h2>

            {{-- **Un local que no maneja ningún producto tiene que decirlo.** El
                 catálogo es único desde la 7.33.0 y `producto_sucursal` dice qué
                 maneja cada sede, así que una sucursal recién abierta llega acá
                 con la lista vacía: tres selectores con «— sin producto —» y nada
                 más. La atención se registra igual —hay servicios que no consumen
                 nada— pero quien atiende no tiene forma de saber si es que no hay
                 productos o si es que el sistema se rompió. Es el mismo criterio
                 de IN-06: nombrar el camino en vez de dejar la pantalla muda. --}}
            @if (! count($productos))
                <div class="alert alert-warning" style="font-size:.85rem">
                    <strong>Esta sucursal todavía no maneja ningún producto</strong>, así que no
                    hay nada que descontar. La atención se registra igual.
                    @if (\App\Servicios\Permisos::puede('inventario.productos'))
                        Para habilitarlos acá andá a
                        <a class="link-oro" href="{{ route('inventario.productos') }}">Inventario → Productos</a>:
                        con el filtro <em>«Sólo en otras sucursales»</em> aparecen los que ya existen
                        en otro local y se traen con <em>«Traer acá»</em>, sin volver a cargarlos.
                    @else
                        Avisale a quien maneja el inventario para que los habilite en este local.
                    @endif
                </div>
            @endif

            <div id="filasProductos">
                @for ($i = 0; $i < 3; $i++)
                    <div class="row g-2 mb-2 filaProducto">
                        <div class="col-md-5">
                            <select class="form-select form-select-sm" name="producto[]" @disabled((bool) $factura)>
                                <option value="0">— sin producto —</option>
                                @foreach ($productos as $p)
                                    <option value="{{ $p->id_producto }}" data-unidad="{{ unidad_consumo((array) $p) }}">
                                        {{ $p->nombre }}
                                        (quedan {{ cant(stock_a_consumo((array) $p, (float) $p->stock)) }}
                                        {{ unidad_consumo((array) $p) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            {{-- La unidad se muestra al lado del campo: sin eso no se sabe si «30»
                                 son 30 ml o 30 frascos, y el número depende del producto elegido. --}}
                            <div class="input-group input-group-sm">
                                <input class="form-control input-miles" name="cantidad[]"
                                       data-decimales="2" data-min="0" placeholder="Cantidad" @disabled((bool) $factura)>
                                <span class="input-group-text unidadProducto">unidad</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" name="servicio_de[]" @disabled((bool) $factura)>
                                {{-- **Sólo los servicios MARCADOS.** Salía el catálogo
                                     entero —quince opciones— y eso deja imputar un producto
                                     a un servicio que la clienta no recibió: el consumo
                                     queda colgado de algo que no ocurrió, y el servidor lo
                                     rechaza al guardar con un mensaje que manda a mirar el
                                     lugar equivocado.

                                     Entran los dos casos: lo que se agendó y lo que se
                                     agrega en el sillón. El comentario de antes decía que
                                     `app.js` los escondía y **no lo hacía nadie** — la lista
                                     se dibujaba completa. Ahora lo hace el bloque de abajo,
                                     que además reacciona al marcar y desmarcar.

                                     Sin JavaScript se ven todos y se puede imputar igual:
                                     es una ayuda, no el control. --}}
                                <option value="0">— imputar al primer servicio —</option>
                                @foreach ($servicios as $sc)
                                    <option value="{{ $sc->id_servicio }}" data-srv="{{ $sc->id_servicio }}">
                                        en {{ $sc->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        {{-- **Había para agregar y no para quitar.** Una fila cargada por
                             error sólo se deshacía volviendo el combo a «sin producto» y
                             borrando la cantidad a mano, y con la fila ya elegida eso no
                             se lee como «borrar». --}}
                        <div class="col-md-1 d-flex align-items-center">
                            <button type="button" class="btn btn-sm btn-outline-neutro quitaProducto w-100"
                                    title="Quitar este producto" @disabled((bool) $factura)><i class="bi bi-x-lg"></i></button>
                        </div>
                    </div>
                @endfor
            </div>

            <button type="button" class="btn btn-sm btn-rapido" id="masProductos" @disabled((bool) $factura)>
                <i class="bi bi-plus-lg"></i> Otra fila
            </button>
        </div>
        @endif

        {{-- 3. Observaciones --}}
        <div class="sgp-panel mb-3">
            <label class="form-label" for="observaciones">Observaciones de la atención</label><x-ayuda campo="observaciones" />
            <textarea class="form-control" id="observaciones" name="observaciones" rows="2"
                      @disabled((bool) $factura)></textarea>
        </div>

        <div class="d-flex gap-2">
            @unless ($soloLectura)
            <button class="btn btn-oro" @disabled((bool) $factura)
                    data-confirmar="Al registrar la atención, la cita queda ATENDIDA y el stock de los productos se descuenta. ¿Confirmás?">
                <i class="bi bi-clipboard-check"></i> Registrar atención
            </button>
            @endunless
            <a class="btn btn-outline-neutro" href="{{ route('citas.agenda') }}">Volver a la agenda</a>
        </div>
    </form>

    {{-- Con la cita cerrada esto ya se muestra arriba: repetirlo sería la misma
         tabla dos veces en la misma pantalla. --}}
    @if ($usados && ! $soloLectura)
        <div class="sgp-panel mt-3">
            <h2 class="sgp-form-titulo mb-2"><i class="bi bi-clock-history"></i> Productos ya cargados en esta cita</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Producto</th><th>Servicio</th><th class="text-end">Cantidad</th></tr></thead>
                    <tbody>
                        @foreach ($usados as $u)
                            <tr>
                                <td>{{ $u->nombre }}</td>
                                <td class="text-muted-warm">{{ $u->servicio }}</td>
                                <td class="text-end">
                                    {{ cant(stock_a_consumo((array) $u, (float) $u->cantidad)) }}
                                    {{ unidad_consumo((array) $u) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
// ---------------------------------------------------------------------------
// «¿En qué servicio se usó?» ofrece sólo los servicios marcados
// ---------------------------------------------------------------------------
// El catálogo entero deja imputar un producto a un servicio que la clienta no
// recibió, y ahí el consumo queda colgado de algo que no ocurrió.
//
// Se recalcula al marcar y desmarcar, y también sobre las filas que agrega el
// botón «Otra fila»: por eso se recorre el documento cada vez en vez de
// guardarse la lista.
(function () {
    function refrescarServiciosDe() {
        var marcados = {};
        document.querySelectorAll('.srvAt').forEach(function (chk) {
            if (chk.checked) marcados[chk.value] = true;
        });

        document.querySelectorAll('[name="servicio_de[]"]').forEach(function (sel) {
            var visibles = 0;
            sel.querySelectorAll('option[data-srv]').forEach(function (op) {
                var ok = !!marcados[op.dataset.srv];
                op.hidden = !ok;
                op.disabled = !ok;
                if (ok) visibles++;
                // Si la opción elegida deja de estar marcada, se vuelve al
                // primer servicio: dejarla seleccionada y escondida mandaría
                // el id igual y el servidor lo rechazaría al guardar.
                if (!ok && sel.value === op.value) sel.value = '0';
            });
            // Sin ningún servicio marcado no hay nada que imputar: el combo
            // queda con su única opción y no engaña.
            sel.disabled = sel.disabled || visibles === 0 ? sel.disabled : false;
        });
    }

    document.addEventListener('change', function (ev) {
        if (ev.target.classList && ev.target.classList.contains('srvAt')) refrescarServiciosDe();
    });
    document.addEventListener('click', function (ev) {
        if (ev.target.closest && ev.target.closest('#masProductos')) {
            // La fila nueva se clona después del clic.
            setTimeout(refrescarServiciosDe, 0);
        }
    });

    refrescarServiciosDe();
})();

// La unidad del campo depende del producto elegido: «ml» para los fraccionados
// y la unidad de compra para el resto. Se actualiza sola al cambiar el select.
function sgpUnidad(fila) {
    var sel = fila.querySelector('select[name="producto[]"]');
    var eti = fila.querySelector('.unidadProducto');
    if (!sel || !eti) { return; }
    var op = sel.options[sel.selectedIndex];
    eti.textContent = (op && op.dataset.unidad) ? op.dataset.unidad : 'unidad';
}

document.getElementById('filasProductos')?.addEventListener('change', function (e) {
    if (e.target.name === 'producto[]') { sgpUnidad(e.target.closest('.filaProducto')); }
});

// **Nunca se queda sin ninguna fila**: con cero, «Otra fila» clona algo que ya
// no existe y el botón deja de funcionar. La última se vacía en vez de irse.
document.getElementById('filasProductos')?.addEventListener('click', function (e) {
    var b = e.target.closest('.quitaProducto');
    if (!b) { return; }
    var cont = document.getElementById('filasProductos');
    var fila = b.closest('.filaProducto');
    if (cont.querySelectorAll('.filaProducto').length > 1) { fila.remove(); return; }
    fila.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
    fila.querySelectorAll('input').forEach(function (i) { i.value = ''; });
    sgpUnidad(fila);
});

// Una fila más para cargar productos, clonando la última vacía
document.getElementById('masProductos')?.addEventListener('click', function () {
    var cont = document.getElementById('filasProductos');
    var copia = cont.querySelector('.filaProducto').cloneNode(true);
    copia.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
    copia.querySelectorAll('input').forEach(function (i) { i.value = ''; });
    cont.appendChild(copia);
    sgpUnidad(copia);
});
</script>
@endpush
