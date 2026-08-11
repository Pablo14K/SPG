@extends('layout.app')

@section('titulo', 'Valoraciones')

@section('contenido')
    {{-- El promedio es el de lo filtrado: si se mira a una profesional, el
         número que interesa es el de ella, no el del salón entero. --}}
    <x-encabezado :sub="'Promedio' . ($f['activos'] ? ' de lo filtrado' : ' general') . ': <strong class=\'txt-oro\'>'
                        . ($prom ? e($prom) . ' ★' : 'sin datos') . '</strong>'" />

    <div class="spg-panel">
        <x-filtros :f="$f" />

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr><th>Fecha</th><th>Cliente</th><th>Profesional</th><th>Puntaje</th><th>Comentario</th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td>{{ fecha($r->fecha) }}</td>
                            <td>{{ $r->cliente }}</td>
                            <td>{{ $r->profesional }}</td>
                            <td class="txt-oro" style="white-space:nowrap">
                                {{ str_repeat('★', (int) $r->puntaje) . str_repeat('☆', 5 - (int) $r->puntaje) }}
                            </td>
                            <td class="text-muted-warm">{{ $r->comentario ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="spg-vacio">
                                    <i class="bi bi-star"></i>
                                    <div class="t">
                                        {{ $f['activos'] ? 'Ninguna valoración con esos filtros.' : 'Todavía no hay valoraciones.' }}
                                    </div>
                                    <div class="d">El cliente las carga desde el portal, después de una cita atendida.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-paginacion :pag="$pag" :f="$f" />
    </div>
@endsection
