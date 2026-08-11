@extends('layout.app')

@section('titulo', 'Compras')

@section('contenido')
    <x-encabezado
        sub="Mercadería que entró al depósito. Al confirmarse, la base genera los movimientos de stock y actualiza el precio de costo."
        :accion="['ruta' => 'inventario.compra_form', 't' => 'Nueva compra', 'ic' => 'bag-plus']" />

    <div class="spg-panel">
        <x-filtros :f="$f" />

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Fecha</th><th>Proveedor</th><th>Nº factura</th>
                        <th class="text-end">Ítems</th><th class="text-end">Total</th>
                        <th class="text-end">Saldo</th><th>Estado</th><th class="text-end">Ver</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $c)
                        <tr>
                            <td>{{ fecha($c->fecha, 'd/m/Y') }}</td>
                            <td>{{ $c->proveedor }}</td>
                            <td class="text-muted-warm">{{ $c->nro_factura_proveedor ?: '—' }}</td>
                            <td class="text-end">{{ (int) $c->items }}</td>
                            <td class="text-end">{{ money($c->total) }}</td>
                            <td class="text-end">
                                @if ((float) $c->saldo > 0.01)
                                    <strong class="txt-no">{{ money($c->saldo) }}</strong>
                                @else
                                    <span class="txt-ok">pagada</span>
                                @endif
                            </td>
                            <td>{!! estado_badge($c->estado) !!}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-neutro" title="Ver el detalle"
                                   href="{{ route('inventario.compra_ver', ['id' => $c->id_compra]) }}">
                                    <i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="spg-vacio">
                                    <i class="bi bi-bag"></i>
                                    <div class="t">{{ $f['activos'] ? 'Ninguna compra coincide con esos filtros.' : 'Todavía no hay compras registradas.' }}</div>
                                    <div class="d">Registrá una para que entre la mercadería al stock.</div>
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
