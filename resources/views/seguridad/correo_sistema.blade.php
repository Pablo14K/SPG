@extends('layout.app')

@section('titulo', 'Correo del sistema')

@section('contenido')
    <x-encabezado sub="La cuenta desde la que salen los avisos: el código de verificación, la recuperación de contraseña, el segundo factor y los recordatorios de cita." />

    <div class="spg-panel" style="max-width:640px">

        {{-- **Qué cuenta manda hoy.** Un correo apagado no se nota —la pantalla
             igual dice «te enviamos un código»—, así que lo primero es decir de
             dónde salen los avisos ahora mismo. --}}
        <div class="alert {{ $personalizado ? 'alert-success' : 'alert-warning' }} py-2" style="font-size:.85rem">
            @if ($personalizado)
                <i class="bi bi-check-circle"></i>
                Ahora los avisos salen desde <strong>{{ $desdeActual }}</strong>,
                cargada acá en el sistema.
            @else
                <i class="bi bi-info-circle"></i>
                Ahora los avisos salen desde <strong>{{ $usuarioActual ?: 'ninguna cuenta' }}</strong>,
                que es la configurada en el servidor. Podés reemplazarla acá sin volver a desplegar.
            @endif
        </div>

        <form method="post" action="{{ route('seguridad.correo_sistema.guardar') }}">
            @csrf

            <p class="text-muted-warm mb-3" style="font-size:.82rem">
                Tiene que ser una cuenta de <strong>Gmail</strong> con una
                <strong>contraseña de aplicación</strong> —no la contraseña normal de la cuenta—.
                Se genera en <em>myaccount.google.com/apppasswords</em> con la verificación en dos
                pasos activada.
            </p>

            <div class="mb-3">
                <label class="form-label" for="mail_usuario">Cuenta de correo (Gmail)</label>
                <input type="email" class="form-control" id="mail_usuario" name="mail_usuario"
                       value="{{ old('mail_usuario', $personalizado ? $usuarioActual : '') }}"
                       placeholder="peluqueria.avisos@gmail.com" autocomplete="off">
                <div class="form-text">Es la que Gmail autentica para poder enviar.</div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="mail_clave">Contraseña de aplicación</label>
                <input type="password" class="form-control" id="mail_clave" name="mail_clave"
                       autocomplete="new-password" placeholder="{{ $tieneClave ? 'Dejala vacía para no cambiarla' : '16 caracteres, sin espacios' }}">
                {{-- **Vacío es «no la cambies».** El campo nunca trae la que hay
                     cargada: mandarla al navegador en cada carga de la pantalla
                     sería regalarla. Es el mismo criterio que la contraseña de
                     una cuenta de usuario. --}}
                <div class="form-text">
                    @if ($tieneClave)
                        Ya hay una guardada. Escribí una nueva sólo si la vas a cambiar.
                    @else
                        Se guarda cifrada; no se muestra de vuelta.
                    @endif
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="mail_desde">
                    Remitente <span class="text-muted-warm">(opcional)</span></label>
                <input type="email" class="form-control" id="mail_desde" name="mail_desde"
                       value="{{ old('mail_desde', $personalizado ? $desdeActual : '') }}"
                       placeholder="Igual que la cuenta" autocomplete="off">
                {{-- Gmail rechaza un remitente de otro dominio, así que si se
                     deja vacío se usa la propia cuenta. --}}
                <div class="form-text">
                    La dirección que ve la clienta. Vacío = la misma cuenta. Tiene que ser del mismo dominio.
                </div>
            </div>

            <div class="d-flex gap-2 align-items-center flex-wrap">
                <button class="btn btn-oro"><i class="bi bi-envelope-check"></i> Guardar la cuenta</button>

                @if ($personalizado)
                    <div class="form-check ms-2">
                        <input class="form-check-input" type="checkbox" value="1" name="restaurar" id="restaurar">
                        <label class="form-check-label text-muted-warm" for="restaurar" style="font-size:.82rem">
                            Volver a la cuenta del servidor (dejá los campos vacíos y marcá esto)
                        </label>
                    </div>
                @endif
            </div>

            <p class="text-muted-warm mt-3 mb-0" style="font-size:.8rem">
                <i class="bi bi-shield-lock"></i>
                Sólo el Administrador puede cambiar esto. La contraseña se guarda cifrada con la
                clave del sistema, así que un respaldo de la base no la deja legible.
            </p>
        </form>
    </div>

    {{-- **Esto NO cambia el remitente de la factura electrónica.**
         Son dos remitentes independientes y es fácil suponer que es uno solo:
         el SPG manda los avisos con la cuenta de arriba, y el Automatizador
         SIFEN manda el KuDE en PDF con la SUYA, que vive en su propio `.env`.
         Callarlo dejaría a la clienta recibiendo la factura desde una cuenta
         que el salón cree haber cambiado. --}}
    <div class="spg-panel mt-3" style="max-width:640px">
        <h2 class="h6"><i class="bi bi-receipt"></i> La factura electrónica se manda aparte</h2>
        <p class="text-muted-warm mb-2" style="font-size:.84rem">
            Lo de arriba vale para el <strong>código de verificación, la recuperación de
            contraseña, el segundo factor, los recordatorios</strong> y el botón «Enviar
            comprobante» de Facturación.
        </p>
        <p class="text-muted-warm mb-0" style="font-size:.84rem">
            El <strong>KuDE en PDF</strong> que se manda al declarar una factura ante la DNIT
            lo envía el <strong>Automatizador SIFEN</strong>, que es otro programa y tiene su
            propia cuenta de correo. <strong>Cambiarla acá no lo toca</strong>: eso se configura
            en el archivo de entorno del Automatizador, en el servidor.
        </p>
    </div>
@endsection
