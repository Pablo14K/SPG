{{--
    Envoltorio de todas las pantallas del sistema.

    Replica la identidad visual definida en app.css: neutros cálidos, oro
    champagne solo donde hay acción o jerarquía, y los cuatro niveles de
    navegación. Bootstrap y sus íconos van por CDN; la hoja propia se carga
    después para pisarle los azules y grises fríos que trae compilados.

    OJO con los nombres de las variables: en el sistema anterior el encabezado
    declaraba `$u` y le pisaba el `$u` que le pasaba el controlador — la
    pantalla de Nuevo usuario terminaba mostrando el nombre de quien estaba
    logueado en el campo Nombre. Blade tiene el mismo riesgo con @include, así
    que acá todo va con prefijo `spg`.
--}}
@php
    use App\Servicios\Navegacion;
    use App\Servicios\Permisos;

    $spgRuta     = Route::currentRouteName() ?? '';
    $spgModulo   = strtok($spgRuta, '.');
    $spgSesion   = session('uid') ? ['nombre' => session('nombre'), 'rol_nom' => session('rol_nom')] : null;
    $spgCliente  = (bool) session('es_cliente', false);
    // En qué local está trabajando. Se muestra SIEMPRE que haya una elegida,
    // aunque el salón tenga una sola: quien atiende tiene que poder contestar
    // «¿en qué sucursal estoy?» sin abrir nada, porque de eso dependen la
    // agenda que ve, la caja que cierra y el stock que descuenta. La clienta
    // no tiene ninguna — elige el local al agendar.
    $spgSucursal = $spgCliente ? '' : (string) session('sucursal_nom', '');
    $spgMenu     = $spgSesion && ! $spgCliente ? Navegacion::modulos() : [];
    $spgRapidos  = $spgSesion && ! $spgCliente ? Navegacion::accesosRapidos($spgRuta) : [];
    $spgPortal   = $spgCliente ? Navegacion::portal() : [];
    $spgContacto = Navegacion::contactos();
@endphp
<!DOCTYPE html>
{{-- El tema sale de la sesión y se dibuja acá arriba, antes que nada: si se
     aplicara con JavaScript, la pantalla parpadearía en claro un instante
     antes de oscurecerse. Las dos vistas de impresión NO lo llevan a
     propósito — el papel siempre va en claro. --}}
<html lang="es" @if (\App\Servicios\Sesion::tema() === 'oscuro') data-tema="oscuro" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', config('app.name')) · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ recurso('css/app.css') }}" rel="stylesheet">
    @stack('estilos')
</head>
<body>

<header class="spg-topbar">
    <a class="spg-brand" href="{{ Navegacion::url($spgCliente ? 'portal.index' : 'panel') ?? url('/') }}">
        <span class="spg-logo"><i class="bi bi-scissors"></i></span>
        <span class="spg-brand-txt">
            <span class="spg-brand-name">{{ config('app.name') }}</span>
            <span class="spg-brand-sub">Sistema de gestión</span>
        </span>
    </a>

    @if ($spgSesion)
        <div class="spg-user">
            @if (! $spgCliente && $spgMenu)
                {{-- Solo en pantallas chicas: en grande está la barra de módulos de abajo --}}
                <div class="dropdown d-lg-none">
                    <button class="spg-user-link spg-modulos-btn" type="button" data-bs-toggle="dropdown"
                            aria-expanded="false" title="Ir a un módulo">
                        <i class="bi bi-grid-3x3-gap-fill"></i> <span class="spg-user-nombre">Módulos</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end spg-dropdown">
                        @foreach ($spgMenu as $spgMod)
                            <li><a class="dropdown-item" href="{{ $spgMod['url'] }}">
                                <i class="bi bi-{{ $spgMod['ic'] }}"></i> {{ $spgMod['titulo'] }}</a></li>
                        @endforeach
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ Navegacion::url('panel') }}">
                            <i class="bi bi-house"></i> Panel principal</a></li>
                    </ul>
                </div>
            @endif

            <div class="dropdown">
                <button class="spg-user-link" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                        title="Mi cuenta">
                    <i class="bi bi-person-circle"></i>
                    <span class="spg-user-nombre">{{ $spgSesion['nombre'] }}</span>
                    <i class="bi bi-chevron-down" style="font-size:.65rem"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end spg-dropdown">
                    <li><span class="dropdown-item-text spg-drop-cabecera">
                        <strong>{{ $spgSesion['nombre'] }}</strong>
                        <span>{{ $spgSesion['rol_nom'] }}</span>
                        {{-- En pantalla chica la ficha de arriba no se dibuja,
                             así que acá es el único lugar donde se ve. --}}
                        @if ($spgSucursal)
                            <span class="txt-oro"><i class="bi bi-shop"></i> {{ $spgSucursal }}</span>
                        @endif
                    </span></li>
                    <li><hr class="dropdown-divider"></li>
                    @if (Navegacion::existe('cuenta.index'))
                        <li><a class="dropdown-item" href="{{ Navegacion::url('cuenta.index') }}">
                            <i class="bi bi-gear"></i> Mi cuenta</a></li>
                    @endif
                    @if ($spgCliente && Navegacion::existe('portal.preferencias'))
                        <li><a class="dropdown-item" href="{{ Navegacion::url('portal.preferencias') }}">
                            <i class="bi bi-bell"></i> Mis recordatorios</a></li>
                    @endif
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        {{-- Salir es un POST: un GET lo dispararía cualquier enlace o precarga --}}
                        <form method="post" action="{{ route('salir') }}" class="d-inline w-100">
                            @csrf
                            <button type="submit" class="dropdown-item" data-confirmar="¿Cerrar la sesión?">
                                <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
            {{-- La sucursal va ANTES del rol y en relleno, no en contorno: es
                 lo que cambia entre una sesión y otra, y lo que hay que poder
                 leer de un vistazo antes de cobrar o descontar stock. El rol
                 queda en contorno, que es información de fondo. --}}
            @if ($spgSucursal)
                <span class="spg-suc-chip d-none d-md-inline" title="Estás trabajando en esta sucursal">
                    <i class="bi bi-shop"></i> {{ $spgSucursal }}</span>
            @endif
            <span class="spg-rol-chip d-none d-md-inline">{{ $spgSesion['rol_nom'] }}</span>
        </div>
    @endif
