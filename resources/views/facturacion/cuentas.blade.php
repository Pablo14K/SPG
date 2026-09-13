@extends('layout.app')

@section('titulo', 'Cuenta bancaria')

@section('contenido')
@php use App\Servicios\Permisos; @endphp

{{-- **Una tarjeta por cuenta, como Cajas.** La cuenta bancaria es una caja
     dedicada al banco (7.121.0, pedido del usuario): lo que entra por
     transferencia se le suma —las señas incluidas—, lo que sale por
     transferencia se le resta, y declarar cuánto dice el banco es su arqueo.
     La caja de siempre queda para el efectivo.

     Era «Configuración → Datos de pago», que sólo decía a dónde le transfiere
     la clienta. Eso sigue acá —el botón «Usar para señas» dice cuál se le
     muestra—, y la pantalla de Configuración se retiró. --}}
<x-encabezado sub="Las cuentas del salón: cuánto hay en cada una, qué pasó hoy, y cuál se le muestra a la clienta para la seña." />

<div class="sgp-panel mb-3">
    <p class="text-muted-warm mb-0" style="font-size:.88rem">
        <i class="bi bi-info-circle"></i>
        <strong>Acá no se cobra ni se paga nada</strong>: la plata entra con los cobros por
        transferencia y sale con los pagos y los movimientos, cada uno desde su pantalla.
        Lo que se hace acá es cargar las cuentas, <strong>hacer el arqueo</strong> —escribir
        cuánto dice el banco— y elegir cuál ve la clienta al registrar su seña. No hay pasarela de
        pagos: la clienta transfiere por su cuenta y sube el comprobante.
    </p>
</div>

