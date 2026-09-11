@extends('layout.app')

@section('titulo', 'Panel principal')

@section('vivo', 'panel')

{{-- **El CSS del panel vive en `app.css`, no acá.** Estaba en un `<style>`
     dentro de `@push('scripts')`, o sea al final del `<body>`: la página se
     pintaba una vez sin estos estilos y se reacomodaba después. Y de paso
     metía `[data-tema="oscuro"]` dentro del HTML del panel, que es lo que la
     prueba del tema busca para comprobar que en claro no queda rastro — con
     eso la cadena aparecía siempre y el guardia dejaba de medir.

     **La forma es la de la maqueta que dio el usuario (7.118.0)**: el saludo
     como título chico arriba a la izquierda, a la izquierda las próximas
     citas y debajo el resumen financiero —cajas abiertas e ingresos de hoy
     contra ayer—, y a la derecha los nueve módulos en tres columnas. Lo que
     había en la fila de abajo —«Citas hoy», «Falta stock»— se va: el primero
     ya lo dice la lista de al lado, y el faltante de stock pasa a la
     campanita, con los nombres y el enlace, que un número suelto no daba. --}}

@section('contenido')
    @php use App\Servicios\Navegacion; use App\Servicios\Permisos; @endphp

    {{-- **El saludo es un título, no un cartel.** Vivía en una caja propia,
         centrado y grande, y eso ocupaba un cuarto de la fila para decir
         «hola»; como título chico arriba a la izquierda —que es lo que es— el
         espacio queda para lo que sí hay que mirar. --}}
    <h1 class="sgp-saludo">Hola, {{ session('nombre') }}</h1>

    <div class="row g-3">
        {{-- IZQUIERDA: las citas, y debajo la plata --}}
        <div class="col-lg-8 d-flex flex-column gap-3">
            <div class="panel-box flex-grow-1 d-flex flex-column">
                {{-- El posesivo dice de quién son: quien no administra la
                     agenda ve LAS SUYAS, no las del salón, y el rótulo lo
                     tiene que decir. --}}
                <h2 class="panel-box-titulo">
                    <i class="bi bi-calendar-check"></i>
                    {{ $verTodo ? 'Próximas citas' : 'Mis próximas citas' }}
                </h2>

                <div class="panel-box-inner flex-grow-1">
                    @if ($atrasadas || $proximas)
                        @if ($atrasadas)
                            <div class="mb-2">
                                <div class="sgp-lista-cab">
                                    <strong class="txt-no"><i class="bi bi-clock-history"></i> Atrasadas
                                        <span class="badge-estado e-no">{{ $atrasadasTotal }}</span></strong>
                                    @if (Navegacion::existe('citas.agenda'))
                                        <a class="link-oro" style="font-size:.78rem" href="{{ Navegacion::url('citas.agenda') }}">ir a la agenda &rarr;</a>
                                    @endif
                                </div>
                                <ul class="list-unstyled mb-0 sgp-lista-citas">
                                    @foreach ($atrasadas as $c)
                                        @php $min = (int) round((strtotime(ahora_bd()) - strtotime($c->fecha_hora)) / 60); @endphp
                                        <li>
                                            <span class="text-truncate pe-2">
                                                <strong>{{ $c->cliente }}</strong>
                                                <span class="text-muted-warm"> · {{ $c->servicios }}</span>
                                            </span>
                                            <span class="txt-no fw-bold" style="white-space:nowrap">hace {{ $min < 60 ? $min . ' min' : intdiv($min, 60) . ' h' }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if ($proximas)
                            <div>
                                @if ($atrasadas)
                                    <div class="sgp-lista-cab"><strong>{{ $verTodo ? 'Por atender' : 'Mis próximas' }}</strong></div>
                                @endif
                                <ul class="list-unstyled mb-0 sgp-lista-citas">
                                    @foreach ($proximas as $c)
                                        {{-- El `id` es el gancho de la prueba que fija que una
                                             cita ATENDIDA deje de anunciarse como próxima: sin
                                             él no hay forma de decir «ésta y no otra». --}}
                                        <li id="citaProxima{{ (int) $c->id_cita }}">
                                            <span class="text-truncate pe-2">
                                                <strong>{{ $c->cliente }}</strong>
                                                <span class="text-muted-warm"> · {{ $c->servicios }}</span>
                                                @if ($verTodo && $c->profesional)
                                                    <span class="text-muted-warm d-none d-md-inline"> · {{ $c->profesional }}</span>
                                                @endif
                                            </span>
                                            <span class="text-end" style="white-space:nowrap">
                                                <span class="text-muted-warm" style="font-size:.78rem">{{ fecha($c->fecha_hora, 'd/m') }}</span>
                                                <strong>{{ fecha($c->fecha_hora, 'H:i') }}</strong>
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @else
                        <div class="sgp-vacio py-4">
                            <i class="bi bi-calendar-check"></i>
                            <div class="t">No hay citas pendientes.</div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- **El resumen financiero**, sólo a quien tiene la caja o los
                 cobros: si no tiene ninguna de las dos, la caja entera no se
                 dibuja —un bloque vacío titulado «financiero» promete algo
                 que a esa persona no le corresponde—. --}}
            @if ($verCaja || $m['ingresos_hoy'] !== null)
                <div class="panel-box">
                    <h2 class="panel-box-titulo"><i class="bi bi-graph-up-arrow"></i> Resumen financiero</h2>
                    <div class="row g-3">
                        @if ($verCaja)
                            {{-- **La clase `sgp-caja-barra` no es decorativa: es el
                                 gancho.** La prueba del panel recorta desde acá hasta
                                 `sgp-metrics` para comprobar que se listen TODAS las
                                 cajas abiertas del local y las mismas para todos —el
                                 defecto de la 7.115.1, donde cada administrador veía
                                 sólo la suya—. Un rediseño que la renombre deja la
                                 guardia mirando al vacío y no da ningún error: es el
                                 patrón que este proyecto persigue. --}}
                            <div class="col-md-6 sgp-caja-barra">
                                <div class="metric-card h-100">
                                    <div class="metric-lbl">Estado de cajas</div>
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <i class="bi bi-safe2 txt-oro" style="font-size:1.4rem"></i>
                                        {{-- **Cuántas hay abiertas, no sólo cuáles.** Con dos
                                             cajones el número es lo que dice de un vistazo si
                                             falta cerrar alguno; sin él hay que contar los
                                             renglones. --}}
                                        <strong style="font-size:1.05rem">
                                            @if ($cajas)
                                                {{ count($cajas) }} {{ count($cajas) === 1 ? 'caja abierta' : 'cajas abiertas' }}
                                            @else
                                                Ninguna caja abierta
                                            @endif
                                        </strong>
                                    </div>
                                    @if ($cajas)
                                        <ul class="list-unstyled mb-0 mt-2 sgp-lista-cajas">
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
                                                <li title="{{ $sgpTit }}">
                                                    <span class="text-truncate">
                                                        {{ $c->nombre }}
                                                        @if ($c->responsable)
                                                            <span class="text-muted-warm" style="font-size:.76rem">· {{ $c->responsable }}</span>
                                                        @endif
                                                    </span>
                                                    <strong>{{ money($c->saldo) }}</strong>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @elseif (Navegacion::url('facturacion.cajas'))
                                        <div class="text-muted-warm mt-1" style="font-size:.8rem">
                                            Sin caja abierta no se cobra.
                                            <a class="link-oro" href="{{ Navegacion::url('facturacion.cajas') }}">Abrir una &rarr;</a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- `sgp-metrics` marca dónde termina la caja: la prueba
                             recorta entre las dos clases. --}}
                        <div class="col-md-6 sgp-metrics">
                            @if ($m['ingresos_hoy'] !== null)
                                <div class="metric-card h-100">
                                    <div class="metric-lbl">Ingresos de hoy</div>
                                    <div class="metric-value">{{ money($m['ingresos_hoy']) }}</div>
                                    @php
                                        // **Contra ayer, con el signo a la vista.** Un número
                                        // solo no dice si el día viene bien o mal; el de ayer
                                        // es la vara que todo el mundo tiene en la cabeza.
                                        $sgpHoy = (float) $m['ingresos_hoy'];
                                        $sgpAyer = (float) ($m['ingresos_ayer'] ?? 0);
                                        $sgpPct = $sgpAyer > 0 ? (int) round(($sgpHoy - $sgpAyer) / $sgpAyer * 100) : null;
                                    @endphp
                                    <div class="metric-comparado">
                                        @if ($sgpPct === null)
                                            @if ($sgpHoy > 0)
                                                <span class="txt-ok"><i class="bi bi-arrow-up-right"></i> ayer no se cobró nada</span>
                                            @else
                                                <span class="text-muted-warm">sin cobros hoy ni ayer</span>
                                            @endif
                                        @elseif ($sgpPct > 0)
                                            <span class="txt-ok"><i class="bi bi-arrow-up-right"></i> {{ $sgpPct }} % vs ayer</span>
                                        @elseif ($sgpPct < 0)
                                            <span class="txt-no"><i class="bi bi-arrow-down-right"></i> {{ abs($sgpPct) }} % vs ayer</span>
                                        @else
                                            <span class="text-muted-warm"><i class="bi bi-dash"></i> igual que ayer</span>
                                        @endif
                                        <span class="text-muted-warm"> · ayer {{ money($sgpAyer) }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- DERECHA: los módulos, tres por fila --}}
        <div class="col-lg-4">
            <div class="panel-box h-100 d-flex flex-column">
                <div class="row g-3 justify-content-center align-content-start flex-grow-1">
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
@endsection
