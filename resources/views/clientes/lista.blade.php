@extends('layout.app')

@section('titulo', 'Clientes')

@section('contenido')
    @php use App\Servicios\Navegacion; @endphp

    <x-encabezado
        sub="Registro de clientes del salón, con sus datos de contacto. Las visitas, los puntos y el nivel se miran en Promociones → Visitas y puntos."
        :accion="['ruta' => 'clientes.form', 't' => 'Nuevo cliente', 'ic' => 'person-plus']" />

    <div class="sgp-panel">
        <x-filtros :f="$f" />

        <div class="table-responsive sgp-tabla-movil">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Cliente</th><th>Teléfono</th>
                        {{-- **Las visitas salieron de acá.** Contaban lo mismo
                             que la pantalla de fidelización —hoy Promociones →
                             Visitas y puntos— y ahí van con su nivel y sus
                             puntos, que es lo que las hace significar algo. Un
                             número suelto en esta tabla obligaba a mirarlo en
                             dos lugares. --}}
                        <th>Estado</th><th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clientes as $c)
                        {{-- Main row: only essential columns --}}
                        <tr>
                            <td class="sgp-movil-titulo" data-label="Cliente">
                                <a class="link-oro" href="{{ route('clientes.historial', $c->id_cliente) }}">
                                    {{ $c->apellido . ', ' . $c->nombre }}</a>
                            </td>
                            <td data-label="Teléfono">{{ $c->telefono ?: '—' }}</td>
                            <td data-label="Estado">
                                @if ($c->activo)
                                    <span class="badge-estado e-ok">Activo</span>
                                @else
                                    <span class="badge-estado e-muted">Inactivo</span>
                                @endif
                            </td>
                            <td class="text-end sgp-movil-acciones" style="white-space:nowrap">
                                <button class="sgp-btn-detalle" data-bs-toggle="collapse"
                                        data-bs-target="#detCli{{ $c->id_cliente }}" aria-expanded="false"
                                        aria-controls="detCli{{ $c->id_cliente }}">
                                    <i class="bi bi-chevron-down"></i> Detalle
                                </button>
                                <a class="btn btn-sm btn-outline-neutro" title="Historial"
                                   href="{{ route('clientes.historial', $c->id_cliente) }}">
                                    <i class="bi bi-clock-history"></i></a>

                                {{-- **Ver la ficha, no crear una cita.** Acá había un
                                     «Nueva cita», y agendar no es lo que se viene a
                                     hacer a esta pantalla: se entra a buscar a alguien
                                     y a mirar sus datos —el teléfono, si tiene
                                     alergias, qué dejó dicho—. Para eso había que
                                     abrir el formulario de edición, o sea entrar a
                                     modificar algo para poder leerlo.

                                     Es un modal y no una pantalla nueva: son los datos
                                     que la lista ya trajo, y una tercera pantalla
                                     diría lo mismo que el historial. --}}
                                <button type="button" class="btn btn-sm btn-outline-neutro" title="Ver la ficha"
                                        data-bs-toggle="modal" data-bs-target="#ficha{{ $c->id_cliente }}">
                                    <i class="bi bi-person-vcard"></i></button>

                                <a class="btn btn-sm btn-outline-neutro" title="Editar"
                                   href="{{ route('clientes.form', $c->id_cliente) }}">
                                    <i class="bi bi-pencil"></i></a>

                                <form method="post" action="{{ route('clientes.baja') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="id_cliente" value="{{ $c->id_cliente }}">
                                    <button class="btn btn-sm btn-outline-neutro"
                                            title="{{ $c->activo ? 'Desactivar' : 'Activar' }}"
                                            data-confirmar="¿{{ $c->activo ? 'Desactivar' : 'Activar' }} a {{ $c->nombre }} {{ $c->apellido }}?">
                                        <i class="bi bi-toggle-{{ $c->activo ? 'on' : 'off' }}"></i></button>
                                </form>
                            </td>
                        </tr>
                        {{-- Expandable detail row --}}
                        <tr class="sgp-fila-detalle">
                            <td colspan="4">
                                <div class="collapse" id="detCli{{ $c->id_cliente }}">
                                    <div class="sgp-det-cuerpo">
                                        <div class="sgp-det-grid">
                                            <div>
                                                <dt>Cédula</dt>
                                                <dd>{{ $c->cedula ?: '—' }}</dd>
                                            </div>
                                            <div>
                                                <dt>Email</dt>
                                                <dd>{{ $c->email ?: '—' }}</dd>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="sgp-vacio">
                                    <i class="bi bi-people"></i>
                                    <div class="t">
                                        {{ $f['activos'] ? 'Ningún cliente coincide con esos filtros.' : 'Todavía no hay clientes cargados.' }}
                                    </div>
                                    <div class="d">
                                        {{ $f['activos'] ? 'Probá con menos filtros o limpialos.' : 'Registrá el primero con el botón «Nuevo cliente».' }}
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-paginacion :pag="$pag" :f="$f" />


    </div>
{{-- **Un modal por clienta, y FUERA de la tabla.** Dentro de un `<tr>` no se
     puede mostrar: un ancestro con `display:none` gana siempre y el modal queda
     con el fondo gris y nada adentro — es el defecto que la 7.87.4 corrigió en
     el detalle de la cita. --}}
