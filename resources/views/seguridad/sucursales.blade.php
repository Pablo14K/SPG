@extends('layout.app')

@section('titulo', 'Sucursales')

@section('contenido')
    <x-encabezado
        sub="Los locales del salón. El RUC y la dirección de la sucursal son los que se imprimen en el comprobante."
        :accion="['ruta' => 'seguridad.sucursal_form', 't' => 'Nueva sucursal', 'ic' => 'plus-lg']" />

    <div class="spg-panel">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Nombre</th><th>RUC</th><th>Ciudad</th><th>Teléfono</th>
                        <th class="text-end">Personal</th><th>Estado</th><th class="text-end">Acciones</th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $s)
                        <tr>
                            <td>{{ $s->nombre }}</td>
                            <td class="text-muted-warm">{{ $s->ruc ?: '—' }}</td>
                            <td class="text-muted-warm">{{ $s->ciudad ?: '—' }}</td>
                            <td>{{ $s->telefono ?: '—' }}</td>
                            <td class="text-end">{{ (int) $s->personal }}</td>
                            <td>
                                @if ($s->activo)
                                    <span class="badge-estado e-ok">Activa</span>
                                @else
                                    <span class="badge-estado e-muted">Inactiva</span>
                                @endif
                            </td>
                            <td class="text-end" style="white-space:nowrap">
                                <a class="btn btn-sm btn-outline-neutro" title="Editar"
                                   href="{{ route('seguridad.sucursal_form', $s->id_sucursal) }}">
                                    <i class="bi bi-pencil"></i></a>
                                <form method="post" action="{{ route('seguridad.sucursal.baja') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="id_sucursal" value="{{ $s->id_sucursal }}">
                                    <button class="btn btn-sm btn-outline-neutro"
                                            title="{{ $s->activo ? 'Desactivar' : 'Activar' }}"
                                            data-confirmar="¿{{ $s->activo ? 'Desactivar' : 'Activar' }} «{{ $s->nombre }}»?">
                                        <i class="bi bi-toggle-{{ $s->activo ? 'on' : 'off' }}"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="spg-vacio">
                                    <i class="bi bi-shop"></i>
                                    <div class="t">No hay sucursales cargadas.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
