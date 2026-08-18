@extends('layout.app')

@section('titulo', 'Registrar atención')

@section('contenido')
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

    @if ($factura)
        <div class="alert alert-warning">
            Esta cita ya fue facturada con el comprobante <strong>{{ $factura->nro }}</strong>.
            No se le pueden agregar más servicios ni productos: la factura quedaría corta.
            @if ($url = Navegacion::url('facturacion.factura_ver'))
                <a class="link-oro" href="{{ $url . '?id=' . $factura->id_factura }}">Ver el comprobante</a>
            @endif
        </div>
    @endif

    {{-- Lo que la clienta pidió desde su celular mientras la atienden. Si no se
         muestra acá, el pedido no le llega a nadie. --}}
    @php $pendientes = array_filter($pedidos, fn ($p) => ! $p->atendido); @endphp
    @if ($pendientes)
        <div class="spg-panel mb-3" style="border-left:3px solid var(--oro)">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-chat-dots"></i> Pedidos de la clienta</h2>
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
        <div class="spg-panel mb-3">
            <h2 class="spg-form-titulo mb-1"><i class="bi bi-scissors"></i> ¿Qué se le hizo?</h2>
            <p class="text-muted-warm mb-2" style="font-size:.8rem">
                Vienen marcados los que se habían agendado. <strong>Lo que quede sin marcar y no se
                haya realizado antes se saca de la cita</strong>, así no se le cobra a la clienta un
                servicio que no recibió.
            </p>

            <input class="form-control form-control-sm mb-2" data-filtra="#listaServiciosAt"
                   placeholder="Buscar un servicio…" autocomplete="off">

            <div class="spg-check-lista" id="listaServiciosAt">
                @foreach ($servicios as $s)
                    <div class="form-check">
                        <input class="form-check-input srvAt" type="checkbox" name="servicios[]"
                               value="{{ $s->id_servicio }}" id="sa{{ $s->id_servicio }}"
                               data-nombre="{{ $s->nombre }}"
                               @checked($s->agendado || $s->ya) @disabled((bool) $factura)>
                        <label class="form-check-label" for="sa{{ $s->id_servicio }}">
                            {{ $s->nombre }}
                            <span class="text-muted-warm">· {{ money($s->precio) }} · {{ $s->categoria }}</span>
                            @if ($s->ya)<span class="badge-estado e-ok">ya registrado</span>
                            @elseif ($s->agendado)<span class="badge-estado e-prog">agendado</span>@endif
                        </label>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- 2. Productos usados --}}
        <div class="spg-panel mb-3">
            <h2 class="spg-form-titulo mb-1"><i class="bi bi-box-seam"></i> ¿Qué productos se usaron?</h2>
            <p class="text-muted-warm mb-2" style="font-size:.8rem">
                Cargá <strong>lo que realmente se usó</strong>: no es una cantidad fija por servicio, cambia
                según el pelo de cada clienta. Los productos fraccionados van en su unidad de consumo
                —30 ml de un frasco de 1 litro— y el sistema traduce solo lo que descuenta del stock.
            </p>

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
                        <div class="col-md-4">
                            <select class="form-select form-select-sm" name="servicio_de[]" @disabled((bool) $factura)>
                                <option value="0">— imputar al primer servicio —</option>
                                @foreach ($servicios as $s)
                                    <option value="{{ $s->id_servicio }}">en {{ $s->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endfor
            </div>

            <button type="button" class="btn btn-sm btn-rapido" id="masProductos" @disabled((bool) $factura)>
                <i class="bi bi-plus-lg"></i> Otra fila
            </button>
        </div>

        {{-- 3. Observaciones --}}
        <div class="spg-panel mb-3">
            <label class="form-label" for="observaciones">Observaciones de la atención</label>
            <textarea class="form-control" id="observaciones" name="observaciones" rows="2"
                      @disabled((bool) $factura)></textarea>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-oro" @disabled((bool) $factura)
                    data-confirmar="Al registrar la atención, la cita queda ATENDIDA y el stock de los productos se descuenta. ¿Confirmás?">
                <i class="bi bi-clipboard-check"></i> Registrar atención
            </button>
            <a class="btn btn-outline-neutro" href="{{ route('citas.agenda') }}">Volver a la agenda</a>
        </div>
    </form>

    @if ($usados)
        <div class="spg-panel mt-3">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-clock-history"></i> Productos ya cargados en esta cita</h2>
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
// La unidad del campo depende del producto elegido: «ml» para los fraccionados
// y la unidad de compra para el resto. Se actualiza sola al cambiar el select.
function spgUnidad(fila) {
    var sel = fila.querySelector('select[name="producto[]"]');
    var eti = fila.querySelector('.unidadProducto');
    if (!sel || !eti) { return; }
    var op = sel.options[sel.selectedIndex];
    eti.textContent = (op && op.dataset.unidad) ? op.dataset.unidad : 'unidad';
}

document.getElementById('filasProductos')?.addEventListener('change', function (e) {
    if (e.target.name === 'producto[]') { spgUnidad(e.target.closest('.filaProducto')); }
});

// Una fila más para cargar productos, clonando la última vacía
document.getElementById('masProductos')?.addEventListener('click', function () {
    var cont = document.getElementById('filasProductos');
    var copia = cont.querySelector('.filaProducto').cloneNode(true);
    copia.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
    copia.querySelectorAll('input').forEach(function (i) { i.value = ''; });
    cont.appendChild(copia);
    spgUnidad(copia);
});
</script>
@endpush
