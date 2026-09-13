@extends('layout.app')

@section('titulo', 'Arqueos')

@section('contenido')
@php use App\Servicios\Permisos; @endphp

{{-- **Es una tabla, no tarjetas.** Un salón acumula un arqueo por cajón y por
     día, así que a los seis meses son cientos: lo que hace falta es poder
     filtrar y paginar, no que cada uno ocupe más lugar.

     **Y son DOS arqueos, cada uno en su pestaña** (7.122.0, pedido del
     usuario): el del cajón —contar el efectivo al cerrar— y el de la cuenta
     bancaria —escribir cuánto dice el banco—. Las pestañas son enlaces de
     verdad, como en Reportes: cada una tiene su URL y anda sin `app.js`. --}}
<x-encabezado sub="Cómo cerró cada caja y qué dijo el banco en cada cuenta: lo que debería haber, lo que se contó y la diferencia."
    :accion="$de === 'cuentas'
        ? ['ruta' => 'facturacion.cuentas', 't' => 'Cuentas bancarias', 'ic' => 'bank']
        : ['ruta' => 'facturacion.cajas', 't' => 'Cajas', 'ic' => 'safe']" />

@if ($verCuentas)
    <nav class="sgp-tabs" aria-label="Qué arqueo">
        <a class="sgp-tab {{ $de === 'cajas' ? 'activo' : '' }}" href="{{ route('facturacion.arqueo') }}">
            <i class="bi bi-safe"></i> Cajas</a>
        <a class="sgp-tab {{ $de === 'cuentas' ? 'activo' : '' }}" href="{{ route('facturacion.arqueo', ['de' => 'cuentas']) }}">
            <i class="bi bi-bank"></i> Cuentas bancarias</a>
    </nav>
@endif

