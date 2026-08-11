@extends('layout.app')

@section('titulo', 'Promociones')

@section('contenido')
    <div class="spg-page-head">
        <h1>Promociones</h1>
        <div class="sub">Se aplica <strong>una sola</strong> por comprobante: la que más te convenga entre tu nivel y las promos vigentes.</div>
    </div>

    @if ($fid)
        <div class="spg-metrics mb-3">
            <div class="spg-metric">
                <div class="lbl">Tu nivel</div>
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
                <div class="lbl">Tu descuento</div>
                <div class="val" style="font-size:1rem">{{ $fid->descuento_del_nivel ?: '—' }}</div>
            </div>
        </div>
    @endif

    <div class="spg-panel">
        <h2 class="spg-form-titulo mb-2"><i class="bi bi-percent"></i> Promociones vigentes</h2>
        @forelse ($promos as $p)
            <div class="d-flex justify-content-between align-items-center py-2"
                 style="border-bottom:1px solid var(--gris-calido)">
                <div>
                    <strong>{{ $p->nombre }}</strong>
                    @if ($p->descripcion)
                        <div class="text-muted-warm" style="font-size:.82rem">{{ $p->descripcion }}</div>
                    @endif
                    @if ($p->fecha_fin)
                        <div class="text-muted-warm" style="font-size:.76rem">
                            hasta el {{ fecha($p->fecha_fin, 'd/m/Y') }}</div>
                    @endif
                </div>
                <span class="txt-oro" style="font-size:1.05rem;font-weight:600">
                    {{ $p->tipo === 'PORCENTAJE' ? cant($p->valor) . ' %' : money($p->valor) }}
                </span>
            </div>
        @empty
            <div class="spg-vacio">
                <i class="bi bi-percent"></i>
                <div class="t">Por ahora no hay promociones vigentes.</div>
                <div class="d">Tu descuento por nivel sigue aplicándose igual.</div>
            </div>
        @endforelse
    </div>
@endsection
