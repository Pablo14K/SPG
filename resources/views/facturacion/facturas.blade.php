@extends('layout.app')

@section('titulo', 'Facturas')

@section('contenido')
    <x-encabezado
        sub="Los comprobantes emitidos. <strong>Anular no es borrar</strong>: la numeración de la DNIT no puede tener huecos, así que el comprobante anulado sigue apareciendo con su sello."
        :accion="['ruta' => 'facturacion.emitir', 't' => 'Emitir factura', 'ic' => 'receipt-cutoff']" />

    @if (! $caja)
        <div class="alert alert-warning">
            La caja está cerrada: se pueden ver los comprobantes, pero no cobrar.
        </div>
    @endif

    <div class="spg-panel">
        <x-filtros :f="$f" />

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Nº</th><th>Fecha</th><th>Cliente</th><th>Comprobante</th>
                        <th class="text-end">Total</th><th class="text-end">Cobrado</th>
                        <th class="text-end">Saldo</th><th>Estado</th><th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td style="white-space:nowrap">
                                <a class="link-oro" href="{{ route('facturacion.factura_ver', ['id' => $r->id_factura]) }}">
                                    {{ $r->nro_comprobante }}</a>
                            </td>
                            <td>{{ fecha($r->fecha_emision) }}</td>
                            <td>{{ $r->cliente }}</td>
                            <td class="text-muted-warm">{{ $r->tipo_comprobante }}</td>
                            <td class="text-end">{{ money($r->total) }}</td>
                            <td class="text-end">{{ money($r->cobrado) }}</td>
                            <td class="text-end">
                                @if ((float) $r->saldo > 0.01)
                                    <strong class="txt-no">{{ money($r->saldo) }}</strong>
                                @else
                                    <span class="txt-ok">saldada</span>
                                @endif
                            </td>
                            <td>
                                {!! estado_badge($r->estado) !!}
                                {{-- Una venta acreditada se ve igual que cualquier otra —«Emitida»,
                                     saldo 0—, así que sin este sello no había forma de saber que
                                     se había devuelto sin entrar al comprobante. --}}
                                @if ((int) $r->acreditada)
                                    <span class="badge-estado e-no" title="Tiene una nota de crédito emitida">acreditada</span>
                                @endif
                            </td>
                            <td class="text-end" style="white-space:nowrap">
                                <a class="btn btn-sm btn-outline-neutro" title="Ver el comprobante"
                                   href="{{ route('facturacion.factura_ver', ['id' => $r->id_factura]) }}">
                                    <i class="bi bi-file-earmark-text"></i></a>

                                {{-- Una nota de crédito no se cobra: el
                                     procedimiento lo rechaza, pero ofrecer el
                                     botón igual sería engañoso. --}}
                                @if ((float) $r->saldo > 0.01 && $r->estado !== 'Anulada' && $caja && (int) $r->signo === 1)
                                    <button class="btn btn-sm btn-oro" title="Cobrar"
                                            data-bs-toggle="modal" data-bs-target="#modalCobro{{ $r->id_factura }}">
                                        <i class="bi bi-cash-coin"></i> Cobrar</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="spg-vacio">
                                    <i class="bi bi-receipt"></i>
                                    <div class="t">{{ $f['activos'] ? 'Ningún comprobante coincide con esos filtros.' : 'Todavía no se emitió ningún comprobante.' }}</div>
                                    <div class="d">Se emiten desde una cita ya atendida.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-paginacion :pag="$pag" :f="$f" />
    </div>

    {{-- Un modal de cobro por factura pendiente.

         El pago mixto no es una función aparte: es el modelo. `cobro` es CADA
         pago, no el pago de la factura, así que se cargan varias líneas y cada
         una es una llamada al procedimiento, todo en una transacción. --}}
    @foreach ($rows as $r)
        @continue ((float) $r->saldo <= 0.01 || $r->estado === 'Anulada' || ! $caja || (int) $r->signo !== 1)
        <div class="modal fade" id="modalCobro{{ $r->id_factura }}" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="post" action="{{ route('facturacion.cobrar') }}">
                        @csrf
                        <input type="hidden" name="id_factura" value="{{ $r->id_factura }}">
                        <div class="modal-header">
                            <h5 class="modal-title" style="font-size:1rem">
                                <i class="bi bi-cash-coin"></i> Cobrar {{ $r->nro_comprobante }} — {{ $r->cliente }}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-3">
                                Saldo pendiente: <strong class="txt-oro">{{ money($r->saldo) }}</strong>
                            </p>

                            {{-- Las líneas las arma `app.js` desde el molde de abajo:
                                 arranca con UNA sola por el saldo entero —el caso de
                                 todos los días— y se agregan las que hagan falta. Antes
                                 había dos fijas y las dos mostraban a la vez los campos
                                 de tarjeta Y los de banco, ocho casillas sueltas sin
                                 etiqueta para un cobro que el 90 % de las veces es en
                                 efectivo.

                                 Los `spg-cobro-*` no son clases decorativas: son las que
                                 busca el bloque de `app.js`. Si se las renombra, el
                                 modal deja de armarse y no avisa. --}}
                            <x-cobro-lineas :uid="$r->id_factura" :max="(float) $r->saldo" :metodos="$metodos" />

                            <p class="text-muted-warm mb-0 mt-3" style="font-size:.78rem">
                                Se puede pagar con varios medios a la vez: una parte en efectivo y otra con
                                tarjeta, por ejemplo. Si una línea falla, no se guarda ninguna.
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-oro">Registrar cobro</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection
