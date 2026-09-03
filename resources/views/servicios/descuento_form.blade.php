@extends('layout.app')

@section('titulo', $d ? 'Editar descuento' : 'Nuevo descuento')

@section('contenido')
    @php $id = $d->id_descuento ?? 0; @endphp

    <div class="spg-page-head">
        <a class="spg-back" href="{{ route('servicios.descuentos') }}"><i class="bi bi-arrow-left"></i> Descuentos</a>
        <h1 class="mt-1">{{ $id ? 'Editar descuento' : 'Nuevo descuento' }}</h1>
    </div>

    @if ($nivel)
        <div class="alert alert-warning">
            Este descuento está atado al nivel <strong>{{ $nivel }}</strong> de fidelización: lo aplica el
            sistema por cantidad de visitas y vale para toda la factura. Elegirle servicios no cambia nada.
        </div>
    @endif

    <div class="spg-panel" style="max-width:820px">
        <form method="post" action="{{ route('servicios.descuento.guardar') }}">
            @csrf
            <input type="hidden" name="id_descuento" value="{{ $id }}">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="nombre">Nombre *</label>
                    <input class="form-control" id="nombre" name="nombre" required maxlength="80"
                           value="{{ old('nombre', $d->nombre ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="tipo">Tipo</label>
                    <select class="form-select" id="tipo" name="tipo">
                        <option value="PORCENTAJE" @selected(old('tipo', $d->tipo ?? 'PORCENTAJE') === 'PORCENTAJE')>Porcentaje</option>
                        <option value="MONTO" @selected(old('tipo', $d->tipo ?? '') === 'MONTO')>Monto fijo</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="valor">Valor *</label>
                    <input class="form-control input-miles" id="valor" name="valor" data-decimales="2"
                           data-min="1" required
                           {{-- **`cant()` y no el valor crudo.** El campo lo dibuja
                                `input-miles`, que habla en formato español: la coma es el
                                decimal y el punto agrupa de a tres. Un `DECIMAL` de SQL
                                llega como «10.00», y `formatear()` le saca el punto y lo
                                convierte en 1.000 — **cada edición multiplicaba el valor
                                por cien**. --}}
                           value="{{ old('valor', isset($d) ? cant($d->valor) : '') }}">
                    {{-- **Cero no es un descuento, es no tener descuento**, y una
                         promoción al 0 % ocupa lugar en la lista sin hacer nada.
                         El tope del 100 % lo comprueba el servidor: en porcentaje
                         no puede pasarse, y en monto fijo la base ya topea al
                         total de la factura. --}}
                    <div class="form-text">
                        En porcentaje, entre 1 y 100. En monto fijo, lo que se descuenta
                        en guaraníes.
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="fecha_inicio">Vigente desde</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio"
                           value="{{ old('fecha_inicio', $d->fecha_inicio ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="fecha_fin">Vigente hasta</label><x-ayuda>Dejalas vacías si la promoción no vence.</x-ayuda>
                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin"
                           value="{{ old('fecha_fin', $d->fecha_fin ?? '') }}">
                </div>

                <div class="col-12">
                    <label class="form-label" for="descripcion">Descripción</label>
                    <input class="form-control" id="descripcion" name="descripcion" maxlength="150"
                           value="{{ old('descripcion', $d->descripcion ?? '') }}">
                </div>
            </div>

            @unless ($nivel)
                <hr class="my-4">

                <h2 class="spg-form-titulo mb-1"><i class="bi bi-scissors"></i> ¿A qué servicios aplica?</h2>
                <p class="text-muted-warm" style="font-size:.8rem">
                    Si no marcás ninguno, el descuento se aplica al <strong>total de la factura</strong>.
                    Marcando algunos, «20 % en coloración» no le descuenta la manicura de la misma factura.
                </p>

                <div class="d-flex gap-2 align-items-center mb-2 flex-wrap">
                    <input class="form-control form-control-sm" data-filtra="#listaServicios"
                           placeholder="Buscar un servicio…" autocomplete="off" style="max-width:260px">

                    {{-- Marcar todos de una. Con veinte servicios en la lista,
                         aplicar una promo a todo el catálogo era veinte clics.
                         Es la misma pieza que ya usan la matriz de permisos y
                         los bloques de Reportes (`data-marca-todo` en app.js):
                         refleja lo que hay marcado y prende o apaga el grupo.
                         No lleva `name`, así que no se envía. --}}
                    <div class="form-check mb-0">
                        {{-- El atributo apunta al CONTENEDOR: `app.js` busca los
                             checkboxes adentro. Y no lleva `@checked`: al cargar
                             la página, `reflejar()` la deja como corresponda —
                             marcada, vacía o a medio marcar. --}}
                        <input class="form-check-input" type="checkbox" id="srvTodos"
                               data-marca-todo="#listaServicios">
                        <label class="form-check-label" for="srvTodos">Todos</label>
                    </div>
                </div>

                <div id="listaServicios" class="spg-check-lista">
                    @foreach ($servicios as $s)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="servicios[]"
                                   value="{{ $s->id_servicio }}" id="srv{{ $s->id_servicio }}"
                                   @checked(in_array((int) $s->id_servicio, $elegidos, true))>
                            <label class="form-check-label" for="srv{{ $s->id_servicio }}">
                                {{ $s->nombre }}
                                <span class="text-muted-warm">· {{ money($s->precio) }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
            @endunless

            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-oro"><i class="bi bi-check-lg"></i> Guardar</button>
                <a class="btn btn-outline-neutro" href="{{ route('servicios.descuentos') }}">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
