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

    <div class="spg-metrics mt-3">
        <div class="spg-metric">
            <div class="lbl">Citas de hoy</div>
            <div class="val">{{ $m['citas_hoy'] }}</div>
        </div>
        <div class="spg-metric">
            <div class="lbl">Clientes activos</div>
            <div class="val">{{ $m['clientes'] }}</div>
        </div>
        <div class="spg-metric">
            <div class="lbl">Productos bajo stock</div>
            <div class="val">{{ $m['bajo_stock'] }}</div>
        </div>
        <div class="spg-metric">
            <div class="lbl">Ingresos de hoy</div>
            <div class="val oro">{{ money($m['ingresos_hoy']) }}</div>
        </div>
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
                    <p>{{ $mod['sub'] }}</p>
                </a>
            @else
                {{-- Módulo todavía no migrado a Laravel: se muestra apagado en
                     lugar de esconderlo, así se ve el avance de la migración. --}}
                <div class="spg-card" style="opacity:.45;cursor:not-allowed" title="Todavía no migrado">
                    <div class="ic"><i class="bi bi-{{ $mod['ic'] }}"></i></div>
                    <h3>{{ $mod['titulo'] }}</h3>
                    <p>{{ $mod['sub'] }}</p>
                </div>
            @endif
        @endforeach
    </div>

    @if ($proximas)
        <div class="spg-panel mt-2">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <h2 style="font-size:1rem;font-weight:500;margin:0;">Próximas citas</h2>
                @if (Permisos::puede('citas') && Navegacion::existe('citas.agenda'))
                    <a class="link-oro" style="font-size:.85rem" href="{{ Navegacion::url('citas.agenda') }}">
                        Ver la agenda completa →</a>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th><th>Cliente</th><th>Profesional</th><th>Servicios</th><th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($proximas as $c)
                            <tr>
                                <td>{{ fecha($c->fecha_hora) }}</td>
                                <td>{{ $c->cliente }}</td>
                                <td>{{ $c->profesional }}</td>
                                <td class="text-muted-warm">{{ $c->servicios ?: '—' }}</td>
                                <td>{!! estado_badge($c->estado) !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
