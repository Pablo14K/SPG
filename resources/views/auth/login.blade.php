@php $spgLogo = \App\Servicios\Config::logo(); @endphp
{{--
    Ingreso al sistema.

    No usa el layout general a propósito: acá no hay barra de módulos, ni
    migas, ni pie con secciones, porque todavía no se sabe quién está del otro
    lado.

    TODO — el panel de ingreso con huella (WebAuthn) se agrega al portar
    webauthn.php, en la tarea de autenticación. El formulario de abajo es el
    camino que siempre tiene que funcionar.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · {{ config('app.name') }}</title>
    @include('layout._favicon')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ recurso('css/app.css') }}" rel="stylesheet">
</head>
<body>
<div class="spg-login-wrap">
    {{-- Los avisos que llegan redirigidos hasta acá. Esta pantalla NO los
         dibujaba —sólo los errores de validación del propio formulario—, así
         que todo lo que mandara a la gente al ingreso se perdía en silencio:
         el «alguien entró a tu cuenta desde otro equipo» no lo veía nadie.
         `auth/marco` sí los dibuja, pero el ingreso no usa ese layout. --}}
    @foreach (session('spg_flash', []) as $f)
        @php $cls = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'][$f['tipo']] ?? 'secondary'; @endphp
        <div class="alert alert-{{ $cls }}" style="font-size:.85rem">{{ $f['msg'] }}</div>
    @endforeach
    {{-- Panel de la huella **con la cuenta recordada**: reemplaza al formulario,
         porque no hay nada que preguntar. Cuando este navegador no recuerda
         ninguna, en su lugar se ofrece el botón chico de abajo del formulario:
         dibujar este panel ahí duplicaría el logo y el título. --}}
    <div class="spg-login" id="bioPanel" style="display:none;text-align:center">
        <div class="logo-big">@if ($spgLogo)<img src="{{ $spgLogo }}" alt="" style="height:100%;width:100%;object-fit:contain">@else<i class="bi bi-scissors"></i>@endif</div>
        <h1 style="font-size:1.2rem;font-weight:500;margin-bottom:.2rem;">{{ config('app.name') }}</h1>
        <p class="text-muted-warm" style="font-size:.85rem;margin-bottom:1.1rem;">Ingresá con tu huella</p>
        <div style="font-size:.95rem;margin-bottom:1rem">
            <i class="bi bi-person-circle"></i> <span id="bioEmail"></span>
        </div>
        <button id="bioBtn" class="btn btn-oro" aria-label="Entrar con huella"
                style="width:74px;height:74px;border-radius:50%;font-size:2rem">
            <i class="bi bi-fingerprint"></i></button>
        <div id="bioMsg" class="text-muted-warm mt-2" style="font-size:.8rem">Tocá para entrar</div>
        <p class="mt-3 mb-0">
            <a href="#" id="usarClave" class="link-oro" style="font-size:.85rem">Usar contraseña</a>
        </p>
    </div>

    <form class="spg-login" id="formLogin" method="post" action="{{ route('login') }}">
        @csrf
        <div class="logo-big">@if ($spgLogo)<img src="{{ $spgLogo }}" alt="" style="height:100%;width:100%;object-fit:contain">@else<i class="bi bi-scissors"></i>@endif</div>
        <h1 class="text-center" style="font-size:1.25rem;font-weight:500;margin-bottom:.2rem;">
            {{ config('app.name') }}
        </h1>
        <p class="text-center text-muted-warm" style="font-size:.85rem;margin-bottom:1.3rem;">
            Sistema de gestión
        </p>

        @if ($errors->any())
            <div class="alert alert-danger py-2" style="font-size:.85rem;">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="mb-3">
            <label class="form-label" for="usuario">Usuario o email</label>
            <input type="text" name="usuario" id="usuario" class="form-control" autofocus required
                   value="{{ old('usuario') }}">
        </div>

        <div class="mb-4">
            <label class="form-label" for="pass">Contraseña</label>
            <div class="input-group">
                <input type="password" name="password" id="pass" class="form-control" required>
                <button class="btn btn-outline-neutro" type="button" id="togglePass" tabindex="-1"
                        aria-label="Mostrar u ocultar contraseña">
                    <i class="bi bi-eye" id="eyeIcon"></i>
                </button>
            </div>
        </div>

        {{-- Sólo aparece cuando el intento anterior chocó con una sesión ya
             abierta. Es la salida para quien cerró el navegador sin salir: la
             marca queda puesta hasta que alguien sale de verdad. --}}
        @if (session('spg_sesion_ocupada'))
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="forzar" value="1" id="forzar">
                <label class="form-check-label" for="forzar" style="font-size:.85rem">
                    Entrar igual y cerrar la sesión del otro equipo
                </label>
            </div>
        @endif

        {{-- **Sin marcarla, la sesión se cierra al cerrar el navegador.** Es
             lo que corresponde en una computadora compartida, y de paso evita
             que la cuenta quede marcada como ocupada por una pestaña que ya no
             existe. --}}
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="recordar" value="1" id="recordar">
            <label class="form-check-label" for="recordar" style="font-size:.85rem">
                Mantener la sesión activa en este dispositivo
            </label>
        </div>

        <button class="btn btn-oro w-100 py-2" type="submit">Ingresar</button>

        <p class="text-center mt-3 mb-1" style="font-size:.85rem;">
            @if (Route::has('recuperar'))
                <a class="link-oro" href="{{ route('recuperar') }}">¿Olvidaste tu contraseña?</a>
            @endif
        </p>
        <p class="text-center mb-0" style="font-size:.85rem;color:var(--gris-oscuro)">
            @if (Route::has('registro'))
                ¿Sos cliente nuevo? <a class="link-oro" href="{{ route('registro') }}">Creá tu cuenta</a>
            @endif
        </p>
    </form>

    {{-- **La huella también sin cuenta recordada.** El botón chico va debajo del
         formulario y no lo reemplaza: puede que en este equipo nadie la haya
         activado, así que la contraseña tiene que quedar a la vista. Lo esconde
         el JS y lo muestra sólo si el equipo tiene sensor — con `app.js` caído no
         se dibuja, y entrar con contraseña sigue andando.

         Quién es la persona lo resuelve el navegador: al tocarlo ofrece las
         huellas guardadas para este sitio, y **se entra a la cuenta que registró
         la que se elija**, porque la credencial apunta a una sola. --}}
    <div class="spg-login mt-3" id="bioSuelto" style="display:none;text-align:center">
        <p class="text-muted-warm mb-2" style="font-size:.85rem">O entrá con tu huella</p>
        <button id="bioBtnSuelto" class="btn btn-oro" type="button" aria-label="Entrar con huella"
                style="width:58px;height:58px;border-radius:50%;font-size:1.5rem">
            <i class="bi bi-fingerprint"></i></button>
    </div>
