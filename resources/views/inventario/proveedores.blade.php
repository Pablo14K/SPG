@extends('layout.app')

@section('titulo', 'Proveedores')

@section('contenido')
    @php use App\Servicios\Navegacion; @endphp

    <x-encabezado
        sub="Quiénes le venden al salón y cuánto se les debe. El saldo lo calcula la base con las compras confirmadas menos los pagos."
        :accion="['modal' => '#modalProveedor', 't' => 'Nuevo proveedor', 'ic' => 'plus-lg']" />

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
                                {{-- **El mismo modal para crear y para editar.** Dos
                                     formularios iguales se desfasan; y con el de editar en
                                     otra pantalla se perdía el lugar en la lista para
                                     cambiar un teléfono. --}}
                                <button type="button" class="btn btn-sm btn-outline-neutro" title="Editar"
                                        data-bs-toggle="modal" data-bs-target="#modalProveedor"
                                        data-id="{{ $p->id_proveedor }}"
                                        data-nombre="{{ $p->nombre }}"
                                        data-ruc="{{ $p->ruc }}"
                                        data-contacto="{{ $p->contacto }}"
                                        data-telefono="{{ $p->telefono }}"
                                        data-email="{{ $p->email }}"
                                        data-direccion="{{ $p->direccion }}">
                                    <i class="bi bi-pencil"></i></button>
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
    {{-- **El alta y la edición del proveedor, en una ventana.** Son seis campos:
         irse a otra pantalla y volver hacía perder la página de la lista y los
         filtros puestos. El formulario suelto (`inventario.proveedor_form`) sigue
         existiendo para quien llegue por un enlace directo. --}}
    <div class="modal fade" id="modalProveedor" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="{{ route('inventario.proveedor.guardar') }}">
                    @csrf
                    <input type="hidden" name="id_proveedor" id="provId" value="0">
                    <div class="modal-header">
                        <h5 class="modal-title" style="font-size:1rem" id="provTitulo">
                            <i class="bi bi-truck"></i> Nuevo proveedor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label" for="provNombre">Nombre o razón social *</label>
                                <input class="form-control" id="provNombre" name="nombre" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="provRuc">RUC</label>
                                <input class="form-control" id="provRuc" name="ruc" data-solo="ruc" inputmode="text">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="provContacto">Persona de contacto</label>
                                <input class="form-control" id="provContacto" name="contacto">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="provTelefono">Teléfono</label>
                                <input class="form-control" id="provTelefono" name="telefono"
                                       data-solo="telefono" inputmode="tel">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="provEmail">Email</label>
                                <input type="email" class="form-control" id="provEmail" name="email">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="provDireccion">Dirección</label>
                                <input class="form-control" id="provDireccion" name="direccion">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-oro"><i class="bi bi-check-lg"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
// El modal se abre desde dos lados: el botón de la cabecera —sin datos, o sea
// alta— y el lápiz de cada fila, que trae la suya en `data-`. Se limpia siempre
// primero, para que un alta no herede lo del proveedor que se miró antes.
document.getElementById('modalProveedor')?.addEventListener('show.bs.modal', function (ev) {
    var b = ev.relatedTarget || {}, d = b.dataset || {};
    document.getElementById('provId').value = d.id || 0;
    document.getElementById('provTitulo').innerHTML =
        '<i class="bi bi-truck"></i> ' + (d.id ? 'Editar proveedor' : 'Nuevo proveedor');
    document.getElementById('provNombre').value = d.nombre || '';
    document.getElementById('provRuc').value = d.ruc || '';
    document.getElementById('provContacto').value = d.contacto || '';
    document.getElementById('provTelefono').value = d.telefono || '';
    document.getElementById('provEmail').value = d.email || '';
    document.getElementById('provDireccion').value = d.direccion || '';
});
</script>
@endpush
