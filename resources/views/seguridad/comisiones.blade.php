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
                    <tr><th>Profesional</th><th>Servicio</th><th>Tipo</th>
                        <th class="text-end">Valor</th><th>Vigente desde</th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $c)
                        <tr>
                            <td>{{ $c->profesional }}</td>
                            <td class="text-muted-warm">{{ $c->servicio }}</td>
                            <td>{{ $c->tipo === 'PORCENTAJE' ? 'Porcentaje' : 'Monto fijo' }}</td>
                            <td class="text-end">
                                <strong>{{ $c->tipo === 'PORCENTAJE' ? cant($c->valor) . ' %' : money($c->valor) }}</strong>
                            </td>
                            <td>{{ fecha($c->vigente_desde, 'd/m/Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
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
