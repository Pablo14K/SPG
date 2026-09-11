{{-- **El arqueo de una caja, en UN solo modal para los dos lugares que lo abren.**

     Lo abre la tarjeta de Cajas —que es donde se pidió: «el botón de arqueo te
     lleva a una pantalla donde vuelven a aparecer los mismos botones, y eso es
     doble paso»— y también la pantalla de la caja, para quien llegue por
     enlace directo. Escrito dos veces, el desglose de un lado se desfasa del
     otro y el arqueo deja de cerrar contra el mismo número.

     Parámetros:
       · $abierta  la fila de `vw_caja_resumen` de la sesión abierta
       · $sufijo   lo que distingue los ids: en la lista hay un modal por caja
       · $titulo   el nombre de la caja, para el encabezado

     **Los ids llevan sufijo porque en la lista hay VARIOS.** `app.js` busca la
     salida y el bloque del motivo por selector (`data-arqueo-*`), así que dos
     modales con el mismo `#arqueoDif` escribirían los dos en el primero. --}}
@php
    $sufijo = $sufijo ?? '';
    $titulo = $titulo ?? 'Arqueo de caja';
@endphp
<div class="modal fade" id="modalArqueo{{ $sufijo }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
    <form method="post" action="{{ route('facturacion.caja.cerrar') }}" class="modal-content">
        @csrf
        <input type="hidden" name="id_caja" value="{{ $abierta->id_caja }}">
        <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-calculator"></i> Arqueo · {{ $titulo }}</h5>
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
                <strong class="txt-oro" id="arqueoEsperado{{ $sufijo }}"
                    data-valor="{{ (float) $abierta->saldo }}">{{ money($abierta->saldo) }}</strong>
                </td>
            </tr>
            </tbody>
        </table>

        {{-- Lo que NO entra acá es lo que no está en el cajón: lo cobrado con
             tarjeta o por transferencia se registra igual pero va a la cuenta,
             así que contarlo haría que el arqueo nunca cuadre. --}}
        @if ((($abierta->cobros_otros ?? 0) + ($abierta->pagos_prov_otros ?? 0) + ($abierta->pagos_pers_otros ?? 0)) > 0)
            <p class="text-muted-warm" style="font-size:.8rem">
            <i class="bi bi-info-circle"></i>
            No se cuenta lo cobrado o pagado por tarjeta, transferencia o cheque
            ({{ money($abierta->cobros_otros ?? 0) }} cobrados): eso no pasa por el cajón.
            </p>
        @endif

        <label class="form-label" for="monto_contado{{ $sufijo }}">Dinero contado en el cajón *</label><x-ayuda campo="monto_contado" />
        <div class="input-group">
            <span class="input-group-text">{{ config('sgp.moneda') }}</span>
            <input class="form-control input-miles" id="monto_contado{{ $sufijo }}" name="monto_contado"
               data-min="0" required autocomplete="off"
               data-arqueo="#arqueoEsperado{{ $sufijo }}" data-arqueo-salida="#arqueoDif{{ $sufijo }}"
               data-arqueo-motivo="#bloqueMotivo{{ $sufijo }}">
        </div>

        <div class="mt-2" id="arqueoDif{{ $sufijo }}" style="font-size:.9rem"></div>

        {{-- **El motivo sólo hace falta cuando no cuadra.** Pedirlo siempre
             haría escribir «ok» todos los días, y con eso deja de significar
             algo. El servidor lo exige cuando hay diferencia; acá el bloque
             aparece con ella. --}}
        <div class="mt-3" id="bloqueMotivo{{ $sufijo }}" style="display:none">
            <label class="form-label" for="motivoDif{{ $sufijo }}">¿A qué se debe la diferencia? *</label>
            <input class="form-control" id="motivoDif{{ $sufijo }}" name="motivo_diferencia" maxlength="255"
               placeholder="Ej: se pagó un delivery sin cargar el movimiento">
        </div>

        <div class="mt-3">
            <label class="form-label" for="obsCierre{{ $sufijo }}">Observación <span class="text-muted-warm">(opcional)</span></label>
            <input class="form-control" id="obsCierre{{ $sufijo }}" name="observacion" maxlength="255"
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
</div>
