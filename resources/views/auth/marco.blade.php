{{-- Marco de las pantallas de acceso (registro, verificación, recuperación).

     No usan el layout general a propósito: acá todavía no se sabe quién está
     del otro lado, así que no hay barra de módulos, ni migas, ni pie con
     secciones. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo') · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ recurso('css/app.css') }}" rel="stylesheet">
</head>
<body>
<div class="spg-login-wrap">
    @foreach (session('spg_flash', []) as $f)
        @php $cls = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'][$f['tipo']] ?? 'secondary'; @endphp
        <div class="alert alert-{{ $cls }}" style="font-size:.85rem">{{ $f['msg'] }}</div>
    @endforeach

    @yield('formulario')
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
{{-- Acá también hace falta: el registro y la recuperación mandan un correo
     antes de contestar, y esa espera —con el formulario ya enviado y la
     pantalla quieta— es justo donde la persona vuelve a apretar el botón. --}}
<script src="{{ recurso('js/app.js') }}"></script>
{{-- Sin esto, todo lo que una pantalla mande con @push('scripts') se pierde en
     silencio: la vista se dibuja entera pero sin su JavaScript. Pasó con la
     pregunta de la huella, donde los dos botones quedaban sin nada detrás y el
     usuario no podía salir de la pantalla. --}}
@stack('scripts')
</body>
</html>
