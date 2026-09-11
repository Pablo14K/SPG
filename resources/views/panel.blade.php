@extends('layout.app')

@section('titulo', 'Panel principal')

@section('vivo', 'panel')

{{-- **El CSS del panel vive en `app.css`, no acá.** Estaba en un `<style>`
     dentro de `@push('scripts')`, o sea al final del `<body>`: la página se
     pintaba una vez sin estos estilos y se reacomodaba después. Y de paso
     metía `[data-tema="oscuro"]` dentro del HTML del panel, que es lo que la
     prueba del tema busca para comprobar que en claro no queda rastro — con
     eso la cadena aparecía siempre y el guardia dejaba de medir.

     Las reglas son las mismas, movidas tal cual: no cambia un píxel. --}}


@section('contenido')
    @php use App\Servicios\Navegacion; use App\Servicios\Permisos; @endphp

    <div class="row g-2">
        {{-- COLUMNA IZQUIERDA: Saludo e Ingresos --}}
        <div class="col-lg-3 col-md-4 d-flex flex-column gap-2">
            {{-- Saludo --}}
            <div class="panel-box d-flex align-items-center justify-content-center text-center py-3">
                <h1 class="m-0" style="font-size: 1.05rem; font-weight: 500;">
                    Hola, {{ session('nombre') }}
                </h1>
            </div>

            {{-- Solo Ingresos --}}
            <div class="panel-box flex-grow-1 d-flex flex-column justify-content-center text-center">
                @if ($m['ingresos_hoy'] !== null)
                    <div class="text-muted-warm mb-1" style="font-size: .85rem;">Ingresos de Hoy</div>
                    <div class="metric-value">{{ money($m['ingresos_hoy']) }}</div>
                @else
                    <div class="text-muted-warm" style="font-size: .85rem;">Ingresos no disponibles</div>
                @endif
            </div>
        </div>

        {{-- COLUMNA CENTRAL: Próximas Citas (y Atrasadas) --}}
        <div class="col-lg-5 col-md-8">
            <div class="panel-box d-flex flex-column h-100">
                <h2 class="text-center mb-2" style="font-size: 1.05rem; font-weight: 500;">
                    Próximas citas
                </h2>

                <div class="panel-box-inner overflow-auto" style="max-height: 250px;">
                    @if ($atrasadas || $proximas)
                        @if ($atrasadas)
                            <div class="mb-2">
                                <div class="d-flex justify-content-between align-items-center mb-1 pb-1" style="border-bottom:1px solid var(--gris-calido)">
                                    <strong class="txt-no" style="font-size:.85rem"><i class="bi bi-clock-history"></i> Atrasadas <span class="badge-estado e-warn">{{ $atrasadasTotal }}</span></strong>
                                </div>
                                <ul class="list-unstyled mb-0" style="font-size:.8rem">
                                    @foreach ($atrasadas as $c)
                                        @php $min = (int) round((strtotime(ahora_bd()) - strtotime($c->fecha_hora)) / 60); @endphp
                                        <li class="d-flex justify-content-between align-items-center py-1 border-bottom border-light">
                                            <span class="text-truncate pe-2">
                                                <strong>{{ $c->cliente }}</strong>
                                            </span>
                                            <span class="txt-no fw-bold" style="white-space:nowrap">{{ $min < 60 ? $min . ' min' : intdiv($min, 60) . ' h' }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if ($proximas)
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-1 pb-1" style="border-bottom:1px solid var(--gris-calido)">
                                    <strong style="font-size:.85rem; color: var(--carbon);">{{ $verTodo ? 'Por atender' : 'Mis próximas' }}</strong>
                                </div>
                                <ul class="list-unstyled mb-0" style="font-size:.8rem">
                                    @foreach ($proximas as $c)
                                        {{-- El `id` es el gancho de la prueba que fija que una
                                             cita ATENDIDA deje de anunciarse como próxima: sin
                                             él no hay forma de decir «ésta y no otra». --}}
                                        <li id="citaProxima{{ (int) $c->id_cita }}"
                                            class="d-flex justify-content-between align-items-center py-1" style="border-bottom:1px dashed var(--gris-calido)">
                                            <span class="text-truncate pe-2">
                                                <strong>{{ $c->cliente }}</strong>
                                            </span>
                                            <span class="text-muted-warm text-end" style="white-space:nowrap">
                                                <strong style="color: var(--carbon);">{{ fecha($c->fecha_hora, 'H:i') }}</strong>
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @else
                        <div class="h-100 d-flex flex-column align-items-center justify-content-center text-muted-warm text-center py-4">
                            <i class="bi bi-calendar-check fs-2 mb-2"></i>
                            <p class="m-0" style="font-size: .85rem;">No hay citas pendientes.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- COLUMNA DERECHA: Módulos (Grid) --}}
        <div class="col-lg-4 col-12">
            <div class="panel-box d-flex flex-column h-100">
                <div class="row g-3 justify-content-center align-content-start h-100">
                    @foreach (config('navegacion.modulos') as $mod)
                        @continue (! Permisos::puede($mod['mod']))
                        @php $url = Navegacion::url($mod['ruta']); @endphp
                        <div class="col-4">
                            @if ($url)
                                <a href="{{ $url }}" class="sgp-modulo-pill" title="{{ Navegacion::subDe($mod['mod'], $mod['sub']) }}">
                                    <i class="bi bi-{{ $mod['ic'] }}"></i>
                                    <span class="text-truncate w-100 px-1">{{ $mod['titulo'] }}</span>
                                </a>
                            @else
                                <div class="sgp-modulo-pill disabled" title="Todavía no migrado">
                                    <i class="bi bi-{{ $mod['ic'] }}"></i>
                                    <span class="text-truncate w-100 px-1">{{ $mod['titulo'] }}</span>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- FILA INFERIOR: Avisos y Resumen movido --}}
    <div class="row mt-2">
        <div class="col-12">
            <div class="panel-box">
                <h2 class="text-center mb-2" style="font-size: 1.05rem; font-weight: 500;">
                    Avisos y Estado General
                </h2>
                <div class="panel-box-inner p-2">
                    
                    {{-- Métricas reubicadas (Caja, Citas, Stock) --}}
                    <div class="row g-2 mb-3">
                        @if ($verCaja)
                            {{-- **La clase `sgp-caja-barra` no es decorativa: es el
                                 gancho.** La prueba del panel recorta desde acá hasta
                                 `sgp-metrics` para comprobar que se listen TODAS las
                                 cajas abiertas del local y las mismas para todos —el
                                 defecto de la 7.115.1, donde cada administrador veía
                                 sólo la suya—. Un rediseño que la renombre deja la
                                 guardia mirando al vacío y no da ningún error: es el
                                 patrón que este proyecto persigue. --}}
                            <div class="col-md-4 sgp-caja-barra">
                                <div class="metric-card mb-0 h-100 d-flex flex-column justify-content-center">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="bi bi-safe txt-oro"></i>
                                        {{-- **Cuántas hay abiertas, no sólo cuáles.** Con dos
                                             cajones el número es lo que dice de un vistazo si
                                             falta cerrar alguno; sin él hay que contar los
                                             renglones. --}}
                                        <strong style="font-size:.85rem">
                                            @if ($cajas)
                                                {{ count($cajas) }} {{ count($cajas) === 1 ? 'caja abierta' : 'cajas abiertas' }}
                                            @else
                                                Estado de Caja
                                            @endif
                                        </strong>
                                    </div>
                                    @if ($cajas)
                                        <div style="font-size:.75rem">
                                            @foreach ($cajas as $c)
                                                @php
                                                    // **El título se arma acá y no con `@if` dentro del
                                                    // atributo.** `@endif@if` pegados no los compila
                                                    // Blade —su patrón lleva `\B` delante de la arroba—
                                                    // así que el segundo deja de ser una directiva y el
                                                    // `@endif` queda huérfano: la pantalla entera
                                                    // revienta con 500. Es la trampa que este proyecto
                                                    // ya pagó con el correo del comprobante.
                                                    $sgpTit = $c->nombre
                                                        . ($c->responsable ? ' · abierta por ' . $c->responsable : '')
                                                        . ($c->fecha_apertura ? ' · el ' . fecha($c->fecha_apertura) : '');
                                                @endphp
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="text-truncate" style="max-width: 90px;"
                                                          title="{{ $sgpTit }}">{{ $c->nombre }}</span>
                                                    <strong>{{ money($c->saldo) }}</strong>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div style="font-size:.75rem" class="text-muted-warm">Ninguna abierta.</div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- `sgp-metrics` marca dónde termina la caja: la prueba
                             recorta entre las dos clases. --}}
                        <div class="col-md-4 sgp-metrics">
                            <div class="metric-card mb-0 h-100 d-flex flex-column justify-content-center">
                                <div class="d-flex justify-content-between align-items-center mb-1" style="font-size: .85rem;">
                                    <span class="text-muted-warm">{{ $verTodo ? 'Citas hoy' : 'Mis citas hoy' }}</span>
                                    <strong>{{ $m['citas_hoy'] }}</strong>
                                </div>
                                @if ($m['clientes'] !== null)
                                    <div class="d-flex justify-content-between align-items-center" style="font-size: .85rem;">
                                        <span class="text-muted-warm">Clientes activos</span>
                                        <strong>{{ $m['clientes'] }}</strong>
                                    </div>
                                @endif
                            </div>
                        </div>

                        @if ($m['bajo_stock'] !== null)
                            <div class="col-md-4">
                                <div class="metric-card mb-0 h-100 d-flex flex-column justify-content-center">
                                    <div class="d-flex justify-content-between align-items-center" style="font-size: .85rem;">
                                        <span class="text-muted-warm">Falta stock</span>
                                        <strong class="{{ $m['bajo_stock'] > 0 ? 'txt-no' : '' }}">{{ $m['bajo_stock'] }}</strong>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- **Lo que falta cargar se fue a la campanita** (pedido
                         del usuario, 7.117.0). Estaba acá como bloque y ahora
                         vive dentro de la bandeja de la barra, junto con lo
                         que está pasando ahora: son dos avisos, y lo que los
                         separa —si se resuelven una vez o todos los días— se
                         dice agrupándolos adentro, no con dos lugares
                         distintos. Y así se ven desde cualquier pantalla, no
                         sólo desde el inicio. --}}
                </div>
            </div>
        </div>
    </div>

@endsection