</div>

{{-- Acá hace falta como en cualquier otra pantalla: validar el usuario, cotejar
     el hash de la contraseña y abrir la sesión toma su tiempo, y hasta la 7.2.1
     esta pantalla era la única que se quedaba muda —no cargaba `app.js`—, justo
     donde la persona vuelve a apretar «Ingresar» porque parece que no pasó nada.
     Va antes de los scripts de abajo para que el bloqueo de doble envío esté
     puesto cuando el formulario se manda. --}}
<script src="{{ recurso('js/app.js') }}"></script>

<script>
    (function () {
        var btn = document.getElementById('togglePass'),
            pass = document.getElementById('pass'),
            icon = document.getElementById('eyeIcon');
        btn.addEventListener('click', function () {
            var ver = pass.type === 'password';
            pass.type = ver ? 'text' : 'password';
            icon.className = ver ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    })();
</script>

<script src="{{ recurso('js/webauthn.js') }}"></script>
<script>
(function () {
    var csrf = @json(csrf_token());
    var urls = {
        options: @json(route('webauthn.auth_options')),
        verify:  @json(route('webauthn.login'))
    };

    var guardado = SPGBio.guardado();
    var panel = document.getElementById('bioPanel'),
        formL = document.getElementById('formLogin'),
        suelto = document.getElementById('bioSuelto'),
        msg = document.getElementById('bioMsg');

    function mostrarClave() { panel.style.display = 'none'; formL.style.display = 'block'; }

    // **Se ofrece siempre que el equipo tenga sensor, no sólo si este
    // navegador recuerda una cuenta.** Antes dependía de `localStorage`, así
    // que en otra computadora, con los datos del sitio borrados o en una
    // ventana privada la huella desaparecía y había que tipear todo otra vez —
    // o sea que servía justo cuando ya no hacía falta.
    //
    // Con una cuenta recordada se la nombra y el panel reemplaza al formulario;
    // sin ninguna, el formulario queda a la vista y la huella se ofrece al lado,
    // porque puede que en este equipo nadie la haya activado. En los dos casos
    // el navegador es el que resuelve de quién es la credencial.
    SPGBio.available().then(function (ok) {
        if (!ok) { mostrarClave(); return; }
        if (guardado && guardado.login) {
            document.getElementById('bioEmail').textContent = guardado.email || guardado.login;
            formL.style.display = 'none';
            panel.style.display = 'block';
        } else {
            suelto.style.display = 'block';
        }
    });

    document.getElementById('usarClave').addEventListener('click', function (e) {
        e.preventDefault(); mostrarClave();
    });

    function entrarConHuella() {
        msg.textContent = 'Esperando tu huella…';
        SPGBio.login(urls, guardado ? guardado.login : '', csrf).then(function (res) {
            if (!res.ok) { throw new Error(res.error || 'No se pudo validar.'); }
            window.location.href = res.redirect;
        }).catch(function (e) {
            // `NotAllowedError` es lo que devuelve el navegador cuando la
            // persona canceló o cuando no hay ninguna credencial guardada acá:
            // son dos cosas distintas y desde el JS no se distinguen, así que el
            // mensaje no promete saber cuál fue.
            msg.textContent = (e && e.message && e.message.indexOf('credenciales') >= 0)
                ? 'En este equipo no hay ninguna huella activada. Entrá con tu contraseña y activala desde Mi cuenta.'
                : 'No se pudo entrar con huella. Probá con tu contraseña.';
            formL.style.display = 'block';
        });
    }

    document.getElementById('bioBtn').addEventListener('click', entrarConHuella);
    document.getElementById('bioBtnSuelto').addEventListener('click', entrarConHuella);
})();
</script>
</body>
</html>
