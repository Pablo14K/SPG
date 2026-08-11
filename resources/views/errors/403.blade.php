{{--
    Sin permiso.

    Mantiene la identidad del sistema y ofrece una salida a un lugar seguro:
    una pantalla de error que deja a la persona sin a dónde ir es peor que el
    error en sí. El mensaje explica qué le falta, no un «acceso denegado» seco.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sin permiso · {{ config('app.name') }}</title>
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
        @endphp
        <a class="btn btn-oro" href="{{ Route::has($destino) ? route($destino) : url('/') }}">Volver al inicio</a>
    </div>
</div>
</body>
</html>
