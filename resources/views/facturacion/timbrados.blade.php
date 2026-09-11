@extends('layout.app')

@section('titulo', 'Timbrados')

@section('contenido')
    <x-encabezado sub="Numeración de los comprobantes según la DNIT (Manual Técnico SIFEN v150): timbrado de 8 dígitos, establecimiento y punto de expedición de 3, y correlativo de 7. El número impreso queda <code>001-001-0000001</code>." />

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="sgp-panel">
                <h2 class="sgp-form-titulo mb-2">
                    <i class="bi bi-file-earmark-text"></i>
                    {{ $editar ? 'Editar timbrado' : 'Nuevo timbrado' }}
                </h2>

                <form method="post" action="{{ route('facturacion.timbrado.guardar') }}">
                    @csrf
                    <input type="hidden" name="id_timbrado" value="{{ $editar->id_timbrado ?? 0 }}">

                    <div class="mb-2">
                        <label class="form-label" for="nro_timbrado">Nº de timbrado * (8 dígitos)</label><x-ayuda campo="nro_timbrado" />
                        <input class="form-control" id="nro_timbrado" name="nro_timbrado" required
                               inputmode="numeric" maxlength="8" pattern="\d{8}"
                               value="{{ old('nro_timbrado', $editar->nro_timbrado ?? '') }}">
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label" for="establecimiento">Establecimiento *</label><x-ayuda campo="establecimiento" />
                            <input class="form-control" id="establecimiento" name="establecimiento" data-solo="numeros" inputmode="numeric" required
                                   maxlength="3" placeholder="001"
                                   value="{{ old('establecimiento', $editar->establecimiento ?? '001') }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="punto_expedicion">Punto de expedición *</label><x-ayuda campo="punto_expedicion" />
                            <input class="form-control" id="punto_expedicion" name="punto_expedicion" data-solo="numeros" inputmode="numeric" required
                                   maxlength="3" placeholder="001"
                                   value="{{ old('punto_expedicion', $editar->punto_expedicion ?? '001') }}">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label" for="id_sucursal">Sucursal *</label>
                        <select class="form-select" id="id_sucursal" name="id_sucursal" required>
                            @foreach ($sucursales as $s)
                                <option value="{{ $s->id_sucursal }}"
                                    @selected((int) old('id_sucursal', $editar->id_sucursal ?? 0) === (int) $s->id_sucursal)>
                                    {{ $s->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label" for="id_tipo_comprobante">Tipo de comprobante *</label><x-ayuda campo="id_tipo_comprobante" />
                        <select class="form-select" id="id_tipo_comprobante" name="id_tipo_comprobante" required>
                            @foreach ($tipos as $t)
                                <option value="{{ $t->id_tipo_comprobante }}"
                                    @selected((int) old('id_tipo_comprobante', $editar->id_tipo_comprobante ?? 0) === (int) $t->id_tipo_comprobante)>
                                    {{ $t->nombre }}</option>
                            @endforeach
                        </select>
                        <x-ayuda>Las notas de crédito usan su propio timbrado.</x-ayuda>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label" for="fecha_inicio">Vigente desde *</label><x-ayuda campo="fecha_inicio" />
                            <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required
                                   value="{{ old('fecha_inicio', $editar->fecha_inicio ?? '') }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="fecha_fin">Vigente hasta *</label><x-ayuda campo="fecha_fin" />
                            <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" required
                                   value="{{ old('fecha_fin', $editar->fecha_fin ?? '') }}">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="nro_desde">Desde el nº</label><x-ayuda campo="nro_desde" />
                            <input type="number" class="form-control" id="nro_desde" name="nro_desde"
                                   min="1" max="9999999" value="{{ old('nro_desde', $editar->nro_desde ?? 1) }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="nro_hasta">Hasta el nº</label><x-ayuda campo="nro_hasta" />
                            <input type="number" class="form-control" id="nro_hasta" name="nro_hasta"
                                   min="1" max="9999999" value="{{ old('nro_hasta', $editar->nro_hasta ?? 9999999) }}">
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-oro w-100"><i class="bi bi-check-lg"></i> Guardar</button>
                        @if ($editar)
                            <a class="btn btn-outline-neutro" href="{{ route('facturacion.timbrados') }}">Cancelar</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="sgp-panel">
                <div class="table-responsive sgp-tabla-movil">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Timbrado</th><th>Vigencia</th>
                                <th>Estado</th><th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $t)
                                <tr>
                                    <td class="sgp-movil-titulo" data-label="Timbrado">
                                        <strong>{{ $t->nro_timbrado }}</strong>
                                        <div class="text-muted-warm" style="font-size:.76rem">
                                            {{ $t->establecimiento }}-{{ $t->punto_expedicion }} · {{ $t->sucursal }}
                                        </div>
                                    </td>
                                    <td class="text-muted-warm" style="font-size:.82rem" data-label="Vigencia">
                                        {{ fecha($t->fecha_inicio, 'd/m/Y') }} – {{ fecha($t->fecha_fin, 'd/m/Y') }}
                                    </td>
                                    <td data-label="Estado">
                                        @if (! $t->activo)
                                            <span class="badge-estado e-muted">Inactivo</span>
                                        @elseif ($t->vigente)
                                            <span class="badge-estado e-ok">Vigente</span>
                                        @else
                                            <span class="badge-estado e-no">Vencido</span>
                                        @endif
                                    </td>
                                    <td class="text-end sgp-movil-acciones" style="white-space:nowrap">
                                        <button class="sgp-btn-detalle" data-bs-toggle="collapse"
                                                data-bs-target="#detTimb{{ $t->id_timbrado }}" aria-expanded="false">
                                            <i class="bi bi-chevron-down"></i> Detalle
                                        </button>
                                        <a class="btn btn-sm btn-outline-neutro" title="Editar"
                                           href="{{ route('facturacion.timbrados', ['editar' => $t->id_timbrado]) }}">
                                            <i class="bi bi-pencil"></i></a>
                                        <form method="post" action="{{ route('facturacion.timbrado.baja') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="id_timbrado" value="{{ $t->id_timbrado }}">
                                            <button class="btn btn-sm btn-outline-neutro"
                                                    title="{{ $t->activo ? 'Desactivar' : 'Activar' }}"
                                                    data-confirmar="¿{{ $t->activo ? 'Desactivar' : 'Activar' }} el timbrado {{ $t->nro_timbrado }}?">
                                                <i class="bi bi-toggle-{{ $t->activo ? 'on' : 'off' }}"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <tr class="sgp-fila-detalle">
                                    <td colspan="4">
                                        <div class="collapse" id="detTimb{{ $t->id_timbrado }}">
                                            <div class="sgp-det-cuerpo">
                                                <div class="sgp-det-grid">
                                                    <div>
                                                        <dt>Comprobante</dt>
                                                        <dd>{{ $t->comprobante }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt>Emitidos</dt>
                                                        <dd>{{ (int) $t->emitidos }} — último {{ (int) $t->ultimo }}/{{ (int) $t->nro_hasta }}</dd>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <div class="sgp-vacio">
                                            <i class="bi bi-file-earmark-text"></i>
                                            <div class="t">No hay timbrados cargados.</div>
                                            <div class="d">Sin timbrado vigente no se puede emitir ningún comprobante.</div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
