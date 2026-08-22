@extends('layout.app')

@section('titulo', 'Panel principal')

@section('contenido')
    @php use App\Servicios\Navegacion; use App\Servicios\Permisos; @endphp

    <div class="spg-page-head">
        <h1>Panel principal</h1>
        <div class="sub">Hola, {{ session('nombre') }}. Elegí un módulo para entrar a sus submódulos.</div>
    </div>

    @if ($verCaja)
        <div class="spg-caja-barra">
            <div class="spg-caja-estado">
                <i class="bi bi-safe"></i>
                @if ($caja)
                    <span>Caja <strong class="txt-ok">abierta</strong> por {{ $caja->responsable }}
                        · desde {{ fecha($caja->fecha_apertura, 'd/m H:i') }}</span>
                    <span class="spg-caja-saldo">{{ money($caja->saldo) }}</span>
                @else
                    <span>La caja está <strong class="txt-no">cerrada</strong>.
                        Abrila para poder registrar cobros.</span>
                @endif
            </div>
            @if (Navegacion::existe('facturacion.caja'))
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-sm btn-outline-neutro" href="{{ Navegacion::url('facturacion.caja') }}">Ver caja</a>
                </div>
            @endif
        </div>
    @endif

    {{-- Cada número se dibuja sólo si el controlador lo calculó, y lo calcula
         sólo para quien tiene el módulo del que sale. Un `null` acá no es un
         cero: es «esto no es tuyo». --}}
    <div class="spg-metrics mt-3">
        <div class="spg-metric">
            <div class="lbl">{{ $verTodo ? 'Citas de hoy' : 'Mis citas de hoy' }}</div>
            <div class="val">{{ $m['citas_hoy'] }}</div>
        </div>
        @if ($m['clientes'] !== null)
            <div class="spg-metric">
                <div class="lbl">Clientes activos</div>
                <div class="val">{{ $m['clientes'] }}</div>
            </div>
        @endif
        @if ($m['bajo_stock'] !== null)
            <div class="spg-metric">
                <div class="lbl">Productos bajo stock</div>
                <div class="val">{{ $m['bajo_stock'] }}</div>
            </div>
        @endif
        @if ($m['ingresos_hoy'] !== null)
            <div class="spg-metric">
                <div class="lbl">Ingresos de hoy</div>
                <div class="val oro">{{ money($m['ingresos_hoy']) }}</div>
            </div>
        @endif
    </div>

    {{-- Las tarjetas son el segundo nivel de navegación: qué hay dentro de
         cada módulo. Solo se dibujan las que el rol puede abrir. --}}
    <div class="spg-cards">
        @foreach (config('navegacion.modulos') as $mod)
            @continue (! Permisos::puede($mod['mod']))
            @php $url = Navegacion::url($mod['ruta']); @endphp
            @if ($url)
                <a class="spg-card {{ ! empty($mod['dark']) ? 'dark' : '' }}" href="{{ $url }}">
                    <div class="ic"><i class="bi bi-{{ $mod['ic'] }}"></i></div>
                    <h3>{{ $mod['titulo'] }}</h3>
                    <p>{{ Navegacion::subDe($mod['mod'], $mod['sub']) }}</p>
                </a>
            @else
                {{-- Módulo todavía no migrado a Laravel: se muestra apagado en
                     lugar de esconderlo, así se ve el avance de la migración. --}}
                <div class="spg-card" style="opacity:.45;cursor:not-allowed" title="Todavía no migrado">
                    <div class="ic"><i class="bi bi-{{ $mod['ic'] }}"></i></div>
                    <h3>{{ $mod['titulo'] }}</h3>
                    <p>{{ Navegacion::subDe($mod['mod'], $mod['sub']) }}</p>
                </div>
            @endif
        @endforeach
    </div>

    {{-- **Los dos bloques van lado a lado y compactos.** Antes eran dos tablas
         completas apiladas —ocho filas y seis, cada una con su encabezado y su
         párrafo— y empujaban las tarjetas de módulo fuera de la pantalla. El
         panel contesta «¿a dónde voy?»: las tarjetas son lo principal y esto es
         el resumen de lo que conviene mirar antes de ir. Para el detalle está
         la agenda, que existe justamente para eso. --}}
    @if ($atrasadas || $proximas)
        <div class="row g-2 mt-2">
            {{-- Atrasados primero: es lo único del panel que pide una acción
                 AHORA. El sistema no decide que la clienta no vino —eso lo sabe
                 quien atiende—: las junta para que alguien las resuelva. --}}
            @if ($atrasadas)
                <div class="col-lg-6">
                    <div class="spg-panel h-100" style="border-left:4px solid var(--oro);">
                        <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-2">
                            <h2 style="font-size:.95rem;font-weight:500;margin:0;">
                                <i class="bi bi-clock-history txt-oro"></i>
                                {{ $verTodo ? 'Clientes atrasados' : 'Tus clientes atrasados' }}
                                <span class="badge-estado e-warn">{{ $atrasadasTotal }}</span>
                            </h2>
                            @if (Permisos::puede('citas.agenda') && Navegacion::existe('citas.agenda'))
                                <a class="link-oro" style="font-size:.8rem" href="{{ Navegacion::url('citas.agenda') }}">
                                    Resolver &rarr;</a>
                            @endif
                        </div>
                        <ul class="list-unstyled mb-0" style="font-size:.84rem">
                            @foreach ($atrasadas as $c)
                                @php $min = (int) round((strtotime(ahora_bd()) - strtotime($c->fecha_hora)) / 60); @endphp
                                <li id="citaAtrasada{{ $c->id_cita }}"
                                    class="d-flex justify-content-between gap-2 py-1"
                                    style="border-top:1px solid var(--gris-calido)">
                                    <span class="text-truncate">
                                        <strong>{{ $c->cliente }}</strong>
                                        <span class="text-muted-warm">· {{ $c->servicios ?: 'sin servicios' }}</span>
                                    </span>
                                    <span class="txt-no" style="white-space:nowrap">
                                        {{ $min < 60 ? $min . ' min' : intdiv($min, 60) . ' h' }}</span>
                                </li>
                            @endforeach
                            @if ($atrasadasTotal > count($atrasadas))
                                <li class="pt-1 text-muted-warm"
                                    style="border-top:1px solid var(--gris-calido);font-size:.8rem">
                                    y {{ $atrasadasTotal - count($atrasadas) }} más en la agenda</li>
                            @endif
                        </ul>
                    </div>
                </div>
            @endif

            @if ($proximas)
                <div class="col-lg-{{ $atrasadas ? 6 : 12 }}">
                    <div class="spg-panel h-100">
                        <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-2">
                            <h2 style="font-size:.95rem;font-weight:500;margin:0;">
                                {{ $verTodo ? 'Próximas citas' : 'Tus próximas citas' }}</h2>
                            @if (Permisos::puede('citas') && Navegacion::existe('citas.agenda'))
                                <a class="link-oro" style="font-size:.8rem" href="{{ Navegacion::url('citas.agenda') }}">
                                    Ver la agenda &rarr;</a>
                            @endif
                        </div>
                        <ul class="list-unstyled mb-0" style="font-size:.84rem">
                            @foreach ($proximas as $c)
                                {{-- El id deja que la prueba mire ESTA cita y no el
                                     nombre de la clienta, que puede repetirse. --}}
                                <li id="citaProxima{{ $c->id_cita }}"
                                    class="d-flex justify-content-between gap-2 py-1"
                                    style="border-top:1px solid var(--gris-calido)">
                                    <span class="text-truncate">
                                        <strong>{{ $c->cliente }}</strong>
                                        <span class="text-muted-warm">· {{ $c->servicios ?: 'sin servicios' }}</span>
                                        @if ($verTodo)
                                            <span class="text-muted-warm">· {{ $c->profesional }}</span>
                                        @endif
                                    </span>
                                    <span class="text-muted-warm" style="white-space:nowrap">{{ fecha($c->fecha_hora) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- **Lo que le falta cargar al salón.**

         Va ABAJO y no arriba a propósito: el panel existe para contestar «¿a
         dónde voy?», y las tarjetas de módulo son eso. Un bloque de avisos
         arriba las empuja fuera de la pantalla, que es el error que la 7.35.0
         ya corrigió con las dos tablas de citas.

         Sólo se dibuja si hay algo, y sólo trae lo que ESTA persona puede
         resolver: el filtro por permiso está en `Pendientes::mios()`. --}}
    @if ($pendientes)
        <div class="spg-panel mt-3">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <h2 style="font-size:.95rem;font-weight:500;margin:0;">
                    <i class="bi bi-sliders me-1"></i>Falta cargar
                    <span class="text-muted-warm">({{ count($pendientes) }})</span>
                </h2>
                <span class="text-muted-warm" style="font-size:.8rem">
                    El sistema funciona igual, pero decide con lo que hay cargado
                </span>
            </div>

            <ul class="list-unstyled mb-0">
                @foreach ($pendientes as $p)
                    @php
                        $url = $p['ruta'] ? Navegacion::url($p['ruta']) : null;
                        // Las clases van escritas enteras y no armadas con el
                        // nivel: `AndamiajeTest` comprueba que toda clase del
                        // CSS aparezca en algún marcado, y una interpolada no
                        // aparece — quedarían las tres como CSS sin uso.
                        [$cls, $rot] = match ($p['nivel']) {
                            'IMPIDE'   => ['spg-falta-impide', 'Impide'],
                            'CONFUNDE' => ['spg-falta-confunde', 'Confunde'],
                            default    => ['spg-falta-conviene', 'Conviene'],
                        };
                    @endphp
                    <li class="spg-falta">
                        <span class="spg-falta-nivel {{ $cls }}">{{ $rot }}</span>
                        <span class="spg-falta-txt">
                            {{ $p['que'] }}
                            <span class="d-block text-muted-warm spg-falta-donde">
                                @if ($url)
                                    <a href="{{ $url }}">{{ $p['donde'] }}</a>
                                @else
                                    {{ $p['donde'] }}
                                @endif
                            </span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
