{{--
    La marca del salón: el logo que cargó, o la tijera de la identidad.

    **Va en un partial porque se dibuja en seis lugares**, y escrito a mano en
    cada uno se desfasa: pasó exactamente eso — el ingreso ya mostraba el logo y
    el formulario de crear cuenta, la pantalla del enlace de la cita y el pie
    seguían con la tijera fija, así que el salón cargaba su logo y la mitad del
    sistema seguía mostrando el genérico.

    Modos:
      · `grande` — el círculo de las pantallas de acceso (`.logo-big`)
      · `barra`  — la pastilla de la barra superior (`.spg-logo`)
      · `linea`  — al lado de un texto, como en el pie

    **El contenedor lo dibuja este partial, no la vista.** Es lo que hace
    posible que la clase `tiene-img` se decida en un solo lugar: con logo
    cargado el fondo dorado —que es el del ÍCONO por defecto— se saca, porque
    si no un logo rectangular queda con dos bandas de oro a los costados y se
    ve el ícono de antes asomando de fondo. Se reportó así.

    Sin logo cargado va la tijera sobre el oro, que es la identidad por defecto
    del sistema. **Quitando el logo vuelve sola**, porque la clase depende de
    que haya imagen.
--}}
@php
    $spgMarca = \App\Servicios\Config::logo();
    $modo = $modo ?? 'linea';
    $spgCaja = ['grande' => 'logo-big', 'barra' => 'spg-logo'][$modo] ?? '';
@endphp
@if ($spgCaja !== '')
    <span class="{{ $spgCaja }}{{ $spgMarca ? ' tiene-img' : '' }}">
        @if ($spgMarca)
            <img src="{{ $spgMarca }}" alt="">
        @else
            <i class="bi bi-scissors"></i>
        @endif
    </span>
@elseif ($spgMarca)
    {{-- **Al lado de un texto, el logo conserva su proporción.** Encerrado en
         1,1 em de ancho, un logo apaisado —los que traen el nombre del salón
         adentro lo son casi siempre— quedaba reducido a un puntito ilegible. --}}
    <img src="{{ $spgMarca }}" alt=""
         style="height:1.4em;width:auto;max-width:7em;object-fit:contain;vertical-align:-.35em">
@else
    <i class="bi bi-scissors"></i>
@endif