<div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
    @if (count($sucursales) > 1)
        <form method="get" class="d-flex gap-2 align-items-end flex-wrap">
            <div>
                <label class="form-label" for="suc">Sucursal</label>
                <select class="form-select form-select-sm" id="suc" name="sucursal"
                        onchange="this.form.submit()">
                    @foreach ($sucursales as $s)
                        <option value="{{ $s->id_sucursal }}"
                            @selected((int) $sucursal === (int) $s->id_sucursal)>{{ $s->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <noscript><button class="btn btn-sm btn-oro">Ver</button></noscript>
        </form>
    @else
        <div></div>
    @endif
    <button type="button" class="btn btn-oro" data-bs-toggle="modal" data-bs-target="#modalCuentaNueva">
        <i class="bi bi-plus-circle"></i> Nueva cuenta
    </button>
</div>

<div class="row g-3">
    @forelse ($cuentas as $c)
        @php
            $delDia = $movs[(int) $c->id_cuenta] ?? [];
            $entro = 0;
            $salio = 0;
            foreach ($delDia as $m) {
                if (! $m->activo) {
                    continue;
                }
                if ((int) $m->signo > 0) {
                    $entro += (float) $m->monto;
                } else {
                    $salio += (float) $m->monto;
                }
            }
        @endphp
        <div class="col-12 col-md-6 col-xl-4">
            <div class="sgp-panel h-100 d-flex flex-column {{ $c->activo ? '' : 'opacity-75' }}">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <h2 class="sgp-form-titulo mb-0">
                            <i class="bi bi-{{ $c->tipo === 'OTRO' ? 'phone' : 'bank' }}"></i> {{ $c->entidad }}</h2>
                        <div class="text-muted-warm" style="font-size:.82rem">
                            {{ $c->medio }}@if ($c->tipo_cuenta) · {{ $c->tipo_cuenta }}@endif
                        </div>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-1">
                        @unless ($c->activo)
                            <span class="badge-estado e-muted"><i class="bi bi-x-circle"></i> Dada de baja</span>
                        @endunless
                        @if ($c->para_senas)
                            <span class="badge-estado e-proc" title="La clienta ve esta cuenta al registrar su seña">
                                <i class="bi bi-cash-coin"></i> Para señas</span>
                        @endif
                    </div>
                </div>

                {{-- Los datos para transferir, tal como los ve la clienta. --}}
                <div class="mt-2" style="font-size:.86rem">
                    @if ($c->alias)
                        <div><span class="text-muted-warm">Alias ({{ $tiposAlias[$c->alias_tipo] ?? 'alias' }}):</span>
                            <strong class="sgp-cuenta-nro">{{ $c->alias }}</strong></div>
                    @endif
                    <div><span class="text-muted-warm">Cuenta:</span> {{ $c->numero_cuenta ?: '—' }}</div>
                    <div><span class="text-muted-warm">A nombre de:</span> {{ $c->titular }}</div>
                </div>

                {{-- **El saldo, que sale del último arqueo.** Parte de lo que dijo el
                     banco en el último arqueo y suma lo que entró y resta lo que salió
                     desde entonces. **Sin arqueo NO es cero**: es «no se sabe», y la
                     campanita lo pide. El historial está en Arqueos (7.122.0). --}}
                <div class="mt-3">
                    <div class="text-muted-warm" style="font-size:.8rem">Saldo según el sistema</div>
                    @if ($c->saldo === null)
                        <div class="txt-no" style="font-size:.95rem">
                            <i class="bi bi-exclamation-triangle"></i> Sin arqueo todavía
                        </div>
                        <div class="text-muted-warm" style="font-size:.78rem">
                            Hasta el primer arqueo, el sistema no puede decir cuánto hay ni
                            avisar si un pago no alcanza.
                        </div>
                    @else
                        <div class="val oro" style="font-size:1.35rem">{{ money($c->saldo) }}</div>
                        <div class="text-muted-warm" style="font-size:.78rem">
                            Último arqueo: {{ money($c->ultimo_arqueo_monto) }} el
                            {{ fecha($c->ultimo_arqueo_en, 'd/m/Y') }} a las {{ fecha($c->ultimo_arqueo_en, 'H:i') }};
                            desde ahí se suma lo que entró y se resta lo que salió.
                        </div>
                    @endif
                </div>

                @if ($c->activo && Permisos::puede('facturacion.movimientos'))
                    <div class="text-muted-warm mt-2" style="font-size:.82rem">
                        @if ($delDia)
                            <i class="bi bi-list-ul"></i> {{ count($delDia) }} movimiento{{ count($delDia) === 1 ? '' : 's' }} hoy
                            · <span class="txt-ok">+ {{ money($entro) }}</span>
                            · <span class="txt-no">− {{ money($salio) }}</span>
                        @else
                            <i class="bi bi-list-ul"></i> Sin movimientos hoy
                        @endif
                    </div>
                @endif

                <div class="d-flex gap-2 flex-wrap mt-3 pt-3 border-top">
                    @if ($c->activo && Permisos::puede('facturacion.movimientos'))
                        <button type="button" class="btn btn-sm btn-outline-neutro"
                                data-bs-toggle="modal" data-bs-target="#modalMovsCta{{ $c->id_cuenta }}">
                            <i class="bi bi-list-ul"></i> Movimientos de hoy</button>
                    @endif
                    @if ($c->activo)
                        <button type="button" class="btn btn-sm {{ $c->saldo === null ? 'btn-oro' : 'btn-outline-neutro' }}"
                                data-bs-toggle="modal" data-bs-target="#modalArqueoCta{{ $c->id_cuenta }}">
                            <i class="bi bi-calculator"></i> {{ $c->saldo === null ? 'Primer arqueo' : 'Arqueo' }}</button>

                        {{-- **Usar para señas**: cuál ve la clienta. Es un interruptor
                             por cuenta, y puede haber varias marcadas —el banco y la
                             billetera— para que elija por dónde le queda cómodo. --}}
                        <form method="post" action="{{ route('facturacion.cuentas.senas') }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="id_cuenta" value="{{ $c->id_cuenta }}">
                            <button class="btn btn-sm btn-outline-neutro"
                                    title="{{ $c->para_senas ? 'Dejar de mostrársela a la clienta para la seña' : 'Mostrársela a la clienta para la seña' }}">
                                <i class="bi bi-{{ $c->para_senas ? 'toggle-on' : 'toggle-off' }}"></i>
                                {{ $c->para_senas ? 'Se usa para señas' : 'Usar para señas' }}</button>
                        </form>
                    @endif

                    @if (count($cuentas) > 1)
                        @foreach (['arriba' => 'up', 'abajo' => 'down'] as $dir => $ic)
                            <form method="post" action="{{ route('facturacion.cuentas.orden') }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="id_cuenta" value="{{ $c->id_cuenta }}">
                                <input type="hidden" name="dir" value="{{ $dir }}">
                                <button class="btn btn-sm btn-outline-neutro" title="Mostrarla más {{ $dir }}">
                                    <i class="bi bi-arrow-{{ $ic }}"></i></button>
                            </form>
                        @endforeach
                    @endif

                    <button type="button" class="btn btn-sm btn-outline-neutro ms-auto"
                            data-bs-toggle="modal" data-bs-target="#modalCuentaEd{{ $c->id_cuenta }}"
                            title="Editar los datos"><i class="bi bi-pencil"></i></button>

                    {{-- **Se da de baja, no se borra.** Los cobros, los pagos y las señas
                         que pasaron por ella la nombran: si desaparece, no hay forma de
                         saber a dónde fue la plata. --}}
                    <form method="post" action="{{ route('facturacion.cuentas.estado') }}" class="d-inline">
                        @csrf
                        <input type="hidden" name="id_cuenta" value="{{ $c->id_cuenta }}">
                        <button class="btn btn-sm btn-outline-neutro"
                                title="{{ $c->activo ? 'Dar de baja: deja de ofrecerse para cobrar y pagar' : 'Volver a activar' }}"
                                data-confirmar="{{ $c->activo
                                    ? '«' . $c->entidad . '» deja de ofrecerse para cobrar y pagar. Su historial queda. ¿Seguimos?'
                                    : '«' . $c->entidad . '» vuelve a estar activa. ¿Seguimos?' }}">
                            <i class="bi bi-{{ $c->activo ? 'x-lg' : 'arrow-counterclockwise' }}"></i></button>
                    </form>
                </div>
            </div>
        </div>

        {{-- **Los modales van FUERA de la tarjeta y de cualquier tabla.** Uno
             dibujado dentro de un ancestro con `display:none` no se puede
             mostrar ni con Bootstrap haciendo su trabajo (7.87.4). --}}
        <div class="modal fade" id="modalCuentaEd{{ $c->id_cuenta }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5"><i class="bi bi-pencil"></i> Editar la cuenta</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        @include('facturacion._cuenta_form', ['uid' => 'e' . $c->id_cuenta, 'editar' => $c])
                    </div>
                </div>
            </div>
        </div>

        @if ($c->activo)
            @include('facturacion._arqueo_cuenta_modal', ['c' => $c, 'volver' => 'cuentas'])

            @if (Permisos::puede('facturacion.movimientos'))
                <div class="modal fade" id="modalMovsCta{{ $c->id_cuenta }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2 class="modal-title fs-5">
                                    <i class="bi bi-list-ul"></i> {{ $c->entidad }} · movimientos de hoy</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                @include('facturacion._movs_dia', ['movs' => $delDia, 'cajon' => 'la cuenta ' . $c->entidad])
                            </div>
                            <div class="modal-footer justify-content-between">
                                <a class="btn btn-sm btn-outline-neutro"
                                   href="{{ route('facturacion.movimientos', ['cuenta' => $c->id_cuenta]) }}">
                                    <i class="bi bi-clock-history"></i> Ver todos los movimientos</a>
                                <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    @empty
        <div class="col-12">
            <div class="sgp-panel">
                <div class="sgp-vacio">
                    <i class="bi bi-bank"></i>
                    <div class="t">Todavía no cargaste ninguna cuenta</div>
                    <div class="d">
                        Sin una cuenta, lo que se cobra por transferencia no se suma a ningún
                        lado y a la clienta que reserva con seña le decimos que se comunique
                        con el salón. Cargá una con «Nueva cuenta».
                    </div>
                </div>
            </div>
        </div>
    @endforelse
