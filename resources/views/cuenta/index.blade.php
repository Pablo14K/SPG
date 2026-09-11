@extends('layout.app')

@section('titulo', 'Mi cuenta')

@section('contenido')
    <div class="sgp-page-head">
        <h1>Mi cuenta<x-ayuda lado="bottom">Tus datos, tu contraseña, el ingreso con huella y cómo se ve el sistema.</x-ayuda></h1>
    </div>

    @if ($pendiente)
        <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>Tenés un cambio de contraseña esperando confirmación.</span>
            <a class="btn btn-sm btn-oro" href="{{ route('cuenta.password_confirmar') }}">Confirmarlo</a>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="sgp-panel">
                <h2 class="sgp-form-titulo mb-2"><i class="bi bi-person"></i> Tus datos</h2>

                {{-- **La foto de perfil, arriba de sus datos.** Es de la PERSONA
                     (`persona.foto`), así que es la misma cara la vea quien la
                     vea; la cambia cada uno para sí mismo, porque no hay ninguna
                     decisión del salón en juego.

                     **Sin foto van las iniciales, no un monigote genérico**: un
                     avatar igual para todos no distingue a nadie, que es lo único
                     que un avatar tiene que hacer. --}}
                @php $sgpFoto = \App\Servicios\Imagen::url($perfil->foto ?? null, 'personas'); @endphp
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="sgp-avatar sgp-avatar-lg {{ $sgpFoto ? 'tiene-img' : '' }}">
                        @if ($sgpFoto)
                            <img src="{{ $sgpFoto }}" alt="Tu foto de perfil">
                        @else
                            {{ \App\Servicios\Perfil::inicialesDe($perfil->nombre, (string) $perfil->apellido) }}
                        @endif
                    </span>
                    <div class="flex-grow-1">
                        <form method="post" action="{{ route('cuenta.foto') }}" enctype="multipart/form-data"
                              class="d-flex flex-wrap align-items-center gap-2">
                            @csrf
                            <input type="file" class="form-control form-control-sm" name="foto"
                                   accept="image/png,image/jpeg,image/webp" style="max-width:230px" required>
                            <button class="btn btn-sm btn-oro"><i class="bi bi-upload"></i> Subir</button>
                        </form>
                        <div class="form-text mt-1">PNG, JPG o WEBP, hasta 512 KB.</div>
                        @if ($sgpFoto)
                            <form method="post" action="{{ route('cuenta.foto_quitar') }}" class="mt-1">
                                @csrf
                                <button class="btn btn-sm btn-outline-neutro"
                                        data-confirmar="¿Sacar tu foto y volver a tus iniciales?">
                                    <i class="bi bi-trash"></i> Quitar la foto</button>
                            </form>
                        @endif
                    </div>
                </div>

                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><td class="text-muted-warm">Nombre</td>
                            <td>{{ $perfil->nombre }} {{ $perfil->apellido }}</td></tr>
                        <tr><td class="text-muted-warm">Usuario</td><td>{{ $perfil->username }}</td></tr>
                        <tr><td class="text-muted-warm">Rol</td><td>{{ $perfil->rol }}</td></tr>
                        <tr><td class="text-muted-warm">Email</td><td>{{ $perfil->email ?: '—' }}</td></tr>
                        <tr><td class="text-muted-warm">Teléfono</td><td>{{ $perfil->telefono ?: '—' }}</td></tr>
                        @if ($sucursalActiva)
                            <tr><td class="text-muted-warm">Sucursal</td>
                                <td><strong class="txt-oro">{{ $sucursalActiva }}</strong></td></tr>
                        @endif
                    </tbody>
                </table>
            </div>


            {{-- Tema de la interfaz. Es una preferencia de cada persona, no del
                 salón: dos que comparten la computadora pueden tener uno cada
                 una, porque va atada a la cuenta y no al navegador. --}}
            <div class="sgp-panel mt-3">
                <h2 class="sgp-form-titulo mb-1"><i class="bi bi-circle-half"></i> Apariencia<x-ayuda>El tema oscuro usa los mismos colores del salón, con los fondos al revés. Se aplica en todas las pantallas y queda guardado para la próxima vez.</x-ayuda></h2>

                <form method="post" action="{{ route('cuenta.tema') }}" class="d-flex gap-2 flex-wrap">
                    @csrf
                    @foreach (\App\Servicios\Sesion::TEMAS as $clave => $etiqueta)
                        <button name="tema" value="{{ $clave }}"
                                class="btn {{ $tema === $clave ? 'btn-oro' : 'btn-outline-neutro' }}">
                            <i class="bi bi-{{ $clave === 'oscuro' ? 'moon-stars' : 'sun' }}"></i>
                            {{ $etiqueta }}
                            @if ($tema === $clave)<i class="bi bi-check-lg"></i>@endif
                        </button>
                    @endforeach
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            {{-- **Las alergias, para la clienta, van acá también.** Se reportó
                 que «no aparece un campo de alergias en Mi cuenta»: existía, y
                 estaba en «Mi ficha». Las dos son pantallas legítimas para
                 buscarlo, así que se dibuja en las dos — con el MISMO partial y
                 el mismo POST, porque copiado se desfasa.

                 Va primero de esta columna: es lo único de esta pantalla que
                 puede lastimar a alguien si nadie lo mira. --}}
            @if ($alergias !== null)
                <div class="sgp-panel mb-3">
                    @include('portal._alergias', ['alergias' => $alergias, 'volver' => 'cuenta'])
                </div>
            @endif

            <div class="sgp-panel mb-3">
                <h2 class="sgp-form-titulo mb-1"><i class="bi bi-fingerprint"></i> Ingreso con huella<x-ayuda>Entrar apoyando el dedo, sin escribir la contraseña. La huella no sale de tu equipo: el sistema solo guarda una clave pública para comprobar que sos vos.</x-ayuda></h2>

                <div id="bioEstado" class="mb-2" style="font-size:.85rem">
                    @if ($bioActivo)
                        <span class="badge-estado e-ok">activo para esta cuenta</span>
                    @else
                        <span class="badge-estado e-muted">no activado</span>
                    @endif
                </div>

                <div id="bioAviso" class="alert alert-warning d-none" style="font-size:.85rem"></div>

                <button class="btn btn-rapido" id="btnBioActivar"
                        data-confirmar="Vas a activar la huella para esta cuenta. Si ya tenías una, se reemplaza. ¿Seguimos?">
                    <i class="bi bi-fingerprint"></i> Activar en este equipo</button>
                @if ($bioActivo)
                    <button class="btn btn-outline-neutro" id="btnBioDesactivar"
                            data-confirmar="¿Desactivar el ingreso con huella para esta cuenta?">
                        Desactivar</button>
                @endif
            </div>

            <div class="sgp-panel">
                <h2 class="sgp-form-titulo mb-1"><i class="bi bi-shield-lock"></i> Cambiar la contraseña<x-ayuda>Después de cargarla te mandamos un código al correo para confirmar. Saber la contraseña actual no alcanza: si alguien se sienta en una computadora con tu sesión abierta, el código es lo que le impide dejarte afuera de tu propia cuenta.</x-ayuda></h2>

                @if (! $perfil->email)
                    {{-- Al Administrador no se le dice «pedile al Administrador»:
                         es él, y además es el único que puede cargarlo. Se le da
                         el enlace a su propia ficha en vez de mandarlo a pedirse
                         el favor a sí mismo. --}}
                    <div class="alert alert-warning" style="font-size:.85rem">
                        Tu cuenta no tiene correo cargado, así que no podemos mandarte el código.
                        @if (\App\Servicios\Permisos::esAdmin())
                            <a class="link-oro"
                               href="{{ route('seguridad.usuario_form', (int) session('uid')) }}">Cargalo en tu ficha</a>.
                        @else
                            Pedile al Administrador que te lo cargue.
                        @endif
                    </div>
                @else
                    <form method="post" action="{{ route('cuenta.password') }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label" for="actual">Contraseña actual</label><x-ayuda campo="actual" />
                            <input type="password" class="form-control" id="actual" name="actual" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="nueva">Contraseña nueva</label><x-ayuda campo="nueva" />
                            <input type="password" class="form-control" id="nueva" name="nueva" required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="nueva2">Repetila</label><x-ayuda campo="nueva2" />
                            <input type="password" class="form-control" id="nueva2" name="nueva2" required minlength="6">
                        </div>
                        <button class="btn btn-oro"><i class="bi bi-envelope"></i> Mandarme el código</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ recurso('js/webauthn.js') }}"></script>
