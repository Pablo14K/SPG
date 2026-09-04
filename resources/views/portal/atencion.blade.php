@extends('layout.app')

@section('titulo', 'Mi atención')

@section('contenido')
    <div class="spg-page-head">
        <h1>Tu atención</h1>
        <div class="sub">
            Con {{ $cita->profesional }} · {{ fecha($cita->fecha_hora) }}
            <span id="estadoBadge">{!! estado_badge($cita->estado) !!}</span>
        </div>
    </div>

    {{-- El total es lo cargado hasta ahora, no un comprobante: el comprobante
         lo emite el salón al terminar. --}}
    <div class="spg-metrics mb-3">
        <div class="spg-metric">
            <div class="lbl">Va sumando</div>
            <div class="val oro" id="mTotal">{{ money($total) }}</div>
        </div>
        @if ($sena > 0)
            <div class="spg-metric">
                <div class="lbl">Ya señaste</div>
                <div class="val" id="mSena">{{ money($sena) }}</div>
            </div>
        @endif
        <div class="spg-metric">
            <div class="lbl">Te quedaría por pagar</div>
            <div class="val" id="mPagar">{{ money($aPagar) }}</div>
        </div>
    </div>

    <div class="spg-panel mb-3">
        <h2 class="spg-form-titulo mb-2"><i class="bi bi-scissors"></i> Lo que te están haciendo</h2>
        <div id="listaServicios">
            @foreach ($servicios as $s)
                <div class="d-flex justify-content-between align-items-center py-1">
                    <div>
                        {{ $s->nombre }}
                        <span class="text-muted-warm">· {{ $s->quien }}</span>
                        @if ($s->hecho)<span class="badge-estado e-ok">listo</span>@endif
                    </div>
                    <strong>{{ money($s->precio) }}</strong>
                </div>
            @endforeach
        </div>
    </div>

    @if ($productos)
        <div class="spg-panel mb-3">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-box-seam"></i> Productos que te están usando</h2>
            <div id="listaProductos">
                @foreach ($productos as $p)
                    <div class="d-flex justify-content-between py-1">
                        <span>{{ $p->nombre }}</span>
                        <span class="text-muted-warm">
                            {{ cant(producto_fraccionado((array) $p)
                                ? stock_a_consumo((array) $p, (float) $p->cantidad)
                                : (float) $p->cantidad) }}
                            {{ unidad_consumo((array) $p) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($enCurso)
        <div class="spg-panel">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-chat-dots"></i> ¿Querés agregar algo?<x-ayuda>Pedilo acá y quien te está atendiendo lo va a ver. Te lo confirma en el momento, porque depende de si hay tiempo y producto.</x-ayuda></h2>
            <button class="btn btn-oro" data-bs-toggle="modal" data-bs-target="#modalPedir">
                <i class="bi bi-plus-lg"></i> Pedir algo más</button>

            @if ($pedidos)
                <div class="mt-3">
                    <div class="text-muted-warm" style="font-size:.8rem">Ya pediste:</div>
                    @foreach ($pedidos as $p)
                        <div style="font-size:.85rem">· {{ $p->observaciones }}</div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="modal fade" id="modalPedir" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="{{ route('portal.pedir') }}">
                        @csrf
                        <input type="hidden" name="id_cita" value="{{ $cita->id_cita }}">
                        <div class="modal-header">
                            <h5 class="modal-title" style="font-size:1rem">Pedir algo más</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-warning" style="font-size:.85rem">
                                Tené en cuenta que agregar un servicio <strong>aumenta el costo</strong> de tu
                                atención. Quien te atiende te va a confirmar el precio y el tiempo.
                            </div>
                            {{-- **Lo que se puede pedir de verdad**, no un campo en
                                 blanco: sólo lo que se ofrece en este local y que
                                 alguna de las personas que te está atendiendo hace.
                                 Pidiendo a mano, el «no» llegaba después y en el
                                 sillón. --}}
                            @if (($puedePedir ?? []) !== [])
                                <label class="form-label">¿Qué te gustaría agregar?</label>
                                <div class="mb-2" style="max-height:220px;overflow-y:auto">
                                    @foreach ($puedePedir as $sv)
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="pedido"
                                                   id="pd{{ $sv->id_servicio }}" required
                                                   value="{{ $sv->nombre }} ({{ money($sv->precio) }})">
                                            <label class="form-check-label" for="pd{{ $sv->id_servicio }}">
                                                {{ $sv->nombre }}
                                                <span class="text-muted-warm">
                                                    · {{ money($sv->precio) }} · {{ (int) $sv->duracion_min }} min</span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                {{-- Sin nada que ofrecerle, el campo libre sigue siendo
                                     la salida: no se le cierra la puerta a preguntar. --}}
                                <label class="form-label" for="pedido">¿Qué te gustaría agregar?</label>
                                <textarea class="form-control" id="pedido" name="pedido" rows="3" required
                                          maxlength="300" placeholder="Ej. ¿me podés hacer las uñas también?"></textarea>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-oro">Enviar el pedido</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- **Cómo terminó el pago.** La pantalla se cortaba con la atención, así
         que la clienta veía el detalle mientras la atendían y después se
         quedaba sin saber si el cobro entró ni con qué comprobante — que es
         justo lo que va a querer mirar si algo no cuadra. --}}
    @if ($comprobante || (float) $cobrado > 0)
        <div class="spg-panel mt-3">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-receipt"></i> Tu pago</h2>
            <table class="table table-sm mb-0" style="font-size:.9rem">
                @if ($comprobante)
                    <tr>
                        <td>{{ $comprobante->tipo }}</td>
                        <td class="text-end"><strong>{{ $comprobante->nro }}</strong></td>
                    </tr>
                    <tr><td>Total</td><td class="text-end">{{ money($comprobante->total) }}</td></tr>
                @endif
                <tr><td>Pagado</td><td class="text-end txt-ok">{{ money($cobrado) }}</td></tr>
                @if ($comprobante && (float) $comprobante->saldo > 0.01)
                    <tr>
                        <td><strong>Falta</strong></td>
                        <td class="text-end"><strong class="txt-no">{{ money($comprobante->saldo) }}</strong></td>
                    </tr>
                @elseif ($comprobante)
                    <tr><td colspan="2" class="txt-ok">Está todo pago. ¡Gracias!</td></tr>
                @endif
            </table>
            @unless ($comprobante)
                <p class="text-muted-warm mb-0 mt-2" style="font-size:.82rem">
                    El comprobante todavía no se emitió. Lo vas a ver acá apenas el salón lo haga.
                </p>
            @endunless
        </div>
    @endif

