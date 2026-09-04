@extends('layout.app')

@section('titulo', 'Comisiones')

@section('contenido')
    <x-encabezado
        sub="Lo que le toca a cada profesional por servicio. La comisión de cada atención la calcula <code>fn_comision_servicio</code> en la base, tomando la vigente a esa fecha."
        :accion="['ruta' => 'seguridad.comision_form', 't' => 'Nueva comisión', 'ic' => 'plus-lg']" />

    <div class="spg-panel">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Profesional</th><th>Sucursal</th><th>Servicio</th><th>Tipo</th>
                        <th class="text-end">Valor</th><th>Vigente desde</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $c)
                        <tr>
                            <td>{{ $c->profesional }}</td>
                            <td class="text-muted-warm">{{ $c->donde }}</td>
                            <td class="text-muted-warm">{{ $c->servicio }}</td>
                            <td>{{ $c->tipo === 'PORCENTAJE' ? 'Porcentaje' : 'Monto fijo' }}</td>
                            <td class="text-end">
                                <strong>{{ $c->tipo === 'PORCENTAJE' ? cant($c->valor) . ' %' : money($c->valor) }}</strong>
                            </td>
                            <td>{{ fecha($c->vigente_desde, 'd/m/Y') }}</td>
                            <td class="text-end text-nowrap">
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
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="spg-vacio">
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
