@extends('layout.app')

@section('titulo', 'Comprobante ' . $f->nro_comprobante)

@push('estilos')
    <link href="{{ recurso('css/imprimir.css') }}" rel="stylesheet" media="print">
@endpush

@section('contenido')
    @php use App\Servicios\Permisos; @endphp

    <div class="spg-page-head no-imprimir">
        <a class="spg-back" href="{{ route('facturacion.facturas') }}"><i class="bi bi-arrow-left"></i> Facturas</a>
        <h1 class="mt-1">Comprobante {{ $f->nro_comprobante }}</h1>
    </div>

    <div class="d-flex gap-2 mb-3 no-imprimir flex-wrap">
        <button class="btn btn-oro" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>

        @if ($f->estado !== 'Anulada')
            @if (Permisos::puede('facturacion.facturas'))
                <button class="btn btn-outline-neutro" data-bs-toggle="modal" data-bs-target="#modalAnularF">
                    <i class="bi bi-x-circle"></i> Anular</button>
                @if ((int) $f->signo === 1 && ! $notas)
                    <button class="btn btn-outline-neutro" data-bs-toggle="modal" data-bs-target="#modalNC">
                        <i class="bi bi-arrow-counterclockwise"></i> Nota de crédito</button>
                @endif
            @endif
        @endif
    </div>

    {{-- El comprobante. Esto es lo único que sale en papel. --}}
    <div class="spg-panel spg-comprobante">
        @if ($f->estado === 'Anulada')
            <div class="spg-sello-anulada">ANULADA</div>
        @endif

        <div class="row">
            <div class="col-md-7">
                <h2 style="font-size:1.05rem;margin-bottom:.2rem">{{ $emisor->nombre ?? config('app.name') }}</h2>
                <div class="text-muted-warm" style="font-size:.82rem">
                    @if ($emisor?->ruc)RUC {{ $emisor->ruc }}<br>@endif
                    {{ $emisor->direccion ?? '' }} {{ $emisor->ciudad ? '· ' . $emisor->ciudad : '' }}<br>
                    @if ($emisor?->telefono)Tel. {{ $emisor->telefono }}@endif
                </div>
            </div>
            <div class="col-md-5 text-md-end">
                <div style="font-size:.95rem"><strong>{{ $f->tipo_comprobante }}</strong></div>
                <div style="font-size:1.15rem;font-weight:600" class="txt-oro">{{ $f->nro_comprobante }}</div>
                <div class="text-muted-warm" style="font-size:.8rem">
                    @if ($emisor?->nro_timbrado)
                        Timbrado {{ $emisor->nro_timbrado }}<br>
                        Vigencia {{ fecha($emisor->timbrado_desde, 'd/m/Y') }} – {{ fecha($emisor->timbrado_hasta, 'd/m/Y') }}
                    @endif
                </div>
            </div>
        </div>

        <hr>

        <div class="row" style="font-size:.85rem">
            <div class="col-md-8">
                <strong>Cliente:</strong> {{ trim(($cli->nombre ?? '') . ' ' . ($cli->apellido ?? '')) ?: '—' }}<br>
                <strong>{{ $cli?->ruc ? 'RUC' : 'CI' }}:</strong> {{ $cli->ruc ?: ($cli->cedula ?: '—') }}
            </div>
            <div class="col-md-4 text-md-end">
                <strong>Fecha:</strong> {{ fecha($f->fecha_emision) }}<br>
                <strong>Condición:</strong> {{ $f->condicion_venta ?? '—' }}
            </div>
        </div>

        <div class="table-responsive mt-3">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Detalle</th><th class="text-end">Cant.</th>
                        <th class="text-end">Precio</th><th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lineas as $l)
                        <tr>
                            <td>{{ $l->item }}</td>
                            <td class="text-end">{{ cant($l->cantidad) }}</td>
                            <td class="text-end">{{ money($l->precio_unitario) }}</td>
                            <td class="text-end">{{ money($l->subtotal) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="row justify-content-end">
            <div class="col-md-5">
                <table class="table table-sm mb-0" style="font-size:.88rem">
                    <tr><td>Subtotal</td><td class="text-end">{{ money($f->subtotal) }}</td></tr>
                    @if ((float) $f->descuento_total > 0)
                        {{-- Se aplica UNO SOLO: el del nivel del cliente o la
                             mejor promoción vigente, el que más le convenga.
                             Nunca los dos sumados. --}}
                        <tr>
                            <td>Descuento</td>
                            <td class="text-end txt-ok">− {{ money($f->descuento_total) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td><strong>Total</strong></td>
                        <td class="text-end"><strong class="txt-oro">{{ money($f->total) }}</strong></td>
                    </tr>
                    @if ($imp)
                        {{-- En Paraguay el IVA va incluido en el precio: se
                             desglosa, no se suma. --}}
                        <tr class="text-muted-warm" style="font-size:.8rem">
                            <td>IVA 10% incluido</td><td class="text-end">{{ money($imp->iva_10 ?? 0) }}</td>
                        </tr>
                        <tr class="text-muted-warm" style="font-size:.8rem">
                            <td>IVA 5% incluido</td><td class="text-end">{{ money($imp->iva_5 ?? 0) }}</td>
                        </tr>
                    @endif
                    <tr><td>Cobrado</td><td class="text-end">{{ money($f->cobrado) }}</td></tr>
                    <tr>
                        <td><strong>Saldo</strong></td>
                        <td class="text-end">
                            <strong class="{{ (float) $f->saldo > 0.01 ? 'txt-no' : 'txt-ok' }}">
                                {{ money($f->saldo) }}</strong>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        @if ($f->comprobante_origen)
            <p class="text-muted-warm mt-3 mb-0" style="font-size:.82rem">
                Emitida sobre el comprobante <strong>{{ $f->comprobante_origen }}</strong>.
            </p>
        @endif
    </div>

    {{-- Los cobros, incluida la seña, que va atada a la cita y no a la factura --}}
    @if ($cobros)
        <div class="spg-panel mt-3 no-imprimir">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-cash-coin"></i> Cobros de este comprobante</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Fecha</th><th>Medio</th><th>Detalle</th><th class="text-end">Monto</th><th>Estado</th></tr></thead>
                    <tbody>
                        @foreach ($cobros as $c)
                            <tr>
                                <td>{{ fecha($c->fecha) }}</td>
                                <td>
                                    {{ $c->metodo }}
                                    @if ($c->es_sena)<span class="badge-estado e-warn">seña</span>@endif
                                </td>
                                <td class="text-muted-warm" style="font-size:.8rem">
                                    @if ($c->marca)
                                        {{ $c->marca }} {{ $c->tipo_tarjeta }}
                                        @if ($c->ultimos_4)···{{ $c->ultimos_4 }}@endif
                                        @if ((int) $c->cuotas > 1) · {{ (int) $c->cuotas }} cuotas @endif
                                    @elseif ($c->banco)
                                        {{ $c->banco }}
                                        @if ($c->nro_cheque) · cheque {{ $c->nro_cheque }}@endif
                                        @if ($c->nro_operacion) · op. {{ $c->nro_operacion }}@endif
                                    @else
                                        {{ $c->referencia ?: '—' }}
                                    @endif
                                </td>
                                <td class="text-end">{{ money($c->monto) }}</td>
                                <td>{!! estado_badge($c->estado) !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($notas)
        <div class="spg-panel mt-3 no-imprimir">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-arrow-counterclockwise"></i> Notas de crédito</h2>
            @foreach ($notas as $n)
                <div class="d-flex justify-content-between align-items-center py-1">
                    <div>
                        <a class="link-oro" href="{{ route('facturacion.factura_ver', ['id' => $n->id_factura]) }}">
                            {{ $n->nro }}</a>
                        <span class="text-muted-warm"> · {{ fecha($n->fecha_emision) }} · {{ $n->motivo }}</span>
                    </div>
                    <strong>{{ money($n->total) }}</strong>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Modales de anulación --}}
    @if ($f->estado !== 'Anulada' && Permisos::puede('facturacion.facturas'))
        <div class="modal fade" id="modalAnularF" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="{{ route('facturacion.factura.anular') }}">
                        @csrf
                        <input type="hidden" name="id_factura" value="{{ $f->id_factura }}">
                        <div class="modal-header">
                            <h5 class="modal-title" style="font-size:1rem">Anular {{ $f->nro_comprobante }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted-warm" style="font-size:.85rem">
                                El comprobante no se borra: la numeración de la SET no puede tener huecos.
                                Va a seguir apareciendo en el listado con el sello «Anulada».
                                Si tiene cobros, hay que anularlos primero.
                            </p>
                            <label class="form-label" for="motivoAnular">Motivo *</label>
                            <input class="form-control" id="motivoAnular" name="motivo" required maxlength="200">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-oro">Anular</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if ((int) $f->signo === 1 && ! $notas)
            <div class="modal fade" id="modalNC" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post" action="{{ route('facturacion.nota_credito') }}">
                            @csrf
                            <input type="hidden" name="id_factura" value="{{ $f->id_factura }}">
                            <div class="modal-header">
                                <h5 class="modal-title" style="font-size:1rem">
                                    Nota de crédito sobre {{ $f->nro_comprobante }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-muted-warm" style="font-size:.85rem">
                                    Acredita el total del comprobante. Se numera con el timbrado de notas
                                    de crédito, que es distinto del de facturas. El motivo se imprime.
                                </p>
                                <label class="form-label" for="motivoNC">Motivo *</label>
                                <input class="form-control" id="motivoNC" name="motivo" required maxlength="200">
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                                <button class="btn btn-oro">Emitir nota de crédito</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endif
@endsection
