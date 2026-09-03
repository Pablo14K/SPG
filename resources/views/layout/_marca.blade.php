{{--
    La marca del salón: el logo que cargó, o la tijera de la identidad.

    **Va en un partial porque se dibuja en cinco lugares**, y escrito a mano en
    cada uno se desfasa: pasó exactamente eso — el ingreso ya mostraba el logo y
    el formulario de crear cuenta, la pantalla del enlace de la cita y el pie
    seguían con la tijera fija, así que el salón cargaba su logo y la mitad del
    sistema seguía mostrando el genérico.

    Modos:
      · `grande` — el círculo de las pantallas de acceso (`.logo-big`)
      · `linea`  — al lado de un texto, como en el pie

    Sin logo cargado va la tijera, que es la identidad por defecto del sistema.
--}}
@php
    $spgMarca = \App\Servicios\Config::logo();
    $modo = $modo ?? 'linea';
@endphp
@if ($spgMarca)
    <img src="{{ $spgMarca }}" alt=""
         @if ($modo === 'grande')
             style="height:100%;width:100%;object-fit:contain"
         @else
             style="height:1.1em;width:1.1em;object-fit:contain;vertical-align:-.2em"
         @endif>
@else
    <i class="bi bi-scissors"></i>
@endif
