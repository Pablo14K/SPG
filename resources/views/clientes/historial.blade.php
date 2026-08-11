@extends('layout.app')

@section('titulo', 'Historial del cliente')

@section('contenido')
    <div class="spg-page-head">
        <a class="spg-back" href="{{ route('clientes.lista') }}"><i class="bi bi-arrow-left"></i> Clientes</a>
        <h1 class="mt-1">{{ $c->nombre }} {{ $c->apellido }}</h1>
        <div class="sub">{{ $c->telefono ?: 'Sin teléfono' }} · {{ $c->email ?: 'Sin email' }}</div>
    </div>

    @if ($fid)
        <div class="spg-metrics">
            <div class="spg-metric">
                <div class="lbl">Nivel</div>
                <div class="val oro">{{ $fid->nivel ?: 'Bronce' }}</div>
            </div>
            <div class="spg-metric">
                <div class="lbl">Visitas</div>
                <div class="val">{{ (int) $fid->visitas }}</div>
            </div>
            <div class="spg-metric">
                <div class="lbl">Puntos</div>
                <div class="val">{{ (int) $fid->puntos }}</div>
            </div>
            <div class="spg-metric">
                <div class="lbl">Descuento</div>
                <div class="val" style="font-size:1rem">{{ $fid->descuento_del_nivel ?: '—' }}</div>
            </div>
        </div>
    @endif

    <div class="spg-panel mt-2">
        <h2 style="font-size:1rem;font-weight:500;margin-bottom:.8rem;">Historial de servicios</h2>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Fecha</th><th>Servicio</th><th>Profesional</th><th>Comprobante</th><th>Puntaje</th></tr>
                </thead>
                <tbody>
                    @forelse ($hist as $h)
                        <tr>
                            <td>{{ fecha($h->fecha_hora) }}</td>
                            <td>{{ $h->servicio }}</td>
                            <td>{{ $h->profesional }}</td>
                            <td class="text-muted-warm">{{ $h->nro_comprobante ?: '—' }}</td>
                            <td class="txt-oro">{{ $h->puntaje ? str_repeat('★', (int) $h->puntaje) : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted-warm py-4">Sin servicios registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
