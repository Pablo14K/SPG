{{--
    Ayuda contextual: un ícono que abre el texto y se cierra al tocar afuera.

    **Para qué existe.** El sistema tenía la explicación siempre a la vista —el
    subtítulo bajo cada título, la línea de ayuda bajo cada campo—, y con quince
    campos en pantalla eso es un párrafo por renglón: quien ya sabe lo que hace
    tiene que leer por encima de todo eso cada vez. La información no se saca, se
    guarda detrás del ícono, que es lo que hace Moodle y lo que se pidió.

        <x-ayuda>Ahí te mandamos el código para activar la cuenta.</x-ayuda>
        <x-ayuda titulo="El timbrado">…</x-ayuda>

    **Se cierra al tocar afuera** porque el disparador es `focus`: Bootstrap lo
    abre al enfocar el botón y lo cierra al perder el foco. Es la única forma de
    los cuatro disparadores que da exactamente el comportamiento pedido — con
    `click` queda abierto hasta que se vuelva a tocar el ícono.

    **Sin Bootstrap sigue siendo legible**, que es la regla de este proyecto: el
    texto va también en `title`, así que el navegador lo muestra al pasar el
    mouse aunque el JavaScript no haya cargado. No es lo mismo, pero no se pierde.

    **Cuándo NO usarlo.** Esto es para lo que *explica*; lo que *advierte* se
    queda a la vista. Un aviso que dice que la seña no se devuelve, o que cambiar
    la cuenta de correo deja al Automatizador con otra, tiene que leerse sin que
    nadie lo busque: esconderlo detrás de un ícono es apagarlo en silencio, que
    es justo lo que este proyecto ya pagó caro una vez.
--}}
@props([
    'titulo' => null,          // encabezado del globo; sin él va sólo el texto
    'etiqueta' => 'Más información',
    'campo' => null,           // toma el texto de `config/ayudas.php`
])

@php
    // **Con `campo` el texto sale del diccionario**, no de la vista. Los mismos
    // campos aparecen en varias pantallas —`nombre` en nueve, `email` en
    // cuatro— y escritos en cada una terminan diciendo cosas distintas del
    // mismo dato. El diccionario es `config/ayudas.php`.
    $texto = $campo ? \App\Servicios\Ayuda::de($campo) : $slot;

    // El slot puede traer saltos de línea y sangría del Blade que lo llama: se
    // normalizan, o el globo sale con huecos raros en el medio de una frase.
    $texto = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $texto)));
@endphp

@if ($texto !== '')
    <button type="button" class="spg-ayuda" aria-label="{{ $etiqueta }}"
            data-bs-toggle="popover" data-bs-trigger="focus"
            data-bs-placement="{{ $attributes->get('lado', 'top') }}"
            @if ($titulo) data-bs-title="{{ $titulo }}" @endif
            data-bs-content="{{ $texto }}"
            title="{{ $texto }}">
        <i class="bi bi-question-circle-fill" aria-hidden="true"></i>
    </button>
@endif
