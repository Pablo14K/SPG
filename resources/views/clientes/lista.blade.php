@extends('layout.app')

@section('titulo', 'Clientes')

@section('contenido')
    @php use App\Servicios\Navegacion; @endphp

    <x-encabezado
        sub="Registro de clientes del salón, con sus datos de contacto. Las visitas, los puntos y el nivel se miran en Promociones → Visitas y puntos."
        :accion="['ruta' => 'clientes.form', 't' => 'Nuevo cliente', 'ic' => 'person-plus']" />

    <div class="spg-panel">
        <x-filtros :f="$f" />

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Cliente</th><th>Cédula</th><th>Teléfono</th><th>Email</th>
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
                        <tr>
                            <td>
                                <a class="link-oro" href="{{ route('clientes.historial', $c->id_cliente) }}">
                                    {{ $c->apellido . ', ' . $c->nombre }}</a>
                            </td>
                            <td>{{ $c->cedula ?: '—' }}</td>
                            <td>{{ $c->telefono ?: '—' }}</td>
                            <td class="text-muted-warm">{{ $c->email ?: '—' }}</td>
                            <td>
                                @if ($c->activo)
                                    <span class="badge-estado e-ok">Activo</span>
                                @else
                                    <span class="badge-estado e-muted">Inactivo</span>
                                @endif
                            </td>
                            <td class="text-end" style="white-space:nowrap">
                                <a class="btn btn-sm btn-outline-neutro" title="Historial"
                                   href="{{ route('clientes.historial', $c->id_cliente) }}">
                                    <i class="bi bi-clock-history"></i></a>

                                @if ($urlCita = Navegacion::url('citas.form'))
                                    <a class="btn btn-sm btn-outline-neutro" title="Nueva cita"
                                       href="{{ $urlCita . '?cliente=' . $c->id_cliente }}">
                                        <i class="bi bi-calendar-plus"></i></a>
                                @endif

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
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="spg-vacio">
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
@endsection
