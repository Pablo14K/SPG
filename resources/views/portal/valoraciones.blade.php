@extends('layout.app')

@section('titulo', 'Valoraciones')

@section('contenido')
    <div class="spg-page-head">
        <h1>Valoraciones<x-ayuda lado="bottom">Contanos cómo te fue en cada cita. Lo lee el salón para mejorar.</x-ayuda></h1>
    </div>

    @if ($pendientes)
        <div class="spg-panel mb-3">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-star"></i> Citas sin calificar</h2>
            @foreach ($pendientes as $c)
                <form method="post" action="{{ route('portal.calificar') }}"
                      class="py-2" style="border-bottom:1px solid var(--gris-calido)">
                    @csrf
                    <input type="hidden" name="id_cita" value="{{ $c->id_cita }}">
                    <div class="mb-2">
                        <strong>{{ fecha($c->fecha_hora) }}</strong>
                        <span class="text-muted-warm">· {{ $c->servicios ?: 'sin servicios' }}</span>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <select class="form-select form-select-sm" name="puntaje" style="width:auto" required>
                            <option value="">Puntaje…</option>
                            @foreach ([5 => '★★★★★', 4 => '★★★★☆', 3 => '★★★☆☆', 2 => '★★☆☆☆', 1 => '★☆☆☆☆'] as $v => $t)
                                <option value="{{ $v }}">{{ $t }}</option>
                            @endforeach
                        </select>
                        <input class="form-control form-control-sm" name="comentario" style="flex:1;min-width:180px"
                               placeholder="Comentario (opcional)" maxlength="300">
                        <button class="btn btn-sm btn-oro">Enviar</button>
                    </div>
                </form>
            @endforeach
        </div>
    @endif

    <div class="spg-panel">
        <h2 class="spg-form-titulo mb-2"><i class="bi bi-clock-history"></i> Lo que ya calificaste</h2>
        @forelse ($hechas as $h)
            <div class="py-2" style="border-bottom:1px solid var(--gris-calido)">
                <span class="txt-oro">{{ str_repeat('★', (int) $h->puntaje) . str_repeat('☆', 5 - (int) $h->puntaje) }}</span>
                <span class="text-muted-warm"> · cita del {{ fecha($h->fecha_hora, 'd/m/Y') }}</span>
                @if ($h->comentario)
                    <div class="text-muted-warm" style="font-size:.85rem">{{ $h->comentario }}</div>
                @endif
            </div>
        @empty
            <div class="spg-vacio">
                <i class="bi bi-star"></i>
                <div class="t">Todavía no calificaste ninguna cita.</div>
            </div>
        @endforelse
    </div>
@endsection
