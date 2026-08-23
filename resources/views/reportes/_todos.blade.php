{{-- **Todo junto, para leerlo de un tirón.**

     Es lo que había antes de partir el módulo, y sigue teniendo su lugar: se
     usa para recorrer el informe entero de arriba abajo, o para llevárselo en
     una sola planilla. Lo que cambió es que ya no es la ÚNICA forma de mirarlo
     — para una pregunta puntual está su pestaña, que carga sólo eso.

     Cada bloque es el mismo partial que dibuja su pestaña, así que no se
     pueden desfasar: lo que se ve acá es exactamente lo que se ve allá. --}}
@php
    $bloques = [
        'citas' => ['Citas', 'calendar-check'],
        'servicios' => ['Servicios', 'scissors'],
        'equipo' => ['Profesionales', 'people'],
        'ingresos' => ['Ingresos', 'cash-coin'],
        'compras' => ['Compras y proveedores', 'truck'],
    ];
    // Por sucursal sólo tiene sentido comparando: con un local elegido —o con
    // uno solo cargado— la tabla sería una fila que repite el resumen.
    if ($sucElegida === '' && count($porSucursal) > 1) {
        $bloques['sucursales'] = ['Por sucursal', 'shop'];
    }
@endphp

@include('reportes._resumen')

@foreach ($bloques as $clave => [$titulo, $ic])
    <div class="spg-todos-sep">
        <h2><i class="bi bi-{{ $ic }}"></i> {{ $titulo }}</h2>
        <a href="{{ route('reportes.index', array_merge(request()->except(['r', 'export']), ['r' => $clave])) }}"
           title="Ver sólo este informe">ver aparte <i class="bi bi-arrow-right"></i></a>
    </div>
    @include('reportes._' . $clave)
@endforeach
