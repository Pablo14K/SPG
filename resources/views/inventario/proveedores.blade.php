@extends('layout.app')

@section('titulo', 'Proveedores')

@section('contenido')
    @php use App\Servicios\Navegacion; @endphp

    <x-encabezado
        sub="Quiénes le venden al salón y cuánto se les debe. El saldo lo calcula la base con las compras confirmadas menos los pagos."
        :accion="['ruta' => 'inventario.proveedor_form', 't' => 'Nuevo proveedor', 'ic' => 'plus-lg']" />

    <div class="spg-panel">
        <x-filtros :f="$f" />

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Proveedor</th><th>RUC</th><th>Contacto</th><th>Teléfono</th>
                        <th class="text-end">Saldo</th><th>Estado</th><th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $p)
                        <tr>
                            <td>{{ $p->nombre }}</td>
                            <td class="text-muted-warm">{{ $p->ruc ?: '—' }}</td>
                            <td class="text-muted-warm">{{ $p->contacto ?: '—' }}</td>
                            <td>{{ $p->telefono ?: '—' }}</td>
                            <td class="text-end">
                                @if ((float) $p->saldo > 0.01)
                                    <strong class="txt-no">{{ money($p->saldo) }}</strong>
                                @else
                                    <span class="txt-ok">sin deuda</span>
                                @endif
                            </td>
                            <td>
                                @if ($p->activo)
                                    <span class="badge-estado e-ok">Activo</span>
                                @else
                                    <span class="badge-estado e-muted">Inactivo</span>
                                @endif
                            </td>
                            <td class="text-end" style="white-space:nowrap">
                                @if ($urlCompra = Navegacion::url('inventario.compra_form'))
                                    <a class="btn btn-sm btn-outline-neutro" title="Nueva compra"
                                       href="{{ $urlCompra . '?proveedor=' . $p->id_proveedor }}">
                                        <i class="bi bi-bag-plus"></i></a>
                                @endif
                                <a class="btn btn-sm btn-outline-neutro" title="Editar"
                                   href="{{ route('inventario.proveedor_form', $p->id_proveedor) }}">
                                    <i class="bi bi-pencil"></i></a>
                                <form method="post" action="{{ route('inventario.proveedor.baja') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="id_proveedor" value="{{ $p->id_proveedor }}">
                                    <button class="btn btn-sm btn-outline-neutro"
                                            title="{{ $p->activo ? 'Desactivar' : 'Activar' }}"
                                            data-confirmar="¿{{ $p->activo ? 'Desactivar' : 'Activar' }} a {{ $p->nombre }}?">
                                        <i class="bi bi-toggle-{{ $p->activo ? 'on' : 'off' }}"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="spg-vacio">
                                    <i class="bi bi-truck"></i>
                                    <div class="t">{{ $f['activos'] ? 'Ningún proveedor coincide con esos filtros.' : 'Todavía no hay proveedores cargados.' }}</div>
                                    <div class="d">Se cargan al registrar la primera compra, o desde acá.</div>
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
