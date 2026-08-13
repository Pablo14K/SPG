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
    @elseif (! collect($tipos)->contains(fn ($t) => (int) $t->id_tipo_comprobante === $tipoDefecto))
        {{-- El comprobante que el sistema quiere emitir por defecto es el Ticket,
             porque la clienta no siempre pide factura. Pero el Ticket necesita su
             propio timbrado, y si no está cargado la pantalla caía en Factura sin
             decir nada: cada atención salía como factura declarable, que es
             justo lo contrario de lo que el salón quiso configurar. --}}
        <div class="alert alert-warning">
            <strong>El comprobante por defecto no tiene timbrado vigente</strong>, así que abajo
            sólo aparecen los que sí lo tienen. Mientras siga así, todo se emite como
            {{ $tipos[0]->nombre }} — y la clienta no siempre pide factura.
            @if (\App\Servicios\Permisos::puede('facturacion.timbrados'))
                <a class="link-oro" href="{{ route('facturacion.timbrados') }}">Cargá el timbrado que falta</a>.
            @else
                Pedile a quien administra los timbrados que cargue el que falta.
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
                        @php $elegida = $sel_cita > 0 && (int) $c->id_cita === $sel_cita; @endphp
                        {{-- La que se eligió en la agenda va primero (lo ordena la
                             consulta) y marcada: si no, con cien citas en la lista
                             hay que buscar a la clienta a mano justo después de
                             haberla señalado en la pantalla anterior. --}}
                        <tr @class(['spg-fila-elegida' => $elegida])>
                            <td>
                                {{ fecha($c->fecha_hora) }}
                                @if ($elegida)
                                    {{-- Texto y no un badge: `.e-warn` es de contorno, y sobre
                                         el fondo teñido de la fila quedaba en 3,15:1 en el tema
                                         oscuro. Así hereda el color de la celda: 12,9:1. --}}
                                    <div style="font-size:.78rem"><i class="bi bi-arrow-return-right"></i> la que elegiste</div>
                                @endif
                            </td>
                            <td>{{ $c->cliente }}</td>
                            <td class="text-muted-warm">{{ $c->servicios ?: '—' }}</td>
                            <td class="text-end">{{ money($c->total) }}</td>
                            <td class="text-end">
                                @if ($tipos)
                                    <form method="post" action="{{ route('facturacion.emitir.guardar') }}"
                                          class="d-flex gap-1 justify-content-end">
                                        @csrf
                                        <input type="hidden" name="id_cita" value="{{ $c->id_cita }}">
                                        {{-- Ticket viene marcado porque en el mostrador la mayoría
                                             no pide factura: quien la pide lo dice, y ahí se cambia. --}}
                                        <select class="form-select form-select-sm" name="id_tipo_comprobante" style="width:auto">
                                            @foreach ($tipos as $t)
                                                <option value="{{ $t->id_tipo_comprobante }}"
                                                    @selected((int) $t->id_tipo_comprobante === $tipoDefecto)>
                                                    {{ $t->nombre }}@if ($sifen && \App\Servicios\Sifen::esElectronico((int) $t->id_tipo_comprobante)) (se declara)@endif
                                                </option>
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
