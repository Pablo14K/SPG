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
                        <th class="text-end">Saldo</th><th>Estado</th><th class="text-end">Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $c)
                        <tr>
                            <td>{{ fecha($c->fecha, 'd/m/Y') }}</td>
                            <td>{{ $c->proveedor }}</td>
                            {{-- **Sin número se dice y se puede cargar de una.** El papel
                                 del proveedor no siempre llega con la mercadería, así que
                                 la compra entra sin él y después hay que anotarlo. Con un
                                 guión y nada más, la única forma de saber cuáles faltan era
                                 abrirlas una por una. --}}
                            <td>
                                @if ($c->nro_factura_proveedor)
                                    <span class="text-muted-warm">{{ $c->nro_factura_proveedor }}</span>
                                @else
                                    <button type="button" class="btn btn-sm btn-rapido"
                                            data-bs-toggle="modal" data-bs-target="#facCompra"
                                            data-id="{{ $c->id_compra }}"
                                            data-prov="{{ $c->proveedor }}"
                                            data-fecha="{{ fecha($c->fecha, 'd/m/Y') }}"
                                            data-total="{{ money($c->total) }}">
                                        <i class="bi bi-paperclip"></i> Cargar factura</button>
                                @endif
                            </td>
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
                                {{-- **El botón dice qué hay adentro.** Un ojito con
                                     «Ver el detalle» no deja adivinar que ahí también se
                                     anota la factura del proveedor. --}}
                                <a class="btn btn-sm btn-outline-neutro"
                                   title="Renglones, vencimiento, cuotas y la factura del proveedor"
                                   href="{{ route('inventario.compra_ver', ['id' => $c->id_compra]) }}">
                                    <i class="bi bi-eye"></i> Detalle</a>
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

    {{-- **Cargar la factura sin entrar a la compra.**

         El número se anotaba sólo desde el detalle, así que con veinte compras
         sin papel había que abrir veinte pantallas. El modal trae **proveedor,
         fecha y total**, que es lo que hace falta para saber cuál de todas es
         la que se tiene en la mano — el número solo no lo dice, justamente
         porque todavía no está cargado.

         Escribe en la misma ruta que el detalle, así que las dos no se pueden
         desfasar. --}}
    <div class="modal fade" id="facCompra" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('inventario.compra.factura') }}" class="modal-content">
                @csrf
                <input type="hidden" name="id_compra" id="facCompraId">
                <input type="hidden" name="desde" value="lista">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-paperclip"></i> Factura del proveedor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="spg-suma-at mb-3">
                        <div class="spg-suma-fila"><span>Proveedor</span><strong id="facCompraProv">—</strong></div>
                        <div class="spg-suma-fila"><span>Fecha de la compra</span><strong id="facCompraFecha">—</strong></div>
                        <div class="spg-suma-fila spg-suma-total"><span>Total</span>
                            <strong class="val oro" id="facCompraTotal">—</strong></div>
                    </div>
                    <label class="form-label" for="facCompraNro">Número de la factura</label><x-ayuda>Como viene impreso en el papel del proveedor.</x-ayuda>
                    <input class="form-control" id="facCompraNro" name="nro_factura_proveedor"
                           placeholder="001-001-0000001" maxlength="20" required autocomplete="off">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-oro"><i class="bi bi-check2"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
// El modal se llena con lo que trae el botón de la fila: así hay uno solo en
// la pantalla en vez de uno por compra.
document.getElementById('facCompra')?.addEventListener('show.bs.modal', function (ev) {
    var b = ev.relatedTarget; if (!b) return;
    document.getElementById('facCompraId').value = b.dataset.id || '';
    document.getElementById('facCompraProv').textContent = b.dataset.prov || '—';
    document.getElementById('facCompraFecha').textContent = b.dataset.fecha || '—';
    document.getElementById('facCompraTotal').textContent = b.dataset.total || '—';
    document.getElementById('facCompraNro').value = '';
});
</script>
@endpush
