@extends('layout.app')

@section('titulo', 'Arqueo de caja')

@section('contenido')
    <x-encabezado sub="Cómo cerró cada caja: lo que debería haber, lo que se contó y la diferencia."
        :accion="['ruta' => 'facturacion.caja', 't' => 'Abrir o cerrar', 'ic' => 'safe']" />

    {{-- El resumen sale de las mismas filas que la tabla, así que no puede
         contradecirla. --}}
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

    <div class="spg-panel">
        <h2 class="spg-form-titulo mb-2"><i class="bi bi-clock-history"></i> Historial de caja</h2>

        {{-- **Apertura y cierre son dos registros distintos.** Estaban en una
             sola fila, así que la apertura de una caja todavía abierta salía
             con las columnas del arqueo en blanco y no se entendía si faltaba
             contarla o si el conteo había dado cero.

             **Y el monto inicial no comparte columna con el esperado**: son dos
             cosas que no se comparan entre sí, y juntas bajo un rótulo doble no
             se entiende cuál es cuál. Cada una tiene la suya y la que no aplica
             va en raya. --}}
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Cuándo</th><th>Qué</th><th>Responsable</th>
                        <th class="text-end">Monto inicial</th>
                        <th class="text-end">Esperado</th>
                        <th class="text-end">Contado</th>
                        <th class="text-end">Diferencia</th>
                        <th>Observación</th><th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        // Un registro por apertura y otro por cierre, ordenados
                        // por su propia fecha: así se lee la sucesión real del
                        // mostrador y no una tabla con mitades vacías.
                        $mov = [];
                        foreach ($rows as $c) {
                            $mov[] = ['t' => 'apertura', 'c' => $c, 'cuando' => $c->fecha_apertura];
                            if ($c->fecha_cierre) {
                                $mov[] = ['t' => 'cierre', 'c' => $c, 'cuando' => $c->fecha_cierre];
                            }
                        }
                        usort($mov, fn ($a, $b) => strcmp((string) $b['cuando'], (string) $a['cuando']));
                    @endphp

                    @forelse ($mov as $m)
                        @php $c = $m['c']; @endphp
                        <tr>
                            <td style="white-space:nowrap">{{ fecha($m['cuando']) }}</td>
                            @if ($m['t'] === 'apertura')
                                <td><span class="badge-estado e-prog"><i class="bi bi-unlock"></i> Apertura</span></td>
                                <td class="text-muted-warm">{{ $c->responsable ?? '—' }}</td>
                                <td class="text-end">{{ money($c->monto_inicial) }}</td>
                                <td class="text-end text-muted-warm">—</td>
                                <td class="text-end text-muted-warm">—</td>
                                <td class="text-end text-muted-warm">—</td>
                                <td class="text-muted-warm" style="font-size:.84rem">
                                    {{ $c->observacion_apertura ?: '—' }}
                                </td>
                                <td>{!! estado_badge($c->estado) !!}</td>
                            @else
                                <td><span class="badge-estado e-muted"><i class="bi bi-lock"></i> Cierre</span></td>
                                <td class="text-muted-warm">{{ $c->arqueo_por ?: ($c->responsable ?? '—') }}</td>
                                <td class="text-end text-muted-warm">—</td>
                                <td class="text-end">{{ money($c->saldo) }}</td>
                                <td class="text-end">
                                    {{-- «—» y no «Gs. 0» cuando no se contó: un cero ahí
                                         se lee como «cuadró». --}}
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
                                    {{-- El motivo sólo se exige cuando NO cuadra, así que
                                         nombrarlo con la caja cuadrada sería pedir algo
                                         que el sistema no pidió. --}}
                                    @if ($c->diferencia !== null && abs((float) $c->diferencia) >= 0.01)
                                        <div>{{ $c->motivo_diferencia ?: 'sin motivo' }}</div>
                                    @endif
                                    {{ $c->observacion_cierre ?: ($c->diferencia === null || abs((float) $c->diferencia) < 0.01 ? '—' : '') }}
                                </td>
                                <td>{!! estado_badge($c->estado) !!}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="spg-vacio">
                                    <i class="bi bi-safe"></i>
                                    <div class="t">Todavía no se abrió ninguna caja acá</div>
                                    <div class="d">El arqueo aparece cuando se cierra la primera.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="text-muted-warm mt-3 mb-0" style="font-size:.82rem">
            <i class="bi bi-info-circle"></i>
            <strong>Lo que no está en el cajón no se cuenta.</strong> Lo cobrado por tarjeta o
            transferencia se registra igual, pero va a la cuenta del salón: contarlo haría que
            el arqueo no cierre nunca.
        </p>
    </div>
@endsection
