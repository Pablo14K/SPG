@extends('layout.app')

@section('titulo', 'Registrar atención')

@section('contenido')
    @php use App\Servicios\Navegacion; @endphp

    <x-encabezado :sub="'Cita de <strong>' . e($cita->cliente) . '</strong> con ' . e($cita->profesional)
                        . ' · ' . e(fecha($cita->fecha_hora))" />

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
                Los productos fraccionados se cargan en su unidad de consumo —30 ml de un frasco de
                1 litro— y el sistema traduce solo lo que descuenta del stock.
            </p>

            <div id="filasProductos">
                @for ($i = 0; $i < 3; $i++)
                    <div class="row g-2 mb-2 filaProducto">
                        <div class="col-md-5">
                            <select class="form-select form-select-sm" name="producto[]" @disabled((bool) $factura)>
                                <option value="0">— sin producto —</option>
                                @foreach ($productos as $p)
                                    <option value="{{ $p->id_producto }}">
                                        {{ $p->nombre }}
                                        (quedan {{ cant(stock_a_consumo((array) $p, (float) $p->stock)) }}
                                        {{ unidad_consumo((array) $p) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input class="form-control form-control-sm input-miles" name="cantidad[]"
                                   data-decimales="2" data-min="0" placeholder="Cantidad" @disabled((bool) $factura)>
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
// Una fila más para cargar productos, clonando la última vacía
document.getElementById('masProductos')?.addEventListener('click', function () {
    var cont = document.getElementById('filasProductos');
    var copia = cont.querySelector('.filaProducto').cloneNode(true);
    copia.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
    copia.querySelectorAll('input').forEach(function (i) { i.value = ''; });
    cont.appendChild(copia);
});
</script>
@endpush
