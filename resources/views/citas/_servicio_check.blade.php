{{-- Una casilla de servicio. Se usa dos veces desde «Registrar atención» —lo
     agendado y lo que se agrega en el sillón— y por eso vive aparte: si se
     escribe dos veces, tarde o temprano una de las dos se queda vieja. --}}
@php
    // **Qué de esta cita le toca cerrar a quien mira.**
    //
    //   · `$cerrado`  esa parte ya terminó: se muestra y no se toca.
    //   · `$ajeno`    está en la cita y es de otra profesional. El
    //                 Administrador y el Asistente cierran la de cualquiera;
    //                 una profesional, sólo la suya.
    //
    // Un servicio que NO está en la cita nunca es ajeno: es el que se agrega en
    // el sillón, y ése lo puede sumar quien esté atendiendo.
    $cerrado = ! empty($s->terminado_en);
    $ajeno = ! ($puedeTodo ?? true)
             && (bool) $s->agendado
             && ! in_array((int) $s->id_servicio, $mias ?? [], true);
    $trabado = (bool) ($factura ?? null) || $cerrado || $ajeno;
@endphp
<div class="form-check">
    <input class="form-check-input srvAt" type="checkbox" name="servicios[]"
           value="{{ $s->id_servicio }}" id="sa{{ $s->id_servicio }}"
           data-nombre="{{ $s->nombre }}"
           {{-- El precio viaja en el marcado para que el resumen de abajo
                pueda sumarlo sin volver a preguntarle al servidor. --}}
           data-precio="{{ (float) $s->precio }}"
           @checked($s->agendado || $s->ya) @disabled($trabado)>
    <label class="form-check-label" for="sa{{ $s->id_servicio }}">
        {{ $s->nombre }}
        <span class="text-muted-warm">· {{ money($s->precio) }} · {{ $s->categoria }}</span>
        @if ($cerrado)
            {{-- **Cerrado no es lo mismo que registrado.** Registrado dice que
                 hay una atención escrita; cerrado dice que esa profesional ya
                 terminó y su agenda quedó libre desde esa hora. --}}
            <span class="badge-estado e-ok">cerrado {{ fecha($s->terminado_en, 'H:i') }}</span>
        @elseif ($s->ya)<span class="badge-estado e-ok">ya registrado</span>
        @elseif ($s->agendado)<span class="badge-estado e-prog">agendado</span>@endif
        @if ($ajeno && ! $cerrado)
            <span class="badge-estado e-muted">de otra profesional</span>
        @endif
    </label>

    {{-- **Quién lo hace.** Un servicio agregado en el sillón lo puede atender
         otra persona —la manicura mientras siguen con el color—, y sin esto
         quedaba a nombre de quien figura en la cita: la comisión se le
         atribuía a quien no lo hizo, que es el hallazgo AG-02 otra vez.

         Vacío = lo hace quien ya lo tenía asignado, o el profesional de la
         cita. Aparece con su casilla, como en Nueva cita. --}}
    @if (! $trabado && ($profs ?? []))
        <select class="form-select form-select-sm mt-1" style="max-width:230px"
                name="prof_realiza[{{ $s->id_servicio }}]"
                data-prof-de="#sa{{ $s->id_servicio }}">
            <option value="0">lo hace quien lo tenía</option>
            @foreach ($profs as $p)
                <option value="{{ $p->id_usuario }}"
                    @selected((int) ($s->id_usuario ?? 0) === (int) $p->id_usuario)>{{ $p->nombre }}</option>
            @endforeach
        </select>
    @endif
</div>