</div>

<div class="modal fade" id="modalCuentaNueva" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5"><i class="bi bi-plus-circle"></i> Cargar una cuenta</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                @include('facturacion._cuenta_form', ['uid' => 'n', 'editar' => null])
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
/* El rótulo del banco y el tipo de cuenta cambian con el medio: una billetera
   no tiene «cuenta corriente» y su número es un celular. Un formulario por
   modal, así que se recorre cada uno por su combo de medio. */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-medio-de]').forEach(function (medio) {
        var form = medio.closest('form');
        if (!form) return;
        var entidad = form.querySelector('[name="entidad"]');
        var entidadLabel = form.querySelector('[data-medio-label="entidad"]');
        var numeroLabel = form.querySelector('[data-medio-label="numero"]');
        var tipoCuenta = form.querySelector('[data-medio-campo="tipo-cuenta"]');

        function actualizar() {
            var op = medio.options[medio.selectedIndex];
            var billetera = op && op.dataset.tipo === 'OTRO';
            if (entidadLabel) entidadLabel.textContent = billetera ? 'Billetera o proveedor' : 'Banco';
            if (entidad) entidad.placeholder = billetera ? 'Tigo Money, Personal Pay…' : 'Itaú, Ueno…';
            if (numeroLabel) numeroLabel.textContent = billetera ? 'Número de celular o cuenta' : 'Número de cuenta';
            if (tipoCuenta) {
                tipoCuenta.classList.toggle('d-none', billetera);
                var sel = tipoCuenta.querySelector('select');
                if (sel && billetera) sel.value = '';
            }
        }
        medio.addEventListener('change', actualizar);
        actualizar();
    });

    /* Si el guardado rechazó, se vuelve a abrir el modal que se estaba
       llenando: la persona no tiene que buscar de nuevo cuál era. */
    var conError = document.querySelector('form[data-cuenta-form] [name="id_cuenta"]');
    @if (session('flash.tipo') === 'error' && old('entidad') !== null)
        var abrir = document.getElementById(@json(old('id_cuenta') ? 'modalCuentaEd' . (int) old('id_cuenta') : 'modalCuentaNueva'));
        if (abrir && window.bootstrap) bootstrap.Modal.getOrCreateInstance(abrir).show();
    @endif
});
</script>
@endpush
@endsection
