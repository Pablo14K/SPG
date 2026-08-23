@extends('layout.app')

@section('titulo', 'Usuarios')

@section('contenido')
    @php use App\Servicios\Permisos; @endphp

    {{-- Al Administrador no se le explica una restricción que no lo alcanza: él
         crea cuentas, y el botón lo tiene ahí al lado. A quien NO es
         Administrador sí, porque si no la pantalla se ve incompleta sin decir
         por qué le falta el botón. --}}
    <x-encabezado
        :sub="Permisos::esAdmin()
            ? (request()->query('desde') === 'personal'
                ? 'El equipo del salón: qué servicios hace cada una y en qué turnos trabaja.'
                : 'Las cuentas que entran al sistema, con su rol y los locales a los que acceden.')
            : 'Las cuentas del personal. <strong>Crear y editar cuentas es exclusivo del Administrador</strong>, sin importar lo que diga la matriz de roles: quien puede editar la matriz podría darse permisos a sí mismo.'"
        :accion="Permisos::esAdmin()
            ? ['ruta' => 'seguridad.usuario_form',
               't' => request()->query('desde') === 'personal' ? 'Nueva profesional' : 'Nuevo usuario',
               'ic' => 'person-plus',
               'q' => request()->query('desde') === 'personal' ? ['desde' => 'personal'] : []]
            : null" />

    <div class="spg-panel">
        <x-filtros :f="$f" />

        <div class="table-responsive">
            <table class="table align-middle">
                {{-- **Dos listas, no una con todo mezclado.**

                     «Usuarios» contesta *¿quién entra al sistema y con qué rol?*
                     y «Profesionales» *¿quién trabaja y qué hace?*. Son las mismas
                     personas pero dos preguntas distintas, y con las columnas de
                     las dos juntas ninguna se contesta de un vistazo.

                     La ficha sigue siendo UNA sola —duplicarla las desfasa— y lo
                     que cambia acá es qué se lista y en qué orden. --}}
                @php $comoPersonal = request()->query('desde') === 'personal'; @endphp
                <thead>
                    <tr>
                        <th>Nombre</th>
                        @if ($comoPersonal)
                            <th>Contacto</th><th>Servicios que hace</th><th>Turnos</th>
                        @else
                            <th>Usuario</th><th>Rol</th><th>Sucursales</th>
                        @endif
                        <th>Estado</th><th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $u)
                        <tr>
                            <td>{{ $u->nombre }} {{ $u->apellido }}</td>

                            @if ($comoPersonal)
                                <td class="text-muted-warm" style="font-size:.82rem">
                                    {{ $u->email }}
                                    @if ($u->telefono)<div>{{ $u->telefono }}</div>@endif
                                </td>
                                <td class="text-muted-warm" style="font-size:.82rem">
                                    {{-- **Sin servicios cargados los hace todos**, que es el
                                         criterio permisivo de siempre. Decir «ninguno» sería
                                         mentir: lo que pasa es que nadie lo administró, y por
                                         eso la agenda se lo ofrece para cualquier cosa. --}}
                                    @if ($u->servicios)
                                        {{ $u->servicios }}
                                    @else
                                        <span class="txt-no">sin cargar · se le ofrece para todo</span>
                                    @endif
                                </td>
                                <td class="text-muted-warm" style="font-size:.82rem">
                                    @if ($u->turnos)
                                        {{ $u->turnos }}
                                    @else
                                        <span class="txt-no">sin turno · no aparece en la agenda</span>
                                    @endif
                                </td>
                            @else
                                <td class="text-muted-warm">{{ $u->username }}</td>
                                <td><span class="badge-estado e-prog">{{ $u->rol }}</span></td>
                                <td class="text-muted-warm" style="font-size:.82rem">
                                    {{ $u->sucursales ?: 'todas' }}
                                </td>
                            @endif
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
                                       {{-- El «desde» viaja para que la ficha abra en la
                                            pestaña que corresponde: entrando por Personal
                                            se administran los datos de la persona, y por
                                            Usuarios, la cuenta. --}}
                                       href="{{ route('seguridad.usuario_form', ['id' => $u->id_usuario]
                                                + (request()->query('desde') ? ['desde' => request()->query('desde')] : [])) }}">
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
                            <td colspan="6">
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
