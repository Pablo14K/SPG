{{-- **Todo junto, para leerlo de un tirón.**

     Es lo que había antes de partir el módulo, y sigue teniendo su lugar: se
     usa para recorrer el informe entero de arriba abajo, o para llevárselo en
     una sola planilla. Para una pregunta puntual siguen estando las secciones
     individuales, que cargan sólo ese detalle. --}}
@php
    $bloques = [
        'citas' => ['Citas', 'calendar-check'],
        'servicios' => ['Servicios', 'scissors'],
        'equipo' => ['Profesionales', 'people'],
        'ingresos' => ['Ingresos', 'cash-coin'],
        'compras' => ['Compras y proveedores', 'truck'],
    ];

    // Por sucursal sólo tiene sentido comparar: con un local elegido —o con
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
