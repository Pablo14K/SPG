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

<div class="sgp-panel">
    <div class="table-responsive sgp-tabla-movil">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Nombre</th><th>Cuenta del sistema</th><th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr>
                        {{-- La cara al lado del nombre: en una lista de nombres
                             parecidos, reconocer a alguien se hace de un vistazo
                             y no leyendo apellido por apellido. --}}
                        <td class="sgp-movil-titulo sgp-movil-sujeto" data-label="Nombre">
                            <span class="sgp-celda-persona">
                                <x-avatar :foto="$r->foto" :nombre="$r->nombre" :apellido="$r->apellido" />
                                {{ trim($r->nombre . ' ' . $r->apellido) }}
                            </span>
                        </td>
                        <td data-label="Cuenta del sistema">
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
                        <td class="text-end sgp-movil-acciones" style="white-space:nowrap">
                            <button class="sgp-btn-detalle" data-bs-toggle="collapse"
                                    data-bs-target="#detProf{{ $r->id_persona }}" aria-expanded="false"
                                    aria-controls="detProf{{ $r->id_persona }}">
                                <i class="bi bi-chevron-down"></i> Detalle
                            </button>
                            <a class="btn btn-sm btn-outline-neutro" title="Editar sus datos"
                               href="{{ route('seguridad.profesional_form', $r->id_persona) }}">
                                <i class="bi bi-pencil"></i></a>
                        </td>
                    </tr>
                    <tr class="sgp-fila-detalle">
                        <td colspan="3">
                            <div class="collapse" id="detProf{{ $r->id_persona }}">
                                <div class="sgp-det-cuerpo">
                                    <div class="sgp-det-grid">
                                        <div>
                                            <dt>Cédula</dt>
                                            <dd>{{ $r->cedula ?: '—' }}</dd>
                                        </div>
                                        <div>
                                            <dt>Contacto</dt>
                                            <dd>
                                                <div>{{ $r->telefono ?: '—' }}</div>
                                                @if ($r->email)
                                                    <div>{{ $r->email }}</div>
                                                @endif
                                            </dd>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">
                            <div class="sgp-vacio">
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