@endsection

@push('scripts')
<script>
// Se refresca sola cada 20 segundos, pero NO con la pestaña en segundo plano:
// no tiene sentido gastarle los datos del celular por una pantalla que nadie
// está mirando. Es una consulta chica; a este ritmo alcanza y no hacen falta
// websockets.
(function () {
    var url = @json(route('portal.atencion_json', ['id' => $cita->id_cita]));
    var estadoActual = @json($cita->estado);

    setInterval(function () {
        if (document.hidden) { return; }

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) { return; }

                // Si cambió el estado, se recarga la pantalla entera: cambian
                // también los botones, no solo los números.
                if (d.estado !== estadoActual) { window.location.reload(); return; }

                document.getElementById('mTotal').textContent = d.total;
                document.getElementById('mPagar').textContent = d.aPagar;

                var cont = document.getElementById('listaServicios');
                cont.innerHTML = d.servicios.map(function (s) {
                    return '<div class="d-flex justify-content-between align-items-center py-1"><div>'
                        + s.nombre + ' <span class="text-muted-warm">· ' + s.quien + '</span>'
                        + (s.hecho ? ' <span class="badge-estado e-ok">listo</span>' : '')
                        + '</div><strong>' + s.precio + '</strong></div>';
                }).join('');
            })
            .catch(function () { /* si falla, se reintenta en la próxima vuelta */ });
    }, 20000);
})();
</script>
@endpush
