@extends('layout.app')

@section('titulo', $editar ? 'Editar comisión' : 'Nueva comisión')

@section('contenido')
    <div class="spg-page-head">
        <a class="spg-back" href="{{ route('seguridad.comisiones') }}"><i class="bi bi-arrow-left"></i> Comisiones</a>
        <h1 class="mt-1">{{ $editar ? 'Editar comisión' : 'Nueva comisión' }}</h1>
    </div>

    <div class="spg-panel" style="max-width:640px">
        {{-- **Editar una comisión cambia lo que ya se liquidó, y hay que
             decirlo.** `fn_comision_servicio` la calcula al vuelo tomando la
             vigente a la fecha del servicio, así que tocar el valor mueve
             también lo que los informes muestran de atenciones pasadas. Quien
             quiere cambiar de acá en adelante tiene la otra salida, y la
             pantalla la nombra en vez de dejar que se descubra después. --}}
        @if ($editar)
            <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:.9rem">
                <i class="bi bi-exclamation-triangle"></i>
                Cambiar el valor también cambia lo que se calcula para las atenciones
                <strong>ya realizadas</strong> desde esa fecha.
                Si lo que querés es cambiar de ahora en adelante, dale de baja a ésta
                y cargá una nueva con la fecha de hoy.
            </div>
        @endif

        <form method="post" action="{{ route('seguridad.comision.guardar') }}">
            @csrf
            @if ($editar)
                <input type="hidden" name="id_comision" value="{{ $editar->id_comision }}">
            @endif

            <div class="mb-3">
                <label class="form-label" for="id_usuario">Profesional *</label>
                <select class="form-select" id="id_usuario" name="id_usuario" required>
                    <option value="">— elegí un profesional —</option>
                    @foreach ($profs as $p)
                        <option value="{{ $p->id_usuario }}"
                            @selected(old('id_usuario', $editar->id_usuario ?? '') == $p->id_usuario)>{{ $p->nombre }}</option>
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
                            <option value="{{ $su->id_sucursal }}"
                                @selected(old('id_sucursal', $editar->id_sucursal ?? 0) == $su->id_sucursal)>{{ $su->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="mb-3">
                <label class="form-label" for="id_servicio">Servicio</label><x-ayuda>Dejalo en «todos» para una comisión general, o elegí uno para una comisión especial.</x-ayuda>
                <select class="form-select" id="id_servicio" name="id_servicio">
                    <option value="0">Todos los servicios</option>
                    @foreach ($servicios as $s)
                        <option value="{{ $s->id_servicio }}"
                            @selected(old('id_servicio', $editar->id_servicio ?? 0) == $s->id_servicio)>{{ $s->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-5">
                    <label class="form-label" for="tipo">Tipo</label>
                    <select class="form-select" id="tipo" name="tipo">
                        <option value="PORCENTAJE" @selected(old('tipo', $editar->tipo ?? '') === 'PORCENTAJE')>Porcentaje del servicio</option>
                        <option value="MONTO" @selected(old('tipo', $editar->tipo ?? '') === 'MONTO')>Monto fijo</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="valor">Valor</label><x-ayuda campo="valor" />
                    <input class="form-control input-miles" id="valor" name="valor" data-decimales="2"
                           data-min="0" required value="{{ old('valor', isset($editar) && $editar ? cant($editar->valor) : '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="vigente_desde">Vigente desde</label><x-ayuda campo="vigente_desde" />
                    <input type="date" class="form-control" id="vigente_desde" name="vigente_desde"
                           value="{{ old('vigente_desde', $editar->vigente_desde ?? date('Y-m-d')) }}">
                </div>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-oro"><i class="bi bi-check-lg"></i> Guardar</button>
                <a class="btn btn-outline-neutro" href="{{ route('seguridad.comisiones') }}">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
