@extends('layout.app')

@section('titulo', 'Caja')

@section('contenido')
    <x-encabezado sub="Se trabaja con <strong>una sola caja abierta por vez</strong> en todo el salón. El saldo es el <strong>efectivo que tiene que estar en el cajón</strong>: lo que entra por tarjeta o transferencia se registra igual, pero no lo toca." />

    @if ($abierta)
        <div class="spg-caja-barra mb-3">
            <div class="spg-caja-estado">
                <i class="bi bi-safe"></i>
                <span>Caja <strong class="txt-ok">abierta</strong> por {{ $abierta->responsable ?? '—' }}
                    · desde {{ fecha($abierta->fecha_apertura, 'd/m H:i') }}</span>
                <span class="spg-caja-saldo">{{ money($abierta->saldo) }}</span>
            </div>
            <form method="post" action="{{ route('facturacion.caja.cerrar') }}">
                @csrf
                <input type="hidden" name="id_caja" value="{{ $abierta->id_caja }}">
                <button class="btn btn-sm btn-outline-neutro"
                        data-confirmar="Vas a CERRAR la caja con un saldo de {{ money($abierta->saldo) }}. Después no se van a poder registrar cobros hasta abrir una nueva. ¿Confirmás?">
                    <i class="bi bi-lock"></i> Cerrar caja
                </button>
            </form>
        </div>

        {{-- Arqueo por medio: sin esto no se puede cuadrar la plata física --}}
        <div class="spg-panel mb-3">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-cash-stack"></i> Arqueo por medio de pago</h2>
            @if ($porMedio)
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-2">
                        <thead><tr><th>Medio</th><th>¿Está en el cajón?</th><th class="text-end">Cobros</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                            @foreach ($porMedio as $m)
                                <tr>
                                    <td>{{ $m->medio }}</td>
                                    <td>
                                        @if ($m->tipo === 'EFECTIVO')
                                            <span class="badge-estado e-ok">sí, contalo</span>
                                        @else
                                            <span class="badge-estado e-muted">no, va a la cuenta</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ (int) $m->cantidad }}</td>
                                    <td class="text-end">{{ money($m->total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-muted-warm mb-0" style="font-size:.85rem">Todavía no hay cobros en esta caja.</p>
            @endif
        </div>

        {{-- Movimiento cargado a mano: lo único del arqueo que no sale de un
             cobro ni de un pago. `fn_caja_saldo` resta esta tabla desde
             siempre y hasta la 7.29.0 no la escribía ninguna pantalla, así que
             el gasto real del mostrador quedaba afuera y el cierre no cuadraba. --}}
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
                        <thead><tr><th>Cuándo</th><th>Tipo</th><th>Concepto</th><th class="text-end">Monto</th></tr></thead>
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
                                    <td>{{ $m->concepto }}</td>
                                    <td class="text-end">{{ money($m->monto) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @else
        <div class="spg-panel mb-3">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-unlock"></i> Abrir caja</h2>
            <p class="text-muted-warm" style="font-size:.85rem">
                La caja está cerrada. Sin caja abierta no se puede cobrar, señar, facturar una nota de
                crédito ni pagarle a nadie: el movimiento quedaría fuera del arqueo.
            </p>
            <form method="post" action="{{ route('facturacion.caja.abrir') }}" class="d-flex gap-2 align-items-end flex-wrap">
                @csrf
                <div>
                    <label class="form-label" for="monto_inicial">Monto inicial en efectivo</label>
                    <div class="input-group">
                        <span class="input-group-text">{{ config('spg.moneda') }}</span>
                        <input class="form-control input-miles" id="monto_inicial" name="monto_inicial"
                               value="0" data-min="0" required>
                    </div>
                </div>
                <button class="btn btn-oro" data-confirmar="¿Abrir la caja?">
                    <i class="bi bi-unlock"></i> Abrir caja</button>
            </form>
        </div>
    @endif

    <div class="spg-panel">
        <h2 class="spg-form-titulo mb-2"><i class="bi bi-clock-history"></i> Cajas anteriores</h2>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Apertura</th><th>Cierre</th><th>Responsable</th>
                        <th class="text-end">Inicial</th><th class="text-end">Cobros en efectivo</th>
                        <th class="text-end">Saldo</th><th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $c)
                        <tr>
                            <td>{{ fecha($c->fecha_apertura) }}</td>
                            <td>{{ $c->fecha_cierre ? fecha($c->fecha_cierre) : '—' }}</td>
                            <td class="text-muted-warm">{{ $c->responsable ?? '—' }}</td>
                            <td class="text-end">{{ money($c->monto_inicial) }}</td>
                            <td class="text-end">{{ money($c->cobros_efectivo ?? 0) }}</td>
                            <td class="text-end"><strong>{{ money($c->saldo) }}</strong></td>
                            <td>{!! estado_badge($c->estado) !!}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="spg-vacio">
                                    <i class="bi bi-safe"></i>
                                    <div class="t">Todavía no se abrió ninguna caja.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
