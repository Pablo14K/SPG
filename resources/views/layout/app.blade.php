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
    que acá todo va con prefijo `sgp`.
--}}
@php
    use App\Servicios\Navegacion;
    use App\Servicios\Permisos;

    // La foto de quien está en sesión, o null para sus iniciales.
    $sgpFotoPerfil = \App\Servicios\Perfil::foto();

    $sgpRuta     = Route::currentRouteName() ?? '';
    // **Cuál módulo se marca en la barra sale del PERMISO de la pantalla.**
    // Con el prefijo del nombre de ruta, estando en Personal se encendía
    // Seguridad: sus pantallas viven bajo `/seguridad` desde la 7.57.0.
    $sgpModulo   = Navegacion::moduloDe($sgpRuta);
    $sgpSesion   = session('uid') ? ['nombre' => session('nombre'), 'rol_nom' => session('rol_nom')] : null;
    $sgpCliente  = (bool) session('es_cliente', false);
    // En qué local está trabajando. Se muestra SIEMPRE que haya una elegida,
    // aunque el salón tenga una sola: quien atiende tiene que poder contestar
    // «¿en qué sucursal estoy?» sin abrir nada, porque de eso dependen la
    // agenda que ve, la caja que cierra y el stock que descuenta. La clienta
    // no tiene ninguna — elige el local al agendar.
    $sgpSucursal = $sgpCliente ? '' : (string) session('sucursal_nom', '');
    $sgpMenu     = $sgpSesion && ! $sgpCliente ? Navegacion::modulos() : [];
    $sgpRapidos  = $sgpSesion && ! $sgpCliente ? Navegacion::accesosRapidos($sgpRuta) : [];
    $sgpPortal   = $sgpCliente ? Navegacion::portal() : [];
    $sgpContacto = Navegacion::contactos();
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
    @include('layout._favicon')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ recurso('css/app.css') }}" rel="stylesheet">
    {{-- **La foto de perfil se pide ANTES de que el navegador llegue a la
         barra.** Se reportó un parpadeo al entrar: un segundo con el disco
         dorado del avatar por defecto y recién después la cara. Es que la
         imagen se pedía cuando el navegador ya había dibujado la barra —después
         de bajar y aplicar el CSS—, y hasta que llegaba se veía el fondo del
         avatar. Con el `preload` la pide junto con el CSS, así que casi siempre
         está antes de que haya que dibujarla. El fondo, además, ya no se pinta
         debajo de una foto (`tiene-img`), así que si igual tarda no se ve el
         avatar de otro. --}}
    @if ($sgpFotoPerfil)
        <link rel="preload" as="image" href="{{ $sgpFotoPerfil }}" fetchpriority="high">
    @endif
    @stack('estilos')
</head>
{{-- **Las pantallas que se miran entre varios avisan si algo cambió.**

     El sistema navega a la vieja usanza, así que cada pantalla es una foto del
     momento en que se pidió: dos personas sobre la misma agenda, una registra
     la atención y la otra la sigue viendo Programada hasta que recarga. La
     sección la declara cada vista con `@section('vivo', 'agenda')`, y desde
     ahí `app.js` consulta la huella; sin declararla, no se consulta nada. --}}
@php $sgpVivo = trim($__env->yieldContent('vivo')); @endphp
<body @if ($sgpVivo && $sgpSesion) data-vivo="{{ $sgpVivo }}"
      data-vivo-url="{{ route('vivo', array_filter(['s' => $sgpVivo, 'dia' => request()->query('dia')])) }}" @endif>

{{-- **El cajón lateral se abre con CSS, no con JavaScript.**

     En pantalla angosta la barra de módulos era un riel fijo de 54 px con el
     ícono grande y el rótulo diminuto, y el contenido se corría con un
     `margin-left` — que **seguía aplicándose en el Panel, donde la barra ni se
     dibuja**: de ahí el hueco al costado que se veía en el celular.

     Ahora es un cajón que se desliza por encima y no le roba ancho a nada. Va
     con la casilla escondida y su etiqueta, como el resto de la navegación del
     sistema, para que siga funcionando con `app.js` caído. --}}
@if ($sgpSesion)
    <input type="checkbox" id="sgpCajon" class="sgp-cajon-int" aria-hidden="true">
    <label for="sgpCajon" class="sgp-cajon-fondo" aria-hidden="true"></label>
@endif

