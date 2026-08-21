{{--
    El campo Ciudad, como combo y no como texto libre.

    **Escribir una ciudad a mano es una fuente de errores sin ninguna ventaja.**
    «Fernando de la Mora», «Fdo. de la Mora» y «fernando de la mora» son la misma
    ciudad escrita de tres formas, y a partir de ahí ningún informe puede
    agruparlas — es el mismo problema que la 7.33.0 resolvió con el catálogo de
    productos, en chico.

    Hasta la 7.49.1 era un `<datalist>`, que **sugiere pero no evita**: hay que
    escribir igual para que filtre, y acepta cualquier cosa que se tipee.

        <x-ciudad :valor="$s->ciudad ?? ''" />
        <x-ciudad name="ciudad" id="sr_ciudad" :valor="''" sm />

    **La opción «Otra» no es un adorno.** La lista es del área metropolitana, y
    un salón que abra en Encarnación tiene que poder cargarla; encerrar el campo
    cambiaría un error de tipeo por uno peor, que es no poder guardar.
--}}
@props(['name' => 'ciudad', 'id' => 'ciudad', 'valor' => '', 'sm' => false])

@php
    $lista = config('spg.ciudades', []);
    $valor = (string) $valor;
    // Una ciudad guardada que no está en la lista —cargada antes, o de otro
    // departamento— tiene que volver a salir tal cual al editar.
    $esOtra = $valor !== '' && ! in_array($valor, $lista, true);
    $chico = $sm ? ' form-select-sm' : '';
@endphp

<select class="form-select{{ $chico }} spg-ciudad" id="{{ $id }}" name="{{ $name }}"
        data-otra="#{{ $id }}Otra">
    <option value="">— Elegí la ciudad —</option>
    @foreach ($lista as $c)
        <option value="{{ $c }}" @selected($valor === $c)>{{ $c }}</option>
    @endforeach
    <option value="__otra" @selected($esOtra)>Otra ciudad…</option>
</select>

{{-- **Arranca visible a propósito.** `app.js` lo esconde cuando el combo no
     está en «Otra»; si el JS no cargó se ven los dos y el campo sigue
     funcionando, que es la regla de siempre: un adorno tiene que poder
     faltar. --}}
<div id="{{ $id }}Otra" class="spg-ciudad-otra mt-2">
    <input class="form-control{{ $sm ? ' form-control-sm' : '' }}" name="{{ $name }}_otra"
           value="{{ $esOtra ? $valor : '' }}" placeholder="¿Cuál? Escribila acá"
           aria-label="Otra ciudad">
</div>
