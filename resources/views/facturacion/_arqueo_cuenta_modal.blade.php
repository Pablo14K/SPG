{{-- **El arqueo de una cuenta bancaria, en UN solo modal para los dos lugares
     que lo abren**: la tarjeta de la cuenta y la lista de Arqueos. Es lo mismo
     que `_arqueo_modal` hace con el cajón, y por el mismo motivo: escrito dos
     veces, un lado dice «esperado» con un número y el otro con otro.

     Parámetros:
       · $c       la fila de `Cuenta::deSucursal()`
       · $volver  'cuentas' o 'arqueos': a dónde vuelve al guardar

     **El esperado es `fn_cuenta_saldo`**: el último arqueo más lo que entró y
     salió desde entonces. En el PRIMER arqueo no hay esperado —no hay contra
     qué comparar— y el modal lo dice en vez de inventar un cero. --}}
@php $volver = $volver ?? 'cuentas'; @endphp
<div class="modal fade" id="modalArqueoCta{{ $c->id_cuenta }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="{{ route('facturacion.cuentas.arqueo') }}" class="modal-content">
            @csrf
            <input type="hidden" name="id_cuenta" value="{{ $c->id_cuenta }}">
            <input type="hidden" name="volver" value="{{ $volver }}">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:1rem">
                    <i class="bi bi-calculator"></i> Arqueo · {{ $c->entidad }}
                    @if ($c->numero_cuenta)
                        <span class="text-muted-warm" style="font-size:.82rem">· {{ $c->numero_cuenta }}</span>
                    @endif
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted-warm" style="font-size:.85rem">
                    Mirá el saldo en el banco y escribilo acá. El sistema lo compara con lo que
                    debería haber y dice si la cuenta cuadra.
                </p>

                @if ($c->saldo !== null)
                    <table class="table table-sm align-middle mb-3" style="font-size:.86rem">
                        <tbody>
                            <tr>
                                <td>Último arqueo
                                    <span class="text-muted-warm" style="font-size:.78rem">
                                        · {{ fecha($c->ultimo_arqueo_en, 'd/m/Y H:i') }}</span></td>
                                <td class="text-end">{{ money($c->ultimo_arqueo_monto) }}</td>
                            </tr>
                            <tr>
                                <td>Lo que entró y salió desde entonces</td>
                                @php $sgpMov = (float) $c->saldo - (float) $c->ultimo_arqueo_monto; @endphp
                                <td class="text-end">{{ $sgpMov < 0 ? '− ' : '+ ' }}{{ money(abs($sgpMov)) }}</td>
                            </tr>
                            <tr style="border-top:2px solid var(--gris-calido)">
                                <td><strong>Saldo esperado</strong></td>
                                <td class="text-end">
                                    <strong class="txt-oro" id="arqueoCtaEsperado{{ $c->id_cuenta }}"
                                            data-valor="{{ (float) $c->saldo }}">{{ money($c->saldo) }}</strong>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                @else
                    <div class="alert alert-warning py-2" style="font-size:.84rem">
                        <strong>Es el primer arqueo de esta cuenta</strong>, así que no hay un saldo
                        esperado contra el cual comparar. Desde este número el sistema empieza a sumar
                        los cobros por transferencia y a descontar los pagos.
                    </div>
                @endif

                <label class="form-label" for="saldoCta{{ $c->id_cuenta }}">¿Cuánto dice el banco que hay? *</label>
                <div class="input-group">
                    <span class="input-group-text">{{ config('sgp.moneda') }}</span>
                    <input class="form-control input-miles" id="saldoCta{{ $c->id_cuenta }}" name="saldo"
                           data-min="0" required autocomplete="off"
                           @if ($c->saldo !== null)
                               data-arqueo="#arqueoCtaEsperado{{ $c->id_cuenta }}"
                               data-arqueo-salida="#arqueoCtaDif{{ $c->id_cuenta }}"
                               data-arqueo-motivo="#bloqueMotivoCta{{ $c->id_cuenta }}"
                               data-arqueo-cuadra="✓ La cuenta cuadra."
                           @endif>
                </div>

                @if ($c->saldo !== null)
                    <div class="mt-2" id="arqueoCtaDif{{ $c->id_cuenta }}" style="font-size:.9rem"></div>

                    {{-- Igual que en el cajón: el motivo sólo cuando no cuadra. El
                         servidor lo vuelve a exigir en `Cuenta::arquear()`. --}}
                    <div class="mt-3" id="bloqueMotivoCta{{ $c->id_cuenta }}" style="display:none">
                        <label class="form-label" for="motivoCta{{ $c->id_cuenta }}">¿A qué se debe la diferencia? *</label>
                        <input class="form-control" id="motivoCta{{ $c->id_cuenta }}" name="motivo_diferencia"
                               maxlength="255" placeholder="Ej: una clienta transfirió y no se cargó el cobro">
                    </div>
                @endif

                <div class="mt-3">
                    <label class="form-label" for="obsCta{{ $c->id_cuenta }}">Observación <span class="text-muted-warm">(opcional)</span></label>
                    <input class="form-control" id="obsCta{{ $c->id_cuenta }}" name="observacion" maxlength="255"
                           placeholder="Ej: extracto del banco al cierre del día">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-oro"><i class="bi bi-check2-square"></i> Registrar el arqueo</button>
            </div>
        </form>
    </div>
</div>