</header>

@if ($spgMenu)
    {{-- Los módulos siempre a la vista, con el actual marcado en oro. Antes
         había que abrir un desplegable para saber dónde se estaba parado. --}}
    <nav class="spg-nav" aria-label="Módulos del sistema">
        <div class="spg-nav-in">
            <a class="spg-nav-item {{ $spgRuta === 'panel' ? 'activo' : '' }}" href="{{ Navegacion::url('panel') }}">
                <i class="bi bi-house-door"></i><span>Panel</span></a>
            @foreach ($spgMenu as $spgMod)
                <a class="spg-nav-item {{ $spgModulo === $spgMod['mod'] ? 'activo' : '' }}"
                   href="{{ $spgMod['url'] }}" title="{{ $spgMod['sub'] }}">
                    <i class="bi bi-{{ $spgMod['ic'] }}"></i><span>{{ $spgMod['titulo'] }}</span></a>
            @endforeach
        </div>
    </nav>
@endif

<main class="container py-2">
    @foreach (session('spg_flash', []) as $spgF)
        @php $spgCls = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'][$spgF['tipo']] ?? 'secondary'; @endphp
        <div class="alert alert-{{ $spgCls }} alert-dismissible fade show" role="alert">
            {{ $spgF['msg'] }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endforeach

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            @foreach ($errors->all() as $spgError)
                <div>{{ $spgError }}</div>
            @endforeach
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    @if ($spgRapidos)
        <nav class="spg-rapidos" aria-label="Accesos rápidos">
            <span class="spg-rapidos-lbl"><i class="bi bi-lightning-charge-fill"></i> Ir a</span>
            @foreach ($spgRapidos as $spgA)
                <a class="spg-chip" href="{{ $spgA['url'] }}">
                    <i class="bi bi-{{ $spgA['icono'] }}"></i> {{ $spgA['titulo'] }}</a>
            @endforeach
        </nav>
    @endif

    @yield('contenido')
</main>

<footer class="spg-footer">
    <div class="spg-footer-in">

        <div class="spg-footer-col spg-footer-marca">
            <div class="spg-footer-logo"><i class="bi bi-scissors"></i> {{ config('app.name') }}</div>
            <p class="spg-footer-tcc">Trabajo de Conclusión de Curso · Ingeniería en Informática</p>
        </div>

        {{-- «Secciones» y no «Módulos»: módulo es la palabra del desarrollo,
             no la de quien usa el sistema. --}}
        @if ($spgMenu)
            <div class="spg-footer-col spg-footer-secciones">
                <h3>Secciones</h3>
                <ul class="spg-footer-grid">
                    @foreach ($spgMenu as $spgMod)
                        <li><a href="{{ $spgMod['url'] }}">{{ $spgMod['titulo'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        @elseif ($spgPortal)
            <div class="spg-footer-col spg-footer-secciones">
                <h3>Secciones</h3>
                <ul class="spg-footer-grid">
                    @foreach ($spgPortal as $spgP)
                        <li><a href="{{ $spgP['url'] }}">{{ $spgP['titulo'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Si no hay ningún contacto cargado, el bloque no se dibuja: un
             «Centro de Ayuda» vacío no ayuda a nadie. --}}
        @if ($spgContacto)
            <div class="spg-footer-col spg-footer-ayuda">
                <h3>Centro de Ayuda y Soporte</h3>
                <ul>
                    @foreach ($spgContacto as $spgC)
                        <li>
                            <a class="spg-contacto" href="{{ $spgC['url'] }}" target="_blank" rel="noopener noreferrer">
                                <i class="bi bi-{{ $spgC['icono'] }}"></i> {{ $spgC['etiqueta'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="spg-footer-col spg-footer-version">
            <h3>Versión</h3>
            <div class="spg-version-nro">v{{ config('spg.version') }}</div>
            <div class="spg-version-fecha">
                Actualizado el {{ fecha(config('spg.version_fecha'), 'd/m/Y') }}
            </div>
        </div>

    </div>

    <div class="spg-footer-pie">
        <span>© {{ date('Y') }} {{ config('app.name') }} · Luque, Paraguay</span>
        <span class="spg-footer-sep">·</span>
        <span>Los comprobantes siguen el formato de la SET (Manual Técnico SIFEN v150)</span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ recurso('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