@foreach ($clientes as $c)
    <div class="modal fade" id="ficha{{ $c->id_cliente }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" style="font-size:1rem">
                        <i class="bi bi-person-vcard txt-oro"></i>
                        {{ $c->nombre }} {{ $c->apellido }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    {{-- **Las alergias van arriba y en rojo**, como en el historial y
                         en la agenda: es el único dato de la ficha que puede lastimar
                         a alguien, así que no se lee al final ni escondido. --}}
                    @if (trim((string) $c->alergias) !== '')
                        <div class="alert alert-danger py-2 mb-3" style="font-size:.85rem">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <strong>Alergias:</strong> {{ $c->alergias }}
                        </div>
                    @else
                        <div class="form-text mb-3">
                            <i class="bi bi-info-circle"></i>
                            Sin alergias registradas. No es lo mismo que no tenerlas:
                            quiere decir que nadie las cargó.
                        </div>
                    @endif

                    <dl class="row mb-0" style="font-size:.9rem">
                        <dt class="col-5 text-muted-warm">Cédula</dt>
                        <dd class="col-7">{{ $c->cedula ?: '—' }}</dd>
                        <dt class="col-5 text-muted-warm">Teléfono</dt>
                        <dd class="col-7">{{ $c->telefono ?: '—' }}</dd>
                        <dt class="col-5 text-muted-warm">Email</dt>
                        <dd class="col-7">{{ $c->email ?: '—' }}</dd>
                        <dt class="col-5 text-muted-warm">Dirección</dt>
                        <dd class="col-7">{{ $c->direccion ?: '—' }}</dd>
                        <dt class="col-5 text-muted-warm">Estado</dt>
                        <dd class="col-7">
                            @if ($c->activo)
                                <span class="badge-estado e-ok">Activo</span>
                            @else
                                <span class="badge-estado e-muted">Inactivo</span>
                            @endif
                        </dd>
                    </dl>

                    @if (trim((string) $c->observaciones) !== '')
                        <div class="mt-3">
                            <div class="text-muted-warm" style="font-size:.8rem">Observaciones</div>
                            <div>{{ $c->observaciones }}</div>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <a class="btn btn-outline-neutro btn-sm"
                       href="{{ route('clientes.historial', $c->id_cliente) }}">
                        <i class="bi bi-clock-history"></i> Historial</a>
                    @if ($urlCita = Navegacion::url('citas.form'))
                        <a class="btn btn-outline-neutro btn-sm"
                           href="{{ $urlCita . '?cliente=' . $c->id_cliente }}">
                            <i class="bi bi-calendar-plus"></i> Nueva cita</a>
                    @endif
                    <a class="btn btn-oro btn-sm" href="{{ route('clientes.form', $c->id_cliente) }}">
                        <i class="bi bi-pencil"></i> Editar</a>
                </div>
            </div>
        </div>
    </div>
@endforeach

@endsection
