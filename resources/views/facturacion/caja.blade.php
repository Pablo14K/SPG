@extends('layout.app')

@section('titulo', 'Caja')

@section('contenido')
    <x-encabezado sub="Se trabaja con <strong>una sola caja abierta por vez y por sucursal</strong>: cada local cuenta su propio cajón. El saldo es el <strong>efectivo que tiene que estar en el cajón</strong>: lo que entra por tarjeta o transferencia se registra igual, pero no lo toca." />

    @if ($abierta)
        <div class="spg-caja-barra mb-3">
            <div class="spg-caja-estado">
                <i class="bi bi-safe"></i>
                <span>Caja <strong class="txt-ok">abierta</strong> por {{ $abierta->responsable ?? '—' }}
                    · desde {{ fecha($abierta->fecha_apertura, 'd/m H:i') }}</span>
                <span class="spg-caja-saldo">{{ money($abierta->saldo) }}</span>
            </div>
            <button class="btn btn-sm btn-outline-neutro" data-bs-toggle="modal" data-bs-target="#modalArqueo">
                <i class="bi bi-lock"></i> Cerrar caja
            </button>
        </div>

        {{-- ------------------------------------------------------------------
             El arqueo.

             **Cerrar la caja era un botón y ahora es un conteo.** El sistema
             sabía cuánto DEBERÍA haber —`fn_caja_saldo`— y nunca preguntaba
             cuánto HAY, así que no podía decir si cuadraba: un faltante se
             descubría al día siguiente y sin saber de qué día venía.

             La diferencia se calcula en el navegador mientras se escribe, para
             que quien cuenta la vea antes de confirmar; **la que vale es la
             que calcula la base** (`fn_caja_diferencia`) con el saldo del
             momento del cierre.
             ------------------------------------------------------------------ --}}
        <div class="modal fade" id="modalArqueo" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="post" action="{{ route('facturacion.caja.cerrar') }}" class="modal-content">
                    @csrf
                    <input type="hidden" name="id_caja" value="{{ $abierta->id_caja }}">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-calculator"></i> Arqueo de caja</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted-warm" style="font-size:.85rem">
                            Contá el efectivo que hay en el cajón y escribí cuánto es. El sistema compara
                            contra lo que debería haber y dice si cuadra.
                        </p>

                        <table class="table table-sm align-middle mb-3" style="font-size:.86rem">
                            <tbody>
                                <tr>
                                    <td>Saldo inicial</td>
                                    <td class="text-end">{{ money($abierta->monto_inicial) }}</td>
                                </tr>
                                <tr>
                                    <td>Cobros en efectivo</td>
                                    <td class="text-end">{{ money($abierta->cobros_efectivo ?? 0) }}</td>
                                </tr>
                                @if (($abierta->otros_ingresos ?? 0) > 0)
                                    <tr>
                                        <td>Otros ingresos</td>
                                        <td class="text-end">{{ money($abierta->otros_ingresos) }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td>Egresos del mostrador</td>
                                    <td class="text-end">− {{ money($abierta->egresos ?? 0) }}</td>
                                </tr>
                                <tr>
                                    <td>Pagos a proveedores en efectivo</td>
                                    <td class="text-end">− {{ money($abierta->pagos_prov_efectivo ?? 0) }}</td>
                                </tr>
                                <tr>
                                    <td>Liquidaciones al personal en efectivo</td>
                                    <td class="text-end">− {{ money($abierta->pagos_pers_efectivo ?? 0) }}</td>
                                </tr>
                                <tr style="border-top:2px solid var(--gris-calido)">
                                    <td><strong>Saldo esperado</strong></td>
                                    <td class="text-end">
                                        <strong class="txt-oro" id="arqueoEsperado"
                                                data-valor="{{ (float) $abierta->saldo }}">{{ money($abierta->saldo) }}</strong>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        {{-- Lo que NO entra acá es lo que no está en el cajón: lo
                             cobrado con tarjeta o por transferencia se registra
                             igual pero va a la cuenta, así que contarlo haría que
                             el arqueo nunca cuadre. --}}
                        @if ((($abierta->cobros_otros ?? 0) + ($abierta->pagos_prov_otros ?? 0) + ($abierta->pagos_pers_otros ?? 0)) > 0)
                            <p class="text-muted-warm" style="font-size:.8rem">
                                <i class="bi bi-info-circle"></i>
                                No se cuenta lo cobrado o pagado por tarjeta, transferencia o cheque
                                ({{ money($abierta->cobros_otros ?? 0) }} cobrados): eso no pasa por el cajón.
                            </p>
                        @endif

                        <label class="form-label" for="monto_contado">Dinero contado en el cajón *</label>
                        <div class="input-group">
                            <span class="input-group-text">{{ config('spg.moneda') }}</span>
                            <input class="form-control input-miles" id="monto_contado" name="monto_contado"
                                   data-min="0" required autocomplete="off"
                                   data-arqueo="#arqueoEsperado" data-arqueo-salida="#arqueoDif">
                        </div>

                        <div class="mt-2" id="arqueoDif" style="font-size:.9rem"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-oro"
                                data-confirmar="Después de cerrar no se van a poder registrar cobros hasta abrir una caja nueva. ¿Confirmás el arqueo?">
                            <i class="bi bi-lock"></i> Cerrar caja
                        </button>
                    </div>
                </form>
            </div>
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

    @else
        {{-- **Este `@else` es lo único que hace alcanzable la apertura.** La
             7.46.0 mudó el bloque de movimientos a su propia pantalla y se
             lo llevó puesto, así que el formulario de abrir quedó dentro de
             la rama «hay caja abierta»: se dibujaba cuando ya no hacía falta
             y desaparecía justo cuando sí. Con la caja cerrada la pantalla
             salía sin nada, y sin caja no se cobra ni se factura. --}}
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
                        <th class="text-end">Esperado</th><th class="text-end">Contado</th>
                        <th class="text-end">Diferencia</th><th>Estado</th>
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
                            {{-- **«—» y no «Gs. 0» cuando no se contó.** Un cero ahí
                                 se lee como «cuadró», que es justo lo que no se
                                 sabe: son las cajas cerradas antes del arqueo. --}}
                            <td class="text-end">
                                {{ $c->monto_contado === null ? '—' : money($c->monto_contado) }}
                            </td>
                            <td class="text-end">
                                @if ($c->diferencia === null)
                                    <span class="text-muted-warm">sin conteo</span>
                                @elseif (abs((float) $c->diferencia) < 0.01)
                                    <span class="badge-estado e-ok">cuadra</span>
                                @elseif ((float) $c->diferencia > 0)
                                    <span class="badge-estado e-warn">sobran {{ money($c->diferencia) }}</span>
                                @else
                                    <span class="badge-estado e-no">faltan {{ money(abs((float) $c->diferencia)) }}</span>
                                @endif
                            </td>
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
