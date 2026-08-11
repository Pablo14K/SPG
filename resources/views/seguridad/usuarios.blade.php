@extends('layout.app')

@section('titulo', 'Usuarios')

@section('contenido')
    @php use App\Servicios\Permisos; @endphp

    <x-encabezado
        sub="Las cuentas del personal. <strong>Crear y editar cuentas es exclusivo del Administrador</strong>, sin importar lo que diga la matriz de roles: quien puede editar la matriz podría darse permisos a sí mismo."
        :accion="Permisos::esAdmin() ? ['ruta' => 'seguridad.usuario_form', 't' => 'Nuevo usuario', 'ic' => 'person-plus'] : null" />

    <div class="spg-panel">
        <x-filtros :f="$f" />

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Nombre</th><th>Usuario</th><th>Rol</th><th>Turnos</th>
                        <th>Contacto</th><th>Estado</th><th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $u)
                        <tr>
                            <td>{{ $u->nombre }} {{ $u->apellido }}</td>
                            <td class="text-muted-warm">{{ $u->username }}</td>
                            <td><span class="badge-estado e-prog">{{ $u->rol }}</span></td>
                            <td class="text-muted-warm" style="font-size:.82rem">
                                @if ($u->turnos)
                                    {{ $u->turnos }}
                                @else
                                    <span class="txt-no">sin turno</span>
                                @endif
                            </td>
                            <td class="text-muted-warm" style="font-size:.82rem">
                                {{ $u->email }}
                                @if ($u->telefono)<div>{{ $u->telefono }}</div>@endif
                            </td>
                            <td>
                                @if ($u->activo)
                                    <span class="badge-estado e-ok">Activo</span>
                                @else
                                    <span class="badge-estado e-muted">Inactivo</span>
                                @endif
                            </td>
                            <td class="text-end" style="white-space:nowrap">
                                @if (Permisos::esAdmin())
                                    <a class="btn btn-sm btn-outline-neutro" title="Editar"
                                       href="{{ route('seguridad.usuario_form', $u->id_usuario) }}">
                                        <i class="bi bi-pencil"></i></a>
                                    <form method="post" action="{{ route('seguridad.usuario.baja') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="id_usuario" value="{{ $u->id_usuario }}">
                                        <button class="btn btn-sm btn-outline-neutro"
                                                title="{{ $u->activo ? 'Desactivar' : 'Activar' }}"
                                                data-confirmar="¿{{ $u->activo ? 'Desactivar' : 'Activar' }} la cuenta de {{ $u->nombre }} {{ $u->apellido }}?">
                                            <i class="bi bi-toggle-{{ $u->activo ? 'on' : 'off' }}"></i></button>
                                    </form>
                                @else
                                    <span class="text-muted-warm" style="font-size:.8rem">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="spg-vacio">
                                    <i class="bi bi-person-badge"></i>
                                    <div class="t">{{ $f['activos'] ? 'Ningún usuario coincide con esos filtros.' : 'Todavía no hay personal cargado.' }}</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-paginacion :pag="$pag" :f="$f" />
    </div>
@endsection
