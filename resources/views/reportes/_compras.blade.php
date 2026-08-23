{{-- **Compras y proveedores, aparte del resto.**

     La deuda con proveedores estaba mezclada abajo de las citas y los
     servicios, y son dos cosas que no se comparan: una habla de la operación
     del salón y la otra de la plata que se le debe a un tercero.

     **La deuda no depende del período** —es lo que se debe hoy— y por eso va en
     su propio bloque, con el aviso. Las compras sí se filtran. --}}
<div class="row g-3">
    <div class="col-12">
        <div class="spg-metrics spg-metrics-compacto">
            <div class="spg-metric">
                <div class="lbl">Compras del período</div>
                <div class="val">{{ (int) ($compras->cantidad ?? 0) }}</div>
            </div>
            <div class="spg-metric">
                <div class="lbl">Total comprado</div>
                <div class="val">{{ money($compras->total ?? 0) }}</div>
            </div>
            <div class="spg-metric">
                <div class="lbl">Saldo de esas compras</div>
                <div class="val @if ((float) ($compras->saldo ?? 0) > 0) txt-no @endif">
                    {{ money($compras->saldo ?? 0) }}</div>
            </div>
            <div class="spg-metric">
                <div class="lbl">Deuda viva total</div>
                <div class="val @if ($prov) txt-no @endif">
                    @php $deuda = 0; foreach ($prov as $p) { $deuda += (float) $p->saldo; } @endphp
                    {{ money($deuda) }}</div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="spg-panel h-100">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-truck"></i> Compras por proveedor</h2>
            @if ($comprasProv)
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" data-ordenable>
                        <thead><tr><th>Proveedor</th><th class="text-end">Compras</th>
                            <th class="text-end">Total</th><th class="text-end">Saldo</th></tr></thead>
                        <tbody>
                            @foreach ($comprasProv as $p)
                                <tr>
                                    <td>{{ $p->proveedor }}</td>
                                    <td class="text-end">{{ (int) $p->compras }}</td>
                                    <td class="text-end">{{ money($p->total) }}</td>
                                    <td class="text-end @if ((float) $p->saldo > 0) txt-no @else txt-ok @endif">
                                        {{ (float) $p->saldo > 0 ? money($p->saldo) : 'pagada' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                @include('reportes._sindatos', ['ic' => 'truck',
                         'd' => 'No se registró ninguna compra en este rango.'])
            @endif
        </div>
    </div>

    <div class="col-lg-6">
        <div class="spg-panel h-100">
            <h2 class="spg-form-titulo mb-2">
                <i class="bi bi-exclamation-triangle"></i> Lo que se debe hoy
            </h2>
            <p class="text-muted-warm mb-2" style="font-size:.8rem">
                <i class="bi bi-info-circle"></i> <strong>No depende del período</strong>: es la
                deuda viva, esté la compra donde esté en el calendario.
            </p>
            @if ($prov)
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Proveedor</th><th>Comprobante</th>
                            <th>Vence</th><th class="text-end">Saldo</th></tr></thead>
                        <tbody>
                            @foreach ($prov as $p)
                                <tr>
                                    <td>{{ $p->proveedor }}</td>
                                    <td class="text-muted-warm">{{ $p->nro_factura_proveedor ?: '—' }}</td>
                                    <td>
                                        {{ $p->vencimiento ? fecha($p->vencimiento, 'd/m/Y') : '—' }}
                                        @if ($p->vencida)<span class="badge-estado e-no">vencida</span>@endif
                                    </td>
                                    <td class="text-end txt-no">{{ money($p->saldo) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                @include('reportes._sindatos', ['ic' => 'check2-circle',
                         'd' => 'No hay nada pendiente con los proveedores.'])
            @endif
        </div>
    </div>
</div>
