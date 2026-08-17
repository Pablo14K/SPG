@extends('layout.app')

@section('titulo', $s ? 'Editar servicio' : 'Nuevo servicio')

@section('contenido')
    @php $id = $s->id_servicio ?? 0; @endphp

    <div class="spg-page-head">
        <a class="spg-back" href="{{ route('servicios.lista') }}"><i class="bi bi-arrow-left"></i> Servicios</a>
        <h1 class="mt-1">{{ $id ? 'Editar servicio' : 'Nuevo servicio' }}</h1>
    </div>

    <div class="spg-panel" style="max-width:720px">
        <form method="post" action="{{ route('servicios.guardar') }}">
            @csrf
            <input type="hidden" name="id_servicio" value="{{ $id }}">

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="nombre">Nombre *</label>
                    <input class="form-control" id="nombre" name="nombre" required
                           value="{{ old('nombre', $s->nombre ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="id_categoria_servicio">Categoría *</label>
                    <select class="form-select" id="id_categoria_servicio" name="id_categoria_servicio" required>
                        @foreach ($cats as $c)
                            <option value="{{ $c->id_categoria_servicio }}"
                                @selected((int) old('id_categoria_servicio', $s->id_categoria_servicio ?? 0) === (int) $c->id_categoria_servicio)>
                                {{ $c->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="precio">Precio</label>
                    <div class="input-group">
                        <span class="input-group-text">{{ config('spg.moneda') }}</span>
                        {{-- input-miles: el JS le pone el separador al escribir y
                             num() lo interpreta del lado del servidor --}}
                        <input class="form-control input-miles" id="precio" name="precio" data-min="0"
                               value="{{ monto_input(old('precio', $s->precio ?? 0)) }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="duracion_min">Duración (minutos)</label>
                    <input type="number" class="form-control" id="duracion_min" name="duracion_min"
                           min="5" max="600" step="5" value="{{ old('duracion_min', $s->duracion_min ?? 30) }}">
                    <div class="form-text">Es la que usa la agenda para calcular los huecos.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="tasa_iva">IVA</label>
                    <select class="form-select" id="tasa_iva" name="tasa_iva">
                        @foreach ([10 => '10 %', 5 => '5 %', 0 => 'Exento'] as $v => $t)
                            <option value="{{ $v }}" @selected((int) old('tasa_iva', $s->tasa_iva ?? 10) === $v)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label" for="descripcion">Descripción</label>
                    <textarea class="form-control" id="descripcion" name="descripcion"
                              rows="2">{{ old('descripcion', $s->descripcion ?? '') }}</textarea>
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="requiere_exclusividad"
                               name="requiere_exclusividad"
                               @checked(old('requiere_exclusividad', $s->requiere_exclusividad ?? 0))>
                        <label class="form-check-label" for="requiere_exclusividad">
                            Requiere atención exclusiva
                        </label>
                        <div class="form-text">
                            Marcalo cuando el servicio no se pueda hacer al mismo tiempo que otro igual: una
                            coloración y una keratina se pisan —las dos son sobre el pelo—, un lavado y una
                            pedicura no. Si los hace la misma persona, van uno después del otro y no hay conflicto.
                        </div>
                    </div>
                </div>

                {{-- **En qué locales se ofrece.** El catálogo es único —«Corte
                     de dama» es UN servicio con un precio— y cada sucursal marca
                     cuáles publica. Sin esto, un local nuevo nacía sin un solo
                     servicio y la clienta no veía nada al querer reservar ahí.
                     Con una sola sucursal el bloque no se dibuja. --}}
                @if (count($sucursales) > 1)
                    <div class="col-12">
                        <label class="form-label">¿En qué sucursales se ofrece?</label>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach ($sucursales as $suc)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sucursales[]"
                                           value="{{ $suc->id_sucursal }}" id="sv{{ $suc->id_sucursal }}"
                                           @checked(in_array((int) $suc->id_sucursal, $publicado, true))>
                                    <label class="form-check-label" for="sv{{ $suc->id_sucursal }}">{{ $suc->nombre }}</label>
                                </div>
                            @endforeach
                        </div>
                        <div class="form-text">
                            Si no marcás ninguna, se ofrece en todas. Un servicio que no se publica en
                            ningún local no se lo puede reservar nadie.
                        </div>
                    </div>
                @endif
            </div>

            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-oro"><i class="bi bi-check-lg"></i> Guardar</button>
                <a class="btn btn-outline-neutro" href="{{ route('servicios.lista') }}">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
