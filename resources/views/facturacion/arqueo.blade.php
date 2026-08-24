@extends('layout.app')

@section('titulo', 'Arqueo de caja')

@section('contenido')
    <x-encabezado sub="Cómo cerró cada caja: lo que debería haber, lo que se contó y la diferencia."
        :accion="['ruta' => 'facturacion.caja', 't' => 'Abrir o cerrar', 'ic' => 'safe']" />

    {{-- Las cuatro cifras salen de las últimas cajas cerradas, que es lo que
         contesta «¿venimos cuadrando?» sin tener que abrir el detalle. --}}
    <div class="spg-metrics spg-metrics-compacto mb-3">
        <div class="spg-metric">
            <div class="lbl">Cajas cerradas</div>
            <div class="val">{{ $cerradas }}</div>
        </div>
        <div class="spg-metric">
            <div class="lbl">Cuadraron</div>
            <div class="val txt-ok">{{ $cuadran }}</div>
        </div>
        <div class="spg-metric">
            <div class="lbl">Sin conteo</div>
            <div class="val">{{ $sinConteo }}</div>
            {{-- **NULL no es cero.** Son las cajas cerradas antes de que
                 existiera el arqueo: un 0 ahí se leería como «cuadró», que es
                 justo lo que no se sabe. --}}
            @if ($sinConteo > 0)
                <div class="spg-metric-pie">cerradas sin contar el cajón</div>
            @endif
        </div>
        <div class="spg-metric">
            <div class="lbl">Diferencia acumulada</div>
            <div class="val @if (abs($difTotal) >= 0.01) @if ($difTotal < 0) txt-no @else txt-oro @endif @endif">
                {{ abs($difTotal) < 0.01 ? money(0) : ($difTotal > 0 ? '+ ' : '− ') . money(abs($difTotal)) }}
            </div>
            <div class="spg-metric-pie">de las que no cuadraron</div>
        </div>
    </div>


{{-- **El desglose por medio vive acá y no en «Apertura y cierre».**

     Es lo que separa la plata que TIENE que estar en el cajón de la que fue a
     la cuenta — o sea la mitad de la pregunta que esta pantalla contesta. En la
     de abrir y cerrar era un bloque más, en una pantalla donde nadie lo iba a
     buscar: ahí se abre y se cierra, acá se cuadra. --}}
@if ($abierta)
    <div class="spg-panel mb-3">
        <h2 class="spg-form-titulo mb-2"><i class="bi bi-cash-stack"></i> La caja abierta, por medio de pago</h2>
        @if ($porMedio)
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-2">
                    <thead>
                        <tr><th>Medio</th><th>¿Está en el cajón?</th>
                            <th class="text-end">Cobros</th><th class="text-end">Total</th></tr>
                    </thead>
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
@endif

    {{-- **El historial salió de acá, por pedido del usuario.**

         Lo reemplaza la pantalla de Arqueos del módulo de Caja rediseñado, que
         lo lista con filtros —sucursal, caja, fecha, estado— y paginación. Una
         tabla de sesenta filas sin filtros no escala, que es exactamente el
         motivo del rediseño. --}}

    <p class="text-muted-warm mb-0" style="font-size:.82rem">
        <i class="bi bi-info-circle"></i>
        <strong>Lo que no está en el cajón no se cuenta.</strong> Lo cobrado por tarjeta o
        transferencia se registra igual, pero va a la cuenta del salón: contarlo haría que
        el arqueo no cierre nunca.
    </p>
@endsection