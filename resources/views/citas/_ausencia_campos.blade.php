{{--
    Los campos de una excepción de agenda.

    **Es UN partial para el alta y para la edición**, por el motivo de siempre:
    dos formularios con los mismos campos se desfasan —se agrega uno de un lado
    y el otro se queda atrás— y este proyecto ya pagó eso más de una vez.

    `$a` es la fila cuando se está editando y null cuando se está creando; `$pfx`
    hace únicos los `id` del marcado, porque en la pantalla conviven el
    formulario de alta y un modal por renglón.
--}}
@php
    // Es un `@include`, no un componente, así que las variables llegan por el
    // ámbito: `$profs`, `$sucursales` y `$tipos` los pone la vista, y estos dos
    // pueden faltar.
    $a = $a ?? null;
    $pfx = $pfx ?? '';
@endphp

@php
    // Al editar mandan los datos de la fila; al crear, lo que quedó de un
    // intento rechazado (`old`). Nunca los dos: `old()` en un modal de edición
    // le metería a TODAS las filas lo que se tipeó en el alta.
    $val = fn ($campo, $def = '') => $a ? ($a->$campo ?? $def) : old($campo, $def);
    $dt = fn ($v) => $v ? str_replace(' ', 'T', substr((string) $v, 0, 16)) : '';
@endphp

<div class="mb-3">
    <label class="form-label" for="{{ $pfx }}id_usuario">¿A quién afecta?</label>
    <select class="form-select" id="{{ $pfx }}id_usuario" name="id_usuario">
        <option value="0">Todo el salón (feriado)</option>
        @foreach ($profs as $p)
            <option value="{{ $p->id_usuario }}"
                @selected((int) $val('id_usuario') === (int) $p->id_usuario)>{{ $p->nombre }}</option>
        @endforeach
    </select>
</div>

{{-- **En qué local.** Sólo se dibuja con más de uno: preguntar algo de una
     única respuesta hace perder un clic. --}}
@if (count($sucursales) > 1)
    <div class="mb-3">
        <label class="form-label" for="{{ $pfx }}id_sucursal">¿En qué sucursal?</label>
        <select class="form-select" id="{{ $pfx }}id_sucursal" name="id_sucursal">
            <option value="0">En todas</option>
            @foreach ($sucursales as $s)
                <option value="{{ $s->id_sucursal }}"
                    @selected((int) $val('id_sucursal') === (int) $s->id_sucursal)>{{ $s->nombre }}</option>
            @endforeach
        </select>
        <x-ayuda>Un feriado del salón va en todas. La licencia de una persona que trabaja en varios locales, también — si no, sigue apareciendo disponible en los otros.</x-ayuda>
    </div>
@endif

<div class="mb-3">
    <label class="form-label" for="{{ $pfx }}id_tipo_ausencia">Tipo *</label><x-ayuda campo="id_tipo_ausencia" />
    <select class="form-select" id="{{ $pfx }}id_tipo_ausencia" name="id_tipo_ausencia" required>
        @foreach ($tipos as $t)
            <option value="{{ $t->id_tipo_ausencia }}"
                @selected((int) $val('id_tipo_ausencia') === (int) $t->id_tipo_ausencia)>{{ $t->nombre }}</option>
        @endforeach
    </select>
</div>

<div class="row g-2 mb-3">
    <div class="col-6">
        <label class="form-label" for="{{ $pfx }}fecha_inicio">Desde *</label><x-ayuda campo="fecha_inicio" />
        <input type="datetime-local" class="form-control" id="{{ $pfx }}fecha_inicio"
               name="fecha_inicio" required value="{{ $dt($val('fecha_inicio')) }}">
    </div>
    <div class="col-6">
        <label class="form-label" for="{{ $pfx }}fecha_fin">Hasta *</label><x-ayuda campo="fecha_fin" />
        <input type="datetime-local" class="form-control" id="{{ $pfx }}fecha_fin"
               name="fecha_fin" required value="{{ $dt($val('fecha_fin')) }}">
    </div>
</div>

<div class="mb-3">
    <label class="form-label" for="{{ $pfx }}motivo">Motivo</label><x-ayuda campo="motivo" />
    <input class="form-control" id="{{ $pfx }}motivo" name="motivo" maxlength="150"
           value="{{ $val('motivo') }}" placeholder="Ej. Licencia médica">
</div>
