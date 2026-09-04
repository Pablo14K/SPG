@extends('layout.app')

@section('titulo', 'Nueva comisión')

@section('contenido')
    <div class="spg-page-head">
        <a class="spg-back" href="{{ route('seguridad.comisiones') }}"><i class="bi bi-arrow-left"></i> Comisiones</a>
        <h1 class="mt-1">Nueva comisión</h1>
    </div>

    <div class="spg-panel" style="max-width:640px">
        <form method="post" action="{{ route('seguridad.comision.guardar') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label" for="id_usuario">Profesional *</label>
                <select class="form-select" id="id_usuario" name="id_usuario" required>
                    <option value="">— elegí un profesional —</option>
                    @foreach ($profs as $p)
                        <option value="{{ $p->id_usuario }}">{{ $p->nombre }}</option>
                    @endforeach
                </select>
            </div>

            {{-- **La comision puede ser distinta segun el local**, por decision
                 del usuario: la misma persona puede cobrar un porcentaje aca y
                 otro alla. Vacio vale en todas, que es lo que espera un salon de
                 un solo local. Solo se dibuja con mas de una sucursal. --}}
            @if (count($sucursales) > 1)
                <div class="mb-3">
                    <label class="form-label" for="id_sucursal">Sucursal</label><x-ayuda>La del local le gana a la que vale en todas, y se aplica segun donde se haya prestado el servicio.</x-ayuda>
                    <select class="form-select" id="id_sucursal" name="id_sucursal">
                        <option value="0">Todas las sucursales</option>
                        @foreach ($sucursales as $su)
                            <option value="{{ $su->id_sucursal }}">{{ $su->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="mb-3">
                <label class="form-label" for="id_servicio">Servicio</label><x-ayuda>Dejalo en «todos» para una comisión general, o elegí uno para una comisión especial.</x-ayuda>
                <select class="form-select" id="id_servicio" name="id_servicio">
                    <option value="0">Todos los servicios</option>
                    @foreach ($servicios as $s)
                        <option value="{{ $s->id_servicio }}">{{ $s->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-5">
                    <label class="form-label" for="tipo">Tipo</label>
                    <select class="form-select" id="tipo" name="tipo">
                        <option value="PORCENTAJE">Porcentaje del servicio</option>
                        <option value="MONTO">Monto fijo</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="valor">Valor</label>
                    <input class="form-control input-miles" id="valor" name="valor" data-decimales="2"
                           data-min="0" required value="{{ old('valor') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="vigente_desde">Vigente desde</label>
                    <input type="date" class="form-control" id="vigente_desde" name="vigente_desde"
                           value="{{ old('vigente_desde', date('Y-m-d')) }}">
                </div>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-oro"><i class="bi bi-check-lg"></i> Guardar</button>
                <a class="btn btn-outline-neutro" href="{{ route('seguridad.comisiones') }}">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
