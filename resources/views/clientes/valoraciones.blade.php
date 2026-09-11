@extends('layout.app')

@section('titulo', 'Valoraciones')

@section('contenido')
    {{-- El promedio es el de lo filtrado: si se mira a una profesional, el
         número que interesa es el de ella, no el del salón entero. --}}
    <x-encabezado :sub="'Promedio' . ($f['activos'] ? ' de lo filtrado' : ' general') . ': <strong class=\'txt-oro\'>'
                        . ($prom ? e($prom) . ' ★' : 'sin datos') . '</strong>'" />

    <div class="sgp-panel">
        <x-filtros :f="$f" />

        <div class="table-responsive sgp-tabla-movil">
            <table class="table align-middle">
                <thead>
                    <tr><th>Fecha</th><th>Cliente</th><th>Puntaje</th><th class="text-end"></th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td class="sgp-movil-titulo" data-label="Fecha">{{ fecha($r->fecha) }}</td>
                            <td data-label="Cliente">{{ $r->cliente }}</td>
                            <td class="txt-oro" style="white-space:nowrap" data-label="Puntaje">
                                {{ str_repeat('★', (int) $r->puntaje) . str_repeat('☆', 5 - (int) $r->puntaje) }}
                            </td>
                            <td class="text-end" style="white-space:nowrap">
                                <button class="sgp-btn-detalle" data-bs-toggle="collapse"
                                        data-bs-target="#detVal{{ $loop->index }}" aria-expanded="false">
                                    <i class="bi bi-chevron-down"></i> Detalle
                                </button>
                            </td>
                        </tr>
                        <tr class="sgp-fila-detalle">
                            <td colspan="4">
                                <div class="collapse" id="detVal{{ $loop->index }}">
                                    <div class="sgp-det-cuerpo">
                                        <div class="sgp-det-grid">
                                            <div>
                                                <dt>Profesional</dt>
                                                <dd>{{ $r->profesional }}</dd>
                                            </div>
                                            <div>
                                                <dt>Comentario</dt>
                                                <dd>{{ $r->comentario ?: '—' }}</dd>
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
