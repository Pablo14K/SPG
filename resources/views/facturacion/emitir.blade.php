@extends('layout.app')

@section('titulo', 'Emitir factura')

@section('contenido')
    <x-encabezado sub="Citas atendidas que todavía no tienen comprobante. El detalle sale de los servicios de la cita, y el descuento lo aplica la base: el del nivel del cliente o la mejor promoción vigente, el que más le convenga." />

    @if (! $tipos)
        <div class="alert alert-danger">
            <strong>No hay ningún timbrado vigente.</strong> Sin timbrado no se puede numerar un
            comprobante.
            @if (\App\Servicios\Permisos::puede('facturacion.timbrados'))
                <a class="link-oro" href="{{ route('facturacion.timbrados') }}">Cargá uno acá</a>.
            @else
                Pedile a quien administra los timbrados que cargue uno.
            @endif
        </div>
    @elseif (! collect($tipos)->contains(fn ($t) => (int) $t->id_tipo_comprobante === $tipoDefecto))
        {{-- El comprobante que el sistema quiere emitir por defecto es el Ticket,
             porque la clienta no siempre pide factura. Pero el Ticket necesita su
             propio timbrado, y si no está cargado la pantalla caía en Factura sin
             decir nada: cada atención salía como factura declarable, que es
             justo lo contrario de lo que el salón quiso configurar. --}}
        {{-- El aviso le habla a quien atiende, no a quien programa: dice qué
             está pasando ahora, qué se está perdiendo y qué hacer. Antes decía
             «el comprobante por defecto no tiene timbrado vigente», que son
             dos palabras del sistema y ninguna del salón. --}}
        {{-- **«No tiene timbrado» se leía como «no hay timbrados».** Quien abre
             esta pantalla acaba de ver dos filas cargadas en Timbrados, así que
             el aviso parecía contradecirlas — y no: los que están son de OTROS
             comprobantes. El aviso nombra los dos lados, el que falta y los que
             sí están, para que no haya nada que adivinar. --}}
        <div class="alert alert-warning">
            <strong>Falta el timbrado del {{ $tipoDefectoNombre }}.</strong>
            Es el comprobante que se usa cuando la clienta <em>no</em> pide factura, y
            **sin su propio timbrado no se puede numerar**, así que no aparece en la
            lista de abajo: por ahora todo sale como
            {{ collect($tipos)->pluck('nombre')->join(' o ') }}.
            <div class="mt-1" style="font-size:.86rem">
                Los timbrados cargados son de
                <strong>{{ collect($tipos)->pluck('nombre')->join(' y ') }}</strong> — cada
                comprobante lleva el suyo, con su propia numeración.
                @if (\App\Servicios\Permisos::puede('facturacion.timbrados'))
                    <a class="link-oro" href="{{ route('facturacion.timbrados') }}">Cargá el
                    del {{ $tipoDefectoNombre }}</a> y vuelve a aparecer acá.
                @else
                    Pedile a quien maneja los timbrados que cargue el del
                    {{ $tipoDefectoNombre }}.
                @endif
            </div>
        </div>
    @endif

    {{-- **Este local va a numerar con el timbrado de otro.** No es un error: la
         caída de `fn_timbrado_vigente` existe para que un local sin timbrado
         propio pueda seguir facturando. Pero arrastra dos cosas que no se ven
         en la pantalla y que quien atiende necesita saber: el establecimiento
         impreso —los tres dígitos con los que la DNIT identifica el local— va a
         decir la otra sede, y el cobro va a entrar al cajón de esa otra sede,
         porque desde la 7.36.3 la sucursal del cobro se deduce del timbrado.

         Una caída en silencio es indistinguible de un error. --}}
    @if (! empty($timbradoAjeno))
        <div class="alert alert-warning">
            <strong>Esta sucursal no tiene timbrado propio.</strong>
            Los comprobantes se van a numerar con el timbrado de
            <strong>{{ $timbradoAjeno }}</strong>, así que van a salir impresos como si
            hubieran sido emitidos ahí — y el cobro va a entrar al cajón de esa sucursal,
            no al de acá.
            @if (\App\Servicios\Permisos::puede('facturacion.timbrados'))
                <a class="link-oro" href="{{ route('facturacion.timbrados') }}">Cargale un timbrado a este local</a>
                y cada comprobante queda donde corresponde.
            @else
                Pedile a quien maneja los timbrados que le cargue uno a este local.
            @endif
        </div>
    @endif

    <div class="spg-panel">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Fecha</th><th>Cliente</th><th>Servicios</th><th class="text-end">Total</th><th class="text-end">Facturar</th></tr>
                </thead>
                <tbody>
                    @forelse ($citas as $c)
                        @php $elegida = $sel_cita > 0 && (int) $c->id_cita === $sel_cita; @endphp
                        {{-- La que se eligió en la agenda va primero (lo ordena la
                             consulta) y marcada: si no, con cien citas en la lista
                             hay que buscar a la clienta a mano justo después de
                             haberla señalado en la pantalla anterior. --}}
                        <tr @class(['spg-fila-elegida' => $elegida])>
                            <td>
                                {{ fecha($c->fecha_hora) }}
                                @if ($elegida)
                                    {{-- Texto y no un badge: `.e-warn` es de contorno, y sobre
                                         el fondo teñido de la fila quedaba en 3,15:1 en el tema
                                         oscuro. Así hereda el color de la celda: 12,9:1. --}}
                                    <div style="font-size:.78rem"><i class="bi bi-arrow-return-right"></i> la que elegiste</div>
                                @endif
                            </td>
                            <td>{{ $c->cliente }}</td>
                            <td class="text-muted-warm">{{ $c->servicios ?: '—' }}</td>
                            {{-- El total con lo que ya está pago por puntos y con el
                                 descuento que la base va a aplicar sola. Antes salía la
                                 suma pelada de los servicios, que casi nunca es lo que
                                 se termina cobrando. --}}
                            <td class="text-end">
                                @php
                                    $base = (float) $c->total - (float) ($c->canjeado ?? 0);
                                    $desc = min((float) ($c->desc_monto ?? 0), $base);
                                @endphp
                                @if ((float) ($c->canjeado ?? 0) > 0 || $desc > 0)
                                    <span class="text-muted-warm" style="text-decoration:line-through">
                                        {{ money($c->total) }}</span><br>
                                    @if ((float) ($c->canjeado ?? 0) > 0)
                                        <span class="text-muted-warm" style="font-size:.75rem">
                                            canje − {{ money($c->canjeado) }}</span><br>
                                    @endif
                                    @if ($desc > 0)
                                        <span class="text-muted-warm" style="font-size:.75rem">
                                            descuento − {{ money($desc) }}</span><br>
                                    @endif
                                    <strong class="txt-oro">{{ money($base - $desc) }}</strong>
                                @else
                                    {{ money($c->total) }}
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($tipos)
                                    <form method="post" action="{{ route('facturacion.emitir.guardar') }}"
                                          class="d-flex gap-1 justify-content-end">
                                        @csrf
                                        <input type="hidden" name="id_cita" value="{{ $c->id_cita }}">
                                        {{-- **Las dos formas de la factura, nombradas.**
                                             «Factura (se declara)» era una sola opción y
                                             dejaba fuera el caso de todos los días: la
                                             clienta que no da su RUC. Esa es la factura
                                             **innominada**, que la DNIT admite por debajo
                                             del tope y que va sin datos del receptor.

                                             Son dos opciones y no una casilla aparte
                                             porque para quien cobra son dos cosas
                                             distintas —una pide los datos y la otra no—
                                             aunque el tipo de comprobante sea el mismo. El
                                             sufijo `-inn` viaja pegado al id. --}}
                                        <select class="form-select form-select-sm" name="id_tipo_comprobante" style="width:auto">
                                            @foreach ($tipos as $t)
                                                @php $declarable = $sifen && \App\Servicios\Sifen::esElectronico((int) $t->id_tipo_comprobante); @endphp
                                                <option value="{{ $t->id_tipo_comprobante }}"
                                                    @selected((int) $t->id_tipo_comprobante === $tipoDefecto)>
                                                    {{ $t->nombre }}@if ($declarable) declarada @endif
                                                </option>
                                                @if ($declarable)
                                                    <option value="{{ $t->id_tipo_comprobante }}-inn">
                                                        {{ $t->nombre }} sin nombre
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <select class="form-select form-select-sm" name="id_condicion_venta" style="width:auto">
                                            @foreach ($condiciones as $cv)
                                                <option value="{{ $cv->id_condicion_venta }}">{{ $cv->nombre }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-sm btn-oro"
                                                data-confirmar="Vas a emitir el comprobante de {{ $c->cliente }} por {{ money($c->total) }} (monto final, con descuentos). La numeración de la DNIT no se puede reutilizar. Si el envío electrónico falla, el comprobante queda guardado y podés reintentarlo desde su detalle. ¿Confirmás?">
                                            <i class="bi bi-receipt-cutoff"></i> Emitir
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="spg-vacio">
                                    <i class="bi bi-receipt"></i>
                                    <div class="t">No hay citas atendidas pendientes de facturar.</div>
                                    <div class="d">Registrá primero la atención de una cita.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