<script>
(function () {
    var csrf = @json(csrf_token());
    var aviso = document.getElementById('bioAviso');
    var urls = {
        options: @json(route('webauthn.reg_options')),
        verify:  @json(route('webauthn.registrar'))
    };

    function decir(txt) { aviso.textContent = txt; aviso.classList.remove('d-none'); }

    SGPBio.estado().then(function (e) {
        if (!e.ok) {
            document.getElementById('btnBioActivar').disabled = true;
            decir(SGPBio.motivoTexto(e.motivo));
        }
    });

    document.getElementById('btnBioActivar').addEventListener('click', function () {
        decir('Seguí las indicaciones del sistema…');
        SGPBio.register(urls, csrf).then(function (res) {
            if (!res.ok) { decir(res.error || 'No se pudo activar.'); return; }
            SGPBio.recordar(res.username, res.email);
            // **Cada cuenta tiene la suya.** Activarla acá no toca la de nadie
            // más: la credencial apunta a esta cuenta, y al entrar el navegador
            // ofrece las guardadas y se entra a la que registró la elegida.
            window.location.reload();
        }).catch(function () {
            decir('No se pudo activar la huella. Podés seguir entrando con tu contraseña.');
        });
    });

    var btnOff = document.getElementById('btnBioDesactivar');
    if (btnOff) {
        btnOff.addEventListener('click', function () {
            fetch(@json(route('webauthn.desactivar')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_token=' + encodeURIComponent(csrf)
            }).then(function () {
                // Este navegador deja de ofrecer la huella en el ingreso
                SGPBio.olvidar();
                window.location.reload();
            });
        });
    }
})();
</script>
@endpush
