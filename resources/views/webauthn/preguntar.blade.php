@extends('auth.marco')

@section('titulo', 'Activar la huella')

@section('formulario')
    <div class="spg-login" style="text-align:center">
        <div class="logo-big"><i class="bi bi-fingerprint"></i></div>
        <h1 style="font-size:1.15rem;font-weight:500;margin-bottom:.4rem">¿Entrar con tu huella?</h1>
        <p class="text-muted-warm" style="font-size:.88rem;margin-bottom:1.2rem">
            La próxima vez podés entrar apoyando el dedo, sin escribir la contraseña.
            La huella <strong>no sale de este equipo</strong>: el sistema solo guarda una clave
            pública para comprobar que sos vos.
        </p>

        <div id="bioAviso" class="alert alert-warning d-none" style="font-size:.85rem"></div>

        <div class="d-flex gap-2 flex-column">
            <button class="btn btn-oro py-2" id="btnActivar">
                <i class="bi bi-fingerprint"></i> Activar en este equipo</button>
            <button class="btn btn-outline-neutro" id="btnAhoraNo">Ahora no</button>
        </div>

        <p class="text-muted-warm mt-3 mb-0" style="font-size:.78rem">
            Podés activarla o desactivarla cuando quieras desde <strong>Mi cuenta</strong>.
        </p>
    </div>
@endsection

@push('scripts')
<script src="{{ recurso('js/webauthn.js') }}"></script>
<script>
(function () {
    var csrf = @json(csrf_token());
    var home = @json(route(\App\Servicios\Sesion::inicio()));
    var urls = {
        options: @json(route('webauthn.reg_options')),
        verify:  @json(route('webauthn.registrar'))
    };
    var aviso = document.getElementById('bioAviso');

    function decir(txt) { aviso.textContent = txt; aviso.classList.remove('d-none'); }

    // Si acá no se puede usar la huella, no tiene sentido ofrecerla — pero hay
    // que decir POR QUÉ: no es lo mismo un equipo sin lector que una conexión
    // sin HTTPS, y confundirlos manda a revisar lo que no es.
    SPGBio.estado().then(function (e) {
        if (!e.ok) {
            document.getElementById('btnActivar').disabled = true;
            decir(SPGBio.motivoTexto(e.motivo));
        }
    });

    document.getElementById('btnActivar').addEventListener('click', function () {
        decir('Seguí las indicaciones del sistema…');
        SPGBio.register(urls, csrf).then(function (res) {
            if (!res.ok) { decir(res.error || 'No se pudo activar.'); return; }
            // Se recuerda en ESTE navegador para ofrecerle la huella al entrar
            SPGBio.recordar(res.username, res.email);
            window.location.href = home;
        }).catch(function (e) {
            decir('No se pudo activar la huella. Podés seguir entrando con tu contraseña.');
        });
    });

    document.getElementById('btnAhoraNo').addEventListener('click', function () {
        fetch(@json(route('webauthn.preguntado')), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: '_token=' + encodeURIComponent(csrf)
        }).finally(function () { window.location.href = home; });
    });
})();
</script>
@endpush
