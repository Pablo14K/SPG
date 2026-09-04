@extends('layout.app')

@section('titulo', 'Excepciones de agenda')

@section('contenido')
    <x-encabezado sub="Feriados, licencias y bloqueos. Mientras están cargados, la agenda no ofrece esos horarios." />

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="spg-panel">
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-calendar-x"></i> Nueva excepción</h2>

                <form method="post" action="{{ route('citas.ausencia.guardar') }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label" for="id_usuario">¿A quién afecta?</label>
                        <select class="form-select" id="id_usuario" name="id_usuario">
                            <option value="0">Todo el salón (feriado)</option>
                            @foreach ($profs as $p)
                                <option value="{{ $p->id_usuario }}"
                                    @selected((int) old('id_usuario') === (int) $p->id_usuario)>{{ $p->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- **En qué local.** Antes no se preguntaba y toda ausencia
                         valía en todas las sucursales: cargar una acá dejaba a esa
                         persona sin agenda en las otras. Sólo se dibuja con más de
                         un local — preguntar algo de una única respuesta hace
                         perder un clic. --}}
                    @if (count($sucursales) > 1)
                        <div class="mb-3">
                            <label class="form-label" for="id_sucursal">¿En qué sucursal?</label>
                            <select class="form-select" id="id_sucursal" name="id_sucursal">
                                <option value="0">En todas</option>
                                @foreach ($sucursales as $s)
                                    <option value="{{ $s->id_sucursal }}"
                                        @selected((int) old('id_sucursal') === (int) $s->id_sucursal)>{{ $s->nombre }}</option>
                                @endforeach
                            </select>
                            <x-ayuda>Un feriado del salón va en todas. La licencia de una persona que trabaja en varios locales, también — si no, sigue apareciendo disponible en los otros.</x-ayuda>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label" for="id_tipo_ausencia">Tipo *</label>
                        <select class="form-select" id="id_tipo_ausencia" name="id_tipo_ausencia" required>
                            @foreach ($tipos as $t)
                                <option value="{{ $t->id_tipo_ausencia }}"
                                    @selected((int) old('id_tipo_ausencia') === (int) $t->id_tipo_ausencia)>{{ $t->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="fecha_inicio">Desde *</label>
                            <input type="datetime-local" class="form-control" id="fecha_inicio"
                                   name="fecha_inicio" required value="{{ old('fecha_inicio') }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="fecha_fin">Hasta *</label>
                            <input type="datetime-local" class="form-control" id="fecha_fin"
                                   name="fecha_fin" required value="{{ old('fecha_fin') }}">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="motivo">Motivo</label>
                        <input class="form-control" id="motivo" name="motivo" maxlength="150"
                               value="{{ old('motivo') }}" placeholder="Ej. Licencia médica">
                    </div>

                    <button class="btn btn-oro w-100"
                            data-confirmar="Mientras esté cargada, la agenda no va a ofrecer esos horarios. ¿Registrar la excepción?">
                        <i class="bi bi-check-lg"></i> Registrar
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="spg-panel">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr><th>Quién</th><th>Dónde</th><th>Tipo</th><th>Desde</th><th>Hasta</th><th>Motivo</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $a)
                                <tr>
                                    <td>{{ $a->quien }}</td>
                                    <td class="text-muted-warm">{{ $a->donde }}</td>
                                    <td><span class="badge-estado e-prog">{{ $a->tipo }}</span></td>
                                    <td>{{ fecha($a->fecha_inicio) }}</td>
                                    <td>{{ fecha($a->fecha_fin) }}</td>
                                    <td class="text-muted-warm">{{ $a->motivo ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="spg-vacio">
                                            <i class="bi bi-calendar-x"></i>
                                            <div class="t">No hay excepciones cargadas.</div>
                                            <div class="d">Cargá una cuando el salón cierre o alguien se ausente.</div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
