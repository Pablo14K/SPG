@extends('layout.app')

@section('titulo', 'Reservar cita')

@section('contenido')
    <div class="spg-page-head">
        <a class="spg-back" href="{{ route('portal.index') }}"><i class="bi bi-arrow-left"></i> Mi portal</a>
        <h1 class="mt-1">Reservar una cita</h1>
        <div class="sub">Elegí los servicios y te mostramos los horarios que quedan libres de verdad.</div>
    </div>

    <div class="spg-panel" style="max-width:760px">
        <form method="post" action="{{ route('portal.guardar_reserva') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label">¿Qué te querés hacer? *</label>
                <div class="spg-check-lista">
                    @foreach ($servicios as $s)
                        <div class="d-flex align-items-center gap-2 flex-wrap py-1">
                            <div class="form-check mb-0 flex-grow-1">
                                <input class="form-check-input srv" type="checkbox" name="servicios[]"
                                       value="{{ $s->id_servicio }}" id="srv{{ $s->id_servicio }}">
                                <label class="form-check-label" for="srv{{ $s->id_servicio }}">
                                    {{ $s->nombre }}
                                    <span class="text-muted-warm">
                                        · {{ money($s->precio) }} · {{ (int) $s->duracion_min }} min</span>
                                </label>
                            </div>
                            <select class="form-select form-select-sm" style="width:auto"
                                    name="prof_servicio[{{ $s->id_servicio }}]">
                                <option value="0">quien me atienda</option>
                                @foreach ($profs as $p)
                                    <option value="{{ $p->id_usuario }}">con {{ $p->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Acá había un «¿Con quién?» para toda la cita, y confundía: cada
                 servicio ya trae su propio selector arriba, con «quien me
                 atienda» por defecto. Eran dos formas de contestar lo mismo, y
                 no era evidente cuál ganaba. Sin preferencia, el profesional lo
                 asigna el servidor al reservar (Agenda::profesionalLibre). --}}

            <div class="mb-3">
                <label class="form-label">¿Cuándo? *</label>
                <div id="avisoAgenda" class="text-muted-warm" style="font-size:.85rem">
                    Elegí primero los servicios para ver los horarios disponibles.
                </div>
                <div id="dias" class="spg-dias mt-2"></div>
                <div id="horas" class="spg-horas mt-2"></div>
                <input type="hidden" name="fecha_hora" id="fecha_hora">
            </div>

            <div class="mb-3">
                <label class="form-label" for="observaciones">¿Algo que quieras avisarnos?</label>
                <textarea class="form-control" id="observaciones" name="observaciones" rows="2" maxlength="300"></textarea>
            </div>

            <button class="btn btn-oro" id="btnReservar" disabled>
                <i class="bi bi-calendar-check"></i> Reservar</button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var url = @json(route('portal.disponibilidad'));
    // Si app.js no cargó, la reserva tiene que seguir andando igual: la
    // señal de carga es un adorno, no parte del funcionamiento.
    var SPGCarga = window.SPGCarga || { envolver: function (p) { return p; } };
    var avisoEl = document.getElementById('avisoAgenda'),
        diasEl = document.getElementById('dias'),
        horasEl = document.getElementById('horas'),
        campo = document.getElementById('fecha_hora'),
        btn = document.getElementById('btnReservar'),
        diaElegido = null;

    function elegidos() {
        return Array.prototype.slice.call(document.querySelectorAll('.srv:checked'))
            .map(function (c) { return c.value; });
    }

    // Con qué agenda se consultan los huecos. Si TODOS los servicios elegidos
    // piden a la misma persona, se miran sus horarios; si piden a varias, o si
    // alguno quedó en «quien me atienda», se juntan los de todo el equipo y el
    // servidor asigna al reservar. Antes esto salía de un combo aparte.
    function profElegido() {
        var pedidos = elegidos().map(function (id) {
            var sel = document.querySelector('[name="prof_servicio[' + id + ']"]');
            return sel ? sel.value : '0';
        });
        var distintos = pedidos.filter(function (v, i, a) { return a.indexOf(v) === i; });

        return (distintos.length === 1 && distintos[0] !== '0') ? distintos[0] : 0;
    }

    function params(extra) {
        var p = new URLSearchParams();
        elegidos().forEach(function (s) { p.append('servicios[]', s); });
        p.append('id_usuario', profElegido());
        for (var k in (extra || {})) { p.append(k, extra[k]); }
        return p;
    }

    function cargarDias() {
        diasEl.innerHTML = ''; horasEl.innerHTML = ''; campo.value = ''; btn.disabled = true;
        if (!elegidos().length) {
            avisoEl.textContent = 'Elegí primero los servicios para ver los horarios disponibles.';
            return;
        }
        avisoEl.innerHTML = '<span class="spg-cargando-texto">'
            + '<span class="spg-spinner"></span> Buscando días con lugar…</span>';

        SPGCarga.envolver(
            fetch(url + '?' + params().toString(), { headers: { 'Accept': 'application/json' } }), diasEl)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) { avisoEl.textContent = d.motivo || 'No se pudo consultar la agenda.'; return; }
                if (!d.dias || !d.dias.length) {
                    avisoEl.textContent = 'No quedan días con lugar. Probá con otro profesional o con menos servicios.';
                    return;
                }
                avisoEl.textContent = 'Tu cita dura ' + d.duracion + ' minutos. Elegí el día:';
                d.dias.forEach(function (f) {
                    var b = document.createElement('button');
                    b.type = 'button'; b.className = 'spg-chip'; b.title = f;
                    b.textContent = f.split('-').reverse().slice(0, 2).join('/');
                    b.addEventListener('click', function () { elegirDia(f, b); });
                    diasEl.appendChild(b);
                });
            });
    }

    function elegirDia(f, boton) {
        diaElegido = f;
        Array.prototype.forEach.call(diasEl.children, function (c) { c.classList.remove('activo'); });
        boton.classList.add('activo');
        horasEl.innerHTML = '<span class="spg-cargando-texto">'
            + '<span class="spg-spinner"></span> Buscando horarios…</span>';
        campo.value = ''; btn.disabled = true;

        SPGCarga.envolver(
            fetch(url + '?' + params({ fecha: f }).toString(), { headers: { 'Accept': 'application/json' } }), horasEl)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                horasEl.innerHTML = '';
                if (!d.ok || !d.horas || !d.horas.length) {
                    horasEl.textContent = 'Ese día ya no tiene horarios libres.';
                    return;
                }
                d.horas.forEach(function (h) {
                    var b = document.createElement('button');
                    b.type = 'button'; b.className = 'spg-chip'; b.textContent = h.hora;
                    b.addEventListener('click', function () {
                        campo.value = diaElegido + ' ' + h.hora + ':00';
                        Array.prototype.forEach.call(horasEl.children, function (c) { c.classList.remove('activo'); });
                        b.classList.add('activo');
                        btn.disabled = false;
                    });
                    horasEl.appendChild(b);
                });
            });
    }

    document.querySelectorAll('.srv').forEach(function (c) { c.addEventListener('change', cargarDias); });
    // Cambiar de profesional en un servicio cambia los horarios posibles, así
    // que la agenda se vuelve a pedir igual que al tildar un servicio.
    document.querySelectorAll('[name^="prof_servicio["]').forEach(function (s) {
        s.addEventListener('change', cargarDias);
    });
})();
</script>
@endpush
