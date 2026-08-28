@extends('layout.app')

@section('titulo', 'Arqueos')

@section('contenido')

{{-- **Es una tabla, no tarjetas.** Un salón acumula un arqueo por cajón y por
     día, así que a los seis meses son cientos: lo que hace falta es poder
     filtrar y paginar, no que cada uno ocupe más lugar. --}}
<x-encabezado sub="Cómo cerró cada caja: lo que debería haber, lo que se contó y la diferencia."
    :accion="['ruta' => 'facturacion.cajas', 't' => 'Cajas', 'ic' => 'safe']" />

<x-filtros :f="$f" />

{{-- **Las cuatro cifras salen de LO FILTRADO, no del total.** Si se pide un
     local y un mes, «cuántas cuadraron» tiene que hablar de ese local y ese
     mes — un resumen que mide otra cosa que la tabla es peor que no tenerlo. --}}
<div class="spg-metrics spg-metrics-compacto mb-3">
    <div class="spg-metric">
        <div class="lbl">Cajas cerradas</div>
        <div class="val">{{ $cerradas }}</div>
    </div>
    <div class="spg-metric">
        <div class="lbl">Cuadraron</div>
        <div class="val">{{ $cuadran }}</div>
    </div>
    <div class="spg-metric">
        <div class="lbl">Sin conteo</div>
        <div class="val">{{ $sinConteo }}</div>
        <div class="spg-metric-pie">cerradas sin contar el cajón</div>
    </div>
    <div class="spg-metric">
        <div class="lbl">Diferencia acumulada</div>
        <div class="val {{ abs($difTotal) < 0.01 ? '' : ($difTotal < 0 ? 'txt-no' : 'txt-oro') }}">
            {{ money($difTotal) }}</div>
        <div class="spg-metric-pie">de las que no cuadraron</div>
    </div>
</div>

<div class="spg-panel">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    {{-- **Un cierre sin su apertura no se puede juzgar.** «Cerró
                         con Gs. 40.000 de diferencia» significa una cosa si la caja
                         estuvo abierta dos horas y otra si estuvo tres días. --}}
                    <th>Abierta</th><th>Cerrada</th><th>Caja</th><th>Sucursal</th><th>Responsable</th>
                    <th class="text-end">Esperado</th>
                    <th class="text-end">Contado</th>
                    <th class="text-end">Diferencia</th>
                    <th>Detalle</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $c)
                    <tr>
                        <td class="text-muted-warm" style="white-space:nowrap">
                            {{ $c->fecha_apertura ? fecha($c->fecha_apertura, 'd/m/Y H:i') : '—' }}</td>
                        <td style="white-space:nowrap">{{ fecha($c->fecha_cierre, 'd/m/Y H:i') }}</td>
                        <td>{{ $c->caja_nombre }}</td>
                        <td class="text-muted-warm">{{ $c->sucursal_nombre }}</td>
                        <td class="text-muted-warm">{{ $c->arqueo_por ?: ($c->responsable ?? '—') }}</td>
                        <td class="text-end">{{ money($c->saldo ?? 0) }}</td>
                        <td class="text-end">
                            {{-- «—» y no «Gs. 0» cuando no se contó: un cero ahí
                                 se lee como «cuadró», que es justo lo que no se
                                 sabe de las cajas cerradas antes del arqueo. --}}
                            {{ $c->monto_contado === null ? '—' : money($c->monto_contado) }}
                        </td>
                        <td class="text-end" style="white-space:nowrap">
                            @if ($c->diferencia === null)
                                <span class="text-muted-warm">sin conteo</span>
                            @elseif (abs((float) $c->diferencia) < 0.01)
                                <span class="badge-estado e-ok">cuadra</span>
                            @elseif ((float) $c->diferencia > 0)
                                <span class="badge-estado e-warn">+ {{ money($c->diferencia) }}</span>
                            @else
                                <span class="badge-estado e-no">− {{ money(abs((float) $c->diferencia)) }}</span>
                            @endif
                        </td>
                        <td class="text-muted-warm" style="font-size:.84rem">
                            {{-- El motivo se exige SÓLO cuando no cuadra, así que
                                 nombrarlo con la caja cuadrada sería pedir algo
                                 que el sistema no pidió. --}}
                            @if ($c->diferencia !== null && abs((float) $c->diferencia) >= 0.01)
                                <div>{{ $c->motivo_diferencia ?: 'sin motivo' }}</div>
                            @endif
                            {{ $c->observacion_cierre ?: '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
                            <div class="spg-vacio">
                                <i class="bi bi-clipboard-check"></i>
                                <div class="t">No hay arqueos con esos filtros</div>
                                <div class="d">El arqueo aparece cuando se cierra una caja.</div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<x-paginacion :pag="$pag" :f="$f" />

<p class="text-muted-warm mt-3 mb-0" style="font-size:.82rem">
    <i class="bi bi-info-circle"></i>
    <strong>Lo que no está en el cajón no se cuenta.</strong> Lo cobrado por tarjeta o
    transferencia se registra igual, pero va a la cuenta del salón: contarlo haría que
    el arqueo no cierre nunca.
</p>
@endsection
