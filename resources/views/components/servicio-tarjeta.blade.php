{{--
    Un servicio para elegir al reservar, con su imagen de referencia.

    **Es UN componente para las dos pantallas** —el portal y Nueva cita— por el
    motivo de siempre: copiado, se desfasan. Lo que cambia entre una y otra es
    qué se dibuja al lado (el combo de profesional, el badge de canje), y eso
    entra por el slot.

    **El marcado NO cambia el funcionamiento.** Sigue siendo el mismo checkbox
    con el mismo `name` y los mismos `data-`: lo que cambia es cómo se ve. La
    tarjeta entera es un `<label>`, así que marca el checkbox **sin JavaScript**
    — con `app.js` caído se sigue pudiendo reservar.

        <x-servicio-tarjeta :s="$s" :marcado="…" :id="'srv'.$s->id_servicio">
            … el combo de profesional …
        </x-servicio-tarjeta>
--}}
@props([
    's',                       // la fila del servicio
    'id',                      // id del checkbox, para el `for` del label
    'marcado' => false,
    'nombreCampo' => 'servicios[]',
    'badge' => null,           // texto de un badge extra (ej. «canjeado»)
])

@php
    $img = \App\Servicios\Imagen::url($s->imagen ?? null, 'servicios');

    // **El precio que va a pagar, no el de lista.** El descuento lo decide la
    // base —el mismo criterio que la factura— y la tarjeta sólo lo muestra: la
    // clienta Oro veía Gs. 75.000 y pagaba 67.500, así que lo mejor que el
    // salón le da no se enteraba hasta el mostrador.
    //
    // Viene calculado desde el controlador (`$s->descuento`) para no consultar
    // por tarjeta: con quince servicios serían quince consultas por carga.
    $desc = (float) ($s->descuento ?? 0);
    $conDesc = max(0, (float) $s->precio - $desc);
@endphp

{{-- **La tarjeta ya NO es un `<label>` entero, y ése era el defecto.**

     El combo de profesional vivía adentro de la etiqueta, y aunque la
     especificación dice que un clic sobre contenido interactivo no activa el
     control asociado, en la práctica **el desplegable del navegador no abre**
     en varios celulares: la etiqueta se queda con el toque y lo reenvía al
     checkbox. Es exactamente la misma trampa que el ícono de ayuda ya había
     pagado en la 7.94.0 — «va FUERA del `<label>`, nunca adentro».

     Ahora el `<label>` cubre sólo la foto y el texto —que es lo que hay que
     tocar para elegir el servicio— y lo que cada pantalla agrega queda como
     hermano, fuera de la etiqueta. Se sigue marcando sin JavaScript. --}}
<div class="spg-srv-card {{ $marcado ? 'elegida' : '' }}" data-srv-card="{{ $id }}">
    <label class="spg-srv-toque" for="{{ $id }}">
    <div class="spg-srv-img">
        @if ($img)
            <img src="{{ $img }}" alt="Imagen de referencia de {{ $s->nombre }}" loading="lazy">
        @else
            {{-- **Sin imagen se dice, no se pone una genérica.** Una foto de
                 archivo que no es de este salón promete un resultado que no se
                 puede sostener; el hueco honesto es mejor. --}}
            <div class="spg-srv-sinimg">
                <i class="bi bi-image"></i>
                <span>Sin imagen de referencia</span>
            </div>
        @endif

        <input class="form-check-input srv spg-srv-check" type="checkbox"
               name="{{ $nombreCampo }}" value="{{ $s->id_servicio }}" id="{{ $id }}"
               data-duracion="{{ $s->duracion_min }}"
               data-precio="{{ $desc > 0 ? $conDesc : (float) $s->precio }}"
               @checked($marcado)>
    </div>

    <div class="spg-srv-cuerpo">
        <div class="spg-srv-nombre">{{ $s->nombre }}</div>

        @if (trim((string) ($s->descripcion ?? '')) !== '')
            <div class="spg-srv-desc">{{ $s->descripcion }}</div>
        @endif

        <div class="spg-srv-precio">
            @if ($desc > 0)
                {{-- El de lista tachado al lado: un precio menor sin explicación
                     se lee como un error de la pantalla. --}}
                <s class="spg-srv-lista">{{ money($s->precio) }}</s>
                {{ money($conDesc) }}
                <span class="badge-estado e-warn spg-srv-off">−{{ cant(round($desc / (float) $s->precio * 100)) }}%</span>
            @else
                {{ money($s->precio) }}
            @endif
        </div>
        <div class="spg-srv-dur">
            <i class="bi bi-clock"></i> {{ (int) $s->duracion_min }} min
            @if ($badge)
                <span class="badge-estado e-warn">{{ $badge }}</span>
            @endif
        </div>

    </div>
    </label>

    {{-- Lo que cada pantalla suma: el combo de profesional en las dos, y el
         aviso de seña en el portal. **Va fuera del `<label>`** — ver el
         comentario de arriba. --}}
    @if (trim($slot) !== '')
        <div class="spg-srv-extra">{{ $slot }}</div>
    @endif
</div>
