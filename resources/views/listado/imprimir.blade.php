{{--
    Cualquier listado del sistema, listo para papel.

    Es UNA sola vista para las doce listas: recibe el título, los encabezados y
    las filas ya armadas por el controlador —las mismas que arma el CSV— así que
    una lista nueva no tiene que escribir su propia pantalla de impresión.

    No usa el layout general a propósito: sin barra de módulos, sin migas y sin
    pie, maquetado para hoja A4. El botón abre el diálogo del navegador, donde se
    elige «Guardar como PDF». No hay librería de PDF: sería una dependencia más
    para hacer lo que el navegador ya hace, y en el VPS la RAM se comparte.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }} · {{ config('app.name') }}</title>
    @include('layout._favicon')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ recurso('css/app.css') }}" rel="stylesheet">
    <link href="{{ recurso('css/imprimir.css') }}" rel="stylesheet" media="print">
</head>
<body class="spg-imprimir">

<div class="container py-3">

    <div class="no-imprimir mb-3 d-flex gap-2">
        <button class="btn btn-oro" onclick="window.print()"><i class="bi bi-printer"></i> Descargar PDF</button>
        {{-- Vuelve a la lista con los filtros puestos: se le sacan sólo los de
             la exportación, para no volver a bajar el archivo al llegar. --}}
        <a class="btn btn-outline-neutro"
           href="{{ url()->current() . '?' . http_build_query(request()->except(['export', 'p'])) }}">Volver</a>
    </div>

    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h1 style="font-size:1.2rem;margin-bottom:.1rem">{{ $emisor->nombre }}</h1>
            <div class="text-muted-warm" style="font-size:.8rem">
                @if ($emisor->ruc ?? null)RUC {{ $emisor->ruc }} · @endif
                {{ $emisor->ciudad ?? '' }}
            </div>
        </div>
        <div class="text-end" style="font-size:.8rem">
            <strong>{{ $titulo }}</strong><br>
            <span class="text-muted-warm">Emitido el {{ $emitido }} por {{ $porQuien }}</span>
        </div>
    </div>

    @if ($filtros)
        {{-- Qué se pidió, escrito en el papel: dos PDF de la misma pantalla con
             filtros distintos son iguales de encabezado si esto no está. --}}
        <div class="mb-2" style="font-size:.78rem">
            <strong>Filtros:</strong> {{ implode(' · ', $filtros) }}
        </div>
    @endif

    <hr>

    <table class="table table-sm">
        <thead>
            <tr>
                @foreach ($encabezados as $h)
                    <th>{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                <tr>
                    @foreach ($fila as $celda)
                        <td>{{ $celda === null || $celda === '' ? '—' : $celda }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ max(1, count($encabezados)) }}">Sin resultados con esos filtros.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="text-muted-warm mt-3" style="font-size:.72rem">
        {{ count($filas) }} {{ count($filas) === 1 ? 'fila' : 'filas' }} ·
        {{ config('app.name') }} · Sistema de gestión v{{ config('spg.version') }}
    </p>
</div>

</body>
</html>
