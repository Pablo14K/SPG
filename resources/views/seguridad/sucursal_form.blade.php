@extends('layout.app')

@section('titulo', $s ? 'Editar sucursal' : 'Nueva sucursal')

@section('contenido')
    @php $id = $s->id_sucursal ?? 0; @endphp

    <div class="spg-page-head">
        <a class="spg-back" href="{{ route('seguridad.sucursales') }}">
            <i class="bi bi-arrow-left"></i> Sucursales</a>
        <h1 class="mt-1">{{ $id ? 'Editar sucursal' : 'Nueva sucursal' }}</h1>
    </div>

    <div class="spg-panel" style="max-width:640px">
        <form method="post" action="{{ route('seguridad.sucursal.guardar') }}">
            @csrf
            <input type="hidden" name="id_sucursal" value="{{ $id }}">

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="nombre">Nombre *</label>
                    <input class="form-control" id="nombre" name="nombre" required
                           value="{{ old('nombre', $s->nombre ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="ruc">RUC</label>
                    <input class="form-control" id="ruc" name="ruc" value="{{ old('ruc', $s->ruc ?? '') }}">
                    <div class="form-text">Se imprime en el comprobante.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="telefono">Teléfono</label>
                    <input class="form-control" id="telefono" name="telefono"
                           value="{{ old('telefono', $s->telefono ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="ciudad">Ciudad</label>
                    <input class="form-control" id="ciudad" name="ciudad"
                           value="{{ old('ciudad', $s->ciudad ?? 'Luque') }}">
                </div>
                <div class="col-12">
                    <label class="form-label" for="direccion">Dirección</label>
                    <input class="form-control" id="direccion" name="direccion"
                           value="{{ old('direccion', $s->direccion ?? '') }}">
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-oro"><i class="bi bi-check-lg"></i> Guardar</button>
                <a class="btn btn-outline-neutro" href="{{ route('seguridad.sucursales') }}">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
