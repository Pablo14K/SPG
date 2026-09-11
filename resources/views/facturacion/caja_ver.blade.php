@extends('layout.app')

@section('titulo', $cajon->nombre)

@section('contenido')
@php use App\Servicios\Permisos; @endphp

{{-- **Acá no se listan las otras cajas.** La lista sirve para elegir; esta
     pantalla, para trabajar con la elegida. Por eso es tan vacía: lo único que
     hay que poder hacer es ver cuánto hay, mirar sus movimientos y cerrarla. --}}
<div class="sgp-page-head">
    <a class="sgp-back" href="{{ route('facturacion.cajas') }}">
        <i class="bi bi-arrow-left"></i> Cajas</a>
    <h1 class="mt-1">
        <i class="bi bi-safe"></i> {{ $cajon->nombre }}
        @if ($abierta)
            <span class="badge-estado e-ok"><i class="bi bi-unlock"></i> Abierta</span>
        @else
            <span class="badge-estado e-muted"><i class="bi bi-lock"></i> Cerrada</span>
        @endif
    </h1>
    <div class="sub">
        {{-- La fecha se nombra: suelta, se lee como cualquier cosa menos la
             apertura, que es lo que es. --}}
        {{ $cajon->sucursal }}@if ($abierta) · abierta por {{ $abierta->responsable }}
            el {{ fecha($abierta->fecha_apertura, 'd/m/Y') }} a las
            {{ fecha($abierta->fecha_apertura, 'H:i') }}@endif
    </div>
</div>

@if ($abierta)
    <div class="sgp-panel mb-3">
        <div class="sgp-metrics sgp-metrics-compacto">
            <div class="sgp-metric">
                <div class="lbl">Efectivo esperado</div>
                <div class="val oro">{{ money($saldo) }}</div>
                <div class="sgp-metric-pie">lo que tiene que estar en el cajón</div>
            </div>
            <div class="sgp-metric">
                <div class="lbl">Monto de apertura</div>
                <div class="val">{{ money($abierta->monto_inicial) }}</div>
            </div>
            <div class="sgp-metric">
                <div class="lbl">Cobrado en efectivo</div>
                <div class="val">{{ money($abierta->cobros_efectivo) }}</div>
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap mt-3">
            {{-- **Acá NO va otra vez «Movimientos de hoy».**

                 Estaba, y era el mismo botón dos veces: la tarjeta de la lista
                 ya abre ese modal, y desde ahí se entra a esta pantalla — así
                 que el botón aparecía en las dos, con el mismo texto y el mismo
                 contenido. Lo que falta desde acá es lo otro: **el arqueo de
                 esta caja**, que es a lo que el botón de la lista dice llevar.

                 Los movimientos siguen a un clic: el enlace de abajo lleva a la
                 historia entera, ya filtrada por este cajón. --}}
            <button class="btn btn-oro" data-bs-toggle="modal" data-bs-target="#modalArqueo">
                <i class="bi bi-lock"></i> Cerrar caja</button>
            <a class="btn btn-outline-neutro"
               href="{{ route('facturacion.arqueo', ['caja' => $cajon->id_caja_fisica]) }}">
                <i class="bi bi-clipboard-check"></i> Arqueos de esta caja</a>
            @if (Permisos::puede('facturacion.movimientos'))
                <a class="btn btn-outline-neutro"
                   href="{{ route('facturacion.movimientos', ['caja' => $cajon->id_caja_fisica]) }}">
                    <i class="bi bi-clock-history"></i> Movimientos de esta caja</a>
            @endif
        </div>
    </div>


    {{-- El mismo modal que abre la tarjeta de Cajas: escrito dos veces, el
         desglose de un lado se desfasa del otro. --}}
    @include('facturacion._arqueo_modal', ['abierta' => $abierta, 'sufijo' => '', 'titulo' => $cajon->nombre])
@else
    {{-- **Abrir es la única acción posible acá**, así que va sola y sin ruido:
         sin caja abierta no se cobra, no se factura y no se paga. --}}
    <div class="sgp-panel">
        <h2 class="sgp-form-titulo mb-2"><i class="bi bi-unlock"></i> Abrir esta caja<x-ayuda>El monto inicial es el efectivo con el que arranca el cajón. Al cerrar se cuenta lo que hay y el sistema dice si cuadra.</x-ayuda></h2>

        <form method="post" action="{{ route('facturacion.caja.abrir') }}"
              class="d-flex gap-2 align-items-end flex-wrap">
            @csrf
            <input type="hidden" name="id_caja_fisica" value="{{ $cajon->id_caja_fisica }}">
            <div>
                <label class="form-label" for="monto_inicial">Monto inicial en efectivo</label><x-ayuda campo="monto_inicial" />
                <div class="input-group">
                    <span class="input-group-text">Gs.</span>
                    <input class="form-control input-miles" id="monto_inicial" name="monto_inicial"
                           inputmode="numeric" data-min="0" value="0" required>
                </div>
            </div>
            <div class="flex-grow-1" style="min-width:220px">
                <label class="form-label" for="obsApertura">
                    Observación <span class="text-muted-warm">(opcional)</span></label>
                <input class="form-control" id="obsApertura" name="observacion" maxlength="255"
                       placeholder="Con qué se abre, si hay algo que aclarar">
            </div>
            <button class="btn btn-oro"><i class="bi bi-unlock"></i> Abrir caja</button>
        </form>
    </div>
@endif
@endsection
