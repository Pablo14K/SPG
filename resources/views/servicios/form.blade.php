@extends('layout.app')

@section('titulo', $s ? 'Editar servicio' : 'Nuevo servicio')

@section('contenido')

    {{-- **Dónde se ofrece este servicio.** El catálogo es único y cada local
         publica los suyos, así que al editar hace falta saber si lo que se está
         tocando lo dan también en otra sede — un cambio de precio les llega a
         todas. Antes esto no se dibujaba en ningún lado: el dato viajaba y la
         pantalla lo ignoraba. --}}
    @if (! empty($tambienEn))
        <div class="alert alert-warning">
            <strong>Este servicio también se ofrece en:</strong>
            @foreach ($tambienEn as $t)
                <span class="spg-rol-chip">{{ $t->nombre }}</span>
            @endforeach
            <div class="mt-1" style="font-size:.82rem">
                El precio, la duración y el nombre son del catálogo, así que lo que cambies
                acá vale también allá. Lo único que es de cada local es si lo publica o no.
            </div>
        </div>
    @elseif (! empty($id))
        <div class="alert alert-secondary" style="font-size:.85rem">
            Este servicio lo ofrece <strong>sólo esta sucursal</strong>.
        </div>
    @endif

    {{-- **Antes de cargar uno nuevo, mirá si ya existe.** Escrito de nuevo,
         «Corte de dama» queda como dos filas con el nombre distinto según quién
         lo tipeó, cada una con su precio y su duración, y a partir de ahí ningún
         informe puede comparar el mismo servicio entre sucursales. Traerlo no
         copia nada: agrega la fila que dice que este local también lo ofrece. --}}
    @if (! empty($ajenos))
        <div class="spg-panel mb-3">
            <h2 class="spg-form-titulo mb-1"><i class="bi bi-box-arrow-in-down"></i> Ya existe en otra sucursal</h2>
            <p class="text-muted-warm mb-2" style="font-size:.82rem">
                Estos servicios ya están cargados en otro local. Traelos acá en vez de
                escribirlos de nuevo: es el mismo servicio, con su precio y su duración.
            </p>
            <div class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label" for="traer">Servicio</label>
                    <select class="form-select" id="traer" form="formTraer" name="id_servicio" required>
                        <option value="">— elegí uno —</option>
                        @foreach ($ajenos as $a)
                            <option value="{{ $a->id_servicio }}">
                                {{ $a->nombre }} · {{ $a->categoria }} · {{ money($a->precio) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <form method="post" action="{{ route('servicios.publicar') }}" id="formTraer">
                        @csrf
                        <button class="btn btn-oro w-100"><i class="bi bi-plus-lg"></i> Traer acá</button>
                    </form>
                </div>
            </div>
            <input data-filtra="#traer" class="form-control form-control-sm mt-2"
                   placeholder="Filtrar la lista…">
        </div>
    @endif
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
                    {{-- **La seña la fija el salón, no la clienta.** Sin esto el
                         sistema no podía contestar «¿este servicio la pide?» ni
                         «¿de cuánto?», así que la clienta anunciaba el monto que
                         quisiera y el salón se lo confirmaba de palabra.

                         Va como PORCENTAJE y no como monto fijo: un monto se
                         separa del precio el día que el servicio sube —queda una
                         seña de 50.000 sobre un servicio de 400.000— y hay que
                         acordarse de tocar los dos. --}}
                    <label class="form-label" for="sena_porcentaje">Seña que se pide</label>
                    <div class="input-group">
                        <input class="form-control" id="sena_porcentaje" name="sena_porcentaje"
                               data-solo="numeros" inputmode="numeric" maxlength="3" placeholder="sin seña"
                               value="{{ old('sena_porcentaje', $s->sena_porcentaje ?? '') }}">
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">Vacío = no pide seña. Ej: 50 para media reserva.</div>
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
                    {{-- **La zona decide qué se puede hacer a la vez.** Antes había
                         acá una casilla, «Requiere atención exclusiva», y con un
                         booleano no se podía expresar el caso normal: coloración y
                         lavado suman aunque el lavado no sea «exclusivo», porque las
                         dos son sobre la misma cabeza; coloración y manicura no, porque
                         son partes distintas. Lo que impide el paralelo no es una
                         propiedad del servicio: es que compartan la parte del cuerpo. --}}
                    <div class="col-md-6">
                        <label class="form-label" for="id_zona">¿Sobre qué parte trabaja?</label>
                        <select class="form-select" id="id_zona" name="id_zona">
                            <option value="">— sin especificar —</option>
                            @foreach ($zonas as $z)
                                <option value="{{ $z->id_zona }}"
                                    @selected((int) old('id_zona', $s->id_zona ?? 0) === (int) $z->id_zona)>
                                    {{ $z->nombre }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">
                            Dos servicios de la <strong>misma</strong> parte se hacen uno después del
                            otro y los tiempos se suman; de partes distintas se hacen a la vez y la
                            cita dura lo del más largo. Sin especificar, se puede hacer junto con
                            cualquier cosa.
                        </div>
                    </div>
                </div>

                {{-- **En qué locales se ofrece.** El catálogo es único —«Corte
                     de dama» es UN servicio con un precio— y cada sucursal marca
                     cuáles publica. Sin esto, un local nuevo nacía sin un solo
                     servicio y la clienta no veía nada al querer reservar ahí.
                     Con una sola sucursal el bloque no se dibuja. --}}
            </div>

            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-oro"><i class="bi bi-check-lg"></i> Guardar</button>
                <a class="btn btn-outline-neutro" href="{{ route('servicios.lista') }}">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
