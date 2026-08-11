@extends('layout.app')

@section('titulo', 'Turnos')

@section('contenido')
    <x-encabezado sub="El turno es una <strong>plantilla</strong>, no una fecha: un nombre, un horario y los días de la semana en que se trabaja. Se define una vez y se le asigna a cada persona desde su ficha." />

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="spg-panel">
                <h2 class="spg-form-titulo mb-2">
                    <i class="bi bi-clock"></i> {{ $editar ? 'Editar turno' : 'Nuevo turno' }}
                </h2>

                <form method="post" action="{{ route('seguridad.turno.guardar') }}">
                    @csrf
                    <input type="hidden" name="id_turno" value="{{ $editar->id_turno ?? 0 }}">

                    <div class="mb-2">
                        <label class="form-label" for="nombre">Nombre *</label>
                        <input class="form-control" id="nombre" name="nombre" required maxlength="60"
                               placeholder="Turno Mañana"
                               value="{{ old('nombre', $editar->nombre ?? '') }}">
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label" for="hora_inicio">Desde *</label>
                            <input type="time" class="form-control" id="hora_inicio" name="hora_inicio" required
                                   value="{{ old('hora_inicio', isset($editar) ? substr((string) $editar->hora_inicio, 0, 5) : '08:00') }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="hora_fin">Hasta *</label>
                            <input type="time" class="form-control" id="hora_fin" name="hora_fin" required
                                   value="{{ old('hora_fin', isset($editar) ? substr((string) $editar->hora_fin, 0, 5) : '12:00') }}">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label" for="id_sucursal">Sucursal *</label>
                        <select class="form-select" id="id_sucursal" name="id_sucursal" required>
                            @foreach ($sucursales as $s)
                                <option value="{{ $s->id_sucursal }}"
                                    @selected((int) old('id_sucursal', $editar->id_sucursal ?? 0) === (int) $s->id_sucursal)>
                                    {{ $s->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Días que se trabaja *</label>
                        {{-- Un día por casilla, y en la base una fila por día:
                             nunca una lista tipo 'LMXJVS' dentro de una columna. --}}
                        <div class="d-flex gap-2 flex-wrap">
                            @foreach ($dias as $n => $nombreDia)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dias[]" value="{{ $n }}"
                                           id="dia{{ $n }}"
                                           @checked(in_array($n, old('dias', $editar->dias ?? [1, 2, 3, 4, 5, 6]), false))>
                                    <label class="form-check-label" for="dia{{ $n }}">{{ substr($nombreDia, 0, 3) }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-oro w-100"><i class="bi bi-check-lg"></i> Guardar</button>
                        @if ($editar)
                            <a class="btn btn-outline-neutro" href="{{ route('seguridad.turnos') }}">Cancelar</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="spg-panel">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr><th>Turno</th><th>Horario</th><th>Días</th><th>Quiénes lo trabajan</th>
                                <th class="text-end">Acciones</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $t)
                                <tr>
                                    <td>
                                        {{ $t->nombre }}
                                        <div class="text-muted-warm" style="font-size:.76rem">{{ $t->sucursal }}</div>
                                    </td>
                                    <td style="white-space:nowrap">
                                        {{ substr((string) $t->hora_inicio, 0, 5) }}
                                        a {{ substr((string) $t->hora_fin, 0, 5) }}
                                    </td>
                                    <td class="text-muted-warm" style="font-size:.82rem">{{ $t->dias_texto }}</td>
                                    <td class="text-muted-warm" style="font-size:.82rem">
                                        @if (! empty($gente[$t->id_turno]))
                                            {{ implode(', ', $gente[$t->id_turno]) }}
                                        @else
                                            <span class="txt-no">nadie todavía</span>
                                        @endif
                                    </td>
                                    <td class="text-end" style="white-space:nowrap">
                                        <a class="btn btn-sm btn-outline-neutro" title="Editar"
                                           href="{{ route('seguridad.turnos', ['editar' => $t->id_turno]) }}">
                                            <i class="bi bi-pencil"></i></a>
                                        <form method="post" action="{{ route('seguridad.turno.baja') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="id_turno" value="{{ $t->id_turno }}">
                                            <button class="btn btn-sm btn-outline-neutro" title="Dar de baja"
                                                    data-confirmar="¿Dar de baja el turno «{{ $t->nombre }}»? Quienes lo trabajan van a quedar sin ese horario en la agenda.">
                                                <i class="bi bi-toggle-on"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <div class="spg-vacio">
                                            <i class="bi bi-clock"></i>
                                            <div class="t">Todavía no hay turnos cargados.</div>
                                            <div class="d">
                                                Sin turnos, la agenda no sabe cuándo atiende cada persona.
                                            </div>
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
