{{-- **Los movimientos de HOY de una caja, para el modal.**

     Es la pregunta del mostrador —«¿qué entró y salió de este cajón hoy?»— y se
     contesta sin salir de la pantalla. Para mirar los de la semana pasada está
     Movimientos, con sus filtros y su paginación.

     Sale de las MISMAS cuatro fuentes que el listado: un pago a proveedor es un
     movimiento de caja, y un cobro también.

     Parámetros: $movs (lista) · $cajon (nombre, para el vacío). --}}
@if ($movs)
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0" style="font-size:.86rem">
            <thead>
                <tr>
                    <th>Hora</th><th>Qué</th><th>Medio</th><th class="text-end">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($movs as $m)
                    <tr>
                        <td class="text-muted-warm" style="white-space:nowrap">
                            {{ fecha($m->cuando, 'H:i') }}</td>
                        <td>
                            {{-- El color dice el signo y el texto dice qué es. --}}
                            <span class="badge-estado {{ (int) $m->signo > 0 ? 'e-ok' : 'e-no' }}">
                                {{ $m->detalle }}</span>
                            @unless ($m->activo)
                                <span class="badge-estado e-no">anulado</span>
                            @endunless
                        </td>
                        <td class="text-muted-warm">{{ $m->medio }}</td>
                        <td class="text-end {{ $m->activo ? ((int) $m->signo > 0 ? 'txt-ok' : 'txt-no') : 'text-muted-warm' }}"
                            style="white-space:nowrap;{{ $m->activo ? '' : 'text-decoration:line-through' }}">
                            {{ (int) $m->signo > 0 ? '+' : '−' }} {{ money($m->monto) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    {{-- **Vacío se dice, no se deja el bloque en blanco.** Una tabla sin filas
         es indistinguible de una pantalla rota. --}}
    <div class="spg-vacio">
        <i class="bi bi-list-ul"></i>
        <div class="t">Sin movimientos hoy</div>
        <div class="d">Todavía no entró ni salió nada de {{ $cajon ?? 'esta caja' }} en el día de hoy.</div>
    </div>
@endif
