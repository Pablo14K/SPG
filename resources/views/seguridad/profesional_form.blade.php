@extends('layout.app')

@section('titulo', $id ? 'Editar profesional' : 'Nuevo profesional')

@section('contenido')
@php use App\Servicios\Permisos; @endphp

{{-- **El título sale del catálogo, que dice «Nuevo profesional».** Editando
     eso es falso, así que se pisa a mano — igual que hacen las demás fichas. --}}
<x-encabezado :titulo="$id ? 'Editar profesional' : 'Nuevo profesional'"
    :sub="$id ? 'Los datos de la persona y qué servicios hace. La cuenta del sistema se administra aparte.' : 'Cargá los datos de la persona. Si además va a entrar al sistema, después le creás la cuenta.'" />

<div class="row g-3">
    <div class="col-lg-8">
        <div class="spg-panel">
            <form method="post" action="{{ route('seguridad.profesional.guardar') }}">
                @csrf
                <input type="hidden" name="id_persona" value="{{ $id }}">

                {{-- **La persona y lo que sabe hacer.** Lo que cuelga de la
                     CUENTA —rol, sucursales, turnos— vive en la ficha de
                     usuario; acá va lo que es de la persona, que sigue siendo
                     cierto aunque nunca entre al sistema. --}}
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-person"></i> Datos de la persona</h2>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label" for="nombre">Nombre *</label><x-ayuda campo="nombre" />
                        <input class="form-control" id="nombre" name="nombre" required maxlength="120"
                               value="{{ old('nombre', $p->nombre ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="apellido">Apellido *</label><x-ayuda campo="apellido" />
                        <input class="form-control" id="apellido" name="apellido" required maxlength="80"
                               value="{{ old('apellido', $p->apellido ?? '') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="cedula">Cédula</label><x-ayuda campo="cedula" />
                        <input class="form-control" id="cedula" name="cedula" maxlength="20" data-solo="documento"
                               value="{{ old('cedula', $p->cedula ?? '') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="telefono">Teléfono</label><x-ayuda campo="telefono" />
                        <input class="form-control" id="telefono" name="telefono" maxlength="20" data-solo="telefono"
                               value="{{ old('telefono', $p->telefono ?? '') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="fecha_nacimiento">Fecha de nacimiento</label><x-ayuda campo="fecha_nacimiento" />
                        <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento"
                               value="{{ old('fecha_nacimiento', $p->fecha_nacimiento ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email</label><x-ayuda campo="email" />
                        <input type="email" class="form-control" id="email" name="email" maxlength="120"
                               value="{{ old('email', $p->email ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="direccion">Dirección</label><x-ayuda campo="direccion" />
                        <input class="form-control" id="direccion" name="direccion" maxlength="255"
                               value="{{ old('direccion', $p->direccion ?? '') }}">
                    </div>
                </div>

                {{-- **Qué servicios hace.** Sin esto la agenda ofrecía a
                     cualquiera para cualquier cosa: la manicurista para una
                     coloración, la clienta reservaba y el día de la cita el salón
                     no lo podía dar.

                     Está acá y no en la ficha de usuario porque es de la PERSONA:
                     saber peinar no depende de tener cuenta de sistema. --}}
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-scissors"></i> Servicios que hace<x-ayuda>Si no marcás ninguno, hace todos. Marcá sólo cuando alguien se dedique a lo suyo: la agenda deja de ofrecerlo para el resto.</x-ayuda></h2>
                <div class="mb-3">
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" id="gServiciosTodo" data-marca-todo="#gServicios">
                        <label class="form-check-label fw-semibold" for="gServiciosTodo">Todos</label>
                    </div>
                    <div class="d-flex gap-3 flex-wrap" id="gServicios">
                        @foreach ($servicios as $sv)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="servicios[]"
                                       value="{{ $sv->id_servicio }}" id="sv{{ $sv->id_servicio }}"
                                       @checked(in_array((int) $sv->id_servicio, old('servicios', $misServicios), false))>
                                <label class="form-check-label" for="sv{{ $sv->id_servicio }}">{{ $sv->nombre }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="d-flex gap-2 pt-2 border-top">
                    <button class="btn btn-oro"><i class="bi bi-check-lg"></i> Guardar</button>
                    <a class="btn btn-outline-neutro" href="{{ route('seguridad.profesionales') }}">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="spg-panel">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-key"></i> Cuenta del sistema</h2>

            @if (! $id)
                <p class="text-muted-warm mb-0" style="font-size:.86rem">
                    Guardá primero los datos. Después, si esta persona va a entrar al
                    sistema, le creás la cuenta desde <strong>Seguridad → Usuarios</strong>.
                </p>
            @elseif ($cuenta)
                <p class="mb-2" style="font-size:.9rem">
                    Entra como <strong>{{ $cuenta->username }}</strong>
                    <span class="badge-estado {{ $cuenta->activo ? 'e-ok' : 'e-muted' }}">
                        {{ $cuenta->activo ? 'activa' : 'inactiva' }}</span>
                </p>
                <p class="text-muted-warm mb-2" style="font-size:.84rem">
                    Rol: {{ $cuenta->rol }}. Ahí se administran también las sucursales
                    a las que entra y los turnos que trabaja.
                </p>
                @if (Permisos::puede('seguridad.usuarios'))
                    <a class="btn btn-sm btn-outline-neutro"
                       href="{{ route('seguridad.usuario_form', $cuenta->id_usuario) }}">
                        <i class="bi bi-key"></i> Ver la cuenta</a>
                @endif
            @else
                {{-- **Sin cuenta no es un error.** Hay gente que atiende y no
                     entra al sistema nunca; decirlo así evita que alguien le
                     invente una para poder cargarla. --}}
                <p class="text-muted-warm mb-2" style="font-size:.86rem">
                    Esta persona <strong>no entra al sistema</strong>, y eso está bien:
                    hay quien atiende sin usar la computadora.
                </p>
                @if (Permisos::puede('seguridad.usuarios'))
                    <a class="btn btn-sm btn-outline-neutro"
                       href="{{ route('seguridad.usuario_form', ['persona' => $id]) }}">
                        <i class="bi bi-person-plus"></i> Crearle una cuenta</a>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
