{{-- Lo que se muestra cuando el período no tiene registros. **No se inventa un
     cero ni se dibuja un gráfico vacío**: un gráfico sin datos se lee como un
     dato, y el salón decide con eso. --}}
<div class="spg-vacio">
    <i class="bi bi-{{ $ic ?? 'inbox' }}"></i>
    <div class="t">Sin datos para el período seleccionado</div>
    <div class="d">{{ $d ?? 'Probá con otro rango de fechas o sacando algún filtro.' }}</div>
</div>
