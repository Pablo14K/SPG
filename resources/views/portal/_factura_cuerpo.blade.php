{{--
    El cuerpo del comprobante de la clienta: lo comparten la pantalla y el PDF.

    **Es UN partial y no dos vistas a propósito.** Escritos por separado se
    desfasan, y ahí la clienta termina con dos documentos que dicen cosas
    distintas del mismo cobro — el error que este proyecto ya se hizo con el
    desglose de la seña y con el catálogo demo.

    `$papel` lo dibuja para Dompdf, que no lee `app.css` ni las variables CSS:
    ahí los colores van a mano. Lo que cambia es cómo se ve, no lo que dice.
--}}
@php
    $acreditada = (int) ($f->acreditada ?? 0) > 0;
    $der = $papel ? 'der' : 'text-end';
    $tenue = $papel ? 'tenue' : 'text-muted-warm';
@endphp

{{-- **Una factura acreditada no vale por lo que dice**, y callarlo dejaría a la
     clienta presentando como gasto algo que el salón ya le devolvió. Es el
     mismo sello que la lista del salón muestra desde la 7.23.0. --}}
@if ($acreditada)
    <div class="{{ $papel ? 'sello' : 'alert alert-danger' }}">
        Este comprobante tiene una nota de crédito emitida: fue total o parcialmente acreditado.
    </div>
@endif

<h1 style="{{ $papel ? '' : 'font-size:1.05rem;margin:0' }}">{{ $salon }}</h1>
<div class="{{ $tenue }}" style="{{ $papel ? '' : 'font-size:.85rem' }}">
    {{ $f->sucursal ? $f->sucursal . ' · ' : '' }}{{ $f->direccion ?: '' }}
    @if ($f->telefono) · {{ $f->telefono }} @endif
    @if ($f->ruc) · RUC {{ $f->ruc }} @endif
</div>

<div style="margin-top:10px">
    <strong>{{ $f->tipo }} {{ $f->nro }}</strong>
    <span class="{{ $tenue }}"> · {{ fecha($f->fecha_emision) }}</span>
</div>

<table class="{{ $papel ? '' : 'table table-sm align-middle mt-3' }}">
    <thead>
        <tr>
            <th>Detalle</th>
            <th class="{{ $der }}">Cant.</th>
            <th class="{{ $der }}">Precio</th>
            <th class="{{ $der }}">Importe</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lineas as $l)
            <tr>
                <td>{{ $l->que }}</td>
                <td class="{{ $der }}">{{ (int) $l->cantidad }}</td>
                {{-- **Un servicio canjeado va en cero y se dice.** Un cero pelado
                     se lee como un error de impresión; lo que pasó es que ya lo
                     pagó con sus puntos. --}}
                <td class="{{ $der }}">
                    {{ (float) $l->precio_unitario > 0 ? money($l->precio_unitario) : 'canjeado' }}
                </td>
                <td class="{{ $der }}">{{ money($l->importe) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div style="margin-top:12px" class="{{ $der }}">
    <div>Subtotal: <strong>{{ money($f->subtotal) }}</strong></div>
    {{-- El descuento se dice siempre que exista: un total menor que la suma de
         los renglones, sin explicación, se lee como un error de la pantalla. --}}
    @if ((float) $f->descuento > 0)
        <div>Descuento: <strong>− {{ money($f->descuento) }}</strong></div>
    @endif
    <div class="tot" style="{{ $papel ? '' : 'font-size:1.1rem;font-weight:600' }}">
        Total: {{ money($f->total) }}
    </div>
    @if ((float) $f->saldo > 0)
        <div class="{{ $papel ? '' : 'txt-no' }}" style="{{ $papel ? 'color:#993535' : '' }}">
            Saldo pendiente: <strong>{{ money($f->saldo) }}</strong>
        </div>
    @endif
</div>

{{-- Con qué se pagó. Es lo primero que se busca al abrir un comprobante viejo:
     si ya está pago y por dónde salió la plata. --}}
@if ($cobros)
    <div style="margin-top:14px">
        <strong style="font-size:{{ $papel ? '11px' : '.9rem' }}">Cómo se pagó</strong>
        <ul style="margin:4px 0 0;padding-left:18px">
            @foreach ($cobros as $c)
                <li>{{ $c->medio }} · {{ money($c->monto) }}
                    <span class="{{ $tenue }}">({{ fecha($c->fecha_cobro) }})</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
