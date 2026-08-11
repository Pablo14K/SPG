@extends('layout.app')

@section('titulo', $u ? 'Editar usuario' : 'Nuevo usuario')

@section('contenido')
    @php $id = $u->id_usuario ?? 0; @endphp

    <div class="spg-page-head">
        <a class="spg-back" href="{{ route('personal.usuarios') }}"><i class="bi bi-arrow-left"></i> Usuarios</a>
        <h1 class="mt-1">{{ $id ? 'Editar usuario' : 'Nuevo usuario' }}</h1>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="spg-panel">
                <form method="post" action="{{ route('personal.usuario.guardar') }}" id="formUsuario">
                    @csrf
                    <input type="hidden" name="id_usuario" value="{{ $id }}">

                    <h2 class="spg-form-titulo mb-2"><i class="bi bi-person"></i> Datos de la persona</h2>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="nombre">Nombre *</label>
                            <input class="form-control" id="nombre" name="nombre" required
                                   value="{{ old('nombre', $u->nombre ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="apellido">Apellido *</label>
                            <input class="form-control" id="apellido" name="apellido" required
                                   value="{{ old('apellido', $u->apellido ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="cedula">Cédula</label>
                            <input class="form-control" id="cedula" name="cedula"
                                   value="{{ old('cedula', $u->cedula ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="telefono">Teléfono</label>
                            <input class="form-control" id="telefono" name="telefono"
                                   value="{{ old('telefono', $u->telefono ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="email">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required
                                   value="{{ old('email', $u->email ?? '') }}">
                            <div class="form-text">Es el canal del código de seguridad.</div>
                        </div>
                    </div>

                    <h2 class="spg-form-titulo mb-2"><i class="bi bi-key"></i> Cuenta</h2>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label" for="username">Usuario *</label>
                            <input class="form-control" id="username" name="username" required
                                   value="{{ old('username', $u->username ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="password">
                                Contraseña {{ $id ? '' : '*' }}
                            </label>
                            <input type="password" class="form-control" id="password" name="password"
                                   {{ $id ? '' : 'required' }} minlength="6">
                            @if ($id)
                                <div class="form-text">Dejala vacía para no cambiarla.</div>
                            @endif
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="id_rol">Rol *</label>
                            <select class="form-select" id="id_rol" name="id_rol" required>
                                @foreach ($roles as $r)
                                    <option value="{{ $r->id_rol }}"
                                        @selected((int) old('id_rol', $u->id_rol ?? 0) === (int) $r->id_rol)>
                                        {{ $r->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <h2 class="spg-form-titulo mb-2"><i class="bi bi-shop"></i> Sucursales donde trabaja</h2>
                    <div class="mb-3">
                        <div class="d-flex gap-3 flex-wrap">
                            @foreach ($sucursales as $s)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sucursales[]"
                                           value="{{ $s->id_sucursal }}" id="suc{{ $s->id_sucursal }}"
                                           @checked(in_array((int) $s->id_sucursal, old('sucursales', $misSuc), false))>
                                    <label class="form-check-label" for="suc{{ $s->id_sucursal }}">{{ $s->nombre }}</label>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-2">
                            <label class="form-label" for="id_sucursal">Sucursal principal</label>
                            <select class="form-select" id="id_sucursal" name="id_sucursal" style="max-width:280px">
                                <option value="0">— la primera marcada —</option>
                                @foreach ($sucursales as $s)
                                    <option value="{{ $s->id_sucursal }}"
                                        @selected((int) old('id_sucursal', $u->id_sucursal ?? 0) === (int) $s->id_sucursal)>
                                        {{ $s->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <h2 class="spg-form-titulo mb-1"><i class="bi bi-clock"></i> Turnos que trabaja</h2>
                    <p class="text-muted-warm mb-2" style="font-size:.8rem">
                        <strong>Sin turno asignado no aparece en la agenda</strong>: el sistema no sabría
                        cuándo atiende. El mismo turno lo puede compartir todo el equipo.
                    </p>
                    <div class="mb-3">
                        @forelse ($turnos as $t)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="turnos[]"
                                       value="{{ $t->id_turno }}" id="tur{{ $t->id_turno }}"
                                       @checked(in_array((int) $t->id_turno, old('turnos', $misTurnos), false))>
                                <label class="form-check-label" for="tur{{ $t->id_turno }}">
                                    {{ $t->nombre }}
                                    <span class="text-muted-warm">
                                        · {{ substr((string) $t->hora_inicio, 0, 5) }} a {{ substr((string) $t->hora_fin, 0, 5) }}
                                        · {{ mb_strtolower($t->dias_texto) }}
                                        · {{ $t->sucursal }}
                                    </span>
                                </label>
                            </div>
                        @empty
                            <p class="txt-no" style="font-size:.85rem">
                                Todavía no hay turnos cargados. Creá uno acá al costado.
                            </p>
                        @endforelse
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-oro"><i class="bi bi-check-lg"></i> Guardar</button>
                        <a class="btn btn-outline-neutro" href="{{ route('personal.usuarios') }}">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            {{-- Altas rápidas: crear un turno o una sucursal sin salir de la
                 ficha. Sin esto había que ir a otra pantalla, crearlo y volver
                 a cargar todo de cero. --}}
            <div class="spg-panel mb-3">
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-clock"></i> Turno nuevo</h2>
                <form method="post" action="{{ route('personal.turno.rapido') }}">
                    @csrf
                    <input type="hidden" name="id_usuario" value="{{ $id }}">
                    <div class="mb-2">
                        <label class="form-label" for="tr_nombre">Nombre *</label>
                        <input class="form-control form-control-sm" id="tr_nombre" name="nombre" required maxlength="60">
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label" for="tr_ini">Desde</label>
                            <input type="time" class="form-control form-control-sm" id="tr_ini"
                                   name="hora_inicio" value="08:00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="tr_fin">Hasta</label>
                            <input type="time" class="form-control form-control-sm" id="tr_fin"
                                   name="hora_fin" value="12:00" required>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="tr_suc">Sucursal</label>
                        <select class="form-select form-select-sm" id="tr_suc" name="id_sucursal" required>
                            @foreach ($sucursales as $s)
                                <option value="{{ $s->id_sucursal }}">{{ $s->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Días</label>
                        <div class="d-flex gap-1 flex-wrap">
                            @foreach ($dias as $n => $nombreDia)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dias[]" value="{{ $n }}"
                                           id="trd{{ $n }}" @checked($n <= 6)>
                                    <label class="form-check-label" for="trd{{ $n }}"
                                           style="font-size:.8rem">{{ substr($nombreDia, 0, 3) }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <button class="btn btn-rapido w-100"><i class="bi bi-plus-lg"></i> Crear turno</button>
                </form>
            </div>

            <div class="spg-panel">
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-shop"></i> Sucursal nueva</h2>
                <form method="post" action="{{ route('personal.sucursal.rapida') }}">
                    @csrf
                    <input type="hidden" name="id_usuario" value="{{ $id }}">
                    <div class="mb-2">
                        <label class="form-label" for="sr_nombre">Nombre *</label>
                        <input class="form-control form-control-sm" id="sr_nombre" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="sr_ciudad">Ciudad</label>
                        <input class="form-control form-control-sm" id="sr_ciudad" name="ciudad" value="Luque">
                    </div>
                    <button class="btn btn-rapido w-100"><i class="bi bi-plus-lg"></i> Crear sucursal</button>
                </form>
            </div>
        </div>
    </div>
@endsection
