{{-- Demanda por hora y por día, en el papel.

     Son dos preguntas distintas: la de por hora dice a qué hora reforzar, y la
     de por día, qué días conviene tener más gente. En pantalla van con barras;
     acá van en tabla, que es lo que se lee bien impreso y no gasta tinta. --}}
@if ($ver('demanda'))
    @php
        $dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
                 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
    @endphp

    <h2 style="font-size:1rem;margin:1.2rem 0 .5rem">Demanda por hora</h2>
    <table class="table table-sm">
        <thead><tr><th>Hora</th><th class="text-end">Citas</th><th class="text-end">Atendidas</th>
            <th class="text-end">No vino</th></tr></thead>
        <tbody>
            @forelse ($demanda as $h)
                <tr>
                    <td>{{ str_pad((string) $h->hora, 2, '0', STR_PAD_LEFT) }}:00</td>
                    <td class="text-end">{{ (int) $h->citas }}</td>
                    <td class="text-end">{{ (int) $h->atendidas }}</td>
                    <td class="text-end">{{ (int) $h->ausencias }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Sin citas en el período.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2 style="font-size:1rem;margin:1.2rem 0 .5rem">Demanda por día</h2>
    <table class="table table-sm">
        <thead><tr><th>Día</th><th class="text-end">Citas</th><th class="text-end">Atendidas</th>
            <th class="text-end">No vino</th></tr></thead>
        <tbody>
            @forelse ($demandaDia as $x)
                <tr>
                    <td>{{ $dias[(int) $x->dia] ?? $x->dia }}</td>
                    <td class="text-end">{{ (int) $x->citas }}</td>
                    <td class="text-end">{{ (int) $x->atendidas }}</td>
                    <td class="text-end">{{ (int) $x->ausencias }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Sin citas en el período.</td></tr>
            @endforelse
        </tbody>
    </table>
@endif
