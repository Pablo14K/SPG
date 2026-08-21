@extends('layout.app')

@section('titulo', $c ? 'Editar cliente' : 'Nuevo cliente')

@section('contenido')
    @php $id = $c->id_cliente ?? 0; @endphp

    <div class="spg-page-head">
        <a class="spg-back" href="{{ route('clientes.lista') }}"><i class="bi bi-arrow-left"></i> Clientes</a>
        <h1 class="mt-1">{{ $id ? 'Editar cliente' : 'Nuevo cliente' }}</h1>
    </div>

    <div class="spg-panel" style="max-width:720px">
        <form method="post" action="{{ route('clientes.guardar') }}">
            @csrf
            <input type="hidden" name="id_cliente" value="{{ $id }}">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="nombre">Nombre *</label>
                    <input class="form-control" id="nombre" name="nombre" required
                           value="{{ old('nombre', $c->nombre ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="apellido">Apellido *</label>
                    <input class="form-control" id="apellido" name="apellido" required
                           value="{{ old('apellido', $c->apellido ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="cedula">Cédula</label>
                    <input class="form-control" id="cedula" name="cedula" data-solo="documento" inputmode="numeric"
                           value="{{ old('cedula', $c->cedula ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="ruc">RUC</label>
                    <input class="form-control" id="ruc" name="ruc" data-solo="ruc" inputmode="text" value="{{ old('ruc', $c->ruc ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="telefono">Teléfono</label>
                    <input class="form-control" id="telefono" name="telefono" data-solo="telefono" inputmode="tel"
                           value="{{ old('telefono', $c->telefono ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="{{ old('email', $c->email ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="fecha_nacimiento">Fecha de nacimiento</label>
                    <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento"
                           value="{{ old('fecha_nacimiento', $c->fecha_nacimiento ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="direccion">Dirección</label>
                    <input class="form-control" id="direccion" name="direccion" maxlength="255"
                           value="{{ old('direccion', $c->direccion ?? '') }}">
                </div>
                <div class="col-12">
                    <label class="form-label" for="observaciones">Observaciones</label>
                    <textarea class="form-control" id="observaciones" name="observaciones"
                              rows="2">{{ old('observaciones', $c->observaciones ?? '') }}</textarea>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-oro"><i class="bi bi-check-lg"></i> Guardar</button>
                <a class="btn btn-outline-neutro" href="{{ route('clientes.lista') }}">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
