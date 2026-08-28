{{--
    Las líneas de un cobro: medio, monto, el detalle que corresponda a ese medio
    y el vuelto. Estaba escrito dentro de la pantalla de Facturas y **sólo ahí**,
    así que cobrar desde la agenda —que es como se cobra en el mostrador— daba
    una sola línea, en efectivo, sin campos de tarjeta ni de banco y sin vuelto.

    Las clases `spg-cobro-*` NO son decorativas: son las que busca `app.js` para
    clonar el molde, mostrar el detalle del medio elegido y calcular el vuelto.
    Si se renombran, el modal deja de armarse y no avisa.

    · $uid     identificador único de este modal (id de factura o de cita)
    · $max     lo máximo que se puede cobrar acá
    · $sugerido el monto que viene propuesto (por defecto, todo lo que falta)
    · $metodos los medios de pago activos
    · $cajas   las cajas abiertas del local, para elegir a cuál entra la plata
--}}
@php $cajasAbiertas = $cajas ?? \App\Servicios\Caja::abiertasDe(); @endphp
                            <div class="spg-cobro" data-saldo="{{ (float) $max }}"
                                     data-sugerido="{{ (float) ($sugerido ?? $max) }}">
                                {{-- **A qué caja entra la plata.** El bloque es el
                                     mismo que usan los pagos: escrito dos veces, uno
                                     de los dos se queda atrás. --}}
                                @include('facturacion._caja_elegir', [
                                    'cajas' => $cajasAbiertas,
                                    'uid' => 'Cobro' . $uid,
                                    'rotulo' => '¿A qué caja entra?',
                                ])

                                <div class="spg-cobro-lineas"></div>

                                {{-- El aire de arriba no es adorno: las líneas se van
                                     apilando y sin separación el botón queda pegado al
                                     último campo, como si fuera parte de esa línea. --}}
                                <button type="button" class="btn btn-sm btn-rapido spg-cobro-add mt-3">
                                    <i class="bi bi-plus-lg"></i> Otro medio de pago
                                </button>

                                <div class="spg-cobro-total mt-3"></div>

                                {{-- **El vuelto es del EFECTIVO, no del cobro entero.**
                                     Se compara contra lo que se paga en billetes: en un
                                     pago partido, la parte por transferencia no tiene
                                     cambio que dar. Y no se guarda —lo que se registra
                                     sigue siendo el monto de la línea—: entra un billete
                                     de 100.000 por un cobro de 30.000 y en el cajón
                                     quedan 30.000, no 100.000. --}}
                                <div class="mt-3 spg-vuelto-bloque"
                                     style="border-top:1px solid var(--gris-calido);padding-top:.7rem">
                                    <label class="form-label mb-1" for="vuelto{{ $uid }}">
                                        <i class="bi bi-cash"></i> Vuelto <span class="text-muted-warm">(sólo la parte en efectivo)</span>
                                    </label>
                                    <div class="text-muted-warm mb-1" style="font-size:.8rem">
                                        ¿Con cuánto billete paga?
                                    </div>
                                    <div class="input-group input-group-sm" style="max-width:260px">
                                        <span class="input-group-text">{{ config('spg.moneda') }}</span>
                                        <input class="form-control input-miles spg-vuelto-recibido"
                                               id="vuelto{{ $uid }}" data-min="0" autocomplete="off">
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
                                            <input class="form-control form-control-sm" name="ultimos_4[]" data-solo="numeros" inputmode="numeric" maxlength="4"
                                                   inputmode="numeric" maxlength="4" placeholder="1234">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Nº de boleta</label>
                                            <input class="form-control form-control-sm" name="nro_boleta[]" data-solo="numeros" inputmode="numeric">
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
                                            <input class="form-control form-control-sm" name="nro_cheque[]" data-solo="numeros" inputmode="numeric">
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
