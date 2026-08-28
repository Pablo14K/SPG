{{-- **De qué caja sale la plata, o a cuál entra.**

     Con dos cajones abiertos en el mismo local, dejar que el sistema elija manda
     el movimiento al arqueo de otra persona y **nada lo dice**: quien cuenta al
     cerrar se encuentra con plata que no cobró, o le falta la que sí pagó. El
     orden automático de los procedimientos sigue de red, pero adivinar no es
     una decisión del sistema.

     **Con una sola no se pregunta, pero SÍ se dice cuál es.** La respuesta sería
     una, así que hacer elegir es hacer perder un clic — lo que no puede pasar es
     que quien paga no sepa de qué cajón salió.

     Parámetros:
     · $cajas   las abiertas del local que corresponda (`Caja::abiertasDe`)
     · $uid     identificador único, para que dos modales no compartan el `id`
     · $rotulo  la pregunta, que cambia según entre o salga plata
     · $ayuda   una línea extra debajo del combo (opcional)
     · $compacto  sólo el `select`, sin rótulo ni ayuda: para una fila de tabla,
                  donde un bloque de tres renglones no entra --}}
@php
    $lista = $cajas ?? [];
    $u = $uid ?? 'x';
    $pregunta = $rotulo ?? '¿A qué caja entra?';
    $chico = ! empty($compacto);
@endphp

@if ($chico)
    {{-- En la fila, el rótulo va en el `aria-label` y en el `title`: el espacio
         no da para tres renglones y sin nombre el combo no se entiende. --}}
    @if (count($lista) > 1)
        <select class="form-select form-select-sm" name="id_caja" style="width:150px"
                aria-label="{{ $pregunta }}" title="{{ $pregunta }}" required>
            @foreach ($lista as $ca)
                <option value="{{ $ca->id_caja }}" @selected($ca->es_mia)>
                    {{ $ca->nombre }}@if ($ca->es_mia) · la tuya @endif
                </option>
            @endforeach
        </select>
    @elseif (count($lista) === 1)
        <input type="hidden" name="id_caja" value="{{ $lista[0]->id_caja }}">
    @endif
@elseif (count($lista) > 1)
    <div class="mb-3">
        <label class="form-label" for="cajaSel{{ $u }}">{{ $pregunta }}</label>
        <select class="form-select form-select-sm" name="id_caja" id="cajaSel{{ $u }}" required>
            @foreach ($lista as $ca)
                <option value="{{ $ca->id_caja }}" @selected($ca->es_mia)>
                    {{ $ca->nombre }}@if ($ca->responsable) · {{ $ca->responsable }}@endif
                    @if ($ca->es_mia) · la tuya @endif
                </option>
            @endforeach
        </select>
        @if (! empty($ayuda))
            <div class="form-text">{{ $ayuda }}</div>
        @endif
    </div>
@elseif (count($lista) === 1)
    <input type="hidden" name="id_caja" value="{{ $lista[0]->id_caja }}">
    <div class="form-text mb-3">
        <i class="bi bi-safe"></i>
        Caja: <strong>{{ $lista[0]->nombre }}</strong>@if ($lista[0]->responsable) · abierta
        por {{ $lista[0]->responsable }}@endif
    </div>
@endif
