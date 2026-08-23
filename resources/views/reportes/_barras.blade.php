{{-- Un gráfico de barras horizontal, dibujado con dos divs.

     **No hay librería de gráficos y es a propósito**: la misma decisión que ya
     está tomada con el PDF. Una barra proporcional es un `width` en por ciento,
     y traer Chart.js para eso agregaría una dependencia de CDN que además hay
     que mantener al día.

     Parámetros: $filas (lista), $rotulo (fn), $valor (fn), $max, $ancho, $vacio.  --}}
@php
    $anchoRot = $ancho ?? '70px';
    $maximo = max(1, (int) ($max ?? 0));
@endphp
@forelse ($filas as $fila)
    @php
        $v = (int) $valor($fila);
        $pct = round($v * 100 / $maximo);
    @endphp
    <div class="spg-graf-fila">
        <span class="spg-graf-rot" style="width:{{ $anchoRot }}">{{ $rotulo($fila) }}</span>
        <span class="spg-graf-pista"><span class="spg-graf-barra" style="width:{{ $pct }}%"></span></span>
        <span class="spg-graf-val">{{ $v }}</span>
    </div>
@empty
    <p class="spg-sin-datos">{{ $vacio ?? 'Sin datos para el período seleccionado.' }}</p>
@endforelse
