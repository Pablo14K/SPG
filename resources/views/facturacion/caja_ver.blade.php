@extends('layout.app')

@section('titulo', $cajon->nombre)

@section('contenido')
@php use App\Servicios\Permisos; @endphp

{{-- **Acá no se listan las otras cajas.** La lista sirve para elegir; esta
     pantalla, para trabajar con la elegida. Por eso es tan vacía: lo único que
     hay que poder hacer es ver cuánto hay, mirar sus movimientos y cerrarla. --}}
<div class="spg-page-head">
    <a class="spg-back" href="{{ route('facturacion.cajas') }}">
        <i class="bi bi-arrow-left"></i> Cajas</a>
    <h1 class="mt-1">
        <i class="bi bi-safe"></i> {{ $cajon->nombre }}
        @if ($abierta)
            <span class="badge-estado e-ok"><i class="bi bi-unlock"></i> Abierta</span>
        @else
            <span class="badge-estado e-muted"><i class="bi bi-lock"></i> Cerrada</span>
        @endif
    </h1>
    <div class="sub">
        {{ $cajon->sucursal }}@if ($abierta) · {{ $abierta->responsable }} · desde
            {{ fecha($abierta->fecha_apertura, 'd/m H:i') }}@endif
    </div>
</div>

@if ($abierta)
    <div class="spg-panel mb-3">
        <div class="spg-metrics spg-metrics-compacto">
            <div class="spg-metric">
                <div class="lbl">Efectivo esperado</div>
                <div class="val oro">{{ money($saldo) }}</div>
                <div class="spg-metric-pie">lo que tiene que estar en el cajón</div>
            </div>
            <div class="spg-metric">
                <div class="lbl">Monto de apertura</div>
                <div class="val">{{ money($abierta->monto_inicial) }}</div>
            </div>
            <div class="spg-metric">
                <div class="lbl">Cobrado en efectivo</div>
                <div class="val">{{ money($abierta->cobros_efectivo) }}</div>
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap mt-3">
            @if (Permisos::puede('facturacion.movimientos'))
                <a class="btn btn-outline-neutro"
                   href="{{ route('facturacion.movimientos', ['caja' => $cajon->id_caja_fisica]) }}">
                    <i class="bi bi-list-ul"></i> Ver movimientos</a>
            @endif
            <button class="btn btn-oro" data-bs-toggle="modal" data-bs-target="#modalArqueo">
                <i class="bi bi-lock"></i> Cerrar caja</button>
        </div>
    </div>


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

            {{-- **El motivo sólo hace falta cuando no cuadra.** Pedirlo
                 siempre haría escribir «ok» todos los días, y con eso
                 deja de significar algo. El servidor lo exige cuando hay
                 diferencia; acá el bloque aparece con ella. --}}
            <div class="mt-3" id="bloqueMotivo" style="display:none">
                <label class="form-label" for="motivoDif">¿A qué se debe la diferencia? *</label>
                <input class="form-control" id="motivoDif" name="motivo_diferencia" maxlength="255"
                   placeholder="Ej: se pagó un delivery sin cargar el movimiento">
            </div>

            <div class="mt-3">
                <label class="form-label" for="obsCierre">Observación <span class="text-muted-warm">(opcional)</span></label>
                <input class="form-control" id="obsCierre" name="observacion" maxlength="255"
                   placeholder="Cómo terminó el día">
            </div>
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
@else
    {{-- **Abrir es la única acción posible acá**, así que va sola y sin ruido:
         sin caja abierta no se cobra, no se factura y no se paga. --}}
    <div class="spg-panel">
        <h2 class="spg-form-titulo mb-2"><i class="bi bi-unlock"></i> Abrir esta caja</h2>
        <p class="text-muted-warm" style="font-size:.85rem">
            El monto inicial es el efectivo con el que arranca el cajón. Al cerrar se
            cuenta lo que hay y el sistema dice si cuadra.
        </p>

        <form method="post" action="{{ route('facturacion.caja.abrir') }}"
              class="d-flex gap-2 align-items-end flex-wrap">
            @csrf
            <input type="hidden" name="id_caja_fisica" value="{{ $cajon->id_caja_fisica }}">
            <div>
                <label class="form-label" for="monto_inicial">Monto inicial en efectivo</label>
                <div class="input-group">
                    <span class="input-group-text">Gs.</span>
                    <input class="form-control input-miles" id="monto_inicial" name="monto_inicial"
                           inputmode="numeric" data-min="0" value="0" required>
                </div>
            </div>
            <div class="flex-grow-1" style="min-width:220px">
                <label class="form-label" for="obsApertura">
                    Observación <span class="text-muted-warm">(opcional)</span></label>
                <input class="form-control" id="obsApertura" name="observacion" maxlength="255"
                       placeholder="Con qué se abre, si hay algo que aclarar">
            </div>
            <button class="btn btn-oro"><i class="bi bi-unlock"></i> Abrir caja</button>
        </form>
    </div>
@endif
@endsection
