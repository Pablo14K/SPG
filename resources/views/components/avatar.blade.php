{{--
    La cara de una persona al lado de su nombre, en una tabla.

    Es el mismo `.sgp-avatar` de la barra —la foto, o las iniciales sobre el
    oro— envuelto en un componente porque lo piden **tres listas distintas**
    (Clientes, Profesionales y Usuarios) y escrito tres veces terminan dibujando
    tres cosas parecidas: una con iniciales, otra con un monigote, otra sin nada.

        <x-avatar :foto="$c->foto" :nombre="$c->nombre" :apellido="$c->apellido" />

    **Sin foto van las INICIALES, no un ícono genérico.** Un avatar igual para
    todos no distingue a nadie, que es lo único que un avatar tiene que hacer.
    Es la misma decisión de la 7.112.0, acá aplicada a la fila.
--}}
@props(['foto' => null, 'nombre' => '', 'apellido' => '', 'alt' => null])

@php
    $url = \App\Servicios\Perfil::fotoDe($foto);
    $ini = \App\Servicios\Perfil::inicialesDe((string) $nombre, (string) $apellido);
    $quien = trim($nombre . ' ' . $apellido);
@endphp

@if ($url)
    {{-- `loading="lazy"` y no `sync`: son veinticinco caras por página y
         ninguna es la que la persona vino a mirar, al revés que la de la barra. --}}
    <span class="sgp-avatar tiene-img" title="{{ $alt ?? $quien }}">
        <img src="{{ $url }}" alt="" width="26" height="26" loading="lazy"></span>
@else
    <span class="sgp-avatar" title="{{ $alt ?? $quien }}" aria-hidden="true">{{ $ini }}</span>
@endif