<header class="sgp-topbar">
    {{-- **También en el portal.** La clienta se quedaba con la barra
         horizontal —una SEGUNDA barra bajo la cabecera, en un teléfono donde
         el alto es lo que falta— mientras el personal ya tenía el cajón. Y el
         botón se dibujaba igual, así que abría algo que no existía. --}}
    @if ($sgpSesion && $sgpRuta !== 'panel' && $sgpRuta !== 'portal.index'
         && ($sgpMenu || $sgpPortal))
        <label for="sgpCajon" class="sgp-cajon-btn" role="button" tabindex="0"
               aria-label="Abrir el menú">
            <i class="bi bi-list"></i>
        </label>
    @endif
    <a class="sgp-brand" href="{{ Navegacion::url($sgpCliente ? 'portal.index' : 'panel') ?? url('/') }}">
        {{-- El logo del salón si lo cargó; si no, la tijera de siempre. Lo
             dibuja el partial, que es donde se decide sacarle el fondo dorado
             —el del ícono por defecto— cuando hay imagen. --}}
        @include('layout._marca', ['modo' => 'barra'])
        <span class="sgp-brand-txt">
            <span class="sgp-brand-name">{{ config('app.name') }}</span>
            <span class="sgp-brand-sub">Sistema de gestión</span>
        </span>
    </a>

    @if ($sgpSesion)
        <div class="sgp-user">
            <div class="dropdown">
                <button class="sgp-user-link" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                        title="Mi cuenta">
                    {{-- **La foto de perfil, si la cargó.** Un monigote igual
                         para todos no distingue a nadie; sin foto van las
                         iniciales, que sí. --}}
                    @if ($sgpFotoPerfil)
                        <span class="sgp-avatar tiene-img"><img src="{{ $sgpFotoPerfil }}" alt=""
                            width="26" height="26" decoding="sync" fetchpriority="high"></span>
                    @else
                        <span class="sgp-avatar">{{ \App\Servicios\Perfil::iniciales() }}</span>
                    @endif
                    <span class="sgp-user-nombre">{{ $sgpSesion['nombre'] }}</span>
                    <i class="bi bi-chevron-down" style="font-size:.65rem"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end sgp-dropdown">
                    <li><span class="dropdown-item-text sgp-drop-cabecera">
                        <strong>{{ $sgpSesion['nombre'] }}</strong>
                        <span>{{ $sgpSesion['rol_nom'] }}</span>
                        {{-- En pantalla chica la ficha de arriba no se dibuja,
                             así que acá es el único lugar donde se ve. --}}
                        @if ($sgpSucursal)
                            <span class="txt-oro"><i class="bi bi-shop"></i> {{ $sgpSucursal }}</span>
                        @endif
                    </span></li>
                    <li><hr class="dropdown-divider"></li>
                    @if (count((array) session('roles', [])) > 1)
                        <li><span class="dropdown-item-text text-muted-warm" style="font-size:.75rem">Cambiar perspectiva</span></li>
                        @foreach ((array) session('roles', []) as $sgpRol)
                            <li><form method="post" action="{{ route('cuenta.cambiar_rol') }}">
                                @csrf
                                <input type="hidden" name="id_rol" value="{{ $sgpRol['id_rol'] }}">
                                <button class="dropdown-item {{ (int) $sgpRol['id_rol'] === (int) session('rol') ? 'active' : '' }}" @disabled((int) $sgpRol['id_rol'] === (int) session('rol'))>
                                    <i class="bi bi-person-badge"></i> {{ $sgpRol['nombre'] }}
                                </button>
                            </form></li>
                        @endforeach
                        <li><hr class="dropdown-divider"></li>
                    @endif
                    @if (Navegacion::existe('cuenta.index'))
                        <li><a class="dropdown-item" href="{{ Navegacion::url('cuenta.index') }}">
                            <i class="bi bi-gear"></i> Mi cuenta</a></li>
                    @endif
                    @if ($sgpCliente && Navegacion::existe('portal.preferencias'))
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
            @if ($sgpSucursal)
                <span class="sgp-suc-chip d-none d-md-inline" title="Estás trabajando en esta sucursal">
                    <i class="bi bi-shop"></i> {{ $sgpSucursal }}</span>
            @endif
            <span class="sgp-rol-chip d-none d-md-inline">{{ $sgpSesion['rol_nom'] }}</span>

        </div>
    @endif
</header>

