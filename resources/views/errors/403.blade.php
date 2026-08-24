{{--
    Sin permiso.

    Mantiene la identidad del sistema y ofrece una salida a un lugar seguro:
    una pantalla de error que deja a la persona sin a dónde ir es peor que el
    error en sí. El mensaje explica qué le falta, no un «acceso denegado» seco.

    **Y la salida tiene que llevar a algún lado distinto de acá.** Ver el
    comentario del `$enBucle`: había un caso en que el botón devolvía a esta
    misma pantalla, que es la peor forma de no tener salida — no parece un
    callejón, parece un botón roto.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sin permiso · {{ config('app.name') }}</title>
    @include('layout._favicon')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ recurso('css/app.css') }}" rel="stylesheet">
</head>
<body>
<div class="container" style="max-width:520px;margin-top:12vh">
    <div class="spg-panel text-center">
        <div style="font-size:2.2rem;color:var(--oro)">&#128274;</div>
        <h1 style="font-size:1.15rem;font-weight:500;margin:.6rem 0">Sin permiso</h1>
        <p class="text-muted-warm" style="font-size:.9rem">
            {{ $exception?->getMessage() ?: 'No tenés permiso para acceder a esta sección.' }}
        </p>
        @php
            $destino = session('uid')
                ? (session('es_cliente') ? 'portal.index' : 'panel')
                : 'login';
            $url = Route::has($destino) ? route($destino) : url('/');

            // **«Volver al inicio» puede ser justamente la pantalla que rebotó.**
            // Le pasa a la cuenta de cliente sin ficha vinculada: su inicio es
            // el portal, y el portal es el que le contesta 403 — el botón la
            // devolvía acá mismo, así que desde afuera «no hacía nada».
            //
            // Cuando el destino es esta misma URL no hay ningún lado a donde
            // volver, y la única salida real es cerrar la sesión.
            $enBucle = session('uid') && rtrim($url, '/') === rtrim(url()->current(), '/');
        @endphp

        @unless ($enBucle)
            <a class="btn btn-oro" href="{{ $url }}">Volver al inicio</a>
        @endunless

        @if (session('uid'))
            {{-- Va como POST porque cerrar sesión cambia algo, y un GET lo
                 dispararía cualquier precarga del navegador. --}}
            <form method="post" action="{{ route('salir') }}" class="d-inline">
                @csrf
                <button class="btn {{ $enBucle ? 'btn-oro' : 'btn-outline-neutro' }}">
                    Cerrar sesión</button>
            </form>
        @endif
    </div>
</div>
</body>
</html>
