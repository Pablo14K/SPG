{{--
    Encabezado estándar de pantalla: migas, título y acciones.

    Antes cada vista escribía su propio bloque —un enlace «volver», un h1 y a
    veces un subtítulo, cada una con su maquetado—. Ahora sale igual en las
    treinta pantallas.

    Las migas se arman SOLAS con el catálogo de config/navegacion.php: la vista
    no las declara, así no se desfasan cuando se renombra una pantalla.

        <x-encabezado sub="Citas del día"
                      :accion="['ruta' => 'citas.form', 't' => 'Nueva cita', 'ic' => 'calendar-plus']" />
--}}
@props(['titulo' => null, 'sub' => null, 'accion' => null, 'acciones' => []])

@php
    use App\Servicios\Navegacion;
    use App\Servicios\Permisos;

    $ruta = Route::currentRouteName() ?? '';
    $pantalla = Navegacion::pantalla($ruta);
    $tituloFinal = $titulo ?? ($pantalla['titulo'] ?? config('app.name'));

    // El módulo sale del permiso de la pantalla: `seguridad.turnos` → `seguridad`
    $modClave = strtok((string) ($pantalla['permiso'] ?? $ruta), '.');
    $modEtiqueta = config('permisos.modulos.' . $modClave);

    $migas = [['t' => 'Panel', 'url' => Navegacion::url('panel'), 'ic' => 'house']];

    // El módulo se saltea cuando se llama igual que la pantalla: «Clientes ›
    // Clientes» no le dice nada a nadie.
    if ($modEtiqueta && $modEtiqueta !== $tituloFinal && Permisos::puede($modClave)) {
        $urlMod = Navegacion::url($modClave . '.index');
        if ($urlMod) {
            $migas[] = ['t' => $modEtiqueta, 'url' => $urlMod, 'ic' => null];
        }
    }
    $migas[] = ['t' => $tituloFinal, 'url' => null, 'ic' => null];
@endphp

<nav class="spg-migas" aria-label="Dónde estoy">
    @foreach ($migas as $i => $m)
        @if ($i)<i class="bi bi-chevron-right spg-miga-sep" aria-hidden="true"></i>@endif
        @if ($m['url'])
            <a href="{{ $m['url'] }}">
                @if ($m['ic'])<i class="bi bi-{{ $m['ic'] }}"></i>@endif{{ $m['t'] }}</a>
        @else
            <span aria-current="page">{{ $m['t'] }}</span>
        @endif
    @endforeach
</nav>

<div class="spg-page-head spg-head-flex">
    <div class="spg-head-txt">
        {{-- **La explicación se guarda detrás del ícono.** Estaba siempre a la
             vista, debajo del título de cada pantalla: para quien ya sabe qué
             hace ahí es un párrafo que hay que saltear cada vez que entra. No se
             saca, se guarda — se toca el ícono y aparece, se toca afuera y se va.

             Va al lado del título y no debajo: es ayuda SOBRE el título, y
             puesta abajo volvería a ocupar su propio renglón, que es lo que se
             quería evitar. --}}
        <h1>{{ $tituloFinal }}@if ($sub)<x-ayuda lado="bottom">{!! $sub !!}</x-ayuda>@endif</h1>
    </div>

    @if ($accion || $acciones)
        <div class="spg-head-acciones">
            @foreach ($acciones as $a)
                @if ($url = Navegacion::url($a['ruta']))
                    <a class="btn btn-outline-neutro" href="{{ $url }}">
                        @if (! empty($a['ic']))<i class="bi bi-{{ $a['ic'] }}"></i>@endif {{ $a['t'] }}</a>
                @endif
            @endforeach

            {{-- **La acción puede abrir un modal en vez de navegar.** Hay altas
                 de una sola pantalla —un proveedor, una categoría— donde irse a
                 otra vista para cargar cinco campos y volver hace perder el
                 lugar en la lista. Se declara con `'modal' => '#idDelModal'` en
                 lugar de `'ruta'`. --}}
            @if ($accion && ! empty($accion['modal']))
                <button type="button" class="btn btn-oro"
                        data-bs-toggle="modal" data-bs-target="{{ $accion['modal'] }}">
                    @if (! empty($accion['ic']))<i class="bi bi-{{ $accion['ic'] }}"></i>@endif {{ $accion['t'] }}</button>
            @endif

            {{-- `q` son los parámetros que el botón arrastra: la ficha del
                 equipo los usa para saber si se entró por Usuarios o por
                 Personal, que abren la misma pantalla en pestañas distintas. --}}
            @if ($accion && empty($accion['modal']) && ($url = Navegacion::url($accion['ruta'])))
                @php
                    if (! empty($accion['q'])) {
                        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($accion['q']);
                    }
                @endphp
                <a class="btn btn-oro" href="{{ $url }}">
                    @if (! empty($accion['ic']))<i class="bi bi-{{ $accion['ic'] }}"></i>@endif {{ $accion['t'] }}</a>
            @endif
        </div>
    @endif
</div>