{{-- **La barra de la clienta.** Hasta la 7.37.1 el portal no tenía ninguna: las
     secciones vivían sólo en el pie, así que para pasar de «Reservar» a «Mis
     citas» había que bajar hasta el final de la página. El personal tenía tres
     niveles de navegación y la clienta ninguno — justo en la parte del sistema
     que usa gente sin entrenamiento.

     Usa la misma barra que el personal (`.sgp-nav`), sin desplegable: las
     secciones de la clienta no tienen pantallas adentro. --}}
{{-- **La barra no va en el panel principal del portal.** Ahí la clienta ya
     tiene todo a la vista en tarjetas grandes, así que la barra repetía la
     misma lista dos veces. La pregunta que contesta —«¿a qué otra pantalla
     voy?»— aparece recién cuando ya está adentro de una. Es el mismo criterio
     que sacó la barra de módulos del Panel en la 7.34.1. --}}
@if ($sgpPortal && $sgpRuta !== 'portal.index')
    <nav class="sgp-nav sgp-nav-mod" aria-label="Secciones">
        <div class="sgp-nav-in">
            @php
                $sgpCitas = collect($sgpPortal)->filter(fn ($p) => in_array($p['clave'], ['portal.reservar', 'portal.citas'], true));
            @endphp
            @if ($sgpCitas->isNotEmpty())
                @php $sgpCitasActivo = $sgpCitas->contains(fn ($p) => $sgpRuta === $p['clave']); @endphp
                {{-- **La misma pieza que los módulos del personal, y no un
                     `<details>`.**

                     Era `<details>/<summary>`, y eso se comporta distinto en
                     escritorio: el grupo se abría **con un clic** y el menú
                     quedaba desplegado en el propio renglón —`position:static`—
                     en vez del desplegable flotante que abre al pasar el mouse
                     en el resto del sistema. En el celular las dos formas se
                     ven igual, así que el defecto sólo se veía en la
                     computadora.

                     Con la casilla escondida y su etiqueta, el portal usa
                     exactamente el mismo mecanismo que la barra de módulos:
                     hover en escritorio, toque en el cajón, **CSS y sin
                     JavaScript** en los dos casos. --}}
                <div class="sgp-nav-grupo">
                    <input type="checkbox" id="sgpGPortalCitas" class="sgp-nav-int"
                           aria-hidden="true" @checked($sgpCitasActivo)>
                    <a class="sgp-nav-item {{ $sgpCitasActivo ? 'activo' : '' }}"
                       href="{{ $sgpCitas->first()['url'] }}" aria-haspopup="true">
                        <i class="bi bi-calendar-event"></i><span>Citas</span>
                        <i class="bi bi-chevron-down sgp-nav-flecha"></i></a>
                    <label for="sgpGPortalCitas" class="sgp-nav-tog" aria-label="Desplegar Citas"></label>
                    <div class="sgp-nav-menu" role="menu" aria-label="Citas">
                        @foreach ($sgpCitas as $sgpP)
                            <a role="menuitem" class="{{ $sgpRuta === $sgpP['clave'] ? 'activo' : '' }}" href="{{ $sgpP['url'] }}">
                                <i class="bi bi-{{ $sgpP['ic'] }}"></i><span>{{ $sgpP['titulo'] }}</span></a>
                        @endforeach
                    </div>
                </div>
            @endif
            @foreach ($sgpPortal as $sgpP)
                @if ($sgpP['barra'] && ! in_array($sgpP['clave'], ['portal.reservar', 'portal.citas'], true))
                    <a class="sgp-nav-item {{ $sgpRuta === $sgpP['clave'] ? 'activo' : '' }}"
                       href="{{ $sgpP['url'] }}">
                        <i class="bi bi-{{ $sgpP['ic'] }}"></i><span>{{ $sgpP['titulo'] }}</span></a>
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
@if ($sgpMenu && $sgpRuta !== 'panel')
    {{-- Los módulos siempre a la vista, con el actual marcado en oro. Antes
         había que abrir un desplegable para saber dónde se estaba parado. --}}
    <nav class="sgp-nav sgp-nav-mod" aria-label="Módulos del sistema">
        <div class="sgp-nav-in">
            <a class="sgp-nav-item {{ $sgpRuta === 'panel' ? 'activo' : '' }}" href="{{ Navegacion::url('panel') }}">
                <i class="bi bi-house-door"></i><span>Panel</span></a>
            @foreach ($sgpMenu as $sgpMod)
                @php
                    // Con una sola pantalla el desplegable repetiría el propio
                    // enlace del módulo: Reportes es una sola pantalla, así que
                    // ahí no se dibuja nada. Un menú de un renglón que lleva al
                    // mismo lugar es ruido.
                    $sgpPant = Navegacion::pantallasDe($sgpMod['mod']);
                    $sgpPant = count($sgpPant) > 1 ? $sgpPant : [];
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
                <div class="sgp-nav-grupo">
                    {{-- **En el celular el módulo DESPLIEGA, no navega.**

                         En el cajón el desplegable de escritorio no existe —el
                         hover tampoco— así que tocar «Tesorería» llevaba a su
                         tarjeta y las ocho pantallas quedaban a dos pantallas
                         de distancia. Ahora la fila abre el grupo ahí mismo, y
                         el primer renglón de adentro es el módulo, para no
                         perder el camino a la tarjeta.

                         Es una casilla escondida con su etiqueta encima, como
                         el propio cajón: **se abre con CSS**, así que funciona
                         con `app.js` caído. En escritorio la etiqueta no se
                         dibuja y manda el enlace de siempre. --}}
                    @if ($sgpPant)
                        <input type="checkbox" id="sgpG{{ $loop->index }}" class="sgp-nav-int"
                               aria-hidden="true" @checked($sgpModulo === $sgpMod['mod'])>
                    @endif
                    <a class="sgp-nav-item {{ $sgpModulo === $sgpMod['mod'] ? 'activo' : '' }}"
                       href="{{ $sgpMod['url'] }}" title="{{ $sgpMod['sub'] }}"
                       @if ($sgpPant) aria-haspopup="true" @endif>
                        <i class="bi bi-{{ $sgpMod['ic'] }}"></i><span>{{ $sgpMod['titulo'] }}</span>
                        @if ($sgpPant)<i class="bi bi-chevron-down sgp-nav-flecha"></i>@endif</a>
                    @if ($sgpPant)
                        <label for="sgpG{{ $loop->index }}" class="sgp-nav-tog"
                               aria-label="Desplegar {{ $sgpMod['titulo'] }}"></label>
                    @endif

                    @if ($sgpPant)
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
                            $sgpGrupos = [];
                            foreach ($sgpPant as $sgpP) {
                                $sgpGrupos[$sgpP['grupo'] ?? ''][] = $sgpP;
                            }
                        @endphp
                        <div class="sgp-nav-menu" role="menu" aria-label="{{ $sgpMod['titulo'] }}">
                            {{-- Sólo en el celular: en escritorio el nombre del
                                 módulo ya es el enlace, y repetirlo sería un
                                 renglón que lleva a donde acabás de tocar. --}}
                            <a role="menuitem" class="sgp-nav-todo" href="{{ $sgpMod['url'] }}">
                                <i class="bi bi-grid"></i><span>Ver {{ $sgpMod['titulo'] }}</span></a>
                            @foreach ($sgpGrupos as $sgpNom => $sgpDelGrupo)
                                @if ($sgpNom === '' || count($sgpDelGrupo) === 1)
                                    @foreach ($sgpDelGrupo as $sgpP)
                                        <a role="menuitem" class="{{ $sgpRuta === $sgpP['clave'] ? 'activo' : '' }}"
                                           href="{{ $sgpP['url'] }}">
                                            <i class="bi bi-{{ $sgpP['ic'] }}"></i><span>{{ $sgpP['t'] }}</span></a>
                                    @endforeach
                                @else
                                    @php
                                        $sgpActivo = collect($sgpDelGrupo)->contains(fn ($x) => $sgpRuta === $x['clave']);
                                    @endphp
                                    <div class="sgp-nav-sub-wrap">
                                        {{-- El grupo lleva al primero de los
                                             suyos, así que también sirve con
                                             el hover roto o en táctil. --}}
                                        <a role="menuitem" href="{{ $sgpDelGrupo[0]['url'] }}"
                                           class="sgp-nav-sub-tit {{ $sgpActivo ? 'activo' : '' }}">
                                            <i class="bi bi-{{ $sgpDelGrupo[0]['ic'] }}"></i>
                                            <span>{{ $sgpNom }}</span>
                                            <i class="bi bi-chevron-right sgp-nav-sub-flecha"></i></a>
                                        <div class="sgp-nav-sub" role="menu" aria-label="{{ $sgpNom }}">
                                            @foreach ($sgpDelGrupo as $sgpP)
                                                <a role="menuitem" href="{{ $sgpP['url'] }}"
                                                   class="{{ $sgpRuta === $sgpP['clave'] ? 'activo' : '' }}">
                                                    <i class="bi bi-{{ $sgpP['ic'] }}"></i><span>{{ $sgpP['t'] }}</span></a>
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
    {{-- **Hay avisos que no se pueden perder de vista, y una franja se cierra
         sin leerse.** El de la reserva sin confirmar dice tres cosas que la
         clienta necesita saber ANTES de irse de la pantalla —el plazo de la
         seña, que el cambio de día es uno solo, y que la seña no vuelve— así
         que va como ventana del sistema, con su botón de Aceptar, igual que la
         de cerrar sesión.

         El tipo `modal` es el único que se dibuja así; los demás siguen siendo
         la franja de siempre, que para «Guardado» es lo correcto. --}}
    @foreach (session('sgp_flash', []) as $sgpI => $sgpF)
        @if (($sgpF['tipo'] ?? '') === 'modal')
            <div class="modal fade" id="sgpAviso{{ $sgpI }}" tabindex="-1" aria-hidden="true"
                 data-sgp-abrir>
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 class="modal-title fs-5">
                                <i class="bi bi-info-circle"></i> {{ $sgpF['titulo'] ?? 'Tenelo en cuenta' }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            {{-- Cada renglón es una frase: un párrafo de seis líneas
                                 no se lee, se saltea. --}}
                            @foreach (preg_split('/\n+/', (string) $sgpF['msg']) as $sgpL)
                                @if (trim($sgpL) !== '')
                                    <p class="mb-2">{{ trim($sgpL) }}</p>
                                @endif
                            @endforeach
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-oro" data-bs-dismiss="modal">Aceptar</button>
                        </div>
                    </div>
                </div>
            </div>
            {{-- **El respaldo se dibuja SIEMPRE y lo saca el JS al abrir la
                 ventana**, no al revés. Con `<noscript>` sólo aparecía con el
                 JavaScript apagado: si Bootstrap no carga —o carga mal— el
                 modal queda en `display:none` y el aviso desaparecía entero,
                 que es justo lo que no puede pasar con éste. --}}
            <div class="alert alert-warning" role="alert"
                 data-sgp-respaldo="sgpAviso{{ $sgpI }}">{{ $sgpF['msg'] }}</div>
        @else
            @php $sgpCls = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'][$sgpF['tipo']] ?? 'secondary'; @endphp
            <div class="alert alert-{{ $sgpCls }} alert-dismissible fade show" role="alert">
                {{ $sgpF['msg'] }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            @foreach ($errors->all() as $sgpError)
                <div>{{ $sgpError }}</div>
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

<footer class="sgp-footer">
    <div class="sgp-footer-in">

        <div class="sgp-footer-col sgp-footer-marca">
            <div class="sgp-footer-logo">@include('layout._marca') {{ config('app.name') }}</div>
            <p class="sgp-footer-tcc">Trabajo de Conclusión de Curso · Ingeniería en Informática</p>
        </div>

        {{-- «Secciones» y no «Módulos»: módulo es la palabra del desarrollo,
             no la de quien usa el sistema. --}}
        @if ($sgpMenu)
            <div class="sgp-footer-col sgp-footer-secciones">
                <h3>Secciones</h3>
                <ul class="sgp-footer-grid">
                    @foreach ($sgpMenu as $sgpMod)
                        <li><a href="{{ $sgpMod['url'] }}">{{ $sgpMod['titulo'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        @elseif ($sgpPortal)
            <div class="sgp-footer-col sgp-footer-secciones">
                <h3>Secciones</h3>
                <ul class="sgp-footer-grid">
                    @foreach ($sgpPortal as $sgpP)
                        <li><a href="{{ $sgpP['url'] }}">{{ $sgpP['titulo'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Si no hay ningún contacto cargado, el bloque no se dibuja: un
             «Centro de Ayuda» vacío no ayuda a nadie. --}}
        @if ($sgpContacto)
            <div class="sgp-footer-col sgp-footer-ayuda">
                <h3>Centro de Ayuda y Soporte</h3>
                <ul>
                    @foreach ($sgpContacto as $sgpC)
                        <li>
                            <a class="sgp-contacto" href="{{ $sgpC['url'] }}" target="_blank" rel="noopener noreferrer">
                                <i class="bi bi-{{ $sgpC['icono'] }}"></i> {{ $sgpC['etiqueta'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="sgp-footer-col sgp-footer-version">
            <h3>Versión</h3>
            <div class="sgp-version-nro">v{{ config('sgp.version') }}</div>
            <div class="sgp-version-fecha">
                Actualizado el {{ fecha(config('sgp.version_fecha'), 'd/m/Y') }}
            </div>
        </div>

    </div>

    <div class="sgp-footer-pie">
        <span>© {{ date('Y') }} {{ config('app.name') }} · Luque, Paraguay</span>
        <span class="sgp-footer-sep">·</span>
        <span>Los comprobantes siguen el formato de la DNIT (Manual Técnico SIFEN v150)</span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ recurso('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
