@extends('layout.app')

@section('titulo', 'Descuentos')

@section('contenido')
    @php use App\Servicios\Permisos; @endphp
    <x-encabezado
        sub="Promociones del salón. Se aplica <strong>una sola</strong> por factura: la que más le convenga al cliente entre su nivel de fidelización y la mejor promoción vigente, nunca las dos sumadas."
        :accion="['ruta' => 'servicios.descuento_form', 't' => 'Nuevo descuento', 'ic' => 'plus-lg']" />

    {{-- Cuánto vale un punto. Va acá y no en un archivo de configuración porque
         contesta la misma pregunta que los descuentos —cuánto le devuelve el
         salón al cliente por comprar acá— y porque lo decide el salón, no quien
         programa: antes cambiarlo era editar código y volver a desplegar. --}}
    <div class="spg-panel mb-3">
        <form method="post" action="{{ route('servicios.puntos.guardar') }}"
              class="d-flex align-items-end gap-2 flex-wrap">
            @csrf
            <div>
                <label class="form-label mb-1" for="puntos_cada_gs">
                    <i class="bi bi-award txt-oro"></i> Fidelización
                </label>
                <div class="d-flex align-items-center gap-2">
                    <span>1 punto por cada</span>
                    <div class="input-group" style="width:190px">
                        <span class="input-group-text">{{ config('spg.moneda') }}</span>
                        <input class="form-control input-miles" id="puntos_cada_gs" name="puntos_cada_gs"
                               data-min="100" data-max="10000000"
                               value="{{ monto_input($puntosCadaGs) }}" required>
                    </div>
                    <span>facturados</span>
                    <button class="btn btn-oro">Guardar</button>
                </div>
            </div>
        </form>
        <p class="text-muted-warm mb-0 mt-2" style="font-size:.8rem">
            Hoy, una factura de {{ money($puntosCadaGs * 32) }} le deja
            <strong>32 puntos</strong> al cliente. Los puntos que las clientas ya tienen
            <strong>no cambian</strong>: esto vale de acá en adelante.
            @if (Permisos::puede('clientes.canjes'))
                Lo que se puede canjear con ellos se carga en
                <a class="link-oro" href="{{ route('clientes.canjes') }}">Clientes → Canjes por puntos</a>.
            @endif
        </p>
    </div>

    <div class="spg-panel">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Descuento</th><th class="text-end">Valor</th><th>Vigencia</th>
                        <th>Estado</th><th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $d)
                        @php
                            $vigente = (! $d->fecha_inicio || $d->fecha_inicio <= date('Y-m-d'))
                                    && (! $d->fecha_fin || $d->fecha_fin >= date('Y-m-d'));
                        @endphp
                        <tr>
                            <td>
                                {{ $d->nombre }}
                                @if ($d->descripcion)
                                    <div class="text-muted-warm" style="font-size:.76rem">{{ $d->descripcion }}</div>
                                @endif
                            </td>
                            <td class="text-end">
                                {{ $d->tipo === 'PORCENTAJE' ? cant($d->valor) . ' %' : money($d->valor) }}
                            </td>
                            <td class="text-muted-warm">
                                @if ($d->fecha_inicio || $d->fecha_fin)
                                    {{ $d->fecha_inicio ? fecha($d->fecha_inicio, 'd/m/Y') : 'siempre' }}
                                    –
                                    {{ $d->fecha_fin ? fecha($d->fecha_fin, 'd/m/Y') : 'sin fin' }}
                                @else
                                    Sin límite de fechas
                                @endif
                            </td>
                            <td>
                                @if (! $d->activo)
                                    <span class="badge-estado e-muted">Inactivo</span>
                                @elseif ($vigente)
                                    <span class="badge-estado e-ok">Vigente</span>
                                @else
                                    <span class="badge-estado e-warn">Fuera de fecha</span>
                                @endif
                            </td>
                            <td class="text-end" style="white-space:nowrap">
                                <a class="btn btn-sm btn-outline-neutro" title="Editar"
                                   href="{{ route('servicios.descuento_form', $d->id_descuento) }}">
                                    <i class="bi bi-pencil"></i></a>
                                <form method="post" action="{{ route('servicios.descuento.baja') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="id_descuento" value="{{ $d->id_descuento }}">
                                    <button class="btn btn-sm btn-outline-neutro"
                                            title="{{ $d->activo ? 'Desactivar' : 'Activar' }}"
                                            data-confirmar="¿{{ $d->activo ? 'Desactivar' : 'Activar' }} «{{ $d->nombre }}»?">
                                        <i class="bi bi-toggle-{{ $d->activo ? 'on' : 'off' }}"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="spg-vacio">
                                    <i class="bi bi-percent"></i>
                                    <div class="t">Todavía no hay descuentos cargados.</div>
                                    <div class="d">Los de los niveles de fidelización se crean solos con el sistema.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
