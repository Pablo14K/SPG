@extends('layout.app')

@section('titulo', 'Mi cuenta')

@section('contenido')
    <div class="spg-page-head">
        <h1>Mi cuenta</h1>
        <div class="sub">Tus datos y tu contraseña.</div>
    </div>

    @if ($pendiente)
        <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>Tenés un cambio de contraseña esperando confirmación.</span>
            <a class="btn btn-sm btn-oro" href="{{ route('cuenta.password_confirmar') }}">Confirmarlo</a>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="spg-panel">
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-person"></i> Tus datos</h2>
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

            {{-- Cambiar de local sin volver a entrar. Sólo aparece si la
                 persona tiene más de una asignada: con una sola no hay nada
                 que elegir, y un selector de una opción es ruido. --}}
            @if (count($misSucursales) > 1)
                <div class="spg-panel mt-3">
                    <h2 class="spg-form-titulo mb-1"><i class="bi bi-shop"></i> Sucursal</h2>
                    <p class="text-muted-warm mb-3" style="font-size:.82rem">
                        La agenda, la caja y el stock que ves son los de este local. Al cambiar,
                        cambia todo el sistema — no hace falta cerrar sesión.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($misSucursales as $s)
                            <form method="post" action="{{ route('sucursal.entrar') }}">
                                @csrf
                                <input type="hidden" name="id_sucursal" value="{{ $s->id_sucursal }}">
                                <button class="btn btn-sm {{ (int) $s->id_sucursal === $idSucursalActiva ? 'btn-oro' : 'btn-rapido' }}"
                                        @disabled((int) $s->id_sucursal === $idSucursalActiva)>
                                    <i class="bi bi-shop"></i> {{ $s->nombre }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Tema de la interfaz. Es una preferencia de cada persona, no del
                 salón: dos que comparten la computadora pueden tener uno cada
                 una, porque va atada a la cuenta y no al navegador. --}}
            <div class="spg-panel mt-3">
                <h2 class="spg-form-titulo mb-1"><i class="bi bi-circle-half"></i> Apariencia</h2>
                <p class="text-muted-warm mb-3" style="font-size:.82rem">
                    El tema oscuro usa los mismos colores del salón, con los fondos al revés.
                    Se aplica en todas las pantallas y queda guardado para la próxima vez.
                </p>

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
            <div class="spg-panel mb-3">
                <h2 class="spg-form-titulo mb-1"><i class="bi bi-fingerprint"></i> Ingreso con huella</h2>
                <p class="text-muted-warm mb-2" style="font-size:.82rem">
                    Entrar apoyando el dedo, sin escribir la contraseña. La huella
                    <strong>no sale de tu equipo</strong>: el sistema solo guarda una clave pública
                    para comprobar que sos vos.
                </p>

                <div id="bioEstado" class="mb-2" style="font-size:.85rem">
                    @if ($bioActivo)
                        <span class="badge-estado e-ok">activo en {{ $bioActivo }} dispositivo(s)</span>
                    @else
                        <span class="badge-estado e-muted">no activado</span>
                    @endif
                </div>

                <div id="bioAviso" class="alert alert-warning d-none" style="font-size:.85rem"></div>

                <button class="btn btn-rapido" id="btnBioActivar"
                        data-confirmar="La huella pertenece a una cuenta por vez: si otra la tiene activada, se le va a desactivar. ¿Seguimos?">
                    <i class="bi bi-fingerprint"></i> Activar en este equipo</button>
                @if ($bioActivo)
                    <button class="btn btn-outline-neutro" id="btnBioDesactivar"
                            data-confirmar="¿Desactivar el ingreso con huella en todos tus dispositivos?">
                        Desactivar</button>
                @endif
            </div>

            <div class="spg-panel">
                <h2 class="spg-form-titulo mb-1"><i class="bi bi-shield-lock"></i> Cambiar la contraseña</h2>
                <p class="text-muted-warm mb-3" style="font-size:.82rem">
                    Después de cargarla te mandamos un <strong>código al correo</strong> para confirmar.
                    Saber la contraseña actual no alcanza: si alguien se sienta en una computadora con
                    tu sesión abierta, el código es lo que le impide dejarte afuera de tu propia cuenta.
                </p>

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
                            <label class="form-label" for="actual">Contraseña actual</label>
                            <input type="password" class="form-control" id="actual" name="actual" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="nueva">Contraseña nueva</label>
                            <input type="password" class="form-control" id="nueva" name="nueva" required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="nueva2">Repetila</label>
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

    SPGBio.estado().then(function (e) {
        if (!e.ok) {
            document.getElementById('btnBioActivar').disabled = true;
            decir(SPGBio.motivoTexto(e.motivo));
        }
    });

    document.getElementById('btnBioActivar').addEventListener('click', function () {
        decir('Seguí las indicaciones del sistema…');
        SPGBio.register(urls, csrf).then(function (res) {
            if (!res.ok) { decir(res.error || 'No se pudo activar.'); return; }
            SPGBio.recordar(res.username, res.email);
            // **Si se le sacó a otra cuenta, se dice.** La huella pertenece a una
            // cuenta por vez, así que activarla acá desactiva la de quien la
            // tenía — y esa persona se encontraría con que dejó de andar sin
            // haber tocado nada. Va antes de recargar, con el aviso puesto para
            // que quede a la vista.
            var q = res.desactivadas || [];
            if (q.length) {
                sessionStorage.setItem('spg_bio_quito', q.join(', '));
            }
            window.location.reload();
        }).catch(function () {
            decir('No se pudo activar la huella. Podés seguir entrando con tu contraseña.');
        });
    });

    // El aviso de la recarga anterior, si hubo.
    try {
        var quito = sessionStorage.getItem('spg_bio_quito');
        if (quito) {
            sessionStorage.removeItem('spg_bio_quito');
            decir('Listo. Se desactivó la huella de ' + quito
                + ': pertenece a una cuenta por vez, así que esa persona vuelve a entrar con su contraseña.');
        }
    } catch (e) {}

    var btnOff = document.getElementById('btnBioDesactivar');
    if (btnOff) {
        btnOff.addEventListener('click', function () {
            fetch(@json(route('webauthn.desactivar')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_token=' + encodeURIComponent(csrf)
            }).then(function () {
                // Este navegador deja de ofrecer la huella en el ingreso
                SPGBio.olvidar();
                window.location.reload();
            });
        });
    }
})();
</script>
@endpush
