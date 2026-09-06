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
                    <label class="form-label" for="nombre">Nombre *</label><x-ayuda campo="nombre" />
                    <input class="form-control" id="nombre" name="nombre" required
                           {{-- Se puede llegar con el nombre puesto desde la agenda:
                                la cita reservada para otra persona ofrece abrirle su
                                ficha, y retipear el nombre que ya está a la vista es
                                justo lo que ese atajo viene a evitar. --}}
                           value="{{ old('nombre', $c->nombre ?? request()->query('nombre', '')) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="apellido">Apellido *</label><x-ayuda campo="apellido" />
                    <input class="form-control" id="apellido" name="apellido" required
                           value="{{ old('apellido', $c->apellido ?? request()->query('apellido', '')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="cedula">Cédula</label><x-ayuda campo="cedula" />
                    <input class="form-control" id="cedula" name="cedula" data-solo="documento" inputmode="numeric"
                           value="{{ old('cedula', $c->cedula ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="ruc">RUC</label><x-ayuda campo="ruc" />
                    <input class="form-control" id="ruc" name="ruc" data-solo="ruc" inputmode="text" value="{{ old('ruc', $c->ruc ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="telefono">Teléfono</label><x-ayuda campo="telefono" />
                    <input class="form-control" id="telefono" name="telefono" data-solo="telefono" inputmode="tel"
                           value="{{ old('telefono', $c->telefono ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">Email</label><x-ayuda campo="email" />
                    <input type="email" class="form-control" id="email" name="email"
                           value="{{ old('email', $c->email ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="fecha_nacimiento">Fecha de nacimiento</label><x-ayuda campo="fecha_nacimiento" />
                    <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento"
                           value="{{ old('fecha_nacimiento', $c->fecha_nacimiento ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="direccion">Dirección</label><x-ayuda campo="direccion" />
                    <input class="form-control" id="direccion" name="direccion" maxlength="255"
                           value="{{ old('direccion', $c->direccion ?? '') }}">
                </div>
                {{-- **Las alergias van aparte de las observaciones, y arriba.**
                     Anotadas entre las notas sueltas quedan mezcladas con
                     «prefiere las 10» y no las lee nadie antes de preparar una
                     mezcla. Es el único dato de la ficha que puede lastimar a
                     alguien si se pasa por alto, así que tiene su propio campo
                     y su propio destaque en el historial y en la agenda. --}}
                <div class="col-12">
                    <label class="form-label" for="alergias">
                        <i class="bi bi-exclamation-triangle txt-no"></i> Alergias y contraindicaciones
                    </label><x-ayuda campo="alergias" />
                    <textarea class="form-control" id="alergias" name="alergias" rows="2"
                              maxlength="300"
                              placeholder="Amoníaco, tinturas con PPD, látex…">{{ old('alergias', $c->alergias ?? '') }}</textarea>
                    <div class="form-text">
                        Se muestra destacado antes de atenderla. Vacío significa «sin registrar»,
                        no «no tiene ninguna».
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="observaciones">Observaciones</label><x-ayuda campo="observaciones" />
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
