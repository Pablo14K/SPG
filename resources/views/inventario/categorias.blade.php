@extends('layout.app')

@section('titulo', 'Categorías de producto')

@section('contenido')
    <x-encabezado sub="Agrupan el catálogo de productos. Una categoría con productos adentro no se puede eliminar: quedarían sin clasificar." />

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="spg-panel">
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-plus-lg"></i> Nueva categoría</h2>
                <form method="post" action="{{ route('inventario.categoria.crear') }}" class="d-flex gap-2">
                    @csrf
                    <input class="form-control" name="nombre" placeholder="Ej. Tintura" required maxlength="60">
                    <button class="btn btn-oro">Agregar</button>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="spg-panel">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr><th>Categoría</th><th class="text-end">Productos</th><th class="text-end">Acciones</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $c)
                                <tr>
                                    <td>
                                        <form method="post" action="{{ route('inventario.categoria.editar') }}"
                                              class="d-flex gap-2 align-items-center">
                                            @csrf
                                            <input type="hidden" name="id" value="{{ $c->id_categoria }}">
                                            <input class="form-control form-control-sm" name="nombre"
                                                   value="{{ $c->nombre }}" maxlength="60" required>
                                            <button class="btn btn-sm btn-outline-neutro" title="Guardar el nombre">
                                                <i class="bi bi-check-lg"></i></button>
                                        </form>
                                    </td>
                                    <td class="text-end">{{ (int) $c->usos }}</td>
                                    <td class="text-end">
                                        <form method="post" action="{{ route('inventario.categoria.borrar') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="id" value="{{ $c->id_categoria }}">
                                            <button class="btn btn-sm btn-outline-neutro" title="Eliminar"
                                                    data-confirmar="¿Eliminar la categoría «{{ $c->nombre }}»?">
                                                <i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        <div class="spg-vacio">
                                            <i class="bi bi-tags"></i>
                                            <div class="t">Todavía no hay categorías.</div>
                                            <div class="d">Cargá la primera para poder crear productos.</div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
