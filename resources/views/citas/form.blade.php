@extends('layout.app')

@section('titulo', 'Nueva cita')

@section('contenido')
    @php use App\Servicios\Permisos; @endphp

    <x-encabezado sub="La fecha no se escribe a mano: se eligen los servicios y el sistema muestra los horarios que quedan libres de verdad." />

    <div class="spg-panel" style="max-width:900px">
        <form method="post" action="{{ route('citas.guardar') }}" id="formCita">
            @csrf

            <div class="row g-3">
                {{-- 1. Cliente --}}
                <div class="col-md-8">
                    <label class="form-label" for="id_cliente">Cliente *</label>
                    <input class="form-control form-control-sm mb-1" data-filtra="#id_cliente"
                           placeholder="Buscar por nombre o cédula…" autocomplete="off">
                    <select class="form-select" id="id_cliente" name="id_cliente" required size="1">
                        <option value="">— Elegí un cliente —</option>
                        @foreach ($clientes as $c)
                            <option value="{{ $c->id_cliente }}"
                                @selected((int) old('id_cliente', $sel_cliente) === (int) $c->id_cliente)>
                                {{ $c->apellido }}, {{ $c->nombre }}
                                @if ($c->cedula) · {{ $c->cedula }} @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                @if (Permisos::puede('clientes.registro'))
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="button" class="btn btn-rapido w-100" data-bs-toggle="modal"
                                data-bs-target="#modalClienteRapido">
                            <i class="bi bi-person-plus"></i> Cliente nuevo
                        </button>
                    </div>
                @endif

                {{-- 2. Profesional --}}
                <div class="col-md-6">
                    <label class="form-label" for="id_usuario">Profesional</label>
                    <select class="form-select" id="id_usuario" name="id_usuario">
                        <option value="0">Sin preferencia (el primero libre)</option>
                        @foreach ($profs as $p)
                            <option value="{{ $p->id_usuario }}"
                                @selected((int) old('id_usuario') === (int) $p->id_usuario)>{{ $p->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- 3. Servicios --}}
                <div class="col-12">
                    <label class="form-label">Servicios *</label>
                    <p class="text-muted-warm mb-2" style="font-size:.8rem">
                        Se puede repartir la cita entre varias personas: elegí quién hace cada servicio.
                        Los que están marcados como <em>exclusivos</em> ocupan a la clienta entera y no se
                        pueden hacer en paralelo con otro exclusivo.
                    </p>

                    <div class="spg-check-lista" id="listaServicios">
                        @foreach ($servicios as $s)
                            <div class="d-flex align-items-center gap-2 flex-wrap py-1">
                                <div class="form-check mb-0 flex-grow-1">
                                    <input class="form-check-input srv" type="checkbox" name="servicios[]"
                                           value="{{ $s->id_servicio }}" id="srv{{ $s->id_servicio }}"
                                           data-duracion="{{ $s->duracion_min }}"
                                           @checked(in_array($s->id_servicio, old('servicios', []), false))>
                                    <label class="form-check-label" for="srv{{ $s->id_servicio }}">
                                        {{ $s->nombre }}
                                        <span class="text-muted-warm">
                                            · {{ money($s->precio) }} · {{ (int) $s->duracion_min }} min
                                        </span>
                                        @if ($s->requiere_exclusividad)
                                            <span class="badge-estado e-warn">exclusivo</span>
                                        @endif
                                    </label>
                                </div>
                                @php $profSel = (int) (old('prof_servicio', [])[$s->id_servicio] ?? 0); @endphp
                                <select class="form-select form-select-sm" style="width:auto"
                                        name="prof_servicio[{{ $s->id_servicio }}]">
                                    <option value="0">lo hace el principal</option>
                                    @foreach ($profs as $p)
                                        <option value="{{ $p->id_usuario }}"
                                            @selected($profSel === (int) $p->id_usuario)>{{ $p->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- 4. Fecha y hora, ofrecidas por el motor de disponibilidad --}}
                <div class="col-12">
                    <label class="form-label">Fecha y hora *</label>
                    <div id="avisoAgenda" class="text-muted-warm" style="font-size:.85rem">
                        Elegí primero los servicios para ver los horarios disponibles.
                    </div>

                    <div id="dias" class="spg-dias mt-2"></div>
                    <div id="horas" class="spg-horas mt-2"></div>

                    <input type="hidden" name="fecha_hora" id="fecha_hora" value="{{ old('fecha_hora') }}">
                </div>

                <div class="col-12">
                    <label class="form-label" for="observaciones">Observaciones</label>
                    <textarea class="form-control" id="observaciones" name="observaciones"
                              rows="2" maxlength="300">{{ old('observaciones') }}</textarea>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-oro" id="btnAgendar" disabled>
                    <i class="bi bi-calendar-check"></i> Agendar
                </button>
                <a class="btn btn-outline-neutro" href="{{ route('citas.agenda') }}">Cancelar</a>
            </div>
        </form>
    </div>

    @if (Permisos::puede('clientes.registro'))
        <div class="modal fade" id="modalClienteRapido" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    {{-- data-borrador: los servicios y el horario ya elegidos
                         vuelven con el redirect en vez de perderse. --}}
                    <form method="post" action="{{ route('citas.cliente_rapido') }}"
                          data-borrador="#formCita">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" style="font-size:1rem">
                                <i class="bi bi-person-plus"></i> Registrar un cliente nuevo</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted-warm" style="font-size:.82rem">
                                Para cuando la persona llega al local sin estar registrada.
                            </p>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label" for="cr_nombre">Nombre *</label>
                                    <input class="form-control" id="cr_nombre" name="nombre" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="cr_apellido">Apellido *</label>
                                    <input class="form-control" id="cr_apellido" name="apellido" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="cr_cedula">Cédula</label>
                                    <input class="form-control" id="cr_cedula" name="cedula">
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="cr_telefono">Teléfono</label>
                                    <input class="form-control" id="cr_telefono" name="telefono">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="cr_email">Email</label>
                                    <input type="email" class="form-control" id="cr_email" name="email">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-oro">Registrar y seguir</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
