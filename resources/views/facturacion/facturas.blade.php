@extends('layout.app')

@section('titulo', 'Facturas')

@section('contenido')
    <x-encabezado
        sub="Los comprobantes emitidos. <strong>Anular no es borrar</strong>: la numeración de la SET no puede tener huecos, así que el comprobante anulado sigue apareciendo con su sello."
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
                            <div class="spg-cobro" data-saldo="{{ (float) $r->saldo }}">
                                <div class="spg-cobro-lineas"></div>

                                {{-- El aire de arriba no es adorno: las líneas se van
                                     apilando y sin separación el botón queda pegado al
                                     último campo, como si fuera parte de esa línea. --}}
                                <button type="button" class="btn btn-sm btn-rapido spg-cobro-add mt-3">
                                    <i class="bi bi-plus-lg"></i> Otro medio de pago
                                </button>

                                <div class="spg-cobro-total mt-3"></div>

                                {{-- El vuelto es una cuenta de mostrador y NO se guarda:
                                     lo que se registra sigue siendo el monto de la línea.
                                     Entra un billete de 100.000 por un cobro de 30.000 y
                                     en el cajón quedan 30.000, no 100.000. --}}
                                <div class="mt-3 spg-vuelto-bloque">
                                    <label class="form-label" for="vuelto{{ $r->id_factura }}">
                                        ¿Con cuánto paga? <span class="text-muted-warm">(para calcular el vuelto)</span>
                                    </label>
                                    <div class="input-group input-group-sm" style="max-width:260px">
                                        <span class="input-group-text">{{ config('spg.moneda') }}</span>
                                        <input class="form-control input-miles spg-vuelto-recibido"
                                               id="vuelto{{ $r->id_factura }}" data-min="0" autocomplete="off">
                                    </div>
                                    <div class="spg-vuelto-res mt-2"></div>
                                </div>
                            </div>

                            {{-- El molde de una línea. Va como hermano de `.spg-cobro`,
                                 que es donde lo busca el JS. Al ser un <template> no se
                                 dibuja ni se envía: sólo se clona. --}}
                            <template class="spg-cobro-molde">
                                <div class="spg-cobro-linea border-top pt-2 mt-2">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-4">
                                            <label class="form-label">Medio de pago</label>
                                            <select class="form-select form-select-sm spg-cobro-metodo" name="metodo[]">
                                                <option value="0" data-tipo="">— ninguno —</option>
                                                @foreach ($metodos as $m)
                                                    <option value="{{ $m->id_metodo_pago }}" data-tipo="{{ $m->tipo }}"
                                                        @selected($m->tipo === 'EFECTIVO')>{{ $m->nombre }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Monto</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">{{ config('spg.moneda') }}</span>
                                                <input class="form-control input-miles spg-cobro-monto" name="monto[]" data-min="0">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Referencia</label>
                                            <input class="form-control form-control-sm" name="referencia[]"
                                                   placeholder="Nº de operación, boleta…">
                                        </div>
                                        <div class="col-md-1 text-end">
                                            <button type="button" class="btn btn-sm btn-outline-neutro spg-cobro-quitar"
                                                    title="Quitar este medio de pago" aria-label="Quitar este medio de pago">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </div>
                                    </div>

                                    {{-- Sólo cuando el medio es una tarjeta. `tipo_tarjeta`
                                         es NOT NULL en `cobro_tarjeta`, por eso es un
                                         select con dos opciones y no un campo libre: si
                                         llegaba vacío, el cobro entero fallaba con 1048. --}}
                                    <div class="row g-2 mt-1 spg-extra-tarjeta">
                                        <div class="col-md-3">
                                            <label class="form-label">Marca</label>
                                            <input class="form-control form-control-sm" name="marca[]"
                                                   placeholder="Visa, Mastercard…">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Débito o crédito</label>
                                            <select class="form-select form-select-sm" name="tipo_tarjeta[]">
                                                <option value="DEBITO">Débito</option>
                                                <option value="CREDITO">Crédito</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Cuotas</label>
                                            <input class="form-control form-control-sm" name="cuotas[]"
                                                   type="number" min="1" max="36" value="1">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Últimos 4</label>
                                            <input class="form-control form-control-sm" name="ultimos_4[]"
                                                   inputmode="numeric" maxlength="4" placeholder="1234">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Nº de boleta</label>
                                            <input class="form-control form-control-sm" name="nro_boleta[]">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Cód. de autorización</label>
                                            <input class="form-control form-control-sm" name="cod_autorizacion[]">
                                        </div>
                                    </div>

                                    {{-- Transferencia y cheque comparten `cobro_banco`, pero NO
                                         los mismos campos: una transferencia no tiene número de
                                         cheque y un cheque no tiene número de operación.
                                         `data-solo` dice para cuál es cada uno. --}}
                                    <div class="row g-2 mt-1 spg-extra-banco">
                                        <div class="col-md-4">
                                            <label class="form-label">Banco</label>
                                            <input class="form-control form-control-sm" name="banco[]"
                                                   placeholder="Itaú, Continental…">
                                        </div>
                                        <div class="col-md-3" data-solo="CHEQUE">
                                            <label class="form-label">Nº de cheque</label>
                                            <input class="form-control form-control-sm" name="nro_cheque[]">
                                        </div>
                                        <div class="col-md-3" data-solo="BANCO">
                                            <label class="form-label">Nº de operación</label>
                                            <input class="form-control form-control-sm" name="nro_operacion[]">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label spg-fecha-banco">Fecha</label>
                                            <input class="form-control form-control-sm" name="fecha_emision[]" type="date">
                                        </div>
                                    </div>
                                </div>
                            </template>

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
