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
    use App\Servicios\Config;
    use App\Servicios\Navegacion;
    use App\Servicios\Permisos;

    // El logo que cargó el salón, o null para la tijera de siempre.
    $spgLogo = Config::logo();

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

{{-- **El cajón lateral se abre con CSS, no con JavaScript.**

     En pantalla angosta la barra de módulos era un riel fijo de 54 px con el
     ícono grande y el rótulo diminuto, y el contenido se corría con un
     `margin-left` — que **seguía aplicándose en el Panel, donde la barra ni se
     dibuja**: de ahí el hueco al costado que se veía en el celular.

     Ahora es un cajón que se desliza por encima y no le roba ancho a nada. Va
     con la casilla escondida y su etiqueta, como el resto de la navegación del
     sistema, para que siga funcionando con `app.js` caído. --}}
@if ($spgSesion)
    <input type="checkbox" id="spgCajon" class="spg-cajon-int" aria-hidden="true">
    <label for="spgCajon" class="spg-cajon-fondo" aria-hidden="true"></label>
@endif

<header class="spg-topbar">
    @if ($spgSesion && $spgMenu && $spgRuta !== 'panel')
        <label for="spgCajon" class="spg-cajon-btn" role="button" tabindex="0"
               aria-label="Abrir el menú de módulos">
            <i class="bi bi-list"></i>
        </label>
    @endif
    <a class="spg-brand" href="{{ Navegacion::url($spgCliente ? 'portal.index' : 'panel') ?? url('/') }}">
        {{-- El logo del salón si lo cargó; si no, la tijera de siempre. --}}
        <span class="spg-logo">
            @if ($spgLogo)
                <img src="{{ $spgLogo }}" alt="" style="height:100%;width:100%;object-fit:contain">
            @else
                <i class="bi bi-scissors"></i>
            @endif
        </span>
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

            {{-- **Contra qué base está corriendo, mientras se desarrolla.**

                 El sistema se levanta contra dos bases —`peluqueria_bd`, la que
                 se entrega, y `peluqueria_test`, el mes simulado— y la línea que
                 lo decide se movió tres veces (6.1.2, 6.3.1, 7.1.1). Cada vez
                 costó lo mismo: se trabaja un rato contra la que no era y no hay
                 nada en pantalla que lo diga.

                 También explica lo de las dos computadoras: el mismo código se
                 comporta distinto según los datos que tenga la base, y sin ver
                 cuál es se busca el problema en el código.

                 **Sólo con `APP_DEBUG`**, o sea nunca en el salón: ahí hay una
                 sola base y decir su nombre sería ruido — además de informar de
                 más a quien mire la pantalla. --}}
            @if (config('app.debug') && ($spgBase = config('database.connections.' . config('database.default') . '.database')))
                <span class="spg-base-chip d-none d-lg-inline"
                      title="Base de datos con la que está corriendo el sistema. Sólo se ve en desarrollo.">
                    <i class="bi bi-database"></i> {{ $spgBase }}</span>
            @endif
        </div>
    @endif
</header>

{{-- **La barra de la clienta.** Hasta la 7.37.1 el portal no tenía ninguna: las
     secciones vivían sólo en el pie, así que para pasar de «Reservar» a «Mis
     citas» había que bajar hasta el final de la página. El personal tenía tres
     niveles de navegación y la clienta ninguno — justo en la parte del sistema
     que usa gente sin entrenamiento.

     Usa la misma barra que el personal (`.spg-nav`), sin desplegable: las
     secciones de la clienta no tienen pantallas adentro. --}}
{{-- **La barra no va en el panel principal del portal.** Ahí la clienta ya
     tiene todo a la vista en tarjetas grandes, así que la barra repetía la
     misma lista dos veces. La pregunta que contesta —«¿a qué otra pantalla
     voy?»— aparece recién cuando ya está adentro de una. Es el mismo criterio
     que sacó la barra de módulos del Panel en la 7.34.1. --}}
@if ($spgPortal && $spgRuta !== 'portal.index')
    <nav class="spg-nav" aria-label="Secciones">
        <div class="spg-nav-in">
            @foreach ($spgPortal as $spgP)
                @if ($spgP['barra'])
                    <a class="spg-nav-item {{ $spgRuta === $spgP['clave'] ? 'activo' : '' }}"
                       href="{{ $spgP['url'] }}">
                        <i class="bi bi-{{ $spgP['ic'] }}"></i><span>{{ $spgP['titulo'] }}</span></a>
                @endif
            @endforeach
        </div>
    </nav>
@endif

{{-- **En el Panel la barra no se dibuja, y adentro de los módulos sí.** Es
     pedido del usuario y el motivo se ve mirando la pantalla: el Panel ya
     muestra los módulos en tarjetas grandes, unos centímetros más abajo, así
     que la barra repite la misma lista dos veces y sólo agrega ruido. La
     pregunta que contesta —«¿a qué otro módulo voy?»— recién aparece cuando ya
     estás adentro de uno, que es donde las tarjetas ya no están. --}}
