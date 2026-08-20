{{-- Una casilla de servicio. Se usa dos veces desde «Registrar atención» —lo
     agendado y lo que se agrega en el sillón— y por eso vive aparte: si se
     escribe dos veces, tarde o temprano una de las dos se queda vieja. --}}
<div class="form-check">
    <input class="form-check-input srvAt" type="checkbox" name="servicios[]"
           value="{{ $s->id_servicio }}" id="sa{{ $s->id_servicio }}"
           data-nombre="{{ $s->nombre }}"
           @checked($s->agendado || $s->ya) @disabled((bool) $factura)>
    <label class="form-check-label" for="sa{{ $s->id_servicio }}">
        {{ $s->nombre }}
        <span class="text-muted-warm">· {{ money($s->precio) }} · {{ $s->categoria }}</span>
        @if ($s->ya)<span class="badge-estado e-ok">ya registrado</span>
        @elseif ($s->agendado)<span class="badge-estado e-prog">agendado</span>@endif
    </label>
</div>
