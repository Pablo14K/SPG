@extends('layout.app')

@section('titulo', 'Movimientos de stock')

@section('contenido')
    <x-encabezado sub="El libro mayor del inventario: cada entrada y cada salida, con quién la registró. De acá sale el stock de cada producto." />

    @if ($prod)
        <div class="spg-metrics mb-3">
            <div class="spg-metric">
                <div class="lbl">{{ $prod->nombre }}</div>
                <div class="val">
                    {{ cant($prod->stock) }}
                    <span style="font-size:.8rem;font-weight:400">{{ $prod->unidad_medida }}</span>
                </div>
                @if (producto_fraccionado((array) $prod))
                    <div class="text-muted-warm" style="font-size:.75rem">
                        {{ cant(stock_a_consumo((array) $prod, (float) $prod->stock)) }} {{ $prod->unidad_consumo }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="spg-panel">
        <x-filtros :f="$f" />

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Fecha</th><th>Producto</th><th>Tipo</th>
                        <th class="text-end">Cantidad</th><th class="text-end">Precio</th>
                        <th>Referencia</th><th>Quién</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $m)
                        <tr>
                            <td style="white-space:nowrap">{{ fecha($m->fecha) }}</td>
                            <td>{{ $m->producto }}</td>
                            <td>
                                @if ($m->signo === 'E')
                                    <span class="badge-estado e-ok">{{ $m->tipo }}</span>
                                @else
                                    <span class="badge-estado e-no">{{ $m->tipo }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <strong>{{ $m->signo === 'E' ? '+' : '−' }} {{ cant($m->cantidad) }}</strong>
                                <span class="text-muted-warm">{{ $m->unidad_medida }}</span>
                                @if (producto_fraccionado((array) $m))
                                    <div class="text-muted-warm" style="font-size:.72rem">
                                        {{ cant(stock_a_consumo((array) $m, (float) $m->cantidad)) }} {{ $m->unidad_consumo }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-end">{{ $m->precio_unitario ? money($m->precio_unitario) : '—' }}</td>
                            <td class="text-muted-warm">
                                {{ $m->referencia ?: '—' }}
                                @if ($m->observaciones)
                                    <div style="font-size:.72rem">{{ $m->observaciones }}</div>
                                @endif
                            </td>
                            <td class="text-muted-warm">{{ $m->usuario }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="spg-vacio">
                                    <i class="bi bi-arrow-left-right"></i>
                                    <div class="t">{{ $f['activos'] ? 'Ningún movimiento con esos filtros.' : 'Todavía no hay movimientos de stock.' }}</div>
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
