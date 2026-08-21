@extends('layout.app')

@section('titulo', $p ? 'Editar proveedor' : 'Nuevo proveedor')

@section('contenido')
    @php $id = $p->id_proveedor ?? 0; @endphp

    <div class="spg-page-head">
        <a class="spg-back" href="{{ route('inventario.proveedores') }}">
            <i class="bi bi-arrow-left"></i> Proveedores</a>
        <h1 class="mt-1">{{ $id ? 'Editar proveedor' : 'Nuevo proveedor' }}</h1>
    </div>

    <div class="spg-panel" style="max-width:720px">
        <form method="post" action="{{ route('inventario.proveedor.guardar') }}">
            @csrf
            <input type="hidden" name="id_proveedor" value="{{ $id }}">

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="nombre">Nombre o razón social *</label>
                    <input class="form-control" id="nombre" name="nombre" required
                           value="{{ old('nombre', $p->nombre ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="ruc">RUC</label>
                    <input class="form-control" id="ruc" name="ruc" data-solo="ruc" inputmode="text" value="{{ old('ruc', $p->ruc ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="contacto">Persona de contacto</label>
                    <input class="form-control" id="contacto" name="contacto"
                           value="{{ old('contacto', $p->contacto ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="telefono">Teléfono</label>
                    <input class="form-control" id="telefono" name="telefono" data-solo="telefono" inputmode="tel"
                           value="{{ old('telefono', $p->telefono ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="{{ old('email', $p->email ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="direccion">Dirección</label>
                    <input class="form-control" id="direccion" name="direccion"
                           value="{{ old('direccion', $p->direccion ?? '') }}">
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-oro"><i class="bi bi-check-lg"></i> Guardar</button>
                <a class="btn btn-outline-neutro" href="{{ route('inventario.proveedores') }}">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
