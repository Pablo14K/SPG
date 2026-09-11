@extends('layout.app')

@section('titulo', 'Comisiones')

@section('contenido')
    <x-encabezado
        sub="Lo que le toca a cada profesional por servicio. La comisión de cada atención la calcula <code>fn_comision_servicio</code> en la base, tomando la vigente a esa fecha."
        :accion="['ruta' => 'seguridad.comision_form', 't' => 'Nueva comisión', 'ic' => 'plus-lg']" />

    <div class="sgp-panel">
        <div class="table-responsive sgp-tabla-movil">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Profesional</th><th>Servicio</th>
                        <th class="text-end">Valor</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $c)
                        {{-- Main row: only essential columns --}}
                        <tr>
                            <td class="sgp-movil-titulo" data-label="Profesional">{{ $c->profesional }}</td>
                            <td class="text-muted-warm" data-label="Servicio">{{ $c->servicio }}</td>
                            <td class="text-end" data-label="Valor">
                                <strong>{{ $c->tipo === 'PORCENTAJE' ? cant($c->valor) . ' %' : money($c->valor) }}</strong>
                            </td>
                            <td class="text-end sgp-movil-acciones" style="white-space:nowrap">
                                <button class="sgp-btn-detalle" data-bs-toggle="collapse"
                                        data-bs-target="#detCom{{ $c->id_comision }}" aria-expanded="false"
                                        aria-controls="detCom{{ $c->id_comision }}">
                                    <i class="bi bi-chevron-down"></i> Detalle
                                </button>
                                <a class="btn btn-sm btn-outline-neutro"
                                   href="{{ route('seguridad.comision_form', ['id' => $c->id_comision]) }}"
                                   title="Editar esta comisión"><i class="bi bi-pencil"></i></a>
                                {{-- Se da de BAJA, no se borra: `fn_comision_servicio` toma
                                     la vigente a la fecha del servicio, así que borrarla
                                     cambiaría lo que dicen los informes de lo ya atendido. --}}
                                <form method="post" action="{{ route('seguridad.comision.baja') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="id_comision" value="{{ $c->id_comision }}">
                                    <button class="btn btn-sm btn-outline-neutro" title="Dar de baja esta comisión"
                                            data-confirmar="Se da de baja, no se borra: lo ya liquidado no cambia. ¿Seguimos?">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        {{-- Expandable detail row --}}
                        <tr class="sgp-fila-detalle">
                            <td colspan="4">
                                <div class="collapse" id="detCom{{ $c->id_comision }}">
                                    <div class="sgp-det-cuerpo">
                                        <div class="sgp-det-grid">
                                            <div>
                                                <dt>Sucursal</dt>
                                                <dd>{{ $c->donde }}</dd>
                                            </div>
                                            <div>
                                                <dt>Tipo</dt>
                                                <dd>{{ $c->tipo === 'PORCENTAJE' ? 'Porcentaje' : 'Monto fijo' }}</dd>
                                            </div>
                                            <div>
                                                <dt>Vigente desde</dt>
                                                <dd>{{ fecha($c->vigente_desde, 'd/m/Y') }}</dd>
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
                                    <i class="bi bi-percent"></i>
                                    <div class="t">Todavía no hay comisiones cargadas.</div>
                                    <div class="d">Sin comisión, la liquidación al personal sale en cero.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
