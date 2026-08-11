@extends('layout.app')

@section('titulo', 'Emitir factura')

@section('contenido')
    <x-encabezado sub="Citas atendidas que todavía no tienen comprobante. El detalle sale de los servicios de la cita, y el descuento lo aplica la base: el del nivel del cliente o la mejor promoción vigente, el que más le convenga." />

    @if (! $tipos)
        <div class="alert alert-danger">
            <strong>No hay ningún timbrado vigente.</strong> Sin timbrado no se puede numerar un
            comprobante.
            @if (\App\Servicios\Permisos::puede('facturacion.timbrados'))
                <a class="link-oro" href="{{ route('facturacion.timbrados') }}">Cargá uno acá</a>.
            @else
                Pedile a quien administra los timbrados que cargue uno.
            @endif
        </div>
    @endif

    <div class="spg-panel">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Fecha</th><th>Cliente</th><th>Servicios</th><th class="text-end">Total</th><th class="text-end">Facturar</th></tr>
                </thead>
                <tbody>
                    @forelse ($citas as $c)
                        <tr>
                            <td>{{ fecha($c->fecha_hora) }}</td>
                            <td>{{ $c->cliente }}</td>
                            <td class="text-muted-warm">{{ $c->servicios ?: '—' }}</td>
                            <td class="text-end">{{ money($c->total) }}</td>
                            <td class="text-end">
                                @if ($tipos)
                                    <form method="post" action="{{ route('facturacion.emitir.guardar') }}"
                                          class="d-flex gap-1 justify-content-end">
                                        @csrf
                                        <input type="hidden" name="id_cita" value="{{ $c->id_cita }}">
                                        <select class="form-select form-select-sm" name="id_tipo_comprobante" style="width:auto">
                                            @foreach ($tipos as $t)
                                                <option value="{{ $t->id_tipo_comprobante }}">{{ $t->nombre }}</option>
                                            @endforeach
                                        </select>
                                        <select class="form-select form-select-sm" name="id_condicion_venta" style="width:auto">
                                            @foreach ($condiciones as $cv)
                                                <option value="{{ $cv->id_condicion_venta }}">{{ $cv->nombre }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-sm btn-oro"
                                                data-confirmar="Se va a emitir el comprobante de {{ $c->cliente }} por {{ money($c->total) }} (antes de descuentos). La numeración de la SET no se puede reusar. ¿Confirmás?">
                                            <i class="bi bi-receipt-cutoff"></i> Emitir
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="spg-vacio">
                                    <i class="bi bi-receipt"></i>
                                    <div class="t">No hay citas atendidas pendientes de facturar.</div>
                                    <div class="d">Registrá primero la atención de una cita.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
