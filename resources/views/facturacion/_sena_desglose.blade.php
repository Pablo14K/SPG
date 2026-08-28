{{-- **De dónde sale la seña, servicio por servicio.**

     Un total suelto —«seña Gs. 210.000»— no se puede comprobar: la clienta no
     sabe si es de un servicio o de tres ni qué porcentaje se le aplicó, y quien
     confirma el pago en el mostrador tampoco. Los dos ven el mismo desglose, y
     por eso es UN bloque y no dos: escrito dos veces, uno de los dos se queda
     atrás y entonces el salón y la clienta discuten sobre números distintos.

     Parámetros:
     · $desglose  lo que devuelve `App\Servicios\Sena::desglose()`
     · $yaPuesta  cuánto se registró ya (opcional), para descontarlo --}}
@php
    $filas = $desglose['filas'] ?? [];
    $total = (float) ($desglose['total'] ?? 0);
    $lista = (float) ($desglose['lista'] ?? 0);
    $puesta = (float) ($yaPuesta ?? 0);
@endphp

<div class="table-responsive mb-2">
    <table class="table table-sm align-middle mb-0" style="font-size:.85rem">
        <thead>
            <tr>
                <th>Servicio</th>
                <th class="text-end">Precio</th>
                <th class="text-end">Seña</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $f)
                <tr>
                    <td>
                        {{ $f->nombre }}
                        @if ((int) $f->canjeado > 0)
                            {{-- Ya está pagado con puntos: pedirle una garantía
                                 sería cobrarle dos veces. --}}
                            <span class="badge-estado e-ok">canjeado</span>
                        @endif
                    </td>
                    <td class="text-end text-muted-warm">{{ money($f->precio) }}</td>
                    <td class="text-end">
                        @if ((float) $f->sena > 0)
                            {{ money($f->sena) }}
                            <div class="text-muted-warm" style="font-size:.78rem">
                                {{ (int) $f->sena_porcentaje }} % del precio</div>
                        @else
                            {{-- **«—» y no «Gs. 0»**: cero se lee como «la seña de
                                 este servicio es cero», y lo que pasa es que no
                                 pide ninguna. --}}
                            <span class="text-muted-warm">no pide</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="border-top:2px solid var(--gris-calido)">
                <th>Total de la cita</th>
                <th class="text-end">{{ money($lista) }}</th>
                <th class="text-end txt-oro">{{ money($total) }}</th>
            </tr>
            @if ($puesta > 0)
                <tr>
                    <td colspan="2" class="text-muted-warm">Ya registrado</td>
                    <td class="text-end text-muted-warm">− {{ money($puesta) }}</td>
                </tr>
                <tr>
                    <th colspan="2">Falta</th>
                    <th class="text-end txt-oro">{{ money(max(0, $total - $puesta)) }}</th>
                </tr>
            @endif
        </tfoot>
    </table>
</div>
