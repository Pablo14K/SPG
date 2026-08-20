@extends('layout.app')

@section('titulo', 'Movimiento de efectivo')

@section('contenido')
    <x-encabezado
        sub="El gasto de caja chica, el retiro, la plata que se saca para el cambio: lo único del arqueo que <strong>no sale de un cobro ni de un pago</strong>. Sin esto el cierre no cuadra y no hay forma de saber por qué." />

    @if (! $abierta)
        {{-- Sin caja abierta no se mueve un guaraní: quedaría fuera del arqueo
             y el cierre no cerraría. El aviso dice qué hacer, no «no se puede». --}}
        <div class="alert alert-warning">
            <strong>No hay ninguna caja abierta en esta sucursal.</strong>
            Un movimiento sin caja quedaría fuera del arqueo.
            @if (\App\Servicios\Permisos::puede('facturacion.caja'))
                <a class="link-oro" href="{{ route('facturacion.caja') }}">Abrí la caja</a> y volvé.
            @else
                Pedile a quien administra la caja que la abra.
            @endif
        </div>
    @else
        <div class="spg-panel mb-3">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-cash-coin"></i> Movimiento de efectivo</h2>
            <p class="text-muted-warm" style="font-size:.85rem">
                Para lo que entra o sale del cajón sin ser un cobro ni un pago: el delivery, el taxi,
                la plata que se saca para el cambio, un retiro. <strong>Queda en el arqueo</strong>, así
                que el cierre cuadra con lo que hay de verdad.
            </p>

            <form method="post" action="{{ route('facturacion.caja.movimiento') }}"
                  class="row g-2 align-items-end">
                @csrf
                <div class="col-md-2">
                    <label class="form-label" for="mc_tipo">Tipo</label>
                    <select class="form-select" id="mc_tipo" name="tipo" required>
                        <option value="EGRESO" @selected(old('tipo') !== 'INGRESO')>Egreso (sale)</option>
                        <option value="INGRESO" @selected(old('tipo') === 'INGRESO')>Ingreso (entra)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="mc_monto">Monto</label>
                    <div class="input-group">
                        <span class="input-group-text">{{ config('spg.moneda') }}</span>
                        <input class="form-control input-miles" id="mc_monto" name="monto"
                               value="{{ old('monto') }}" data-min="1" required>
                    </div>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="mc_concepto">Concepto</label>
                    <input class="form-control" id="mc_concepto" name="concepto" maxlength="150"
                           value="{{ old('concepto') }}" required
                           placeholder="Ej.: delivery del almuerzo, retiro de la dueña…">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-oro w-100"
                            data-confirmar="Este movimiento entra al arqueo de la caja abierta. ¿Confirmás?">
                        <i class="bi bi-plus-lg"></i> Registrar
                    </button>
                </div>
            </form>

            @if (count($movimientos))
                <div class="table-responsive mt-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Cuándo</th><th>Tipo</th><th>Concepto</th>
                            <th class="text-end">Monto</th><th class="text-end"></th></tr></thead>
                        <tbody>
                            @foreach ($movimientos as $m)
                                <tr>
                                    <td>{{ fecha($m->fecha, 'd/m H:i') }}</td>
                                    <td>
                                        @if ($m->tipo === 'INGRESO')
                                            <span class="badge-estado e-ok">Ingreso</span>
                                        @else
                                            <span class="badge-estado e-no">Egreso</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $m->concepto }}
                                        @unless ($m->activo)
                                            <span class="badge-estado e-no">anulado</span>
                                            <div class="text-muted-warm" style="font-size:.75rem">
                                                {{ $m->anulado_motivo }}</div>
                                        @endunless
                                    </td>
                                    <td class="text-end {{ $m->activo ? '' : 'text-muted-warm' }}"
                                        style="{{ $m->activo ? '' : 'text-decoration:line-through' }}">
                                        {{ money($m->monto) }}</td>
                                    {{-- **Se anula, no se borra**: el arqueo tiene que poder
                                         explicar qué pasó. Y sólo mientras la caja siga
                                         abierta — después del cierre el número ya se contó. --}}
                                    <td class="text-end">
                                        @if ($m->activo)
                                            <button type="button" class="btn btn-sm btn-outline-neutro"
                                                    data-bs-toggle="modal" data-bs-target="#anularMov{{ $m->id_movimiento_caja }}">
                                                <i class="bi bi-x-lg"></i> Anular</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Un modal por movimiento: el motivo es obligatorio, porque es
                     lo único que explica esa anulación al cerrar la caja. --}}
                @foreach ($movimientos as $m)
                    @if ($m->activo)
                        <div class="modal fade" id="anularMov{{ $m->id_movimiento_caja }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="post" action="{{ route('facturacion.caja.movimiento.anular') }}">
                                        @csrf
                                        <input type="hidden" name="id_movimiento_caja"
                                               value="{{ $m->id_movimiento_caja }}">
                                        <div class="modal-header">
                                            <h5 class="modal-title" style="font-size:1rem">Anular el movimiento</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="text-muted-warm" style="font-size:.85rem">
                                                {{ $m->tipo === 'INGRESO' ? 'Ingreso' : 'Egreso' }} de
                                                <strong>{{ money($m->monto) }}</strong> — {{ $m->concepto }}.
                                                El movimiento no se borra: queda anulado y con su motivo,
                                                y el saldo del cajón deja de contarlo.
                                            </p>
                                            <label class="form-label" for="mot{{ $m->id_movimiento_caja }}">¿Por qué?</label>
                                            <input class="form-control" id="mot{{ $m->id_movimiento_caja }}"
                                                   name="motivo" maxlength="150" required
                                                   placeholder="Se cargó dos veces, monto equivocado…">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-neutro"
                                                    data-bs-dismiss="modal">Cancelar</button>
                                            <button class="btn btn-oro">Anular</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            @endif
        </div>
    @endif
@endsection
