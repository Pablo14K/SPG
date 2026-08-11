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

        {{-- «Ahora no» es un envío de formulario de verdad, no un botón atado al
             JavaScript: es la única salida de esta pantalla y tiene que andar
             aunque el JS no cargue. Activar sí necesita JS —la huella se pide
             con la API del navegador—, pero si eso falla la persona igual puede
             seguir de largo. --}}
        <div class="d-flex gap-2 flex-column">
            <button class="btn btn-oro py-2" id="btnActivar">
                <i class="bi bi-fingerprint"></i> Activar en este equipo</button>
            <form method="post" action="{{ route('webauthn.preguntado') }}">
                @csrf
                <button class="btn btn-outline-neutro w-100" id="btnAhoraNo">Ahora no</button>
            </form>
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

    // «Ahora no» no lleva JavaScript: lo resuelve el formulario. Atarlo también
    // acá haría dos cosas por un clic —el envío y un fetch— y una cancelaría a
    // la otra.
})();
</script>
@endpush
