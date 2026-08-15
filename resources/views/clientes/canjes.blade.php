@extends('layout.app')

@section('titulo', 'Canjes por puntos')

@section('contenido')
    <x-encabezado sub="Qué servicios puede llevarse una clienta a cambio de sus puntos." />

    <div class="spg-panel">
        <h2 class="spg-form-titulo mb-1"><i class="bi bi-gift"></i> Sumar un servicio a los canjes</h2>
        <p class="text-muted-warm" style="font-size:.82rem">
            La clienta ve estos servicios en su portal y los canjea sola. Al canjearlo se le
            descuentan los puntos y le queda <strong>guardado para usar en una cita</strong>,
            hasta la fecha que fije la vigencia.
        </p>

        <form method="post" action="{{ route('clientes.canje.guardar') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-5">
                <label class="form-label" for="id_servicio">Servicio</label>
                <input class="form-control form-control-sm mb-1" data-filtra="#id_servicio"
                       placeholder="Buscar…" autocomplete="off">
                <select class="form-select" id="id_servicio" name="id_servicio" required>
                    <option value="">Elegí un servicio…</option>
                    @foreach ($servicios as $s)
                        <option value="{{ $s->id_servicio }}" @selected((int) old('id_servicio') === (int) $s->id_servicio)>
                            {{ $s->categoria }} · {{ $s->nombre }} ({{ money($s->precio) }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="puntos">Cuesta (en puntos)</label>
                <input class="form-control" id="puntos" name="puntos" data-min="1"
                       value="{{ old('puntos', 100) }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="dias_vigencia">Vigencia</label>
                <div class="input-group">
                    <input class="form-control" id="dias_vigencia" name="dias_vigencia"
                           data-min="1" data-max="365" value="{{ old('dias_vigencia', 30) }}" required>
                    <span class="input-group-text">días</span>
                </div>
            </div>
            <div class="col-md-2">
                <button class="btn btn-oro w-100"><i class="bi bi-plus-lg"></i> Sumar</button>
            </div>
            <div class="col-12">
                <p class="text-muted-warm mb-0" style="font-size:.8rem">
                    La vigencia se cuenta <strong>desde que la clienta canjea</strong>, no desde hoy:
                    con 30 días, quien canjea el 5 tiene hasta el 4 del mes siguiente para usarlo.
                </p>
            </div>
        </form>
    </div>

    @if ($rows)
        <div class="spg-panel mt-3">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-list-check"></i> Lo que se puede canjear</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Servicio</th><th class="text-end">Vale</th>
                            <th class="text-end" style="width:120px">Puntos</th>
                            <th class="text-end" style="width:120px">Vigencia</th>
                            <th>Estado</th><th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr>
                                <td>
                                    {{ $r->nombre }}
                                    <div class="text-muted-warm" style="font-size:.8rem">{{ $r->categoria }}</div>
                                </td>
                                <td class="text-end text-muted-warm">{{ money($r->precio) }}</td>
                                {{-- Los campos van atados con `form=` a un formulario declarado
                                     FUERA de la tabla. Un `<form>` no puede cruzar celdas: el
                                     navegador lo saca del `<tbody>` y la fila se desarma. --}}
                                <td class="text-end">
                                    <input class="form-control form-control-sm text-end" name="puntos"
                                           form="fc{{ $r->id_servicio_canjeable }}"
                                           value="{{ (int) $r->puntos }}" data-min="1" aria-label="Puntos">
                                </td>
                                <td class="text-end">
                                    <input class="form-control form-control-sm text-end" name="dias_vigencia"
                                           form="fc{{ $r->id_servicio_canjeable }}"
                                           value="{{ (int) $r->dias_vigencia }}" data-min="1" data-max="365"
                                           aria-label="Días de vigencia">
                                </td>
                                <td>
                                    @if (! $r->activo)
                                        <span class="badge-estado e-muted">Sin ofrecer</span>
                                    @elseif (! $r->servicio_activo)
                                        <span class="badge-estado e-warn" title="El servicio está dado de baja">servicio inactivo</span>
                                    @else
                                        <span class="badge-estado e-ok">Se ofrece</span>
                                    @endif
                                </td>
                                <td class="text-end" style="white-space:nowrap">
                                    <button class="btn btn-sm btn-outline-neutro"
                                            form="fc{{ $r->id_servicio_canjeable }}">Guardar</button>
                                    <button class="btn btn-sm btn-outline-neutro"
                                            form="fb{{ $r->id_servicio_canjeable }}"
                                            data-confirmar="Los canjes que las clientas ya hicieron siguen valiendo. ¿Seguimos?">
                                        {{ $r->activo ? 'Dejar de ofrecer' : 'Volver a ofrecer' }}
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-muted-warm mt-2 mb-0" style="font-size:.8rem">
                Cambiar los puntos o la vigencia <strong>no toca lo que ya se canjeó</strong>: cada
                canje guardó las condiciones del día en que se hizo.
            </p>
        </div>

        {{-- Los formularios de cada fila, fuera de la tabla. Los botones y los
             campos los alcanzan con `form=`. --}}
        @foreach ($rows as $r)
            <form method="post" action="{{ route('clientes.canje.editar') }}" id="fc{{ $r->id_servicio_canjeable }}" hidden>
                @csrf
                <input type="hidden" name="id_servicio_canjeable" value="{{ $r->id_servicio_canjeable }}">
            </form>
            <form method="post" action="{{ route('clientes.canje.baja') }}" id="fb{{ $r->id_servicio_canjeable }}" hidden>
                @csrf
                <input type="hidden" name="id_servicio_canjeable" value="{{ $r->id_servicio_canjeable }}">
            </form>
        @endforeach
    @endif

    @if ($canjeados)
        <div class="spg-panel mt-3">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-clock-history"></i> Últimos canjes de las clientas</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr><th>Cuándo</th><th>Clienta</th><th>Servicio</th>
                            <th class="text-end">Puntos</th><th>Vence</th><th>Estado</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($canjeados as $c)
                            <tr>
                                <td style="white-space:nowrap">{{ fecha($c->fecha, 'd/m/Y') }}</td>
                                <td>{{ $c->cliente }}</td>
                                <td>{{ $c->servicio }}</td>
                                <td class="text-end">{{ (int) $c->puntos }}</td>
                                <td style="white-space:nowrap">{{ fecha($c->vence_en, 'd/m/Y') }}</td>
                                <td>
                                    @switch($c->estado)
                                        @case('USADO')
                                            <span class="badge-estado e-ok">Usado</span> @break
                                        @case('VENCIDO')
                                            <span class="badge-estado e-no">Vencido</span> @break
                                        @default
                                            <span class="badge-estado e-warn">Sin usar</span>
                                    @endswitch
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