@if ($spgMenu && $spgRuta !== 'panel')
    {{-- Los módulos siempre a la vista, con el actual marcado en oro. Antes
         había que abrir un desplegable para saber dónde se estaba parado. --}}
    <nav class="spg-nav spg-nav-mod" aria-label="Módulos del sistema">
        <div class="spg-nav-in">
            <a class="spg-nav-item {{ $spgRuta === 'panel' ? 'activo' : '' }}" href="{{ Navegacion::url('panel') }}">
                <i class="bi bi-house-door"></i><span>Panel</span></a>
            @foreach ($spgMenu as $spgMod)
                @php
                    // Con una sola pantalla el desplegable repetiría el propio
                    // enlace del módulo: Reportes es una sola pantalla, así que
                    // ahí no se dibuja nada. Un menú de un renglón que lleva al
                    // mismo lugar es ruido.
                    $spgPant = Navegacion::pantallasDe($spgMod['mod']);
                    $spgPant = count($spgPant) > 1 ? $spgPant : [];
                @endphp
                {{-- **El módulo se abre al pasar el mouse**, para llegar a la
                     pantalla sin pasar por la tarjeta del medio: eran dos clics
                     y una pantalla entera de por medio para algo que se hace
                     veinte veces por día.

                     El enlace del módulo sigue estando y sigue llevando a su
                     tarjeta: el desplegable es un atajo, no un reemplazo. Por
                     eso se abre con `:hover` de CSS y **no con JavaScript** —así
                     funciona igual si `app.js` no cargó— y por eso también se
                     abre con el foco del teclado (`:focus-within`), que si no
                     quien navega con Tab se queda sin los atajos.

                     Sale del catálogo de pantallas, con el mismo filtro por
                     permiso que pide el middleware. Ver `pantallasDe()`. --}}
                <div class="spg-nav-grupo">
                    <a class="spg-nav-item {{ $spgModulo === $spgMod['mod'] ? 'activo' : '' }}"
                       href="{{ $spgMod['url'] }}" title="{{ $spgMod['sub'] }}"
                       @if ($spgPant) aria-haspopup="true" @endif>
                        <i class="bi bi-{{ $spgMod['ic'] }}"></i><span>{{ $spgMod['titulo'] }}</span>
                        @if ($spgPant)<i class="bi bi-chevron-down spg-nav-flecha"></i>@endif</a>

                    @if ($spgPant)
                        @php
                            // **Los renglones se juntan por grupo.** Con las
                            // ocho pantallas de Tesorería sueltas no se ve qué
                            // va con qué, y con los rótulos intercalados el
                            // menú se hace largo: son doce renglones para
                            // elegir uno.
                            //
                            // Agrupado son cuatro —Facturación, Cobros, Caja,
                            // Pagos— y cada uno abre al costado. **El grupo de
                            // una sola pantalla NO abre nada**: se dibuja como
                            // enlace directo, porque un submenú de un renglón
                            // hace pasar el mouse por dos lugares para llegar
                            // al mismo sitio.
                            $spgGrupos = [];
                            foreach ($spgPant as $spgP) {
                                $spgGrupos[$spgP['grupo'] ?? ''][] = $spgP;
                            }
                        @endphp
                        <div class="spg-nav-menu" role="menu" aria-label="{{ $spgMod['titulo'] }}">
                            @foreach ($spgGrupos as $spgNom => $spgDelGrupo)
                                @if ($spgNom === '' || count($spgDelGrupo) === 1)
                                    @foreach ($spgDelGrupo as $spgP)
                                        <a role="menuitem" class="{{ $spgRuta === $spgP['clave'] ? 'activo' : '' }}"
                                           href="{{ $spgP['url'] }}">
                                            <i class="bi bi-{{ $spgP['ic'] }}"></i><span>{{ $spgP['t'] }}</span></a>
                                    @endforeach
                                @else
                                    @php
                                        $spgActivo = collect($spgDelGrupo)->contains(fn ($x) => $spgRuta === $x['clave']);
                                    @endphp
                                    <div class="spg-nav-sub-wrap">
                                        {{-- El grupo lleva al primero de los
                                             suyos, así que también sirve con
                                             el hover roto o en táctil. --}}
                                        <a role="menuitem" href="{{ $spgDelGrupo[0]['url'] }}"
                                           class="spg-nav-sub-tit {{ $spgActivo ? 'activo' : '' }}">
                                            <i class="bi bi-{{ $spgDelGrupo[0]['ic'] }}"></i>
                                            <span>{{ $spgNom }}</span>
                                            <i class="bi bi-chevron-right spg-nav-sub-flecha"></i></a>
                                        <div class="spg-nav-sub" role="menu" aria-label="{{ $spgNom }}">
                                            @foreach ($spgDelGrupo as $spgP)
                                                <a role="menuitem" href="{{ $spgP['url'] }}"
                                                   class="{{ $spgRuta === $spgP['clave'] ? 'activo' : '' }}">
                                                    <i class="bi bi-{{ $spgP['ic'] }}"></i><span>{{ $spgP['t'] }}</span></a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
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

    {{-- Acá iba la barra de accesos rápidos («Ir a: Nueva cita · Clientes…»).
         Se saca por pedido del usuario: competía con la barra de módulos y con
         las tarjetas del módulo, que ya contestan a dónde ir. Tres niveles de
         navegación apilados arriba del contenido es ruido, no ayuda.

         La maquinaria sigue en `Navegacion::accesosRapidos()` y en
         `config/navegacion.php` por si se la quiere devolver en otro lugar —
         un pie de pantalla, por ejemplo— pero hoy no la dibuja nadie. --}}

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
