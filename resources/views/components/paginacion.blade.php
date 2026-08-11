{{--
    Pie de listado estándar: cuántos hay y en qué página estamos.

    Lo importante no son los botones, es la frase «Mostrando 1–20 de 137».
    Antes las consultas cortaban con LIMIT 200 sin decir nada: el usuario veía
    200 filas y no tenía forma de saber que había 340. Eso es peor que no
    tener paginación, porque no se nota.

        <x-paginacion :pag="$pag" :f="$f" />
--}}
@props(['pag' => null, 'f' => null])

@if ($pag)
    @php
        $qs = $f ? \App\Servicios\Listado::query($f) : [];
        $enlace = fn (int $p) => url()->current() . '?' . http_build_query(array_merge($qs, ['p' => $p]));

        // Ventana alrededor de la página actual: con 50 páginas no se dibujan
        // 50 botones, se dibujan los vecinos y los extremos.
        $desdeP = max(1, $pag['pagina'] - 2);
        $hastaP = min($pag['paginas'], $pag['pagina'] + 2);
    @endphp

    <div class="spg-paginacion">
        <div class="spg-pag-conteo">
            @if (! $pag['total'])
                Sin resultados{{ $f && $f['activos'] ? ' con esos filtros' : '' }}.
            @else
                Mostrando <strong>{{ $pag['desde'] }}–{{ $pag['hasta'] }}</strong>
                de <strong>{{ $pag['total'] }}</strong>
                {{ $pag['total'] === 1 ? 'registro' : 'registros' }}
                @if ($pag['paginas'] > 1)
                    <span class="text-muted-warm">· página {{ $pag['pagina'] }} de {{ $pag['paginas'] }}</span>
                @endif
            @endif
        </div>

        @if ($pag['paginas'] > 1)
            <nav class="spg-pag-botones" aria-label="Páginas">
                @if ($pag['pagina'] <= 1)
                    <span class="spg-pag inactivo" aria-disabled="true"><i class="bi bi-chevron-left"></i></span>
                @else
                    <a class="spg-pag" href="{{ $enlace($pag['pagina'] - 1) }}" title="Anterior">
                        <i class="bi bi-chevron-left"></i></a>
                @endif

                @if ($desdeP > 1)
                    <a class="spg-pag" href="{{ $enlace(1) }}">1</a>
                    @if ($desdeP > 2)<span class="spg-pag-puntos">…</span>@endif
                @endif

                @for ($i = $desdeP; $i <= $hastaP; $i++)
                    @if ($i === $pag['pagina'])
                        <span class="spg-pag activo" aria-current="page">{{ $i }}</span>
                    @else
                        <a class="spg-pag" href="{{ $enlace($i) }}">{{ $i }}</a>
                    @endif
                @endfor

                @if ($hastaP < $pag['paginas'])
                    @if ($hastaP < $pag['paginas'] - 1)<span class="spg-pag-puntos">…</span>@endif
                    <a class="spg-pag" href="{{ $enlace($pag['paginas']) }}">{{ $pag['paginas'] }}</a>
                @endif

                @if ($pag['pagina'] >= $pag['paginas'])
                    <span class="spg-pag inactivo" aria-disabled="true"><i class="bi bi-chevron-right"></i></span>
                @else
                    <a class="spg-pag" href="{{ $enlace($pag['pagina'] + 1) }}" title="Siguiente">
                        <i class="bi bi-chevron-right"></i></a>
                @endif
            </nav>
        @endif
    </div>
@endif
