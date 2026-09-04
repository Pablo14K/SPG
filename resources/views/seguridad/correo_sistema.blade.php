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
                <label class="form-label" for="mail_usuario">Cuenta de correo (Gmail)</label><x-ayuda>Es la que Gmail autentica para poder enviar.</x-ayuda>
                <input type="email" class="form-control" id="mail_usuario" name="mail_usuario"
                       value="{{ old('mail_usuario', $personalizado ? $usuarioActual : '') }}"
                       placeholder="peluqueria.avisos@gmail.com" autocomplete="off">
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
                <x-ayuda>La dirección que ve la clienta. Vacío = la misma cuenta. Tiene que ser del mismo dominio.</x-ayuda>
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

    {{-- **Esta cuenta cubre TODO, incluida la factura electrónica.**
         Las dos cosas saben mandar el comprobante —el SPG y el Automatizador
         SIFEN, y las dos adjuntan el KuDE y el XML— pero cada una lo haría con
         su propia cuenta, y con las dos prendidas la clienta lo recibe dos
         veces desde direcciones distintas. Por eso el que manda es uno solo: el
         SPG, que es el que tiene la cuenta configurable. --}}
    <div class="spg-panel mt-3" style="max-width:640px">
        <h2 class="h6"><i class="bi bi-check2-circle"></i> Qué sale con esta cuenta</h2>
        <ul class="text-muted-warm mb-2" style="font-size:.84rem">
            <li>El <strong>código de verificación</strong> al crear una cuenta.</li>
            <li>La <strong>recuperación de contraseña</strong> y el <strong>segundo factor</strong>.</li>
            <li>Los <strong>recordatorios de cita</strong> y los avisos de reprogramación.</li>
            <li>El <strong>comprobante</strong>, con el <strong>KuDE en PDF y el XML</strong> adjuntos
                cuando la factura se declaró ante la DNIT.</li>
        </ul>
        <p class="text-muted-warm mb-0" style="font-size:.82rem">
            <i class="bi bi-info-circle"></i>
            El Automatizador SIFEN <strong>no manda correos</strong>: genera el comprobante y el
            SPG se baja el PDF y el XML para adjuntarlos. Es a propósito — si los dos mandaran,
            la clienta recibiría lo mismo dos veces desde direcciones distintas, y cambiar la
            cuenta acá arreglaría sólo la mitad. Si alguna vez vuelve a mandar, el sistema lo
            avisa al emitir.
        </p>
    </div>
@endsection