// ---------------------------------------------------------------------
//  Selector de disponibilidad
//
//  No se deja escribir una fecha a mano: se le pregunta al servidor qué días
//  y qué horas quedan libres de verdad para los servicios elegidos, y solo se
//  ofrecen esos. El servidor vuelve a comprobarlo al guardar, dentro del
//  candado del procedimiento.
// ---------------------------------------------------------------------
(function () {
    var url        = @json(route('citas.disponibilidad'));
    var avisoEl    = document.getElementById('avisoAgenda');
    var diasEl     = document.getElementById('dias');
    var horasEl    = document.getElementById('horas');
    var campoFecha = document.getElementById('fecha_hora');
    var btn        = document.getElementById('btnAgendar');
    var diaElegido = null;
    // Si app.js no cargó, agendar tiene que seguir andando igual: la señal
    // de carga es un adorno, no parte del funcionamiento.
    var SPGCarga   = window.SPGCarga || { envolver: function (p) { return p; } };

    function serviciosElegidos() {
        return Array.prototype.slice
            .call(document.querySelectorAll('.srv:checked'))
            .map(function (c) { return c.value; });
    }

    function parametros(extra) {
        var p = new URLSearchParams();
        serviciosElegidos().forEach(function (s) { p.append('servicios[]', s); });
        p.append('id_usuario', document.getElementById('id_usuario').value || 0);
        for (var k in (extra || {})) { p.append(k, extra[k]); }
        return p;
    }

    function limpiar() {
        diasEl.innerHTML = '';
        horasEl.innerHTML = '';
        campoFecha.value = '';
        btn.disabled = true;
    }

    function cargarDias() {
        limpiar();
        if (!serviciosElegidos().length) {
            avisoEl.textContent = 'Elegí primero los servicios para ver los horarios disponibles.';
            return;
        }
        // El cálculo mira turnos, citas y ausencias de 60 días: con la agenda
        // cargada tarda, y sin señal parece que el sistema se quedó.
        avisoEl.innerHTML = '<span class="spg-cargando-texto">'
            + '<span class="spg-spinner"></span> Buscando días con lugar…</span>';

        SPGCarga.envolver(
            fetch(url + '?' + parametros().toString(), { headers: { 'Accept': 'application/json' } }), diasEl)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) { avisoEl.textContent = d.motivo || 'No se pudo consultar la agenda.'; return; }
                if (!d.dias || !d.dias.length) {
                    avisoEl.textContent = 'No quedan días con lugar en los próximos dos meses. '
                                        + 'Probá con otro profesional o con menos servicios.';
                    return;
                }
                avisoEl.textContent = 'La cita dura ' + d.duracion + ' minutos. Elegí el día:';
                d.dias.forEach(function (f) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'spg-chip';
                    b.textContent = f.split('-').reverse().slice(0, 2).join('/');
                    b.title = f;
                    b.addEventListener('click', function () { elegirDia(f, b); });
                    diasEl.appendChild(b);
                });
            })
            .catch(function () { avisoEl.textContent = 'No se pudo consultar la agenda.'; });
    }

    function elegirDia(f, boton) {
        diaElegido = f;
        Array.prototype.forEach.call(diasEl.children, function (c) { c.classList.remove('activo'); });
        boton.classList.add('activo');
        horasEl.innerHTML = '<span class="spg-cargando-texto">'
            + '<span class="spg-spinner"></span> Buscando horarios…</span>';
        campoFecha.value = '';
        btn.disabled = true;

        SPGCarga.envolver(
            fetch(url + '?' + parametros({ fecha: f }).toString(), { headers: { 'Accept': 'application/json' } }), horasEl)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                horasEl.innerHTML = '';
                if (!d.ok || !d.horas || !d.horas.length) {
                    horasEl.textContent = 'Ese día ya no tiene horarios libres.';
                    return;
                }
                d.horas.forEach(function (h) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'spg-chip';
                    b.textContent = h.hora;
                    b.addEventListener('click', function () {
                        campoFecha.value = diaElegido + ' ' + h.hora + ':00';
                        Array.prototype.forEach.call(horasEl.children, function (c) { c.classList.remove('activo'); });
                        b.classList.add('activo');
                        btn.disabled = false;
                    });
                    horasEl.appendChild(b);
                });
            });
    }

    document.querySelectorAll('.srv').forEach(function (c) { c.addEventListener('change', cargarDias); });
    document.getElementById('id_usuario').addEventListener('change', cargarDias);
})();
</script>
@endpush
