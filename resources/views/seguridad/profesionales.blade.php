@extends('layout.app')

@section('titulo', 'Profesionales')

@section('contenido')
@php use App\Servicios\Permisos; @endphp

{{-- **Es la PERSONA, no la cuenta, y esa es toda la diferencia.**

     Hasta la 7.68.0 «Profesionales» abría la ficha de usuario, así que para
     cargar a alguien había que inventarle una cuenta de sistema — y hay gente
     que atiende y no entra al sistema nunca.

     Acá se cargan los datos de la persona. La cuenta se crea después, desde
     Seguridad → Usuarios, eligiendo a esta persona. --}}
<x-encabezado sub="Quiénes trabajan en el salón. Acá van sus datos; la cuenta para entrar al sistema se crea aparte."
    :accion="['ruta' => 'seguridad.profesional_form', 't' => 'Nuevo profesional', 'ic' => 'person-plus']" />

<x-filtros :f="$f" />

<div class="spg-panel">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Nombre</th><th>Cédula</th><th>Contacto</th>
                    <th>Cuenta del sistema</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td>{{ trim($r->nombre . ' ' . $r->apellido) }}</td>
                        <td class="text-muted-warm">{{ $r->cedula ?: '—' }}</td>
                        <td class="text-muted-warm" style="font-size:.85rem">
                            <div>{{ $r->telefono ?: '—' }}</div>
                            @if ($r->email)
                                <div>{{ $r->email }}</div>
                            @endif
                        </td>
                        <td>
                            {{-- **«Sin cuenta» no es un error y hay que decirlo así.**
                                 Es alguien que atiende y no entra al sistema, que es
                                 un caso normal del salón. --}}
                            @if ($r->username)
                                <span class="badge-estado e-ok">{{ $r->username }}</span>
                                <span class="text-muted-warm" style="font-size:.8rem">· {{ $r->rol }}</span>
                            @else
                                <span class="text-muted-warm" style="font-size:.85rem">sin cuenta</span>
                                @if (Permisos::puede('seguridad.usuarios'))
                                    <a class="ms-1" style="font-size:.8rem"
                                       href="{{ route('seguridad.usuario_form', ['persona' => $r->id_persona]) }}">
                                       crearle una</a>
                                @endif
                            @endif
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-neutro" title="Editar sus datos"
                               href="{{ route('seguridad.profesional_form', $r->id_persona) }}">
                                <i class="bi bi-pencil"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="spg-vacio">
                                <i class="bi bi-people"></i>
                                <div class="t">No hay profesionales cargados</div>
                                <div class="d">Cargá al equipo con el botón de arriba.</div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<x-paginacion :pag="$pag" :f="$f" />
@endsection