@if ($de === 'cajas')
    {{-- **Lo que se puede arquear hoy: las cajas abiertas del local.** El arqueo
         de la caja es su cierre, así que el botón abre el mismo modal que la
         tarjeta de Cajas. --}}
    <div class="sgp-panel mb-3">
        <h2 class="sgp-form-titulo mb-2"><i class="bi bi-calculator"></i> Por arquear hoy
            @if ($sucursalNombre)<span class="text-muted-warm" style="font-size:.82rem;font-weight:400">· {{ $sucursalNombre }}</span>@endif
        </h2>
        @if ($abiertas)
            <div class="table-responsive sgp-tabla-movil">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                        @foreach ($abiertas as $ab)
                            <tr>
                                <td class="sgp-movil-titulo" data-label="Caja"><strong>{{ $ab->caja_nombre }}</strong></td>
                                <td class="text-muted-warm" data-label="Abierta">
                                    Abierta el {{ fecha($ab->fecha_apertura, 'd/m') }} a las {{ fecha($ab->fecha_apertura, 'H:i') }}
                                    @if ($ab->responsable) · por {{ $ab->responsable }} @endif
                                </td>
                                <td class="text-end" data-label="Esperado">
                                    <span class="text-muted-warm" style="font-size:.8rem">esperado</span>
                                    <strong>{{ money($ab->saldo) }}</strong>
                                </td>
                                <td class="text-end sgp-movil-acciones">
                                    <button type="button" class="btn btn-sm btn-oro" data-bs-toggle="modal"
                                            data-bs-target="#modalArqueo{{ $ab->id_caja_fisica }}">
                                        <i class="bi bi-calculator"></i> Arqueo y cierre</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted-warm mb-0" style="font-size:.88rem">
                No hay ninguna caja abierta en este local: el arqueo del cajón se hace al cerrarla.
            </p>
        @endif
    </div>

    @foreach ($abiertas as $ab)
        @include('facturacion._arqueo_modal', [
            'abierta' => $ab, 'sufijo' => $ab->id_caja_fisica, 'titulo' => $ab->caja_nombre, 'volver' => 'arqueos',
        ])
    @endforeach

    <x-filtros :f="$f" />

    {{-- **Las cuatro cifras salen de LO FILTRADO, no del total.** --}}
    <div class="sgp-metrics sgp-metrics-compacto mb-3">
        <div class="sgp-metric">
            <div class="lbl">Cajas cerradas</div>
            <div class="val">{{ $cerradas }}</div>
        </div>
        <div class="sgp-metric">
            <div class="lbl">Cuadraron</div>
            <div class="val">{{ $cuadran }}</div>
        </div>
        <div class="sgp-metric">
            <div class="lbl">Sin conteo</div>
            <div class="val">{{ $sinConteo }}</div>
            <div class="sgp-metric-pie">cerradas sin contar el cajón</div>
        </div>
        <div class="sgp-metric">
            <div class="lbl">Diferencia acumulada</div>
            <div class="val {{ abs($difTotal) < 0.01 ? '' : ($difTotal < 0 ? 'txt-no' : 'txt-oro') }}">
                {{ money($difTotal) }}</div>
            <div class="sgp-metric-pie">de las que no cuadraron</div>
        </div>
    </div>

    <div class="sgp-panel">
        <div class="table-responsive sgp-tabla-movil">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        {{-- **Un cierre sin su apertura no se puede juzgar.** «Cerró
                             con Gs. 40.000 de diferencia» significa una cosa si la caja
                             estuvo abierta dos horas y otra si estuvo tres días. --}}
                        <th>Abierta</th><th>Cerrada</th><th>Caja</th>
                        {{-- **Quién abrió y quién cerró son DOS personas y dos
                             responsabilidades.** --}}
                        <th class="sgp-movil-oculto">Abrió</th><th class="sgp-movil-oculto">Cerró</th>
                        <th class="text-end">Esperado</th>
                        <th class="text-end sgp-movil-oculto">Contado</th>
                        <th class="text-end">Diferencia</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $c)
                        <tr>
                            <td class="text-muted-warm sgp-movil-titulo" style="white-space:nowrap" data-label="Abierta">
                                {{ $c->fecha_apertura ? fecha($c->fecha_apertura, 'd/m/Y H:i') : '—' }}</td>
                            <td style="white-space:nowrap" data-label="Cerrada">{{ fecha($c->fecha_cierre, 'd/m/Y H:i') }}</td>
                            <td data-label="Caja">{{ $c->caja_nombre }}</td>
                            <td class="text-muted-warm sgp-movil-oculto" data-label="Abrió">{{ $c->responsable ?? '—' }}</td>
                            <td class="text-muted-warm sgp-movil-oculto" data-label="Cerró">
                                {{-- Sin `arqueo_por` es una caja cerrada antes de que el
                                     arqueo existiera: se dice, en vez de repetir a quien
                                     abrió como si hubiera contado él. --}}
                                {{ $c->arqueo_por ?: '—' }}
                            </td>
                            <td class="text-end" data-label="Esperado">{{ money($c->saldo ?? 0) }}</td>
                            <td class="text-end sgp-movil-oculto" data-label="Contado">
                                {{-- «—» y no «Gs. 0» cuando no se contó: un cero ahí
                                     se lee como «cuadró». --}}
                                {{ $c->monto_contado === null ? '—' : money($c->monto_contado) }}
                            </td>
                            <td class="text-end" style="white-space:nowrap" data-label="Diferencia">
                                @if ($c->diferencia === null)
                                    <span class="text-muted-warm">sin conteo</span>
                                @elseif (abs((float) $c->diferencia) < 0.01)
                                    <span class="badge-estado e-ok">cuadra</span>
                                @elseif ((float) $c->diferencia > 0)
                                    <span class="badge-estado e-warn">+ {{ money($c->diferencia) }}</span>
                                @else
                                    <span class="badge-estado e-no">− {{ money(abs((float) $c->diferencia)) }}</span>
                                @endif
                            </td>
                            <td class="text-muted-warm" style="font-size:.84rem" data-label="Detalle">
                                @if ($c->diferencia !== null && abs((float) $c->diferencia) >= 0.01)
                                    <div>{{ $c->motivo_diferencia ?: 'sin motivo' }}</div>
                                @endif
                                {{ $c->observacion_cierre ?: '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="sgp-vacio">
                                    <i class="bi bi-clipboard-check"></i>
                                    <div class="t">No hay arqueos con esos filtros</div>
                                    <div class="d">El arqueo del cajón aparece cuando se cierra una caja.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-paginacion :pag="$pag" :f="$f" />

    <p class="text-muted-warm mt-3 mb-0" style="font-size:.82rem">
        <i class="bi bi-info-circle"></i>
        <strong>Lo que no está en el cajón no se cuenta.</strong> Lo cobrado por tarjeta o
        transferencia se registra igual, pero va a la cuenta del salón: su arqueo está en
        la pestaña de las cuentas bancarias.
    </p>
@else
    {{-- **Lo que se puede arquear hoy: las cuentas activas del local**, con lo
         que el sistema calcula que hay. El botón abre el mismo modal que la
         tarjeta de la cuenta. --}}
    <div class="sgp-panel mb-3">
        <h2 class="sgp-form-titulo mb-2"><i class="bi bi-calculator"></i> Cuentas del local
            @if ($sucursalNombre)<span class="text-muted-warm" style="font-size:.82rem;font-weight:400">· {{ $sucursalNombre }}</span>@endif
        </h2>
        @if ($cuentas)
            <div class="table-responsive sgp-tabla-movil">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                        @foreach ($cuentas as $ct)
                            <tr>
                                <td class="sgp-movil-titulo" data-label="Cuenta">
                                    <strong>{{ $ct->entidad }}</strong>
                                    <div class="text-muted-warm" style="font-size:.78rem">
                                        {{ $ct->medio }}@if ($ct->numero_cuenta) · {{ $ct->numero_cuenta }}@endif
                                    </div>
                                </td>
                                <td class="text-muted-warm" data-label="Último arqueo">
                                    @if ($ct->ultimo_arqueo_en)
                                        Último arqueo el {{ fecha($ct->ultimo_arqueo_en, 'd/m/Y') }}
                                    @else
                                        <span class="txt-no"><i class="bi bi-exclamation-triangle"></i> Nunca se arqueó</span>
                                    @endif
                                </td>
                                <td class="text-end" data-label="Saldo">
                                    @if ($ct->saldo !== null)
                                        <span class="text-muted-warm" style="font-size:.8rem">según el sistema</span>
                                        <strong>{{ money($ct->saldo) }}</strong>
                                    @else
                                        <span class="text-muted-warm">no se sabe</span>
                                    @endif
                                </td>
                                <td class="text-end sgp-movil-acciones">
                                    <button type="button" class="btn btn-sm {{ $ct->saldo === null ? 'btn-oro' : 'btn-outline-neutro' }}"
                                            data-bs-toggle="modal" data-bs-target="#modalArqueoCta{{ $ct->id_cuenta }}">
                                        <i class="bi bi-calculator"></i> {{ $ct->saldo === null ? 'Primer arqueo' : 'Hacer el arqueo' }}</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted-warm mb-0" style="font-size:.88rem">
                Este local no tiene cuentas bancarias activas.
                <a class="link-oro" href="{{ route('facturacion.cuentas') }}">Cargar una &rarr;</a>
            </p>
        @endif
    </div>

    @foreach ($cuentas as $ct)
        @include('facturacion._arqueo_cuenta_modal', ['c' => $ct, 'volver' => 'arqueos'])
    @endforeach

    <x-filtros :f="$f" :ocultos="['de' => 'cuentas']" />

    <div class="sgp-metrics sgp-metrics-compacto mb-3">
        <div class="sgp-metric">
            <div class="lbl">Arqueos</div>
            <div class="val">{{ $hechos }}</div>
        </div>
        <div class="sgp-metric">
            <div class="lbl">Cuadraron</div>
            <div class="val">{{ $cuadran }}</div>
        </div>
        <div class="sgp-metric">
            <div class="lbl">Primer arqueo</div>
            <div class="val">{{ $primeros }}</div>
            <div class="sgp-metric-pie">sin uno anterior contra qué comparar</div>
        </div>
        <div class="sgp-metric">
            <div class="lbl">Diferencia acumulada</div>
            <div class="val {{ abs($difTotal) < 0.01 ? '' : ($difTotal < 0 ? 'txt-no' : 'txt-oro') }}">
                {{ money($difTotal) }}</div>
            <div class="sgp-metric-pie">de los que no cuadraron</div>
        </div>
    </div>

    <div class="sgp-panel">
        <div class="table-responsive sgp-tabla-movil">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th><th>Cuenta</th><th class="sgp-movil-oculto">Lo hizo</th>
                        <th class="text-end">Esperado</th>
                        <th class="text-end">Dijo el banco</th>
                        <th class="text-end">Diferencia</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $a)
                        <tr>
                            <td class="sgp-movil-titulo" style="white-space:nowrap" data-label="Fecha">{{ fecha($a->fecha, 'd/m/Y H:i') }}</td>
                            <td data-label="Cuenta">
                                {{ $a->entidad }}
                                @if ($a->numero_cuenta)
                                    <div class="text-muted-warm" style="font-size:.78rem">{{ $a->numero_cuenta }}</div>
                                @endif
                            </td>
                            <td class="text-muted-warm sgp-movil-oculto" data-label="Lo hizo">
                                {{-- Sin persona es lo mudado desde el saldo declarado de
                                     antes de la 7.122.0: no se sabe quién lo cargó. --}}
                                {{ $a->quien ?: '—' }}
                            </td>
                            <td class="text-end" data-label="Esperado">
                                {{ $a->esperado === null ? '—' : money($a->esperado) }}</td>
                            <td class="text-end" data-label="Dijo el banco">{{ money($a->monto_contado) }}</td>
                            <td class="text-end" style="white-space:nowrap" data-label="Diferencia">
                                @if ($a->diferencia === null)
                                    <span class="text-muted-warm">primer arqueo</span>
                                @elseif (abs((float) $a->diferencia) < 0.01)
                                    <span class="badge-estado e-ok">cuadra</span>
                                @elseif ((float) $a->diferencia > 0)
                                    <span class="badge-estado e-warn">+ {{ money($a->diferencia) }}</span>
                                @else
                                    <span class="badge-estado e-no">− {{ money(abs((float) $a->diferencia)) }}</span>
                                @endif
                            </td>
                            <td class="text-muted-warm" style="font-size:.84rem" data-label="Detalle">
                                @if ($a->diferencia !== null && abs((float) $a->diferencia) >= 0.01)
                                    <div>{{ $a->motivo_diferencia ?: 'sin motivo' }}</div>
                                @endif
                                {{ $a->observacion ?: '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="sgp-vacio">
                                    <i class="bi bi-clipboard-check"></i>
                                    <div class="t">No hay arqueos de cuentas con esos filtros</div>
                                    <div class="d">El arqueo de una cuenta es escribir cuánto dice el banco.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-paginacion :pag="$pag" :f="$f" :ocultos="['de' => 'cuentas']" />

    <p class="text-muted-warm mt-3 mb-0" style="font-size:.82rem">
        <i class="bi bi-info-circle"></i>
        <strong>El esperado sale del arqueo anterior</strong> más lo que entró por transferencia
        y menos lo que salió desde entonces. Un depósito hecho por fuera del sistema no lo ve:
        por eso el primer arqueo no tiene esperado, y una diferencia puede ser plata que entró
        sin cargarse.
    </p>
@endif
@endsection
